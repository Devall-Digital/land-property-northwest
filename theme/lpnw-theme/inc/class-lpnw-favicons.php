<?php
/**
 * Theme favicon assets when WordPress Site Icon is not configured.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Outputs standard favicon link tags from bundled PNG/ICO files.
 */
final class LPNW_Favicons {

	/**
	 * Register hooks.
	 */
	public static function bootstrap(): void {
		add_action( 'wp_head', array( self::class, 'maybe_output_frontend' ), 7 );
		add_action( 'login_head', array( self::class, 'maybe_output' ), 7 );
	}

	/**
	 * Front-end head: skip wp-admin and JSON/feed contexts.
	 */
	public static function maybe_output_frontend(): void {
		if ( is_admin() || wp_is_json_request() || is_feed() || is_embed() ) {
			return;
		}
		self::maybe_output();
	}

	/**
	 * Print favicon links unless Site Icon already handles them.
	 */
	public static function maybe_output(): void {
		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			return;
		}

		$dir = get_stylesheet_directory() . '/assets/img/favicons';
		$uri = get_stylesheet_directory_uri() . '/assets/img/favicons';

		$ico_path = $dir . '/favicon.ico';
		if ( ! is_readable( $ico_path ) ) {
			return;
		}

		$ver = (string) filemtime( $ico_path );
		$q   = '' !== $ver ? '?ver=' . rawurlencode( $ver ) : '';

		$svg      = esc_url( $uri . '/favicon.svg' . $q );
		$ico      = esc_url( $uri . '/favicon.ico' . $q );
		$i32      = esc_url( $uri . '/favicon-32x32.png' . $q );
		$i16      = esc_url( $uri . '/favicon-16x16.png' . $q );
		$apple    = esc_url( $uri . '/apple-touch-icon.png' . $q );
		$manifest = esc_url( $uri . '/site.webmanifest' . $q );

		if ( is_readable( $dir . '/favicon.svg' ) ) {
			echo '<link rel="icon" href="' . $svg . '" type="image/svg+xml" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above.
		}
		echo '<link rel="icon" href="' . $ico . '" sizes="any" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above.
		echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $i32 . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above.
		echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $i16 . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above.
		echo '<link rel="apple-touch-icon" href="' . $apple . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above.
		echo '<link rel="manifest" href="' . $manifest . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above.
	}
}
