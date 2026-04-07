<?php
/**
 * Dynamic Open Graph preview image (1200×630 PNG): brand + messaging, not a raw hero photo.
 *
 * Served at: home_url( '/?lpnw_og=card&key=...&v=home|default' )
 * Key is site-specific (HMAC); appears in og:image HTML only (crawlers need it).
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders social share card images on the fly.
 */
final class LPNW_OG_Card {

	public const WIDTH  = 1200;
	public const HEIGHT = 630;

	/**
	 * Bootstrap template_redirect handler.
	 */
	public static function bootstrap(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_image' ), 0 );
	}

	/**
	 * Public URL for the dynamic card (cache-busted).
	 *
	 * @param string $variant 'home' or 'default'.
	 * @return string
	 */
	public static function get_image_url( string $variant ): string {
		$variant = ( 'home' === $variant ) ? 'home' : 'default';
		$key     = self::get_request_key();

		return add_query_arg(
			array(
				'lpnw_og' => 'card',
				'key'     => $key,
				'v'       => $variant,
				't'       => (string) self::cache_buster(),
			),
			home_url( '/' )
		);
	}

	/**
	 * Integer that changes when copy changes (bust Facebook cache after deploy).
	 */
	public static function cache_buster(): int {
		return 2;
	}

	/**
	 * Site-specific key (no extra wp-config required).
	 *
	 * @return string
	 */
	public static function get_request_key(): string {
		if ( defined( 'LPNW_OG_CARD_SECRET' ) && is_string( LPNW_OG_CARD_SECRET ) && LPNW_OG_CARD_SECRET !== '' ) {
			return hash( 'sha256', LPNW_OG_CARD_SECRET );
		}

		return hash_hmac( 'sha256', 'lpnw_og_card_v2', wp_salt( 'nonce' ) );
	}

	/**
	 * Output PNG and exit when query matches.
	 */
	public static function maybe_serve_image(): void {
		if ( is_admin() || ! isset( $_GET['lpnw_og'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$mode = sanitize_key( wp_unslash( $_GET['lpnw_og'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'card' !== $mode ) {
			return;
		}

		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! hash_equals( self::get_request_key(), $key ) ) {
			status_header( 403 );
			exit;
		}

		$variant = isset( $_GET['v'] ) ? sanitize_key( wp_unslash( $_GET['v'] ) ) : 'default'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $variant, array( 'home', 'default' ), true ) ) {
			$variant = 'default';
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			status_header( 503 );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: public, max-age=86400' );

		$png = self::render_png_binary( $variant );
		if ( '' === $png ) {
			status_header( 500 );
			exit;
		}

		echo $png; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw PNG bytes.
		exit;
	}

	/**
	 * @param string $variant home|default.
	 * @return string PNG binary or empty on failure.
	 */
	private static function render_png_binary( string $variant ): string {
		$w = self::WIDTH;
		$h = self::HEIGHT;

		$im = imagecreatetruecolor( $w, $h );
		if ( false === $im ) {
			return '';
		}

		imagealphablending( $im, false );
		imagesavealpha( $im, false );

		$navy   = imagecolorallocate( $im, 15, 29, 53 );
		$amber  = imagecolorallocate( $im, 240, 165, 0 );
		$teal   = imagecolorallocate( $im, 0, 212, 170 );
		$white  = imagecolorallocate( $im, 255, 255, 255 );
		$muted  = imagecolorallocate( $im, 200, 210, 225 );
		$bar_bg = imagecolorallocate( $im, 26, 45, 79 );

		imagefilledrectangle( $im, 0, 0, $w, $h, $navy );

		// Soft corner glow (single ellipse, cheap).
		$glow = imagecolorallocatealpha( $im, 0, 212, 170, 115 );
		imagefilledellipse( $im, (int) ( $w * 0.78 ), (int) ( $h * 0.35 ), 520, 380, $glow );

		imagefilledrectangle( $im, 0, 0, 12, $h, $amber );
		imagefilledrectangle( $im, 0, $h - 140, $w, $h, $bar_bg );
		imagefilledrectangle( $im, 12, $h - 6, 400, $h, $teal );

		$font    = get_stylesheet_directory() . '/assets/fonts/DejaVuSans-Bold.ttf';
		$use_ttf = is_readable( $font );

		$site = get_bloginfo( 'name' );
		if ( ! is_string( $site ) || $site === '' ) {
			$site = 'Land & Property Northwest';
		}

		if ( 'home' === $variant ) {
			$headline = __( 'Instant NW property alerts', 'lpnw-theme' );
			$lines    = array(
				__( 'Rightmove, OnTheMarket, planning, auctions, EPC signals', 'lpnw-theme' ),
				__( 'and Land Registry data in one subscription.', 'lpnw-theme' ),
			);
		} else {
			$headline = self::get_context_headline();
			$lines    = self::wrap_description_lines(
				self::get_context_description(),
				$use_ttf ? $font : '',
				22,
				1040
			);
		}

		// imagettftext: y is the baseline.
		$baseline = 98;
		if ( $use_ttf ) {
			self::ttf_text( $im, $font, 24, 64, $baseline, $muted, self::utf8( $site ) );
			$baseline += 58;
			self::ttf_text( $im, $font, 40, 64, $baseline, $white, self::utf8( $headline ) );
			$baseline += 62;
			foreach ( $lines as $line ) {
				self::ttf_text( $im, $font, 21, 64, $baseline, $muted, self::utf8( $line ) );
				$baseline += 34;
			}
			self::ttf_text( $im, $font, 17, 64, $h - 48, $muted, self::utf8( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'land-property-northwest.co.uk' ) );
		} else {
			$y = 70;
			imagestring( $im, 5, 64, $y, self::latin1_clip( $site, 60 ), $muted );
			$y += 26;
			imagestring( $im, 5, 64, $y, self::latin1_clip( $headline, 50 ), $white );
			$y += 26;
			foreach ( $lines as $line ) {
				imagestring( $im, 4, 64, $y, self::latin1_clip( $line, 70 ), $muted );
				$y += 20;
			}
		}

		ob_start();
		imagepng( $im, null, 6 );
		imagedestroy( $im );
		$out = ob_get_clean();

		return is_string( $out ) ? $out : '';
	}

	/**
	 * @param resource $im GD image.
	 */
	private static function ttf_text( $im, string $font, float $size, float $x, float $y, int $color, string $text ): void {
		if ( $text === '' ) {
			return;
		}
		imagettftext( $im, $size, 0, (int) round( $x ), (int) round( $y ), $color, $font, $text );
	}

	private static function utf8( string $s ): string {
		return $s;
	}

	private static function latin1_clip( string $s, int $max_len ): string {
		$s = wp_strip_all_tags( $s );
		if ( function_exists( 'iconv' ) ) {
			$conv = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $s ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $conv ) && $conv !== '' ) {
				$s = $conv;
			}
		}
		if ( strlen( $s ) > $max_len ) {
			$s = substr( $s, 0, $max_len - 1 ) . '…';
		}

		return $s;
	}

	private static function get_context_headline(): string {
		if ( is_page( 'pricing' ) ) {
			return __( 'Plans for every investor', 'lpnw-theme' );
		}
		if ( is_page( 'properties' ) || is_page( 'browse-properties' ) ) {
			return __( 'Browse live NW listings', 'lpnw-theme' );
		}
		if ( is_page( 'about' ) ) {
			return __( 'Property intelligence for the Northwest', 'lpnw-theme' );
		}
		if ( is_page( 'contact' ) ) {
			return __( 'Talk to the team', 'lpnw-theme' );
		}
		if ( is_singular() ) {
			return get_the_title() ?: __( 'Northwest property intelligence', 'lpnw-theme' );
		}

		return __( 'Northwest property intelligence', 'lpnw-theme' );
	}

	private static function get_context_description(): string {
		$default = __( 'Paid alerts when properties and land opportunities match your criteria across Greater Manchester, Merseyside, Lancashire, Cheshire, and Cumbria.', 'lpnw-theme' );

		if ( is_page( 'pricing' ) ) {
			return __( 'Free weekly digest or instant Pro alerts from £19.99/month. Investor VIP for priority coverage.', 'lpnw-theme' );
		}
		if ( is_page( 'properties' ) || is_page( 'browse-properties' ) ) {
			return __( 'Filter by area, price, type, tenure, and source. See what is on the market across the Northwest in one place.', 'lpnw-theme' );
		}
		if ( is_page( 'about' ) ) {
			return __( 'We aggregate listings, planning, auctions, EPC signals, and registry data so you hear about deals first.', 'lpnw-theme' );
		}
		if ( is_page( 'contact' ) ) {
			return __( 'Questions about coverage, billing, or partnerships? Reach us from the contact page.', 'lpnw-theme' );
		}
		if ( is_singular() ) {
			$excerpt = get_the_excerpt();
			if ( is_string( $excerpt ) && trim( $excerpt ) !== '' ) {
				return wp_strip_all_tags( $excerpt );
			}
		}

		return $default;
	}

	/**
	 * Word-wrap for TTF width in pixels.
	 *
	 * @return array<int, string>
	 */
	private static function wrap_description_lines( string $text, string $font, float $size, float $max_width ): array {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		if ( ! is_string( $text ) ) {
			$text = '';
		}
		$text = trim( $text );

		if ( $font === '' || ! is_readable( $font ) ) {
			$chunk = 70;
			return str_split( $text, $chunk ) ?: array( $text );
		}

		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			return array( $text );
		}

		$lines = array();
		$line  = '';
		foreach ( $words as $word ) {
			$test = ( $line === '' ) ? $word : $line . ' ' . $word;
			$box  = imagettfbbox( $size, 0, $font, $test );
			if ( false === $box ) {
				$line = $test;
				continue;
			}
			$width = abs( $box[2] - $box[0] );
			if ( $width > $max_width && $line !== '' ) {
				$lines[] = $line;
				$line    = $word;
			} else {
				$line = $test;
			}
		}
		if ( $line !== '' ) {
			$lines[] = $line;
		}

		$lines = array_slice( $lines, 0, 5 );

		return $lines;
	}
}
