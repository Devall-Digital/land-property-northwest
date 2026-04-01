<?php
/**
 * Temporary WooCommerce and menu bootstrap for Land & Property Northwest.
 *
 * Upload to the WordPress site root next to wp-load.php, visit once:
 * https://example.com/lpnw-woo-setup.php?key=lpnw2026setup
 * Then delete this file from the server.
 *
 * @package LPNW_Property_Alerts
 */

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- One-off URL secret; no nonce (before wp-load).
$setup_key = isset( $_GET['key'] ) ? (string) $_GET['key'] : '';
if ( 'lpnw2026setup' !== $setup_key ) {
	header( 'HTTP/1.1 403 Forbidden' );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "Forbidden\n";
	exit;
}

require_once __DIR__ . '/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "WordPress failed to load.\n";
	exit( 1 );
}

$setup_file = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-setup-woocommerce.php';
if ( ! is_readable( $setup_file ) ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "Setup class not found at: {$setup_file}\n";
	exit( 1 );
}

require_once $setup_file;

// Menu APIs require edit_theme_options; there is no interactive login when using the URL key alone.
$admin_ids = get_users(
	array(
		'role'    => 'administrator',
		'number'  => 1,
		'orderby' => 'ID',
		'order'   => 'ASC',
		'fields'  => 'ID',
	)
);
if ( ! empty( $admin_ids[0] ) ) {
	wp_set_current_user( (int) $admin_ids[0] );
}

header( 'Content-Type: text/plain; charset=utf-8' );

if ( ! current_user_can( 'edit_theme_options' ) ) {
	echo "WARNING: No user with edit_theme_options; menu creation may fail. Create an administrator user first.\n\n";
}

echo "LPNW WooCommerce / menu setup\n";
echo str_repeat( '-', 40 ) . "\n\n";

$report = LPNW_Setup_WooCommerce::run();

if ( ! empty( $report['summary_lines'] ) && is_array( $report['summary_lines'] ) ) {
	foreach ( $report['summary_lines'] as $line ) {
		echo $line . "\n";
	}
	echo "\n";
}

echo "Full report (JSON):\n";
echo str_repeat( '-', 40 ) . "\n";
echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

echo "\nDone. Remove lpnw-woo-setup.php from the server root now.\n";
