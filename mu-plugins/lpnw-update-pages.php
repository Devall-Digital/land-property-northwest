<?php
/**
 * Legacy one-shot: sync Home, About, Pricing, Contact post_content from LPNW_Page_Content.
 *
 * Prefer the built-in handler in the plugin (LPNW 1.0.17+): same URL, does not self-delete.
 * This file removes itself after success if you still upload it.
 *
 * Requires `?key=` matching `LPNW_PAGE_SYNC_SECRET` or `LPNW_CRON_SECRET` in wp-config.php.
 *
 * @package LPNW_MU
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_action(
	'init',
	function () {
		if ( empty( $_GET['lpnw_update'] ) || 'pages' !== $_GET['lpnw_update'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET shared secret, not a form nonce.
		$provided = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';
		$allowed  = false;
		if ( defined( 'LPNW_PAGE_SYNC_SECRET' ) && '' !== (string) LPNW_PAGE_SYNC_SECRET ) {
			$allowed = hash_equals( (string) LPNW_PAGE_SYNC_SECRET, $provided );
		} elseif ( defined( 'LPNW_CRON_SECRET' ) && '' !== (string) LPNW_CRON_SECRET ) {
			$allowed = hash_equals( (string) LPNW_CRON_SECRET, $provided );
		}
		if ( ! $allowed ) {
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
		$self = __FILE__;
		if ( is_string( $self ) && is_readable( $self ) ) {
			@unlink( $self );
		}
		exit;
	},
	1
);
