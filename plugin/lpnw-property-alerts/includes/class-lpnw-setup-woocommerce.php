<?php
/**
 * One-off WooCommerce and navigation setup for LPNW.
 *
 * Invoke {@see LPNW_Setup_WooCommerce::run()} from a temporary bootstrap script
 * uploaded to the WordPress root (then delete the script).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-lpnw-woocommerce-store.php';

/**
 * Configures WooCommerce, subscription-tier products, and nav menus.
 */
final class LPNW_Setup_WooCommerce {

	private const MENU_PRIMARY    = 'Primary';
	private const MENU_SUBSCRIBER = 'Subscriber';

	/**
	 * Run all setup steps. Safe to call more than once (idempotent where practical).
	 *
	 * @return array<string, mixed> Report including woocommerce, products, menus, notices, summary_lines.
	 */
	public static function run(): array {
		$report = array(
			'woocommerce' => array(),
			'products'    => array(),
			'menus'       => array(),
			'notices'     => array(),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$report['woocommerce'] = self::configure_woocommerce();
			$report['products']    = self::ensure_products();
		} else {
			$report['notices'][] = 'WooCommerce is not active; skipped WooCommerce settings and products.';
		}

		$menu_report       = self::configure_menus();
		$report['menus']   = $menu_report['menus'];
		$report['notices'] = array_merge( $report['notices'], $menu_report['notices'] );

		$report['summary_lines'] = self::get_summary_lines( $report );

		return $report;
	}

	/**
	 * Flatten a human-readable summary for CLI output.
	 *
	 * @param array<string, mixed> $report Report from run().
	 * @return array<int, string>
	 */
	public static function get_summary_lines( array $report ): array {
		$lines = array();

		if ( ! empty( $report['woocommerce'] ) && is_array( $report['woocommerce'] ) ) {
			$lines[] = 'WooCommerce: currency GBP, position left with space, default country GB, guest checkout off, account creation at checkout on.';
		}

		if ( ! empty( $report['products'] ) && is_array( $report['products'] ) ) {
			foreach ( $report['products'] as $slug => $row ) {
				if ( is_array( $row ) && isset( $row['action'], $row['id'] ) ) {
					$kind = '';
					if ( class_exists( 'WC_Product_Subscription' ) && in_array( $slug, array( 'lpnw-pro', 'lpnw-vip' ), true ) ) {
						$kind = ' (subscription)';
					}
					$lines[] = sprintf( 'Product %s: %s (ID %d)%s.', $slug, $row['action'], (int) $row['id'], $kind );
				}
			}
		}

		if ( ! empty( $report['menus'] ) && is_array( $report['menus'] ) ) {
			if ( ! empty( $report['menus']['primary_menu_id'] ) ) {
				$lines[] = sprintf( 'Primary menu ID %d: Home, Pricing, About, Contact (WooCommerce shop/cart/checkout/account links removed).', (int) $report['menus']['primary_menu_id'] );
			}
			if ( ! empty( $report['menus']['woocommerce_items_cleared_from_primary'] ) ) {
				$lines[] = sprintf(
					'Cleared %d WooCommerce page link(s) from the Primary menu before rebuild.',
					(int) $report['menus']['woocommerce_items_cleared_from_primary']
				);
			}
			if ( ! empty( $report['menus']['subscriber_menu_id'] ) ) {
				$lines[] = sprintf( 'Subscriber menu ID %d: Dashboard, Preferences, Property Map, Saved Properties.', (int) $report['menus']['subscriber_menu_id'] );
			}
			if ( ! empty( $report['menus']['woocommerce_items_removed_from_previous_primary_menu'] ) ) {
				$lines[] = sprintf(
					'Removed %d WooCommerce page link(s) from the former primary menu.',
					(int) $report['menus']['woocommerce_items_removed_from_previous_primary_menu']
				);
			}
		}

		foreach ( $report['notices'] as $notice ) {
			$lines[] = 'Notice: ' . $notice;
		}

		return $lines;
	}

	/**
	 * Apply core WooCommerce options.
	 *
	 * @return array<string, mixed>
	 */
	private static function configure_woocommerce(): array {
		$out = array();

		update_option( 'woocommerce_currency', 'GBP' );
		$out['currency'] = 'GBP';

		update_option( 'woocommerce_currency_pos', 'left_space' );
		$out['currency_position'] = 'left_space';

		update_option( 'woocommerce_default_country', 'GB' );
		$out['default_country'] = 'GB';

		update_option( 'woocommerce_enable_guest_checkout', 'no' );
		$out['guest_checkout'] = 'no';

		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
		$out['signup_at_checkout'] = 'yes';

		return $out;
	}

	/**
	 * Create or update the three tier products.
	 *
	 * @return array<string, array<string, int|string>>
	 */
	private static function ensure_products(): array {
		$defs = array(
			'lpnw-free' => array(
				'name'  => 'Free Property Alerts',
				'price' => '0',
				'desc'  => '<p>Weekly digest email covering property and planning highlights across Northwest England. A simple way to stay in the loop without instant notifications.</p>',
			),
			'lpnw-pro' => array(
				'name'  => 'Pro Property Alerts',
				'price' => '19.99',
				'desc'  => '<p>Instant alerts when new opportunities match your criteria, plus full filtering over areas, price, and property types so you only see what you care about.</p>',
			),
			'lpnw-vip' => array(
				'name'  => 'Investor VIP Alerts',
				'price' => '79.99',
				'desc'  => '<p>Priority alerts and curated off-market style intelligence for investors who want the signal first, with room for higher-touch deal flow.</p>',
			),
		);

		$results = array();

		$results['lpnw-free'] = self::upsert_simple_product(
			'lpnw-free',
			$defs['lpnw-free']['name'],
			$defs['lpnw-free']['price'],
			$defs['lpnw-free']['desc']
		);

		$use_sub_products = class_exists( 'WC_Product_Subscription' );
		if ( $use_sub_products ) {
			$results['lpnw-pro'] = self::upsert_subscription_product(
				'lpnw-pro',
				$defs['lpnw-pro']['name'],
				$defs['lpnw-pro']['price'],
				$defs['lpnw-pro']['desc'],
				array(
					'trial_days' => 7,
				)
			);
			$results['lpnw-vip'] = self::upsert_subscription_product(
				'lpnw-vip',
				$defs['lpnw-vip']['name'],
				$defs['lpnw-vip']['price'],
				$defs['lpnw-vip']['desc'],
				array()
			);
		} else {
			$results['lpnw-pro'] = self::upsert_simple_product(
				'lpnw-pro',
				$defs['lpnw-pro']['name'],
				$defs['lpnw-pro']['price'],
				$defs['lpnw-pro']['desc']
			);
			$results['lpnw-vip'] = self::upsert_simple_product(
				'lpnw-vip',
				$defs['lpnw-vip']['name'],
				$defs['lpnw-vip']['price'],
				$defs['lpnw-vip']['desc']
			);
		}

		return $results;
	}

	/**
	 * Create or update a simple subscription product (monthly, until cancelled).
	 *
	 * @param string               $slug    Product slug.
	 * @param string               $name    Title.
	 * @param string               $price   Price per period.
	 * @param string               $desc    HTML description.
	 * @param array<string, mixed> $opts    Optional: trial_days (int).
	 * @return array<string, int|string>
	 */
	private static function upsert_subscription_product( string $slug, string $name, string $price, string $desc, array $opts = array() ): array {
		if ( ! class_exists( 'WC_Product_Subscription' ) ) {
			return self::upsert_simple_product( $slug, $name, $price, $desc );
		}

		$existing_id = self::get_post_id_by_slug( $slug, 'product' );

		if ( $existing_id > 0 ) {
			$product = wc_get_product( $existing_id );
			if ( ! $product instanceof WC_Product_Subscription ) {
				wp_delete_post( $existing_id, true );
				$existing_id = 0;
			}
		}

		if ( $existing_id > 0 ) {
			$product = wc_get_product( $existing_id );
			if ( ! $product instanceof WC_Product_Subscription ) {
				$product = new WC_Product_Subscription( $existing_id );
			}
			$action = 'updated';
		} else {
			$product = new WC_Product_Subscription();
			$action  = 'created';
		}

		$product->set_name( $name );
		$product->set_slug( $slug );
		$product->set_regular_price( $price );
		$product->set_virtual( true );
		$product->set_catalog_visibility( 'visible' );
		$product->set_description( wp_kses_post( $desc ) );
		$product->set_short_description( wp_kses_post( $desc ) );
		$product->set_status( 'publish' );
		$product->set_manage_stock( false );

		if ( method_exists( $product, 'set_subscription_period' ) ) {
			$product->set_subscription_period( 'month' );
		}
		if ( method_exists( $product, 'set_subscription_period_interval' ) ) {
			$product->set_subscription_period_interval( 1 );
		}
		if ( method_exists( $product, 'set_subscription_length' ) ) {
			$product->set_subscription_length( 0 );
		}

		$trial_days = isset( $opts['trial_days'] ) ? max( 0, absint( $opts['trial_days'] ) ) : 0;
		if ( $trial_days > 0 && method_exists( $product, 'set_trial_length' ) && method_exists( $product, 'set_trial_period' ) ) {
			$product->set_trial_length( $trial_days );
			$product->set_trial_period( 'day' );
		} elseif ( method_exists( $product, 'set_trial_length' ) ) {
			$product->set_trial_length( 0 );
		}

		$product_id = $product->save();

		$saved = wc_get_product( $product_id );
		if ( $saved instanceof WC_Product && class_exists( 'LPNW_WooCommerce_Store' ) ) {
			LPNW_WooCommerce_Store::apply_tier_product_flags( $saved );
			$saved->save();
		}

		wc_delete_product_transients( $product_id );

		return array(
			'id'     => $product_id,
			'action' => $action,
		);
	}

	/**
	 * @param string $slug    Product post_name.
	 * @param string $name    Product title.
	 * @param string $price   Regular price (stored as string for WC).
	 * @param string $desc    HTML description.
	 * @return array<string, int|string>
	 */
	private static function upsert_simple_product( string $slug, string $name, string $price, string $desc ): array {
		$existing_id = self::get_post_id_by_slug( $slug, 'product' );

		if ( $existing_id > 0 ) {
			$product = wc_get_product( $existing_id );
			if ( ! $product instanceof WC_Product ) {
				$product = new WC_Product_Simple( $existing_id );
			}
			$action = 'updated';
		} else {
			$product = new WC_Product_Simple();
			$action  = 'created';
		}

		$product->set_name( $name );
		$product->set_slug( $slug );
		$product->set_regular_price( $price );
		$product->set_virtual( true );
		$product->set_catalog_visibility( 'visible' );
		$product->set_description( wp_kses_post( $desc ) );
		$product->set_short_description( wp_kses_post( $desc ) );
		$product->set_status( 'publish' );
		$product->set_manage_stock( false );

		$product_id = $product->save();

		$saved = wc_get_product( $product_id );
		if ( $saved instanceof WC_Product && class_exists( 'LPNW_WooCommerce_Store' ) ) {
			LPNW_WooCommerce_Store::apply_tier_product_flags( $saved );
			$saved->save();
		}

		wc_delete_product_transients( $product_id );

		return array(
			'id'     => $product_id,
			'action' => $action,
		);
	}

	/**
	 * Resolve a published (or any-status) post ID by slug.
	 *
	 * @param string $slug      Post name.
	 * @param string $post_type Post type.
	 * @return int Post ID or 0.
	 */
	private static function get_post_id_by_slug( string $slug, string $post_type ): int {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'name'                   => $slug,
				'posts_per_page'         => 1,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $query->posts[0] ) ) {
			return 0;
		}

		return (int) $query->posts[0];
	}

	/**
	 * Build Primary and Subscriber menus. WooCommerce pages are not added; existing WC links are cleared from Primary.
	 *
	 * @return array{menus: array<string, mixed>, notices: array<int, string>}
	 */
	private static function configure_menus(): array {
		$notices = array();
		$menus   = array();

		if ( ! function_exists( 'wp_update_nav_menu_item' ) ) {
			require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
		}

		$locations_before    = get_nav_menu_locations();
		$previous_primary_id = ( is_array( $locations_before ) && isset( $locations_before['primary'] ) )
			? (int) $locations_before['primary']
			: 0;

		$primary_menu_id = self::get_or_create_nav_menu( self::MENU_PRIMARY );
		if ( is_wp_error( $primary_menu_id ) ) {
			$notices[] = 'Could not create Primary menu: ' . $primary_menu_id->get_error_message();
			return array(
				'menus'   => $menus,
				'notices' => $notices,
			);
		}

		$wc_on_primary_before = self::count_woocommerce_nav_items( (int) $primary_menu_id );
		self::empty_nav_menu_items( (int) $primary_menu_id );

		self::add_primary_menu_items( (int) $primary_menu_id );
		$menus['primary_menu_id']                          = (int) $primary_menu_id;
		$menus['woocommerce_items_cleared_from_primary'] = $wc_on_primary_before;

		$subscriber_menu_id = self::get_or_create_nav_menu( self::MENU_SUBSCRIBER );
		if ( is_wp_error( $subscriber_menu_id ) ) {
			$notices[] = 'Could not create Subscriber menu: ' . $subscriber_menu_id->get_error_message();
		} else {
			self::empty_nav_menu_items( (int) $subscriber_menu_id );
			self::add_subscriber_menu_items( (int) $subscriber_menu_id );
			$menus['subscriber_menu_id'] = (int) $subscriber_menu_id;
		}

		$locations = get_nav_menu_locations();
		if ( ! is_array( $locations ) ) {
			$locations = array();
		}

		$locations['primary'] = (int) $primary_menu_id;

		$registered = get_registered_nav_menus();
		if ( isset( $registered['lpnw_subscriber'] ) && ! is_wp_error( $subscriber_menu_id ) ) {
			$locations['lpnw_subscriber'] = (int) $subscriber_menu_id;
		} elseif ( ! isset( $registered['lpnw_subscriber'] ) ) {
			$notices[] = 'Theme location "lpnw_subscriber" is not registered; Subscriber menu was created but not assigned. Update the child theme or assign the menu manually.';
		}

		set_theme_mod( 'nav_menu_locations', $locations );

		if ( $previous_primary_id > 0
			&& $previous_primary_id !== (int) $primary_menu_id
			&& class_exists( 'WooCommerce' )
		) {
			$removed = self::remove_woocommerce_page_items_from_menu( $previous_primary_id );
			if ( $removed > 0 ) {
				$menus['woocommerce_items_removed_from_previous_primary_menu'] = $removed;
			}
		}

		$menus['theme_locations'] = array(
			'primary' => (int) $primary_menu_id,
		);
		if ( isset( $registered['lpnw_subscriber'] ) && ! is_wp_error( $subscriber_menu_id ) ) {
			$menus['theme_locations']['lpnw_subscriber'] = (int) $subscriber_menu_id;
		}

		return array(
			'menus'   => $menus,
			'notices' => $notices,
		);
	}

	/**
	 * Count nav menu items that point at WooCommerce shop, cart, checkout, or my account pages.
	 *
	 * @param int $menu_id Nav menu term ID.
	 * @return int
	 */
	private static function count_woocommerce_nav_items( int $menu_id ): int {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}

		$wc_page_ids = self::get_woocommerce_page_ids();
		if ( empty( $wc_page_ids ) ) {
			return 0;
		}

		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
		if ( empty( $items ) || ! is_array( $items ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $items as $item ) {
			if ( ! $item instanceof WP_Post ) {
				continue;
			}
			if ( (int) $item->object_id > 0 && in_array( (int) $item->object_id, $wc_page_ids, true ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * @return array<int, int>
	 */
	private static function get_woocommerce_page_ids(): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return array();
		}

		$wc_page_ids = array();
		foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $page_key ) {
			$id = (int) wc_get_page_id( $page_key );
			if ( $id > 0 ) {
				$wc_page_ids[] = $id;
			}
		}

		return array_values( array_unique( $wc_page_ids ) );
	}

	/**
	 * Delete nav menu items that point at WooCommerce shop, cart, checkout, or my account pages.
	 *
	 * @param int $menu_id Nav menu term ID.
	 * @return int Number of items removed.
	 */
	private static function remove_woocommerce_page_items_from_menu( int $menu_id ): int {
		$wc_page_ids = self::get_woocommerce_page_ids();

		if ( empty( $wc_page_ids ) ) {
			return 0;
		}

		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
		if ( empty( $items ) || ! is_array( $items ) ) {
			return 0;
		}

		$removed = 0;
		foreach ( $items as $item ) {
			if ( ! $item instanceof WP_Post ) {
				continue;
			}
			if ( (int) $item->object_id > 0 && in_array( (int) $item->object_id, $wc_page_ids, true ) ) {
				wp_delete_post( $item->ID, true );
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * @param int $menu_id Nav menu term ID.
	 */
	private static function empty_nav_menu_items( int $menu_id ): void {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
		if ( empty( $items ) || ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( $item instanceof WP_Post ) {
				wp_delete_post( $item->ID, true );
			}
		}
	}

	/**
	 * @param int $menu_id Nav menu term ID.
	 */
	private static function add_primary_menu_items( int $menu_id ): void {
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => __( 'Home', 'lpnw-alerts' ),
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);

		$pages = array(
			'pricing' => __( 'Pricing', 'lpnw-alerts' ),
			'about'   => __( 'About', 'lpnw-alerts' ),
			'contact' => __( 'Contact', 'lpnw-alerts' ),
		);

		foreach ( $pages as $slug => $label ) {
			self::add_page_or_fallback_link( $menu_id, $slug, $label );
		}
	}

	/**
	 * @param int $menu_id Nav menu term ID.
	 */
	private static function add_subscriber_menu_items( int $menu_id ): void {
		$pages = array(
			'dashboard'   => __( 'Dashboard', 'lpnw-alerts' ),
			'preferences' => __( 'Preferences', 'lpnw-alerts' ),
			'map'         => __( 'Property Map', 'lpnw-alerts' ),
			'saved'       => __( 'Saved Properties', 'lpnw-alerts' ),
		);

		foreach ( $pages as $slug => $label ) {
			self::add_page_or_fallback_link( $menu_id, $slug, $label );
		}
	}

	/**
	 * @param int    $menu_id Nav menu term ID.
	 * @param string $slug    Page slug.
	 * @param string $label   Menu label.
	 */
	private static function add_page_or_fallback_link( int $menu_id, string $slug, string $label ): void {
		$page_id = self::get_post_id_by_slug( $slug, 'page' );

		if ( $page_id > 0 ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $label,
					'menu-item-object-id' => $page_id,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
			return;
		}

		$url = trailingslashit( home_url( '/' . $slug . '/' ) );

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => $label,
				'menu-item-url'    => $url,
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);
	}

	/**
	 * @param string $name Menu name (term name).
	 * @return int|\WP_Error
	 */
	private static function get_or_create_nav_menu( string $name ) {
		$menus = wp_get_nav_menus();
		foreach ( $menus as $menu ) {
			if ( $menu->name === $name ) {
				return (int) $menu->term_id;
			}
		}

		return wp_create_nav_menu( $name );
	}
}
