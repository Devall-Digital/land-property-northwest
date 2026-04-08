<?php
/**
 * Subscriber preferences model.
 *
 * Handles CRUD for alert preferences stored in lpnw_subscriber_preferences.
 * Each WordPress user can have one set of preferences.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Subscriber {

	/**
	 * User meta: admin-only tier adjustment when the user has no qualifying paid order.
	 * Values: empty (use orders), free, pro, vip.
	 */
	public const USER_META_ADMIN_TIER_OVERRIDE = 'lpnw_admin_tier_override';

	/**
	 * Allowed property type checkbox values for subscriber preferences (matches matcher + preferences UI).
	 *
	 * @return array<int, string>
	 */
	public static function allowed_preference_property_types(): array {
		return array(
			'Detached',
			'Semi-detached',
			'Terraced',
			'Flat/Maisonette',
			'Auction lot',
			'Other',
		);
	}

	/**
	 * Intersect submitted types with the allow-list.
	 *
	 * @param array<int, string> $raw Raw values from the form.
	 * @return array<int, string>
	 */
	public static function sanitize_preference_property_types( array $raw ): array {
		$clean = array_map( 'sanitize_text_field', $raw );

		return array_values( array_intersect( self::allowed_preference_property_types(), $clean ) );
	}

	/**
	 * Get preferences for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	public static function get_preferences( int $user_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_subscriber_preferences';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		if ( $row ) {
			$row->areas              = json_decode( $row->areas, true ) ?: array();
			$row->property_types     = json_decode( $row->property_types, true ) ?: array();
			$row->alert_types        = json_decode( $row->alert_types, true ) ?: array();
			$row->listing_channels   = json_decode( $row->listing_channels ?? '', true ) ?: array();
			$row->tenure_preferences = json_decode( $row->tenure_preferences ?? '', true ) ?: array();
			$row->required_features  = json_decode( $row->required_features ?? '', true ) ?: array();
		}

		return $row;
	}

	/**
	 * Save preferences for a user (insert or update).
	 *
	 * @param int                  $user_id WordPress user ID.
	 * @param array<string, mixed> $prefs   Preference data.
	 * @return bool
	 */
	public static function save_preferences( int $user_id, array $prefs ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_subscriber_preferences';

		$is_active = array_key_exists( 'is_active', $prefs )
			? ( ! empty( $prefs['is_active'] ) ? 1 : 0 )
			: 1;

		$row = array(
			'user_id'            => $user_id,
			'areas'              => wp_json_encode( $prefs['areas'] ?? array() ),
			'min_price'          => isset( $prefs['min_price'] ) ? absint( $prefs['min_price'] ) : null,
			'max_price'          => isset( $prefs['max_price'] ) ? absint( $prefs['max_price'] ) : null,
			'min_bedrooms'       => isset( $prefs['min_bedrooms'] ) ? absint( $prefs['min_bedrooms'] ) : null,
			'max_bedrooms'       => isset( $prefs['max_bedrooms'] ) ? absint( $prefs['max_bedrooms'] ) : null,
			'listing_channels'   => wp_json_encode( $prefs['listing_channels'] ?? array() ),
			'tenure_preferences' => wp_json_encode( $prefs['tenure_preferences'] ?? array() ),
			'required_features'  => wp_json_encode( $prefs['required_features'] ?? array() ),
			'property_types'     => wp_json_encode( $prefs['property_types'] ?? array() ),
			'alert_types'        => wp_json_encode( $prefs['alert_types'] ?? array() ),
			'frequency'          => sanitize_text_field( $prefs['frequency'] ?? 'weekly' ),
			'is_active'          => $is_active,
		);

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		if ( $existing ) {
			// $wpdb->update returns int rows affected; 0 means "no change" but is still success.
			$result = $wpdb->update( $table, $row, array( 'id' => $existing ) );

			$ok = false !== $result;
			if ( $ok && ! empty( $prefs['mark_setup_incomplete'] ) && class_exists( 'LPNW_Onboarding' ) ) {
				delete_user_meta( $user_id, LPNW_Onboarding::USER_META_SETUP_COMPLETE );
			}

			return $ok;
		}

		$result = $wpdb->insert( $table, $row );

		$ok = false !== $result;
		if ( $ok && ! empty( $prefs['mark_setup_incomplete'] ) && class_exists( 'LPNW_Onboarding' ) ) {
			delete_user_meta( $user_id, LPNW_Onboarding::USER_META_SETUP_COMPLETE );
		}

		return $ok;
	}

	/**
	 * Get all active subscribers, optionally filtered by frequency.
	 *
	 * @param string|null $frequency Filter by frequency (instant, daily, weekly) or null for all.
	 * @return array<int, object>
	 */
	public static function get_active( ?string $frequency = null ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_subscriber_preferences';

		if ( $frequency ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE is_active = 1 AND frequency = %s",
					$frequency
				)
			);
		}

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_active = 1"
		);
	}

	/**
	 * Highest LPNW tier indicated by line items on one order (slug match, same rules as get_tier_from_orders).
	 *
	 * @param object $order WooCommerce order object.
	 * @return string One of: free, pro, vip
	 */
	private static function lpnw_tier_signal_from_order( object $order ): string {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return 'free';
		}

		if ( method_exists( $order, 'get_status' ) ) {
			$st = $order->get_status();
			if ( in_array( $st, array( 'refunded', 'cancelled', 'failed', 'trash' ), true ) ) {
				return 'free';
			}
		}

		if ( method_exists( $order, 'get_total' ) && method_exists( $order, 'get_total_refunded' ) ) {
			$total    = (float) $order->get_total();
			$refunded = (float) $order->get_total_refunded();
			if ( $total > 0 && $refunded >= $total - 0.01 ) {
				return 'free';
			}
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
	 * Whether Pro/VIP should come from WooCommerce Subscriptions when the extension is active.
	 *
	 * @return bool
	 */
	public static function use_subscription_for_paid_tier(): bool {
		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			return class_exists( 'LPNW_Woo_Subscription_Tier' ) && LPNW_Woo_Subscription_Tier::is_available();
		}
		if ( array_key_exists( 'tier_use_subscriptions', $settings ) ) {
			return ! empty( $settings['tier_use_subscriptions'] );
		}
		return class_exists( 'LPNW_Woo_Subscription_Tier' ) && LPNW_Woo_Subscription_Tier::is_available();
	}

	/**
	 * Paid tier from billing: active subscriptions first (when enabled), else qualifying orders.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string One of: free, pro, vip
	 */
	public static function get_tier_from_billing( int $user_id ): string {
		if ( self::use_subscription_for_paid_tier() && class_exists( 'LPNW_Woo_Subscription_Tier' ) && LPNW_Woo_Subscription_Tier::is_available() ) {
			return LPNW_Woo_Subscription_Tier::get_paid_tier( $user_id );
		}
		return self::get_tier_from_orders( $user_id );
	}

	/**
	 * Tier from WooCommerce orders only (ignores admin override). For admin display and support.
	 *
	 * VIP wins over Pro if both appear in order history.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string One of: free, pro, vip
	 */
	public static function get_tier_from_orders( int $user_id ): string {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 'free';
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'completed', 'processing' ),
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects',
			)
		);

		if ( ! is_array( $orders ) ) {
			return 'free';
		}

		$has_vip = false;
		$has_pro = false;

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) ) {
				continue;
			}

			$sig = self::lpnw_tier_signal_from_order( $order );
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
	 * Distinct WordPress user IDs with a qualifying Pro/VIP line item on at least one completed or processing order.
	 *
	 * Guest orders (no linked customer ID) are excluded. Paginates through orders for large shops.
	 *
	 * @return int
	 */
	public static function count_customers_with_paid_tier_order(): int {
		if ( self::use_subscription_for_paid_tier() && class_exists( 'LPNW_Woo_Subscription_Tier' ) && LPNW_Woo_Subscription_Tier::is_available() ) {
			return LPNW_Woo_Subscription_Tier::count_users_with_paid_subscription_tier();
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$seen = array();
		$page = 1;
		$per  = 100;

		do {
			$orders = wc_get_orders(
				array(
					'status'  => array( 'completed', 'processing' ),
					'limit'   => $per,
					'page'    => $page,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'objects',
				)
			);

			$batch_count = is_array( $orders ) ? count( $orders ) : 0;

			if ( $batch_count < 1 ) {
				break;
			}

			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_customer_id' ) ) {
					continue;
				}

				$cid = (int) $order->get_customer_id();
				if ( $cid <= 0 ) {
					continue;
				}

				$sig = self::lpnw_tier_signal_from_order( $order );
				if ( 'pro' === $sig || 'vip' === $sig ) {
					$seen[ $cid ] = true;
				}
			}

			++$page;
		} while ( $batch_count >= $per );

		return count( $seen );
	}

	/**
	 * Effective tier: paid subscription (when enabled) or qualifying orders; else admin override.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string One of: free, pro, vip
	 */
	public static function get_tier( int $user_id ): string {
		$from_billing = self::get_tier_from_billing( $user_id );
		if ( 'pro' === $from_billing || 'vip' === $from_billing ) {
			return $from_billing;
		}

		$raw = get_user_meta( $user_id, self::USER_META_ADMIN_TIER_OVERRIDE, true );
		$raw = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';
		if ( '' === $raw ) {
			return 'free';
		}
		if ( in_array( $raw, array( 'free', 'pro', 'vip' ), true ) ) {
			return $raw;
		}

		return 'free';
	}

	/**
	 * Count users with a preferences row by effective tier (for admin metrics).
	 *
	 * @return array{free: int, pro: int, vip: int, total: int}
	 */
	public static function count_pref_users_by_effective_tier(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_subscriber_preferences';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$table} WHERE is_active = 1" );
		$out = array(
			'free'  => 0,
			'pro'   => 0,
			'vip'   => 0,
			'total' => 0,
		);
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return $out;
		}
		$out['total'] = count( $ids );
		foreach ( $ids as $uid ) {
			$uid = (int) $uid;
			if ( $uid <= 0 ) {
				continue;
			}
			$tier = self::get_tier( $uid );
			if ( 'vip' === $tier ) {
				++$out['vip'];
			} elseif ( 'pro' === $tier ) {
				++$out['pro'];
			} else {
				++$out['free'];
			}
		}
		return $out;
	}
}
