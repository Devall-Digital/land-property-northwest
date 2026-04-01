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
		$tier    = LPNW_Subscriber::get_tier( $user_id );

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

		$saved = $wpdb->get_results( $wpdb->prepare(
			"SELECT sp.*, p.address, p.postcode, p.price, p.source, p.property_type, p.application_type, p.source_url, p.description, p.raw_data
			 FROM {$wpdb->prefix}lpnw_saved_properties sp
			 INNER JOIN {$wpdb->prefix}lpnw_properties p ON p.id = sp.property_id
			 WHERE sp.user_id = %d
			 ORDER BY sp.saved_at DESC",
			$user_id
		) );

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/saved-properties.php';
		return ob_get_clean();
	}

	private static function login_prompt(): string {
		return sprintf(
			'<div class="lpnw-login-prompt"><p>Please <a href="%s">log in</a> to access your dashboard.</p></div>',
			esc_url( wp_login_url( get_permalink() ) )
		);
	}
}
