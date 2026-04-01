<?php
/**
 * LPNW alert pipeline test (must-use plugin).
 *
 * Deploy: upload this file to wp-content/mu-plugins/lpnw-test-subscriber.php
 * (WordPress loads mu-plugins automatically; no activation needed.)
 *
 * Run on any front-end URL:
 * https://yoursite.example/?lpnw_test_sub=run&key=lpnw2026setup
 *
 * Optional: place a copy in the site root beside wp-load.php and open:
 * https://yoursite.example/lpnw-test-subscriber.php?lpnw_test_sub=run&key=lpnw2026setup
 *
 * Does not self-delete. Remove from production when finished testing.
 *
 * @package LPNW_Property_Alerts
 */

declare( strict_types=1 );

/**
 * Execute the test pipeline and echo plain-text results.
 */
$lpnw_test_subscriber_run = static function (): void {
	$out = array();

	if ( ! class_exists( 'LPNW_Subscriber' ) || ! class_exists( 'LPNW_Matcher' ) || ! class_exists( 'LPNW_Dispatcher' ) ) {
		$out[] = 'ERROR: LPNW Property Alerts classes missing. Activate the lpnw-property-alerts plugin.';
		echo implode( "\n", $out ) . "\n";
		return;
	}

	$test_email = 'admin@codevall.co.uk';

	$user = get_user_by( 'email', $test_email );
	if ( ! $user instanceof WP_User ) {
		$out[] = 'ERROR: No WordPress user found with email: ' . $test_email;
		echo implode( "\n", $out ) . "\n";
		return;
	}

	$user_id = (int) $user->ID;
	$out[]   = 'LPNW test subscriber pipeline';
	$out[]   = '---';
	$out[]   = 'User ID: ' . $user_id;
	$out[]   = 'Email: ' . $test_email;

	$prefs = array(
		'areas'          => array( 'M', 'L', 'PR', 'BL' ),
		'min_price'      => 0,
		'max_price'      => 0,
		'property_types' => array(),
		'alert_types'    => array( 'listing' ),
		'frequency'      => 'instant',
	);

	$saved = LPNW_Subscriber::save_preferences( $user_id, $prefs );
	$out[] = 'save_preferences() (areas M,L,PR,BL; min/max 0 = no filter; property_types empty = all; alert_types listing; frequency instant; is_active 1 via save_preferences): ' . ( $saved ? 'OK' : 'FAILED' );

	$tier = LPNW_Subscriber::get_tier( $user_id );
	$out[] = 'Subscription tier: ' . $tier;

	global $wpdb;

	$prefs_row = LPNW_Subscriber::get_preferences( $user_id );
	if ( ! $prefs_row || empty( $prefs_row->id ) ) {
		$out[] = 'ERROR: Could not load subscriber preferences row after save.';
		echo implode( "\n", $out ) . "\n";
		return;
	}

	$subscriber_pk = (int) $prefs_row->id;

	$prop_table   = $wpdb->prefix . 'lpnw_properties';
	$queue_table  = $wpdb->prefix . 'lpnw_alert_queue';
	$property_ids = $wpdb->get_col(
		"SELECT id FROM {$prop_table} ORDER BY updated_at DESC, id DESC LIMIT 10"
	);
	$property_ids = array_map( 'intval', is_array( $property_ids ) ? $property_ids : array() );

	$out[] = 'Recent property IDs (10, by updated_at): ' . ( $property_ids ? implode( ', ', $property_ids ) : '(none in database)' );

	$count_queue_for_sub = static function () use ( $wpdb, $queue_table, $subscriber_pk ): int {
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE subscriber_id = %d",
				$subscriber_pk
			)
		);
	};

	$queue_before = $count_queue_for_sub();

	$matcher = new LPNW_Matcher();
	$queued  = $matcher->match_and_queue( $property_ids );
	$out[]   = 'match_and_queue() alerts queued (return value): ' . $queued;

	$queue_after_match = $count_queue_for_sub();
	$out[]             = 'Queue rows for this subscriber (preferences id ' . $subscriber_pk . '): before match ' . $queue_before . ', after match ' . $queue_after_match . ' (new rows ' . ( $queue_after_match - $queue_before ) . ')';

	$queued_before_dispatch = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$queue_table} WHERE subscriber_id = %d AND status = %s",
			$subscriber_pk,
			'queued'
		)
	);

	$out[] = '---';
	$out[] = 'Dispatcher: new LPNW_Dispatcher(); process_queue();';
	$out[] = 'Note: process_queue() only sends for tier vip and pro. Free-tier rows stay queued.';

	$dispatcher = new LPNW_Dispatcher();
	$dispatcher->process_queue();

	$queued_after = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$queue_table} WHERE subscriber_id = %d AND status = %s",
			$subscriber_pk,
			'queued'
		)
	);
	$sent_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$queue_table} WHERE subscriber_id = %d AND status = %s",
			$subscriber_pk,
			'sent'
		)
	);
	$failed_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$queue_table} WHERE subscriber_id = %d AND status = %s",
			$subscriber_pk,
			'failed'
		)
	);

	$out[] = 'After process_queue(): still queued=' . $queued_after . ', sent=' . $sent_count . ', failed=' . $failed_count;

	if ( 'free' === $tier ) {
		$out[] = 'Emails sent: no (tier is free; dispatcher does not process free-tier queue in process_queue()).';
	} elseif ( $sent_count > 0 ) {
		$out[] = 'Emails sent: yes (at least one queue row marked sent; delivery via Mautic or wp_mail).';
	} elseif ( $failed_count > 0 && 0 === $sent_count ) {
		$out[] = 'Emails sent: no (rows marked failed after send attempt).';
	} elseif ( $queued_before_dispatch > 0 && $queued_after === $queued_before_dispatch ) {
		$out[] = 'Emails sent: no (rows still queued; check tier or batch limits).';
	} elseif ( 0 === $queued_before_dispatch ) {
		$out[] = 'Emails sent: n/a (nothing was queued for dispatch for this subscriber).';
	} else {
		$out[] = 'Emails sent: unknown (see queue counts above).';
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, property_id, tier, status, queued_at, sent_at, mautic_email_id
			 FROM {$queue_table}
			 WHERE subscriber_id = %d
			 ORDER BY id DESC
			 LIMIT 50",
			$subscriber_pk
		),
		ARRAY_A
	);

	$out[] = '---';
	$out[] = 'Alert queue rows for this subscriber (latest 50):';
	if ( empty( $rows ) ) {
		$out[] = '(none)';
	} else {
		foreach ( $rows as $r ) {
			$out[] = sprintf(
				'id=%s property_id=%s tier=%s status=%s queued_at=%s sent_at=%s mautic_email_id=%s',
				(string) ( $r['id'] ?? '' ),
				(string) ( $r['property_id'] ?? '' ),
				(string) ( $r['tier'] ?? '' ),
				(string) ( $r['status'] ?? '' ),
				(string) ( $r['queued_at'] ?? '' ),
				(string) ( $r['sent_at'] ?? '' ),
				(string) ( $r['mautic_email_id'] ?? '' )
			);
		}
	}

	$out[] = '---';
	$out[] = 'Done.';

	echo implode( "\n", $out ) . "\n";
};

if ( defined( 'ABSPATH' ) ) {
	add_action(
		'template_redirect',
		static function () use ( $lpnw_test_subscriber_run ): void {
			if ( ! isset( $_GET['lpnw_test_sub'], $_GET['key'] ) || 'run' !== $_GET['lpnw_test_sub'] || 'lpnw2026setup' !== $_GET['key'] ) {
				return;
			}

			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Robots-Tag: noindex, nofollow' );

			$lpnw_test_subscriber_run();
			exit;
		},
		0
	);
	return;
}

// Standalone: direct request when this file lives beside wp-load.php (or similar).
if ( ! isset( $_GET['lpnw_test_sub'], $_GET['key'] ) || 'run' !== $_GET['lpnw_test_sub'] || 'lpnw2026setup' !== $_GET['key'] ) {
	exit;
}

header( 'Content-Type: text/plain; charset=utf-8' );
header( 'X-Robots-Tag: noindex, nofollow' );

$wp_load_candidates = array(
	__DIR__ . '/wp-load.php',
	dirname( __DIR__ ) . '/wp-load.php',
);

$wp_loaded = false;
foreach ( $wp_load_candidates as $wp_load_path ) {
	if ( is_readable( $wp_load_path ) ) {
		require_once $wp_load_path;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded || ! defined( 'ABSPATH' ) ) {
	echo "ERROR: Could not load WordPress. Tried:\n";
	foreach ( $wp_load_candidates as $p ) {
		echo '  ' . $p . "\n";
	}
	exit;
}

$lpnw_test_subscriber_run();
