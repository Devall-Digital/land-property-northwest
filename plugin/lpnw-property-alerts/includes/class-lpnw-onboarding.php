<?php
/**
 * New subscriber onboarding: default preferences, redirect to preferences, setup tracking.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps alert preferences for new accounts and nudges first-time setup.
 */
final class LPNW_Onboarding {

	public const USER_META_SETUP_COMPLETE = 'lpnw_prefs_setup_complete';

	public const USER_META_REDIRECT_PENDING = 'lpnw_redirect_prefs_pending';

	public static function init(): void {
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 20, 1 );
		add_filter( 'login_redirect', array( __CLASS__, 'filter_login_redirect' ), 20, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_to_preferences' ), 1 );
	}

	/**
	 * Create default preferences row and flag one-time redirect to /preferences/.
	 *
	 * @param int $user_id New WordPress user ID.
	 */
	public static function on_user_register( int $user_id ): void {
		if ( $user_id < 1 ) {
			return;
		}

		if ( ! class_exists( 'LPNW_Subscriber' ) ) {
			return;
		}

		if ( null !== LPNW_Subscriber::get_preferences( $user_id ) ) {
			return;
		}

		self::bootstrap_default_preferences( $user_id );
		update_user_meta( $user_id, self::USER_META_REDIRECT_PENDING, '1' );
	}

	/**
	 * Insert NW-wide defaults so matching can run before the user customises.
	 *
	 * @param int $user_id User ID.
	 */
	public static function bootstrap_default_preferences( int $user_id ): void {
		if ( $user_id < 1 || ! class_exists( 'LPNW_Subscriber' ) ) {
			return;
		}

		if ( null !== LPNW_Subscriber::get_preferences( $user_id ) ) {
			return;
		}

		$areas = defined( 'LPNW_NW_POSTCODES' ) && is_array( LPNW_NW_POSTCODES )
			? array_values( LPNW_NW_POSTCODES )
			: array();

		$tier = LPNW_Subscriber::get_tier( $user_id );
		$freq = 'weekly';
		if ( in_array( $tier, array( 'pro', 'vip' ), true ) ) {
			$freq = 'instant';
		}

		LPNW_Subscriber::save_preferences(
			$user_id,
			array(
				'areas'                 => $areas,
				'property_types'        => array(),
				'alert_types'           => array( 'listing' ),
				'listing_channels'      => array(),
				'tenure_preferences'    => array(),
				'required_features'     => array(),
				'frequency'             => $freq,
				'is_active'             => 1,
				'mark_setup_incomplete' => true,
			)
		);
	}

	/**
	 * After login, send new accounts to preferences when no other redirect was requested.
	 *
	 * @param string           $redirect_to           Default redirect.
	 * @param string           $requested_redirect_to From request.
	 * @param WP_User|WP_Error $user                  User logging in.
	 * @return string
	 */
	public static function filter_login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		if ( class_exists( 'LPNW_Subscriber' ) && null === LPNW_Subscriber::get_preferences( $user->ID ) ) {
			self::bootstrap_default_preferences( $user->ID );
			update_user_meta( $user->ID, self::USER_META_REDIRECT_PENDING, '1' );
		}

		if ( '' !== $requested_redirect_to ) {
			return $redirect_to;
		}

		if ( '1' !== (string) get_user_meta( $user->ID, self::USER_META_REDIRECT_PENDING, true ) ) {
			return $redirect_to;
		}

		return home_url( '/preferences/?lpnw_welcome=1' );
	}

	/**
	 * If the user is still pending setup, send them to preferences once (any front-end URL).
	 */
	public static function maybe_redirect_to_preferences(): void {
		if ( ! is_user_logged_in() || is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$uid = get_current_user_id();
		if ( $uid < 1 || '1' !== (string) get_user_meta( $uid, self::USER_META_REDIRECT_PENDING, true ) ) {
			return;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( is_string( $path ) && str_contains( $path, '/preferences' ) ) {
			delete_user_meta( $uid, self::USER_META_REDIRECT_PENDING );
			return;
		}

		wp_safe_redirect( home_url( '/preferences/?lpnw_welcome=1' ) );
		exit;
	}

	/**
	 * Mark that the subscriber explicitly saved the preferences form (not auto-bootstrap).
	 *
	 * @param int $user_id User ID.
	 */
	public static function mark_setup_complete( int $user_id ): void {
		if ( $user_id < 1 ) {
			return;
		}
		update_user_meta( $user_id, self::USER_META_SETUP_COMPLETE, '1' );
		delete_user_meta( $user_id, self::USER_META_REDIRECT_PENDING );
	}

	/**
	 * Whether the user has completed at least one explicit save on the preferences form.
	 *
	 * @param int $user_id User ID.
	 */
	public static function has_completed_setup( int $user_id ): bool {
		return $user_id > 0 && '1' === (string) get_user_meta( $user_id, self::USER_META_SETUP_COMPLETE, true );
	}
}
