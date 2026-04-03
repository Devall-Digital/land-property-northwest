<?php
/**
 * Alert dispatcher.
 *
 * Processes the alert queue and sends emails via Mautic or wp_mail fallback.
 * Respects tier priority: VIP first, then Pro, then batches for Free.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Dispatcher {

	private LPNW_Mautic $mautic;
	private int $batch_size = 50;

	public function __construct() {
		$this->mautic = new LPNW_Mautic();
	}

	/**
	 * Process queued alerts in tier priority order.
	 */
	public function process_queue(): void {
		$this->process_tier( 'vip' );
		$this->process_tier( 'pro' );
	}

	/**
	 * Send the weekly digest for free-tier subscribers.
	 * Called by a separate weekly cron hook.
	 */
	public function send_free_digest(): void {
		$this->process_tier( 'free' );
	}

	private function process_tier( string $tier ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'lpnw_alert_queue';

		$queued = $wpdb->get_results( $wpdb->prepare(
			"SELECT aq.*, sp.user_id
			 FROM {$table} aq
			 INNER JOIN {$wpdb->prefix}lpnw_subscriber_preferences sp ON sp.id = aq.subscriber_id
			 WHERE aq.tier = %s AND aq.status = 'queued'
			 ORDER BY aq.queued_at ASC
			 LIMIT %d",
			$tier,
			$this->batch_size
		) );

		if ( empty( $queued ) ) {
			return;
		}

		$grouped = array();
		foreach ( $queued as $alert ) {
			$grouped[ $alert->user_id ][] = $alert;
		}

		foreach ( $grouped as $user_id => $alerts ) {
			$this->send_alert_email( (int) $user_id, $alerts, $tier );
		}
	}

	/**
	 * @param int          $user_id WordPress user ID.
	 * @param array<object> $alerts  Alert queue rows.
	 * @param string       $tier    Subscription tier.
	 */
	private function send_alert_email( int $user_id, array $alerts, string $tier ): void {
		global $wpdb;

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$properties = array();
		foreach ( $alerts as $alert ) {
			$property = LPNW_Property::get( (int) $alert->property_id );
			if ( $property ) {
				$properties[] = $property;
			}
		}

		if ( empty( $properties ) ) {
			$ids = array_map( static function ( $a ) {
				return (int) $a->id;
			}, $alerts );
			if ( ! empty( $ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}lpnw_alert_queue SET status = %s, sent_at = NULL WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'failed',
					...$ids
				) );
			}
			return;
		}

		$sent       = false;
		$mautic_tpl = null;

		$prefs               = LPNW_Subscriber::get_preferences( $user_id );
		$effective_frequency = self::get_effective_alert_frequency( $tier, $prefs );
		if ( $this->mautic->is_configured() && $this->mautic->has_email_template_for_tier( $tier ) ) {
			$mautic_tpl = $this->mautic->send_alert_get_template_id(
				$user->user_email,
				$properties,
				$tier
			);
			$sent       = null !== $mautic_tpl;
		}

		if ( ! $sent ) {
			$sent = $this->send_via_wp_mail( $user, $properties, $effective_frequency, $prefs );
		}

		$status = $sent ? 'sent' : 'failed';
		$ids    = array_map( fn( $a ) => (int) $a->id, $alerts );

		if ( ! empty( $ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sent_at_sql  = ( 'sent' === $status ) ? 'NOW()' : 'NULL';
			if ( 'sent' === $status && null !== $mautic_tpl && $mautic_tpl > 0 ) {
				$mid_sql = $wpdb->prepare( '%s', (string) $mautic_tpl );
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}lpnw_alert_queue SET status = %s, sent_at = {$sent_at_sql}, mautic_email_id = {$mid_sql} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$status,
					...$ids
				) );
			} else {
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}lpnw_alert_queue SET status = %s, sent_at = {$sent_at_sql} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$status,
					...$ids
				) );
			}
		}
	}

	/**
	 * @param \WP_User      $user                 WordPress user.
	 * @param array<object> $properties           Properties to include.
	 * @param string        $effective_frequency One of instant, daily, weekly.
	 * @param object|null   $prefs               Subscriber preferences (areas for daily subject).
	 */
	private function send_via_wp_mail( \WP_User $user, array $properties, string $effective_frequency, ?object $prefs = null ): bool {
		$count = count( $properties );

		switch ( $effective_frequency ) {
			case 'instant':
				$subject = self::format_instant_email_subject( $properties[0] );
				break;
			case 'daily':
				$subject = self::format_daily_email_subject( $count, $prefs );
				break;
			case 'weekly':
			default:
				/* translators: %d: number of properties in the weekly digest. */
				$subject = sprintf(
					__( 'This week: %d NW properties you might have missed', 'lpnw-alerts' ),
					$count
				);
				break;
		}

		$preheader = self::build_email_preheader( $properties, $effective_frequency );

		$body = self::build_alert_email_html(
			$user,
			$properties,
			$effective_frequency,
			array(
				'preheader'             => $preheader,
				'subscriber_first_name' => self::get_subscriber_greeting_first_name( $user ),
			)
		);

		$headers = LPNW_Email_Branding::get_alert_mail_headers();

		return wp_mail( $user->user_email, $subject, $body, $headers );
	}

	/**
	 * Build HTML body for alert emails (same templates as wp_mail delivery).
	 *
	 * @param \WP_User      $user                 WordPress user.
	 * @param array<object> $properties           Properties to include.
	 * @param string        $effective_frequency One of instant, daily, weekly.
	 * @param array<string, string> $template_context Optional: preheader, subscriber_first_name.
	 */
	public static function build_alert_email_html( \WP_User $user, array $properties, string $effective_frequency, array $template_context = array() ): string {
		if ( empty( $properties ) ) {
			return '';
		}

		$template = 'email-weekly-digest.html';
		switch ( $effective_frequency ) {
			case 'instant':
				$template = 'email-instant-alert.html';
				break;
			case 'daily':
				$template = 'email-daily-digest.html';
				break;
			case 'weekly':
			default:
				$template = 'email-weekly-digest.html';
				break;
		}

		$template_path = LPNW_PLUGIN_DIR . 'templates/' . $template;

		if ( file_exists( $template_path ) ) {
			ob_start();
			$alert_properties       = $properties;
			$subscriber_first_name  = isset( $template_context['subscriber_first_name'] ) ? (string) $template_context['subscriber_first_name'] : self::get_subscriber_greeting_first_name( $user );
			$email_preheader        = isset( $template_context['preheader'] ) ? (string) $template_context['preheader'] : '';
			$dashboard_url          = home_url( '/dashboard/' );
			$unsubscribe_url        = add_query_arg( 'tab', 'preferences', $dashboard_url );
			include $template_path;
			$html = ob_get_clean();
			return is_string( $html ) ? $html : '';
		}

		return self::build_plain_email( $properties );
	}

	/**
	 * @param array<object> $properties Properties.
	 */
	private static function build_plain_email( array $properties ): string {
		$lines = array( '<h2>Your NW Property Alerts</h2>' );

		foreach ( $properties as $prop ) {
			$lines[] = sprintf(
				'<div style="margin-bottom:16px;padding:12px;border:1px solid #e5e7eb;border-radius:4px;">
					<strong>%s</strong><br>
					%s<br>
					%s%s
					<a href="%s">View details</a>
				</div>',
				esc_html( $prop->address ),
				esc_html( $prop->postcode ),
				$prop->price ? '&pound;' . number_format( (int) $prop->price ) . '<br>' : '',
				esc_html( ucfirst( $prop->source ) ) . ' | ' . esc_html( $prop->property_type ) . '<br>',
				esc_url( $prop->source_url )
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * First name (or single token) from display_name for email greetings.
	 */
	public static function get_subscriber_greeting_first_name( \WP_User $user ): string {
		$raw = trim( (string) $user->display_name );
		if ( '' === $raw ) {
			return 'there';
		}
		$parts = preg_split( '/\s+/u', $raw, 2 );
		$first = isset( $parts[0] ) ? trim( (string) $parts[0] ) : '';
		return '' === $first ? 'there' : $first;
	}

	/**
	 * Map a UK postcode string to an NW bucket code (M, L, CH, …). Empty if not in NW buckets.
	 */
	private static function lpnw_postcode_to_nw_bucket( string $postcode ): string {
		$pc = strtoupper( preg_replace( '/\s+/', '', trim( $postcode ) ) );
		if ( '' === $pc ) {
			return '';
		}
		$two = substr( $pc, 0, 2 );
		$two_letter = array( 'BB', 'BL', 'CA', 'CH', 'CW', 'FY', 'LA', 'OL', 'PR', 'SK', 'WA', 'WN' );
		if ( in_array( $two, $two_letter, true ) ) {
			return $two;
		}
		if ( preg_match( '/^M[0-9]/', $pc ) ) {
			return 'M';
		}
		if ( preg_match( '/^L[0-9]/', $pc ) ) {
			return 'L';
		}
		return '';
	}

	/**
	 * Outward code for subject lines (e.g. M14 from M14 5AB).
	 */
	private static function get_postcode_outward_for_subject( string $postcode ): string {
		$pc = strtoupper( trim( $postcode ) );
		if ( '' === $pc ) {
			return '';
		}
		$parts = preg_split( '/\s+/', $pc, 2 );
		return isset( $parts[0] ) ? $parts[0] : '';
	}

	/**
	 * City and outward postcode for instant alert copy.
	 */
	private static function format_area_segment_for_email( object $prop ): string {
		$labels = LPNW_Property::get_nw_area_labels();
		$bucket = self::lpnw_postcode_to_nw_bucket( (string) ( $prop->postcode ?? '' ) );
		$city   = isset( $labels[ $bucket ] ) ? $labels[ $bucket ] : '';
		$out    = self::get_postcode_outward_for_subject( (string) ( $prop->postcode ?? '' ) );
		if ( '' !== $city && '' !== $out ) {
			return $city . ', ' . $out;
		}
		if ( '' !== $city ) {
			return $city;
		}
		if ( '' !== $out ) {
			return $out;
		}
		return __( 'the Northwest', 'lpnw-alerts' );
	}

	/**
	 * Property type string for subject and preheader.
	 */
	private static function format_property_type_for_subject( object $prop ): string {
		$raw = trim( str_replace( '_', ' ', (string) ( $prop->property_type ?? '' ) ) );
		if ( '' === $raw ) {
			return __( 'property', 'lpnw-alerts' );
		}
		return $raw;
	}

	/**
	 * Instant alert email subject from the first property.
	 */
	private static function format_instant_email_subject( object $prop ): string {
		$type  = self::format_property_type_for_subject( $prop );
		$area  = self::format_area_segment_for_email( $prop );
		$price = '';
		if ( ! empty( $prop->price ) ) {
			$price = ' - GBP ' . number_format( (int) $prop->price );
		}
		/* translators: 1: property type, 2: area (e.g. Manchester, M14), 3: optional price suffix such as " - GBP 225,000". */
		return sprintf( __( 'New %1$s in %2$s%3$s', 'lpnw-alerts' ), $type, $area, $price );
	}

	/**
	 * Daily digest subject from count and saved area preferences.
	 */
	private static function format_daily_email_subject( int $count, ?object $prefs ): string {
		$areas = array();
		if ( $prefs && isset( $prefs->areas ) && is_array( $prefs->areas ) ) {
			$areas = $prefs->areas;
		}
		$labels = LPNW_Property::get_nw_area_labels();
		$names  = array();
		foreach ( $areas as $code ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			if ( isset( $labels[ $code ] ) ) {
				$names[] = $labels[ $code ];
			}
		}
		$names = array_values( array_unique( $names ) );
		sort( $names, SORT_STRING );
		if ( 1 === count( $names ) ) {
			/* translators: 1: number of new listings, 2: area name (e.g. Manchester). */
			return sprintf( __( '%1$d new %2$s listings today', 'lpnw-alerts' ), $count, $names[0] );
		}
		if ( 2 === count( $names ) ) {
			/* translators: 1: number of new listings, 2: first area, 3: second area. */
			return sprintf( __( '%1$d new %2$s/%3$s listings today', 'lpnw-alerts' ), $count, $names[0], $names[1] );
		}
		/* translators: %d: number of new listings. */
		return sprintf( _n( '%d new property in your areas today', '%d new properties in your areas today', $count, 'lpnw-alerts' ), $count );
	}

	/**
	 * Preheader text for HTML alert emails (inbox preview).
	 *
	 * @param array<object> $properties          Properties in the email.
	 * @param string        $effective_frequency instant, daily, or weekly.
	 */
	private static function build_email_preheader( array $properties, string $effective_frequency ): string {
		$count = count( $properties );
		switch ( $effective_frequency ) {
			case 'instant':
				return self::build_preheader_instant( $properties[0] );
			case 'daily':
				return self::build_preheader_daily();
			case 'weekly':
			default:
				return self::build_preheader_weekly( $count );
		}
	}

	/**
	 * Instant alert preheader.
	 */
	private static function build_preheader_instant( object $prop ): string {
		$type = self::format_property_type_for_subject( $prop );
		$area = self::format_area_segment_for_email( $prop );
		$type_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $type, 'UTF-8' ) : strtolower( $type );
		/* translators: 1: property type (lowercase), 2: area. */
		return sprintf( __( 'A new %1$s just listed in %2$s. View details and act fast.', 'lpnw-alerts' ), $type_lower, $area );
	}

	/**
	 * Daily digest preheader.
	 */
	private static function build_preheader_daily(): string {
		$date_str = wp_date( get_option( 'date_format' ) );
		/* translators: %s: formatted date (site timezone). */
		return sprintf( __( 'Your personalised NW property digest for %s.', 'lpnw-alerts' ), $date_str );
	}

	/**
	 * Weekly (free tier) digest preheader.
	 */
	private static function build_preheader_weekly( int $count ): string {
		/* translators: %d: number of properties in the digest. */
		return sprintf( __( '%d opportunities across the Northwest this week. Upgrade for instant alerts.', 'lpnw-alerts' ), $count );
	}

	/**
	 * Resolve alert email frequency from saved preferences, capped by subscription tier.
	 *
	 * Free: weekly only. Pro: daily or instant. VIP: instant or daily (weekly coerced to daily).
	 *
	 * @param string        $tier  Subscription tier.
	 * @param object|null   $prefs Row from LPNW_Subscriber::get_preferences().
	 * @return string One of instant, daily, weekly.
	 */
	public static function get_effective_alert_frequency( string $tier, ?object $prefs ): string {
		$saved = 'daily';
		if ( $prefs && isset( $prefs->frequency ) && is_string( $prefs->frequency ) ) {
			$saved = strtolower( $prefs->frequency );
		}
		$allowed_saved = array( 'instant', 'daily', 'weekly' );
		if ( ! in_array( $saved, $allowed_saved, true ) ) {
			$saved = 'daily';
		}

		if ( 'free' === $tier ) {
			return 'weekly';
		}

		if ( 'vip' === $tier ) {
			if ( 'weekly' === $saved ) {
				$saved = 'daily';
			}
			if ( 'instant' === $saved ) {
				return 'instant';
			}
			return 'daily';
		}

		if ( 'pro' === $tier ) {
			if ( 'instant' === $saved ) {
				return 'instant';
			}
			return 'daily';
		}

		return 'daily';
	}

	/**
	 * Mautic contact fields when creating/updating a contact (flat keys for api/contacts/new).
	 *
	 * @return array<string, string>
	 */
	public static function get_mautic_contact_fields_for_sync( \WP_User $user ): array {
		$first = self::get_subscriber_greeting_first_name( $user );
		$dn    = trim( (string) $user->display_name );
		$last  = '';
		if ( '' !== $dn && preg_match( '/\s/u', $dn ) ) {
			$parts = preg_split( '/\s+/u', $dn, 2 );
			$last  = isset( $parts[1] ) ? trim( (string) $parts[1] ) : '';
		}
		$fields = array(
			'firstname' => ( 'there' === $first ) ? '' : $first,
			'lastname'  => $last,
		);

		return apply_filters( 'lpnw_mautic_contact_sync_fields', $fields, $user );
	}

	/**
	 * Body for POST api/emails/{id}/contact/{id}/send with merge tokens for the Mautic template.
	 *
	 * @param array<object> $properties Rows from LPNW_Property::get().
	 * @return array<string, array<string, string>>
	 */
	public static function get_mautic_send_body_with_tokens( \WP_User $user, array $properties, string $tier ): array {
		$first = self::get_subscriber_greeting_first_name( $user );
		$html  = self::build_plain_email( $properties );

		$tokens = array(
			'{lpnw_subscriber_first_name}' => $first,
			'{lpnw_alert_count}'           => (string) count( $properties ),
			'{lpnw_tier}'                  => strtoupper( $tier ),
			'{lpnw_properties_html}'       => $html,
		);

		$tokens = apply_filters( 'lpnw_mautic_alert_email_tokens', $tokens, $user, $properties, $tier );

		return array( 'tokens' => $tokens );
	}
}
