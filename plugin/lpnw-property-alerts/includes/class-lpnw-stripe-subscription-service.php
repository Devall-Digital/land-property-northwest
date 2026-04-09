<?php
/**
 * Create Stripe subscriptions after WooCommerce checkout (native recurring without WCS).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks WooCommerce payment complete and calls the Stripe API.
 */
final class LPNW_Stripe_Subscription_Service {

	/**
	 * Whether WooCommerce hooks are registered.
	 *
	 * @var bool
	 */
	private static bool $did_register_hooks = false;

	/**
	 * Register WooCommerce hooks.
	 */
	public static function init(): void {
		if ( self::$did_register_hooks ) {
			return;
		}
		self::$did_register_hooks = true;

		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 35, 1 );
	}

	/**
	 * After successful payment, create a Stripe subscription for Pro/VIP tier products.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_payment_complete( int $order_id ): void {
		if ( $order_id < 1 || ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		if ( ! LPNW_Stripe_Subscription_Tier::is_enabled() ) {
			return;
		}

		if ( class_exists( 'LPNW_Woo_Subscription_Tier' ) && LPNW_Woo_Subscription_Tier::is_available() ) {
			$settings = get_option( 'lpnw_settings', array() );
			if ( is_array( $settings ) && ! empty( $settings['tier_use_subscriptions'] ) ) {
				return;
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( $user_id < 1 ) {
			return;
		}

		$tier = self::order_paid_tier_slug( $order );
		if ( null === $tier ) {
			return;
		}

		$price_id = self::stripe_price_id_for_tier( $tier );
		if ( '' === $price_id ) {
			return;
		}

		$customer_id = self::resolve_stripe_customer_id( $order );
		if ( '' === $customer_id ) {
			$order->add_order_note( __( 'LPNW: Stripe customer id missing on order; could not start subscription. Check Stripe gateway meta keys.', 'lpnw-alerts' ) );
			return;
		}

		$pm = self::resolve_payment_method_id( $order, $customer_id );
		if ( '' === $pm ) {
			$order->add_order_note( __( 'LPNW: Could not resolve Stripe payment method after checkout; subscription not created.', 'lpnw-alerts' ) );
			return;
		}

		self::cancel_prior_stripe_subscriptions_for_user( $user_id );

		$trial_days = (int) apply_filters( 'lpnw_stripe_subscription_trial_days_after_wc_checkout', 30, $order, $tier );
		if ( $trial_days < 1 ) {
			$trial_days = 30;
		}
		if ( $trial_days > 90 ) {
			$trial_days = 90;
		}

		$params = array(
			'customer'               => $customer_id,
			'default_payment_method' => $pm,
			'trial_period_days'      => (string) $trial_days,
			'metadata'               => array(
				'lpnw_tier'     => $tier,
				'wp_user_id'    => (string) $user_id,
				'wc_order_id'   => (string) $order_id,
				'lpnw_site_url' => home_url( '/' ),
			),
			'items'                  => array(
				array( 'price' => $price_id ),
			),
			'payment_settings'       => array(
				'save_default_payment_method' => 'on_subscription',
			),
		);

		$created = LPNW_Stripe_API::post_form( 'subscriptions', $params );
		if ( ! is_array( $created ) || empty( $created['id'] ) || ! is_string( $created['id'] ) ) {
			$order->add_order_note( __( 'LPNW: Stripe subscription create failed. Check secret key, Price IDs, and Stripe logs.', 'lpnw-alerts' ) );
			return;
		}

		$mapped = self::map_stripe_subscription_payload_to_row( $created, $user_id, $tier, $customer_id, $order_id );
		LPNW_Stripe_Subscription_Repository::upsert_by_external_id( $mapped );

		$order->add_order_note(
			sprintf(
				/* translators: %s: Stripe subscription id */
				__( 'LPNW: Stripe subscription created (%s). Next charge after trial aligns with WooCommerce first payment.', 'lpnw-alerts' ),
				$created['id']
			)
		);
	}

	/**
	 * Resolve configured Stripe Price ID for a tier slug.
	 *
	 * @param string $tier pro|vip.
	 * @return string Stripe price id or empty.
	 */
	private static function stripe_price_id_for_tier( string $tier ): string {
		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			return '';
		}
		if ( 'vip' === $tier ) {
			$id = isset( $settings['stripe_price_id_vip'] ) ? trim( (string) $settings['stripe_price_id_vip'] ) : '';
		} else {
			$id = isset( $settings['stripe_price_id_pro'] ) ? trim( (string) $settings['stripe_price_id_pro'] ) : '';
		}
		if ( ! preg_match( '/^price_[a-zA-Z0-9]+$/', $id ) ) {
			return '';
		}
		return $id;
	}

	/**
	 * Detect Pro/VIP tier from order line items.
	 *
	 * @param WC_Order $order Order.
	 * @return string|null pro, vip, or null.
	 */
	private static function order_paid_tier_slug( $order ): ?string {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return null;
		}
		$has_vip = false;
		$has_pro = false;
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product' ) ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_slug' ) ) {
				continue;
			}
			$slug = (string) $product->get_slug();
			if ( str_contains( $slug, 'vip' ) ) {
				$has_vip = true;
			}
			if ( str_contains( $slug, 'pro' ) ) {
				$has_pro = true;
			}
		}
		if ( $has_vip ) {
			return 'vip';
		}
		if ( $has_pro ) {
			return 'pro';
		}
		return null;
	}

	/**
	 * Read Stripe customer id from common WooCommerce Stripe gateway meta keys.
	 *
	 * @param WC_Order $order Order.
	 * @return string Stripe customer id or empty.
	 */
	private static function resolve_stripe_customer_id( $order ): string {
		$keys = array(
			'_stripe_customer_id',
			'stripe_customer_id',
			'_wc_stripe_customer',
		);
		foreach ( $keys as $key ) {
			$v = $order->get_meta( $key, true );
			if ( is_string( $v ) && preg_match( '/^cus_[a-zA-Z0-9]+$/', $v ) ) {
				return $v;
			}
		}
		return '';
	}

	/**
	 * Resolve a payment method id for subscription creation.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $customer_id Stripe customer id.
	 * @return string Payment method id or empty.
	 */
	private static function resolve_payment_method_id( $order, string $customer_id ): string {
		$direct_keys = array(
			'_stripe_payment_method_id',
			'_payment_method_id',
			'stripe_payment_method',
		);
		foreach ( $direct_keys as $key ) {
			$v = $order->get_meta( $key, true );
			if ( is_string( $v ) && preg_match( '/^pm_[a-zA-Z0-9]+$/', $v ) ) {
				return $v;
			}
		}

		$intent_keys = array(
			'_stripe_intent_id',
			'_transaction_id',
			'stripe_intent_id',
		);
		foreach ( $intent_keys as $key ) {
			$intent_id = $order->get_meta( $key, true );
			if ( ! is_string( $intent_id ) || ! str_starts_with( $intent_id, 'pi_' ) ) {
				continue;
			}
			$intent = LPNW_Stripe_API::get( 'payment_intents/' . rawurlencode( $intent_id ) );
			if ( is_array( $intent ) && ! empty( $intent['payment_method'] ) && is_string( $intent['payment_method'] ) ) {
				$pm = $intent['payment_method'];
				if ( preg_match( '/^pm_[a-zA-Z0-9]+$/', $pm ) ) {
					return $pm;
				}
			}
		}

		$customer = LPNW_Stripe_API::get( 'customers/' . rawurlencode( $customer_id ) );
		if ( is_array( $customer ) && ! empty( $customer['invoice_settings']['default_payment_method'] ) && is_string( $customer['invoice_settings']['default_payment_method'] ) ) {
			$pm = $customer['invoice_settings']['default_payment_method'];
			if ( preg_match( '/^pm_[a-zA-Z0-9]+$/', $pm ) ) {
				return $pm;
			}
		}

		return '';
	}

	/**
	 * Cancel prior active Stripe subscriptions for this user before attaching a new plan.
	 *
	 * @param int $user_id User ID.
	 */
	private static function cancel_prior_stripe_subscriptions_for_user( int $user_id ): void {
		$rows = LPNW_Stripe_Subscription_Repository::get_all_for_user( $user_id );
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) || empty( $row->external_id ) ) {
				continue;
			}
			$status = isset( $row->status ) ? sanitize_key( (string) $row->status ) : '';
			if ( ! in_array( $status, array( 'active', 'trialing', 'past_due', 'unpaid' ), true ) ) {
				continue;
			}
			$sub_id = (string) $row->external_id;
			if ( ! preg_match( '/^sub_[a-zA-Z0-9]+$/', $sub_id ) ) {
				continue;
			}
			LPNW_Stripe_API::delete( 'subscriptions/' . rawurlencode( $sub_id ) );
		}
	}

	/**
	 * Map a Stripe subscription object into repository row fields.
	 *
	 * @param array<string, mixed> $sub Stripe subscription object.
	 * @param int                  $user_id User ID.
	 * @param string               $tier pro|vip.
	 * @param string               $customer_id Stripe customer.
	 * @param int                  $order_id WC order id.
	 * @return array<string, mixed>
	 */
	public static function map_stripe_subscription_payload_to_row( array $sub, int $user_id, string $tier, string $customer_id, int $order_id = 0 ): array {
		$external_id = isset( $sub['id'] ) && is_string( $sub['id'] ) ? $sub['id'] : '';
		$status      = isset( $sub['status'] ) && is_string( $sub['status'] ) ? sanitize_key( $sub['status'] ) : '';

		$end_ts = null;
		if ( isset( $sub['current_period_end'] ) ) {
			$end_ts = absint( $sub['current_period_end'] );
			$end_ts = $end_ts > 0 ? $end_ts : null;
		}

		$cap = ! empty( $sub['cancel_at_period_end'] );

		if ( isset( $sub['metadata'] ) && is_array( $sub['metadata'] ) && isset( $sub['metadata']['lpnw_tier'] ) && is_string( $sub['metadata']['lpnw_tier'] ) ) {
			$meta_tier = sanitize_key( $sub['metadata']['lpnw_tier'] );
			if ( in_array( $meta_tier, array( 'pro', 'vip' ), true ) ) {
				$tier = $meta_tier;
			}
		}

		return array(
			'user_id'               => $user_id,
			'external_id'           => $external_id,
			'stripe_customer_id'    => $customer_id,
			'tier'                  => $tier,
			'status'                => $status,
			'current_period_end_ts' => $end_ts,
			'cancel_at_period_end'  => $cap ? 1 : 0,
			'initial_wc_order_id'   => $order_id > 0 ? $order_id : null,
		);
	}
}
