<?php
if ( ! defined( 'ABSPATH' ) ) { return; }
add_action( 'wp_loaded', function() {
	if ( empty( $_GET['lpnw_products'] ) || 'list' !== $_GET['lpnw_products'] ) { return; }
	if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) { return; }
	header( 'Content-Type: text/plain; charset=utf-8' );

	// Check all product post types
	global $wpdb;
	$products = $wpdb->get_results(
		"SELECT ID, post_title, post_name, post_status, post_type FROM {$wpdb->posts}
		 WHERE post_type IN ('product', 'product_variation')
		 ORDER BY ID ASC LIMIT 50"
	);
	echo "All product posts:\n";
	foreach ($products as $p) {
		$price = get_post_meta($p->ID, '_price', true);
		echo "  ID {$p->ID}: {$p->post_title} | slug={$p->post_name} | status={$p->post_status} | type={$p->post_type} | price={$price}\n";
	}

	if (empty($products)) {
		echo "  No products found in database.\n";

		// Check if WooCommerce pages exist
		$woo_pages = $wpdb->get_results(
			"SELECT ID, post_title, post_name FROM {$wpdb->posts}
			 WHERE post_type = 'page' AND post_name IN ('shop', 'cart', 'checkout', 'my-account')
			 LIMIT 10"
		);
		echo "\nWooCommerce pages:\n";
		foreach ($woo_pages as $p) {
			echo "  {$p->post_name}: ID {$p->ID}\n";
		}
	}

	@unlink(__FILE__);
	exit;
}, 0 );
