<?php
/**
 * Plugin Name: LPNW Cache Purge
 * Description: One-shot full cache purge. Upload to wp-content/mu-plugins/. Trigger: ?lpnw_cache=purge&key=YOUR_LPNW_SECRET (see docs/DEPLOYMENT.md)
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
	return;
}

if ( ! isset( $_GET['lpnw_cache'], $_GET['key'] ) ) {
	return;
}

$lpnw_cache_param = sanitize_text_field( wp_unslash( $_GET['lpnw_cache'] ) );
$lpnw_key_param   = sanitize_text_field( wp_unslash( $_GET['key'] ) );

if ( 'purge' !== $lpnw_cache_param || ! lpnw_tool_query_key_ok( $lpnw_key_param ) ) {
	return;
}

header( 'Cache-Control: no-cache, no-store, must-revalidate' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );
header( 'Content-Type: text/plain; charset=utf-8' );

$lpnw_purge_results = array();

/**
 * Record a purge step.
 *
 * @param string      $method  Human-readable method name.
 * @param bool        $tried   Whether the step ran.
 * @param bool|null   $success True if known good, false if known bad, null if unknown.
 * @param string      $detail  Extra detail.
 */
$lpnw_record = static function ( $method, $tried, $success, $detail = '' ) use ( &$lpnw_purge_results ) {
	$lpnw_purge_results[] = array(
		'method'  => $method,
		'tried'   => $tried,
		'success' => $success,
		'detail'  => $detail,
	);
};

// 1. WordPress object cache.
if ( function_exists( 'wp_cache_flush' ) ) {
	$lpnw_flush_ret = wp_cache_flush();
	$lpnw_record(
		'wp_cache_flush()',
		true,
		( false !== $lpnw_flush_ret ),
		false === $lpnw_flush_ret ? 'returned false' : 'ok'
	);
} else {
	$lpnw_record( 'wp_cache_flush()', false, null, 'function not available' );
}

// 2. WP Super Cache.
if ( function_exists( 'wp_cache_clean_cache' ) ) {
	wp_cache_clean_cache();
	$lpnw_record( 'wp_cache_clean_cache()', true, true, 'called' );
} else {
	$lpnw_record( 'wp_cache_clean_cache()', false, null, 'function not available' );
}

// 3. LiteSpeed Cache.
if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
	LiteSpeed_Cache_API::purge_all();
	$lpnw_record( 'LiteSpeed_Cache_API::purge_all()', true, true, 'called' );
} else {
	$lpnw_record( 'LiteSpeed_Cache_API::purge_all()', false, null, 'class or method not available' );
}

// 4. WP Rocket.
if ( function_exists( 'rocket_clean_domain' ) ) {
	rocket_clean_domain();
	$lpnw_record( 'rocket_clean_domain()', true, true, 'called' );
} else {
	$lpnw_record( 'rocket_clean_domain()', false, null, 'function not available' );
}

// 5. Delete transient rows (LIKE contains literal _transient_; esc_like avoids SQL LIKE underscore wildcard issues).
global $wpdb;
if ( $wpdb instanceof wpdb ) {
	$lpnw_like = '%' . $wpdb->esc_like( '_transient_' ) . '%';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from wpdb.
	$lpnw_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $lpnw_like ) );
	if ( false === $lpnw_deleted ) {
		$lpnw_record(
			'DELETE transients from options',
			true,
			false,
			'query failed: ' . ( $wpdb->last_error ? $wpdb->last_error : 'unknown error' )
		);
	} else {
		$lpnw_record(
			'DELETE transients from options',
			true,
			true,
			sprintf( 'rows affected: %d', (int) $lpnw_deleted )
		);
	}
} else {
	$lpnw_record( 'DELETE transients from options', false, null, '$wpdb not available' );
}

// 6. W3 Total Cache.
if ( function_exists( 'w3tc_flush_all' ) ) {
	w3tc_flush_all();
	$lpnw_record( 'w3tc_flush_all()', true, true, 'called' );
} else {
	$lpnw_record( 'w3tc_flush_all()', false, null, 'function not available' );
}

// 7. Rewrite rules.
if ( function_exists( 'flush_rewrite_rules' ) ) {
	flush_rewrite_rules();
	$lpnw_record( 'flush_rewrite_rules()', true, true, 'called' );
} else {
	$lpnw_record( 'flush_rewrite_rules()', false, null, 'function not available' );
}

echo "LPNW cache purge\n";
echo "================\n\n";

foreach ( $lpnw_purge_results as $lpnw_row ) {
	$lpnw_tried   = $lpnw_row['tried'] ? 'yes' : 'no';
	$lpnw_success = $lpnw_row['success'];
	if ( null === $lpnw_success ) {
		$lpnw_ok = 'n/a';
	} elseif ( $lpnw_success ) {
		$lpnw_ok = 'yes';
	} else {
		$lpnw_ok = 'no';
	}
	$lpnw_detail = $lpnw_row['detail'] ? ' (' . $lpnw_row['detail'] . ')' : '';
	echo esc_html( $lpnw_row['method'] ) . "\n";
	echo '  tried: ' . esc_html( $lpnw_tried ) . "\n";
	echo '  success: ' . esc_html( $lpnw_ok ) . esc_html( $lpnw_detail ) . "\n\n";
}

$lpnw_self = __FILE__;
$lpnw_unlink_ok = @unlink( $lpnw_self ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort self-delete.

if ( $lpnw_unlink_ok ) {
	echo "Self-delete: succeeded (" . esc_html( $lpnw_self ) . ")\n";
} else {
	echo "Self-delete: failed (" . esc_html( $lpnw_self ) . ") — remove this file manually from mu-plugins.\n";
}

exit;
