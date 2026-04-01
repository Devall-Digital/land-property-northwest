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
		add_shortcode( 'lpnw_contact_form', array( __CLASS__, 'render_contact_form' ) );
		add_shortcode( 'lpnw_area_stats', array( __CLASS__, 'render_area_stats' ) );
		add_shortcode( 'lpnw_total_sources', array( __CLASS__, 'render_total_sources' ) );

		add_action( 'wp_ajax_lpnw_save_preferences', array( __CLASS__, 'ajax_save_preferences' ) );
		add_action( 'wp_ajax_lpnw_contact_form', array( __CLASS__, 'ajax_contact_form' ) );
		add_action( 'wp_ajax_nopriv_lpnw_contact_form', array( __CLASS__, 'ajax_contact_form' ) );
		add_action( 'wp_ajax_lpnw_save_property', array( __CLASS__, 'ajax_save_property' ) );
		add_action( 'wp_ajax_lpnw_unsave_property', array( __CLASS__, 'ajax_unsave_property' ) );
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
	 * [lpnw_area_stats] - Grid of property counts by NW postcode area.
	 *
	 * @return string HTML.
	 */
	public static function render_area_stats(): string {
		$rows   = LPNW_Property::count_by_nw_area();
		$labels = LPNW_Property::get_nw_area_labels();

		if ( empty( $rows ) ) {
			return '<p class="lpnw-area-stats lpnw-area-stats--empty">' . esc_html__( 'Area breakdown will appear once we have postcode data.', 'lpnw-alerts' ) . '</p>';
		}

		$list = '';
		foreach ( $rows as $row ) {
			$code  = isset( $row->area ) ? sanitize_text_field( (string) $row->area ) : '';
			$count = isset( $row->cnt ) ? (int) $row->cnt : 0;
			if ( '' === $code || $count < 1 ) {
				continue;
			}
			$name = isset( $labels[ $code ] ) ? $labels[ $code ] : $code;
			$line = sprintf(
				/* translators: 1: area name (e.g. Manchester), 2: property count */
				__( '%1$s: %2$s properties', 'lpnw-alerts' ),
				$name,
				number_format_i18n( $count )
			);
			$list .= '<li class="lpnw-area-stats__item"><span class="lpnw-area-stats__text">' . esc_html( $line ) . '</span></li>';
		}

		if ( '' === $list ) {
			return '<p class="lpnw-area-stats lpnw-area-stats--empty">' . esc_html__( 'Area breakdown will appear once we have postcode data.', 'lpnw-alerts' ) . '</p>';
		}

		return '<div class="lpnw-area-stats" role="region" aria-label="' . esc_attr__( 'Property counts by northwest area', 'lpnw-alerts' ) . '">'
			. '<ul class="lpnw-area-stats__grid" role="list">' . $list . '</ul>'
			. '</div>';
	}

	/**
	 * [lpnw_total_sources] - How many distinct feed sources have rows in the property table.
	 *
	 * @return string HTML.
	 */
	public static function render_total_sources(): string {
		global $wpdb;

		$table = $wpdb->prefix . 'lpnw_properties';
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT source) FROM {$table} WHERE TRIM(COALESCE(source, '')) <> ''" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( $count < 1 ) {
			return '<p class="lpnw-total-sources lpnw-total-sources--empty">' . esc_html__( 'Source counts will appear as feeds add data.', 'lpnw-alerts' ) . '</p>';
		}

		$text = sprintf(
			/* translators: %d: number of distinct data sources */
			_n( 'Data from %d source', 'Data from %d sources', $count, 'lpnw-alerts' ),
			$count
		);

		return '<p class="lpnw-total-sources">' . esc_html( $text ) . '</p>';
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
	 * [lpnw_contact_form] - Native contact form (AJAX to admin-ajax.php).
	 *
	 * @return string HTML.
	 */
	public static function render_contact_form(): string {
		ob_start();
		$nonce = wp_nonce_field( 'lpnw_contact', 'nonce', true, false );
		include LPNW_PLUGIN_DIR . 'public/views/contact-form.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_latest_properties] - Shows recent properties (teaser for non-subscribers).
	 *
	 * Attributes: limit, source, postcode_prefix (NW outward code, e.g. M, CH).
	 */
	public static function render_latest_properties( array $atts = array() ): string {
		defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', true );

		$atts = shortcode_atts( array(
			'limit'             => 5,
			'source'            => '',
			'postcode_prefix'   => '',
		), $atts );

		$filters = array();
		if ( ! empty( $atts['source'] ) ) {
			$filters['source'] = $atts['source'];
		}
		if ( ! empty( $atts['postcode_prefix'] ) ) {
			$pp = strtoupper( trim( sanitize_text_field( $atts['postcode_prefix'] ) ) );
			if ( in_array( $pp, LPNW_NW_POSTCODES, true ) ) {
				$filters['postcode_prefix'] = $pp;
			}
		}

		$limit = (int) $atts['limit'];
		if ( ! empty( $filters['postcode_prefix'] ) ) {
			$properties = LPNW_Property::query( $filters, $limit, 0 );
		} else {
			$properties = LPNW_Property::query_diverse( $filters, $limit );
		}

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/latest-properties.php';
		return ob_get_clean();
	}

	public static function ajax_save_preferences(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in.' );
		}

		$user_id = get_current_user_id();
		$tier    = LPNW_Subscriber::get_tier( $user_id );

		$prefs = array(
			'areas'          => array_map( 'sanitize_text_field', $_POST['areas'] ?? array() ),
			'min_price'      => absint( $_POST['min_price'] ?? 0 ),
			'max_price'      => absint( $_POST['max_price'] ?? 0 ),
			'property_types' => array_map( 'sanitize_text_field', $_POST['property_types'] ?? array() ),
			'alert_types'    => array_map( 'sanitize_text_field', $_POST['alert_types'] ?? array() ),
			'frequency'      => self::clamp_frequency_for_tier(
				sanitize_text_field( $_POST['frequency'] ?? 'weekly' ),
				$tier
			),
		);

		$saved = LPNW_Subscriber::save_preferences( $user_id, $prefs );

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

	public static function ajax_unsave_property(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in.' );
		}

		global $wpdb;

		$property_id = absint( $_POST['property_id'] ?? 0 );

		if ( ! $property_id ) {
			wp_send_json_error( 'Invalid property.' );
		}

		$deleted = $wpdb->delete(
			$wpdb->prefix . 'lpnw_saved_properties',
			array(
				'user_id'     => get_current_user_id(),
				'property_id' => $property_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( 'Could not remove saved property.' );
		}

		wp_send_json_success( 'Property removed from saved list.' );
	}

	/**
	 * Handle public contact form submission.
	 */
	public static function ajax_contact_form(): void {
		check_ajax_referer( 'lpnw_contact', 'nonce' );

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === $name || '' === $message || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your name, a valid email, and a message.', 'lpnw-alerts' ),
				)
			);
		}

		$admin_email = get_option( 'admin_email' );
		if ( ! is_email( $admin_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The site cannot accept messages right now. Please try again later.', 'lpnw-alerts' ),
				)
			);
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( '' !== $subject ) {
			/* translators: 1: site name, 2: user-supplied subject line */
			$mail_subject = sprintf( __( '[%1$s] %2$s', 'lpnw-alerts' ), $site_name, $subject );
		} else {
			/* translators: %s: site name */
			$mail_subject = sprintf( __( '[%s] Contact form', 'lpnw-alerts' ), $site_name );
		}

		$body = sprintf(
			"%s\n%s: %s\n%s: %s\n\n%s\n",
			/* translators: email body header */
			__( 'New message from the website contact form.', 'lpnw-alerts' ),
			/* translators: email label */
			__( 'Name', 'lpnw-alerts' ),
			$name,
			/* translators: email label */
			__( 'Email', 'lpnw-alerts' ),
			$email,
			$message
		);

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . $email,
		);

		$sent = wp_mail( $admin_email, $mail_subject, $body, $headers );

		if ( ! $sent ) {
			wp_send_json_error(
				array(
					'message' => __( 'Could not send your message. Please try again later.', 'lpnw-alerts' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Thank you. We have received your message and will reply as soon as we can.', 'lpnw-alerts' ),
			)
		);
	}

	public static function ajax_load_properties(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Login required.' );
		}

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

	/**
	 * Restrict alert frequency to values allowed for the subscription tier.
	 *
	 * Free: weekly only. Pro: daily or instant (or weekly). VIP: any known value.
	 *
	 * @param string $frequency Requested frequency (instant, daily, weekly).
	 * @param string $tier      One of free, pro, vip.
	 * @return string Clamped frequency.
	 */
	private static function clamp_frequency_for_tier( string $frequency, string $tier ): string {
		$valid = array( 'instant', 'daily', 'weekly' );
		if ( ! in_array( $frequency, $valid, true ) ) {
			$frequency = 'weekly';
		}

		$tier = strtolower( $tier );

		if ( 'free' === $tier ) {
			return 'weekly';
		}

		// Pro or VIP: any valid frequency (instant, daily, weekly).
		return $frequency;
	}
}
