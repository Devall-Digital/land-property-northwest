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
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'render_shop_trust_line' ), 45 );
		add_filter( 'woocommerce_post_class', array( __CLASS__, 'product_post_class' ), 10, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_product_trust_note' ), 5 );
		add_action( 'wp', array( __CLASS__, 'configure_product_images_as_brand_logo' ), 99 );
		add_filter( 'woocommerce_product_get_image', array( __CLASS__, 'filter_product_get_image' ), 10, 6 );
		add_filter( 'woocommerce_rest_prepare_product_object', array( __CLASS__, 'rest_product_use_brand_logo' ), 10, 3 );
		add_filter( 'woocommerce_rest_prepare_product_variation_object', array( __CLASS__, 'rest_variation_use_brand_logo' ), 10, 3 );
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
	 * Use the shared brand logo anywhere WooCommerce renders a product image (cart, checkout, orders, emails).
	 * Single product keeps the default gallery removed; a simple logo is output separately.
	 */
	public static function configure_product_images_as_brand_logo(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
		add_action( 'woocommerce_before_single_product_summary', array( __CLASS__, 'render_single_product_brand_logo' ), 20 );
	}

	/**
	 * Replace WC product image markup with the theme brand logo asset.
	 *
	 * @param string                       $image       Default image HTML.
	 * @param WC_Product|object|null       $product     Product instance.
	 * @param string|int[]                 $size        Image size.
	 * @param string|array<string, string> $attr        Img attributes.
	 * @param bool                         $placeholder Whether placeholder was allowed.
	 * @param string                       $deprecated  Legacy argument in some WC versions.
	 * @return string
	 */
	public static function filter_product_get_image( $image, $product, $size = '', $attr = array(), $placeholder = true, $deprecated = '' ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- WC passes placeholder + legacy 6th arg.
		unset( $placeholder, $deprecated );
		if ( ! $product instanceof \WC_Product ) {
			return $image;
		}
		$markup = self::get_brand_logo_img_markup( $attr );
		return '' !== $markup ? $markup : $image;
	}

	/**
	 * Store API (cart / checkout blocks): serve the brand logo as the only product image.
	 *
	 * @param \WP_REST_Response          $response Response.
	 * @param \WC_Product|\WP_Post|mixed $prepared_item Product or post (unused; filter signature).
	 * @param \WP_REST_Request           $request  Request.
	 * @return \WP_REST_Response
	 */
	public static function rest_product_use_brand_logo( $response, $prepared_item, $request ) {
		if ( ! $request instanceof \WP_REST_Request || ! $response instanceof \WP_REST_Response ) {
			return $response;
		}
		$route = (string) $request->get_route();
		if ( false === strpos( $route, '/wc/store/' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		$response->set_data( self::replace_rest_product_images( $data ) );
		return $response;
	}

	/**
	 * Store API: same logo treatment for variations in block cart / checkout.
	 *
	 * @param \WP_REST_Response           $response Response.
	 * @param \WC_Product_Variation|mixed $prepared_item Variation (unused; filter signature).
	 * @param \WP_REST_Request            $request  Request.
	 * @return \WP_REST_Response
	 */
	public static function rest_variation_use_brand_logo( $response, $prepared_item, $request ) {
		if ( ! $request instanceof \WP_REST_Request || ! $response instanceof \WP_REST_Response ) {
			return $response;
		}
		$route = (string) $request->get_route();
		if ( false === strpos( $route, '/wc/store/' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		$response->set_data( self::replace_rest_variation_images( $data ) );
		return $response;
	}

	/**
	 * One centred brand logo above the single-product summary (no gallery).
	 */
	public static function render_single_product_brand_logo(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		echo '<div class="lpnw-wc-single-product-logo">';
		echo wp_kses_post(
			$product->get_image(
				'woocommerce_single',
				array(
					'class'   => 'lpnw-wc-single-product-logo__img',
					'loading' => 'eager',
				)
			)
		);
		echo '</div>';
	}

	/**
	 * Build <img> for the brand logo; merges classes from WooCommerce $attr.
	 *
	 * @param array<string, string>|string $attr Requested attributes.
	 * @return string
	 */
	private static function get_brand_logo_img_markup( $attr ): string {
		if ( ! function_exists( 'lpnw_theme_get_brand_logo_url' ) ) {
			return '';
		}
		$url = lpnw_theme_get_brand_logo_url();
		if ( '' === $url ) {
			return '';
		}
		$alt = get_bloginfo( 'name', 'display' );

		$attr_arr = array();
		if ( is_array( $attr ) ) {
			$attr_arr = $attr;
		}

		$classes           = isset( $attr_arr['class'] ) ? trim( (string) $attr_arr['class'] ) . ' lpnw-wc-product-brand-logo' : 'lpnw-wc-product-brand-logo';
		$attr_out          = array_merge(
			array(
				'src'      => $url,
				'alt'      => $alt,
				'class'    => trim( $classes ),
				'decoding' => 'async',
			),
			$attr_arr
		);
		$attr_out['src']   = $url;
		$attr_out['alt']   = $alt;
		$attr_out['class'] = trim( $classes );

		if ( ! isset( $attr_out['loading'] ) ) {
			$attr_out['loading'] = 'lazy';
		}

		if ( function_exists( 'wc_implode_html_attributes' ) ) {
			return '<img ' . wc_implode_html_attributes( $attr_out ) . ' />';
		}

		return sprintf(
			'<img src="%s" alt="%s" class="%s" loading="%s" decoding="async" />',
			esc_url( $url ),
			esc_attr( $alt ),
			esc_attr( $attr_out['class'] ),
			esc_attr( (string) $attr_out['loading'] )
		);
	}

	/**
	 * Replace the REST `images` list with a single brand logo entry.
	 *
	 * @param array<string, mixed> $data Response data.
	 * @return array<string, mixed>
	 */
	private static function replace_rest_product_images( array $data ): array {
		if ( ! function_exists( 'lpnw_theme_get_brand_logo_url' ) ) {
			return $data;
		}
		$url  = lpnw_theme_get_brand_logo_url();
		$name = get_bloginfo( 'name', 'display' );
		if ( '' === $url ) {
			return $data;
		}
		$data['images'] = array(
			array(
				'id'        => 0,
				'src'       => $url,
				'thumbnail' => $url,
				'srcset'    => '',
				'sizes'     => '',
				'name'      => $name,
				'alt'       => $name,
			),
		);
		return $data;
	}

	/**
	 * Variation REST payloads use a single `image` object; mirror that for Store API consumers.
	 *
	 * @param array<string, mixed> $data Response data.
	 * @return array<string, mixed>
	 */
	private static function replace_rest_variation_images( array $data ): array {
		if ( ! function_exists( 'lpnw_theme_get_brand_logo_url' ) ) {
			return $data;
		}
		$url  = lpnw_theme_get_brand_logo_url();
		$name = get_bloginfo( 'name', 'display' );
		if ( '' === $url ) {
			return $data;
		}
		$logo          = array(
			'id'   => 0,
			'src'  => $url,
			'name' => $name,
			'alt'  => $name,
		);
		$data['image'] = $logo;
		if ( isset( $data['images'] ) ) {
			$data['images'] = array(
				array(
					'id'        => 0,
					'src'       => $url,
					'thumbnail' => $url,
					'srcset'    => '',
					'sizes'     => '',
					'name'      => $name,
					'alt'       => $name,
				),
			);
		}
		return $data;
	}

	/**
	 * Match pricing page trust copy (Stripe / cancel messaging).
	 */
	public static function render_shop_trust_line(): void {
		if ( ! self::is_product_archive_listing() ) {
			return;
		}

		echo '<p class="lpnw-wc-shop-trust">' . esc_html__( 'Cancel any time. Card payments are processed securely by Stripe.', 'lpnw-theme' ) . '</p>';
	}

	/**
	 * Loop / single product wrapper classes for pricing-parity styling (featured, tier slugs).
	 *
	 * @param array<int, string> $classes CSS classes.
	 * @param WC_Product         $product Product object.
	 * @return array<int, string>
	 */
	public static function product_post_class( array $classes, $product ): array {
		if ( ! $product instanceof \WC_Product ) {
			return $classes;
		}

		if ( method_exists( $product, 'is_featured' ) && $product->is_featured() ) {
			$classes[] = 'lpnw-wc-product--featured';
		}

		$slug = (string) $product->get_slug();
		if ( 'lpnw-vip' === $slug ) {
			$classes[] = 'lpnw-wc-product--vip';
		} elseif ( 'lpnw-pro' === $slug ) {
			$classes[] = 'lpnw-wc-product--pro';
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

		echo '<p class="lpnw-wc-product-note" role="note">' . esc_html__( 'Billed monthly after checkout. Secure card payment. Cancel any time from your account.', 'lpnw-theme' ) . '</p>';
	}
}
