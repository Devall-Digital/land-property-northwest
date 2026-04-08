<?php
/**
 * Subscriber dashboard.
 *
 * Handles the frontend dashboard pages for logged-in subscribers
 * (alert feed, preferences, saved properties, account).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Dashboard {

	public static function init(): void {
		add_shortcode( 'lpnw_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_shortcode( 'lpnw_preferences', array( __CLASS__, 'render_preferences' ) );
		add_shortcode( 'lpnw_saved_properties', array( __CLASS__, 'render_saved' ) );
		add_shortcode( 'lpnw_email_preview', array( __CLASS__, 'render_email_preview' ) );
	}

	/**
	 * [lpnw_dashboard] - Main subscriber dashboard.
	 */
	public static function render_dashboard(): string {
		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		$user_id = get_current_user_id();
		$tier    = LPNW_Subscriber::get_tier( $user_id );
		$prefs   = LPNW_Subscriber::get_preferences( $user_id );
		if ( ! $prefs && class_exists( 'LPNW_Onboarding' ) ) {
			LPNW_Onboarding::bootstrap_default_preferences( $user_id );
			$prefs = LPNW_Subscriber::get_preferences( $user_id );
		}

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/dashboard.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_preferences] - Alert preferences form.
	 */
	public static function render_preferences(): string {
		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		$user_id = get_current_user_id();
		$prefs   = LPNW_Subscriber::get_preferences( $user_id );
		if ( ! $prefs && class_exists( 'LPNW_Onboarding' ) ) {
			LPNW_Onboarding::bootstrap_default_preferences( $user_id );
			$prefs = LPNW_Subscriber::get_preferences( $user_id );
		}
		$tier = LPNW_Subscriber::get_tier( $user_id );

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/preferences.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_saved_properties] - User's saved/bookmarked properties.
	 */
	public static function render_saved(): string {
		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		global $wpdb;
		$user_id = get_current_user_id();

		$saved = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sp.*, p.address, p.postcode, p.price, p.source, p.property_type, p.application_type, p.source_url, p.description, p.raw_data, p.bedrooms, p.bathrooms, p.tenure_type, p.agent_name, p.first_listed_date, p.created_at, p.key_features_text
			 FROM {$wpdb->prefix}lpnw_saved_properties sp
			 INNER JOIN {$wpdb->prefix}lpnw_properties p ON p.id = sp.property_id
			 WHERE sp.user_id = %d
			 ORDER BY sp.saved_at DESC",
				$user_id
			)
		);

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/saved-properties.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_email_preview] - Preview alert email HTML using real matching properties.
	 */
	public static function render_email_preview(): string {
		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$tier    = LPNW_Subscriber::get_tier( $user_id );
		$prefs   = LPNW_Subscriber::get_preferences( $user_id );

		$subscriber_row = self::subscriber_row_for_matcher( $prefs );
		$matcher        = new LPNW_Matcher();
		$matching       = array();

		global $wpdb;
		$property_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}lpnw_properties ORDER BY updated_at DESC, id DESC LIMIT %d",
				400
			)
		);

		if ( is_array( $property_ids ) ) {
			foreach ( $property_ids as $pid ) {
				$property = LPNW_Property::get( (int) $pid );
				if ( ! $property ) {
					continue;
				}
				if ( $matcher->property_matches_subscriber( $property, $subscriber_row ) ) {
					$matching[] = $property;
					if ( count( $matching ) >= 5 ) {
						break;
					}
				}
			}
		}

		$frequency  = LPNW_Dispatcher::get_effective_alert_frequency( $tier, $prefs );
		$freq_label = self::frequency_layout_label( $frequency );

		$email_preview_body_html = '';
		if ( ! empty( $matching ) ) {
			$email_preview_body_html = LPNW_Dispatcher::build_alert_email_html( $user, $matching, $frequency );
		}

		$email_preview_matching   = $matching;
		$email_preview_freq_label = $freq_label;

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/email-preview.php';
		return ob_get_clean();
	}

	/**
	 * Build a subscriber-shaped object for {@see LPNW_Matcher::property_matches_subscriber()}
	 * from decoded preferences (JSON fields as strings).
	 *
	 * @param object|null $prefs From {@see LPNW_Subscriber::get_preferences()}.
	 */
	private static function subscriber_row_for_matcher( ?object $prefs ): object {
		$areas  = array();
		$types  = array();
		$alerts = array();
		$min    = null;
		$max    = null;

		if ( $prefs ) {
			$areas  = is_array( $prefs->areas ?? null ) ? $prefs->areas : array();
			$types  = is_array( $prefs->property_types ?? null ) ? $prefs->property_types : array();
			$alerts = is_array( $prefs->alert_types ?? null ) ? $prefs->alert_types : array();
			$min    = isset( $prefs->min_price ) ? $prefs->min_price : null;
			$max    = isset( $prefs->max_price ) ? $prefs->max_price : null;
		}

		$listing_ch = array();
		$tenure_p   = array();
		$req_feat   = array();
		$min_bed    = null;
		$max_bed    = null;
		if ( $prefs ) {
			$listing_ch = is_array( $prefs->listing_channels ?? null ) ? $prefs->listing_channels : array();
			$tenure_p   = is_array( $prefs->tenure_preferences ?? null ) ? $prefs->tenure_preferences : array();
			$req_feat   = is_array( $prefs->required_features ?? null ) ? $prefs->required_features : array();
			$min_bed    = isset( $prefs->min_bedrooms ) ? $prefs->min_bedrooms : null;
			$max_bed    = isset( $prefs->max_bedrooms ) ? $prefs->max_bedrooms : null;
		}

		return (object) array(
			'user_id'            => get_current_user_id(),
			'areas'              => wp_json_encode( $areas ),
			'property_types'     => wp_json_encode( $types ),
			'alert_types'        => wp_json_encode( $alerts ),
			'min_price'          => $min,
			'max_price'          => $max,
			'listing_channels'   => wp_json_encode( $listing_ch ),
			'tenure_preferences' => wp_json_encode( $tenure_p ),
			'required_features'  => wp_json_encode( $req_feat ),
			'min_bedrooms'       => $min_bed,
			'max_bedrooms'       => $max_bed,
		);
	}

	/**
	 * Short label for the email template layout.
	 */
	private static function frequency_layout_label( string $frequency ): string {
		switch ( $frequency ) {
			case 'instant':
				return __( 'instant alert', 'lpnw-alerts' );
			case 'daily':
				return __( 'daily digest', 'lpnw-alerts' );
			case 'weekly':
			default:
				return __( 'weekly digest', 'lpnw-alerts' );
		}
	}

	private static function login_prompt(): string {
		return sprintf(
			'<div class="lpnw-login-prompt"><p>Please <a href="%s">log in</a> to access your dashboard.</p></div>',
			esc_url( wp_login_url( get_permalink() ) )
		);
	}
}
