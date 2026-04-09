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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ), 26 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_product_trust_note' ), 5 );
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

		echo '<p class="lpnw-wc-product-note">' . esc_html__( 'Billed monthly after checkout. Secure card payment. Cancel any time from your account.', 'lpnw-theme' ) . '</p>';
	}
}
