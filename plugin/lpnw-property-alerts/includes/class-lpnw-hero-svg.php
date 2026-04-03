<?php
/**
 * Front-page hero cityscape SVG (single source of truth for markup and upgrades).
 *
 * Live sites often store hero HTML in post content; we replace the illustration
 * via `the_content` filter so design improvements deploy without manual DB edits.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hero illustration markup.
 */
class LPNW_Hero_Svg {

	public const VERSION = '2';

	/**
	 * Register content filter on front page.
	 */
	public static function init(): void {
		add_filter( 'the_content', array( __CLASS__, 'filter_replace_illustration' ), 8 );
	}

	/**
	 * Swap inline hero SVG for the current bundled version.
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

		return is_string( $out ) ? $out : $content;
	}

	/**
	 * Full inline SVG (no outer PHP). IDs prefixed to reduce clashes.
	 *
	 * @return string
	 */
	public static function get_illustration_markup(): string {
		$v = esc_attr( self::VERSION );

		return <<<SVG
<svg class="lpnw-hero__illustration" viewBox="0 0 1400 500" preserveAspectRatio="xMidYMax meet" fill="none" xmlns="http://www.w3.org/2000/svg" data-lpnw-hero-svg="{$v}" aria-hidden="true">
<defs>
<linearGradient id="lpnwhs-sky" x1="0" y1="0" x2="0" y2="1">
<stop offset="0%" stop-color="#050a14"/>
<stop offset="45%" stop-color="#0c1a30"/>
<stop offset="100%" stop-color="#152a4a"/>
</linearGradient>
<linearGradient id="lpnwhs-fog" x1="0" y1="0" x2="0" y2="1">
<stop offset="0%" stop-color="transparent"/>
<stop offset="55%" stop-color="rgba(15,29,53,0)"/>
<stop offset="100%" stop-color="rgba(5,10,20,0.55)"/>
</linearGradient>
<radialGradient id="lpnwhs-moon" cx="0.4" cy="0.35" r="0.5">
<stop offset="0%" stop-color="rgba(255,248,220,0.95)"/>
<stop offset="50%" stop-color="rgba(200,220,255,0.25)"/>
<stop offset="100%" stop-color="transparent"/>
</radialGradient>
<linearGradient id="lpnwhs-river" x1="0" y1="0" x2="1" y2="0">
<stop offset="0%" stop-color="rgba(0,212,170,0)"/>
<stop offset="35%" stop-color="rgba(0,212,170,0.12)"/>
<stop offset="65%" stop-color="rgba(240,165,0,0.08)"/>
<stop offset="100%" stop-color="rgba(0,212,170,0)"/>
</linearGradient>
<linearGradient id="lpnwhs-glow-gold" x1="0" y1="0" x2="0" y2="1">
<stop offset="0%" stop-color="rgba(240,165,0,0.35)"/>
<stop offset="100%" stop-color="transparent"/>
</linearGradient>
<radialGradient id="lpnwhs-bell-halo" cx="0.5" cy="0.45" r="0.55">
<stop offset="0%" stop-color="rgba(240,165,0,0.45)"/>
<stop offset="45%" stop-color="rgba(0,212,170,0.12)"/>
<stop offset="100%" stop-color="transparent"/>
</radialGradient>
<radialGradient id="lpnwhs-lamplight" cx="0.5" cy="0" r="0.85">
<stop offset="0%" stop-color="rgba(240,165,0,0.28)"/>
<stop offset="100%" stop-color="transparent"/>
</radialGradient>
<linearGradient id="lpnwhs-glass" x1="0" y1="0" x2="1" y2="0">
<stop offset="0%" stop-color="rgba(255,255,255,0)"/>
<stop offset="45%" stop-color="rgba(255,255,255,0.14)"/>
<stop offset="55%" stop-color="rgba(255,255,255,0.06)"/>
<stop offset="100%" stop-color="rgba(255,255,255,0)"/>
</linearGradient>
<linearGradient id="lpnwhs-facade-a" x1="0" y1="0" x2="1" y2="1">
<stop offset="0%" stop-color="#1e3a5f"/>
<stop offset="100%" stop-color="#0f1d35"/>
</linearGradient>
<linearGradient id="lpnwhs-facade-b" x1="0" y1="0" x2="0" y2="1">
<stop offset="0%" stop-color="#2a4a78"/>
<stop offset="100%" stop-color="#121f36"/>
</linearGradient>
<filter id="lpnwhs-soft" x="-20%" y="-20%" width="140%" height="140%">
<feGaussianBlur in="SourceGraphic" stdDeviation="1.2"/>
</filter>
<filter id="lpnwhs-glow-win" x="-50%" y="-50%" width="200%" height="200%">
<feGaussianBlur in="SourceGraphic" stdDeviation="1.8" result="b"/>
<feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
</filter>
</defs>
<rect width="1400" height="500" fill="url(#lpnwhs-sky)"/>
<ellipse cx="1180" cy="95" rx="52" ry="52" fill="url(#lpnwhs-moon)" opacity="0.9"/>
<path d="M1180 55c-8 0-14 10-12 22 2 12 12 18 22 16 10-2 16-12 14-22-2-14-14-24-24-16z" fill="rgba(8,14,26,0.92)"/>
<g opacity="0.35" fill="rgba(255,255,255,0.5)">
<path d="M180 88c40-18 85-8 120 4s90 12 130-6 95-10 140 8 100 20 155 6" stroke="rgba(255,255,255,0.25)" stroke-width="28" stroke-linecap="round" filter="url(#lpnwhs-soft)"/>
<path d="M90 120c50-22 100-15 160 2s120 8 175-12 130-5 185 14" stroke="rgba(147,197,253,0.2)" stroke-width="36" stroke-linecap="round" filter="url(#lpnwhs-soft)"/>
</g>
<g fill="#fff" opacity="0.5">
<circle cx="220" cy="62" r="1.2"/><circle cx="340" cy="48" r="0.9"/><circle cx="520" cy="72" r="1"/><circle cx="680" cy="38" r="0.8"/><circle cx="900" cy="55" r="1.1"/><circle cx="1020" cy="42" r="0.7"/><circle cx="1280" cy="68" r="1"/>
</g>
<rect x="0" y="280" width="1400" height="220" fill="url(#lpnwhs-fog)"/>
<rect x="0" y="468" width="1400" height="32" fill="url(#lpnwhs-river)"/>
<rect x="0" y="418" width="1400" height="70" fill="url(#lpnwhs-glow-gold)" opacity="0.6"/>
<g opacity="0.92">
<rect x="40" y="268" width="88" height="232" rx="3" fill="url(#lpnwhs-facade-a)"/>
<rect x="152" y="198" width="108" height="302" rx="3" fill="url(#lpnwhs-facade-b)"/>
<rect x="198" y="162" width="36" height="40" fill="#1a2d4f"/>
<polygon points="216,128 198,162 234,162" fill="#243d62"/>
<rect x="208" y="132" width="10" height="14" fill="#3d5a80"/>
<rect x="280" y="352" width="52" height="148" rx="2" fill="#253d65"/>
<rect x="338" y="338" width="52" height="162" rx="2" fill="#2d4470"/>
<rect x="396" y="358" width="52" height="142" rx="2" fill="#253d65"/>
<polygon points="280,352 306,325 332,352" fill="#1e3a5f"/>
<polygon points="338,338 364,312 390,338" fill="#1a2d4f"/>
<polygon points="396,358 422,332 448,358" fill="#1e3a5f"/>
<rect x="868" y="72" width="42" height="428" rx="2" fill="#101f38"/>
<rect x="864" y="66" width="50" height="12" fill="#1e3a5f"/>
<polygon points="895,28 864,66 926,66" fill="#1a2d4f"/>
<rect x="938" y="272" width="78" height="228" rx="3" fill="url(#lpnwhs-facade-a)"/>
<rect x="1142" y="362" width="54" height="138" rx="2" fill="#253d65"/>
<rect x="1204" y="372" width="54" height="128" rx="2" fill="#2d4470"/>
<rect x="1266" y="366" width="54" height="134" rx="2" fill="#253d65"/>
</g>
<rect x="872" y="90" width="36" height="380" fill="url(#lpnwhs-glass)" opacity="0.5"/>
<g stroke="#2d4470" stroke-linecap="round">
<line x1="1056" y1="168" x2="1056" y2="500" stroke-width="5"/>
<line x1="1008" y1="172" x2="1112" y2="172" stroke-width="4"/>
<line x1="1056" y1="172" x2="1012" y2="192" stroke-width="3"/>
<line x1="1056" y1="172" x2="1108" y2="186" stroke-width="3"/>
<line x1="1108" y1="186" x2="1108" y2="228" stroke-width="2" stroke="#3d5a80"/>
</g>
<rect x="1098" y="226" width="14" height="12" rx="1" fill="#f0a500" opacity="0.35"/>
<rect x="432" y="396" width="5" height="104" fill="#3d5a80"/>
<rect x="418" y="382" width="62" height="36" rx="4" fill="#f0a500"/>
<rect x="424" y="388" width="50" height="24" rx="2" fill="rgba(255,255,255,0.92)"/>
<ellipse cx="520" cy="412" rx="26" ry="34" fill="#0d5c4a" opacity="0.75"/>
<ellipse cx="528" cy="400" rx="20" ry="28" fill="#00d4aa" opacity="0.28"/>
<rect x="516" y="438" width="7" height="62" fill="#143d32"/>
<rect x="818" y="398" width="4" height="102" fill="#3d5a80"/>
<ellipse cx="820" cy="396" rx="14" ry="5" fill="#f0a500" opacity="0.2"/>
<rect x="814" y="392" width="12" height="8" rx="2" fill="#f0a500" opacity="0.75"/>
<ellipse cx="820" cy="462" rx="38" ry="32" fill="url(#lpnwhs-lamplight)" opacity="0.45"/>
<g transform="translate(652 288)">
<circle cx="48" cy="48" r="120" fill="url(#lpnwhs-bell-halo)" opacity="0.85"/>
<circle cx="48" cy="48" r="78" fill="none" stroke="#f0a500" stroke-width="1.2" opacity="0.22">
<animate attributeName="r" values="72;82;72" dur="5s" repeatCount="indefinite"/>
<animate attributeName="opacity" values="0.15;0.35;0.15" dur="5s" repeatCount="indefinite"/>
</circle>
<circle cx="48" cy="48" r="56" fill="none" stroke="#00d4aa" stroke-width="1" opacity="0.25">
<animate attributeName="r" values="52;62;52" dur="4.2s" repeatCount="indefinite"/>
</circle>
<g>
<animateTransform attributeName="transform" type="rotate" values="-5 48 48;5 48 48;-5 48 48" dur="3.8s" repeatCount="indefinite"/>
<circle cx="48" cy="48" r="46" fill="rgba(240,165,0,0.08)"/>
<path d="M48 12c-3.2 0-5.8 2.4-5.8 5.6v3.8C30 23 20 34 20 46.5v18l-8 8.5v3.5h72v-3.5l-8-8.5V46.5C76 34 66 23 53.8 21.4V17.6C53.8 14.4 51.2 12 48 12z" fill="#f0a500" opacity="0.95"/>
<path d="M48 86c4.2 0 7.5-3.5 7.5-7.8H40.5C40.5 82.5 43.8 86 48 86z" fill="#f7c23a" opacity="0.88"/>
<circle cx="68" cy="22" r="7" fill="#00d4aa" opacity="0.95"/>
<text x="68" y="25" fill="#fff" font-size="9" font-weight="700" text-anchor="middle" font-family="system-ui,sans-serif">3</text>
</g>
</g>
<g opacity="0.85">
<rect x="58" y="278" width="9" height="7" rx="1" fill="#f0a500" filter="url(#lpnwhs-glow-win)"/><rect x="78" y="278" width="9" height="7" rx="1" fill="#fff" opacity="0.14"/>
<rect x="98" y="278" width="9" height="7" rx="1" fill="#f0a500" opacity="0.4"/><rect x="58" y="298" width="9" height="7" rx="1" fill="#f0a500" opacity="0.5" filter="url(#lpnwhs-glow-win)"/>
<rect x="178" y="218" width="11" height="8" rx="1" fill="#f0a500" opacity="0.55" filter="url(#lpnwhs-glow-win)"/><rect x="208" y="218" width="11" height="8" rx="1" fill="#fff" opacity="0.18"/>
<rect x="238" y="218" width="11" height="8" rx="1" fill="#f0a500" opacity="0.42"/><rect x="178" y="248" width="11" height="8" rx="1" fill="#fff" opacity="0.12"/>
<rect x="208" y="248" width="11" height="8" rx="1" fill="#f0a500" opacity="0.52" filter="url(#lpnwhs-glow-win)"/><rect x="238" y="248" width="11" height="8" rx="1" fill="#f0a500" opacity="0.32"/>
<rect x="952" y="292" width="8" height="6" rx="1" fill="#f0a500" opacity="0.48" filter="url(#lpnwhs-glow-win)"/><rect x="978" y="292" width="8" height="6" rx="1" fill="#fff" opacity="0.16"/>
<rect x="1004" y="292" width="8" height="6" rx="1" fill="#f0a500" opacity="0.38"/><rect x="890" y="108" width="6" height="5" rx="0.5" fill="#f0a500" opacity="0.45" filter="url(#lpnwhs-glow-win)"/>
<rect x="902" y="108" width="6" height="5" rx="0.5" fill="#fff" opacity="0.12"/><rect x="890" y="200" width="6" height="5" rx="0.5" fill="#f0a500" opacity="0.5" filter="url(#lpnwhs-glow-win)"/>
</g>
<line x1="0" y1="500" x2="1400" y2="500" stroke="#2d4470" stroke-width="1.2" opacity="0.35"/>
</svg>
SVG;
	}
}
