<?php
if ( ! defined( 'ABSPATH' ) ) { return; }
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';
add_action( 'init', function() {
	if ( empty( $_GET['lpnw_areas'] ) || 'create' !== $_GET['lpnw_areas'] ) { return; }
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	if ( ! class_exists( 'LPNW_Area_Pages' ) ) { echo "Area pages class not loaded.\n"; @unlink(__FILE__); exit; }
	$result = LPNW_Area_Pages::create_area_pages();
	echo "Created: " . count( $result['created'] ?? [] ) . "\n";
	echo "Skipped: " . count( $result['skipped'] ?? [] ) . "\n";
	foreach ( $result['created'] ?? [] as $slug ) { echo "  + {$slug}\n"; }
	foreach ( $result['skipped'] ?? [] as $slug ) { echo "  ~ {$slug}\n"; }
	if ( ! empty( $result['errors'] ) ) { foreach ( $result['errors'] as $e ) { echo "  ! {$e}\n"; } }
	echo "\nDone.\n";
	@unlink( __FILE__ );
	exit;
}, 1 );
