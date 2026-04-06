<?php
if ( ! defined( 'ABSPATH' ) ) { return; }
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';
add_action( 'wp_loaded', function() {
	if ( empty( $_GET['lpnw_tier'] ) ) { return; }
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}
	$target = sanitize_text_field( $_GET['lpnw_tier'] );
	if ( ! in_array( $target, array( 'free', 'pro', 'vip' ), true ) ) { return; }
	header( 'Content-Type: text/plain; charset=utf-8' );

	$user = get_user_by( 'email', 'admin@codevall.co.uk' );
	if ( ! $user ) { echo "User not found.\n"; exit; }

	$product_ids = array( 'pro' => 22, 'vip' => 23 );

	echo "User: {$user->display_name} (ID {$user->ID})\n";
	echo "Target: {$target}\n\n";

	try {
		// Cancel existing - use WC API for HPOS compatibility
		$orders = wc_get_orders( array(
			'customer' => $user->ID,
			'status'   => array( 'completed', 'processing' ),
			'limit'    => 50,
			'return'   => 'ids',
		) );
		echo "Active orders to cancel: " . count( $orders ) . "\n";
		foreach ( $orders as $oid ) {
			$order = wc_get_order( $oid );
			if ( $order ) {
				$order->set_status( 'cancelled' );
				$order->save();
				echo "  Cancelled #{$oid}\n";
			}
		}

		if ( 'free' === $target ) {
			echo "\nDowngraded to FREE.\n";
		} else {
			$pid = $product_ids[ $target ];
			$product = wc_get_product( $pid );
			if ( ! $product ) { echo "Product ID {$pid} not found.\n"; exit; }

			echo "Product: {$product->get_name()} (GBP {$product->get_price()})\n";

			$order = wc_create_order( array( 'customer_id' => $user->ID ) );
			$order->add_product( $product );
			$order->calculate_totals();
			$order->set_status( 'completed' );
			$order->save();
			echo "Order #{$order->get_id()} created.\n";
		}

		$tier = LPNW_Subscriber::get_tier( $user->ID );
		echo "\nVerified tier: {$tier}\n";
		echo "Done.\n";
	} catch ( \Throwable $e ) {
		echo "Error: {$e->getMessage()}\n at {$e->getFile()}:{$e->getLine()}\n";
	}
	exit;
}, 0 );
