<?php
/**
 * Static HTML page bodies for WordPress pages (setup scripts insert post_content).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Marketing and legal page HTML returned for insertion into page post_content.
 */
class LPNW_Page_Content {

	/**
	 * Home page: hero, steps, live feed shortcodes, pricing teaser, CTA.
	 *
	 * @return string HTML (shortcodes preserved for WordPress rendering).
	 */
	public static function get_home_content(): string {
		$register = esc_url( wp_registration_url() );
		$pricing  = esc_url( home_url( '/pricing/' ) );
		$shop_url = esc_url( home_url( '/shop/' ) );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_page = wc_get_page_permalink( 'shop' );
			if ( $shop_page ) {
				$shop_url = esc_url( $shop_page );
			}
		}

		return <<<HTML
<section class="lpnw-hero" aria-labelledby="lpnw-hero-heading">
	<canvas id="lpnw-hero-particles" class="lpnw-hero__canvas" aria-hidden="true"></canvas>
	<div class="lpnw-hero__scene" aria-hidden="true">
		<svg class="lpnw-hero__illustration" viewBox="0 0 1400 500" preserveAspectRatio="xMidYMax meet" fill="none" xmlns="http://www.w3.org/2000/svg">
			<!-- Sky gradient -->
			<defs>
				<linearGradient id="skyGrad" x1="0" y1="0" x2="0" y2="1">
					<stop offset="0%" stop-color="#080E1A"/>
					<stop offset="70%" stop-color="#0F1D35"/>
					<stop offset="100%" stop-color="#1A2D4F"/>
				</linearGradient>
				<linearGradient id="groundGlow" x1="0" y1="0" x2="0" y2="1">
					<stop offset="0%" stop-color="rgba(240,165,0,0.12)"/>
					<stop offset="100%" stop-color="transparent"/>
				</linearGradient>
				<radialGradient id="lampLight" cx="0.5" cy="0" r="0.8">
					<stop offset="0%" stop-color="rgba(240,165,0,0.25)"/>
					<stop offset="100%" stop-color="transparent"/>
				</radialGradient>
				<filter id="windowGlow">
					<feGaussianBlur in="SourceGraphic" stdDeviation="1.5"/>
				</filter>
			</defs>
			<!-- Ground glow -->
			<rect x="0" y="420" width="1400" height="80" fill="url(#groundGlow)"/>
			<!-- Back buildings - lighter navy -->
			<rect x="50" y="260" width="80" height="240" rx="2" fill="#1E3A5F"/>
			<rect x="55" y="275" width="8" height="6" rx="1" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<rect x="75" y="275" width="8" height="6" rx="1" fill="#F0A500" opacity="0.35"/>
			<rect x="95" y="275" width="8" height="6" rx="1" fill="#FFF" opacity="0.15"/>
			<rect x="55" y="295" width="8" height="6" rx="1" fill="#FFF" opacity="0.12"/>
			<rect x="75" y="295" width="8" height="6" rx="1" fill="#F0A500" opacity="0.45" filter="url(#windowGlow)"/>
			<rect x="95" y="295" width="8" height="6" rx="1" fill="#F0A500" opacity="0.3"/>
			<rect x="55" y="315" width="8" height="6" rx="1" fill="#F0A500" opacity="0.4"/>
			<rect x="75" y="315" width="8" height="6" rx="1" fill="#FFF" opacity="0.18"/>
			<rect x="95" y="315" width="8" height="6" rx="1" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<rect x="55" y="335" width="8" height="6" rx="1" fill="#FFF" opacity="0.1"/>
			<rect x="75" y="335" width="8" height="6" rx="1" fill="#F0A500" opacity="0.35"/>
			<rect x="95" y="335" width="8" height="6" rx="1" fill="#FFF" opacity="0.15"/>
			<!-- Liver Building inspired -->
			<rect x="160" y="200" width="100" height="300" rx="2" fill="#1A2D4F"/>
			<rect x="195" y="170" width="30" height="30" fill="#1A2D4F"/>
			<polygon points="210,145 195,170 225,170" fill="#1E3A5F"/>
			<rect x="206" y="145" width="8" height="10" fill="#2D4470"/>
			<rect x="170" y="220" width="10" height="8" rx="1" fill="#F0A500" opacity="0.6" filter="url(#windowGlow)"/>
			<rect x="195" y="220" width="10" height="8" rx="1" fill="#FFF" opacity="0.2"/>
			<rect x="220" y="220" width="10" height="8" rx="1" fill="#F0A500" opacity="0.45"/>
			<rect x="170" y="245" width="10" height="8" rx="1" fill="#FFF" opacity="0.15"/>
			<rect x="195" y="245" width="10" height="8" rx="1" fill="#F0A500" opacity="0.55" filter="url(#windowGlow)"/>
			<rect x="220" y="245" width="10" height="8" rx="1" fill="#F0A500" opacity="0.3"/>
			<rect x="170" y="270" width="10" height="8" rx="1" fill="#F0A500" opacity="0.4"/>
			<rect x="195" y="270" width="10" height="8" rx="1" fill="#F0A500" opacity="0.35"/>
			<rect x="220" y="270" width="10" height="8" rx="1" fill="#FFF" opacity="0.2"/>
			<rect x="170" y="295" width="10" height="8" rx="1" fill="#FFF" opacity="0.12"/>
			<rect x="195" y="295" width="10" height="8" rx="1" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<rect x="220" y="295" width="10" height="8" rx="1" fill="#F0A500" opacity="0.4"/>
			<rect x="170" y="320" width="10" height="8" rx="1" fill="#F0A500" opacity="0.45"/>
			<rect x="195" y="320" width="10" height="8" rx="1" fill="#FFF" opacity="0.18"/>
			<rect x="220" y="320" width="10" height="8" rx="1" fill="#F0A500" opacity="0.35" filter="url(#windowGlow)"/>
			<!-- Terrace row - left -->
			<rect x="290" y="360" width="45" height="140" rx="1" fill="#2D4470"/>
			<rect x="340" y="355" width="45" height="145" rx="1" fill="#253D65"/>
			<rect x="390" y="365" width="45" height="135" rx="1" fill="#2D4470"/>
			<polygon points="290,360 312,340 335,360" fill="#1E3A5F"/>
			<polygon points="340,355 362,335 385,355" fill="#1A2D4F"/>
			<polygon points="390,365 412,345 435,365" fill="#1E3A5F"/>
			<rect x="300" y="380" width="8" height="8" rx="1" fill="#F0A500" opacity="0.6" filter="url(#windowGlow)"/>
			<rect x="318" y="380" width="8" height="8" rx="1" fill="#F0A500" opacity="0.4"/>
			<rect x="350" y="375" width="8" height="8" rx="1" fill="#FFF" opacity="0.2"/>
			<rect x="368" y="375" width="8" height="8" rx="1" fill="#F0A500" opacity="0.55" filter="url(#windowGlow)"/>
			<rect x="400" y="385" width="8" height="8" rx="1" fill="#F0A500" opacity="0.35"/>
			<rect x="418" y="385" width="8" height="8" rx="1" fill="#FFF" opacity="0.15"/>
			<rect x="305" y="435" width="12" height="25" rx="1" fill="#F0A500" opacity="0.2"/>
			<rect x="357" y="430" width="12" height="25" rx="1" fill="#F0A500" opacity="0.25"/>
			<rect x="407" y="440" width="12" height="25" rx="1" fill="#F0A500" opacity="0.18"/>
			<!-- FOR SALE sign -->
			<rect x="445" y="410" width="4" height="90" fill="#3D5A80"/>
			<rect x="435" y="400" width="55" height="30" rx="3" fill="#F0A500"/>
			<rect x="439" y="404" width="47" height="22" rx="2" fill="#FFF"/>
			<!-- Tree -->
			<ellipse cx="510" cy="420" rx="22" ry="30" fill="#0D7D5F" opacity="0.7"/>
			<ellipse cx="518" cy="410" rx="18" ry="25" fill="#00D4AA" opacity="0.35"/>
			<rect x="508" y="445" width="5" height="55" fill="#1A4035"/>
			<!-- CENTER: Notification bell with pulse rings -->
			<g transform="translate(660, 300)">
				<circle cx="40" cy="40" r="70" fill="none" stroke="#F0A500" stroke-width="1" opacity="0.15" class="lpnw-pulse-ring lpnw-pulse-ring--1"/>
				<circle cx="40" cy="40" r="50" fill="none" stroke="#F0A500" stroke-width="1.5" opacity="0.25" class="lpnw-pulse-ring lpnw-pulse-ring--2"/>
				<circle cx="40" cy="40" r="30" fill="none" stroke="#00D4AA" stroke-width="1" opacity="0.2" class="lpnw-pulse-ring lpnw-pulse-ring--3"/>
				<g class="lpnw-hero__bell-swing">
					<circle cx="40" cy="40" r="45" fill="rgba(240,165,0,0.06)"/>
					<path d="M40 10C37.5 10 35.5 12 35.5 14.5V17.8C25 19.8 17 28.8 17 39.5V55L10 62V65H70V62L63 55V39.5C63 28.8 55 19.8 44.5 17.8V14.5C44.5 12 42.5 10 40 10Z" fill="#F0A500" opacity="0.9"/>
					<path d="M40 75C43.3 75 46 72.3 46 69H34C34 72.3 36.7 75 40 75Z" fill="#F7C23A" opacity="0.8"/>
					<circle cx="58" cy="18" r="6" fill="#00D4AA" opacity="0.9"/>
					<text x="55" y="21" fill="#FFF" font-size="8" font-weight="700" text-anchor="middle">3</text>
				</g>
			</g>
			<!-- Right: Beetham Tower inspired -->
			<rect x="880" y="80" width="35" height="420" rx="1" fill="#162B48"/>
			<rect x="878" y="75" width="39" height="8" fill="#1E3A5F"/>
			<polygon points="897,40 878,75 917,75" fill="#1A2D4F"/>
			<rect x="886" y="100" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<rect x="900" y="100" width="5" height="4" rx="0.5" fill="#FFF" opacity="0.15"/>
			<rect x="886" y="120" width="5" height="4" rx="0.5" fill="#FFF" opacity="0.12"/>
			<rect x="900" y="120" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.45"/>
			<rect x="886" y="140" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.55" filter="url(#windowGlow)"/>
			<rect x="900" y="140" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.3"/>
			<rect x="886" y="160" width="5" height="4" rx="0.5" fill="#FFF" opacity="0.18"/>
			<rect x="900" y="160" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.4"/>
			<rect x="886" y="180" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.35"/>
			<rect x="900" y="180" width="5" height="4" rx="0.5" fill="#FFF" opacity="0.12"/>
			<rect x="886" y="200" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<rect x="900" y="200" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.25"/>
			<rect x="886" y="250" width="5" height="4" rx="0.5" fill="#FFF" opacity="0.15"/>
			<rect x="900" y="250" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.45" filter="url(#windowGlow)"/>
			<rect x="886" y="300" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.4"/>
			<rect x="900" y="300" width="5" height="4" rx="0.5" fill="#FFF" opacity="0.1"/>
			<rect x="886" y="350" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.35"/>
			<rect x="900" y="350" width="5" height="4" rx="0.5" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<!-- Mid-right buildings -->
			<rect x="950" y="280" width="70" height="220" rx="2" fill="#1E3A5F"/>
			<rect x="958" y="300" width="8" height="6" rx="1" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<rect x="978" y="300" width="8" height="6" rx="1" fill="#FFF" opacity="0.18"/>
			<rect x="998" y="300" width="8" height="6" rx="1" fill="#F0A500" opacity="0.4"/>
			<rect x="958" y="325" width="8" height="6" rx="1" fill="#FFF" opacity="0.12"/>
			<rect x="978" y="325" width="8" height="6" rx="1" fill="#F0A500" opacity="0.55" filter="url(#windowGlow)"/>
			<rect x="998" y="325" width="8" height="6" rx="1" fill="#F0A500" opacity="0.3"/>
			<rect x="958" y="350" width="8" height="6" rx="1" fill="#F0A500" opacity="0.45"/>
			<rect x="978" y="350" width="8" height="6" rx="1" fill="#F0A500" opacity="0.35"/>
			<rect x="998" y="350" width="8" height="6" rx="1" fill="#FFF" opacity="0.2"/>
			<!-- Crane -->
			<g class="lpnw-hero__crane">
				<line x1="1060" y1="180" x2="1060" y2="500" stroke="#2D4470" stroke-width="4"/>
				<line x1="1020" y1="185" x2="1120" y2="185" stroke="#2D4470" stroke-width="3"/>
				<line x1="1060" y1="185" x2="1020" y2="200" stroke="#2D4470" stroke-width="2"/>
				<line x1="1060" y1="185" x2="1120" y2="195" stroke="#2D4470" stroke-width="2"/>
				<line x1="1120" y1="195" x2="1120" y2="230" stroke="#3D5A80" stroke-width="1.5"/>
				<rect x="1115" y="230" width="10" height="8" fill="#F0A500" opacity="0.3"/>
			</g>
			<!-- Far right terraces -->
			<rect x="1150" y="370" width="50" height="130" rx="1" fill="#253D65"/>
			<rect x="1205" y="380" width="50" height="120" rx="1" fill="#2D4470"/>
			<rect x="1260" y="375" width="50" height="125" rx="1" fill="#253D65"/>
			<polygon points="1150,370 1175,350 1200,370" fill="#1E3A5F"/>
			<polygon points="1205,380 1230,360 1255,380" fill="#1A2D4F"/>
			<polygon points="1260,375 1285,355 1310,375" fill="#1E3A5F"/>
			<rect x="1160" y="392" width="7" height="7" rx="1" fill="#F0A500" opacity="0.5" filter="url(#windowGlow)"/>
			<rect x="1180" y="392" width="7" height="7" rx="1" fill="#FFF" opacity="0.15"/>
			<rect x="1215" y="400" width="7" height="7" rx="1" fill="#F0A500" opacity="0.4"/>
			<rect x="1235" y="400" width="7" height="7" rx="1" fill="#F0A500" opacity="0.55" filter="url(#windowGlow)"/>
			<rect x="1270" y="396" width="7" height="7" rx="1" fill="#FFF" opacity="0.18"/>
			<rect x="1290" y="396" width="7" height="7" rx="1" fill="#F0A500" opacity="0.35"/>
			<!-- Lamppost with light cone -->
			<rect x="830" y="410" width="3" height="90" fill="#3D5A80"/>
			<ellipse cx="831" cy="408" rx="12" ry="4" fill="#F0A500" opacity="0.15"/>
			<rect x="826" y="405" width="10" height="6" rx="2" fill="#F0A500" opacity="0.7"/>
			<ellipse cx="831" cy="470" rx="35" ry="30" fill="url(#lampLight)" opacity="0.5"/>
			<!-- Ground line -->
			<line x1="0" y1="500" x2="1400" y2="500" stroke="#2D4470" stroke-width="1" opacity="0.3"/>
		</svg>
		<!-- Scan line overlay -->
		<div class="lpnw-hero__scan-line"></div>
	</div>
	<div class="lpnw-hero__content">
		<h1 id="lpnw-hero-heading" class="lpnw-hero__title">
			<span class="lpnw-hero__line lpnw-hero__line--1">Property <em>alerts</em></span>
			<span class="lpnw-hero__line lpnw-hero__line--2">before <em>anyone</em></span>
			<span class="lpnw-hero__line lpnw-hero__line--3">else.</span>
		</h1>
		<p class="lpnw-hero__subtitle">We scan every listing across Northwest England and alert you the moment something matches. While others are still browsing, you already have the details.</p>
		<div class="lpnw-hero__actions">
			<a class="lpnw-btn lpnw-btn--primary" href="{$register}">Start free</a>
			<a class="lpnw-btn lpnw-btn--ghost" href="{$pricing}">See pricing</a>
		</div>
	</div>
</section>

<section class="lpnw-trust-bar" aria-label="How often listings are refreshed">
	<p class="lpnw-trust-bar__text">Pulling the latest property listings from across the Northwest every 15 minutes.</p>
</section>

<section class="lpnw-stats-bar" aria-labelledby="lpnw-stats-bar-title">
	<h2 id="lpnw-stats-bar-title" class="screen-reader-text">Coverage and update frequency</h2>
	<ul class="lpnw-stats-bar__list" role="list">
		<li class="lpnw-stats-bar__item">
			<svg class="lpnw-stats-bar__icon" width="80" height="80" viewBox="0 0 80 80" fill="none" aria-hidden="true">
				<rect x="10" y="30" width="18" height="40" rx="2" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<rect x="31" y="15" width="18" height="55" rx="2" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<rect x="52" y="5" width="18" height="65" rx="2" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
			</svg>
			<span class="lpnw-stats-bar__value">[lpnw_property_count plus="1"]</span> properties in our live index
		</li>
		<li class="lpnw-stats-bar__item">
			<svg class="lpnw-stats-bar__icon" width="80" height="80" viewBox="0 0 80 80" fill="none" aria-hidden="true">
				<circle cx="20" cy="20" r="6" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<circle cx="60" cy="20" r="6" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<circle cx="40" cy="55" r="6" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<circle cx="15" cy="60" r="6" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<circle cx="65" cy="60" r="6" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<line x1="26" y1="22" x2="54" y2="22" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
				<line x1="22" y1="26" x2="38" y2="49" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
				<line x1="58" y1="26" x2="42" y2="49" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
				<line x1="21" y1="54" x2="34" y2="55" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
				<line x1="59" y1="54" x2="46" y2="55" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
			</svg>
			From <span class="lpnw-stats-bar__value">[lpnw_total_sources format="stat"]</span> data sources
		</li>
		<li class="lpnw-stats-bar__item">
			<svg class="lpnw-stats-bar__icon" width="80" height="80" viewBox="0 0 80 80" fill="none" aria-hidden="true">
				<path d="M40 8C28 8 14 18 10 35C8 44 12 55 20 62L40 72L60 62C68 55 72 44 70 35C66 18 52 8 40 8Z" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<circle cx="40" cy="38" r="8" stroke="rgba(255,255,255,0.04)" stroke-width="1" fill="none"/>
			</svg>
			<span class="lpnw-stats-bar__value">Entire Northwest England</span> covered
		</li>
		<li class="lpnw-stats-bar__item">
			<svg class="lpnw-stats-bar__icon" width="80" height="80" viewBox="0 0 80 80" fill="none" aria-hidden="true">
				<circle cx="40" cy="40" r="28" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" fill="none"/>
				<line x1="40" y1="18" x2="40" y2="40" stroke="rgba(255,255,255,0.05)" stroke-width="1.5"/>
				<line x1="40" y1="40" x2="55" y2="50" stroke="rgba(255,255,255,0.05)" stroke-width="1.5"/>
				<circle cx="40" cy="40" r="3" fill="rgba(255,255,255,0.04)"/>
			</svg>
			Listing checks and alert runs every <span class="lpnw-stats-bar__value">15 minutes</span>
		</li>
	</ul>
</section>

<section class="lpnw-how-it-works" id="lpnw-how-it-works" aria-labelledby="lpnw-how-it-works-title">
	<h2 id="lpnw-how-it-works-title" class="lpnw-how-it-works__title">How it works</h2>
	<div class="lpnw-steps">
		<div class="lpnw-step">
			<div class="lpnw-step__icon" aria-hidden="true">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none">
					<circle cx="20" cy="20" r="18" stroke="rgba(240,165,0,0.25)" stroke-width="1" fill="none"/>
					<circle cx="20" cy="20" r="12" stroke="rgba(240,165,0,0.2)" stroke-width="1" fill="none"/>
					<circle cx="20" cy="20" r="6" stroke="rgba(240,165,0,0.3)" stroke-width="1" fill="none"/>
					<circle cx="20" cy="20" r="2" fill="rgba(240,165,0,0.5)"/>
					<line x1="20" y1="20" x2="35" y2="8" stroke="rgba(240,165,0,0.35)" stroke-width="1.5" stroke-linecap="round"/>
				</svg>
			</div>
			<h3 id="lpnw-how-it-works-step-1" class="lpnw-step__title">We watch the whole region</h3>
			<p class="lpnw-step__text">Our systems continuously scan property listings across the Northwest, checking for new properties every 15 minutes so nothing gets past you.</p>
		</div>
		<div class="lpnw-step">
			<div class="lpnw-step__icon" aria-hidden="true">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none">
					<path d="M8 6L20 16L32 6" stroke="rgba(240,165,0,0.3)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
					<path d="M12 16L20 24L28 16" stroke="rgba(240,165,0,0.25)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
					<line x1="20" y1="24" x2="20" y2="36" stroke="rgba(240,165,0,0.3)" stroke-width="1.5" stroke-linecap="round"/>
					<circle cx="12" cy="30" r="2.5" stroke="rgba(240,165,0,0.35)" stroke-width="1" fill="rgba(240,165,0,0.15)"/>
					<circle cx="28" cy="30" r="2.5" stroke="rgba(240,165,0,0.35)" stroke-width="1" fill="rgba(240,165,0,0.15)"/>
					<circle cx="20" cy="36" r="2.5" stroke="rgba(240,165,0,0.35)" stroke-width="1" fill="rgba(240,165,0,0.15)"/>
				</svg>
			</div>
			<h3 id="lpnw-how-it-works-step-2" class="lpnw-step__title">You set your criteria</h3>
			<p class="lpnw-step__text">Choose areas, price bands, property types, and which sources you care about. Paid plans unlock full filtering so you only see deals you would actually pursue.</p>
		</div>
		<div class="lpnw-step">
			<div class="lpnw-step__icon" aria-hidden="true">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none">
					<path d="M20 2C18.6 2 17.5 3.1 17.5 4.5V6.1C11.5 7.4 7 12.8 7 19.2V28L3.5 31.5V33H36.5V31.5L33 28V19.2C33 12.8 28.5 7.4 22.5 6.1V4.5C22.5 3.1 21.4 2 20 2Z" stroke="rgba(240,165,0,0.3)" stroke-width="1.5" fill="none"/>
					<path d="M20 38C22.2 38 24 36.2 24 34H16C16 36.2 17.8 38 20 38Z" stroke="rgba(240,165,0,0.3)" stroke-width="1.5" fill="none"/>
					<path d="M26 14L22 20H28L22 28" stroke="rgba(240,165,0,0.5)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
				</svg>
			</div>
			<h3 id="lpnw-how-it-works-step-3" class="lpnw-step__title">You get the alert</h3>
			<p class="lpnw-step__text">When a new record matches, we email you. Pro and VIP send instant or daily alerts. Free accounts get a weekly digest so you can judge quality before you upgrade.</p>
		</div>
	</div>
</section>

<section class="lpnw-home-feed" aria-labelledby="lpnw-home-feed-title">
	<h2 id="lpnw-home-feed-title" class="lpnw-pricing-section__title">Latest activity</h2>
	<p>A live sample of six recent records from our normalised Northwest database.</p>
	<div class="lpnw-home-feed__properties">
		[lpnw_latest_properties limit="6"]
	</div>
</section>

<section class="lpnw-pricing-section lpnw-home-pricing-teaser" id="lpnw-home-pricing" aria-labelledby="lpnw-home-pricing-title">
	<h2 id="lpnw-home-pricing-title" class="lpnw-pricing-section__title">Simple plans</h2>
	<p class="lpnw-home-pricing-teaser__lead">Start on the weekly digest, upgrade when you want instant alerts and full control. <a href="{$pricing}">Full comparison and FAQ on the pricing page</a>.</p>
	<div class="lpnw-pricing">
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-home-tier-free">
			<div class="lpnw-pricing-card__icon" aria-hidden="true">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none">
					<path d="M20 4C18.9 4 18 4.9 18 6V7.5C13.2 8.6 9.5 12.8 9.5 18V26L6 29.5V31H34V29.5L30.5 26V18C30.5 12.8 26.8 8.6 22 7.5V6C22 4.9 21.1 4 20 4Z" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" fill="none"/>
					<path d="M20 36C21.7 36 23 34.7 23 33H17C17 34.7 18.3 36 20 36Z" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" fill="none"/>
				</svg>
			</div>
			<h3 id="lpnw-home-tier-free" class="lpnw-pricing-card__name">Free</h3>
			<p class="lpnw-pricing-card__price">&pound;0</p>
			<p class="lpnw-pricing-card__period">forever</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Weekly digest email</li>
				<li>Sample across data sources</li>
				<li>Upgrade any time</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$register}">Sign up free</a>
		</article>
		<article class="lpnw-pricing-card lpnw-pricing-card--featured" aria-labelledby="lpnw-home-tier-pro">
			<div class="lpnw-pricing-card__icon" aria-hidden="true">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none">
					<circle cx="20" cy="20" r="16" stroke="rgba(240,165,0,0.35)" stroke-width="1.5" fill="none"/>
					<circle cx="20" cy="20" r="10" stroke="rgba(240,165,0,0.25)" stroke-width="1" fill="none"/>
					<circle cx="20" cy="20" r="4" stroke="rgba(240,165,0,0.4)" stroke-width="1" fill="rgba(240,165,0,0.2)"/>
					<path d="M32 8C28 12 24 14 20 16" stroke="rgba(240,165,0,0.35)" stroke-width="1.5" stroke-linecap="round" fill="none"/>
					<path d="M34 14L36 8L30 10" stroke="rgba(240,165,0,0.3)" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
					<path d="M36 14L38 8L32 10" stroke="rgba(240,165,0,0.2)" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
				</svg>
			</div>
			<h3 id="lpnw-home-tier-pro" class="lpnw-pricing-card__name">Pro</h3>
			<p class="lpnw-pricing-card__price">&pound;19.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Instant alerts when you want them</li>
				<li>Full filters by area, price, and type</li>
				<li>Dashboard and saved properties</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--primary" href="{$shop_url}">Get Pro</a>
		</article>
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-home-tier-vip">
			<div class="lpnw-pricing-card__icon" aria-hidden="true">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none">
					<path d="M20 4L24 14L34 14L26 20L29 31L20 24L11 31L14 20L6 14L16 14L20 4Z" stroke="rgba(0,212,170,0.4)" stroke-width="1.5" fill="rgba(0,212,170,0.08)"/>
					<path d="M8 8L14 16" stroke="rgba(0,212,170,0.25)" stroke-width="1" stroke-linecap="round"/>
					<path d="M32 8L26 16" stroke="rgba(0,212,170,0.25)" stroke-width="1" stroke-linecap="round"/>
					<path d="M20 2L20 6" stroke="rgba(0,212,170,0.25)" stroke-width="1" stroke-linecap="round"/>
				</svg>
			</div>
			<h3 id="lpnw-home-tier-vip" class="lpnw-pricing-card__name">VIP</h3>
			<p class="lpnw-pricing-card__price">&pound;79.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Priority delivery ahead of Pro</li>
				<li>Off-market style deal alerts</li>
				<li>Monthly report and introductions</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$shop_url}">Get VIP</a>
		</article>
	</div>
	<p class="lpnw-home-pricing-teaser__foot"><a class="lpnw-btn lpnw-btn--secondary" href="{$pricing}">Compare all features</a></p>
</section>

<aside class="lpnw-cta-banner" aria-labelledby="lpnw-home-cta-heading">
	<h2 id="lpnw-home-cta-heading">Get the next deal in your inbox first</h2>
	<p>Create a free account in a minute, set your areas and alert types, and see Northwest opportunities as soon as we match them.</p>
	<a class="lpnw-btn lpnw-btn--primary" href="{$register}">Create your free account</a>
</aside>
HTML;
	}

	/**
	 * About page: product explanation, honest coverage, area stats, CTA.
	 *
	 * @return string HTML.
	 */
	public static function get_about_content(): string {
		$site     = esc_html( get_bloginfo( 'name' ) );
		$pricing  = esc_url( home_url( '/pricing/' ) );
		$contact  = esc_url( home_url( '/contact/' ) );
		$register = esc_url( wp_registration_url() );

		return <<<HTML
<div class="lpnw-page-about">

<h1>About {$site}</h1>

<p>{$site} is a property alert service for Northwest England. We monitor listings, match them to the areas and criteria you choose, and email you when something new fits. You get one place to watch the region instead of hopping between sites and spreadsheets.</p>

<h2>The problem</h2>

<p>If you invest or source deals in the Northwest, you already know the drill: open Rightmove, Zoopla, and whatever else you use, filter by postcode and price, and hope you did not miss a listing that went live while you were in a meeting. Speed matters, and manual checking does not scale.</p>

<h2>How we help</h2>

<p>Our systems scan on a fixed schedule (every fifteen minutes) and normalise what we find into a single database. When a property matches your rules, we can alert you straight away on paid plans, or include it in a weekly digest on the free tier. Your account dashboard pulls the same feed together so you are not reconciling half a dozen tabs.</p>

<h2>What we cover</h2>

<p>We focus on Northwest England: Greater Manchester, Merseyside, Lancashire, Cheshire, Cumbria, and the surrounding postcode areas we treat as the region (for example M, L, CH, WA, and the other prefixes we include in our filters). The market here still offers solid rental demand, development angles, and land opportunities if you see stock early enough.</p>

<p>Live numbers from our database:</p>

[lpnw_area_stats]

<h2>Our approach</h2>

<p>Today we monitor <strong>Rightmove</strong> listings for the Northwest. That is the core of what runs in production. We are adding more portal and data sources over time so the same alert rules can cover a wider picture without you doing the legwork twice.</p>

<p>We are careful about what we claim: alerts are based on what we have ingested and matched, not on every possible website. We improve parsers and coverage as we go. We are not solicitors or surveyors; we help you find leads faster, not replace your own checks.</p>

<h2>Try it</h2>

<p>Open a free account, set your areas and alert types, and see what lands in your digest. When the feed proves useful, upgrade for instant alerts and full filters.</p>

<p><a class="lpnw-btn lpnw-btn--primary" href="{$register}">Start free</a> <a class="lpnw-btn lpnw-btn--secondary" href="{$pricing}">View pricing</a> <a class="lpnw-btn lpnw-btn--secondary" href="{$contact}">Contact us</a></p>

</div>
HTML;
	}

	/**
	 * Pricing page: comparison table and FAQ.
	 *
	 * @return string HTML.
	 */
	public static function get_pricing_content(): string {
		$register = esc_url( wp_registration_url() );
		$shop_url = esc_url( home_url( '/shop/' ) );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_page = wc_get_page_permalink( 'shop' );
			if ( $shop_page ) {
				$shop_url = esc_url( $shop_page );
			}
		}

		return <<<HTML
<section class="lpnw-hero" aria-labelledby="lpnw-pricing-hero-heading">
	<div class="lpnw-hero__cityscape lpnw-hero__cityscape--pricing" aria-hidden="true">
		<svg class="lpnw-hero__skyline lpnw-hero__skyline--mid" viewBox="0 0 1600 200" preserveAspectRatio="xMidYMax slice" fill="none">
			<path d="M0 200V170L40 170L40 155L60 155L60 140L65 135L70 140L70 155L100 155L100 170L200 170L200 150L210 150L210 135L220 135L220 120L225 115L230 120L230 135L240 135L240 150L250 150L250 170L400 170L400 145L410 145L410 130L420 130L420 100L424 90L428 90L428 130L440 130L440 145L450 145L450 170L600 170L600 155L610 155L610 140L620 140L620 155L640 155L640 170L800 170L800 150L810 150L810 135L820 135L820 150L840 150L840 170L1000 170L1000 155L1010 155L1010 140L1020 140L1020 155L1040 155L1040 170L1200 170L1200 160L1220 160L1220 145L1240 145L1240 160L1260 160L1260 170L1400 170L1400 162L1420 162L1420 170L1600 170V200Z" fill="rgba(10,20,38,0.5)"/>
		</svg>
		<div class="lpnw-hero__glow"></div>
	</div>
	<h1 id="lpnw-pricing-hero-heading">Pricing</h1>
	<p>We scan Rightmove listings for Northwest England every 15 minutes, match them to your criteria where your plan allows, and email you. Three plans: a free weekly digest, Pro for full filters and alerts as listings are found, and Investor VIP if you want your alerts processed about half an hour before Pro subscribers.</p>
</section>

<section class="lpnw-pricing-section" id="lpnw-pricing-compare" aria-labelledby="lpnw-pricing-compare-title">
	<h2 id="lpnw-pricing-compare-title" class="lpnw-pricing-section__title">Compare plans</h2>
	<div class="lpnw-pricing-table-wrap">
		<table class="lpnw-pricing-table">
			<thead>
				<tr>
					<th scope="col">Feature</th>
					<th scope="col">Free</th>
					<th scope="col">Pro</th>
					<th scope="col">VIP</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th scope="row">Property alerts</th>
					<td>Weekly digest</td>
					<td>Instant or daily</td>
					<td>Priority (30 min ahead)</td>
				</tr>
				<tr>
					<th scope="row">Area filtering</th>
					<td>&mdash;</td>
					<td>&#10003;</td>
					<td>&#10003;</td>
				</tr>
				<tr>
					<th scope="row">Bedroom/price/type filters</th>
					<td>&mdash;</td>
					<td>&#10003;</td>
					<td>&#10003;</td>
				</tr>
				<tr>
					<th scope="row">Subscriber dashboard</th>
					<td>&mdash;</td>
					<td>&#10003;</td>
					<td>&#10003;</td>
				</tr>
				<tr>
					<th scope="row">Saved properties</th>
					<td>&mdash;</td>
					<td>&#10003;</td>
					<td>&#10003;</td>
				</tr>
				<tr>
					<th scope="row">Property map</th>
					<td>&mdash;</td>
					<td>&#10003;</td>
					<td>&#10003;</td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="lpnw-pricing">
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-pricing-tier-free">
			<h3 id="lpnw-pricing-tier-free" class="lpnw-pricing-card__name">Free</h3>
			<p class="lpnw-pricing-card__price">£0</p>
			<p class="lpnw-pricing-card__period">no charge</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Weekly email digest of Northwest property listings</li>
				<li>No dashboard, no filters, no instant alerts</li>
				<li>Useful if you want to see what we cover before paying</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$register}">Sign up free</a>
		</article>
		<article class="lpnw-pricing-card lpnw-pricing-card--featured" aria-labelledby="lpnw-pricing-tier-pro">
			<h3 id="lpnw-pricing-tier-pro" class="lpnw-pricing-card__name">Pro</h3>
			<p class="lpnw-pricing-card__price">£19.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Instant email alerts when properties match your criteria (or daily, your choice)</li>
				<li>Filter by area, bedrooms, price, property type, tenure, and features</li>
				<li>Dashboard for preferences and saved properties, including the property map</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--primary" href="{$shop_url}">Get Pro</a>
		</article>
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-pricing-tier-vip">
			<h3 id="lpnw-pricing-tier-vip" class="lpnw-pricing-card__name">Investor VIP</h3>
			<p class="lpnw-pricing-card__price">£79.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Everything in Pro</li>
				<li>Your alerts are processed about 30 minutes ahead of Pro when speed matters on competitive listings</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$shop_url}">Get Investor VIP</a>
		</article>
	</div>
</section>

<section class="lpnw-how-it-works" id="lpnw-pricing-faq" aria-labelledby="lpnw-pricing-faq-title">
	<h2 id="lpnw-pricing-faq-title" class="lpnw-how-it-works__title">Frequently asked questions</h2>
	<div class="lpnw-faq">
		<details class="lpnw-faq__item" open>
			<summary class="lpnw-faq__question">What exactly does this service do?</summary>
			<div class="lpnw-faq__answer"><p>We run an automated check on Rightmove listings for Northwest England roughly every 15 minutes. When a listing matches a paying subscriber&rsquo;s criteria, we send an email. Free accounts get a single weekly digest instead of instant or daily alerts, and they cannot narrow listings with our filters or use the dashboard.</p></div>
		</details>
		<details class="lpnw-faq__item">
			<summary class="lpnw-faq__question">How quickly will I get alerts?</summary>
			<div class="lpnw-faq__answer"><p>We scan every 15 minutes. On Pro and Investor VIP, you can get an email as soon as we detect a match (or choose a daily summary). Free tier is weekly only. Investor VIP alerts are queued about 30 minutes before the same matches go to Pro.</p></div>
		</details>
		<details class="lpnw-faq__item">
			<summary class="lpnw-faq__question">Can I cancel anytime?</summary>
			<div class="lpnw-faq__answer"><p>Yes. Cancel whenever you like, no questions asked. You keep access until the end of the billing period you have already paid for.</p></div>
		</details>
		<details class="lpnw-faq__item">
			<summary class="lpnw-faq__question">What areas do you cover?</summary>
			<div class="lpnw-faq__answer"><p>Northwest England: broadly Manchester, Liverpool, Lancashire, Cheshire, and Cumbria, using the postcode areas we support across the region.</p></div>
		</details>
		<details class="lpnw-faq__item">
			<summary class="lpnw-faq__question">Do I need a Rightmove account?</summary>
			<div class="lpnw-faq__answer"><p>No. We run the scans; you do not need to log in to Rightmove for this service.</p></div>
		</details>
		<details class="lpnw-faq__item">
			<summary class="lpnw-faq__question">Is my payment secure?</summary>
			<div class="lpnw-faq__answer"><p>Yes. Card payments go through Stripe. We never see or store your full card details on our site.</p></div>
		</details>
	</div>
</section>
HTML;
	}

	/**
	 * Contact page.
	 *
	 * @return string HTML.
	 */
	public static function get_contact_content(): string {
		return <<<'HTML'
<div class="lpnw-page-contact">

<h1>Contact</h1>

<p>For billing queries, technical issues, data questions, or partnership enquiries, use the form below. We aim to reply within two working days. If you already have an account, include the email on your subscription so we can find you quickly.</p>

[lpnw_contact_form]

</div>
HTML;
	}

	/**
	 * Privacy policy (sole trader, UK GDPR).
	 *
	 * @return string HTML.
	 */
	public static function get_privacy_content(): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url  = esc_url( home_url( '/' ) );
		$contact   = esc_url( home_url( '/contact/' ) );

		return <<<HTML
<div class="lpnw-page-legal">

<h1>Privacy policy</h1>
<p><strong>Last updated:</strong> 1 April 2026</p>

<p>{$site_name} ("we", "us", "our") operates {$site_url}. This policy describes how we collect, use, and store personal data when you use the site, register, or subscribe. The business is operated as a <strong>sole trader</strong> based in England. For UK GDPR and the Data Protection Act 2018, we are the data controller.</p>

<h2>What data we collect</h2>
<ul>
	<li><strong>Identity and contact:</strong> name and email address when you register, subscribe, or contact us.</li>
	<li><strong>Alert preferences:</strong> areas, postcodes, price ranges, property types, alert frequency, saved properties, and similar settings stored in your account.</li>
	<li><strong>Account and billing identifiers:</strong> WordPress user ID, subscription status, and transaction references. Card numbers are processed by Stripe; we do not hold your full card details on our server.</li>
	<li><strong>Technical data:</strong> IP address, browser type, timestamps, and similar data in server logs and security tools (for example Wordfence).</li>
</ul>

<h2>Why we use your data</h2>
<p>We use personal data to provide the service: matching public property records to your criteria and sending email alerts. We also use it to manage your account, take payments, respond to enquiries, comply with law, and protect the site from abuse.</p>
<p>Lawful bases include performance of a contract (subscriptions and registered accounts), legitimate interests (operating and securing the platform, limited analytics), and consent where we ask for it clearly (for example optional marketing).</p>

<h2>How we store it</h2>
<p>Account and preference data are stored in the WordPress database on <strong>UK-based hosting</strong> (20i). Access is restricted to what is needed to run the site. Backups may be held by our hosting or backup provider under their terms.</p>

<h2>Third parties</h2>
<ul>
	<li><strong>Stripe</strong> processes card payments for WooCommerce. Stripe receives the payment details you enter at checkout.</li>
	<li><strong>Mautic</strong> (marketing.land-property-northwest.co.uk) sends transactional and marketing emails and may record engagement metrics (for example opens or clicks) according to your settings and our configuration.</li>
	<li><strong>Hosting, email transport, and security vendors</strong> process data only as needed to deliver the service.</li>
</ul>
<p>We use processors under terms that require them to protect personal data appropriately.</p>

<h2>Cookies</h2>
<p>WordPress sets cookies to keep you logged in when you use an account. WooCommerce may set cookies for cart and checkout. A cookie notice plugin may store your consent choice. You can control cookies through your browser settings.</p>

<h2>Retention</h2>
<p>We keep account and billing records while your relationship is active and for a period afterwards where the law requires (for example tax or dispute resolution). You can ask for erasure sooner where the law allows.</p>

<h2>Your rights</h2>
<p>Under UK GDPR you may have the right to access, rectify, erase, restrict, or object to processing of your personal data, and in some cases the right to data portability. You may lodge a complaint with the <strong>Information Commissioner's Office (ICO)</strong>. We encourage you to contact us first so we can resolve issues quickly.</p>

<h2>Contact details</h2>
<p>For privacy requests or questions about this policy, use our <a href="{$contact}">contact page</a> or the email address shown in the site footer. You may also write to the postal address given in the footer.</p>

</div>
HTML;
	}

	/**
	 * Terms of service.
	 *
	 * @return string HTML.
	 */
	public static function get_terms_content(): string {
		$site_name   = esc_html( get_bloginfo( 'name' ) );
		$site_url    = esc_url( home_url( '/' ) );
		$privacy_id  = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy     = $privacy_id ? esc_url( get_permalink( $privacy_id ) ) : esc_url( home_url( '/privacy-policy/' ) );
		$contact     = esc_url( home_url( '/contact/' ) );

		return <<<HTML
<div class="lpnw-page-legal">

<h1>Terms of service</h1>
<p><strong>Last updated:</strong> 1 April 2026</p>

<p>These terms govern your use of {$site_name} at {$site_url}. By registering, subscribing, or using paid features, you agree to them. If you do not agree, do not use the service.</p>

<h2>Subscriptions</h2>
<p>Paid plans are recurring subscriptions sold through WooCommerce. Prices are as shown at checkout and include VAT where applicable. By subscribing you authorise us and our payment processor to charge your payment method on each renewal until you cancel.</p>

<h2>Auto-renewal</h2>
<p>Subscriptions renew automatically for the same term unless you cancel before the renewal date. Renewal charges use the payment method you provided unless you update it.</p>

<h2>Cancellation</h2>
<p>You may <strong>cancel at any time</strong> from your account area or using the instructions in your receipt or subscription emails. Cancellation stops future charges. You normally retain access until the end of the period you have already paid for, unless we tell you otherwise at purchase.</p>

<h2>Accuracy and disclaimer</h2>
<p>We aggregate <strong>public and open data</strong> from third-party sources. We do not guarantee that every record is complete, timely, or error-free. Alerts are for information only. Nothing on this site is financial, investment, tax, or legal advice. You must carry out your own due diligence and take professional advice where appropriate.</p>

<h2>Acceptable use</h2>
<p>You must not misuse the service: for example by attempting to bypass access controls, overloading our systems, reselling raw alert feeds without agreement, or using the site for unlawful purposes. We may suspend or close accounts that breach these terms.</p>

<h2>Limitation of liability</h2>
<p>To the fullest extent permitted by law, we exclude liability for indirect or consequential loss, loss of profit, or loss of opportunity arising from your use of the service or reliance on alert content. Our total liability relating to these terms is capped at the fees you paid us in the twelve months before the claim, except where English law does not allow such a limit.</p>

<h2>Privacy</h2>
<p>Our use of personal data is described in the <a href="{$privacy}">privacy policy</a>.</p>

<h2>Governing law</h2>
<p>These terms are governed by the laws of <strong>England and Wales</strong>. The courts of England and Wales have exclusive jurisdiction, subject to any mandatory consumer rights that apply where you live.</p>

<h2>Changes</h2>
<p>We may update these terms by posting a new version on the site. Where changes materially affect paid subscribers, we will give notice by email or through your account where appropriate.</p>

<h2>Contact</h2>
<p>Questions about these terms: <a href="{$contact}">contact us</a>.</p>

</div>
HTML;
	}
}
