<?php
/**
 * Shared helpers for HTTP cron triggers (?lpnw_cron=tick).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates optional cron URL secret (wp-config constant).
 */
class LPNW_Cron_HTTP {

	/**
	 * Whether the request may run wp_cron().
	 *
	 * If `LPNW_CRON_SECRET` is defined and non-empty, `$_GET['key']` must match.
	 * If the constant is not set, behaviour matches the legacy open endpoint
	 * (operators should define the constant on production).
	 *
	 * @return bool
	 */
	public static function request_is_authorized(): bool {
		if ( ! defined( 'LPNW_CRON_SECRET' ) || '' === (string) LPNW_CRON_SECRET ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Query key is verified against LPNW_CRON_SECRET, not a WP nonce.
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';

		return hash_equals( (string) LPNW_CRON_SECRET, $key );
	}
}
