<?php
/**
 * Update page content from LPNW_Page_Content class.
 * Self-deletes after running.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action(
	'init',
	function () {
		if ( empty( $_GET['lpnw_update'] ) || 'pages' !== $_GET['lpnw_update'] ) {
			return;
		}
		$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
		if ( ! lpnw_tool_query_key_ok( $key ) ) {
			return;
		}

		$file = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-page-content.php';
		if ( ! is_readable( $file ) ) {
			wp_die( 'Page content class not found.' );
		}
		require_once $file;

		header( 'Content-Type: text/plain; charset=utf-8' );

		$pages = array(
			'Home'    => LPNW_Page_Content::get_home_content(),
			'About'   => LPNW_Page_Content::get_about_content(),
			'Pricing' => LPNW_Page_Content::get_pricing_content(),
			'Contact' => LPNW_Page_Content::get_contact_content(),
		);

		foreach ( $pages as $title => $content ) {
			$page = get_page_by_title( $title, OBJECT, 'page' );
			if ( $page ) {
				wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
				echo "Updated: {$title}\n";
			} else {
				echo "Not found: {$title}\n";
			}
		}

		wp_cache_flush();
		echo "\nCache flushed. Done.\n";
		@unlink( __FILE__ );
		exit;
	},
	1
);
