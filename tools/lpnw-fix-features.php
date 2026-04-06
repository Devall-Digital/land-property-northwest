<?php
if ( ! defined( 'ABSPATH' ) ) { return; }
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';
add_action( 'init', function() {
	if ( empty( $_GET['lpnw_fix_kf'] ) || 'run' !== $_GET['lpnw_fix_kf'] ) { return; }
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}
	set_time_limit( 300 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_properties';

	$rows = $wpdb->get_results(
		"SELECT id, raw_data FROM {$table}
		 WHERE raw_data IS NOT NULL AND TRIM(raw_data) != ''
		 AND (key_features_text IS NULL OR TRIM(key_features_text) = '')
		 LIMIT 500"
	);

	echo "Key features backfill: " . count( $rows ) . " candidates\n\n";
	$updated = 0;

	foreach ( $rows as $row ) {
		$data = json_decode( $row->raw_data, true );
		if ( ! is_array( $data ) ) { continue; }

		$features = array();

		if ( ! empty( $data['keyFeatures'] ) && is_array( $data['keyFeatures'] ) ) {
			foreach ( $data['keyFeatures'] as $kf ) {
				$text = '';
				if ( is_string( $kf ) ) {
					$text = trim( $kf );
				} elseif ( is_array( $kf ) ) {
					$text = trim( $kf['description'] ?? $kf['text'] ?? $kf['value'] ?? $kf['label'] ?? $kf['htmlDescription'] ?? '' );
				}
				if ( '' !== $text ) {
					$text = wp_strip_all_tags( $text );
					$features[] = $text;
				}
			}
		}

		if ( empty( $features ) ) { continue; }

		$features_text = implode( '|', $features );
		$wpdb->update( $table, array( 'key_features_text' => $features_text ), array( 'id' => $row->id ) );
		$updated++;
	}

	$remaining = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$table} WHERE raw_data IS NOT NULL AND (key_features_text IS NULL OR TRIM(key_features_text) = '')"
	);

	echo "Updated: {$updated}\n";
	echo "Remaining without features: {$remaining}\n";
	if ( $remaining > 0 ) { echo "Run again to process more.\n"; }
	echo "Done.\n";
	exit;
}, 1 );
