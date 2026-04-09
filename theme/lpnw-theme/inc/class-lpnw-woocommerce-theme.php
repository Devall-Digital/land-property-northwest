<?php
/**
 * WooCommerce storefront polish (product, cart, checkout, My Account).
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
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_product_trust_note' ), 5 );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'filter_account_menu_items' ), 999 );
		add_action( 'woocommerce_before_account_navigation', array( __CLASS__, 'render_member_tool_strip' ), 5 );
		add_action( 'woocommerce_account_content', array( __CLASS__, 'render_member_tool_strip' ), 1 );
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
		$wc_area = ( function_exists( 'is_woocommerce' ) && is_woocommerce() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() );
		if ( ! $wc_area ) {
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
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$classes[] = 'lpnw-woocommerce';
		}
		return $classes;
	}

	/**
	 * Trim default My Account items that do not apply to virtual alert subscriptions.
	 *
	 * @param array<string, string> $items Endpoint slug => label.
	 * @return array<string, string>
	 */
	public static function filter_account_menu_items( array $items ): array {
		unset( $items['downloads'] );
		if ( isset( $items['dashboard'] ) ) {
			$items['dashboard'] = __( 'Account home', 'lpnw-theme' );
		}
		return $items;
	}

	/**
	 * Wayfinding to LPNW member pages (above WooCommerce account nav).
	 */
	public static function render_member_tool_strip(): void {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		$rendered = true;

		$links = array(
			array(
				'url'   => home_url( '/dashboard/' ),
				'label' => __( 'Alert dashboard', 'lpnw-theme' ),
			),
			array(
				'url'   => home_url( '/preferences/' ),
				'label' => __( 'Alert preferences', 'lpnw-theme' ),
			),
			array(
				'url'   => home_url( '/map/' ),
				'label' => __( 'Property map', 'lpnw-theme' ),
			),
			array(
				'url'   => home_url( '/saved/' ),
				'label' => __( 'Saved properties', 'lpnw-theme' ),
			),
		);

		echo '<div class="lpnw-wc-member-strip" role="navigation" aria-label="' . esc_attr__( 'Property alerts and saved listings', 'lpnw-theme' ) . '">';
		echo '<p class="lpnw-wc-member-strip__title">' . esc_html__( 'Your alerts and listings', 'lpnw-theme' ) . '</p>';
		echo '<ul class="lpnw-wc-member-strip__list">';
		foreach ( $links as $row ) {
			echo '<li><a class="lpnw-wc-member-strip__link" href="' . esc_url( $row['url'] ) . '">' . esc_html( $row['label'] ) . '</a></li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Keyboard / screen reader: skip past header and notices to main store content.
	 */
	public static function render_skip_link(): void {
		if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
			return;
		}

		$label = ( function_exists( 'is_account_page' ) && is_account_page() )
			? __( 'Skip to account content', 'lpnw-theme' )
			: __( 'Skip to shop content', 'lpnw-theme' );
		echo '<a class="lpnw-wc-skip-link" href="#lpnw-wc-main">' . esc_html( $label ) . '</a>';
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
