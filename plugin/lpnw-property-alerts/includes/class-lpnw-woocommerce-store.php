<?php
/**
 * WooCommerce product behaviour for LPNW tier products.
 *
 * Keeps catalog tidy: free tier hidden from shop, one subscription per cart line.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product flags and visibility for LPNW slugs.
 */
final class LPNW_WooCommerce_Store {

	private static bool $did_register_hooks = false;

	public const PRODUCT_SLUG_FREE = 'lpnw-free';

	public const PRODUCT_SLUG_PRO = 'lpnw-pro';

	public const PRODUCT_SLUG_VIP = 'lpnw-vip';

	/**
	 * @return array<int, string>
	 */
	public static function tier_product_slugs(): array {
		return array(
			self::PRODUCT_SLUG_FREE,
			self::PRODUCT_SLUG_PRO,
			self::PRODUCT_SLUG_VIP,
		);
	}

	public static function init(): void {
		if ( ! self::$did_register_hooks ) {
			self::$did_register_hooks = true;
			add_action( 'plugins_loaded', array( __CLASS__, 'maybe_sync_stored_tier_products' ), 20 );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_product_get_catalog_visibility', array( __CLASS__, 'filter_catalog_visibility' ), 10, 2 );
		add_filter( 'woocommerce_product_is_sold_individually', array( __CLASS__, 'filter_sold_individually' ), 10, 2 );
	}

	/**
	 * Once per plugin version: persist virtual, sold individually, and visibility on tier products.
	 */
	public static function maybe_sync_stored_tier_products(): void {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
			return;
		}

		$key     = 'lpnw_wc_tier_product_sync_version';
		$last    = (string) get_option( $key, '' );
		$current = defined( 'LPNW_VERSION' ) ? LPNW_VERSION : '0';

		if ( version_compare( $last, $current, '>=' ) ) {
			return;
		}

		foreach ( self::tier_product_slugs() as $slug ) {
			$ids = wc_get_products(
				array(
					'slug'   => $slug,
					'limit'  => 1,
					'return' => 'ids',
					'status' => array( 'publish', 'draft', 'pending', 'private' ),
				)
			);
			if ( empty( $ids[0] ) ) {
				continue;
			}
			$product = wc_get_product( (int) $ids[0] );
			if ( $product instanceof WC_Product ) {
				self::apply_tier_product_flags( $product );
				$product->save();
				wc_delete_product_transients( (int) $ids[0] );
			}
		}

		update_option( $key, $current );
	}

	/**
	 * Hide the free product from category and search; still reachable by direct URL.
	 *
	 * @param string     $visibility Catalog visibility.
	 * @param WC_Product $product    Product.
	 */
	public static function filter_catalog_visibility( string $visibility, $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return $visibility;
		}
		if ( self::PRODUCT_SLUG_FREE === $product->get_slug() ) {
			return 'hidden';
		}
		return $visibility;
	}

	/**
	 * One unit per line for tier products (simpler checkout and support).
	 *
	 * @param bool       $sold       Whether sold individually.
	 * @param WC_Product $product    Product.
	 */
	public static function filter_sold_individually( bool $sold, $product ): bool {
		if ( ! $product instanceof WC_Product ) {
			return $sold;
		}
		if ( in_array( $product->get_slug(), self::tier_product_slugs(), true ) ) {
			return true;
		}
		return $sold;
	}

	/**
	 * Apply stored flags when saving an LPNW tier product (setup script and manual edits).
	 *
	 * @param WC_Product $product Product instance.
	 */
	public static function apply_tier_product_flags( WC_Product $product ): void {
		$slug = $product->get_slug();
		if ( ! in_array( $slug, self::tier_product_slugs(), true ) ) {
			return;
		}

		$product->set_virtual( true );
		$product->set_manage_stock( false );
		$product->set_sold_individually( true );

		if ( self::PRODUCT_SLUG_FREE === $slug ) {
			$product->set_catalog_visibility( 'hidden' );
		} else {
			$product->set_catalog_visibility( 'visible' );
		}
	}
}
