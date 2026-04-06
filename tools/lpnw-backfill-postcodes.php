<?php
/**
 * Bulk reverse geocode properties that have lat/lng but no postcode.
 * Processes 100 at a time via postcodes.io bulk endpoint.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action( 'init', function() {
	if ( empty( $_GET['lpnw_backfill'] ) || 'run' !== $_GET['lpnw_backfill'] ) { return; }
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}

	set_time_limit( 300 );
	header( 'Content-Type: text/plain; charset=utf-8' );

	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_properties';

	$candidates = $wpdb->get_results(
		"SELECT id, latitude, longitude FROM {$table}
		 WHERE (postcode IS NULL OR TRIM(postcode) = '')
		 AND latitude IS NOT NULL AND longitude IS NOT NULL
		 AND latitude != 0 AND longitude != 0
		 LIMIT 200"
	);

	echo "Postcode backfill: " . count( $candidates ) . " candidates\n\n";

	if ( empty( $candidates ) ) {
		echo "Nothing to backfill.\n";
		@unlink( __FILE__ );
		exit;
	}

	$updated = 0;
	$failed  = 0;
	$chunks  = array_chunk( $candidates, 100 );

	foreach ( $chunks as $chunk ) {
		$geolocations = array();
		foreach ( $chunk as $row ) {
			$geolocations[] = array(
				'latitude'  => (float) $row->latitude,
				'longitude' => (float) $row->longitude,
			);
		}

		$response = wp_remote_post(
			'https://api.postcodes.io/postcodes',
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'geolocations' => $geolocations ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			echo "API error: " . $response->get_error_message() . "\n";
			$failed += count( $chunk );
			continue;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['result'] ) ) {
			echo "No results from API\n";
			$failed += count( $chunk );
			continue;
		}

		foreach ( $body['result'] as $i => $item ) {
			if ( ! isset( $chunk[ $i ] ) ) { continue; }

			$prop_id  = (int) $chunk[ $i ]->id;
			$postcode = '';

			if ( ! empty( $item['result'] ) && is_array( $item['result'] ) ) {
				$nearest = $item['result'][0] ?? null;
				if ( $nearest && ! empty( $nearest['postcode'] ) ) {
					$postcode = strtoupper( trim( $nearest['postcode'] ) );
				}
			}

			if ( ! empty( $postcode ) ) {
				$wpdb->update( $table, array( 'postcode' => $postcode ), array( 'id' => $prop_id ) );
				$updated++;
			} else {
				$failed++;
			}
		}

		usleep( 500000 );
	}

	echo "Updated: {$updated}\n";
	echo "Failed: {$failed}\n";
	echo "Remaining without postcode: " . $wpdb->get_var(
		"SELECT COUNT(*) FROM {$table} WHERE (postcode IS NULL OR TRIM(postcode) = '') AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0"
	) . "\n";

	echo "\nRun again to process the next batch.\n";
	echo "Done.\n";
	exit;
}, 1 );
