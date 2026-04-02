<?php
/**
 * Re-enable WP-Cron traffic triggering as backup.
 * If external cron can't reach us, at least page visits trigger it.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_action( 'init', function() {
	if ( empty( $_GET['lpnw_fix_cron'] ) || 'enable' !== $_GET['lpnw_fix_cron'] ) { return; }
	if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) { return; }

	$wp_config = ABSPATH . 'wp-config.php';
	$config = file_get_contents( $wp_config );

	// Comment out the DISABLE_WP_CRON line instead of removing it
	$config = str_replace(
		"define( 'DISABLE_WP_CRON', true );",
		"// define( 'DISABLE_WP_CRON', true ); // Commented out - using traffic-based cron until external service works",
		$config
	);

	file_put_contents( $wp_config, $config );

	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "WP-Cron re-enabled (traffic-based).\n";
	echo "Cron will now fire on page visits.\n";
	echo "The custom ?lpnw_cron=tick endpoint still works too.\n";

	@unlink( __FILE__ );
	exit;
}, 0 );
