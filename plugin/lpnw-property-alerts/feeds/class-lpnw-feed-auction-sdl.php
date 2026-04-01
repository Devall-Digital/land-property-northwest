<?php
/**
 * SDL Property Auctions data feed.
 *
 * Loads property URLs from the public property sitemap (primary). If that fails,
 * attempts discovery pages (/properties/, /auctions/, /lots/). Fetches each listing
 * HTML with browser-like headers and parses guide price, dates, and features.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_SDL extends LPNW_Feed_Base {

	private const SITEMAP_URL = 'https://www.sdlauctions.co.uk/property-sitemap.xml';

	private const SITE_ORIGIN = 'https://www.sdlauctions.co.uk';

	/**
	 * Fallback listing pages (often 404; logged for diagnosis).
	 *
	 * @var array<int, string>
	 */
	private const DISCOVERY_PAGE_URLS = array(
		'https://www.sdlauctions.co.uk/properties/',
		'https://www.sdlauctions.co.uk/auctions/',
		'https://www.sdlauctions.co.uk/lots/',
	);

	private const MAX_PROPERTY_PAGES = 200;

	/**
	 * Browser User-Agent strings (aligned with Rightmove feed rotation).
	 *
	 * @var array<int, string>
	 */
	private const BROWSER_USER_AGENTS = array(
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
	);

	public function get_source_name(): string {
		return 'auction_sdl';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch(): array {
		$out = array();

		try {
			$urls = $this->fetch_property_urls_from_sitemap();
			if ( empty( $urls ) ) {
				$this->lpnw_feed_log( 'discovery', self::SITEMAP_URL, 0, 0, 'sitemap yielded no URLs; trying discovery pages' );
				$urls = $this->fetch_property_urls_from_discovery_pages();
			}

			if ( empty( $urls ) ) {
				return $out;
			}

			foreach ( $urls as $url ) {
				try {
					$response = wp_remote_get( $url, $this->lpnw_http_args( self::SITE_ORIGIN . '/' ) );

					if ( is_wp_error( $response ) ) {
						$this->lpnw_feed_log( 'property', $url, 0, 0, 'WP_Error: ' . $response->get_error_message() );
						continue;
					}

					$code = (int) wp_remote_retrieve_response_code( $response );
					$html = is_string( wp_remote_retrieve_body( $response ) ) ? wp_remote_retrieve_body( $response ) : '';
					$len  = strlen( $html );
					$this->lpnw_feed_log( 'property', $url, $code, $len, 'property page fetch' );

					if ( $code < 200 || $code >= 300 ) {
						continue;
					}

					$lot = $this->parse_property_html( $html, $url );
					if ( ! empty( $lot['address'] ) ) {
						$out[] = $lot;
					} elseif ( $len > 400 ) {
						$this->lpnw_log_html_structure_hint( $url, $html, $code, $len );
					}
				} catch ( \Throwable $e ) {
					$this->lpnw_feed_log( 'property', $url, 0, 0, 'lot error: ' . $e->getMessage() );
				}
			}
		} catch ( \Throwable $e ) {
			$this->lpnw_feed_log( 'feed', self::SITEMAP_URL, 0, 0, 'feed error: ' . $e->getMessage() );
		}

		return $out;
	}

	/**
	 * @return array<int, string>
	 */
	private function fetch_property_urls_from_sitemap(): array {
		$response = wp_remote_get( self::SITEMAP_URL, $this->lpnw_http_args( self::SITE_ORIGIN . '/' ) );

		if ( is_wp_error( $response ) ) {
			$this->lpnw_feed_log( 'sitemap', self::SITEMAP_URL, 0, 0, 'WP_Error: ' . $response->get_error_message() );
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$xml  = is_string( wp_remote_retrieve_body( $response ) ) ? wp_remote_retrieve_body( $response ) : '';
		$len  = strlen( $xml );
		$this->lpnw_feed_log( 'sitemap', self::SITEMAP_URL, $code, $len, 'sitemap response' );

		if ( $code < 200 || $code >= 300 || '' === trim( $xml ) ) {
			return array();
		}

		$urls = array();
		if ( preg_match_all( '#<loc>(https://www\.sdlauctions\.co\.uk/property/\d+/[^<]+)</loc>#i', $xml, $matches ) ) {
			foreach ( $matches[1] as $loc ) {
				$urls[] = esc_url_raw( trim( $loc ) );
			}
		}

		$urls = array_values( array_unique( array_filter( $urls ) ) );

		if ( count( $urls ) > self::MAX_PROPERTY_PAGES ) {
			$urls = array_slice( $urls, 0, self::MAX_PROPERTY_PAGES );
		}

		if ( array() === $urls && $len > 200 ) {
			$this->lpnw_log_html_structure_hint( self::SITEMAP_URL, $xml, $code, $len );
		}

		return $urls;
	}

	/**
	 * Scrape absolute /property/{id}/… URLs from HTML listing pages.
	 *
	 * @return array<int, string>
	 */
	private function fetch_property_urls_from_discovery_pages(): array {
		$urls = array();

		foreach ( self::DISCOVERY_PAGE_URLS as $page_url ) {
			$response = wp_remote_get( $page_url, $this->lpnw_http_args( self::SITE_ORIGIN . '/' ) );

			if ( is_wp_error( $response ) ) {
				$this->lpnw_feed_log( 'discovery', $page_url, 0, 0, 'WP_Error: ' . $response->get_error_message() );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$html = is_string( wp_remote_retrieve_body( $response ) ) ? wp_remote_retrieve_body( $response ) : '';
			$len  = strlen( $html );
			$this->lpnw_feed_log( 'discovery', $page_url, $code, $len, 'discovery page fetch' );

			if ( 200 !== $code || '' === trim( $html ) ) {
				continue;
			}

			if ( preg_match_all( '#https://www\.sdlauctions\.co\.uk/property/\d+/[^"\s<>]+#i', $html, $m ) ) {
				foreach ( $m[0] as $u ) {
					$urls[] = esc_url_raw( rtrim( $u, '/.' ) );
				}
			}

			if ( array() === $urls && $len > 400 ) {
				$this->lpnw_log_html_structure_hint( $page_url, $html, $code, $len );
			}
		}

		$urls = array_values( array_unique( array_filter( $urls ) ) );

		if ( count( $urls ) > self::MAX_PROPERTY_PAGES ) {
			$urls = array_slice( $urls, 0, self::MAX_PROPERTY_PAGES );
		}

		return $urls;
	}

	/**
	 * @param string $kind Short label: sitemap, property, discovery, feed.
	 */
	private function lpnw_feed_log( string $kind, string $url, int $http_code, int $resp_len, string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational feed diagnostics.
		error_log(
			sprintf(
				'[LPNW feed=%s kind=%s] ts=%s url=%s http=%d len=%d %s',
				$this->get_source_name(),
				$kind,
				gmdate( 'c' ),
				$url,
				$http_code,
				$resp_len,
				$message
			)
		);
	}

	private function lpnw_log_html_structure_hint( string $url, string $html, int $code, int $len ): void {
		$snippet = function_exists( 'mb_substr' )
			? mb_substr( preg_replace( '/\s+/', ' ', $html ), 0, 500 )
			: substr( preg_replace( '/\s+/', ' ', $html ), 0, 500 );
		$this->lpnw_feed_log( 'parse', $url, $code, $len, 'HTML structure may have changed; snippet=' . $snippet );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function lpnw_http_args( string $referer ): array {
		return array(
			'timeout'    => 30,
			'decompress' => true,
			'headers'    => array(
				'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
				'Accept-Language'           => 'en-GB,en;q=0.9',
				'Accept-Encoding'           => 'gzip, deflate, br',
				'Cache-Control'             => 'no-cache',
				'Connection'                => 'keep-alive',
				'Referer'                   => $referer,
				'Sec-Fetch-Dest'            => 'document',
				'Sec-Fetch-Mode'            => 'navigate',
				'Sec-Fetch-Site'            => 'same-origin',
				'Sec-Fetch-User'            => '?1',
				'Upgrade-Insecure-Requests' => '1',
				'User-Agent'                => self::BROWSER_USER_AGENTS[ wp_rand( 0, count( self::BROWSER_USER_AGENTS ) - 1 ) ],
			),
		);
	}

	/**
	 * @param string $html Full property page HTML.
	 * @param string $url  Canonical listing URL.
	 * @return array<string, mixed>
	 */
	private function parse_property_html( string $html, string $url ): array {
		$lot = array(
			'source_url'      => $url,
			'address'         => '',
			'raw_price'       => '',
			'auction_raw'     => '',
			'lot_number'      => '',
			'price_digits'    => '',
			'property_type'   => '',
			'description_txt' => '',
		);

		if ( '' === trim( $html ) ) {
			return $lot;
		}

		if ( preg_match( '#sdlauctions\.co\.uk/property/(\d+)/#i', $url, $id_match ) ) {
			$lot['lot_number'] = $id_match[1];
		}

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new \DOMXPath( $dom );

		$title_nodes = $xpath->query( "//h1[contains(@class, 'property-title')]" );
		if ( $title_nodes && $title_nodes->length > 0 ) {
			$lot['address'] = trim( $title_nodes->item( 0 )->textContent );
		}

		$guide_nodes = $xpath->query( "//h3[contains(@class, 'property-details-right-guideprice')]" );
		if ( $guide_nodes && $guide_nodes->length > 0 ) {
			$lot['raw_price'] = trim( html_entity_decode( $guide_nodes->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		if ( '' === $lot['raw_price'] ) {
			$guide_nodes = $xpath->query( "//h3[contains(., 'Guide Price')]" );
			if ( $guide_nodes && $guide_nodes->length > 0 ) {
				$lot['raw_price'] = trim( html_entity_decode( $guide_nodes->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}
		}

		$icon_block = $xpath->query( "//div[@id='property-icons']" );
		if ( $icon_block && $icon_block->length > 0 ) {
			$lot['auction_raw'] = trim( preg_replace( '/\s+/', ' ', $icon_block->item( 0 )->textContent ) );
		}

		if ( preg_match( '/data-begin="([^"]+)"/', $html, $dm ) ) {
			$lot['auction_calendar_begin'] = trim( $dm[1] );
		}

		$intro = $xpath->query( "//div[contains(@class,'property-intro')]" );
		if ( $intro && $intro->length > 0 ) {
			$lot['description_txt'] = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $intro->item( 0 )->textContent ) ) );
		}

		if ( preg_match( '#/property/\d+/([^/]+)/?#i', $url, $um ) ) {
			$slug = $um[1];
			if ( preg_match( '/^(.+?)-for-auction-/i', $slug, $sm ) ) {
				$lot['property_type'] = ucwords( str_replace( '-', ' ', $sm[1] ) );
			}
		}

		$lot['price_digits'] = preg_replace( '/[^0-9]/', '', $lot['raw_price'] );

		return $lot;
	}

	/**
	 * @param array<string, mixed> $raw_item Extracted lot data.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$address = sanitize_text_field( $raw_item['address'] ?? '' );
		if ( '' === $address ) {
			return array();
		}

		$postcode = '';
		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $matches ) ) {
			$postcode = strtoupper( preg_replace( '/\s+/', ' ', trim( $matches[1] ) ) );
		}

		if ( '' === $postcode || ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		$price = absint( $raw_item['price_digits'] ?? 0 );
		if ( $price < 1 && ! empty( $raw_item['raw_price'] ) ) {
			$price = $this->parse_money_string( (string) $raw_item['raw_price'] );
		}

		$auction_raw = (string) ( $raw_item['auction_raw'] ?? '' );
		if ( ! empty( $raw_item['auction_calendar_begin'] ) ) {
			$auction_raw .= ' ' . (string) $raw_item['auction_calendar_begin'];
		}

		$auction_date = $this->normalize_auction_date( $auction_raw );
		if ( '' === $auction_date && ! empty( $raw_item['auction_calendar_begin'] ) ) {
			$auction_date = $this->normalize_auction_date( (string) $raw_item['auction_calendar_begin'] );
		}

		$lot_ref = ! empty( $raw_item['lot_number'] ) ? (string) $raw_item['lot_number'] : md5( $address );

		$desc = sprintf(
			'Auction lot #%s. Guide: %s.',
			$lot_ref,
			! empty( $raw_item['raw_price'] ) ? sanitize_text_field( (string) $raw_item['raw_price'] ) : 'TBC'
		);

		$ptype = sanitize_text_field( $raw_item['property_type'] ?? '' );
		if ( '' === $ptype ) {
			$ptype = 'Auction lot';
		}

		$bed_bath = $this->lpnw_extract_beds_baths_from_text(
			$auction_raw . ' ' . (string) ( $raw_item['description_txt'] ?? '' )
		);

		$out = array(
			'source'           => $this->get_source_name(),
			'source_ref'       => sanitize_text_field( 'sdl-' . $lot_ref ),
			'address'          => $address,
			'postcode'         => $postcode,
			'price'            => $price,
			'property_type'    => $ptype,
			'description'      => $desc,
			'source_url'       => esc_url_raw( $raw_item['source_url'] ?? '' ),
			'auction_date'     => $auction_date,
			'application_type' => 'sale',
			'raw_data'         => $raw_item,
		);

		if ( null !== $bed_bath['bedrooms'] ) {
			$out['bedrooms'] = $bed_bath['bedrooms'];
		}
		if ( null !== $bed_bath['bathrooms'] ) {
			$out['bathrooms'] = $bed_bath['bathrooms'];
		}

		return $out;
	}

	private function parse_money_string( string $raw ): int {
		$raw = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$raw = trim( $raw );
		if ( preg_match( '/£?\s*([\d,]+(?:\.\d+)?)\s*M/i', $raw, $m ) ) {
			return (int) round( (float) str_replace( ',', '', $m[1] ) * 1000000 );
		}
		if ( preg_match( '/£?\s*([\d,]+(?:\.\d+)?)\s*K/i', $raw, $m ) ) {
			return (int) round( (float) str_replace( ',', '', $m[1] ) * 1000 );
		}
		if ( preg_match( '/£?\s*([\d,]+)/', $raw, $m ) ) {
			return (int) str_replace( ',', '', $m[1] );
		}

		return 0;
	}

	private function normalize_auction_date( string $text ): string {
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( '' === $text ) {
			return '';
		}

		if ( preg_match( '/(\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+\d{4})/i', $text, $m ) ) {
			$ts = strtotime( $m[1] );
			if ( false !== $ts ) {
				return gmdate( 'Y-m-d', $ts );
			}
		}

		if ( preg_match( '/(\d{1,2}\/\d{1,2}\/\d{4})/', $text, $m ) ) {
			$ts = strtotime( $m[1] );
			if ( false !== $ts ) {
				return gmdate( 'Y-m-d', $ts );
			}
		}

		$ts = strtotime( $text );
		if ( false !== $ts ) {
			return gmdate( 'Y-m-d', $ts );
		}

		return sanitize_text_field( substr( $text, 0, 64 ) );
	}

	/**
	 * @return array{bedrooms: ?int, bathrooms: ?int}
	 */
	private function lpnw_extract_beds_baths_from_text( string $text ): array {
		$beds  = null;
		$baths = null;
		if ( preg_match( '/\b(\d+)\s*bed(?:room)?s?\b/i', $text, $m ) ) {
			$beds = min( 50, absint( $m[1] ) );
		}
		if ( preg_match( '/\b(\d+)\s*bath(?:room)?s?\b/i', $text, $m ) ) {
			$baths = min( 50, absint( $m[1] ) );
		}
		return array(
			'bedrooms'  => $beds,
			'bathrooms' => $baths,
		);
	}
}
