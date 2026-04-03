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

	public const VERSION = '6';

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

		$new = self::get_illustration_markup();
		if ( '' === $new ) {
			return $content;
		}

		$has_scene = str_contains( $content, 'lpnw-hero__scene' );
		$has_svg   = str_contains( $content, 'lpnw-hero__illustration' );

		if ( $has_svg ) {
			$out = preg_replace(
				'/<svg\b[^>]*\bclass="[^"]*\blpnw-hero__illustration\b[^"]*"[^>]*>[\s\S]*?<\/svg>/i',
				$new,
				$content,
				1
			);
			if ( is_string( $out ) && $out !== $content ) {
				$unwrapped = preg_replace(
					'/<div\s+class="lpnw-hero__scene"(?:\s+aria-hidden="true")?>\s*(<div\s+class="lpnw-hero__parallax"[\s\S]*?<\/div>)\s*<\/div>/i',
					'$1',
					$out,
					1
				);
				self::$replaced_hero = true;
				return is_string( $unwrapped ) ? $unwrapped : $out;
			}
		}

		if ( $has_scene ) {
			$out = preg_replace(
				'/<div\s+class="lpnw-hero__scene"[^>]*>[\s\S]*?<\/div>/i',
				$new,
				$content,
				1
			);
			if ( is_string( $out ) && $out !== $content ) {
				self::$replaced_hero = true;
				return $out;
			}
		}

		if ( str_contains( $content, 'lpnw-hero__content' ) ) {
			$out = preg_replace(
				'/(<div\s+class="lpnw-hero__content")/i',
				$new . '$1',
				$content,
				1
			);
			if ( is_string( $out ) && $out !== $content ) {
				self::$replaced_hero = true;
				return $out;
			}
		}

		return $content;
	}

	/**
	 * Stable per-request ID prefix for SVG defs.
	 */
	private static function id_prefix(): string {
		static $p = null;
		if ( null === $p ) {
			$p = 'lpnwh6' . bin2hex( random_bytes( 4 ) );
		}
		return $p;
	}

	// --- V6: Hand-crafted 3-layer SVG cityscape ---

	private static function layer_back_v6( string $p ): string {
		return '<svg class="lpnw-hero__layer lpnw-hero__layer--back" viewBox="0 0 1600 500" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg">'
		. '<defs>'
		. '<linearGradient id="' . $p . '-sky" x1="0" y1="0" x2="0.2" y2="1">'
		. '<stop offset="0%" stop-color="#0a0e1a"/><stop offset="30%" stop-color="#0f1d35"/><stop offset="65%" stop-color="#1a3355"/><stop offset="85%" stop-color="#2d3848"/><stop offset="100%" stop-color="#3d2a1e"/>'
		. '</linearGradient>'
		. '<radialGradient id="' . $p . '-moon" cx="0.85" cy="0.18" r="0.12">'
		. '<stop offset="0%" stop-color="rgba(220,230,255,0.95)"/><stop offset="50%" stop-color="rgba(180,200,240,0.3)"/><stop offset="100%" stop-color="transparent"/>'
		. '</radialGradient>'
		. '<filter id="' . $p . '-glow"><feGaussianBlur stdDeviation="8" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>'
		. '<filter id="' . $p . '-cblur"><feGaussianBlur stdDeviation="16"/></filter>'
		. '<linearGradient id="' . $p . '-haze" x1="0" y1="0" x2="0" y2="1">'
		. '<stop offset="0%" stop-color="transparent"/><stop offset="100%" stop-color="rgba(15,29,53,0.35)"/>'
		. '</linearGradient>'
		. '</defs>'
		. '<rect width="1600" height="500" fill="url(#' . $p . '-sky)"/>'
		. '<circle cx="1360" cy="90" r="32" fill="url(#' . $p . '-moon)" filter="url(#' . $p . '-glow)"/>'
		. '<circle cx="1360" cy="90" r="56" fill="none" stroke="rgba(200,215,245,0.06)" stroke-width="1.5"/>'
		// Stars.
		. '<circle cx="120" cy="42" r="1.2" fill="#fff" opacity="0.7"/>'
		. '<circle cx="340" cy="28" r="0.8" fill="#fff" opacity="0.5"/>'
		. '<circle cx="520" cy="55" r="1" fill="#fff" opacity="0.6"/>'
		. '<circle cx="680" cy="18" r="1.3" fill="#fff" opacity="0.4"/>'
		. '<circle cx="860" cy="65" r="0.9" fill="#fff" opacity="0.55"/>'
		. '<circle cx="1020" cy="35" r="1.1" fill="#fff" opacity="0.65"/>'
		. '<circle cx="1180" cy="48" r="0.7" fill="#fff" opacity="0.45"/>'
		. '<circle cx="1480" cy="30" r="1" fill="#fff" opacity="0.6"/>'
		. '<circle cx="200" cy="80" r="0.8" fill="#fff" opacity="0.35"/>'
		. '<circle cx="450" cy="72" r="1.2" fill="#fff" opacity="0.5"/>'
		. '<circle cx="750" cy="40" r="0.9" fill="#fff" opacity="0.55"/>'
		. '<circle cx="1100" cy="22" r="1.1" fill="#fff" opacity="0.4"/>'
		. '<circle cx="1300" cy="58" r="0.7" fill="#fff" opacity="0.5"/>'
		. '<circle cx="1550" cy="70" r="1" fill="#fff" opacity="0.45"/>'
		. '<circle cx="60" cy="110" r="0.6" fill="#fff" opacity="0.3"/>'
		. '<circle cx="950" cy="85" r="1.3" fill="#fff" opacity="0.35"/>'
		// Distant hills.
		. '<path d="M0 420 Q200 340 400 380 Q600 350 800 370 Q1000 330 1200 360 Q1400 340 1600 375 L1600 500 L0 500Z" fill="rgba(25,45,75,0.4)"/>'
		// Clouds with SMIL drift.
		. '<g opacity="0.5" filter="url(#' . $p . '-cblur)">'
		. '<g><ellipse cx="350" cy="120" rx="200" ry="35" fill="rgba(255,255,255,0.06)"/><animateTransform attributeName="transform" type="translate" values="0 0;40 0;0 0" dur="90s" repeatCount="indefinite"/></g>'
		. '<g><ellipse cx="900" cy="95" rx="170" ry="28" fill="rgba(255,255,255,0.04)"/><animateTransform attributeName="transform" type="translate" values="0 0;-30 0;0 0" dur="110s" repeatCount="indefinite"/></g>'
		. '<g><ellipse cx="1400" cy="140" rx="190" ry="32" fill="rgba(255,255,255,0.05)"/><animateTransform attributeName="transform" type="translate" values="0 0;25 0;0 0" dur="100s" repeatCount="indefinite"/></g>'
		. '</g>'
		// Warm horizon glow.
		. '<rect x="0" y="400" width="1600" height="100" fill="url(#' . $p . '-haze)"/>'
		. '</svg>';
	}

	private static function layer_mid_v6( string $p ): string {
		return '<svg class="lpnw-hero__layer lpnw-hero__layer--mid" viewBox="0 0 1600 500" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg">'
		. '<defs>'
		. '<filter id="' . $p . '-wg"><feGaussianBlur stdDeviation="1.2"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>'
		. '</defs>'
		// Manchester-inspired skyline silhouette — hand-composed path.
		. '<path d="'
		// Left section: terraced houses + chimney
		. 'M0 500 L0 380 L30 380 L30 360 L32 355 L34 360 L60 360 L60 370 L90 370 L90 350 L120 350 L120 370 L150 370 L150 340 L155 340 L155 330 L160 330 L160 340 L165 340 L165 370 L200 370'
		// Industrial building with sawtooth roof
		. ' L200 350 L215 330 L230 350 L245 330 L260 350 L275 330 L290 350 L290 370 L320 370'
		// Office block
		. ' L320 310 L340 310 L340 300 L345 295 L350 300 L380 300 L380 310 L400 310 L400 370'
		// Beetham Tower (tall thin)
		. ' L420 370 L420 180 L425 170 L430 165 L435 170 L440 180 L440 370'
		// Gap + cathedral spire
		. ' L480 370 L480 320 L510 320 L510 280 L530 280 L545 220 L560 280 L580 280 L580 320 L610 320 L610 370'
		// Mid section office blocks
		. ' L640 370 L640 290 L680 290 L680 270 L720 270 L720 290 L760 290 L760 370'
		// Curved modern tower
		. ' L800 370 L800 340 Q810 240 830 200 Q850 240 860 340 L860 370'
		// More buildings
		. ' L900 370 L900 310 L940 310 L940 280 L960 280 L960 310 L1000 310 L1000 370'
		// Wide industrial
		. ' L1040 370 L1040 320 L1060 310 L1080 320 L1140 320 L1160 310 L1180 320 L1180 370'
		// Residential cluster
		. ' L1220 370 L1220 340 L1260 340 L1260 350 L1300 350 L1300 330 L1340 330 L1340 350 L1380 350 L1380 370'
		// Right edge buildings
		. ' L1420 370 L1420 300 L1460 300 L1460 320 L1500 320 L1500 290 L1505 280 L1510 290 L1540 290 L1540 320 L1600 320 L1600 500 Z'
		. '" fill="#2a4060"/>'
		// Lit windows (warm amber glow scattered across buildings).
		. '<g filter="url(#' . $p . '-wg)">'
		// Beetham Tower windows
		. '<rect x="427" y="195" width="4" height="3.5" rx="0.5" fill="#f7c23a" opacity="0.7"/>'
		. '<rect x="427" y="215" width="4" height="3.5" rx="0.5" fill="#f0a500" opacity="0.55"/>'
		. '<rect x="427" y="240" width="4" height="3.5" rx="0.5" fill="#fff8e6" opacity="0.6"/>'
		. '<rect x="433" y="260" width="4" height="3.5" rx="0.5" fill="#f7c23a" opacity="0.5"/>'
		. '<rect x="427" y="290" width="4" height="3.5" rx="0.5" fill="#f0a500" opacity="0.65"/>'
		. '<rect x="433" y="320" width="4" height="3.5" rx="0.5" fill="#fff8e6" opacity="0.5"/>'
		// Cathedral area
		. '<rect x="520" y="295" width="4" height="3.5" rx="0.5" fill="#f7c23a" opacity="0.55"/>'
		. '<rect x="540" y="300" width="4" height="3.5" rx="0.5" fill="#f0a500" opacity="0.45"/>'
		// Office blocks
		. '<rect x="660" y="300" width="5" height="4" rx="0.5" fill="#f7c23a" opacity="0.6"/>'
		. '<rect x="670" y="285" width="5" height="4" rx="0.5" fill="#f0a500" opacity="0.5"/>'
		. '<rect x="690" y="295" width="5" height="4" rx="0.5" fill="#fff8e6" opacity="0.55"/>'
		. '<rect x="730" y="280" width="5" height="4" rx="0.5" fill="#f7c23a" opacity="0.45"/>'
		// Modern tower
		. '<rect x="825" y="250" width="4" height="4" rx="0.5" fill="#00d4aa" opacity="0.4"/>'
		. '<rect x="835" y="280" width="4" height="4" rx="0.5" fill="#f7c23a" opacity="0.5"/>'
		// Industrial
		. '<rect x="1070" y="332" width="5" height="4" rx="0.5" fill="#f0a500" opacity="0.55"/>'
		. '<rect x="1120" y="335" width="5" height="4" rx="0.5" fill="#f7c23a" opacity="0.45"/>'
		// Right section
		. '<rect x="1440" y="310" width="5" height="4" rx="0.5" fill="#fff8e6" opacity="0.5"/>'
		. '<rect x="1510" y="300" width="5" height="4" rx="0.5" fill="#f0a500" opacity="0.6"/>'
		. '<rect x="1520" y="310" width="5" height="4" rx="0.5" fill="#00d4aa" opacity="0.35"/>'
		// Left residential
		. '<rect x="100" y="358" width="4" height="3" rx="0.5" fill="#f7c23a" opacity="0.5"/>'
		. '<rect x="140" y="352" width="4" height="3" rx="0.5" fill="#f0a500" opacity="0.45"/>'
		. '<rect x="340" y="305" width="4" height="3.5" rx="0.5" fill="#fff8e6" opacity="0.55"/>'
		. '</g>'
		// Water reflection line at bottom.
		. '<rect x="0" y="480" width="1600" height="20" fill="rgba(15,25,45,0.3)"/>'
		. '<line x1="0" y1="482" x2="1600" y2="482" stroke="rgba(200,215,240,0.08)" stroke-width="1"/>'
		. '</svg>';
	}

	private static function layer_front_v6( string $p ): string {
		return '<svg class="lpnw-hero__layer lpnw-hero__layer--front" viewBox="0 0 1600 500" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg">'
		. '<defs>'
		. '<filter id="' . $p . '-lg"><feGaussianBlur stdDeviation="4"/></filter>'
		. '<linearGradient id="' . $p . '-st" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="transparent"/><stop offset="100%" stop-color="rgba(5,10,20,0.6)"/></linearGradient>'
		. '</defs>'
		// Foreground buildings — terraced houses and smaller structures.
		. '<path d="'
		. 'M0 500 L0 410 L40 410 L40 400 L80 400 L80 410 L120 410 L120 395 L160 395 L160 410 L200 410 L200 500'
		. ' M280 500 L280 420 L320 420 L320 405 L360 405 L360 420 L400 420 L400 500'
		. ' M1200 500 L1200 415 L1240 415 L1240 400 L1280 400 L1280 415 L1320 415 L1320 405 L1360 405 L1360 420 L1400 420 L1400 500'
		. ' M1480 500 L1480 410 L1520 410 L1520 395 L1560 395 L1560 410 L1600 410 L1600 500'
		. '" fill="#142238"/>'
		// Trees.
		. '<g>'
		. '<rect x="218" y="435" width="4" height="22" fill="#1a3828"/><ellipse cx="220" cy="425" rx="14" ry="18" fill="#0f2818" opacity="0.85"/>'
		. '<rect x="448" y="438" width="4" height="20" fill="#1a3828"/><ellipse cx="450" cy="428" rx="12" ry="16" fill="#122a1c" opacity="0.8"/>'
		. '<rect x="618" y="432" width="5" height="24" fill="#1a3828"/><ellipse cx="620" cy="420" rx="16" ry="20" fill="#0f2818" opacity="0.85"/>'
		. '<rect x="878" y="435" width="4" height="22" fill="#1a3828"/><ellipse cx="880" cy="424" rx="13" ry="17" fill="#122a1c" opacity="0.82"/>'
		. '<rect x="1078" y="430" width="5" height="26" fill="#1a3828"/><ellipse cx="1080" cy="418" rx="15" ry="19" fill="#0f2818" opacity="0.85"/>'
		. '<rect x="1158" y="436" width="4" height="21" fill="#1a3828"/><ellipse cx="1160" cy="426" rx="12" ry="16" fill="#122a1c" opacity="0.8"/>'
		. '</g>'
		// Lamp posts with warm glow.
		. '<g>'
		. '<rect x="508" y="430" width="2" height="28" fill="#2a3a52"/><circle cx="509" cy="428" r="3" fill="#f0a500" opacity="0.2" filter="url(#' . $p . '-lg)"/><circle cx="509" cy="428" r="1.5" fill="#f7c23a" opacity="0.7"/>'
		. '<rect x="748" y="432" width="2" height="26" fill="#2a3a52"/><circle cx="749" cy="430" r="3" fill="#f0a500" opacity="0.2" filter="url(#' . $p . '-lg)"/><circle cx="749" cy="430" r="1.5" fill="#f7c23a" opacity="0.7"/>'
		. '<rect x="968" y="428" width="2" height="30" fill="#2a3a52"/><circle cx="969" cy="426" r="3" fill="#f0a500" opacity="0.2" filter="url(#' . $p . '-lg)"/><circle cx="969" cy="426" r="1.5" fill="#f7c23a" opacity="0.7"/>'
		. '</g>'
		// Road and pavement.
		. '<rect x="0" y="458" width="1600" height="42" fill="#15202f"/>'
		. '<line x1="0" y1="478" x2="1600" y2="478" stroke="rgba(240,165,0,0.2)" stroke-width="1.5" stroke-dasharray="12 16"/>'
		// FOR SALE sign.
		. '<g transform="translate(680,430)">'
		. '<rect x="0" y="0" width="2" height="28" fill="#3a4a5f"/>'
		. '<rect x="-6" y="0" width="16" height="10" rx="1" fill="#1e3050" stroke="rgba(240,165,0,0.4)" stroke-width="0.8"/>'
		. '<rect x="-4" y="2.5" width="12" height="5" rx="0.5" fill="rgba(240,165,0,0.15)"/>'
		. '</g>'
		// Teal beacon lights on mid-ground buildings (visible through gaps).
		. '<circle cx="430" cy="390" r="2.5" fill="#00d4aa" opacity="0.5"><animate attributeName="opacity" values="0.2;0.8;0.2" dur="4s" repeatCount="indefinite"/></circle>'
		. '<circle cx="830" cy="380" r="2.5" fill="#00d4aa" opacity="0.4"><animate attributeName="opacity" values="0.3;0.9;0.3" dur="5s" repeatCount="indefinite"/></circle>'
		. '<circle cx="1300" cy="395" r="2" fill="#00d4aa" opacity="0.45"><animate attributeName="opacity" values="0.2;0.7;0.2" dur="6s" repeatCount="indefinite"/></circle>'
		// Street gradient overlay.
		. '<rect x="0" y="440" width="1600" height="60" fill="url(#' . $p . '-st)"/>'
		. '</svg>';
	}

	public static function get_illustration_markup(): string {
		$p = self::id_prefix();
		$v = esc_attr( self::VERSION );

		return '<div class="lpnw-hero__parallax" data-lpnw-hero-svg="' . $v . '" aria-hidden="true">'
			. self::layer_back_v6( $p )
			. self::layer_mid_v6( $p )
			. self::layer_front_v6( $p )
			. '</div>';
	}
}
