<?php
/**
 * Portal WP-Cron scheduling: one event per portal so each run gets its own HTTP request
 * and the full PHP max_execution_time budget (shared hosting).
 *
 * Events are staggered by 5 minutes within the 15-minute recurrence so a single wp-cron
 * tick is less likely to run Rightmove + Zoopla + OnTheMarket back-to-back in one process.
 * External cron should ping wp-cron (or ?lpnw_cron=tick) at least every 5 minutes so
 * staggered hooks are not all overdue at once.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Portal_Cron {

	public const HOOK_RIGHTMOVE    = 'lpnw_cron_portal_rightmove';
	public const HOOK_ZOOPLA       = 'lpnw_cron_portal_zoopla';
	public const HOOK_ONTHEMARKET  = 'lpnw_cron_portal_onthemarket';

	/** @var array<string, int> hook => first-run offset seconds from "now" when scheduling. */
	private const STAGGER_OFFSETS = array(
		self::HOOK_RIGHTMOVE   => 0,
		self::HOOK_ZOOPLA      => 300,
		self::HOOK_ONTHEMARKET => 600,
	);

	/**
	 * Schedule split portal crons; remove legacy single lpnw_cron_portals hook.
	 */
	public static function schedule_split_portal_events(): void {
		self::clear_legacy_portal_hook();

		foreach ( self::STAGGER_OFFSETS as $hook => $offset ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + $offset, 'lpnw_fifteen_min', $hook );
			}
		}

		update_option( 'lpnw_portal_cron_split', '1', true );
	}

	/**
	 * Migrate from lpnw_cron_portals and ensure all three portal hooks exist.
	 */
	public static function maybe_migrate_and_schedule(): void {
		$had_legacy = (bool) wp_next_scheduled( 'lpnw_cron_portals' );
		if ( $had_legacy ) {
			wp_clear_scheduled_hook( 'lpnw_cron_portals' );
		}

		foreach ( self::STAGGER_OFFSETS as $hook => $offset ) {
			wp_clear_scheduled_hook( $hook );
			wp_schedule_event( time() + $offset, 'lpnw_fifteen_min', $hook );
		}

		update_option( 'lpnw_portal_cron_split', '1', true );
	}

	/**
	 * Clear legacy combined portal hook (used before split scheduling).
	 */
	public static function clear_legacy_portal_hook(): void {
		wp_clear_scheduled_hook( 'lpnw_cron_portals' );
	}

	/**
	 * Hooks monitored for stale-cron repair (portal leg).
	 *
	 * @return array<int, string>
	 */
	public static function get_portal_hook_names(): array {
		return array_keys( self::STAGGER_OFFSETS );
	}
}
