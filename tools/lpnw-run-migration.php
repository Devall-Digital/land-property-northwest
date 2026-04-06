<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';
add_action(
	'init',
	function () {
		if ( empty( $_GET['lpnw_migrate'] ) || 'run' !== $_GET['lpnw_migrate'] ) {
			return;
		}
		$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
		if ( ! lpnw_tool_query_key_ok( $key ) ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		if ( class_exists( 'LPNW_Activator' ) ) {
			LPNW_Activator::activate();
			echo 'Migration ran. DB version: ' . get_option( 'lpnw_db_version', 'unknown' ) . "\n";
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}lpnw_properties" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			echo 'Properties columns: ' . implode( ', ', $cols ) . "\n";
			$pcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}lpnw_subscriber_preferences" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			echo 'Preferences columns: ' . implode( ', ', $pcols ) . "\n";
		} else {
			echo "LPNW_Activator not found.\n";
		}
		@unlink( __FILE__ );
		exit;
	},
	1
);
