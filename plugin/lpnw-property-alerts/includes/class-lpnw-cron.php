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

	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_intervals' ) );

		add_action( 'lpnw_cron_planning', array( __CLASS__, 'run_planning_feed' ) );
		add_action( 'lpnw_cron_epc', array( __CLASS__, 'run_epc_feed' ) );
		add_action( 'lpnw_cron_landregistry', array( __CLASS__, 'run_landregistry_feed' ) );
		add_action( 'lpnw_cron_auctions', array( __CLASS__, 'run_auction_feeds' ) );
		add_action( 'lpnw_cron_portals', array( __CLASS__, 'run_portal_feeds' ) );
		add_action( 'lpnw_cron_dispatch_alerts', array( __CLASS__, 'dispatch_alerts' ) );
		add_action( 'lpnw_cron_free_digest', array( __CLASS__, 'run_free_digest' ) );
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
		$schedules['lpnw_six_hours'] = array(
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'lpnw-alerts' ),
		);
		return $schedules;
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
	 * Run property portal feeds (Rightmove, Zoopla).
	 * These are the primary "new to market" data sources.
	 */
	public static function run_portal_feeds(): void {
		$settings = get_option( 'lpnw_settings', array() );

		if ( ! isset( $settings['portals_enabled'] ) || $settings['portals_enabled'] ) {
			$feeds = array(
				new LPNW_Feed_Portal_Rightmove(),
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
}
