<?php
/**
 * Free tier weekly cap for instant-style alert sends.
 *
 * Free subscribers still receive the weekly digest; a small number of matches
 * per week are sent immediately so they can sample instant delivery.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Free_Tier_Instant {

	public const USER_META_WEEK_ID = 'lpnw_free_instant_week_id';
	public const USER_META_SENT    = 'lpnw_free_instant_sent';

	/**
	 * ISO-8601 week identifier in GMT (e.g. 2026-W14).
	 */
	public static function get_current_week_id_gmt(): string {
		return gmdate( 'o-\WW' );
	}

	/**
	 * Configured maximum instant sends per free user per GMT week (0 disables instant sends).
	 */
	public static function get_weekly_limit(): int {
		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$raw = isset( $settings['free_tier_weekly_instant_alerts'] ) ? $settings['free_tier_weekly_instant_alerts'] : 5;
		$n   = absint( $raw );
		if ( $n > 100 ) {
			$n = 100;
		}

		/**
		 * Filter the weekly instant alert cap for free tier users.
		 *
		 * @param int $n Cap after absint and max clamp.
		 */
		return (int) apply_filters( 'lpnw_free_tier_weekly_instant_limit', $n );
	}

	/**
	 * Ensure week meta matches the current GMT week (rollover resets the counter).
	 */
	public static function normalize_user_week( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		$week_now = self::get_current_week_id_gmt();
		$stored   = get_user_meta( $user_id, self::USER_META_WEEK_ID, true );
		if ( (string) $stored !== $week_now ) {
			update_user_meta( $user_id, self::USER_META_WEEK_ID, $week_now );
			update_user_meta( $user_id, self::USER_META_SENT, 0 );
		}
	}

	/**
	 * Number of instant sends already used this GMT week.
	 */
	public static function get_sent_this_week( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		self::normalize_user_week( $user_id );
		$v = get_user_meta( $user_id, self::USER_META_SENT, true );
		return max( 0, absint( $v ) );
	}

	/**
	 * Remaining instant sends this week (0 if disabled or exhausted).
	 */
	public static function get_remaining( int $user_id ): int {
		$limit = self::get_weekly_limit();
		if ( $limit < 1 ) {
			return 0;
		}
		$used = self::get_sent_this_week( $user_id );
		return max( 0, $limit - $used );
	}

	/**
	 * Record one instant send for quota (call only after a successful send).
	 */
	public static function increment_sent( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		self::normalize_user_week( $user_id );
		$cur = self::get_sent_this_week( $user_id );
		update_user_meta( $user_id, self::USER_META_SENT, $cur + 1 );
	}

	/**
	 * Clear quota meta when the user upgrades to a paid tier.
	 */
	public static function reset_for_user( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		delete_user_meta( $user_id, self::USER_META_WEEK_ID );
		delete_user_meta( $user_id, self::USER_META_SENT );
	}
}
