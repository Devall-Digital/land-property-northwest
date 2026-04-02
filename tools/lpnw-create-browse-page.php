<?php
/**
 * One-shot MU-plugin: ensure Browse Properties page exists, verify shortcode + template, then remove itself.
 *
 * Upload to: wp-content/mu-plugins/lpnw-create-browse-page.php
 * Visit the site once (front or admin). If checks pass, this file deletes itself.
 * If unlink fails, option lpnw_mu_browse_page_done prevents repeat work on every request.
 *
 * @package LPNW_Property_Alerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		if ( get_option( 'lpnw_mu_browse_page_done' ) ) {
			return;
		}

		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return;
		}

		$slug      = 'properties';
		$shortcode = '[lpnw_property_search]';
		$marker    = 'lpnw-property-search';

		$plugin_dir = WP_PLUGIN_DIR . '/lpnw-property-alerts';
		$template   = $plugin_dir . '/public/views/property-search.php';
		$template_ok = is_readable( $template );

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'name'           => $slug,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		$page = ! empty( $pages ) ? $pages[0] : null;

		if ( ! $page ) {
			$id = wp_insert_post(
				array(
					'post_title'   => __( 'Browse Properties', 'lpnw-alerts' ),
					'post_name'    => $slug,
					'post_content' => $shortcode,
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				true
			);

			if ( is_wp_error( $id ) || ! $id ) {
				return;
			}

			$page = get_post( $id );
		} else {
			$content = (string) $page->post_content;
			if ( false === strpos( $content, 'lpnw_property_search' ) ) {
				$new_content = trim( $content . "\n\n" . $shortcode );
				wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => $new_content,
					)
				);
				$page = get_post( $page->ID );
			}

			if ( $page && 'publish' !== $page->post_status ) {
				wp_update_post(
					array(
						'ID'          => $page->ID,
						'post_status' => 'publish',
					)
				);
				$page = get_post( $page->ID );
			}
		}

		if ( ! $page || 'publish' !== $page->post_status ) {
			return;
		}

		if ( ! shortcode_exists( 'lpnw_property_search' ) ) {
			return;
		}

		$rendered    = do_shortcode( $shortcode );
		$renders_ok  = ( strlen( $rendered ) > 50 && false !== strpos( $rendered, $marker ) );

		$all_ok = $template_ok && $renders_ok;

		if ( $all_ok ) {
			update_option( 'lpnw_mu_browse_page_done', time(), false );
			$self = __FILE__;
			if ( is_string( $self ) && '' !== $self && file_exists( $self ) && is_writable( $self ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- intentional one-shot self-removal.
				@unlink( $self );
			}
		}
	},
	999
);
