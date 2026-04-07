<?php
/**
 * Favicon and manifest tags; theme wins over Customizer Site Icon for predictable tabs.
 *
 * Header uses transparent lpnw-brand-icon.png; tabs use lpnw-tab-icon.png (navy tile) for contrast on light UI.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Head tags: strip core wp_site_icon, print theme PNG links and manifest.
 */
final class LPNW_Favicons {

	public const THEME_COLOR = '#1B2A4A';

	/**
	 * Register hooks.
	 */
	public static function bootstrap(): void {
		add_action( 'wp_head', array( __CLASS__, 'strip_core_site_icon' ), 0 );
		add_action( 'login_head', array( __CLASS__, 'strip_core_site_icon_login' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'output_head_tags' ), 1 );
		add_action( 'login_head', array( __CLASS__, 'output_login_head_tags' ), 1 );
	}

	/**
	 * Remove WordPress default site icon on the front end (runs before priority 99).
	 */
	public static function strip_core_site_icon(): void {
		remove_action( 'wp_head', 'wp_site_icon', 99 );
	}

	/**
	 * Remove WordPress default site icon on wp-login.php.
	 */
	public static function strip_core_site_icon_login(): void {
		remove_action( 'login_head', 'wp_site_icon', 99 );
	}

	/**
	 * Print favicon links, manifest, and theme-color on the front end.
	 */
	public static function output_head_tags(): void {
		if ( is_admin() || wp_is_json_request() || is_feed() || is_embed() ) {
			return;
		}

		self::output_icon_tags();
	}

	/**
	 * Same bundle on wp-login.php.
	 */
	public static function output_login_head_tags(): void {
		self::output_icon_tags();
	}

	/**
	 * Echo link and meta tags (tab icon + manifest; brand PNG is for header/schema only).
	 */
	private static function output_icon_tags(): void {
		$tab      = get_stylesheet_directory_uri() . '/assets/img/lpnw-tab-icon.png';
		$manifest = get_stylesheet_directory_uri() . '/assets/site.webmanifest';

		echo '<link rel="icon" href="' . esc_url( $tab ) . '" type="image/png" sizes="192x192">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $tab ) . '">' . "\n";
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
