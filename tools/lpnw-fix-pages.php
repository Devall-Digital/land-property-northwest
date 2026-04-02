<?php
/**
 * Must-use plugin: one-time fixes for Blog posts page, Privacy Policy slug, and area map shortcodes.
 *
 * Upload to wp-content/mu-plugins/lpnw-fix-pages.php, visit the site once (any URL), then the file removes itself.
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_action(
	'init',
	static function () {
		$self = __FILE__;
		if ( ! is_readable( $self ) ) {
			return;
		}

		if ( ! function_exists( 'wp_insert_post' ) ) {
			return;
		}

		$author_id = 1;
		$admins    = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
			)
		);
		if ( ! empty( $admins ) && isset( $admins[0]->ID ) ) {
			$author_id = (int) $admins[0]->ID;
		}

		// 1) Blog page + Posts page setting.
		$blog = get_page_by_path( 'blog', OBJECT, 'page' );
		if ( ! $blog instanceof WP_Post ) {
			$blog_id = wp_insert_post(
				wp_slash(
					array(
						'post_title'   => __( 'Blog', 'default' ),
						'post_name'    => 'blog',
						'post_content' => '',
						'post_status'  => 'publish',
						'post_type'    => 'page',
						'post_author'  => $author_id,
					)
				),
				true
			);
			if ( ! is_wp_error( $blog_id ) ) {
				update_option( 'page_for_posts', (int) $blog_id );
			}
		} else {
			update_option( 'page_for_posts', (int) $blog->ID );
		}
		flush_rewrite_rules( false );

		// 2) Privacy Policy: slug privacy-policy, create or fix, sync WP privacy option.
		$content_file = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-page-content.php';
		$privacy_id   = 0;

		$by_slug = get_page_by_path( 'privacy-policy', OBJECT, 'page' );
		if ( $by_slug instanceof WP_Post ) {
			$privacy_id = (int) $by_slug->ID;
		} else {
			$by_title = get_page_by_title( 'Privacy Policy', OBJECT, 'page' );
			if ( $by_title instanceof WP_Post ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'        => $by_title->ID,
							'post_name' => 'privacy-policy',
						)
					)
				);
				$privacy_id = (int) $by_title->ID;
			} elseif ( is_readable( $content_file ) ) {
				require_once $content_file;
				if ( class_exists( 'LPNW_Page_Content' ) ) {
					$privacy_id = wp_insert_post(
						wp_slash(
							array(
								'post_title'   => 'Privacy Policy',
								'post_name'    => 'privacy-policy',
								'post_content' => LPNW_Page_Content::get_privacy_content(),
								'post_status'  => 'publish',
								'post_type'    => 'page',
								'post_author'  => $author_id,
							)
						),
						true
					);
					if ( is_wp_error( $privacy_id ) ) {
						$privacy_id = 0;
					} else {
						$privacy_id = (int) $privacy_id;
					}
				}
			}
		}

		if ( $privacy_id > 0 ) {
			update_option( 'wp_page_for_privacy_policy', $privacy_id );
		}

		// 3) Area landing pages: add limit="100" to map shortcode if missing (DB content).
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$area_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_name LIKE %s",
				'page',
				'publish',
				$wpdb->esc_like( 'property-alerts-' ) . '%'
			)
		);
		if ( is_array( $area_ids ) ) {
			foreach ( $area_ids as $aid ) {
				$post = get_post( (int) $aid );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$updated = preg_replace_callback(
					'/\[lpnw_property_map\s+([^\]]*)\]/',
					static function ( array $m ) {
						if ( preg_match( '/\blimit\s*=/', $m[1] ) ) {
							return $m[0];
						}
						return '[lpnw_property_map ' . trim( $m[1] ) . ' limit="100"]';
					},
					$post->post_content
				);
				if ( is_string( $updated ) && $updated !== $post->post_content ) {
					wp_update_post(
						wp_slash(
							array(
								'ID'           => $post->ID,
								'post_content' => $updated,
							)
						)
					);
				}
			}
		}

		// Self-delete.
		if ( is_writable( $self ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $self );
		}
	},
	1
);
