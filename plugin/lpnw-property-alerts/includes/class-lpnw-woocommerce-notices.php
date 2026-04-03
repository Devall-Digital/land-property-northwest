<?php
/**
 * WooCommerce order hooks: one-time subscriber messaging after upgrade.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * One-time tips for subscribers after a paid tier purchase (WooCommerce thank-you).
 */
class LPNW_WooCommerce_Notices {

	public const USER_META_ALERT_SCHEDULE_TIP = 'lpnw_show_alert_schedule_tip';

	/**
	 * Register WooCommerce hooks when WooCommerce is present.
	 */
	public static function init(): void {
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'flag_new_paid_subscriber' ), 20, 1 );
	}

	/**
	 * After checkout, flag the customer to see alert-schedule guidance on next dashboard visit.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function flag_new_paid_subscriber( int $order_id ): void {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		if ( ! self::order_has_paid_tier_product( $order ) ) {
			return;
		}

		update_user_meta( $user_id, self::USER_META_ALERT_SCHEDULE_TIP, '1' );
	}

	/**
	 * True if the order contains a Pro or VIP subscription product (slug contains pro or vip).
	 *
	 * @param \WC_Order $order Order object.
	 */
	private static function order_has_paid_tier_product( $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product' ) ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || ! method_exists( $product, 'get_slug' ) ) {
				continue;
			}
			$slug = (string) $product->get_slug();
			if ( str_contains( $slug, 'vip' ) || str_contains( $slug, 'pro' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether to show the one-time tip on the subscriber dashboard (and clear the flag).
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function consume_alert_schedule_tip( int $user_id ): bool {
		$v = get_user_meta( $user_id, self::USER_META_ALERT_SCHEDULE_TIP, true );
		if ( '1' !== $v && 1 !== $v && true !== $v ) {
			return false;
		}
		delete_user_meta( $user_id, self::USER_META_ALERT_SCHEDULE_TIP );
		return true;
	}
}
