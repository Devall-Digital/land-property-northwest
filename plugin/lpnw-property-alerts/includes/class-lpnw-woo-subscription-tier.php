<?php
/**
 * Paid tier from WooCommerce Subscriptions (monthly Pro/VIP).
 *
 * When WooCommerce Subscriptions is active, use this instead of one-off orders
 * for Pro/VIP access. Trial periods count as active while status is active.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves pro/vip from subscription records and line-item product slugs.
 */
final class LPNW_Woo_Subscription_Tier {

	/**
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'wcs_get_users_subscriptions' );
	}

	/**
	 * Highest paid tier from the user's subscriptions (pro or vip).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string One of: free, pro, vip
	 */
	public static function get_paid_tier( int $user_id ): string {
		if ( $user_id < 1 || ! self::is_available() ) {
			return 'free';
		}

		$grace_days = self::get_on_hold_grace_days();
		$subs       = wcs_get_users_subscriptions( $user_id );
		if ( ! is_array( $subs ) || empty( $subs ) ) {
			return 'free';
		}

		$has_vip = false;
		$has_pro = false;

		foreach ( $subs as $subscription ) {
			if ( ! is_object( $subscription ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
				continue;
			}

			if ( ! self::subscription_grants_access( $subscription, $grace_days ) ) {
				continue;
			}

			$sig = self::tier_signal_from_subscription( $subscription );
			if ( 'vip' === $sig ) {
				$has_vip = true;
			} elseif ( 'pro' === $sig ) {
				$has_pro = true;
			}
		}

		if ( $has_vip ) {
			return 'vip';
		}
		if ( $has_pro ) {
			return 'pro';
		}

		return 'free';
	}

	/**
	 * On-hold grace from LPNW settings (days after last modification while on-hold).
	 *
	 * @return int
	 */
	public static function get_on_hold_grace_days(): int {
		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			return 14;
		}
		$days = isset( $settings['subscription_on_hold_grace_days'] ) ? absint( $settings['subscription_on_hold_grace_days'] ) : 14;
		if ( $days > 60 ) {
			$days = 60;
		}
		// 0 = no grace while on-hold (strict).
		return $days;
	}

	/**
	 * Whether a subscription currently entitles the user to paid features.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @param int             $grace_days   Days to keep access after on-hold.
	 */
	private static function subscription_grants_access( $subscription, int $grace_days ): bool {
		if ( ! is_object( $subscription ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
			return false;
		}
		$status = $subscription->get_status();

		if ( in_array( $status, array( 'active', 'pending-cancel' ), true ) ) {
			return true;
		}

		if ( 'on-hold' === $status && $grace_days > 0 ) { // Grace 0 = no access while on-hold.
			$modified = $subscription->get_date_modified();
			if ( $modified ) {
				$ts = is_a( $modified, 'DateTimeInterface' ) ? $modified->getTimestamp() : strtotime( (string) $modified );
				if ( $ts && ( time() - $ts ) <= $grace_days * DAY_IN_SECONDS ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Tier signal from subscription line items (product slug contains vip or pro).
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return string free|pro|vip
	 */
	private static function tier_signal_from_subscription( $subscription ): string {
		if ( ! is_object( $subscription ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
			return 'free';
		}
		$has_vip = false;
		$has_pro = false;

		foreach ( $subscription->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product' ) ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_slug' ) ) {
				continue;
			}

			$slug = $product->get_slug();
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

		return 'free';
	}

	/**
	 * Count distinct users with an access-granting Pro/VIP subscription.
	 *
	 * @return int
	 */
	public static function count_users_with_paid_subscription_tier(): int {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return 0;
		}

		$grace_days = self::get_on_hold_grace_days();
		$seen       = array();
		$page       = 1;
		$per        = 100;

		do {
			$subs = wcs_get_subscriptions(
				array(
					'subscription_status' => array( 'active', 'pending-cancel', 'on-hold' ),
					'limit'               => $per,
					'page'                => $page,
					'return'              => 'objects',
				)
			);

			$batch = is_array( $subs ) ? count( $subs ) : 0;
			if ( $batch < 1 ) {
				break;
			}

			foreach ( $subs as $subscription ) {
				if ( ! is_object( $subscription ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
					continue;
				}
				if ( ! self::subscription_grants_access( $subscription, $grace_days ) ) {
					continue;
				}
				$sig = self::tier_signal_from_subscription( $subscription );
				if ( 'pro' !== $sig && 'vip' !== $sig ) {
					continue;
				}
				$uid = (int) $subscription->get_user_id();
				if ( $uid > 0 ) {
					$seen[ $uid ] = true;
				}
			}

			++$page;
		} while ( $batch >= $per );

		return count( $seen );
	}
}
