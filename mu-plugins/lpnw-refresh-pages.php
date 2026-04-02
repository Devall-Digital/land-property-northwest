<?php
defined( 'ABSPATH' ) || exit;
add_action( 'wp_loaded', function () {
	$f = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-page-content.php';
	if ( ! class_exists( 'LPNW_Page_Content' ) && file_exists( $f ) ) { require_once $f; }
	if ( ! class_exists( 'LPNW_Page_Content' ) ) { return; }
	global $wpdb;
	$u = 0;
	$fid = (int) get_option( 'page_on_front' );
	if ( $fid > 0 ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => LPNW_Page_Content::get_home_content() ), array( 'ID' => $fid ), array( '%s' ), array( '%d' ) );
		clean_post_cache( $fid ); $u++;
	}
	wp_cache_flush();
	error_log( '[LPNW] Refreshed ' . $u . ' pages (v6 immersive)' );
	@unlink( __FILE__ );
}, 99 );
