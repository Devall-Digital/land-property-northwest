<?php
/**
 * WooCommerce Products admin list: prevent plugin columns (e.g. Rank Math) from collapsing.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline admin styles for the product list table only.
 */
final class LPNW_Admin_Woo_Product_List {

	/**
	 * Register hooks.
	 */
	public static function bootstrap(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_list_table_fix' ), 20 );
	}

	/**
	 * Core `.widefat.fixed` uses table-layout:fixed; extra columns (SEO, Brands) then crush to ~0 width.
	 *
	 * @param string $hook Current admin page hook suffix (e.g. edit.php).
	 */
	public static function enqueue_list_table_fix( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		$css = '
body.post-type-product .wp-list-table.widefat.fixed {
	table-layout: auto;
	width: 100%;
	min-width: 960px;
}
body.post-type-product .wp-list-table th,
body.post-type-product .wp-list-table td {
	word-break: normal;
	overflow-wrap: break-word;
	vertical-align: top;
}
body.post-type-product .wp-list-table [class*="column-rank_math"] {
	min-width: 12rem;
	max-width: 22rem;
	white-space: normal;
}
body.post-type-product .wp-list-table .column-taxonomy-product_brand,
body.post-type-product .wp-list-table [class*="column-product_brand"] {
	min-width: 8rem;
	white-space: normal;
}
';

		wp_register_style( 'lpnw-admin-product-list', false, array(), '1.0' );
		wp_enqueue_style( 'lpnw-admin-product-list' );
		wp_add_inline_style( 'lpnw-admin-product-list', $css );
	}
}
