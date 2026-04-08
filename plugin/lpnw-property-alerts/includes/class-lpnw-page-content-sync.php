<?php
// phpcs:disable PEAR.Commenting.FileComment,PEAR.Commenting.ClassComment,PEAR.Commenting.FunctionComment,PEAR.NamingConventions.ValidFunctionName,Generic.Files.LineLength
/**
 * Syncs marketing page post_content from LPNW_Page_Content (Home, About, Pricing, Contact).
 *
 * The one-shot mu-plugin self-deletes after success; this class stays in the plugin so the
 * sync URL keeps working after deploys.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public GET handler to refresh DB page HTML from code.
 */
class LPNW_Page_Content_Sync {


	/**
	 * Register init hook.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'maybe_sync_on_query' ), 2 );
	}

	/**
	 * Run sync when ?lpnw_update=pages is present and the caller is authorized.
	 */
	public static function maybe_sync_on_query(): void {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET secret / capability gate, not a form nonce.
		if ( empty( $_GET['lpnw_update'] ) || 'pages' !== $_GET['lpnw_update'] ) {
			return;
		}

		if ( ! self::_is_authorized() ) {
			wp_die(
				esc_html__( 'Unauthorized.', 'lpnw-alerts' ),
				esc_html__( 'LPNW', 'lpnw-alerts' ),
				array( 'response' => 403 )
			);
		}

		if ( ! class_exists( 'LPNW_Page_Content' ) ) {
			$file = LPNW_PLUGIN_DIR . 'includes/class-lpnw-page-content.php';
			if ( is_readable( $file ) ) {
				include_once $file;
			}
		}

		if ( ! class_exists( 'LPNW_Page_Content' ) ) {
			wp_die( esc_html__( 'Page content class not found.', 'lpnw-alerts' ) );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );

		$pages = array(
			'Home'    => LPNW_Page_Content::get_home_content(),
			'About'   => LPNW_Page_Content::get_about_content(),
			'Pricing' => LPNW_Page_Content::get_pricing_content(),
			'Contact' => LPNW_Page_Content::get_contact_content(),
		);

		foreach ( $pages as $title => $content ) {
			$page_id = self::_get_page_id_by_title( $title );
			if ( $page_id ) {
				wp_update_post(
					array(
						'ID'           => $page_id,
						'post_content' => $content,
					)
				);
				echo 'Updated: ' . esc_html( $title ) . "\n";
			} else {
				echo 'Not found: ' . esc_html( $title ) . "\n";
			}
		}

		wp_cache_flush();
		echo "\nCache flushed. Done.\n";
		exit;
	}

	/**
	 * Authorization: administrator session, or shared secret in wp-config / filter.
	 *
	 * There is no default query key: without secrets, only a logged-in admin can sync.
	 *
	 * @return bool
	 */
	private static function _is_authorized(): bool {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- compared to server-side secrets.
		$provided = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';

		if ( defined( 'LPNW_PAGE_SYNC_SECRET' ) && '' !== (string) LPNW_PAGE_SYNC_SECRET ) {
			return hash_equals( (string) LPNW_PAGE_SYNC_SECRET, $provided );
		}

		if ( defined( 'LPNW_CRON_SECRET' ) && '' !== (string) LPNW_CRON_SECRET ) {
			return hash_equals( (string) LPNW_CRON_SECRET, $provided );
		}

		/**
		 * Filter: optional extra shared key for page sync (e.g. CI). Empty = not used.
		 *
		 * @param string $key Expected ?key= value; empty string disables this path.
		 */
		$filter_key = (string) apply_filters( 'lpnw_page_sync_allowed_key', '' );
		if ( '' !== $filter_key ) {
			return hash_equals( $filter_key, $provided );
		}

		return false;
	}

	/**
	 * Find a published page ID by exact title.
	 *
	 * @param string $title Page title.
	 *
	 * @return int Page ID or 0.
	 */
	private static function _get_page_id_by_title( string $title ): int {
		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'title'                  => $title,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return 0;
		}

		$post    = $query->posts[0];
		$page_id = ( $post instanceof WP_Post ) ? (int) $post->ID : 0;
		wp_reset_postdata();

		return $page_id;
	}
}

// phpcs:enable PEAR.Commenting.FileComment,PEAR.Commenting.ClassComment,PEAR.Commenting.FunctionComment,PEAR.NamingConventions.ValidFunctionName,Generic.Files.LineLength
