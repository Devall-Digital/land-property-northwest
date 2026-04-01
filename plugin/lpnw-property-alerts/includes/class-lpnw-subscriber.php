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
	 * Get preferences for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	public static function get_preferences( int $user_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_subscriber_preferences';

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d",
			$user_id
		) );

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

		$row = array(
			'user_id'        => $user_id,
			'areas'          => wp_json_encode( $prefs['areas'] ?? array() ),
			'min_price'      => isset( $prefs['min_price'] ) ? absint( $prefs['min_price'] ) : null,
			'max_price'          => isset( $prefs['max_price'] ) ? absint( $prefs['max_price'] ) : null,
			'min_bedrooms'       => isset( $prefs['min_bedrooms'] ) ? absint( $prefs['min_bedrooms'] ) : null,
			'max_bedrooms'       => isset( $prefs['max_bedrooms'] ) ? absint( $prefs['max_bedrooms'] ) : null,
			'listing_channels'   => wp_json_encode( $prefs['listing_channels'] ?? array() ),
			'tenure_preferences' => wp_json_encode( $prefs['tenure_preferences'] ?? array() ),
			'required_features'  => wp_json_encode( $prefs['required_features'] ?? array() ),
			'property_types'     => wp_json_encode( $prefs['property_types'] ?? array() ),
			'alert_types'    => wp_json_encode( $prefs['alert_types'] ?? array() ),
			'frequency'      => sanitize_text_field( $prefs['frequency'] ?? 'weekly' ),
			'is_active'      => 1,
		);

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d",
			$user_id
		) );

		if ( $existing ) {
			return (bool) $wpdb->update( $table, $row, array( 'id' => $existing ) );
		}

		return (bool) $wpdb->insert( $table, $row );
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
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_active = 1 AND frequency = %s",
				$frequency
			) );
		}

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_active = 1"
		);
	}

	/**
	 * Determine a user's tier from paid WooCommerce orders (completed or processing).
	 *
	 * VIP wins over Pro if both appear in order history.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string One of: free, pro, vip
	 */
	public static function get_tier( int $user_id ): string {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 'free';
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'completed', 'processing' ),
				'limit'       => -1,
				'return'      => 'objects',
			)
		);

		if ( ! is_array( $orders ) ) {
			return 'free';
		}

		$has_vip = false;
		$has_pro = false;

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
				continue;
			}

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
		}

		if ( $has_vip ) {
			return 'vip';
		}
		if ( $has_pro ) {
			return 'pro';
		}

		return 'free';
	}
}
