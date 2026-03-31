<?php
/**
 * Public-facing functionality.
 *
 * Registers shortcodes, enqueues frontend assets, and handles AJAX endpoints.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Public {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_shortcode( 'lpnw_alert_feed', array( __CLASS__, 'render_alert_feed' ) );
		add_shortcode( 'lpnw_property_count', array( __CLASS__, 'render_property_count' ) );
		add_shortcode( 'lpnw_signup_form', array( __CLASS__, 'render_signup_form' ) );
		add_shortcode( 'lpnw_latest_properties', array( __CLASS__, 'render_latest_properties' ) );

		add_action( 'wp_ajax_lpnw_save_preferences', array( __CLASS__, 'ajax_save_preferences' ) );
		add_action( 'wp_ajax_lpnw_save_property', array( __CLASS__, 'ajax_save_property' ) );
		add_action( 'wp_ajax_lpnw_load_properties', array( __CLASS__, 'ajax_load_properties' ) );
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'lpnw-public',
			LPNW_PLUGIN_URL . 'public/css/lpnw-public.css',
			array(),
			LPNW_VERSION
		);

		wp_enqueue_script(
			'lpnw-public',
			LPNW_PLUGIN_URL . 'public/js/lpnw-public.js',
			array(),
			LPNW_VERSION,
			true
		);

		wp_localize_script( 'lpnw-public', 'lpnwData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'lpnw_public' ),
			'homeUrl' => home_url(),
		) );
	}

	/**
	 * [lpnw_alert_feed] - Displays the user's matched alert feed.
	 */
	public static function render_alert_feed( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your alerts.</p>';
		}

		$atts = shortcode_atts( array( 'limit' => 20 ), $atts );

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/alert-feed.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_property_count] - Shows total properties tracked.
	 */
	public static function render_property_count(): string {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_properties" );
		return '<span class="lpnw-property-count">' . esc_html( number_format( $count ) ) . '</span>';
	}

	/**
	 * [lpnw_signup_form] - Alert signup/preferences form.
	 */
	public static function render_signup_form(): string {
		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/signup-form.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_latest_properties] - Shows recent properties (teaser for non-subscribers).
	 */
	public static function render_latest_properties( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'limit'  => 5,
			'source' => '',
		), $atts );

		$filters = array();
		if ( ! empty( $atts['source'] ) ) {
			$filters['source'] = $atts['source'];
		}

		$properties = LPNW_Property::query( $filters, (int) $atts['limit'] );

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/latest-properties.php';
		return ob_get_clean();
	}

	public static function ajax_save_preferences(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in.' );
		}

		$prefs = array(
			'areas'          => array_map( 'sanitize_text_field', $_POST['areas'] ?? array() ),
			'min_price'      => absint( $_POST['min_price'] ?? 0 ),
			'max_price'      => absint( $_POST['max_price'] ?? 0 ),
			'property_types' => array_map( 'sanitize_text_field', $_POST['property_types'] ?? array() ),
			'alert_types'    => array_map( 'sanitize_text_field', $_POST['alert_types'] ?? array() ),
			'frequency'      => sanitize_text_field( $_POST['frequency'] ?? 'daily' ),
		);

		$saved = LPNW_Subscriber::save_preferences( get_current_user_id(), $prefs );

		if ( $saved ) {
			wp_send_json_success( 'Preferences saved.' );
		} else {
			wp_send_json_error( 'Could not save preferences.' );
		}
	}

	public static function ajax_save_property(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in.' );
		}

		global $wpdb;

		$property_id = absint( $_POST['property_id'] ?? 0 );
		$notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );

		if ( ! $property_id ) {
			wp_send_json_error( 'Invalid property.' );
		}

		$wpdb->replace(
			$wpdb->prefix . 'lpnw_saved_properties',
			array(
				'user_id'     => get_current_user_id(),
				'property_id' => $property_id,
				'notes'       => $notes,
			)
		);

		wp_send_json_success( 'Property saved.' );
	}

	public static function ajax_load_properties(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		$filters = array(
			'source'          => sanitize_text_field( $_POST['source'] ?? '' ),
			'postcode_prefix' => sanitize_text_field( $_POST['postcode_prefix'] ?? '' ),
			'min_price'       => absint( $_POST['min_price'] ?? 0 ),
			'max_price'       => absint( $_POST['max_price'] ?? 0 ),
			'property_type'   => sanitize_text_field( $_POST['property_type'] ?? '' ),
		);

		$filters = array_filter( $filters );
		$limit   = min( absint( $_POST['limit'] ?? 50 ), 100 );
		$offset  = absint( $_POST['offset'] ?? 0 );

		$properties = LPNW_Property::query( $filters, $limit, $offset );

		wp_send_json_success( $properties );
	}
}
