<?php
/**
 * Stripe webhook endpoint (subscription lifecycle).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST route: POST /wp-json/lpnw/v1/stripe-webhook
 */
final class LPNW_Stripe_Webhook_Controller {

	/**
	 * Register REST routes on rest_api_init.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the Stripe webhook route.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'lpnw/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify signature and sync subscription events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_request( WP_REST_Request $request ) {
		$settings = get_option( 'lpnw_settings', array() );
		$secret   = is_array( $settings ) && ! empty( $settings['stripe_webhook_secret'] ) ? (string) $settings['stripe_webhook_secret'] : '';
		if ( '' === $secret ) {
			return new WP_Error( 'lpnw_webhook_disabled', __( 'Webhook not configured.', 'lpnw-alerts' ), array( 'status' => 400 ) );
		}

		$payload = $request->get_body();
		if ( ! is_string( $payload ) || '' === $payload ) {
			return new WP_Error( 'lpnw_webhook_empty', __( 'Empty body.', 'lpnw-alerts' ), array( 'status' => 400 ) );
		}

		$sig = $request->get_header( 'stripe-signature' );
		if ( ! is_string( $sig ) || '' === $sig ) {
			return new WP_Error( 'lpnw_webhook_sig', __( 'Missing signature.', 'lpnw-alerts' ), array( 'status' => 400 ) );
		}

		if ( ! self::signature_is_valid( $payload, $sig, $secret ) ) {
			return new WP_Error( 'lpnw_webhook_bad_sig', __( 'Invalid signature.', 'lpnw-alerts' ), array( 'status' => 400 ) );
		}

		$json = json_decode( $payload, true );
		if ( ! is_array( $json ) || empty( $json['type'] ) || ! is_string( $json['type'] ) ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		$type = $json['type'];
		$data = isset( $json['data']['object'] ) && is_array( $json['data']['object'] ) ? $json['data']['object'] : null;

		if ( is_array( $data ) && str_starts_with( $type, 'customer.subscription.' ) ) {
			self::sync_subscription_object( $data );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Validate the Stripe-Signature header (v1 HMAC).
	 *
	 * @param string $payload Raw body.
	 * @param string $sig_header Stripe-Signature header.
	 * @param string $secret Webhook signing secret.
	 */
	private static function signature_is_valid( string $payload, string $sig_header, string $secret ): bool {
		$timestamp  = null;
		$signatures = array();
		$parts      = explode( ',', $sig_header );
		foreach ( $parts as $part ) {
			$part = trim( $part );
			$kv   = explode( '=', $part, 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] ) {
				$timestamp = $kv[1];
			}
			if ( 'v1' === $kv[0] ) {
				$signatures[] = $kv[1];
			}
		}
		if ( null === $timestamp || empty( $signatures ) ) {
			return false;
		}
		if ( abs( time() - (int) $timestamp ) > 600 ) {
			return false;
		}
		$signed_payload = $timestamp . '.' . $payload;
		$expected       = hash_hmac( 'sha256', $signed_payload, $secret );
		foreach ( $signatures as $sig ) {
			if ( is_string( $sig ) && hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Upsert local billing row from a Stripe subscription payload.
	 *
	 * @param array<string, mixed> $sub Subscription object from Stripe.
	 */
	private static function sync_subscription_object( array $sub ): void {
		$external_id = isset( $sub['id'] ) && is_string( $sub['id'] ) ? $sub['id'] : '';
		if ( '' === $external_id || ! str_starts_with( $external_id, 'sub_' ) ) {
			return;
		}

		$user_id = 0;
		if ( isset( $sub['metadata'] ) && is_array( $sub['metadata'] ) && isset( $sub['metadata']['wp_user_id'] ) && is_string( $sub['metadata']['wp_user_id'] ) ) {
			$user_id = absint( $sub['metadata']['wp_user_id'] );
		}

		$customer_id = isset( $sub['customer'] ) && is_string( $sub['customer'] ) ? $sub['customer'] : '';

		if ( $user_id < 1 && '' !== $customer_id && preg_match( '/^cus_[a-zA-Z0-9]+$/', $customer_id ) ) {
			$user_id = self::find_user_id_by_stripe_customer_id( $customer_id );
		}

		if ( $user_id < 1 ) {
			return;
		}

		$tier = self::tier_from_subscription( $sub );

		$order_id = 0;
		if ( isset( $sub['metadata'] ) && is_array( $sub['metadata'] ) && isset( $sub['metadata']['wc_order_id'] ) && is_string( $sub['metadata']['wc_order_id'] ) ) {
			$order_id = absint( $sub['metadata']['wc_order_id'] );
		}

		$row = LPNW_Stripe_Subscription_Service::map_stripe_subscription_payload_to_row( $sub, $user_id, $tier, $customer_id, $order_id );
		LPNW_Stripe_Subscription_Repository::upsert_by_external_id( $row );
	}

	/**
	 * Infer Pro vs VIP from metadata or Stripe Price ID.
	 *
	 * @param array<string, mixed> $sub Subscription object.
	 * @return string pro|vip
	 */
	private static function tier_from_subscription( array $sub ): string {
		if ( isset( $sub['metadata'] ) && is_array( $sub['metadata'] ) && isset( $sub['metadata']['lpnw_tier'] ) && is_string( $sub['metadata']['lpnw_tier'] ) ) {
			$t = sanitize_key( $sub['metadata']['lpnw_tier'] );
			if ( in_array( $t, array( 'pro', 'vip' ), true ) ) {
				return $t;
			}
		}

		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			return 'pro';
		}
		$pro_id = isset( $settings['stripe_price_id_pro'] ) ? trim( (string) $settings['stripe_price_id_pro'] ) : '';
		$vip_id = isset( $settings['stripe_price_id_vip'] ) ? trim( (string) $settings['stripe_price_id_vip'] ) : '';

		$price = '';
		if ( isset( $sub['items']['data'] ) && is_array( $sub['items']['data'] ) && ! empty( $sub['items']['data'][0] ) && is_array( $sub['items']['data'][0] ) ) {
			$item = $sub['items']['data'][0];
			if ( isset( $item['price']['id'] ) && is_string( $item['price']['id'] ) ) {
				$price = $item['price']['id'];
			}
		}

		if ( '' !== $price && '' !== $vip_id && hash_equals( $vip_id, $price ) ) {
			return 'vip';
		}
		if ( '' !== $price && '' !== $pro_id && hash_equals( $pro_id, $price ) ) {
			return 'pro';
		}

		return 'pro';
	}

	/**
	 * Fallback user id when webhook metadata is missing.
	 *
	 * @param string $customer_id Stripe customer id.
	 */
	private static function find_user_id_by_stripe_customer_id( string $customer_id ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$uid = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ('_stripe_customer_id','stripe_customer_id') AND meta_value = %s LIMIT 1",
				$customer_id
			)
		);
		if ( $uid > 0 ) {
			return $uid;
		}
		$table = $wpdb->prefix . 'lpnw_billing_subscription';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE stripe_customer_id = %s ORDER BY id DESC LIMIT 1", $customer_id ) );
		if ( $row && isset( $row->user_id ) ) {
			return (int) $row->user_id;
		}
		return 0;
	}
}
