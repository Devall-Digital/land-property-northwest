<?php
/**
 * Defines lpnw_tool_query_key_ok() for one-shot mu-plugins in this directory.
 *
 * Accepts &key= matching LPNW_CRON_SECRET, LPNW_PAGE_SYNC_SECRET, or LPNW_LOGIN_AS_SECRET
 * from wp-config.php only (see plugin includes/class-lpnw-tool-auth.php).
 *
 * Usage at top of another mu-plugin after ABSPATH check:
 *   require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';
 *
 * @package LPNW_MuPlugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'lpnw_tool_query_key_ok' ) ) {
	return;
}

if ( defined( 'WP_PLUGIN_DIR' ) && is_readable( WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-tool-auth.php' ) ) {
	require_once WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-tool-auth.php';
}

if ( ! function_exists( 'lpnw_tool_query_key_ok' ) ) {
	/**
	 * Stub when the LPNW plugin is missing: no key is accepted.
	 *
	 * @param string $provided Raw key from ?key=.
	 */
	function lpnw_tool_query_key_ok( string $provided ): bool {
		return false;
	}
}
