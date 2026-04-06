<?php
/**
 * Log in as admin or test subscriber when wp-login.php is awkward (e.g. host WAF).
 *
 * Requires LPNW_LOGIN_AS_SECRET in wp-config.php (non-empty). Agents use the same value
 * in Cursor environment secrets on the URL as &key=...
 *
 * URL examples:
 *   /?nocache=1&lpnw_login_as=admin&key=SECRET   → wp-admin
 *   /?nocache=1&lpnw_login_as=test&key=SECRET   → test user dashboard
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Login bypass via GET + wp-config secret.
		if ( empty( $_GET['lpnw_login_as'] ) ) {
			return;
		}

		if ( ! defined( 'LPNW_LOGIN_AS_SECRET' ) || '' === (string) LPNW_LOGIN_AS_SECRET ) {
			return;
		}

		$provided = '';
		if ( isset( $_GET['key'] ) && is_string( $_GET['key'] ) ) {
			$provided = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		}

		if ( '' === $provided || ! hash_equals( (string) LPNW_LOGIN_AS_SECRET, $provided ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$target = sanitize_text_field( wp_unslash( $_GET['lpnw_login_as'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'test' === $target ) {
			$user = get_user_by( 'email', 'admin@codevall.co.uk' );
		} elseif ( 'admin' === $target ) {
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			);
			$user   = ! empty( $admins[0] ) ? get_userdata( (int) $admins[0] ) : null;
		} else {
			return;
		}

		if ( ! $user ) {
			wp_die( esc_html( 'User not found.' ) );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect = ( 'test' === $target ) ? home_url( '/dashboard/' ) : admin_url();
		wp_safe_redirect( $redirect );
		exit;
	},
	0
);
