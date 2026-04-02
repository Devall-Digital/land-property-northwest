<?php
if ( ! defined( 'ABSPATH' ) ) { return; }
add_action( 'init', function() {
	if ( empty( $_GET['lpnw_migrate'] ) || 'run' !== $_GET['lpnw_migrate'] ) { return; }
	if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) { return; }
	header( 'Content-Type: text/plain; charset=utf-8' );
	if ( class_exists( 'LPNW_Activator' ) ) {
		LPNW_Activator::activate();
		echo "Migration ran. DB version: " . get_option( 'lpnw_db_version', 'unknown' ) . "\n";
		global $wpdb;
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}lpnw_properties" );
		echo "Properties columns: " . implode( ', ', $cols ) . "\n";
		$pcols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}lpnw_subscriber_preferences" );
		echo "Preferences columns: " . implode( ', ', $pcols ) . "\n";
	} else {
		echo "LPNW_Activator not found.\n";
	}
	@unlink( __FILE__ );
	exit;
}, 1 );
