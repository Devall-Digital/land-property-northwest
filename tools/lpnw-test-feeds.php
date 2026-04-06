<?php
/**
 * LPNW end-to-end feed test.
 *
 * Tests the full pipeline: portal feed pull -> DB storage -> matching.
 * Upload to wp-content/mu-plugins/ and visit any page with ?lpnw_test=feeds&key=YOUR_LPNW_SECRET (see docs/DEPLOYMENT.md)
 * Self-deletes after running.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action( 'init', function() {
	if ( empty( $_GET['lpnw_test'] ) || 'feeds' !== $_GET['lpnw_test'] ) {
		return;
	}
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}

	set_time_limit( 300 );
	header( 'Content-Type: text/html; charset=utf-8' );

	echo '<html><head><title>LPNW Feed Test</title>';
	echo '<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px;line-height:1.6;} .ok{color:green;} .warn{color:orange;} .err{color:red;} pre{background:#f4f4f4;padding:12px;overflow-x:auto;font-size:13px;}</style>';
	echo '</head><body>';
	echo '<h1>LPNW End-to-End Feed Test</h1>';

	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_properties';

	$before_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	echo '<p>Properties in DB before test: <strong>' . $before_count . '</strong></p>';

	// Test 1: Rightmove feed
	echo '<h2>Test 1: Rightmove Feed</h2>';
	$start = microtime( true );

	try {
		$rm_feed = new LPNW_Feed_Portal_Rightmove();
		$rm_feed->run();
		$elapsed = round( microtime( true ) - $start, 2 );

		$after_rm = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$new_rm   = $after_rm - $before_count;

		echo '<p class="ok">Rightmove feed completed in ' . $elapsed . 's</p>';
		echo '<p>New properties added: <strong>' . $new_rm . '</strong></p>';

		if ( $new_rm > 0 ) {
			$samples = $wpdb->get_results(
				"SELECT address, postcode, price, source, property_type FROM {$table} WHERE source = 'rightmove' ORDER BY created_at DESC LIMIT 5"
			);
			echo '<h3>Sample Rightmove Properties</h3>';
			echo '<pre>';
			foreach ( $samples as $s ) {
				echo esc_html( sprintf(
					"%s | %s | %s | %s\n",
					$s->address,
					$s->postcode,
					$s->price ? '£' . number_format( (int) $s->price ) : 'N/A',
					$s->property_type
				) );
			}
			echo '</pre>';
		} else {
			echo '<p class="warn">No new properties. This could mean: the API returned no results, the response format has changed, or Rightmove is blocking the request.</p>';

			$log = $wpdb->get_row(
				"SELECT * FROM {$wpdb->prefix}lpnw_feed_log WHERE feed_name = 'rightmove' ORDER BY started_at DESC LIMIT 1"
			);
			if ( $log ) {
				echo '<p>Feed log: found=' . $log->properties_found . ', new=' . $log->properties_new . ', status=' . $log->status . '</p>';
				if ( $log->errors ) {
					echo '<p class="err">Errors: ' . esc_html( $log->errors ) . '</p>';
				}
			}
		}
	} catch ( \Throwable $e ) {
		echo '<p class="err">Rightmove feed failed: ' . esc_html( $e->getMessage() ) . '</p>';
	}

	// Test 2: Planning Portal feed
	echo '<h2>Test 2: Planning Portal Feed (first 2 authorities only)</h2>';
	$before_plan = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$start2      = microtime( true );

	try {
		$plan_feed = new LPNW_Feed_Planning();
		$plan_feed->run();
		$elapsed2 = round( microtime( true ) - $start2, 2 );

		$after_plan = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$new_plan   = $after_plan - $before_plan;

		echo '<p class="ok">Planning feed completed in ' . $elapsed2 . 's</p>';
		echo '<p>New properties added: <strong>' . $new_plan . '</strong></p>';

		if ( $new_plan > 0 ) {
			$samples = $wpdb->get_results(
				"SELECT address, postcode, description, source FROM {$table} WHERE source = 'planning' ORDER BY created_at DESC LIMIT 3"
			);
			echo '<pre>';
			foreach ( $samples as $s ) {
				echo esc_html( sprintf( "%s | %s | %s\n", $s->address, $s->postcode, wp_trim_words( $s->description, 15 ) ) );
			}
			echo '</pre>';
		}
	} catch ( \Throwable $e ) {
		echo '<p class="err">Planning feed failed: ' . esc_html( $e->getMessage() ) . '</p>';
	}

	// Test 3: Check alert queue
	echo '<h2>Test 3: Alert Queue Status</h2>';
	$queued = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'queued'" );
	$sent   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'sent'" );
	echo '<p>Alerts queued: ' . $queued . ', Alerts sent: ' . $sent . '</p>';

	if ( $queued > 0 ) {
		echo '<p class="ok">Matching engine is working. Alerts are queued for dispatch.</p>';
	} else {
		echo '<p class="warn">No alerts queued. This is expected if there are no subscribers with preferences set yet.</p>';
	}

	// Test 4: Feed log summary
	echo '<h2>Test 4: Feed Log Summary</h2>';
	$logs = $wpdb->get_results(
		"SELECT feed_name, status, properties_found, properties_new, started_at, errors
		 FROM {$wpdb->prefix}lpnw_feed_log ORDER BY started_at DESC LIMIT 10"
	);
	echo '<table border="1" cellpadding="6" style="border-collapse:collapse;">';
	echo '<tr><th>Feed</th><th>Status</th><th>Found</th><th>New</th><th>Time</th><th>Errors</th></tr>';
	foreach ( $logs as $log ) {
		$err = $log->errors ? count( json_decode( $log->errors, true ) ?: array() ) : 0;
		echo '<tr>';
		echo '<td>' . esc_html( $log->feed_name ) . '</td>';
		echo '<td>' . esc_html( $log->status ) . '</td>';
		echo '<td>' . esc_html( $log->properties_found ) . '</td>';
		echo '<td>' . esc_html( $log->properties_new ) . '</td>';
		echo '<td>' . esc_html( $log->started_at ) . '</td>';
		echo '<td>' . ( $err > 0 ? '<span class="err">' . $err . '</span>' : '0' ) . '</td>';
		echo '</tr>';
	}
	echo '</table>';

	// Final stats
	$final_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	echo '<hr>';
	echo '<h2>Final Stats</h2>';
	echo '<p>Total properties in database: <strong>' . $final_count . '</strong></p>';
	echo '<p>New in this test run: <strong>' . ( $final_count - $before_count ) . '</strong></p>';

	$by_source = $wpdb->get_results(
		"SELECT source, COUNT(*) as cnt FROM {$table} GROUP BY source ORDER BY cnt DESC"
	);
	if ( $by_source ) {
		echo '<p>By source: ';
		$parts = array();
		foreach ( $by_source as $src ) {
			$parts[] = esc_html( $src->source ) . ' (' . $src->cnt . ')';
		}
		echo implode( ', ', $parts );
		echo '</p>';
	}

	echo '<hr>';
	echo '<p class="ok"><strong>Test complete.</strong></p>';
	echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">View site</a></p>';
	echo '<p class="warn">This mu-plugin will self-delete now.</p>';
	echo '</body></html>';

	@unlink( __FILE__ );
	exit;
}, 1 );
