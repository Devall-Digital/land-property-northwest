<?php
if ( ! defined( 'ABSPATH' ) ) { return; }
add_action( 'wp_loaded', function() {
	if ( empty( $_GET['lpnw_create_pp'] ) || 'run' !== $_GET['lpnw_create_pp'] ) { return; }
	if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) { return; }
	header( 'Content-Type: text/plain; charset=utf-8' );

	$existing = get_page_by_path( 'privacy-policy' );
	if ( $existing ) {
		echo "Privacy Policy page exists: ID {$existing->ID}, status {$existing->post_status}\n";
		if ( 'publish' !== $existing->post_status ) {
			wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
			echo "Published.\n";
		}
	} else {
		$content = '';
		if ( class_exists( 'LPNW_Page_Content' ) ) {
			$content = LPNW_Page_Content::get_privacy_content();
		} else {
			$content = '<p>Privacy policy content to be added.</p>';
		}
		$id = wp_insert_post( array(
			'post_title'   => 'Privacy Policy',
			'post_name'    => 'privacy-policy',
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
		echo "Created Privacy Policy: ID {$id}\n";
		update_option( 'wp_page_for_privacy_policy', $id );
		echo "Set as privacy policy page.\n";
	}

	flush_rewrite_rules( false );
	@unlink( __FILE__ );
	exit;
}, 0 );
