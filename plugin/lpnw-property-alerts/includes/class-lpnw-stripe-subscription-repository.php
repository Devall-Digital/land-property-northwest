<?php
/**
 * Persisted Stripe subscriptions linked to WordPress users (LPNW-native recurring billing).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for {@see LPNW_Activator} table lpnw_billing_subscription.
 */
final class LPNW_Stripe_Subscription_Repository {

	/**
	 * Billing source identifier stored with each row.
	 */
	public const SOURCE_STRIPE = 'stripe';

	/**
	 * Whether the user has any stored billing subscription row.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_any_row( int $user_id ): bool {
		if ( $user_id < 1 ) {
			return false;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_billing_subscription';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
		return $n > 0;
	}

	/**
	 * Latest row per user (by updated_at).
	 *
	 * @param int $user_id User ID.
	 * @return object|null Row object.
	 */
	public static function get_latest_for_user( int $user_id ): ?object {
		if ( $user_id < 1 ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_billing_subscription';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC, id DESC LIMIT 1",
				$user_id
			)
		);
		return is_object( $row ) ? $row : null;
	}

	/**
	 * All rows for user (newest first).
	 *
	 * @param int $user_id User ID.
	 * @return array<int, object>
	 */
	public static function get_all_for_user( int $user_id ): array {
		if ( $user_id < 1 ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_billing_subscription';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC, id DESC",
				$user_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Load a row by Stripe subscription id.
	 *
	 * @param string $external_id Stripe subscription id.
	 * @return object|null
	 */
	public static function get_by_external_id( string $external_id ): ?object {
		$external_id = sanitize_text_field( $external_id );
		if ( '' === $external_id ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_billing_subscription';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE source = %s AND external_id = %s LIMIT 1",
				self::SOURCE_STRIPE,
				$external_id
			)
		);
		return is_object( $row ) ? $row : null;
	}

	/**
	 * Insert or update by Stripe subscription id.
	 *
	 * @param array<string, mixed> $data Column map.
	 * @return bool
	 */
	public static function upsert_by_external_id( array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_billing_subscription';

		$external_id = isset( $data['external_id'] ) ? sanitize_text_field( (string) $data['external_id'] ) : '';
		if ( '' === $external_id ) {
			return false;
		}

		$existing = self::get_by_external_id( $external_id );

		$now_mysql = current_time( 'mysql' );

		$period_end_ts = null;
		if ( array_key_exists( 'current_period_end_ts', $data ) && null !== $data['current_period_end_ts'] ) {
			$period_end_ts = absint( $data['current_period_end_ts'] );
			$period_end_ts = $period_end_ts > 0 ? $period_end_ts : null;
		}

		$row = array(
			'user_id'               => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
			'source'                => self::SOURCE_STRIPE,
			'external_id'           => $external_id,
			'stripe_customer_id'    => isset( $data['stripe_customer_id'] ) ? sanitize_text_field( (string) $data['stripe_customer_id'] ) : '',
			'tier'                  => isset( $data['tier'] ) ? sanitize_key( (string) $data['tier'] ) : 'pro',
			'status'                => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : '',
			'current_period_end_ts' => $period_end_ts,
			'cancel_at_period_end'  => ! empty( $data['cancel_at_period_end'] ) ? 1 : 0,
			'initial_wc_order_id'   => isset( $data['initial_wc_order_id'] ) ? absint( $data['initial_wc_order_id'] ) : null,
			'updated_at'            => $now_mysql,
		);

		if ( ! in_array( $row['tier'], array( 'pro', 'vip' ), true ) ) {
			$row['tier'] = 'pro';
		}

		if ( $existing && isset( $existing->id ) ) {
			if ( $row['user_id'] < 1 && isset( $existing->user_id ) ) {
				$row['user_id'] = (int) $existing->user_id;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = false !== $wpdb->update(
				$table,
				$row,
				array( 'id' => (int) $existing->id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);
			return $ok;
		}

		$row['created_at'] = $now_mysql;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
		return false !== $inserted;
	}
}
