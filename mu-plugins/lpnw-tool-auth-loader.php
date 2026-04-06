<?php
/**
 * Defines lpnw_tool_query_key_ok() for one-shot mu-plugins in this directory.
 *
 * Accepts &key= matching LPNW_CRON_SECRET, LPNW_PAGE_SYNC_SECRET, LPNW_LOGIN_AS_SECRET
 * (from wp-config.php), or the dev fallback lpnw2026setup.
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
	 * @param string $provided Raw key from ?key=.
	 */
	function lpnw_tool_query_key_ok( string $provided ): bool {
		return hash_equals( 'lpnw2026setup', trim( $provided ) );
	}
}
