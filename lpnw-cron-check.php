<?php
/**
 * One-off LPNW cron diagnostic. Upload next to wp-load.php (or under mu-plugins) and visit once:
 * ?lpnw_cron_check=run&key=lpnw2026setup
 *
 * @package LPNW_Property_Alerts
 */

if ( ! isset( $_GET['lpnw_cron_check'], $_GET['key'] ) || 'run' !== $_GET['lpnw_cron_check'] || 'lpnw2026setup' !== $_GET['key'] ) {
	exit;
}

header( 'Content-Type: text/plain; charset=utf-8' );
set_time_limit( 300 );

/**
 * Locate wp-load.php from site root or wp-content/mu-plugins.
 *
 * @return string|null Absolute path or null.
 */
function lpnw_cron_check_find_wp_load(): ?string {
	$dir = __DIR__;
	for ( $i = 0; $i < 8; $i++ ) {
		$candidate = $dir . '/wp-load.php';
		if ( is_readable( $candidate ) ) {
			return $candidate;
		}
		$parent = dirname( $dir );
		if ( $parent === $dir ) {
			break;
		}
		$dir = $parent;
	}
	return null;
}

$wp_load = lpnw_cron_check_find_wp_load();
if ( null === $wp_load ) {
	echo "ERROR: wp-load.php not found. Place this file in the WordPress root or under wp-content/mu-plugins.\n";
	exit;
}

require_once $wp_load;

global $wpdb;

$hooks = array(
	'lpnw_cron_planning',
	'lpnw_cron_epc',
	'lpnw_cron_landregistry',
	'lpnw_cron_auctions',
	'lpnw_cron_portals',
	'lpnw_cron_dispatch_alerts',
	'lpnw_cron_free_digest',
);

echo "LPNW Cron Diagnostic\n";
echo str_repeat( '=', 60 ) . "\n\n";

echo "--- Scheduled events (wp_next_scheduled) ---\n";
foreach ( $hooks as $hook ) {
	$next = wp_next_scheduled( $hook );
	if ( false === $next ) {
		echo "{$hook}: NOT SCHEDULED\n";
		continue;
	}
	echo $hook . ': next run ' . gmdate( 'Y-m-d H:i:s', $next ) . " UTC (" . $next . ")\n";
}
echo "\n";

/**
 * Feed log summary: last run per feed_name with status (exact diagnostic query).
 *
 * @return array<int, object>|null
 */
function lpnw_cron_check_feed_log_summary(): ?array {
	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_feed_log';
	$sql   = "SELECT feed_name, MAX(started_at) AS last_run, status FROM {$table} GROUP BY feed_name ORDER BY last_run DESC";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from prefix only.
	$rows = $wpdb->get_results( $sql );
	if ( '' !== $wpdb->last_error ) {
		echo 'feed_log SQL error: ' . $wpdb->last_error . "\n";
		return null;
	}
	return is_array( $rows ) ? $rows : null;
}

/**
 * Print feed log rows as plain text.
 *
 * @param array<int, object>|null $rows Rows from feed log query.
 */
function lpnw_cron_check_print_feed_log( ?array $rows ): void {
	if ( null === $rows || array() === $rows ) {
		echo "(no rows)\n";
		return;
	}
	foreach ( $rows as $row ) {
		$feed = isset( $row->feed_name ) ? (string) $row->feed_name : '';
		$run  = isset( $row->last_run ) ? (string) $row->last_run : '';
		$st   = isset( $row->status ) ? (string) $row->status : '';
		echo "{$feed}\tlast_run={$run}\tstatus={$st}\n";
	}
}

echo "--- lpnw_feed_log (before spawn_cron): per feed MAX(started_at) ---\n";
$before_log = lpnw_cron_check_feed_log_summary();
lpnw_cron_check_print_feed_log( $before_log );
echo "\n";

echo "--- Properties ---\n";
$props_table = $wpdb->prefix . 'lpnw_properties';
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$props_table}" );
echo "Total: {$total}\n";
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$by_source = $wpdb->get_results(
	"SELECT source, COUNT(*) AS cnt FROM {$props_table} GROUP BY source ORDER BY cnt DESC"
);
if ( is_array( $by_source ) ) {
	foreach ( $by_source as $row ) {
		$src = isset( $row->source ) ? (string) $row->source : '';
		$c   = isset( $row->cnt ) ? (int) $row->cnt : 0;
		echo "  {$src}: {$c}\n";
	}
}
echo "\n";

echo "--- DISABLE_WP_CRON ---\n";
if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
	echo "DISABLE_WP_CRON is defined and true (traffic-based cron disabled; external cron should hit wp-cron.php).\n";
} elseif ( defined( 'DISABLE_WP_CRON' ) && ! DISABLE_WP_CRON ) {
	echo "DISABLE_WP_CRON is defined but false.\n";
} else {
	echo "DISABLE_WP_CRON is not defined.\n";
}
echo "\n";

echo "--- spawn_cron() ---\n";
spawn_cron();
echo "spawn_cron() returned.\n\n";

echo "Waiting 5 seconds...\n";
sleep( 5 );
echo "\n";

echo "--- lpnw_feed_log (after wait): per feed MAX(started_at) ---\n";
$after_log = lpnw_cron_check_feed_log_summary();
lpnw_cron_check_print_feed_log( $after_log );
echo "\n";

echo "Done.\n";

$self = __FILE__;
if ( is_string( $self ) && file_exists( $self ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- intentional one-off self-delete.
	if ( @unlink( $self ) ) {
		echo "This script file was deleted.\n";
	} else {
		echo "Could not delete this script file; remove it manually from the server.\n";
	}
}
