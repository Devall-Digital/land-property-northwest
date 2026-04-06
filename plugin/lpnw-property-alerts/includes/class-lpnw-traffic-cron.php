<?php
/**
 * Rate-limited traffic cron bridge for shared hosting.
 *
 * WordPress calls _wp_cron() on normal page loads only when DISABLE_WP_CRON is
 * false. Many hosts set DISABLE_WP_CRON true and expect a real cron to hit
 * wp-cron.php; when that URL is blocked, nothing runs unless something still
 * calls spawn_cron(). The LPNW cron_request filter points spawn_cron() at
 * ?lpnw_cron=tick (mu-plugin). This class triggers spawn_cron() from public
 * traffic and from wp-admin for administrators, at most once per 15 minutes,
 * so scheduled portal feeds keep moving without relying on core's wp-cron hook.
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
	 * Register shutdown hook on front-end requests and (for admins) wp-admin.
	 */
	public static function init(): void {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( is_admin() && ! is_user_logged_in() ) {
			return;
		}

		if ( is_admin() && ! current_user_can( 'manage_options' ) ) {
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
		if ( false !== get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}

		set_transient( self::TRANSIENT_KEY, time(), self::LOCK_SECONDS );

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}
}
