<?php
/**
 * Custom cron endpoint - bypasses 20i's wp-cron.php bot protection.
 *
 * CRON SERVICE URL:
 * https://land-property-northwest.co.uk/?lpnw_cron=tick
 *
 * This triggers WordPress's built-in cron scheduler exactly like
 * wp-cron.php would, but through a normal page request that 20i
 * doesn't block.
 *
 * This file lives permanently in mu-plugins. Do not delete it.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_action( 'init', function () {
	if ( ! isset( $_GET['lpnw_cron'] ) || 'tick' !== $_GET['lpnw_cron'] ) {
		return;
	}

	if ( ! defined( 'DOING_CRON' ) ) {
		define( 'DOING_CRON', true );
	}

	wp_cron();

	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 'ok ' . gmdate( 'Y-m-d H:i:s' ) . "\n";
	exit;
}, 0 );
