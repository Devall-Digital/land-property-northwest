<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Drops custom database tables and removes plugin options.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'lpnw_properties',
	$wpdb->prefix . 'lpnw_subscriber_preferences',
	$wpdb->prefix . 'lpnw_alert_queue',
	$wpdb->prefix . 'lpnw_saved_properties',
	$wpdb->prefix . 'lpnw_feed_log',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'lpnw_settings' );
delete_option( 'lpnw_version' );
delete_option( 'lpnw_auctions_cron_15m' );
delete_option( 'lpnw_mautic_api_url' );
delete_option( 'lpnw_mautic_api_user' );
delete_option( 'lpnw_mautic_api_password' );

wp_clear_scheduled_hook( 'lpnw_cron_planning' );
wp_clear_scheduled_hook( 'lpnw_cron_epc' );
wp_clear_scheduled_hook( 'lpnw_cron_landregistry' );
wp_clear_scheduled_hook( 'lpnw_cron_auctions' );
wp_clear_scheduled_hook( 'lpnw_cron_portals' );
wp_clear_scheduled_hook( 'lpnw_cron_dispatch_alerts' );
wp_clear_scheduled_hook( 'lpnw_cron_free_digest' );
wp_clear_scheduled_hook( 'lpnw_cron_data_retention' );
