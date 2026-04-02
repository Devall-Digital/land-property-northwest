<?php
/**
 * Rate-limited traffic cron bridge for shared hosting.
 *
 * WordPress may call spawn_cron() on many page views; on busy sites that can mean
 * frequent background hits to wp-cron.php. This class ensures at most one spawn
 * attempt per 15 minutes while keeping the advertised portal cadence honest when
 * DISABLE_WP_CRON is not set.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Traffic-driven cron spawn with a transient lock.
 */
class LPNW_Traffic_Cron {

	public const TRANSIENT_KEY = 'lpnw_traffic_spawn_cron';

	/**
	 * Align with lpnw_fifteen_min schedule (900 seconds).
	 */
	public const LOCK_SECONDS = 900;

	/**
	 * Register shutdown hook on front-end requests.
	 */
	public static function init(): void {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( 'shutdown', array( __CLASS__, 'maybe_spawn_cron' ), 1 );
	}

	/**
	 * Spawn a non-blocking wp-cron run if the lock allows.
	 */
	public static function maybe_spawn_cron(): void {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return;
		}

		if ( false !== get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}

		set_transient( self::TRANSIENT_KEY, time(), self::LOCK_SECONDS );

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}
}
