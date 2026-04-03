<?php
/**
 * Custom cron trigger that bypasses 20i's wp-cron.php protection.
 * This mu-plugin provides an alternative URL for external cron services.
 *
 * Set your cron-job.org URL to:
 * https://land-property-northwest.co.uk/?lpnw_cron=tick
 *
 * No secret key needed - this just triggers WordPress's built-in
 * cron scheduler, same as wp-cron.php does.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_action(
	'init',
	function () {
		if ( ! isset( $_GET['lpnw_cron'] ) || 'tick' !== $_GET['lpnw_cron'] ) {
			return;
		}

		if ( class_exists( 'LPNW_Cron_HTTP', false ) && ! LPNW_Cron_HTTP::request_is_authorized() ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'Forbidden';
			exit;
		}

		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		if ( function_exists( 'wp_cron' ) ) {
			wp_cron();
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-LPNW-Cron: ok' );
		echo 'ok';
		exit;
	},
	0
);
