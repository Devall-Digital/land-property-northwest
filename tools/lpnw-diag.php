<?php
/**
 * Plugin Name: LPNW Diagnostic (remove after use)
 * Description: One-shot DB check and LPNW_Property::query test. Upload to wp-content/mu-plugins/, then open any front URL with ?lpnw_diag=run&key=YOUR_SECRET (wp-config LPNW_* or dev lpnw2026setup). Deletes itself after output.
 *
 * @package LPNW_Diagnostic
 */

defined( 'ABSPATH' ) || exit;

require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['lpnw_diag'], $_GET['key'] ) || 'run' !== $_GET['lpnw_diag'] ) {
			return;
		}
		$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
		if ( ! lpnw_tool_query_key_ok( $key ) ) {
			return;
		}

		global $wpdb;

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			nocache_headers();
		}

		$table = $wpdb->prefix . 'lpnw_properties';

		$lines   = array();
		$lines[] = 'LPNW diagnostic';
		$lines[] = '----------------';
		$lines[] = 'DB prefix: ' . $wpdb->prefix;
		$lines[] = 'Table: ' . $table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( null === $count && ! empty( $wpdb->last_error ) ) {
			$lines[] = 'COUNT(*) error: ' . $wpdb->last_error;
		} else {
			$lines[] = 'COUNT(*): ' . (string) (int) $count;
		}

		$sample = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				3
			)
		);
		if ( ! empty( $wpdb->last_error ) ) {
			$lines[] = 'Sample SELECT error: ' . $wpdb->last_error;
		} else {
			$lines[] = 'Last 3 rows (newest created_at):';
			$lines[] = $sample ? wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '(no rows)';
		}

		$lines[] = '';
		$lines[] = 'LPNW_Property::query( [], 3, 0 ):';
		if ( ! class_exists( 'LPNW_Property' ) ) {
			$lines[] = 'Class LPNW_Property not loaded (main plugin inactive or error?).';
		} else {
			$q = LPNW_Property::query( array(), 3, 0 );
			$lines[] = 'Returned count: ' . count( $q );
			$lines[] = empty( $q ) ? '(empty array)' : wp_json_encode( $q, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		$lines[] = '';
		$self = __FILE__;
		$lines[] = 'Self-delete: ' . $self;
		if ( is_writable( $self ) && @unlink( $self ) ) {
			$lines[] = 'Removed mu-plugin file OK.';
		} else {
			$lines[] = 'Could not delete this file; remove it manually from mu-plugins.';
		}

		echo implode( "\n", $lines );
		exit;
	},
	1
);
