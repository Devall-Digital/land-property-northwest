<?php
/**
 * Plugin Name: LPNW Postcode Diagnostic (one-shot)
 * Description: Drop into wp-content/mu-plugins/. Open any front URL with ?lpnw_pc=stats&key=lpnw2026setup. Removes itself after the run.
 *
 * @package LPNW_Postcode_Stats_MU
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalise a UK postcode like LPNW_Property::clean_postcode (private).
 *
 * @param string $postcode Raw postcode.
 * @return string
 */
function lpnw_mu_clean_postcode( string $postcode ): string {
	$postcode = strtoupper( trim( $postcode ) );
	$postcode = preg_replace( '/[^A-Z0-9 ]/', '', $postcode );
	return $postcode;
}

/**
 * Reverse geocode if LPNW_Geocoder is unavailable.
 *
 * @param float $lat Latitude.
 * @param float $lng Longitude.
 * @return string|null
 */
function lpnw_mu_reverse_geocode( float $lat, float $lng ): ?string {
	if ( ! is_finite( $lat ) || ! is_finite( $lng ) ) {
		return null;
	}

	$url = add_query_arg(
		array(
			'lon'   => $lng,
			'lat'   => $lat,
			'limit' => 1,
		),
		'https://api.postcodes.io/postcodes'
	);

	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

	if ( is_wp_error( $response ) ) {
		return null;
	}

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['result'] ) || ! is_array( $body['result'] ) ) {
		return null;
	}

	$first = $body['result'][0];
	if ( empty( $first['postcode'] ) || ! is_string( $first['postcode'] ) ) {
		return null;
	}

	return strtoupper( trim( $first['postcode'] ) );
}

/**
 * @param float $lat Lat.
 * @param float $lng Lng.
 * @return string|null
 */
function lpnw_mu_resolve_postcode( float $lat, float $lng ): ?string {
	if ( class_exists( 'LPNW_Geocoder' ) ) {
		return LPNW_Geocoder::reverse_geocode( $lat, $lng );
	}

	return lpnw_mu_reverse_geocode( $lat, $lng );
}

/**
 * Run diagnostic output and optional backfill.
 */
function lpnw_mu_postcode_stats_run(): void {
	global $wpdb;

	if ( ! class_exists( 'LPNW_Geocoder' ) ) {
		$geocoder_path = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-geocoder.php';
		if ( is_readable( $geocoder_path ) ) {
			require_once $geocoder_path;
		}
	}

	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );

	$table = $wpdb->prefix . 'lpnw_properties';

	$like = $wpdb->esc_like( $table );
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

	if ( $exists !== $table ) {
		echo "ERROR: Table {$table} not found.\n";
		lpnw_mu_postcode_stats_self_delete();
		exit;
	}

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$with_pc = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$table} WHERE TRIM(COALESCE(postcode, '')) <> ''" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	);

	$without_pc = $total - $with_pc;

	$candidates = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$table}
		WHERE TRIM(COALESCE(postcode, '')) = ''
		AND latitude IS NOT NULL AND longitude IS NOT NULL
		AND ABS(latitude) > 0.0001 AND ABS(longitude) > 0.0001" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	);

	echo "LPNW postcode diagnostic\n";
	echo 'Time (UTC): ' . gmdate( 'c' ) . "\n\n";

	echo "--- Counts ---\n";
	echo "Total properties: {$total}\n";
	echo "With postcode (non-empty trim): {$with_pc}\n";
	echo "Without postcode: {$without_pc}\n";
	echo "Lat/lng set but no postcode (reverse-geocode candidates): {$candidates}\n\n";

	echo "--- Top 10 outward codes (by count) ---\n";
	$top = $wpdb->get_results(
		"SELECT SUBSTRING_INDEX(UPPER(TRIM(postcode)), ' ', 1) AS outward, COUNT(*) AS cnt
		FROM {$table}
		WHERE TRIM(COALESCE(postcode, '')) <> ''
		GROUP BY outward
		ORDER BY cnt DESC
		LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		ARRAY_A
	);
	if ( empty( $top ) ) {
		echo "(none)\n";
	} else {
		foreach ( $top as $row ) {
			echo (string) $row['outward'] . "\t" . (string) $row['cnt'] . "\n";
		}
	}

	echo "\n--- Reverse geocode up to 50 (no postcode, has coordinates) ---\n";

	$rows = $wpdb->get_results(
		"SELECT id, latitude, longitude, address
		FROM {$table}
		WHERE TRIM(COALESCE(postcode, '')) = ''
		AND latitude IS NOT NULL AND longitude IS NOT NULL
		AND ABS(latitude) > 0.0001 AND ABS(longitude) > 0.0001
		ORDER BY id ASC
		LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		ARRAY_A
	);

	$updated = 0;
	$failed  = 0;
	$skipped = 0;

	if ( empty( $rows ) ) {
		echo "No rows to process.\n";
	} else {
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$lat = (float) $row['latitude'];
			$lng = (float) $row['longitude'];
			$addr = isset( $row['address'] ) ? (string) $row['address'] : '';

			$resolved = lpnw_mu_resolve_postcode( $lat, $lng );

			if ( null === $resolved || '' === $resolved ) {
				++$failed;
				echo "id={$id} FAIL (no postcode from API) lat={$lat} lng={$lng} address=" . substr( $addr, 0, 80 ) . "\n";
				continue;
			}

			$clean = lpnw_mu_clean_postcode( $resolved );
			if ( '' === $clean ) {
				++$skipped;
				echo "id={$id} SKIP (empty after clean)\n";
				continue;
			}

			$ok = $wpdb->update(
				$table,
				array( 'postcode' => $clean ),
				array( 'id' => $id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false === $ok ) {
				++$failed;
				echo "id={$id} DB ERROR {$wpdb->last_error}\n";
				continue;
			}

			if ( 0 === (int) $ok ) {
				++$failed;
				echo "id={$id} WARN zero rows affected (id missing?)\n";
				continue;
			}

			++$updated;
			echo "id={$id} OK -> {$clean}\n";
		}
	}

	echo "\n--- Summary ---\n";
	echo "Updated: {$updated}\n";
	echo "Failed (API or DB): {$failed}\n";
	echo "Skipped (clean empty): {$skipped}\n";

	lpnw_mu_postcode_stats_self_delete();
	exit;
}

/**
 * Remove this file from disk.
 */
function lpnw_mu_postcode_stats_self_delete(): void {
	$file = __FILE__;
	if ( ! is_string( $file ) || '' === $file ) {
		echo "\nSelf-delete: could not resolve file path.\n";
		return;
	}

	$deleted = @unlink( $file );
	if ( $deleted ) {
		echo "\nSelf-delete: removed " . $file . "\n";
	} else {
		echo "\nSelf-delete: FAILED for " . $file . " (delete manually from mu-plugins)\n";
	}
}

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['lpnw_pc'], $_GET['key'] ) ) {
			return;
		}

		$pc = sanitize_text_field( wp_unslash( $_GET['lpnw_pc'] ) );
		$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );

		if ( 'stats' !== $pc || 'lpnw2026setup' !== $key ) {
			return;
		}

		lpnw_mu_postcode_stats_run();
	},
	5
);
