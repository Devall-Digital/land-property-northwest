<?php
/**
 * Zoopla property portal feed.
 *
 * Monitors Zoopla search results for new property listings
 * across Northwest England.
 *
 * Zoopla uses Cloudflare protection which may block server-side requests.
 * This feed class attempts multiple strategies:
 * 1. Direct HTML search page fetch + JSON extraction
 * 2. Graceful degradation if Cloudflare blocks us
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Portal_Zoopla extends LPNW_Feed_Base {

	private const BASE_URL = 'https://www.zoopla.co.uk';

	/**
	 * Zoopla uses URL path slugs for locations.
	 * We search each NW area separately.
	 */
	private const NW_AREA_SLUGS = array(
		'manchester'  => 'Manchester',
		'liverpool'   => 'Liverpool',
		'preston'     => 'Preston',
		'blackpool'   => 'Blackpool',
		'blackburn'   => 'Blackburn',
		'bolton'      => 'Bolton',
		'bury'        => 'Bury',
		'oldham'      => 'Oldham',
		'rochdale'    => 'Rochdale',
		'salford'     => 'Salford',
		'stockport'   => 'Stockport',
		'wigan'       => 'Wigan',
		'warrington'  => 'Warrington',
		'chester'     => 'Chester',
		'lancaster'   => 'Lancaster',
		'burnley'     => 'Burnley',
		'carlisle'    => 'Carlisle',
	);

	private bool $cloudflare_blocked = false;

	public function get_source_name(): string {
		return 'zoopla';
	}

	protected function fetch(): array {
		$all_results = array();

		foreach ( self::NW_AREA_SLUGS as $slug => $name ) {
			if ( $this->cloudflare_blocked ) {
				error_log( 'LPNW Zoopla feed: Cloudflare blocking detected, stopping further requests.' );
				break;
			}

			$sale_results = $this->fetch_area( $slug, 'for-sale' );
			$all_results  = array_merge( $all_results, $sale_results );

			usleep( 2000000 );

			if ( $this->cloudflare_blocked ) {
				break;
			}

			$rent_results = $this->fetch_area( $slug, 'to-rent' );
			$all_results  = array_merge( $all_results, $rent_results );

			usleep( 2000000 );
		}

		return $all_results;
	}

	/**
	 * Fetch newest listings for a single area.
	 *
	 * @param string $slug    Zoopla area slug.
	 * @param string $section for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_area( string $slug, string $section ): array {
		$url = sprintf(
			'%s/%s/property/%s/?results_sort=newest_listings&search_source=home',
			self::BASE_URL,
			$section,
			rawurlencode( $slug )
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 30,
			'headers' => array(
				'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-GB,en;q=0.9',
				'Accept-Encoding' => 'gzip, deflate',
				'Connection'      => 'keep-alive',
				'Cache-Control'   => 'no-cache',
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'LPNW Zoopla feed error for ' . $slug . ': ' . $response->get_error_message() );
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 403 === $code || 503 === $code ) {
			$this->cloudflare_blocked = true;
			error_log( 'LPNW Zoopla feed: HTTP ' . $code . ' (likely Cloudflare). Feed paused.' );
			return array();
		}

		if ( 200 !== $code ) {
			error_log( 'LPNW Zoopla feed HTTP ' . $code . ' for ' . $slug );
			return array();
		}

		$body = wp_remote_retrieve_body( $response );

		if ( $this->is_cloudflare_challenge( $body ) ) {
			$this->cloudflare_blocked = true;
			error_log( 'LPNW Zoopla feed: Cloudflare challenge detected for ' . $slug );
			return array();
		}

		$listings = $this->extract_listings( $body, $section );

		return $listings;
	}

	/**
	 * Check if response body is a Cloudflare challenge page.
	 */
	private function is_cloudflare_challenge( string $body ): bool {
		if ( stripos( $body, 'cf-browser-verification' ) !== false ) {
			return true;
		}
		if ( stripos( $body, 'Checking your browser' ) !== false ) {
			return true;
		}
		if ( stripos( $body, 'cf_chl_opt' ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * Extract listing data from Zoopla HTML search results.
	 *
	 * Zoopla embeds listing data as JSON in __NEXT_DATA__ script tags
	 * or as structured data within the page.
	 *
	 * @param string $html    Raw HTML.
	 * @param string $section for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_listings( string $html, string $section ): array {
		$listings = array();

		if ( preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*type="application\/json"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			$next_data = json_decode( $matches[1], true );
			if ( $next_data ) {
				$results = $next_data['props']['pageProps']['regularListingsFormatted'] ?? array();
				if ( empty( $results ) ) {
					$results = $next_data['props']['pageProps']['listings'] ?? array();
				}

				foreach ( $results as $listing ) {
					$listing['_section'] = $section;
					$listings[] = $listing;
				}
			}
		}

		if ( empty( $listings ) ) {
			$listings = $this->extract_from_html_dom( $html, $section );
		}

		return $listings;
	}

	/**
	 * Fallback: parse listing cards from HTML DOM if JSON extraction fails.
	 *
	 * @param string $html    Raw HTML.
	 * @param string $section for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_from_html_dom( string $html, string $section ): array {
		$listings = array();

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new \DOMXPath( $dom );

		$cards = $xpath->query( "//div[@data-testid='search-result'] | //article[contains(@class, 'listing')] | //div[contains(@class, 'srp-listing')]" );

		if ( ! $cards || 0 === $cards->length ) {
			return $listings;
		}

		foreach ( $cards as $card ) {
			$listing = $this->parse_card_node( $card, $xpath, $section );
			if ( ! empty( $listing['address'] ) ) {
				$listings[] = $listing;
			}
		}

		return $listings;
	}

	/**
	 * Parse a single listing card from the DOM.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_card_node( \DOMNode $card, \DOMXPath $xpath, string $section ): array {
		$address_node = $xpath->query( ".//address | .//*[@data-testid='listing-card-address'] | .//h2 | .//a[contains(@class, 'address')]", $card );
		$price_node   = $xpath->query( ".//*[@data-testid='listing-card-price'] | .//*[contains(@class, 'price')] | .//p[contains(@class, 'price')]", $card );
		$link_node    = $xpath->query( ".//a[contains(@href, '/details/')]", $card );
		$beds_node    = $xpath->query( ".//*[contains(@class, 'bed')] | .//*[@data-testid='beds']", $card );
		$type_node    = $xpath->query( ".//*[contains(@class, 'property-type')] | .//*[@data-testid='listing-card-property-type']", $card );

		$address = $address_node->length ? trim( $address_node->item( 0 )->textContent ) : '';
		$price   = $price_node->length ? trim( $price_node->item( 0 )->textContent ) : '';
		$link    = $link_node->length ? $link_node->item( 0 )->getAttribute( 'href' ) : '';
		$beds    = $beds_node->length ? trim( $beds_node->item( 0 )->textContent ) : '';
		$type    = $type_node->length ? trim( $type_node->item( 0 )->textContent ) : '';

		$price_clean = preg_replace( '/[^0-9]/', '', $price );

		if ( $link && ! str_starts_with( $link, 'http' ) ) {
			$link = self::BASE_URL . $link;
		}

		$listing_id = '';
		if ( preg_match( '/\/details\/(\d+)/', $link, $m ) ) {
			$listing_id = $m[1];
		}

		return array(
			'address'    => $address,
			'price'      => $price_clean,
			'source_url' => $link,
			'listing_id' => $listing_id,
			'beds'       => $beds,
			'type'       => $type,
			'raw_price'  => $price,
			'_section'   => $section,
		);
	}

	/**
	 * @param array<string, mixed> $raw_item Listing data (JSON or DOM-extracted).
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$address = '';
		if ( isset( $raw_item['address'] ) && is_string( $raw_item['address'] ) ) {
			$address = sanitize_text_field( $raw_item['address'] );
		} elseif ( isset( $raw_item['displayAddress'] ) ) {
			$address = sanitize_text_field( $raw_item['displayAddress'] );
		} elseif ( isset( $raw_item['title'] ) ) {
			$address = sanitize_text_field( $raw_item['title'] );
		}

		if ( empty( $address ) ) {
			return array();
		}

		$postcode = '';
		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $m ) ) {
			$postcode = strtoupper( trim( $m[1] ) );
		}

		if ( ! empty( $postcode ) && ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		$price = 0;
		if ( isset( $raw_item['pricing']['value'] ) ) {
			$price = absint( $raw_item['pricing']['value'] );
		} elseif ( isset( $raw_item['price'] ) && is_numeric( $raw_item['price'] ) ) {
			$price = absint( $raw_item['price'] );
		} elseif ( isset( $raw_item['price'] ) ) {
			$price = absint( preg_replace( '/[^0-9]/', '', (string) $raw_item['price'] ) );
		}

		$listing_id = sanitize_text_field(
			(string) ( $raw_item['listingId'] ?? $raw_item['listing_id'] ?? $raw_item['id'] ?? '' )
		);

		if ( empty( $listing_id ) ) {
			$listing_id = md5( $address . $price );
		}

		$url = '';
		if ( ! empty( $raw_item['listingUri'] ) ) {
			$url = self::BASE_URL . $raw_item['listingUri'];
		} elseif ( ! empty( $raw_item['source_url'] ) ) {
			$url = $raw_item['source_url'];
		} elseif ( ! empty( $raw_item['details_url'] ) ) {
			$url = $raw_item['details_url'];
		}

		$lat  = null;
		$lng  = null;
		if ( isset( $raw_item['location']['coordinates']['latitude'] ) ) {
			$lat = floatval( $raw_item['location']['coordinates']['latitude'] );
			$lng = floatval( $raw_item['location']['coordinates']['longitude'] );
		} elseif ( isset( $raw_item['latitude'] ) ) {
			$lat = floatval( $raw_item['latitude'] );
			$lng = floatval( $raw_item['longitude'] ?? 0 );
		}

		$beds = '';
		if ( isset( $raw_item['attributes']['bedrooms'] ) ) {
			$beds = absint( $raw_item['attributes']['bedrooms'] );
		} elseif ( isset( $raw_item['num_bedrooms'] ) ) {
			$beds = absint( $raw_item['num_bedrooms'] );
		} elseif ( isset( $raw_item['beds'] ) ) {
			$beds = absint( preg_replace( '/[^0-9]/', '', (string) $raw_item['beds'] ) );
		}

		$prop_type = sanitize_text_field(
			$raw_item['propertyType'] ?? $raw_item['property_type'] ?? $raw_item['type'] ?? ''
		);

		$section = ( $raw_item['_section'] ?? '' ) === 'to-rent' ? 'To let' : 'For sale';

		$desc_parts = array();
		if ( $beds ) {
			$desc_parts[] = $beds . ' bed';
		}
		if ( $prop_type ) {
			$desc_parts[] = strtolower( $prop_type );
		}
		$desc_parts[] = $section;
		if ( ! empty( $raw_item['summary'] ) ) {
			$desc_parts[] = wp_trim_words( sanitize_text_field( $raw_item['summary'] ), 20, '...' );
		}

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => 'zp-' . $listing_id,
			'address'       => $address,
			'postcode'      => $postcode,
			'latitude'      => $lat,
			'longitude'     => $lng,
			'price'         => $price > 0 ? $price : null,
			'property_type' => $prop_type,
			'description'   => implode( '. ', $desc_parts ),
			'source_url'    => esc_url_raw( $url ),
			'raw_data'      => $raw_item,
		);
	}
}
