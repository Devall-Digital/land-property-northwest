<?php
/**
 * PNG favicons, web app manifest, and theme color when no Customizer site icon is set.
 *
 * If the admin uploads a Site Icon (Appearance > Customize > Site Identity), WordPress
 * outputs its own tags and this class stays quiet to avoid duplicates.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Head tags for theme PNG icon and PWA-lite manifest.
 */
final class LPNW_Favicons {

	public const THEME_COLOR = '#1B2A4A';

	/**
	 * Register hooks.
	 */
	public static function bootstrap(): void {
		add_action( 'wp_head', array( __CLASS__, 'maybe_output_head_tags' ), 2 );
		add_action( 'login_head', array( __CLASS__, 'maybe_output_login_head_tags' ), 1 );
	}

	/**
	 * Print favicon links, manifest, and theme-color when WP is not using a custom site icon.
	 */
	public static function maybe_output_head_tags(): void {
		if ( is_admin() || wp_is_json_request() || is_feed() || is_embed() ) {
			return;
		}

		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			return;
		}

		self::output_icon_tags();
	}

	/**
	 * Same favicon bundle on wp-login.php (theme wp_head does not run there).
	 */
	public static function maybe_output_login_head_tags(): void {
		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			return;
		}

		self::output_icon_tags();
	}

	/**
	 * Echo link and meta tags (single shared PNG).
	 */
	private static function output_icon_tags(): void {
		$icon     = get_stylesheet_directory_uri() . '/assets/img/lpnw-brand-icon.png';
		$manifest = get_stylesheet_directory_uri() . '/assets/site.webmanifest';

		echo '<link rel="icon" href="' . esc_url( $icon ) . '" type="image/png" sizes="512x512">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $icon ) . '">' . "\n";
		echo '<link rel="manifest" href="' . esc_url( $manifest ) . '">' . "\n";
		printf(
			'<meta name="theme-color" content="%s">' . "\n",
			esc_attr( self::THEME_COLOR )
		);
		printf(
			'<meta name="msapplication-TileColor" content="%s">' . "\n",
			esc_attr( self::THEME_COLOR )
		);
	}
}
