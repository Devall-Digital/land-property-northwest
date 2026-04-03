<?php
/**
 * Keeps Mautic alert email IDs in lpnw_settings when API templates exist (no manual copy-paste).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sync VIP / Pro / Free Mautic email IDs from named templates.
 */
class LPNW_Mautic_Sync {

	private const TRANSIENT_KEY = 'lpnw_mautic_id_sync';

	private const LOCK_SECONDS = 300;

	/**
	 * Boot hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync_on_admin' ), 30 );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_sync_daily' ), 50 );
	}

	/**
	 * Run in wp-admin when visiting LPNW screens (fresh IDs after seeding).
	 */
	public static function maybe_sync_on_admin(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( 'lpnw-dashboard', 'lpnw-settings' ), true ) ) {
			return;
		}

		self::sync_if_needed( true );
	}

	/**
	 * Lightweight daily sync for long-running sites (no admin visit required).
	 */
	public static function maybe_sync_daily(): void {
		if ( is_admin() ) {
			return;
		}

		$last = (int) get_option( 'lpnw_mautic_sync_last', 0 );
		if ( $last > 0 && ( time() - $last ) < DAY_IN_SECONDS ) {
			return;
		}

		self::sync_if_needed( false );
	}

	/**
	 * Pull template IDs from Mautic and merge into lpnw_settings.
	 *
	 * @param bool $force Skip transient when true (admin LPNW pages).
	 */
	private static function sync_if_needed( bool $force ): void {
		if ( ! $force && get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}

		$client = new LPNW_Mautic();
		if ( ! $client->is_configured() ) {
			return;
		}

		$map = $client->get_alert_email_ids_by_name();
		if ( empty( $map ) ) {
			set_transient( self::TRANSIENT_KEY, 1, self::LOCK_SECONDS );

			return;
		}

		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$changed = false;
		foreach ( $map as $tier => $id ) {
			$key = 'mautic_email_' . $tier;
			$cur = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
			if ( $cur !== $id ) {
				$settings[ $key ] = (string) $id;
				$changed          = true;
			}
		}

		if ( $changed ) {
			update_option( 'lpnw_settings', $settings );
		}

		update_option( 'lpnw_mautic_sync_last', time() );
		set_transient( self::TRANSIENT_KEY, 1, self::LOCK_SECONDS );
	}
}
