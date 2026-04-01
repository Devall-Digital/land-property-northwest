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
			return;
		}

		$sent = false;

		$prefs               = LPNW_Subscriber::get_preferences( $user_id );
		$effective_frequency = self::get_effective_alert_frequency( $tier, $prefs );

		if ( $this->mautic->is_configured() && $this->mautic->has_email_template_for_tier( $tier ) ) {
			$sent = $this->mautic->send_alert(
				$user->user_email,
				$properties,
				$tier
			);
		}

		if ( ! $sent ) {
			$sent = $this->send_via_wp_mail( $user, $properties, $effective_frequency );
		}

		$status = $sent ? 'sent' : 'failed';
		$ids    = array_map( fn( $a ) => (int) $a->id, $alerts );

		if ( ! empty( $ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sent_at_sql = ( 'sent' === $status ) ? 'NOW()' : 'NULL';
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}lpnw_alert_queue SET status = %s, sent_at = {$sent_at_sql} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				...$ids
			) );
		}
	}

	/**
	 * @param \WP_User      $user                 WordPress user.
	 * @param array<object> $properties           Properties to include.
	 * @param string        $effective_frequency One of instant, daily, weekly.
	 */
	private function send_via_wp_mail( \WP_User $user, array $properties, string $effective_frequency ): bool {
		$count = count( $properties );

		switch ( $effective_frequency ) {
			case 'instant':
				/* translators: %d: number of new alerts. */
				$subject = sprintf( _n( '%d new NW property alert', '%d new NW property alerts', $count, 'lpnw-alerts' ), $count );
				break;
			case 'daily':
				/* translators: 1: number of matches, 2: "Match" or "Matches". */
				$subject = sprintf(
					'Your Daily NW Property Digest - %1$d New %2$s',
					$count,
					_n( 'Match', 'Matches', $count, 'lpnw-alerts' )
				);
				break;
			case 'weekly':
			default:
				/* translators: 1: number of matches, 2: "Match" or "Matches". */
				$subject = sprintf(
					'Your Weekly NW Property Digest - %1$d New %2$s',
					$count,
					_n( 'Match', 'Matches', $count, 'lpnw-alerts' )
				);
				break;
		}

		$body = self::build_alert_email_html( $user, $properties, $effective_frequency );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $user->user_email, $subject, $body, $headers );
	}

	/**
	 * Build HTML body for alert emails (same templates as wp_mail delivery).
	 *
	 * @param \WP_User      $user                 WordPress user.
	 * @param array<object> $properties           Properties to include.
	 * @param string        $effective_frequency One of instant, daily, weekly.
	 */
	public static function build_alert_email_html( \WP_User $user, array $properties, string $effective_frequency ): string {
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
			$alert_properties = $properties;
			$subscriber_name  = $user->display_name;
			$dashboard_url    = home_url( '/dashboard/' );
			$unsubscribe_url  = add_query_arg( 'tab', 'preferences', $dashboard_url );
			include $template_path;
			return ob_get_clean();
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
	 * Resolve alert email frequency from saved preferences, capped by subscription tier.
	 *
	 * Free: weekly only. Pro: daily or instant. VIP: instant only.
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
			return 'instant';
		}

		if ( 'pro' === $tier ) {
			if ( 'instant' === $saved ) {
				return 'instant';
			}
			return 'daily';
		}

		return 'daily';
	}
}
