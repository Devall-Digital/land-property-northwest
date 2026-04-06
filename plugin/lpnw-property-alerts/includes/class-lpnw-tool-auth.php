<?php
/**
 * Shared query-string key check for one-shot tools dropped in mu-plugins or web root.
 *
 * Load only after WordPress bootstrap (wp-load.php) so wp-config constants exist.
 * Accepts &key= matching (in order): LPNW_CRON_SECRET, LPNW_PAGE_SYNC_SECRET,
 * LPNW_LOGIN_AS_SECRET. No default key: define at least one in wp-config.php.
 *
 * @package LPNW_Property_Alerts
 */

if ( ! function_exists( 'lpnw_tool_query_key_ok' ) ) {
	/**
	 * Whether a tool URL key is authorised.
	 *
	 * @param string $provided Raw key from ?key= (already unslashed by caller if from $_GET).
	 */
	function lpnw_tool_query_key_ok( string $provided ): bool {
		$provided = trim( $provided );
		if ( '' === $provided ) {
			return false;
		}

		if ( defined( 'LPNW_CRON_SECRET' ) && '' !== (string) LPNW_CRON_SECRET
			&& hash_equals( (string) LPNW_CRON_SECRET, $provided ) ) {
			return true;
		}
		if ( defined( 'LPNW_PAGE_SYNC_SECRET' ) && '' !== (string) LPNW_PAGE_SYNC_SECRET
			&& hash_equals( (string) LPNW_PAGE_SYNC_SECRET, $provided ) ) {
			return true;
		}
		if ( defined( 'LPNW_LOGIN_AS_SECRET' ) && '' !== (string) LPNW_LOGIN_AS_SECRET
			&& hash_equals( (string) LPNW_LOGIN_AS_SECRET, $provided ) ) {
			return true;
		}

		return false;
	}
}
