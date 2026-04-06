<?php
/**
 * Must-use plugin: one-time WooCommerce and content setup.
 * Drop into wp-content/mu-plugins/ to auto-execute.
 * Self-deletes after successful run.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action(
	'init',
	function () {
		if ( empty( $_GET['lpnw_setup'] ) || 'run' !== $_GET['lpnw_setup'] ) {
			return;
		}
		$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
		if ( ! lpnw_tool_query_key_ok( $key ) ) {
			return;
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<html><head><title>LPNW Setup</title>';
		echo '<style>body{font-family:sans-serif;max-width:700px;margin:40px auto;padding:0 20px;line-height:1.6;} .ok{color:green;} .warn{color:orange;} .err{color:red;}</style>';
		echo '</head><body><h1>LPNW Setup Runner</h1>';

		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		if ( ! empty( $admins[0] ) ) {
			wp_set_current_user( (int) $admins[0] );
		}

		// WooCommerce setup
		$woo_file = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-setup-woocommerce.php';
		if ( is_readable( $woo_file ) ) {
			require_once $woo_file;
			$report = LPNW_Setup_WooCommerce::run();
			if ( ! empty( $report['summary_lines'] ) ) {
				foreach ( $report['summary_lines'] as $line ) {
					echo '<p>' . esc_html( $line ) . '</p>';
				}
			}
			echo '<p class="ok">WooCommerce setup complete.</p>';
		} else {
			echo '<p class="err">WooCommerce setup class not found.</p>';
		}

		// Page content setup
		$content_file = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-page-content.php';
		if ( is_readable( $content_file ) ) {
			require_once $content_file;

			$pages = array(
				'Home'             => LPNW_Page_Content::get_home_content(),
				'About'            => LPNW_Page_Content::get_about_content(),
				'Pricing'          => LPNW_Page_Content::get_pricing_content(),
				'Contact'          => LPNW_Page_Content::get_contact_content(),
				'Privacy Policy'   => LPNW_Page_Content::get_privacy_content(),
				'Terms of Service' => LPNW_Page_Content::get_terms_content(),
			);

			foreach ( $pages as $title => $content ) {
				$page = get_page_by_title( $title, OBJECT, 'page' );
				if ( $page ) {
					wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
					echo '<p>Updated page: ' . esc_html( $title ) . '</p>';
				}
			}
			echo '<p class="ok">Page content updated.</p>';
		} else {
			echo '<p class="err">Page content class not found.</p>';
		}

		// Blog posts
		$blog_file = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-blog-content.php';
		if ( is_readable( $blog_file ) ) {
			require_once $blog_file;

			$cats = LPNW_Blog_Content::get_categories();
			foreach ( $cats as $cat ) {
				if ( ! term_exists( $cat['slug'], 'category' ) ) {
					wp_insert_term( $cat['name'], 'category', array( 'slug' => $cat['slug'] ) );
				}
			}

			$posts = LPNW_Blog_Content::get_posts();
			foreach ( $posts as $post ) {
				$existing = get_page_by_path( $post['slug'], OBJECT, 'post' );
				if ( ! $existing ) {
					$cat_term = get_term_by( 'slug', $post['category'], 'category' );
					$cat_id   = $cat_term ? $cat_term->term_id : 1;

					wp_insert_post( array(
						'post_title'    => $post['title'],
						'post_name'     => $post['slug'],
						'post_content'  => $post['content'],
						'post_excerpt'  => $post['excerpt'],
						'post_status'   => 'publish',
						'post_type'     => 'post',
						'post_category' => array( $cat_id ),
					) );
					echo '<p>Published: ' . esc_html( $post['title'] ) . '</p>';
				} else {
					echo '<p>Exists: ' . esc_html( $post['title'] ) . '</p>';
				}
			}
			echo '<p class="ok">Blog posts published.</p>';
		} else {
			echo '<p class="warn">Blog content class not found. Skipping.</p>';
		}

		// Install RankMath SEO
		if ( ! is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';

			if ( ! file_exists( WP_PLUGIN_DIR . '/seo-by-rank-math/rank-math.php' ) ) {
				$api = plugins_api( 'plugin_information', array( 'slug' => 'seo-by-rank-math', 'fields' => array( 'sections' => false ) ) );
				if ( ! is_wp_error( $api ) ) {
					$skin     = new \Automatic_Upgrader_Skin();
					$upgrader = new \Plugin_Upgrader( $skin );
					$upgrader->install( $api->download_link );
					echo '<p>RankMath SEO installed.</p>';
				}
			}

			$activated = activate_plugin( 'seo-by-rank-math/rank-math.php' );
			if ( ! is_wp_error( $activated ) ) {
				echo '<p class="ok">RankMath SEO activated.</p>';
			} else {
				echo '<p class="err">RankMath activation failed: ' . esc_html( $activated->get_error_message() ) . '</p>';
			}
		} else {
			echo '<p>RankMath SEO already active.</p>';
		}

		// Self-delete
		$self = __FILE__;
		echo '<hr><p class="ok"><strong>Setup complete.</strong></p>';
		echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">View site</a></p>';
		echo '<p class="warn">This mu-plugin will self-delete now.</p>';
		echo '</body></html>';

		@unlink( $self );
		exit;
	},
	1
);
