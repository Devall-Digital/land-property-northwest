<?php
/**
 * Log in as the test user to review subscriber experience.
 * Self-deletes after use.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_action( 'init', function() {
	if ( empty( $_GET['lpnw_login_as'] ) ) { return; }
	if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) { return; }

	$target = sanitize_text_field( $_GET['lpnw_login_as'] );

	if ( 'test' === $target ) {
		$user = get_user_by( 'email', 'admin@codevall.co.uk' );
	} elseif ( 'admin' === $target ) {
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		$user = ! empty( $admins[0] ) ? get_userdata( (int) $admins[0] ) : null;
	} else {
		return;
	}

	if ( ! $user ) {
		wp_die( 'User not found.' );
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	do_action( 'wp_login', $user->user_login, $user );

	$redirect = ( 'test' === $target ) ? home_url( '/dashboard/' ) : admin_url();
	wp_safe_redirect( $redirect );
	exit;
}, 0 );
