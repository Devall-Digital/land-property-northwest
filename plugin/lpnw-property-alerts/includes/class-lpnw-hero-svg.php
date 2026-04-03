<?php
/**
 * Front-page hero: layered procedural SVG (afternoon cityscape, motion-safe SMIL).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Procedural cityscape with sky depth, slow clouds, and skyline parallax layers.
 */
class LPNW_Hero_Svg {

	public const VERSION = '5';

	private const VIEW_H = 520;

	/**
	 * @var bool
	 */
	private static $replaced_hero = false;

	public static function init(): void {
		add_action( 'wp', array( __CLASS__, 'register_front_page_content_filter' ) );
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_front_page_hero_ctas' ), 15 );
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function filter_body_class( array $classes ): array {
		if ( is_front_page() && is_user_logged_in() ) {
			$classes[] = 'lpnw-hero--logged-in';
		}

		return $classes;
	}

	/**
	 * Swap hero CTAs for logged-in visitors (post content only).
	 *
	 * @param string $content Post content.
	 */
	public static function filter_front_page_hero_ctas( string $content ): string {
		if ( ! is_front_page() || is_feed() || ! is_user_logged_in() ) {
			return $content;
		}

		if ( ! str_contains( $content, 'lpnw-hero__actions' ) ) {
			return $content;
		}

		$dash = esc_url( home_url( '/dashboard/' ) );
		$prefs = esc_url( home_url( '/preferences/' ) );

		$block = '<div class="lpnw-hero__actions">'
			. '<a class="lpnw-btn lpnw-btn--primary" href="' . $dash . '">' . esc_html__( 'Open dashboard', 'lpnw-alerts' ) . '</a>'
			. '<a class="lpnw-btn lpnw-btn--ghost" href="' . $prefs . '">' . esc_html__( 'Alert preferences', 'lpnw-alerts' ) . '</a>'
			. '</div>';

		$out = preg_replace(
			'/<div\s+class="lpnw-hero__actions"[^>]*>[\s\S]*?<\/div>/i',
			$block,
			$content,
			1
		);

		return is_string( $out ) ? $out : $content;
	}

	/**
	 * Front-page static pages may not run `the_content` inside a query loop; register once per request.
	 */
	public static function register_front_page_content_filter(): void {
		if ( is_admin() || ! is_front_page() || is_feed() ) {
			return;
		}
		add_filter( 'the_content', array( __CLASS__, 'filter_replace_illustration' ), 8 );
	}

	public static function filter_replace_illustration( string $content ): string {
		if ( self::$replaced_hero || ! is_front_page() || is_feed() ) {
			return $content;
		}

		if ( ! str_contains( $content, 'lpnw-hero__illustration' ) ) {
			return $content;
		}

		$new = self::get_illustration_markup();
		if ( '' === $new ) {
			return $content;
		}

		$out = preg_replace(
			'/<svg\b[^>]*\bclass="[^"]*\blpnw-hero__illustration\b[^"]*"[^>]*>[\s\S]*?<\/svg>/i',
			$new,
			$content,
			1
		);

		if ( ! is_string( $out ) || $out === $content ) {
			return $content;
		}

		$unwrapped = preg_replace(
			'/<div\s+class="lpnw-hero__scene"(?:\s+aria-hidden="true")?>\s*(<div\s+class="lpnw-hero__parallax"[\s\S]*?<\/div>)\s*<\/div>/i',
			'$1',
			$out,
			1
		);

		self::$replaced_hero = true;

		return is_string( $unwrapped ) ? $unwrapped : $out;
	}

	private static function hash99( int $seed, int $i ): int {
		return (int) ( ( $seed * 7919 + $i * 104729 ) % 100 );
	}

	private static function window_lit( int $seed, int $r, int $c, float $lit_pct ): bool {
		$h = self::hash99( $seed, $r * 997 + $c * 37 );

		return $h < ( $lit_pct * 100 );
	}

	/**
	 * @param float $gw Window width.
	 * @param float $gh Window height.
	 */
	private static function windows_grid( float $bx, float $by, float $bw, float $bh, int $seed, float $lit_pct, string $filter_id, float $gw, float $gh ): string {
		$gap    = 3.0 + ( self::hash99( $seed, 900 ) % 3 ) * 0.4;
		$pad    = 5.0;
		$header = 14.0 + ( self::hash99( $seed, 901 ) % 8 );
		$ox     = $bx + $pad;
		$oy     = $by + $header;
		$inner_w = $bw - 2 * $pad;
		$inner_h = $bh - $header - $pad;
		$cols   = max( 1, (int) floor( ( $inner_w + $gap ) / ( $gw + $gap ) ) );
		$rows   = max( 1, (int) floor( ( $inner_h + $gap ) / ( $gh + $gap ) ) );
		$html   = '';

		for ( $r = 0; $r < $rows; $r++ ) {
			for ( $c = 0; $c < $cols; $c++ ) {
				$x = $ox + $c * ( $gw + $gap );
				$y = $oy + $r * ( $gh + $gap );
				if ( $x + $gw > $bx + $bw - $pad || $y + $gh > $by + $bh - $pad ) {
					continue;
				}
				$lit = self::window_lit( $seed, $r, $c, $lit_pct );
				$h2  = self::hash99( $seed, 400 + $r * 31 + $c );
				if ( $lit ) {
					$op = 0.38 + ( $h2 % 40 ) / 100;
					$fl = ( 0 === $h2 % 4 ) ? '#fff8e6' : ( ( 0 === $h2 % 3 ) ? '#f7c23a' : '#f0a500' );
					$html .= sprintf(
						'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="0.5" fill="%s" opacity="%.2f" filter="url(#%s)"/>',
						$x,
						$y,
						$gw,
						$gh,
						esc_attr( $fl ),
						$op,
						esc_attr( $filter_id )
					);
				} else {
					$op = 0.12 + ( $h2 % 12 ) / 120;
					$html .= sprintf(
						'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="0.5" fill="#1a3a5c" opacity="%.2f"/>',
						$x,
						$y,
						$gw,
						$gh,
						$op
					);
				}
			}
		}

		return $html;
	}

	/**
	 * @param int $style 0–6 silhouette variants.
	 */
	private static function building( float $x, float $y_bottom, float $w, float $h, string $fill, int $seed, float $lit_pct, string $fid, int $style ): string {
		$y      = $y_bottom - $h;
		$r      = min( 3.0, $w * 0.08 );
		$gw     = 4.2 + ( self::hash99( $seed, 800 ) % 5 ) * 0.35;
		$gh     = 3.4 + ( self::hash99( $seed, 801 ) % 4 ) * 0.35;
		$html   = '';
		$fill_e = esc_attr( $fill );

		if ( 6 === $style ) {
			$html .= sprintf(
				'<ellipse cx="%.2f" cy="%.2f" rx="%.2f" ry="%.2f" fill="%s"/>',
				$x + $w / 2,
				$y + $h * 0.52,
				$w / 2,
				$h * 0.48,
				$fill_e
			);
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s"/>',
				$x + $w * 0.08,
				$y,
				$w * 0.84,
				$h * 0.55,
				$r,
				$fill_e
			);
		} else {
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s"/>',
				$x,
				$y,
				$w,
				$h,
				$r,
				$fill_e
			);
		}

		if ( 6 !== $style ) {
			$html .= self::windows_grid( $x, $y, $w, $h, $seed, $lit_pct, $fid, $gw, $gh );
		} else {
			$html .= self::windows_grid( $x + $w * 0.12, $y + $h * 0.08, $w * 0.76, $h * 0.42, $seed + 3, $lit_pct * 0.85, $fid, $gw * 0.9, $gh );
		}

		if ( 1 === $style ) {
			$step = $w * 0.22;
			$html .= sprintf(
				'<polygon points="%.2f,%.2f %.2f,%.2f %.2f,%.2f" fill="%s" opacity="0.95"/>',
				$x + $step,
				$y,
				$x + $w - $step,
				$y,
				$x + $w / 2,
				$y - $h * 0.1,
				$fill_e
			);
		} elseif ( 2 === $style ) {
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" opacity="0.9"/>',
				$x + $w * 0.42,
				$y - $h * 0.14,
				$w * 0.16,
				$h * 0.14,
				$fill_e
			);
			$html .= sprintf(
				'<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#5a7aaa" stroke-width="1.2" opacity="0.55"/>',
				$x + $w / 2,
				$y - $h * 0.14,
				$x + $w / 2,
				$y - $h * 0.26
			);
		} elseif ( 3 === $style ) {
			$s1w = $w * 0.72;
			$s1x = $x + ( $w - $s1w ) / 2;
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="2" fill="%s" opacity="0.92"/>',
				$s1x,
				$y - $h * 0.12,
				$s1w,
				$h * 0.12,
				$fill_e
			);
			$s2w = $w * 0.48;
			$s2x = $x + ( $w - $s2w ) / 2;
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="2" fill="%s" opacity="0.9"/>',
				$s2x,
				$y - $h * 0.22,
				$s2w,
				$h * 0.1,
				$fill_e
			);
		} elseif ( 4 === $style ) {
			$pk = $h * 0.07;
			$html .= sprintf(
				'<polygon points="%.2f,%.2f %.2f,%.2f %.2f,%.2f" fill="%s" opacity="0.88"/>',
				$x + $w * 0.25,
				$y,
				$x + $w * 0.5,
				$y - $pk,
				$x + $w * 0.5,
				$y,
				$fill_e
			);
			$html .= sprintf(
				'<polygon points="%.2f,%.2f %.2f,%.2f %.2f,%.2f" fill="%s" opacity="0.88"/>',
				$x + $w * 0.5,
				$y,
				$x + $w * 0.75,
				$y - $pk,
				$x + $w * 0.75,
				$y,
				$fill_e
			);
		} elseif ( 5 === $style ) {
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="6" fill="#f0a500" opacity="0.35"/>',
				$x + 2,
				$y + 18,
				$w - 4
			);
		}

		return $html;
	}

	/**
	 * @param array<int, string> $palette Facade colours.
	 */
	private static function skyline( int $layer_seed, float $y_bottom, float $w, float $min_h, float $max_h, float $lit_pct, string $fid, array $palette ): string {
		$html  = '';
		$cx    = -60.0;
		$i     = 0;
		$fills = array_values( $palette );

		while ( $cx < $w + 90 ) {
			$bw = 22.0 + self::hash99( $layer_seed, $i * 3 ) * 0.65 + ( $i % 5 ) * 2.8;
			$bh = $min_h + ( self::hash99( $layer_seed, $i * 7 ) / 100 ) * ( $max_h - $min_h );
			$fi = $fills[ ( $i + self::hash99( $layer_seed, $i ) ) % count( $fills ) ];
			$st = self::hash99( $layer_seed, $i * 11 ) % 7;
			$html .= self::building( $cx, $y_bottom, $bw, $bh, $fi, $layer_seed + $i * 17, $lit_pct, $fid, $st );
			$gap = -16 + self::hash99( $layer_seed, $i * 5 ) * 0.52;
			$cx += $bw + $gap;
			++$i;
		}

		return $html;
	}

	/**
	 * Distant birds (SMIL drift).
	 *
	 * @param string $prefix ID prefix (unique per hero).
	 */
	private static function birds_layer( float $w, float $h, string $prefix ): string {
		$out = '<g fill="none" stroke="rgba(30,55,90,0.35)" stroke-width="1.2" stroke-linecap="round" opacity="0.85">';
		$birds = array(
			array( $w * 0.18, 68, 38 ),
			array( $w * 0.42, 52, 52 ),
			array( $w * 0.72, 78, 44 ),
			array( $w * 0.88, 58, 60 ),
		);
		$bi    = 0;
		foreach ( $birds as $b ) {
			$bx = $b[0];
			$by = $b[1];
			$dur = $b[2];
			$id = $prefix . '-bird-' . $bi;
			$out .= sprintf(
				'<g id="%s"><path d="M%.1f %.1f l 5 -3 m 0 0 l 5 3"/><animateTransform attributeName="transform" type="translate" values="0 0; 28 0; 0 0" dur="%ds" repeatCount="indefinite"/></g>',
				esc_attr( $id ),
				$bx,
				$by,
				$dur
			);
			++$bi;
		}
		$out .= '</g>';

		return $out;
	}

	/**
	 * @param string $prefix Unique SVG id prefix.
	 */
	private static function layer_back( float $w, string $prefix ): string {
		$h = self::VIEW_H;
		$p = esc_attr( $prefix );

		return sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--back" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. '<linearGradient id="' . $p . '-sky" x1="0" y1="0" x2="0.35" y2="1">'
		. '<stop offset="0%" stop-color="#1a3d6e"/><stop offset="28%" stop-color="#4a8fd9"/><stop offset="55%" stop-color="#a8c8ef"/><stop offset="82%" stop-color="#e8c9a8"/><stop offset="100%" stop-color="#f2dfb8"/>'
		. '</linearGradient>'
		. '<radialGradient id="' . $p . '-sun" cx="0.5" cy="0.42" r="0.55">'
		. '<stop offset="0%" stop-color="rgba(255,252,235,0.98)"/><stop offset="45%" stop-color="rgba(255,210,130,0.45)"/><stop offset="100%" stop-color="transparent"/>'
		. '</radialGradient>'
		. '<linearGradient id="' . $p . '-haze" x1="0" y1="0" x2="0" y2="1">'
		. '<stop offset="0%" stop-color="transparent"/><stop offset="100%" stop-color="rgba(15,29,53,0.22)"/>'
		. '</linearGradient>'
		. '<filter id="' . $p . '-cloudblur" x="-40%" y="-40%" width="180%" height="180%"><feGaussianBlur stdDeviation="18"/></filter>'
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="url(#%s-sky)"/>', $w, $h, $p )
		. sprintf( '<circle cx="%.2f" cy="88" r="62" fill="url(#%s-sun)" opacity="0.82"/>', $w * 0.11, $p )
		. sprintf( '<circle cx="%.2f" cy="88" r="88" fill="none" stroke="rgba(255,220,160,0.12)" stroke-width="2" opacity="0.9"/>', $w * 0.11 )
		// Hills.
		. sprintf(
			'<path d="M0 %.1f Q %.1f %.1f %.1f %.1f T %.1f %.1f L %.1f %d L 0 %d Z" fill="rgba(45,85,130,0.28)"/>',
			(float) $h - 120,
			$w * 0.2,
			(float) $h - 200,
			$w * 0.45,
			(float) $h - 150,
			$w * 0.78,
			(float) $h - 175,
			$w,
			$h,
			$h
		)
		// Cloud banks (SMIL drift).
		. '<g opacity="0.62" filter="url(#' . $p . '-cloudblur)">'
		. sprintf(
			'<g><ellipse cx="%.2f" cy="112" rx="260" ry="46" fill="rgba(255,255,255,0.58)"/><animateTransform attributeName="transform" type="translate" values="0 0; 36 0; 0 0" dur="85s" repeatCount="indefinite"/></g>',
			$w * 0.32
		)
		. sprintf(
			'<g><ellipse cx="%.2f" cy="92" rx="210" ry="38" fill="rgba(255,255,255,0.48)"/><animateTransform attributeName="transform" type="translate" values="0 0; -28 0; 0 0" dur="110s" repeatCount="indefinite"/></g>',
			$w * 0.64
		)
		. sprintf(
			'<g><ellipse cx="%.2f" cy="128" rx="230" ry="42" fill="rgba(255,255,255,0.38)"/><animateTransform attributeName="transform" type="translate" values="0 0; 22 0; 0 0" dur="95s" repeatCount="indefinite"/></g>',
			$w * 0.9
		)
		. '</g>'
		. self::birds_layer( $w, (float) $h, $prefix )
		. sprintf( '<rect width="%.2f" height="%d" fill="url(#%s-haze)"/>', $w, $h, $p )
		. '</svg>';
	}

	/**
	 * @param string $prefix Unique SVG id prefix.
	 */
	private static function layer_mid( float $w, string $prefix ): string {
		$h   = self::VIEW_H;
		$fid = $prefix . '-glow-mid';
		$pal = array( '#3d5a80', '#2d4a6e', '#3a5f7a', '#2f5070', '#4a6788', '#355a72' );
		$p   = esc_attr( $prefix );

		return sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--mid" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. sprintf( '<filter id="%s" x="-80%%" y="-80%%" width="260%%" height="260%%"><feGaussianBlur stdDeviation="1.1"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>', esc_attr( $fid ) )
		. '<linearGradient id="' . $p . '-atm" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="rgba(255,255,255,0.06)"/><stop offset="100%" stop-color="transparent"/></linearGradient>'
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="transparent"/>', $w, $h )
		. self::skyline( 701, (float) $h - 2, $w, 125, 275, 0.29, $fid, $pal )
		. sprintf( '<rect width="%.2f" height="%d" fill="url(#%s-atm)"/>', $w, $h, $p )
		. '</svg>';
	}

	/**
	 * @param string $prefix Unique SVG id prefix.
	 */
	private static function layer_front( float $w, string $prefix ): string {
		$h    = self::VIEW_H;
		$fid  = $prefix . '-glow-front';
		$pal  = array( '#1e3355', '#243d5c', '#1a2f4d', '#2a4a68', '#162842', '#203a52' );
		$base = (float) $h - 2;
		$p    = esc_attr( $prefix );

		$html = sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--front" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. sprintf( '<filter id="%s" x="-100%%" y="-100%%" width="300%%" height="300%%"><feGaussianBlur stdDeviation="1.6"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>', esc_attr( $fid ) )
		. '<linearGradient id="' . $p . '-street" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="rgba(0,0,0,0)"/><stop offset="100%" stop-color="rgba(8,14,26,0.55)"/></linearGradient>'
		. '<linearGradient id="' . $p . '-glass" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="rgba(255,255,255,0.14)"/><stop offset="100%" stop-color="rgba(0,212,170,0.08)"/></linearGradient>'
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="transparent"/>', $w, $h )
		. self::skyline( 503, $base, $w, 190, 405, 0.44, $fid, $pal );

		for ( $t = 0; $t < 12; $t++ ) {
			$tx = 20 + ( $t * 143 + self::hash99( 88, $t * 3 ) ) % (int) ( $w - 90 );
			$gh = 0.52 + ( self::hash99( 77, $t ) % 22 ) / 100;
			$html .= sprintf(
				'<ellipse cx="%.2f" cy="%.2f" rx="13" ry="19" fill="#2d6b52" opacity="%.2f"/><rect x="%.2f" y="%.2f" width="5" height="26" fill="#1f4a38"/>',
				$tx + 9,
				$base - 16,
				$gh,
				$tx + 6.5,
				$base - 8
			);
		}

		$html .= sprintf(
			'<rect x="0" y="%.2f" width="%.2f" height="11" fill="rgba(35,50,72,0.5)"/>',
			$base - 9,
			$w
		);
		$html .= sprintf(
			'<line x1="0" y1="%.2f" x2="%.2f" y2="%.2f" stroke="rgba(240,165,0,0.28)" stroke-width="2" stroke-dasharray="10 14"/>',
			$base - 4,
			$w,
			$base - 4
		);
		// Crosswalk.
		for ( $z = 0; $z < 7; $z++ ) {
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="5" height="14" rx="1" fill="rgba(255,255,255,0.2)"/>',
				$w * 0.46 + $z * 12,
				$base - 22
			);
		}
		// Landmark glass tower hint.
		$html .= sprintf(
			'<rect x="%.2f" y="%.2f" width="42" height="%.2f" rx="3" fill="url(#%s-glass)" stroke="rgba(255,255,255,0.15)" stroke-width="1"/>',
			$w * 0.62,
			$base - 310,
			302.0,
			$p
		);
		for ( $fl = 0; $fl < 8; $fl++ ) {
			$html .= sprintf(
				'<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="rgba(255,255,255,0.12)" stroke-width="1"/>',
				$w * 0.62 + 6,
				$base - 295 + $fl * 36,
				$w * 0.62 + 36,
				$base - 295 + $fl * 36
			);
		}
		$html .= sprintf(
			'<rect x="0" y="%.2f" width="%.2f" height="48" fill="url(#%s-street)"/>',
			$base - 44,
			$w,
			$p
		);

		for ( $b = 0; $b < 7; $b++ ) {
			$bx = 70 + $b * ( $w / 6.5 ) + self::hash99( 44, $b * 2 );
			$html .= sprintf(
				'<circle cx="%.2f" cy="%.2f" r="2.6" fill="#00d4aa" opacity="0.45"><animate attributeName="opacity" values="0.2;0.9;0.2" dur="%ds" repeatCount="indefinite"/></circle>',
				$bx,
				$base - 228 - ( $b % 3 ) * 30,
				5 + ( $b % 3 )
			);
		}

		return $html . '</svg>';
	}

	public static function get_illustration_markup(): string {
		$w       = 2000.0;
		$v       = esc_attr( self::VERSION );
		$prefix = 'lpnwh5' . bin2hex( random_bytes( 4 ) );

		return '<div class="lpnw-hero__parallax" data-lpnw-hero-svg="' . $v . '" aria-hidden="true">'
			. self::layer_back( $w, $prefix )
			. self::layer_mid( $w, $prefix )
			. self::layer_front( $w, $prefix )
			. '</div>';
	}
}
