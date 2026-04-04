<?php
/**
 * One-time auto-login for wp-admin.
 * Bypasses wp-login.php by setting auth cookies directly.
 * Requires LPNW_LOGIN_AS_SECRET in wp-config.php. Self-deletes after successful login.
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! defined( 'LPNW_LOGIN_AS_SECRET' ) || '' === LPNW_LOGIN_AS_SECRET ) {
	return;
}

add_action(
	'init',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- One-shot GET login bypass for host WAF; secret in wp-config.
		if ( empty( $_GET['lpnw_autologin'] ) || 'admin' !== $_GET['lpnw_autologin'] ) {
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

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);

		if ( empty( $admins[0] ) ) {
			wp_die( esc_html( 'No admin user found.' ) );
		}

		$user_id = (int) $admins[0];
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html( 'No admin user found.' ) );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', $user->user_login, $user );

		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( __FILE__ );
		}

		wp_safe_redirect( admin_url() );
		exit;
	},
	0
);
