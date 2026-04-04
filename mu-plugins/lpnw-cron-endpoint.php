<?php
/**
 * Custom cron endpoint - bypasses 20i's wp-cron.php bot protection.
 *
 * CRON SERVICE URL:
 * https://example.com/?lpnw_cron=tick
 * Optional: &key=SECRET when LPNW_CRON_SECRET is defined in wp-config.php.
 *
 * Runs the same worker logic as wp-cron.php (scheduled hooks), but via a normal
 * front URL 20i does not block. Do not call wp_cron() from init then exit: since
 * WP 5.7, wp_cron() defers to wp_loaded, so an early exit never runs jobs.
 *
 * This file lives permanently in mu-plugins. Do not delete it.
 *
 * @package LPNW_MuPlugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_action(
	'wp_loaded',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Cron ping uses optional shared secret, not a form nonce.
		if ( ! isset( $_GET['lpnw_cron'] ) || 'tick' !== $_GET['lpnw_cron'] ) {
			return;
		}

		$helper = WP_CONTENT_DIR . '/plugins/lpnw-property-alerts/includes/class-lpnw-cron-http.php';
		if ( is_readable( $helper ) ) {
			require_once $helper;
		}

		$allowed = true;
		if ( class_exists( 'LPNW_Cron_HTTP', false ) ) {
			$allowed = LPNW_Cron_HTTP::request_is_authorized();
		} elseif ( defined( 'LPNW_CRON_SECRET' ) && '' !== (string) LPNW_CRON_SECRET ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Shared secret in query string for external cron.
			$key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';
			$allowed = hash_equals( (string) LPNW_CRON_SECRET, $key );
		}

		if ( ! $allowed ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( "Forbidden\n" );
			exit;
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core entry script; defines DOING_CRON after its own guard.
		require_once ABSPATH . 'wp-cron.php';

		// wp-cron.php uses return when another process holds the lock (included from this scope).
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
		}
		echo esc_html( "cron_lock_busy\n" );
		exit;
	},
	999
);
