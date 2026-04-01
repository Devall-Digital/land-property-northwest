<?php
/**
 * SEO landing pages for NW postcode areas (property alerts by city / region).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Area definitions, HTML bodies, and one-off page creation.
 */
class LPNW_Area_Pages {

	/**
	 * NW areas: prefix => slug, labels, map centre, and intro copy.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_definitions(): array {
		return array(
			'M' => array(
				'slug'       => 'property-alerts-manchester',
				'area_name'  => 'Manchester',
				'region'     => 'Greater Manchester',
				'tagline'    => 'the UK\'s second city and the heart of the NW property market',
				'map'        => array( 'lat' => 53.4808, 'lng' => -2.2426, 'zoom' => 11 ),
				'excerpt'    => 'Instant property alerts for Greater Manchester. See new Rightmove-style listings on a map and sign up with M postcodes pre-selected.',
				'intros'     => array(
					'Greater Manchester pulls serious volume across buy-to-let, resale, and small-scale development, from the city centre out along the tram corridors and mature suburbs.',
					'Competition on well-priced stock is still tight; seeing new M-postcode listings as they land helps you respond before the open-day rush.',
					'We normalise portal and other feeds for Northwest England so you can monitor Manchester alongside the rest of the region if you choose.',
				),
			),
			'L' => array(
				'slug'       => 'property-alerts-liverpool',
				'area_name'  => 'Liverpool',
				'region'     => 'Merseyside',
				'tagline'    => 'a city with strong rental yields and ongoing regeneration',
				'map'        => array( 'lat' => 53.4084, 'lng' => -2.9916, 'zoom' => 11 ),
				'excerpt'    => 'New listing alerts for Liverpool and Merseyside L postcodes. Map view, sample properties, and signup with your area ticked.',
				'intros'     => array(
					'Liverpool mixes strong rental pockets with long-running regeneration around the waterfront, knowledge quarter, and inner suburbs.',
					'Yields still attract landlords in several L districts, while family housing and refurbishment plays remain active in outer wards.',
					'Our alerts cover fresh L-postcode records as they hit the database, not after you have manually refreshed the portals.',
				),
			),
			'BL' => array(
				'slug'       => 'property-alerts-bolton',
				'area_name'  => 'Bolton',
				'region'     => 'Greater Manchester',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.5780, 'lng' => -2.4290, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Bolton (BL). Track new listings and related signals across Greater Manchester\'s north.',
				'intros'     => array(
					'Bolton offers a spread of terraced stock, post-war estates, and greener fringe villages, often priced below central Manchester.',
					'Investors compare Bolton with Bury and Wigan on yield and refurbishment costs, so speed on fresh BL listings still matters.',
					'Use this page to preview recent BL activity and route straight into signup with Bolton selected.',
				),
			),
			'OL' => array(
				'slug'       => 'property-alerts-oldham',
				'area_name'  => 'Oldham',
				'region'     => 'Greater Manchester',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.5409, 'lng' => -2.1184, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Oldham (OL) and surrounding Greater Manchester.',
				'intros'     => array(
					'Oldham combines urban terraces, former mill towns, and Pennine-edge locations with different buyer profiles.',
					'Development and buy-to-let interest tends to cluster on transport links toward Manchester and on larger family homes.',
					'Subscribe with OL ticked to hear when matching stock appears across our Northwest pipeline.',
				),
			),
			'SK' => array(
				'slug'       => 'property-alerts-stockport',
				'area_name'  => 'Stockport',
				'region'     => 'Greater Manchester',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.4106, 'lng' => -2.1577, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Stockport (SK): south Manchester corridor listings and map.',
				'intros'     => array(
					'Stockport sits on a busy commuter axis with a mix of Victorian suburbs, interwar semis, and newer schemes toward Cheshire.',
					'Demand often holds up for good schools and rail access, so new SK listings can move quickly when priced sensibly.',
					'Alerts help you watch SK postcodes without running manual searches on every portal each morning.',
				),
			),
			'WN' => array(
				'slug'       => 'property-alerts-wigan',
				'area_name'  => 'Wigan',
				'region'     => 'Greater Manchester',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.5450, 'lng' => -2.6325, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Wigan (WN) and borough.',
				'intros'     => array(
					'Wigan borough stretches from the town centre to semi-rural parishes, with affordable stock relative to inner Manchester.',
					'Buyers range from first-time owners to investors targeting yield in traditional terraces.',
					'WN-postcode alerts let you react when suitable lots appear in the feed we already run for the Northwest.',
				),
			),
			'WA' => array(
				'slug'       => 'property-alerts-warrington',
				'area_name'  => 'Warrington',
				'region'     => 'Cheshire',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.3900, 'lng' => -2.5970, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Warrington (WA): Cheshire and M62 corridor.',
				'intros'     => array(
					'Warrington benefits from motorway and rail links between Liverpool and Manchester, with logistics and commuter demand supporting housing.',
					'The market spans new-build clusters, 1930s suburbs, and outlying villages toward Cheshire green belt.',
					'WA-postcode alerts suit anyone underwriting stock along that corridor without missing quieter drops.',
				),
			),
			'PR' => array(
				'slug'       => 'property-alerts-preston',
				'area_name'  => 'Preston',
				'region'     => 'Lancashire',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.7632, 'lng' => -2.7031, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Preston (PR) and Central Lancashire.',
				'intros'     => array(
					'Preston anchors Central Lancashire with the university, public sector employment, and improving city-centre residential stock.',
					'Investors often weigh Preston against Blackburn and the Fylde coast on price and tenant depth.',
					'Fresh PR listings surface here as soon as our ingest pipeline records them.',
				),
			),
			'BB' => array(
				'slug'       => 'property-alerts-blackburn',
				'area_name'  => 'Blackburn',
				'region'     => 'Lancashire',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.7488, 'lng' => -2.4828, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Blackburn (BB) and East Lancashire.',
				'intros'     => array(
					'Blackburn and Darwen offer some of the lower entry prices in Lancashire, with steady rental demand in many wards.',
					'Stock is still dominated by terraces and cheaper semis, plus pockets of industrial land and conversion opportunities.',
					'BB-postcode alerts keep you aligned with new supply without refreshing multiple sites by hand.',
				),
			),
			'FY' => array(
				'slug'       => 'property-alerts-blackpool',
				'area_name'  => 'Blackpool',
				'region'     => 'Lancashire',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.8175, 'lng' => -3.0357, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Blackpool (FY) and the Fylde coast.',
				'intros'     => array(
					'Blackpool and the wider Fylde coast trade on tourism, retirement moves, and investor interest in holiday lets and terraces.',
					'Seasonality and condition vary street by street, so early sight of new FY listings helps shortlist faster.',
					'We tag FY postcodes the same way as inland Northwest areas for consistent alerts.',
				),
			),
			'LA' => array(
				'slug'       => 'property-alerts-lancaster',
				'area_name'  => 'Lancaster',
				'region'     => 'Lancashire and Cumbria',
				'tagline'    => '',
				'map'        => array( 'lat' => 54.0466, 'lng' => -2.8007, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Lancaster (LA): Morecambe Bay, Lune valley, and north Lancashire.',
				'intros'     => array(
					'Lancaster and the surrounding LA patch cover the city, Morecambe, rural parishes, and links north toward the Lakes.',
					'The market mixes student HMOs, coastal second homes, and agricultural or edge-of-settlement opportunities.',
					'LA-postcode alerts help you monitor that geography inside the same Northwest subscription.',
				),
			),
			'CH' => array(
				'slug'       => 'property-alerts-chester',
				'area_name'  => 'Chester',
				'region'     => 'Cheshire',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.1934, 'lng' => -2.8931, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Chester (CH) and west Cheshire.',
				'intros'     => array(
					'Chester and the CH area include the walled city, Wirral-facing suburbs, and rural Cheshire with strong commuter ties.',
					'Price points span premium family housing, classic terraces, and occasional development land.',
					'Use CH filters here to preview listings before you commit preferences in your account.',
				),
			),
			'CW' => array(
				'slug'       => 'property-alerts-crewe',
				'area_name'  => 'Crewe',
				'region'     => 'Cheshire',
				'tagline'    => '',
				'map'        => array( 'lat' => 53.0990, 'lng' => -2.4419, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Crewe (CW) and east Cheshire.',
				'intros'     => array(
					'Crewe and east Cheshire sit on the West Coast main line with industrial and logistics employers nearby.',
					'Housing ranges from affordable CW terraces to villages toward Nantwich and the Staffordshire border.',
					'CW-postcode alerts suit buyers tracking that rail and employment axis.',
				),
			),
			'CA' => array(
				'slug'       => 'property-alerts-carlisle',
				'area_name'  => 'Carlisle',
				'region'     => 'Cumbria',
				'tagline'    => '',
				'map'        => array( 'lat' => 54.8951, 'lng' => -2.9382, 'zoom' => 11 ),
				'excerpt'    => 'Property alerts for Carlisle (CA) and north Cumbria.',
				'intros'     => array(
					'Carlisle is the retail and public-sector hub for north Cumbria, with stock stretching toward the Scottish border and the Pennines.',
					'Investors and developers watch CA postcodes for yields, land, and refurbishment plays away from southern hotspots.',
					'Our Northwest coverage includes CA alongside the Manchester and Liverpool cores.',
				),
			),
		);
	}

	/**
	 * Registration URL that sends new users to preferences with this area highlighted.
	 *
	 * @param string $prefix NW outward code (e.g. M, CH).
	 */
	public static function get_signup_url_for_prefix( string $prefix ): string {
		$p = strtoupper( trim( $prefix ) );
		if ( '' === $p || ! in_array( $p, LPNW_NW_POSTCODES, true ) ) {
			return esc_url( wp_registration_url() );
		}
		$prefs_url = add_query_arg( 'lpnw_area', $p, home_url( '/preferences/' ) );
		return esc_url(
			add_query_arg(
				'redirect_to',
				rawurlencode( $prefs_url ),
				wp_registration_url()
			)
		);
	}

	/**
	 * Page title for a given prefix (used when creating pages).
	 */
	public static function get_page_title( string $prefix ): string {
		$defs = self::get_definitions();
		$p    = strtoupper( trim( $prefix ) );
		if ( ! isset( $defs[ $p ] ) ) {
			return '';
		}
		/* translators: %s: area name (e.g. Manchester) */
		return sprintf( __( 'Property alerts for %s', 'lpnw-alerts' ), $defs[ $p ]['area_name'] );
	}

	/**
	 * Marketing HTML for one area (shortcodes preserved).
	 *
	 * @param string $prefix NW outward code.
	 * @return string HTML or empty if unknown prefix.
	 */
	public static function get_area_page_content( string $prefix ): string {
		$defs = self::get_definitions();
		$p    = strtoupper( trim( $prefix ) );
		if ( ! isset( $defs[ $p ] ) ) {
			return '';
		}

		$d         = $defs[ $p ];
		$name      = $d['area_name'];
		$region    = $d['region'];
		$map       = $d['map'];
		$signup    = self::get_signup_url_for_prefix( $p );
		$tag_block = '';
		if ( ! empty( $d['tagline'] ) ) {
			$tagline_esc = esc_html( (string) $d['tagline'] );
			$tag_block   = '<p class="lpnw-area-landing__tagline">' . $tagline_esc . '</p>';
		}

		$intro_html = '';
		foreach ( $d['intros'] as $sentence ) {
			$intro_html .= '<p class="lpnw-area-landing__intro">' . esc_html( (string) $sentence ) . '</p>';
		}

		$region_esc = esc_html( $region );
		$name_esc   = esc_html( $name );
		$heading    = sprintf(
			/* translators: %s: area name */
			esc_html__( 'Property alerts for %s', 'lpnw-alerts' ),
			$name_esc
		);

		$lat   = esc_attr( (string) $map['lat'] );
		$lng   = esc_attr( (string) $map['lng'] );
		$zoom  = esc_attr( (string) $map['zoom'] );
		$pref  = esc_attr( $p );

		return <<<HTML
<section class="lpnw-area-landing lpnw-area-landing--{$pref}" aria-labelledby="lpnw-area-landing-heading-{$pref}">
	<header class="lpnw-area-landing__hero">
		<h1 id="lpnw-area-landing-heading-{$pref}">{$heading}</h1>
		<p class="lpnw-area-landing__region"><strong>{$region_esc}</strong> &middot; {$name_esc} postcode area ({$pref})</p>
		{$tag_block}
	</header>
	<div class="lpnw-area-landing__body">
		{$intro_html}
	</div>
</section>

<section class="lpnw-area-landing__feed" aria-labelledby="lpnw-area-feed-title-{$pref}">
	<h2 id="lpnw-area-feed-title-{$pref}" class="lpnw-pricing-section__title">Recent listings in this area</h2>
	<p class="lpnw-area-landing__feed-lead">Sample of six recent Rightmove-sourced records with {$name_esc} postcodes from our Northwest database.</p>
	<div class="lpnw-area-landing__properties">
		[lpnw_latest_properties source="rightmove" postcode_prefix="{$pref}" limit="6"]
	</div>
</section>

<section class="lpnw-area-landing__map" aria-labelledby="lpnw-area-map-title-{$pref}">
	<h2 id="lpnw-area-map-title-{$pref}" class="lpnw-pricing-section__title">Map: {$name_esc}</h2>
	<p>Interactive map centred on {$name_esc}, showing geocoded properties in the {$pref} area (Rightmove by default; change source in the control).</p>
	[lpnw_property_map source="rightmove" postcode_prefix="{$pref}" lat="{$lat}" lng="{$lng}" zoom="{$zoom}" height="420px"]
</section>

<aside class="lpnw-area-landing__cta lpnw-cta-banner" aria-labelledby="lpnw-area-cta-heading-{$pref}">
	<h2 id="lpnw-area-cta-heading-{$pref}">Get {$name_esc} alerts in your inbox</h2>
	<p>Create a free account and we will take you to preferences with the {$name_esc} ({$pref}) area ready to select, so you only hear about deals in the patch you care about.</p>
	<p><a class="lpnw-btn lpnw-btn--primary" href="{$signup}">Sign up for {$name_esc} property alerts</a></p>
</aside>
HTML;
	}

	/**
	 * Create published WordPress pages for all 14 NW areas (idempotent by slug).
	 *
	 * @return array{created: array<int, int>, skipped: array<int, string>, errors: array<int, string>}
	 */
	public static function create_area_pages(): array {
		$created = array();
		$skipped = array();
		$errors  = array();

		$author_id = self::resolve_default_author_id();

		foreach ( self::get_definitions() as $prefix => $def ) {
			$slug = (string) $def['slug'];
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof WP_Post ) {
				$skipped[] = $slug;
				continue;
			}

			$title   = self::get_page_title( $prefix );
			$content = self::get_area_page_content( $prefix );
			if ( '' === $title || '' === $content ) {
				$errors[] = $slug;
				continue;
			}

			$post_id = wp_insert_post(
				wp_slash(
					array(
						'post_title'   => $title,
						'post_name'    => $slug,
						'post_content' => $content,
						'post_excerpt' => (string) $def['excerpt'],
						'post_status'  => 'publish',
						'post_type'    => 'page',
						'post_author'  => $author_id,
					)
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$errors[] = $slug . ': ' . $post_id->get_error_message();
				continue;
			}

			$created[] = (int) $post_id;
		}

		return array(
			'created' => $created,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * @return int User ID to attribute new pages to.
	 */
	private static function resolve_default_author_id(): int {
		$users = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
			)
		);
		if ( ! empty( $users ) && isset( $users[0]->ID ) ) {
			return (int) $users[0]->ID;
		}
		return 1;
	}
}
