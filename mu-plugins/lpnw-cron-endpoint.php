<?php
/**
 * Custom cron endpoint - bypasses 20i's wp-cron.php bot protection.
 *
 * CRON SERVICE URL:
 * https://example.com/?lpnw_cron=tick
 * Optional: &key=SECRET when LPNW_CRON_SECRET is defined in wp-config.php.
 *
 * This triggers WordPress's built-in cron scheduler exactly like
 * wp-cron.php would, but through a normal page request that 20i
 * doesn't block.
 *
 * This file lives permanently in mu-plugins. Do not delete it.
 *
 * @package LPNW_MuPlugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_action(
	'init',
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

		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		wp_cron();

		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( 'ok ' . gmdate( 'Y-m-d H:i:s' ) . "\n" );
		exit;
	},
	0
);
