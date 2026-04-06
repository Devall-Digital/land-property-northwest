<?php
/**
 * Plugin deactivation handler.
 *
 * Clears scheduled cron events. Does NOT drop tables (that happens on uninstall).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'lpnw_cron_planning' );
		wp_clear_scheduled_hook( 'lpnw_cron_epc' );
		wp_clear_scheduled_hook( 'lpnw_cron_landregistry' );
		wp_clear_scheduled_hook( 'lpnw_cron_auctions' );
		wp_clear_scheduled_hook( 'lpnw_cron_portals' );
		wp_clear_scheduled_hook( 'lpnw_cron_portal_rightmove' );
		wp_clear_scheduled_hook( 'lpnw_cron_portal_zoopla' );
		wp_clear_scheduled_hook( 'lpnw_cron_portal_onthemarket' );
		wp_clear_scheduled_hook( 'lpnw_cron_dispatch_alerts' );
		wp_clear_scheduled_hook( 'lpnw_cron_free_digest' );
		wp_clear_scheduled_hook( 'lpnw_cron_data_retention' );

		flush_rewrite_rules();
	}
}
