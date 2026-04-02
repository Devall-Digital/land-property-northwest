<?php
/**
 * LPNW Save Property + Email Alert feature test.
 *
 * Upload to the WordPress site root (same directory as wp-load.php).
 * Run once in a browser:
 *   https://example.com/lpnw-test-features.php?lpnw_test_features=run&key=lpnw2026setup
 *
 * The script deletes itself after execution.
 *
 * @package LPNW_Property_Alerts
 */

if ( ! isset( $_GET['lpnw_test_features'], $_GET['key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	|| 'run' !== $_GET['lpnw_test_features']
	|| 'lpnw2026setup' !== $_GET['key'] ) {
	exit;
}

require_once __DIR__ . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

set_time_limit( 120 );
header( 'Content-Type: text/plain; charset=utf-8' );

$script_path = __FILE__;
$lines       = array();

/**
 * Append a report line and echo for progressive output.
 *
 * @param string $msg Message.
 */
$report = static function ( string $msg ) use ( &$lines ): void {
	$lines[] = $msg;
	echo $msg . "\n";
	if ( function_exists( 'ob_get_level' ) && ob_get_level() > 0 ) {
		ob_flush();
	}
	flush();
};

$report( 'LPNW Feature Test (Save Property, Email Alert, Unsave)' );
$report( str_repeat( '=', 60 ) );

if ( ! class_exists( 'LPNW_Subscriber' ) || ! class_exists( 'LPNW_Dispatcher' ) || ! class_exists( 'LPNW_Matcher' ) || ! class_exists( 'LPNW_Property' ) ) {
	$report( 'FAIL: LPNW plugin classes not loaded. Is the plugin active?' );
	lpnw_test_features_finish( $script_path, $lines, $report );
}

$user = get_user_by( 'email', 'admin@codevall.co.uk' );
if ( ! $user ) {
	$report( 'FAIL: User admin@codevall.co.uk not found.' );
	lpnw_test_features_finish( $script_path, $lines, $report );
}

wp_set_current_user( $user->ID );
$user_id = (int) $user->ID;
$report( 'Loaded WordPress as: ' . $user->user_email . ' (user ID ' . $user_id . ')' );
$report( '' );

global $wpdb;
$saved_table     = $wpdb->prefix . 'lpnw_saved_properties';
$properties_table = $wpdb->prefix . 'lpnw_properties';

// --- TEST SAVE PROPERTY ---
$save_ok    = false;
$property_id = 0;

$property_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT p.id FROM {$properties_table} p
		 WHERE NOT EXISTS (
			SELECT 1 FROM {$saved_table} s
			WHERE s.user_id = %d AND s.property_id = p.id
		 )
		 ORDER BY RAND() LIMIT 1",
		$user_id
	)
);

if ( ! $property_id ) {
	$property_id = (int) $wpdb->get_var( "SELECT id FROM {$properties_table} ORDER BY RAND() LIMIT 1" );
	if ( $property_id ) {
		$wpdb->delete(
			$saved_table,
			array(
				'user_id'     => $user_id,
				'property_id' => $property_id,
			),
			array( '%d', '%d' )
		);
	}
}

if ( ! $property_id ) {
	$report( 'Saved property #0: FAIL (no properties in database)' );
} else {
	$inserted = $wpdb->insert(
		$saved_table,
		array(
			'user_id'     => $user_id,
			'property_id' => $property_id,
		),
		array( '%d', '%d' )
	);

	if ( false === $inserted ) {
		$report( 'Saved property #' . $property_id . ': FAIL (INSERT error)' );
	} else {
		$verify = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$saved_table} WHERE user_id = %d AND property_id = %d",
				$user_id,
				$property_id
			)
		);
		$save_ok = ( $verify > 0 );
		$report( 'Saved property #' . $property_id . ': ' . ( $save_ok ? 'SUCCESS' : 'FAIL (row not found after INSERT)' ) );
	}
}

$report( '' );

// --- TEST EMAIL DISPATCH ---
$email_ok = false;

$prefs = LPNW_Subscriber::get_preferences( $user_id );
$tier  = LPNW_Subscriber::get_tier( $user_id );

$areas    = array();
$types    = array();
$alerts   = array();
$min      = null;
$max      = null;
if ( $prefs ) {
	$areas  = is_array( $prefs->areas ?? null ) ? $prefs->areas : array();
	$types  = is_array( $prefs->property_types ?? null ) ? $prefs->property_types : array();
	$alerts = is_array( $prefs->alert_types ?? null ) ? $prefs->alert_types : array();
	$min    = isset( $prefs->min_price ) ? $prefs->min_price : null;
	$max    = isset( $prefs->max_price ) ? $prefs->max_price : null;
}

$subscriber_row = (object) array(
	'areas'          => wp_json_encode( $areas ),
	'property_types' => wp_json_encode( $types ),
	'alert_types'    => wp_json_encode( $alerts ),
	'min_price'      => $min,
	'max_price'      => $max,
);

$matcher   = new LPNW_Matcher();
$matching  = array();
$prop_ids  = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT id FROM {$properties_table} ORDER BY updated_at DESC, id DESC LIMIT %d",
		400
	)
);

if ( is_array( $prop_ids ) ) {
	foreach ( $prop_ids as $pid ) {
		$property = LPNW_Property::get( (int) $pid );
		if ( ! $property ) {
			continue;
		}
		if ( $matcher->property_matches_subscriber( $property, $subscriber_row ) ) {
			$matching[] = $property;
			if ( count( $matching ) >= 3 ) {
				break;
			}
		}
	}
}

if ( count( $matching ) < 3 ) {
	$report( 'Test email sent: FAIL (fewer than 3 properties match subscriber preferences; got ' . count( $matching ) . ')' );
} else {
	$dispatcher = new LPNW_Dispatcher(); // Ensures dispatcher stack (e.g. Mautic client) initialised like production.
	$frequency  = LPNW_Dispatcher::get_effective_alert_frequency( $tier, $prefs );

	$body = LPNW_Dispatcher::build_alert_email_html(
		$user,
		$matching,
		$frequency,
		array(
			'subscriber_first_name' => LPNW_Dispatcher::get_subscriber_greeting_first_name( $user ),
		)
	);

	if ( '' === $body ) {
		$report( 'Test email sent: FAIL (build_alert_email_html returned empty)' );
	} else {
		$subject = '[LPNW Test] Feature check — ' . count( $matching ) . ' sample properties';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( 'admin@codevall.co.uk', $subject, $body, $headers );
		$email_ok = (bool) $sent;
		$report( 'Test email sent: ' . ( $email_ok ? 'SUCCESS' : 'FAIL (wp_mail returned false)' ) );
	}
}

$report( '' );

// --- TEST UNSAVE ---
$unsave_ok = false;
if ( $property_id > 0 ) {
	$deleted = $wpdb->delete(
		$saved_table,
		array(
			'user_id'     => $user_id,
			'property_id' => $property_id,
		),
		array( '%d', '%d' )
	);
	$still = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$saved_table} WHERE user_id = %d AND property_id = %d",
			$user_id,
			$property_id
		)
	);
	$unsave_ok = ( false !== $deleted && 0 === $still );
	$report( 'Unsaved: ' . ( $unsave_ok ? 'SUCCESS' : 'FAIL' ) );
} else {
	$report( 'Unsaved: FAIL (no property_id from save test)' );
}

$report( '' );
$report( str_repeat( '-', 60 ) );
$report( 'OVERALL' );
$report( '  Save property:     ' . ( $save_ok ? 'works' : 'does not work' ) );
$report( '  Email alert send:  ' . ( $email_ok ? 'works' : 'does not work' ) );
$report( '  Unsave:            ' . ( $unsave_ok ? 'works' : 'does not work' ) );
$report( str_repeat( '=', 60 ) );

lpnw_test_features_finish( $script_path, $lines, $report );

/**
 * Self-delete script and exit.
 *
 * @param string        $path   Absolute path to this file.
 * @param array<string> $lines  Collected lines (unused; reserved).
 * @param callable      $report Report callback.
 */
function lpnw_test_features_finish( string $path, array $lines, callable $report ): void {
	if ( is_string( $path ) && '' !== $path && file_exists( $path ) && is_writable( $path ) ) {
		if ( @unlink( $path ) ) {
			$report( 'Self-delete: SUCCESS (this file was removed)' );
		} else {
			$report( 'Self-delete: FAIL (remove lpnw-test-features.php manually)' );
		}
	} else {
		$report( 'Self-delete: SKIP (file not writable)' );
	}
	exit;
}
