<?php
/**
 * Sync Mautic email Do-Not-Contact (bounces, unsubscribes) to WordPress subscriber preferences.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hourly job: Mautic contacts with dnc:email → matching WP users get alerts deactivated.
 */
class LPNW_Mautic_Suppression_Sync {

	private const TRANSIENT_LOCK = 'lpnw_mautic_suppression_sync_lock';

	private const LOCK_TTL = 120;

	/**
	 * Run sync (cron callback via LPNW_Cron::run_mautic_suppression_sync).
	 */
	public static function run(): void {
		if ( false !== get_transient( self::TRANSIENT_LOCK ) ) {
			return;
		}
		set_transient( self::TRANSIENT_LOCK, 1, self::LOCK_TTL );

		$mautic = new LPNW_Mautic();
		if ( ! $mautic->is_configured() ) {
			delete_transient( self::TRANSIENT_LOCK );

			return;
		}

		$start     = 0;
		$limit     = 75;
		$max_pages = 30;
		$processed = 0;

		for ( $page = 0; $page < $max_pages; $page++ ) {
			$response = $mautic->get_contacts_search( 'dnc:email', $start, $limit );
			if ( null === $response || empty( $response['contacts'] ) || ! is_array( $response['contacts'] ) ) {
				break;
			}

			$batch_count = 0;
			foreach ( $response['contacts'] as $contact ) {
				if ( ! is_array( $contact ) ) {
					continue;
				}
				++$batch_count;
				$email = $mautic->get_email_from_contact_row( $contact );
				$email = strtolower( trim( $email ) );
				if ( '' === $email || ! is_email( $email ) ) {
					continue;
				}

				$user = get_user_by( 'email', $email );
				if ( ! $user instanceof \WP_User ) {
					continue;
				}

				if ( user_can( $user, 'manage_options' ) ) {
					continue;
				}

				/**
				 * Short-circuit per user (e.g. VIP testers): return true to skip suppression.
				 *
				 * @param bool    $skip Skip updating this user.
				 * @param WP_User $user WordPress user.
				 * @param string  $email Normalised email.
				 */
				if ( apply_filters( 'lpnw_mautic_suppression_skip_user', false, $user, $email ) ) {
					continue;
				}

				$prefs = LPNW_Subscriber::get_preferences( $user->ID );
				if ( $prefs && isset( $prefs->is_active ) && 0 === (int) $prefs->is_active ) {
					$already = get_user_meta( $user->ID, LPNW_Subscriber::USER_META_MAUTIC_EMAIL_SUPPRESSED, true );
					if ( '1' === (string) $already ) {
						continue;
					}
				}

				$was_active = ! $prefs || ! isset( $prefs->is_active ) || 1 === (int) $prefs->is_active;

				if ( ! LPNW_Subscriber::deactivate_alerts_preserve_preferences( $user->ID ) ) {
					continue;
				}

				update_user_meta( $user->ID, LPNW_Subscriber::USER_META_MAUTIC_EMAIL_SUPPRESSED, '1' );
				update_user_meta( $user->ID, LPNW_Subscriber::USER_META_MAUTIC_SUPPRESSED_AT, (string) time() );
				if ( $was_active ) {
					++$processed;
				}
			}

			if ( $batch_count < $limit ) {
				break;
			}

			$start += $limit;
		}

		delete_transient( self::TRANSIENT_LOCK );

		if ( $processed > 0 ) {
			error_log( 'LPNW Mautic suppression sync: deactivated alerts for ' . (int) $processed . ' WordPress user(s) matching Mautic dnc:email.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
