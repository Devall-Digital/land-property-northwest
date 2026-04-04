<?php
/**
 * VIP user submission of off-market opportunities (public form).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode [lpnw_submit_off_market] and POST handler for logged-in VIP users.
 */
class LPNW_Off_Market_Submit {

	public const NONCE_ACTION = 'lpnw_submit_off_market';

	private const RATE_TRANSIENT_PREFIX = 'lpnw_om_submit_';

	private const RATE_LIMIT = 5;

	private const RATE_WINDOW = 3600;

	public static function init(): void {
		add_shortcode( 'lpnw_submit_off_market', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'admin_post_lpnw_submit_off_market', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_post_nopriv_lpnw_submit_off_market', array( __CLASS__, 'handle_post_nopriv' ) );
	}

	/**
	 * @param array<string, string> $atts Shortcode attributes (unused).
	 */
	public static function render_shortcode( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			$url = wp_login_url( get_permalink() ?: home_url( '/' ) );

			return '<p class="lpnw-off-market-submit__login">' . sprintf(
				/* translators: %s: log in URL */
				wp_kses_post( __( 'You need to <a href="%s">log in</a> to submit an off-market opportunity.', 'lpnw-alerts' ) ),
				esc_url( $url )
			) . '</p>';
		}

		$tier = LPNW_Subscriber::get_tier( get_current_user_id() );
		if ( 'vip' !== $tier ) {
			return '<p class="lpnw-off-market-submit__tier">' . esc_html__(
				'Off-market submissions are available on the Investor VIP plan. Upgrade to share opportunities with other VIP subscribers.',
				'lpnw-alerts'
			) . '</p>';
		}

		ob_start();
		$nonce = wp_nonce_field( self::NONCE_ACTION, 'lpnw_submit_om_nonce', true, false );
		include LPNW_PLUGIN_DIR . 'public/views/off-market-submit.php';

		return (string) ob_get_clean();
	}

	public static function handle_post_nopriv(): void {
		wp_safe_redirect( wp_login_url( wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	public static function handle_post(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}

		$user_id = get_current_user_id();
		$referer = wp_get_referer();
		if ( ! is_string( $referer ) || '' === $referer ) {
			$referer = home_url( '/' );
		}

		if ( ! isset( $_POST['lpnw_submit_om_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lpnw_submit_om_nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'bad_nonce', $referer ) );
			exit;
		}

		if ( 'vip' !== LPNW_Subscriber::get_tier( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'not_vip', $referer ) );
			exit;
		}

		$honeypot = isset( $_POST['lpnw_om_website'] ) ? trim( (string) wp_unslash( $_POST['lpnw_om_website'] ) ) : '';
		if ( '' !== $honeypot ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'ok', $referer ) );
			exit;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$rk = self::RATE_TRANSIENT_PREFIX . md5( $ip . '|' . (string) $user_id );
		$n  = (int) get_transient( $rk );
		if ( $n >= self::RATE_LIMIT ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'rate', $referer ) );
			exit;
		}
		set_transient( $rk, $n + 1, self::RATE_WINDOW );

		$address  = isset( $_POST['lpnw_om_address'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_om_address'] ) ) : '';
		$postcode = isset( $_POST['lpnw_om_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_om_postcode'] ) ) : '';

		if ( '' === $address || '' === $postcode ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'missing', $referer ) );
			exit;
		}

		if ( ! self::postcode_is_nw( $postcode ) ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'postcode', $referer ) );
			exit;
		}

		$allowed_types = array( 'Detached', 'Semi-detached', 'Terraced', 'Flat/Maisonette', 'Auction lot', 'Other' );
		$property_type = isset( $_POST['lpnw_om_property_type'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_om_property_type'] ) ) : '';
		if ( '' === $property_type || ! in_array( $property_type, $allowed_types, true ) ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'type', $referer ) );
			exit;
		}

		$application_type = isset( $_POST['lpnw_om_application_type'] ) ? sanitize_key( wp_unslash( $_POST['lpnw_om_application_type'] ) ) : 'sale';
		if ( 'rent' !== $application_type ) {
			$application_type = 'sale';
		}

		$price = isset( $_POST['lpnw_om_price'] ) ? absint( $_POST['lpnw_om_price'] ) : 0;
		$price = $price > 0 ? $price : null;

		$bedrooms = null;
		if ( isset( $_POST['lpnw_om_bedrooms'] ) && '' !== $_POST['lpnw_om_bedrooms'] ) {
			$bedrooms = min( 50, max( 0, absint( $_POST['lpnw_om_bedrooms'] ) ) );
		}

		$description = isset( $_POST['lpnw_om_description'] ) ? wp_kses_post( wp_unslash( $_POST['lpnw_om_description'] ) ) : '';
		$contact     = isset( $_POST['lpnw_om_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_om_contact'] ) ) : '';
		$off_reason  = isset( $_POST['lpnw_om_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lpnw_om_reason'] ) ) : '';

		$user = wp_get_current_user();
		$submitter = $user && $user->exists() ? $user->display_name : '';
		if ( '' === $contact && is_email( $user->user_email ) ) {
			$contact = $user->user_email;
		}

		$source_ref = 'u' . (string) $user_id . '_' . ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true ) );

		$raw_payload = array(
			'lpnw_off_market'      => true,
			'user_submitted'       => true,
			'submitted_by_user_id' => $user_id,
			'submitted_by_name'    => $submitter,
			'agent_contact'        => $contact,
			'off_market_reason'    => $off_reason,
		);

		$data = array(
			'source'            => 'off_market',
			'source_ref'        => $source_ref,
			'address'           => $address,
			'postcode'          => strtoupper( trim( $postcode ) ),
			'price'             => $price,
			'property_type'     => $property_type,
			'bedrooms'          => $bedrooms,
			'bathrooms'         => null,
			'tenure_type'       => '',
			'description'       => $description,
			'application_type'  => $application_type,
			'agent_name'        => $submitter,
			'key_features_text' => '',
			'source_url'        => '',
			'raw_data'          => $raw_payload,
		);

		if ( 'rent' === $application_type ) {
			$data['price_frequency'] = 'pcm';
		}

		if ( ! empty( $data['postcode'] ) && class_exists( 'LPNW_Geocoder' ) ) {
			$coords = LPNW_Geocoder::geocode( $data['postcode'] );
			if ( null !== $coords ) {
				$data['latitude']  = $coords['latitude'];
				$data['longitude'] = $coords['longitude'];
			}
		}

		$property_id = LPNW_Property::upsert( $data );
		if ( ! $property_id ) {
			wp_safe_redirect( add_query_arg( 'lpnw_om', 'fail', $referer ) );
			exit;
		}

		$matcher = new LPNW_Matcher();
		$matcher->match_and_queue( array( (int) $property_id ) );

		self::notify_admin( (int) $property_id, $user_id, $address );

		wp_safe_redirect( add_query_arg( 'lpnw_om', 'ok', $referer ) );
		exit;
	}

	private static function notify_admin( int $property_id, int $user_id, string $address ): void {
		$to = get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return;
		}

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subj = sprintf( '[%s] VIP off-market submission #%d', $site, $property_id );
		$body = sprintf(
			"A VIP user submitted an off-market opportunity.\n\nProperty ID: %d\nUser ID: %d\nAddress: %s\n\nReview in wp-admin or browse listings.\n",
			$property_id,
			$user_id,
			$address
		);

		wp_mail( $to, $subj, $body );
	}

	private static function postcode_is_nw( string $postcode ): bool {
		$postcode = strtoupper( trim( $postcode ) );
		if ( '' === $postcode ) {
			return false;
		}

		foreach ( LPNW_NW_POSTCODES as $prefix ) {
			if ( ! str_starts_with( $postcode, $prefix ) ) {
				continue;
			}
			$next = substr( $postcode, strlen( $prefix ), 1 );
			if ( ctype_digit( $next ) || ' ' === $next ) {
				return true;
			}
		}

		return false;
	}
}
