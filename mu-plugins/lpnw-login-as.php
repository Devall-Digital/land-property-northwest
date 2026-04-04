<?php
/**
 * Emergency login when wp-login.php is difficult to reach (e.g. host WAF).
 *
 * Requires LPNW_LOGIN_AS_SECRET in wp-config.php (long random string). Without it,
 * this file does nothing. After a successful login, this file deletes itself; redeploy
 * from the repo if you need it again.
 *
 * URL examples (replace SECRET):
 *   /?lpnw_login_as=admin&key=SECRET   → wp-admin
 *   /?lpnw_login_as=test&key=SECRET    → subscriber test user (email in code below)
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LPNW_LOGIN_AS_SECRET' ) || '' === LPNW_LOGIN_AS_SECRET ) {
	return;
}

add_action(
	'init',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- One-shot GET login bypass for host WAF; secret in wp-config.
		if ( empty( $_GET['lpnw_login_as'] ) ) {
			return;
		}

		$provided = '';
		if ( isset( $_GET['key'] ) && is_string( $_GET['key'] ) ) {
			$provided = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $provided || ! hash_equals( (string) LPNW_LOGIN_AS_SECRET, $provided ) ) {
			return;
		}

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

		$self = __FILE__;
		if ( is_string( $self ) && is_readable( $self ) && function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $self );
		}

		wp_safe_redirect( $redirect );
		exit;
	},
	0
);
