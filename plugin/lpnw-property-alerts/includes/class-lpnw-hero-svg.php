<?php
/**
 * Front-page hero: three full-width SVG layers (sky or buildings) with scroll parallax.
 *
 * Replaces the single inline hero SVG in post content via `the_content` filter.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Procedural layered cityscape for the marketing hero.
 */
class LPNW_Hero_Svg {

	public const VERSION = '3';

	private const VIEW_H = 500;

	/**
	 * Register content filter on front page.
	 */
	public static function init(): void {
		add_filter( 'the_content', array( __CLASS__, 'filter_replace_illustration' ), 8 );
	}

	/**
	 * Swap inline hero SVG for layered parallax markup.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_replace_illustration( string $content ): string {
		if ( ! is_front_page() || ! in_the_loop() || ! is_main_query() ) {
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
			'/<svg\s+class="lpnw-hero__illustration"[\s\S]*?<\/svg>/i',
			$new,
			$content,
			1
		);

		if ( ! is_string( $out ) ) {
			return $content;
		}

		// Full-width parallax: drop the narrow scene wrapper when it only wraps the illustration.
		$unwrapped = preg_replace(
			'/<div\s+class="lpnw-hero__scene"(?:\s+aria-hidden="true")?>\s*(<div\s+class="lpnw-hero__parallax"[\s\S]*?<\/div>)\s*<\/div>/i',
			'$1',
			$out,
			1
		);

		return is_string( $unwrapped ) ? $unwrapped : $out;
	}

	/**
	 * Deterministic 0..99 from seed + coordinates.
	 *
	 * @param int $seed Layer seed.
	 * @param int $i    Index.
	 * @return int
	 */
	private static function hash99( int $seed, int $i ): int {
		return (int) ( ( $seed * 7919 + $i * 104729 ) % 100 );
	}

	/**
	 * Whether a window is "lit" from hash.
	 *
	 * @param int   $seed    Seed.
	 * @param int   $r       Row.
	 * @param int   $c       Col.
	 * @param float $lit_pct 0..1.
	 * @return bool
	 */
	private static function window_lit( int $seed, int $r, int $c, float $lit_pct ): bool {
		$h = self::hash99( $seed, $r * 997 + $c * 37 );

		return $h < ( $lit_pct * 100 );
	}

	/**
	 * Window grid inside a building rect.
	 *
	 * @param float  $bx       Left.
	 * @param float  $by       Top.
	 * @param float  $bw       Width.
	 * @param float  $bh       Height.
	 * @param int    $seed     Variation seed.
	 * @param float  $lit_pct  Fraction lit.
	 * @param string $filter_id Glow filter id (defs in same svg).
	 * @return string
	 */
	private static function windows_grid( float $bx, float $by, float $bw, float $bh, int $seed, float $lit_pct, string $filter_id ): string {
		$gw      = 5.0;
		$gh      = 4.0;
		$gap     = 3.0;
		$pad     = 5.0;
		$header  = 14.0;
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
					$op = 0.42 + ( $h2 % 35 ) / 100;
					$fl = ( $h2 % 3 === 0 ) ? '#f7c23a' : '#f0a500';
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
					$op = 0.05 + ( $h2 % 8 ) / 200;
					$html .= sprintf(
						'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="0.5" fill="#cfe8ff" opacity="%.2f"/>',
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
	 * One building block.
	 *
	 * @param float  $x         Left.
	 * @param float  $y_bottom  Bottom y (baseline).
	 * @param float  $w         Width.
	 * @param float  $h         Height.
	 * @param string $fill      Facade colour.
	 * @param int    $seed      Seed.
	 * @param float  $lit_pct   Window lit ratio.
	 * @param string $fid       Filter id.
	 * @param int    $style     0 box, 1 stepped roof, 2 flat + antenna.
	 * @return string
	 */
	private static function building( float $x, float $y_bottom, float $w, float $h, string $fill, int $seed, float $lit_pct, string $fid, int $style ): string {
		$y = $y_bottom - $h;
		$r = min( 3.0, $w * 0.08 );
		$html = sprintf(
			'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s"/>',
			$x,
			$y,
			$w,
			$h,
			$r,
			esc_attr( $fill )
		);
		$html .= self::windows_grid( $x, $y, $w, $h, $seed, $lit_pct, $fid );

		if ( 1 === $style ) {
			$step = $w * 0.22;
			$html .= sprintf(
				'<polygon points="%.2f,%.2f %.2f,%.2f %.2f,%.2f" fill="%s" opacity="0.95"/>',
				$x + $step,
				$y,
				$x + $w - $step,
				$y,
				$x + $w / 2,
				$y - $h * 0.08,
				esc_attr( $fill )
			);
		} elseif ( 2 === $style ) {
			$html .= sprintf(
				'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" opacity="0.9"/>',
				$x + $w * 0.42,
				$y - $h * 0.12,
				$w * 0.16,
				$h * 0.12,
				esc_attr( $fill )
			);
			$html .= sprintf(
				'<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#5a7aaa" stroke-width="1.2" opacity="0.6"/>',
				$x + $w / 2,
				$y - $h * 0.12,
				$x + $w / 2,
				$y - $h * 0.22
			);
		}

		return $html;
	}

	/**
	 * Fill skyline with varied buildings across width W.
	 *
	 * @param int    $layer_seed Seed for layer.
	 * @param float  $y_bottom   Horizon baseline.
	 * @param float  $w          Viewport width.
	 * @param float  $min_h      Min building height.
	 * @param float  $max_h      Max building height.
	 * @param float  $lit_pct    Window lit fraction.
	 * @param string $fid        Glow filter id.
	 * @param array<int, string> $palette Facade colours.
	 * @return string
	 */
	private static function skyline( int $layer_seed, float $y_bottom, float $w, float $min_h, float $max_h, float $lit_pct, string $fid, array $palette ): string {
		$html  = '';
		$cx    = -40.0;
		$i     = 0;
		$fills = array_values( $palette );

		while ( $cx < $w + 60 ) {
			$bw = 28.0 + self::hash99( $layer_seed, $i * 3 ) * 0.45;
			$bh = $min_h + ( self::hash99( $layer_seed, $i * 7 ) / 100 ) * ( $max_h - $min_h );
			$fi = $fills[ $i % count( $fills ) ];
			$st = self::hash99( $layer_seed, $i * 11 ) % 3;
			$html .= self::building( $cx, $y_bottom, $bw, $bh, $fi, $layer_seed + $i * 17, $lit_pct, $fid, $st );
			$gap = -6 + self::hash99( $layer_seed, $i * 5 ) * 0.35;
			$cx += $bw + $gap;
			++$i;
		}

		return $html;
	}

	/**
	 * Back layer: sky, moon, soft clouds.
	 *
	 * @param float $w Width.
	 * @return string
	 */
	private static function layer_back( float $w ): string {
		$h      = self::VIEW_H;
		$stars  = '';
		for ( $s = 0; $s < 48; $s++ ) {
			$px = ( self::hash99( 31, $s * 13 ) / 100 ) * $w;
			$py = 20 + ( self::hash99( 31, $s * 19 ) % 120 );
			$pr = 0.5 + ( self::hash99( 31, $s * 7 ) % 8 ) / 10;
			$stars .= sprintf( '<circle cx="%.2f" cy="%d" r="%.2f"/>', $px, $py, $pr );
		}

		return sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--back" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. '<linearGradient id="lpnwh3-sky" x1="0" y1="0" x2="0" y2="1">'
		. '<stop offset="0%" stop-color="#030810"/><stop offset="50%" stop-color="#0a1628"/><stop offset="100%" stop-color="#132a4a"/>'
		. '</linearGradient>'
		. '<radialGradient id="lpnwh3-moon" cx="0.5" cy="0.3" r="0.45">'
		. '<stop offset="0%" stop-color="rgba(255,250,235,0.9)"/><stop offset="70%" stop-color="rgba(180,200,255,0.15)"/><stop offset="100%" stop-color="transparent"/>'
		. '</radialGradient>'
		. '<filter id="lpnwh3-cloudblur" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="18"/></filter>'
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="url(#lpnwh3-sky)"/>', $w, $h )
		. sprintf( '<circle cx="%.2f" cy="88" r="46" fill="url(#lpnwh3-moon)" opacity="0.85"/>', $w * 0.78 )
		. sprintf( '<ellipse cx="%.2f" cy="95" rx="52" ry="48" fill="rgba(5,10,20,0.75)"/>', $w * 0.78 )
		. '<g opacity="0.4" filter="url(#lpnwh3-cloudblur)">'
		. sprintf( '<ellipse cx="%.2f" cy="120" rx="220" ry="38" fill="rgba(120,160,220,0.35)"/>', $w * 0.25 )
		. sprintf( '<ellipse cx="%.2f" cy="95" rx="180" ry="32" fill="rgba(255,255,255,0.2)"/>', $w * 0.55 )
		. sprintf( '<ellipse cx="%.2f" cy="140" rx="200" ry="36" fill="rgba(80,120,180,0.25)"/>', $w * 0.82 )
		. '</g>'
		. '<g fill="#fff" opacity="0.35">'
		. $stars
		. '</g></svg>';
	}

	/**
	 * Mid layer: main skyline (cooler, smaller windows).
	 *
	 * @param float $w Width.
	 * @return string
	 */
	private static function layer_mid( float $w ): string {
		$h   = self::VIEW_H;
		$fid = 'lpnwh3-glow-mid';
		$pal = array( '#1a3052', '#1e3a5f', '#243d62', '#162b48', '#203a58' );
		$html = sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--mid" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. sprintf( '<filter id="%s" x="-80%%" y="-80%%" width="260%%" height="260%%"><feGaussianBlur stdDeviation="1.4"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>', esc_attr( $fid ) )
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="transparent"/>', $w, $h )
		. self::skyline( 701, (float) $h - 2, $w, 140, 290, 0.32, $fid, $pal )
		. '</svg>';

		return $html;
	}

	/**
	 * Front layer: taller silhouettes, more lit windows, street detail.
	 *
	 * @param float $w Width.
	 * @return string
	 */
	private static function layer_front( float $w ): string {
		$h    = self::VIEW_H;
		$fid  = 'lpnwh3-glow-front';
		$pal  = array( '#0f1d35', '#152a45', '#1a3354', '#12243c', '#1e3f66' );
		$base = (float) $h - 2;
		$html = sprintf(
			'<svg class="lpnw-hero__layer lpnw-hero__layer--front" viewBox="0 0 %.2f %d" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">',
			$w,
			$h
		)
		. '<defs>'
		. sprintf( '<filter id="%s" x="-100%%" y="-100%%" width="300%%" height="300%%"><feGaussianBlur stdDeviation="2"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>', esc_attr( $fid ) )
		. '<linearGradient id="lpnwh3-street" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="rgba(0,212,170,0)"/><stop offset="100%" stop-color="rgba(0,212,170,0.14)"/></linearGradient>'
		. '</defs>'
		. sprintf( '<rect width="%.2f" height="%d" fill="transparent"/>', $w, $h )
		. self::skyline( 503, $base, $w, 200, 380, 0.48, $fid, $pal );

		// Trees (simple).
		for ( $t = 0; $t < 9; $t++ ) {
			$tx = 40 + ( $t * 137 + self::hash99( 88, $t * 3 ) ) % (int) ( $w - 120 );
			$html .= sprintf(
				'<ellipse cx="%.2f" cy="%.2f" rx="16" ry="22" fill="#0a5c4a" opacity="0.75"/><rect x="%.2f" y="%.2f" width="5" height="28" fill="#143d32"/>',
				$tx + 10,
				$base - 18,
				$tx + 7.5,
				$base - 10
			);
		}

		// Benches / low walls.
		$html .= sprintf(
			'<rect x="0" y="%.2f" width="%.2f" height="8" fill="rgba(15,29,53,0.55)"/><line x1="0" y1="%.2f" x2="%.2f" y2="%.2f" stroke="rgba(240,165,0,0.15)" stroke-width="2" stroke-dasharray="10 14"/>',
			$base - 6,
			$w,
			$base - 2,
			$w,
			$base - 2
		);

		// Street glow strip.
		$html .= sprintf(
			'<rect x="0" y="%.2f" width="%.2f" height="36" fill="url(#lpnwh3-street)"/>',
			$base - 36,
			$w
		);

		// Subtle rooftop beacons (alert motif).
		for ( $b = 0; $b < 5; $b++ ) {
			$bx = 120 + $b * ( $w / 5.5 ) + self::hash99( 44, $b );
			$html .= sprintf(
				'<circle cx="%.2f" cy="%.2f" r="3" fill="#00d4aa" opacity="0.55"><animate attributeName="opacity" values="0.35;0.9;0.35" dur="%ds" repeatCount="indefinite"/></circle>',
				$bx,
				$base - 260 - ( $b * 12 ),
				3 + $b
			);
		}

		return $html . '</svg>';
	}

	/**
	 * Wrapper + three layers. Width tracks viewport via CSS (100vw).
	 *
	 * @return string
	 */
	public static function get_illustration_markup(): string {
		$w = 1600.0;
		$v = esc_attr( self::VERSION );

		return '<div class="lpnw-hero__parallax" data-lpnw-hero-svg="' . $v . '" aria-hidden="true">'
			. self::layer_back( $w )
			. self::layer_mid( $w )
			. self::layer_front( $w )
			. '</div>';
	}
}
