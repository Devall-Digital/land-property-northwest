<?php
/**
 * Front-page hero: three full-width SVG layers with scroll parallax.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Procedural layered cityscape (afternoon palette, varied silhouettes).
 */
class LPNW_Hero_Svg {

	public const VERSION = '4';

	private const VIEW_H = 500;

	public static function init(): void {
		// `the_content` runs with `is_main_query()` false on many themes; scope the filter to the main loop only.
		add_action( 'loop_start', array( __CLASS__, 'maybe_add_content_filter' ) );
		add_action( 'loop_end', array( __CLASS__, 'maybe_remove_content_filter' ) );
	}

	/**
	 * @param \WP_Query $query Current query.
	 */
	public static function maybe_add_content_filter( $query ): void {
		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( ! is_front_page() || is_feed() ) {
			return;
		}
		add_filter( 'the_content', array( __CLASS__, 'filter_replace_illustration' ), 8 );
	}

	/**
	 * @param \WP_Query $query Current query.
	 */
	public static function maybe_remove_content_filter( $query ): void {
		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		remove_filter( 'the_content', array( __CLASS__, 'filter_replace_illustration' ), 8 );
	}

	public static function filter_replace_illustration( string $content ): string {
		if ( ! is_front_page() || is_feed() ) {
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

		if ( ! is_string( $out ) ) {
			return $content;
		}

		$unwrapped = preg_replace(
			'/<div\s+class="lpnw-hero__scene"(?:\s+aria-hidden="true")?>\s*(<div\s+class="lpnw-hero__parallax"[\s\S]*?<\/div>)\s*<\/div>/i',
			'$1',
			$out,
			1
		);

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
	 * @param float  $gw Window width (varies per building).
	 * @param float  $gh Window height.
	 */
	private static function windows_grid( float $bx, float $by, float $bw, float $bh, int $seed, float $lit_pct, string $filter_id, float $gw, float $gh ): string {
		$gap     = 3.0 + ( self::hash99( $seed, 900 ) % 3 ) * 0.4;
		$pad     = 5.0;
		$header  = 14.0 + ( self::hash99( $seed, 901 ) % 8 );
		$ox      = $bx + $pad;
		$oy      = $by + $header;
		$inner_w = $bw - 2 * $pad;
		$inner_h = $bh - $header - $pad;
		$cols    = max( 1, (int) floor( ( $inner_w + $gap ) / ( $gw + $gap ) ) );
		$rows    = max( 1, (int) floor( ( $inner_h + $gap ) / ( $gh + $gap ) ) );
		$html    = '';

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
					$fl = ( $h2 % 4 === 0 ) ? '#fff8e6' : ( ( $h2 % 3 === 0 ) ? '#f7c23a' : '#f0a500' );
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
		$y     = $y_bottom - $h;
		$r     = min( 3.0, $w * 0.08 );
		$gw    = 4.2 + ( self::hash99( $seed, 800 ) % 5 ) * 0.35;
		$gh    = 3.4 + ( self::hash99( $seed, 801 ) % 4 ) * 0.35;
		$html  = '';
		$fill_e = esc_attr( $fill );

		if ( 6 === $style ) {
			// Rounded tower (ellipse body + flat top cap).
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
			// Stepped setbacks (two smaller blocks on top).
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
			// Twin gable hint.
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
			// Ornate cornice band.
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
		$cx    = -55.0;
		$i     = 0;
		$fills = array_values( $palette );

		while ( $cx < $w + 80 ) {
			$bw = 24.0 + self::hash99( $layer_seed, $i * 3 ) * 0.62 + ( $i % 4 ) * 3;
			$bh = $min_h + ( self::hash99( $layer_seed, $i * 7 ) / 100 ) * ( $max_h - $min_h );
			$fi = $fills[ ( $i + self::hash99( $layer_seed, $i ) ) % count( $fills ) ];
			$st = self::hash99( $layer_seed, $i * 11 ) % 7;
			$html .= self::building( $cx, $y_bottom, $bw, $bh, $fi, $layer_seed + $i * 17, $lit_pct, $fid, $st );
			$gap = -14 + self::hash99( $layer_seed, $i * 5 ) * 0.55;
			$cx += $bw + $gap;
			++$i;
		}

		return $html;
	}

	private static function layer_back( float $w ): string {
		$h = self::VIEW_H;

		return sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--back" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. '<linearGradient id="lpnwh4-sky" x1="0" y1="0" x2="0.3" y2="1">'
		. '<stop offset="0%" stop-color="#7eb8ff"/><stop offset="42%" stop-color="#b8d4f5"/><stop offset="78%" stop-color="#e8c9a8"/><stop offset="100%" stop-color="#f0d9a8"/>'
		. '</linearGradient>'
		. '<radialGradient id="lpnwh4-sun" cx="0.5" cy="0.45" r="0.5">'
		. '<stop offset="0%" stop-color="rgba(255,248,220,0.95)"/><stop offset="55%" stop-color="rgba(255,210,120,0.35)"/><stop offset="100%" stop-color="transparent"/>'
		. '</radialGradient>'
		. '<filter id="lpnwh4-cloudblur" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="16"/></filter>'
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="url(#lpnwh4-sky)"/>', $w, $h )
		. sprintf( '<circle cx="%.2f" cy="92" r="56" fill="url(#lpnwh4-sun)" opacity="0.75"/>', $w * 0.12 )
		. '<g opacity="0.55" filter="url(#lpnwh4-cloudblur)">'
		. sprintf( '<ellipse cx="%.2f" cy="108" rx="240" ry="42" fill="rgba(255,255,255,0.55)"/>', $w * 0.35 )
		. sprintf( '<ellipse cx="%.2f" cy="88" rx="200" ry="36" fill="rgba(255,255,255,0.45)"/>', $w * 0.62 )
		. sprintf( '<ellipse cx="%.2f" cy="125" rx="220" ry="40" fill="rgba(255,255,255,0.4)"/>', $w * 0.88 )
		. '</g>'
		. '<g stroke="rgba(30,60,100,0.2)" stroke-width="1.2" fill="none" opacity="0.35">'
		. sprintf( '<path d="M%.2f 42 Q %.2f 38 %.2f 44"/>', $w * 0.72, $w * 0.76, $w * 0.8 )
		. sprintf( '<path d="M%.2f 48 Q %.2f 44 %.2f 50"/>', $w * 0.78, $w * 0.82, $w * 0.86 )
		. '</g>'
		. '</svg>';
	}

	private static function layer_mid( float $w ): string {
		$h   = self::VIEW_H;
		$fid = 'lpnwh4-glow-mid';
		$pal = array( '#3d5a80', '#2d4a6e', '#3a5f7a', '#2f5070', '#4a6788', '#355a72' );

		return sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--mid" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. sprintf( '<filter id="%s" x="-80%%" y="-80%%" width="260%%" height="260%%"><feGaussianBlur stdDeviation="1.2"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>', esc_attr( $fid ) )
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="transparent"/>', $w, $h )
		. self::skyline( 701, (float) $h - 2, $w, 120, 270, 0.28, $fid, $pal )
		. '</svg>';
	}

	private static function layer_front( float $w ): string {
		$h    = self::VIEW_H;
		$fid  = 'lpnwh4-glow-front';
		$pal  = array( '#1e3355', '#243d5c', '#1a2f4d', '#2a4a68', '#162842', '#203a52' );
		$base = (float) $h - 2;

		$html = sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--front" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. sprintf( '<filter id="%s" x="-100%%" y="-100%%" width="300%%" height="300%%"><feGaussianBlur stdDeviation="1.8"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>', esc_attr( $fid ) )
		. '<linearGradient id="lpnwh4-street" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="rgba(0,0,0,0)"/><stop offset="100%" stop-color="rgba(15,29,53,0.35)"/></linearGradient>'
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="transparent"/>', $w, $h )
		. self::skyline( 503, $base, $w, 185, 395, 0.42, $fid, $pal );

		for ( $t = 0; $t < 11; $t++ ) {
			$tx = 25 + ( $t * 149 + self::hash99( 88, $t * 3 ) ) % (int) ( $w - 100 );
			$gh = 0.55 + ( self::hash99( 77, $t ) % 20 ) / 100;
			$html .= sprintf(
				'<ellipse cx="%.2f" cy="%.2f" rx="14" ry="20" fill="#2d6b52" opacity="%.2f"/><rect x="%.2f" y="%.2f" width="5" height="26" fill="#1f4a38"/>',
				$tx + 9,
				$base - 16,
				$gh,
				$tx + 6.5,
				$base - 8
			);
		}

		$html .= sprintf(
			'<rect x="0" y="%.2f" width="%.2f" height="10" fill="rgba(40,55,75,0.45)"/>',
			$base - 8,
			$w
		);
		$html .= sprintf(
			'<line x1="0" y1="%.2f" x2="%.2f" y2="%.2f" stroke="rgba(240,165,0,0.22)" stroke-width="2" stroke-dasharray="12 16"/>',
			$base - 3,
			$w,
			$base - 3
		);
		$html .= sprintf(
			'<rect x="0" y="%.2f" width="%.2f" height="40" fill="url(#lpnwh4-street)"/>',
			$base - 40,
			$w
		);

		for ( $b = 0; $b < 6; $b++ ) {
			$bx = 80 + $b * ( $w / 6.2 ) + self::hash99( 44, $b * 2 );
			$html .= sprintf(
				'<circle cx="%.2f" cy="%.2f" r="2.8" fill="#00d4aa" opacity="0.5"><animate attributeName="opacity" values="0.25;0.85;0.25" dur="%ds" repeatCount="indefinite"/></circle>',
				$bx,
				$base - 220 - ( $b % 3 ) * 28,
				4 + ( $b % 3 )
			);
		}

		return $html . '</svg>';
	}

	public static function get_illustration_markup(): string {
		$w = 1800.0;
		$v = esc_attr( self::VERSION );

		return '<div class="lpnw-hero__parallax" data-lpnw-hero-svg="' . $v . '" aria-hidden="true">'
			. self::layer_back( $w )
			. self::layer_mid( $w )
			. self::layer_front( $w )
			. '</div>';
	}
}
