<?php
/**
 * Shared helpers for HTTP cron triggers (?lpnw_cron=tick).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates cron URL secret (wp-config constant). Fails closed when unset.
 */
class LPNW_Cron_HTTP {

	/**
	 * Whether the request may run wp_cron().
	 *
	 * `LPNW_CRON_SECRET` must be defined and non-empty in wp-config.php, and
	 * `$_GET['key']` must match. This avoids an accidental open endpoint if the
	 * constant is missing on production.
	 *
	 * @return bool
	 */
	public static function request_is_authorized(): bool {
		if ( ! defined( 'LPNW_CRON_SECRET' ) || '' === (string) LPNW_CRON_SECRET ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Query key is verified against LPNW_CRON_SECRET, not a WP nonce.
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';

		return hash_equals( (string) LPNW_CRON_SECRET, $key );
	}
}
