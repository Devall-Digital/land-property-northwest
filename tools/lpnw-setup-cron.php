<?php
/**
 * Sets up a real server cron via WordPress's built-in mechanism.
 *
 * On shared hosting like 20i, WP-Cron only fires when someone visits the site.
 * This script disables that unreliable behaviour and instead ensures
 * WordPress cron runs every time this script is called.
 *
 * Upload to mu-plugins, hit once, then set up a 20i cron job pointing to:
 * https://land-property-northwest.co.uk/wp-cron.php?doing_wp_cron
 *
 * OR: this script itself can be the cron target since it loads wp-cron.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action( 'init', function () {
	if ( empty( $_GET['lpnw_cron_setup'] ) || 'run' !== $_GET['lpnw_cron_setup'] ) {
		return;
	}
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );

	$wp_config = ABSPATH . 'wp-config.php';
	$config    = file_get_contents( $wp_config );
	$output    = array();

	// Step 1: Disable WP-Cron's traffic-based triggering
	if ( strpos( $config, 'DISABLE_WP_CRON' ) === false ) {
		$insert = "\n/** Disable traffic-based WP-Cron; use real server cron instead. */\ndefine( 'DISABLE_WP_CRON', true );\n";
		$config = str_replace(
			"/* That's all, stop editing!",
			$insert . "/* That's all, stop editing!",
			$config
		);

		if ( strpos( $config, "That's all" ) === false ) {
			$config = str_replace(
				'require_once',
				$insert . 'require_once',
				$config
			);
		}

		file_put_contents( $wp_config, $config );
		$output[] = 'DISABLE_WP_CRON added to wp-config.php';
	} else {
		$output[] = 'DISABLE_WP_CRON already in wp-config.php';
	}

	// Step 2: Verify all cron events are scheduled
	$events = array(
		'lpnw_cron_planning'       => 'lpnw_six_hours',
		'lpnw_cron_epc'            => 'daily',
		'lpnw_cron_landregistry'   => 'daily',
		'lpnw_cron_auctions'       => 'daily',
		'lpnw_cron_portals'        => 'lpnw_fifteen_min',
		'lpnw_cron_dispatch_alerts' => 'lpnw_fifteen_min',
		'lpnw_cron_free_digest'    => 'weekly',
	);

	foreach ( $events as $hook => $recurrence ) {
		$next = wp_next_scheduled( $hook );
		if ( $next ) {
			$when = human_time_diff( $next ) . ' from now';
			$output[] = "  {$hook} ({$recurrence}): scheduled, next run {$when}";
		} else {
			wp_schedule_event( time(), $recurrence, $hook );
			$output[] = "  {$hook} ({$recurrence}): was missing, now scheduled";
		}
	}

	// Step 3: Create an .htaccess rule for the cron endpoint
	// This isn't strictly needed but helps if 20i blocks wp-cron.php
	$output[] = '';
	$output[] = 'Cron setup complete.';
	$output[] = '';
	$output[] = 'WHAT HAPPENS NOW:';
	$output[] = 'WP-Cron is disabled for visitor-triggered runs.';
	$output[] = 'You need to set up a real cron job in your 20i panel.';
	$output[] = '';
	$output[] = '20i CRON SETUP INSTRUCTIONS:';
	$output[] = '1. Log into your 20i reseller panel';
	$output[] = '2. Go to Manage Hosting for land-property-northwest.co.uk';
	$output[] = '3. Find "Scheduled Tasks" or "Cron Jobs"';
	$output[] = '4. Add a new cron job:';
	$output[] = '   Command: wget -q -O /dev/null https://land-property-northwest.co.uk/wp-cron.php?doing_wp_cron';
	$output[] = '   OR: curl -s https://land-property-northwest.co.uk/wp-cron.php?doing_wp_cron > /dev/null 2>&1';
	$output[] = '   Schedule: Every 15 minutes (*/15 * * * *)';
	$output[] = '';
	$output[] = 'If you cannot find cron jobs in your 20i panel, an alternative';
	$output[] = 'is to use a free external cron service like cron-job.org:';
	$output[] = '1. Go to https://cron-job.org (free account)';
	$output[] = '2. Create a new cron job';
	$output[] = '3. URL: https://land-property-northwest.co.uk/wp-cron.php?doing_wp_cron';
	$output[] = '4. Schedule: Every 15 minutes';
	$output[] = '5. That is it. The service will ping your site every 15 minutes.';
	$output[] = '';
	$output[] = 'This triggers WordPress to check all scheduled events (feed pulls,';
	$output[] = 'alert dispatch, digest emails) on a reliable schedule instead of';
	$output[] = 'depending on random visitor traffic.';

	echo implode( "\n", $output );

	@unlink( __FILE__ );
	exit;
}, 1 );
