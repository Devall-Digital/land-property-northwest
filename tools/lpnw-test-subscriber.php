<?php
/**
 * LPNW subscriber pipeline test. Upload to mu-plugins, hit URL with params.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action( 'init', function() {
	if ( empty( $_GET['lpnw_test_sub'] ) || 'run' !== $_GET['lpnw_test_sub'] ) { return; }
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}

	set_time_limit( 120 );
	header( 'Content-Type: text/plain; charset=utf-8' );

	global $wpdb;
	echo "LPNW Subscriber Pipeline Test\n";
	echo str_repeat( '=', 50 ) . "\n\n";

	// Find user
	$user = get_user_by( 'email', 'admin@codevall.co.uk' );
	if ( ! $user ) {
		echo "ERROR: User admin@codevall.co.uk not found.\n";
		exit;
	}
	echo "User: {$user->display_name} (ID {$user->ID})\n\n";

	// Check classes
	if ( ! class_exists( 'LPNW_Subscriber' ) ) {
		echo "ERROR: LPNW plugin classes not loaded.\n";
		exit;
	}

	// Save preferences
	$prefs = array(
		'areas'          => array( 'M', 'L', 'PR', 'BL', 'CA' ),
		'min_price'      => 0,
		'max_price'      => 0,
		'property_types' => array(),
		'alert_types'    => array( 'listing' ),
		'frequency'      => 'instant',
	);
	$saved = LPNW_Subscriber::save_preferences( $user->ID, $prefs );
	echo "Preferences saved: " . ( $saved ? 'yes' : 'no' ) . "\n";
	echo "Areas: M, L, PR, BL | Alert types: listing | Frequency: instant\n\n";

	// Get tier
	$tier = LPNW_Subscriber::get_tier( $user->ID );
	echo "Tier: {$tier}\n\n";

	// Get recent properties
	$table = $wpdb->prefix . 'lpnw_properties';
	$props = $wpdb->get_col( "SELECT id FROM {$table} ORDER BY id DESC LIMIT 10" );
	echo "Properties to match: " . count( $props ) . "\n";

	if ( empty( $props ) ) {
		echo "No properties in DB. Run the Rightmove feed first.\n";
		exit;
	}

	// Run matcher
	$matcher = new LPNW_Matcher();
	$queued  = $matcher->match_and_queue( array_map( 'intval', $props ) );
	echo "Alerts queued: {$queued}\n\n";

	// Check queue
	$pref_row = LPNW_Subscriber::get_preferences( $user->ID );
	$sub_id   = $pref_row ? $pref_row->id : 0;

	$queue_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE subscriber_id = %d",
		$sub_id
	) );
	$queued_pending = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE subscriber_id = %d AND status = 'queued'",
		$sub_id
	) );
	echo "Queue for this subscriber: {$queue_count} total, {$queued_pending} pending\n\n";

	// Run dispatcher
	echo "Running dispatcher...\n";
	$dispatcher = new LPNW_Dispatcher();
	$dispatcher->process_queue();

	// Check results
	$sent = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE subscriber_id = %d AND status = 'sent'",
		$sub_id
	) );
	$failed = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE subscriber_id = %d AND status = 'failed'",
		$sub_id
	) );
	$still_queued = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE subscriber_id = %d AND status = 'queued'",
		$sub_id
	) );

	echo "\nResults:\n";
	echo "  Sent: {$sent}\n";
	echo "  Failed: {$failed}\n";
	echo "  Still queued: {$still_queued}\n";
	echo "\nIf tier is 'free', alerts stay queued until the weekly digest runs.\n";
	echo "If tier is 'pro' or 'vip', alerts should be sent immediately.\n";
	echo "\nCheck admin@codevall.co.uk inbox for the alert email.\n";

	echo "\n" . str_repeat( '=', 50 ) . "\nDone.\n";
	exit;
}, 1 );
