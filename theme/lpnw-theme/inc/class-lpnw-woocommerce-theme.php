<?php
/**
 * WooCommerce storefront polish (product, cart, checkout).
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues scoped styles and small UX hooks for tier checkout.
 */
final class LPNW_WooCommerce_Theme {

	/**
	 * Register hooks.
	 */
	public static function bootstrap(): void {
		add_action( 'after_setup_theme', array( __CLASS__, 'theme_support' ), 11 );
		add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ), 26 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'woocommerce_before_main_content', array( __CLASS__, 'render_skip_link' ), 1 );
		add_action( 'woocommerce_before_main_content', array( __CLASS__, 'open_main_anchor' ), 2 );
		add_action( 'woocommerce_after_main_content', array( __CLASS__, 'close_main_anchor' ), 999 );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'open_shop_toolbar' ), 5 );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'close_shop_toolbar' ), 40 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_product_trust_note' ), 5 );
	}

	/**
	 * WooCommerce image widths and product grid (classic templates).
	 */
	public static function theme_support(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_theme_support(
			'woocommerce',
			array(
				'thumbnail_image_width' => 420,
				'single_image_width'    => 640,
				'product_grid'          => array(
					'default_rows'    => 3,
					'min_rows'        => 1,
					'max_rows'        => 8,
					'default_columns' => 3,
					'min_columns'     => 1,
					'max_columns'     => 4,
				),
			)
		);
	}

	/**
	 * Load WooCommerce-specific stylesheet on shop pages.
	 */
	public static function enqueue_styles(): void {
		if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
			return;
		}

		$path = get_stylesheet_directory() . '/assets/css/woocommerce.css';
		$uri  = get_stylesheet_directory_uri() . '/assets/css/woocommerce.css';
		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_style(
			'lpnw-woocommerce',
			$uri,
			array( 'lpnw-child' ),
			(string) filemtime( $path )
		);
	}

	/**
	 * Add a body class for WooCommerce views.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			$classes[] = 'lpnw-woocommerce';
		}
		return $classes;
	}

	/**
	 * Keyboard / screen reader: skip past header and notices to main store content.
	 */
	public static function render_skip_link(): void {
		if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
			return;
		}

		echo '<a class="lpnw-wc-skip-link" href="#lpnw-wc-main">' . esc_html__( 'Skip to shop content', 'lpnw-theme' ) . '</a>';
	}

	/**
	 * Landmark for skip link (tabindex allows focus after activate).
	 */
	public static function open_main_anchor(): void {
		echo '<div id="lpnw-wc-main" class="lpnw-wc-main" tabindex="-1">';
	}

	/**
	 * Close main landmark wrapper.
	 */
	public static function close_main_anchor(): void {
		echo '</div>';
	}

	/**
	 * Flex row for sort + result count (avoids float overlap when default Woo layout CSS is dequeued).
	 */
	public static function open_shop_toolbar(): void {
		if ( ! self::is_product_archive_listing() ) {
			return;
		}
		echo '<div class="lpnw-wc-shop-toolbar">';
	}

	/**
	 * Close shop toolbar wrapper.
	 */
	public static function close_shop_toolbar(): void {
		if ( ! self::is_product_archive_listing() ) {
			return;
		}
		echo '</div>';
	}

	/**
	 * Shop main page or product category/tag archives (hooks that output the loop toolbar).
	 */
	private static function is_product_archive_listing(): bool {
		if ( ! function_exists( 'is_shop' ) || ! function_exists( 'is_product_taxonomy' ) ) {
			return false;
		}
		return is_shop() || is_product_taxonomy();
	}

	/**
	 * Short billing clarity note above add to cart for Pro/VIP products.
	 */
	public static function render_product_trust_note(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_slug' ) ) {
			return;
		}

		$slug = (string) $product->get_slug();
		if ( ! in_array( $slug, array( 'lpnw-pro', 'lpnw-vip' ), true ) ) {
			return;
		}

		echo '<p class="lpnw-wc-product-note" role="note">' . esc_html__( 'Billed monthly after checkout. Secure card payment. Cancel any time from your account.', 'lpnw-theme' ) . '</p>';
	}
}
