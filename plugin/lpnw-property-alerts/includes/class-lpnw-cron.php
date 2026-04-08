<?php
/**
 * Cron schedule management.
 *
 * Registers custom intervals and hooks feed/dispatch jobs.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Cron {

	/**
	 * Transient key: rate-limit stale-cron self-heal checks.
	 */
	private const CRON_REPAIR_CHECK_TRANSIENT = 'lpnw_cron_stale_check';

	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_intervals' ) );

		add_action( 'init', array( __CLASS__, 'maybe_repair_stale_fifteen_min_cron' ), 5 );

		add_action( 'lpnw_cron_planning', array( __CLASS__, 'run_planning_feed' ) );
		add_action( 'lpnw_cron_epc', array( __CLASS__, 'run_epc_feed' ) );
		add_action( 'lpnw_cron_landregistry', array( __CLASS__, 'run_landregistry_feed' ) );
		add_action( 'lpnw_cron_auctions', array( __CLASS__, 'run_auction_feeds' ) );
		add_action( 'lpnw_cron_portals', array( __CLASS__, 'run_portal_feeds' ) );
		add_action( 'lpnw_cron_dispatch_alerts', array( __CLASS__, 'dispatch_alerts' ) );
		add_action( 'lpnw_cron_free_digest', array( __CLASS__, 'run_free_digest' ) );
		add_action( 'lpnw_cron_data_retention', array( __CLASS__, 'run_data_retention' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules Existing cron schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_intervals( array $schedules ): array {
		$schedules['lpnw_fifteen_min'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes', 'lpnw-alerts' ),
		);
		$schedules['lpnw_six_hours']   = array(
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'lpnw-alerts' ),
		);
		return $schedules;
	}

	/**
	 * If portal or alert-dispatch events are far overdue, reschedule them.
	 *
	 * Long feed runs can exceed the HTTP request time limit during wp_cron(),
	 * leaving `doing_cron` set and preventing reschedules. Events then sit in the
	 * past and nothing runs until manual intervention.
	 */
	public static function maybe_repair_stale_fifteen_min_cron(): void {
		if ( wp_installing() ) {
			return;
		}

		if ( false !== get_transient( self::CRON_REPAIR_CHECK_TRANSIENT ) ) {
			return;
		}

		set_transient( self::CRON_REPAIR_CHECK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );

		$grace = 20 * MINUTE_IN_SECONDS;

		$hooks = array(
			'lpnw_cron_portals',
			'lpnw_cron_auctions',
			'lpnw_cron_dispatch_alerts',
		);

		$stale = false;
		foreach ( $hooks as $hook ) {
			$next = wp_next_scheduled( $hook );
			if ( false === $next || $next < time() - $grace ) {
				$stale = true;
				break;
			}
		}

		if ( ! $stale ) {
			return;
		}

		delete_transient( 'doing_cron' );

		$now = time();
		wp_clear_scheduled_hook( 'lpnw_cron_portals' );
		wp_clear_scheduled_hook( 'lpnw_cron_auctions' );
		wp_clear_scheduled_hook( 'lpnw_cron_dispatch_alerts' );
		wp_schedule_event( $now, 'lpnw_fifteen_min', 'lpnw_cron_portals' );
		wp_schedule_event( $now + 60, 'lpnw_fifteen_min', 'lpnw_cron_auctions' );
		wp_schedule_event( $now + 120, 'lpnw_fifteen_min', 'lpnw_cron_dispatch_alerts' );
	}

	public static function run_planning_feed(): void {
		$settings = get_option( 'lpnw_settings', array() );
		if ( empty( $settings['planning_enabled'] ) ) {
			return;
		}

		$feed = new LPNW_Feed_Planning();
		$feed->run();
	}

	public static function run_epc_feed(): void {
		$settings = get_option( 'lpnw_settings', array() );
		if ( empty( $settings['epc_enabled'] ) ) {
			return;
		}

		$feed = new LPNW_Feed_EPC();
		$feed->run();
	}

	public static function run_landregistry_feed(): void {
		$settings = get_option( 'lpnw_settings', array() );
		if ( empty( $settings['landregistry_enabled'] ) ) {
			return;
		}

		$feed = new LPNW_Feed_LandRegistry();
		$feed->run();
	}

	public static function run_auction_feeds(): void {
		$settings = get_option( 'lpnw_settings', array() );
		if ( empty( $settings['auctions_enabled'] ) ) {
			return;
		}

		$feeds = array(
			new LPNW_Feed_Auction_Pugh(),
			new LPNW_Feed_Auction_SDL(),
			new LPNW_Feed_Auction_AHNW(),
			new LPNW_Feed_Auction_Allsop(),
		);

		foreach ( $feeds as $feed ) {
			$feed->run();
		}
	}

	/**
	 * Run property portal feeds (Rightmove, OnTheMarket, Zoopla).
	 *
	 * Order: Rightmove and OnTheMarket first (useful data); Zoopla last because it often
	 * returns nothing or is slow behind protection, and should not delay the others.
	 */
	public static function run_portal_feeds(): void {
		$settings = get_option( 'lpnw_settings', array() );

		if ( ! isset( $settings['portals_enabled'] ) || $settings['portals_enabled'] ) {
			$feeds = array(
				new LPNW_Feed_Portal_Rightmove(),
				new LPNW_Feed_Portal_OnTheMarket(),
				new LPNW_Feed_Portal_Zoopla(),
			);

			foreach ( $feeds as $feed ) {
				$feed->run();
				sleep( 2 );
			}
		}
	}

	public static function dispatch_alerts(): void {
		$dispatcher = new LPNW_Dispatcher();
		$dispatcher->process_queue();
	}

	public static function run_free_digest(): void {
		$dispatcher = new LPNW_Dispatcher();
		$dispatcher->send_free_digest();
	}

	/**
	 * Remove property rows older than configured retention and related queue/saved rows.
	 */
	public static function run_data_retention(): void {
		global $wpdb;

		$settings       = get_option( 'lpnw_settings', array() );
		$retention_days = isset( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : 180;
		if ( $retention_days < 1 ) {
			$retention_days = 180;
		}

		$props_table = $wpdb->prefix . 'lpnw_properties';
		$queue_table = $wpdb->prefix . 'lpnw_alert_queue';
		$saved_table = $wpdb->prefix . 'lpnw_saved_properties';

		$deleted_queue = $wpdb->query(
			$wpdb->prepare(
				"DELETE q FROM {$queue_table} q
			INNER JOIN {$props_table} p ON q.property_id = p.id
			WHERE p.created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$retention_days
			)
		);
		$deleted_queue = false !== $deleted_queue ? (int) $deleted_queue : 0;

		$deleted_saved = $wpdb->query(
			$wpdb->prepare(
				"DELETE s FROM {$saved_table} s
			INNER JOIN {$props_table} p ON s.property_id = p.id
			WHERE p.created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$retention_days
			)
		);
		$deleted_saved = false !== $deleted_saved ? (int) $deleted_saved : 0;

		$deleted_props = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$props_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);
		$deleted_props = false !== $deleted_props ? (int) $deleted_props : 0;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational cron diagnostics.
		error_log(
			sprintf(
				'LPNW data retention: deleted %d properties, %d alert_queue rows, %d saved_properties rows (retention_days=%d).',
				$deleted_props,
				$deleted_queue,
				$deleted_saved,
				$retention_days
			)
		);
	}
}
