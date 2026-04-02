<?php
/**
 * One-shot MU-plugin: create Property Map page (slug map), then remove itself.
 *
 * Upload to: wp-content/mu-plugins/lpnw-create-map-page.php
 * Visit the site once. After the page is created or updated and published, this file deletes itself.
 * If unlink fails, option lpnw_mu_map_page_done prevents repeat work on every request.
 *
 * @package LPNW_Property_Alerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		if ( get_option( 'lpnw_mu_map_page_done' ) ) {
			return;
		}

		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return;
		}

		$slug      = 'map';
		$title     = 'Property Map';
		$shortcode = '[lpnw_property_map height="700px"]';

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
					'post_title'   => $title,
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
			if ( false === strpos( $content, 'lpnw_property_map' ) ) {
				wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => $shortcode,
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

			if ( $page && $title !== $page->post_title ) {
				wp_update_post(
					array(
						'ID'         => $page->ID,
						'post_title' => $title,
					)
				);
				$page = get_post( $page->ID );
			}
		}

		if ( ! $page || 'publish' !== $page->post_status ) {
			return;
		}

		update_option( 'lpnw_mu_map_page_done', time(), false );
		$self = __FILE__;
		if ( is_string( $self ) && '' !== $self && file_exists( $self ) && is_writable( $self ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- intentional one-shot self-removal.
			@unlink( $self );
		}
	},
	999
);
