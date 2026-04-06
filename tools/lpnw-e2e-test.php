<?php
/**
 * LPNW end-to-end pipeline test.
 *
 * Tests: Rightmove feed -> DB -> matcher -> queue.
 * Upload to wp-content/mu-plugins/ and visit ?lpnw_e2e=run&key=YOUR_LPNW_SECRET (see docs/DEPLOYMENT.md)
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action( 'init', function() {
	if ( empty( $_GET['lpnw_e2e'] ) || 'run' !== $_GET['lpnw_e2e'] ) {
		return;
	}
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}

	set_time_limit( 600 );
	header( 'Content-Type: text/plain; charset=utf-8' );

	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_properties';
	$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

	echo "LPNW E2E Pipeline Test\n";
	echo str_repeat( '=', 50 ) . "\n\n";
	echo "Properties before: {$before}\n\n";

	// Test 1: Rightmove feed
	echo "--- TEST 1: Rightmove Feed ---\n";
	$t1 = microtime( true );
	try {
		$feed = new LPNW_Feed_Portal_Rightmove();
		$feed->run();
		$elapsed = round( microtime( true ) - $t1, 1 );
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$new = $after - $before;
		echo "Time: {$elapsed}s\n";
		echo "New properties: {$new}\n";

		if ( $new > 0 ) {
			$samples = $wpdb->get_results(
				"SELECT address, postcode, price, property_type FROM {$table} WHERE source = 'rightmove' ORDER BY created_at DESC LIMIT 5"
			);
			echo "Samples:\n";
			foreach ( $samples as $s ) {
				$p = $s->price ? number_format( (int) $s->price ) : 'N/A';
				echo "  {$s->address} | {$s->postcode} | GBP {$p} | {$s->property_type}\n";
			}
		}

		$log = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}lpnw_feed_log WHERE feed_name = 'rightmove' ORDER BY started_at DESC LIMIT 1"
		);
		if ( $log ) {
			echo "Feed log: found={$log->properties_found} new={$log->properties_new} status={$log->status}\n";
			if ( $log->errors ) {
				$errs = json_decode( $log->errors, true );
				echo "Errors: " . count( $errs ) . "\n";
				foreach ( array_slice( $errs, 0, 3 ) as $e ) {
					echo "  - {$e}\n";
				}
			}
		}
	} catch ( \Throwable $e ) {
		echo "FAILED: " . $e->getMessage() . "\n";
	}

	echo "\n--- TEST 2: Planning Portal (quick) ---\n";
	$before2 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$t2 = microtime( true );
	try {
		$feed2 = new LPNW_Feed_Planning();
		$feed2->run();
		$elapsed2 = round( microtime( true ) - $t2, 1 );
		$after2 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		echo "Time: {$elapsed2}s\n";
		echo "New properties: " . ( $after2 - $before2 ) . "\n";

		$log2 = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}lpnw_feed_log WHERE feed_name = 'planning' ORDER BY started_at DESC LIMIT 1"
		);
		if ( $log2 ) {
			echo "Feed log: found={$log2->properties_found} new={$log2->properties_new} status={$log2->status}\n";
		}
	} catch ( \Throwable $e ) {
		echo "FAILED: " . $e->getMessage() . "\n";
	}

	echo "\n--- TEST 3: Alert Queue ---\n";
	$queued = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'queued'" );
	$sent = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'sent'" );
	echo "Queued: {$queued} | Sent: {$sent}\n";

	echo "\n--- FINAL STATS ---\n";
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	echo "Total properties: {$total}\n";
	echo "New this run: " . ( $total - $before ) . "\n";

	$by_source = $wpdb->get_results( "SELECT source, COUNT(*) as cnt FROM {$table} GROUP BY source ORDER BY cnt DESC" );
	if ( $by_source ) {
		echo "By source:\n";
		foreach ( $by_source as $src ) {
			echo "  {$src->source}: {$src->cnt}\n";
		}
	}

	$feed_logs = $wpdb->get_results(
		"SELECT feed_name, status, properties_found, properties_new, started_at FROM {$wpdb->prefix}lpnw_feed_log ORDER BY started_at DESC LIMIT 10"
	);
	if ( $feed_logs ) {
		echo "\nRecent feed logs:\n";
		foreach ( $feed_logs as $fl ) {
			echo "  {$fl->feed_name} | {$fl->status} | found={$fl->properties_found} new={$fl->properties_new} | {$fl->started_at}\n";
		}
	}

	echo "\n" . str_repeat( '=', 50 ) . "\n";
	echo "Test complete.\n";

	@unlink( __FILE__ );
	exit;
}, 1 );
