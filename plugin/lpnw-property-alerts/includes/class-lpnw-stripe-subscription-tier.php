<?php
/**
 * Paid tier from LPNW-managed Stripe subscriptions (no WooCommerce Subscriptions plugin).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves pro/vip from {@see LPNW_Stripe_Subscription_Repository} rows.
 */
final class LPNW_Stripe_Subscription_Tier {

	/**
	 * Feature enabled in settings and Stripe Price IDs present.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['stripe_recurring_enabled'] ) ) {
			return false;
		}
		$pro = isset( $settings['stripe_price_id_pro'] ) ? trim( (string) $settings['stripe_price_id_pro'] ) : '';
		$vip = isset( $settings['stripe_price_id_vip'] ) ? trim( (string) $settings['stripe_price_id_vip'] ) : '';
		if ( '' === $pro || '' === $vip ) {
			return false;
		}
		return '' !== LPNW_Stripe_API::get_secret_key();
	}

	/**
	 * Highest paid tier from stored Stripe subscription rows for the user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string One of: free, pro, vip
	 */
	public static function get_paid_tier( int $user_id ): string {
		if ( $user_id < 1 || ! self::is_enabled() ) {
			return 'free';
		}

		$rows = LPNW_Stripe_Subscription_Repository::get_all_for_user( $user_id );
		if ( empty( $rows ) ) {
			return 'free';
		}

		$has_vip = false;
		$has_pro = false;

		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			if ( ! self::row_grants_access( $row ) ) {
				continue;
			}
			$tier = isset( $row->tier ) ? sanitize_key( (string) $row->tier ) : '';
			if ( 'vip' === $tier ) {
				$has_vip = true;
			} elseif ( 'pro' === $tier ) {
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
	 * Grace period for failed renewals (same knob as WooCommerce Subscriptions on-hold grace).
	 *
	 * @return int Days.
	 */
	public static function get_past_due_grace_days(): int {
		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			return 14;
		}
		$days = isset( $settings['subscription_on_hold_grace_days'] ) ? absint( $settings['subscription_on_hold_grace_days'] ) : 14;
		if ( $days > 60 ) {
			$days = 60;
		}
		return $days;
	}

	/**
	 * Whether a stored row currently grants Pro/VIP features.
	 *
	 * @param object $row DB row.
	 */
	public static function row_grants_access( object $row ): bool {
		$status = isset( $row->status ) ? sanitize_key( (string) $row->status ) : '';
		if ( in_array( $status, array( 'active', 'trialing' ), true ) ) {
			return true;
		}

		if ( 'past_due' === $status ) {
			$grace = self::get_past_due_grace_days();
			if ( $grace < 1 ) {
				return false;
			}
			$updated = isset( $row->updated_at ) ? strtotime( (string) $row->updated_at ) : false;
			if ( ! $updated ) {
				return false;
			}
			return ( time() - (int) $updated ) <= $grace * DAY_IN_SECONDS;
		}

		if ( 'canceled' === $status || 'cancelled' === $status ) {
			$cap = ! empty( $row->cancel_at_period_end );
			$end = isset( $row->current_period_end_ts ) ? (int) $row->current_period_end_ts : 0;
			if ( $cap && $end > time() ) {
				return true;
			}
		}

		return false;
	}
}
