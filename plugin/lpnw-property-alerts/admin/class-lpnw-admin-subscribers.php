<?php
/**
 * LPNW admin: subscriber list with tier and WooCommerce shortcuts.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Subscribers submenu under LPNW Alerts.
 */
final class LPNW_Admin_Subscribers {

	public const PER_PAGE = 40;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ), 20 );
	}

	public static function add_submenu(): void {
		add_submenu_page(
			'lpnw-dashboard',
			__( 'Subscribers', 'lpnw-alerts' ),
			__( 'Subscribers', 'lpnw-alerts' ),
			'manage_options',
			'lpnw-subscribers',
			array( __CLASS__, 'render_page' )
		);
	}


	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view subscribers.', 'lpnw-alerts' ) );
		}

		$data = self::get_page_data();

		include LPNW_PLUGIN_DIR . 'admin/views/subscribers.php';
	}

	/**
	 * @return array{items: array<int, array<string, mixed>>, total: int, paged: int, per_page: int, search: string, tier_counts: array<string, int>}
	 */
	private static function get_page_data(): array {
		global $wpdb;

		$prefs = $wpdb->prefix . 'lpnw_subscriber_preferences';
		$users = $wpdb->users;

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset = ( $paged - 1 ) * self::PER_PAGE;

		$where  = '1=1';
		$params = array();

		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$count_sql = "SELECT COUNT(DISTINCT sp.user_id) FROM {$prefs} sp INNER JOIN {$users} u ON u.ID = sp.user_id WHERE {$where}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$order_sql = "SELECT sp.user_id, sp.frequency, sp.is_active, sp.updated_at, sp.created_at, u.user_email, u.display_name
			FROM {$prefs} sp
			INNER JOIN {$users} u ON u.ID = sp.user_id
			WHERE {$where}
			ORDER BY sp.updated_at DESC
			LIMIT %d OFFSET %d";

		$list_params = array_merge( $params, array( self::PER_PAGE, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $order_sql, $list_params ) );

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$uid = (int) $row->user_id;
				$from_orders = LPNW_Subscriber::get_tier_from_orders( $uid );
				$effective   = LPNW_Subscriber::get_tier( $uid );
				$override    = get_user_meta( $uid, LPNW_Subscriber::USER_META_ADMIN_TIER_OVERRIDE, true );
				$override    = is_string( $override ) && '' !== $override ? $override : '';

				$orders_url = '';
				if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
					&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$orders_url = add_query_arg(
						array(
							'page'     => 'wc-orders',
							'customer' => (string) $uid,
						),
						admin_url( 'admin.php' )
					);
				} elseif ( post_type_exists( 'shop_order' ) ) {
					$orders_url = add_query_arg(
						array(
							'post_type'      => 'shop_order',
							'_customer_user' => $uid,
						),
						admin_url( 'edit.php' )
					);
				}

				$setup_done = class_exists( 'LPNW_Onboarding' ) && LPNW_Onboarding::has_completed_setup( $uid );
				$pending    = '1' === (string) get_user_meta( $uid, LPNW_Onboarding::USER_META_REDIRECT_PENDING, true );

				$items[] = array(
					'user_id'        => $uid,
					'email'          => (string) $row->user_email,
					'display_name'   => (string) $row->display_name,
					'frequency'      => (string) $row->frequency,
					'is_active'      => (int) $row->is_active,
					'updated_at'     => (string) $row->updated_at,
					'created_at'     => isset( $row->created_at ) ? (string) $row->created_at : '',
					'setup_complete' => $setup_done,
					'redirect_pending' => $pending,
					'tier'           => $effective,
					'from_orders'    => $from_orders,
					'override'       => $override,
					'orders_url'     => $orders_url,
					'edit_url'       => get_edit_user_link( $uid ),
				);
			}
		}

		$no_profile = 0;
		$no_profile_users = array();
		if ( '' === $search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$no_profile = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$users} u LEFT JOIN {$prefs} sp ON sp.user_id = u.ID WHERE sp.id IS NULL"
			);
			if ( $no_profile > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
				$no_profile_users = $wpdb->get_results(
					"SELECT u.ID, u.user_email, u.display_name, u.user_registered
					FROM {$users} u
					LEFT JOIN {$prefs} sp ON sp.user_id = u.ID
					WHERE sp.id IS NULL
					ORDER BY u.user_registered DESC
					LIMIT 15"
				);
				if ( ! is_array( $no_profile_users ) ) {
					$no_profile_users = array();
				}
			}
		}

		return array(
			'items'             => $items,
			'total'             => $total,
			'paged'             => $paged,
			'per_page'          => self::PER_PAGE,
			'search'            => $search,
			'tier_counts'       => LPNW_Subscriber::count_pref_users_by_effective_tier(),
			'no_profile_count'  => $no_profile,
			'no_profile_sample' => $no_profile_users,
		);
	}
}
