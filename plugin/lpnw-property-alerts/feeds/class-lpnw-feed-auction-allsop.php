<?php
/**
 * Allsop auctions data feed.
 *
 * Scrapes residential and commercial auction tiles on allsop.co.uk (several base URLs).
 * Filters to Northwest England postcodes.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_Allsop extends LPNW_Feed_Base {

	/**
	 * Listing pages tried in order (root /commercial-auctions/ may 404; logged).
	 *
	 * @var array<int, string>
	 */
	private const PAGE_URLS = array(
		'https://www.allsop.co.uk/auctions/',
		'https://www.allsop.co.uk/auctions/residential-auctions/',
		'https://www.allsop.co.uk/auctions/commercial-auctions/',
		'https://www.allsop.co.uk/residential-auctions/',
		'https://www.allsop.co.uk/commercial-auctions/',
	);

	private const SITE_ORIGIN = 'https://www.allsop.co.uk';

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
		return 'auction_allsop';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch(): array {
		$all  = array();
		$seen = array();

		foreach ( self::PAGE_URLS as $page_url ) {
			try {
				$response = wp_remote_get( $page_url, $this->lpnw_http_args( self::SITE_ORIGIN . '/' ) );

				if ( is_wp_error( $response ) ) {
					$this->lpnw_feed_log( $page_url, 0, 0, 'WP_Error: ' . $response->get_error_message() );
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code( $response );
				$html = is_string( wp_remote_retrieve_body( $response ) ) ? wp_remote_retrieve_body( $response ) : '';
				$len  = strlen( $html );
				$this->lpnw_feed_log( $page_url, $code, $len, 'auctions page fetch' );

				if ( $code < 200 || $code >= 300 ) {
					continue;
				}

				$chunk = $this->extract_lots_from_page( $html );
				if ( array() === $chunk && $len > 400 ) {
					$this->lpnw_log_html_structure_hint( $page_url, $html, $code, $len );
				}

				foreach ( $chunk as $lot ) {
					$key = $lot['detail_url'] ?? md5( wp_json_encode( $lot ) );
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$all[]        = $lot;
				}
			} catch ( \Throwable $e ) {
				$this->lpnw_feed_log( $page_url, 0, 0, 'page error: ' . $e->getMessage() );
			}
		}

		return $all;
	}

	private function lpnw_feed_log( string $url, int $http_code, int $resp_len, string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational feed diagnostics.
		error_log(
			sprintf(
				'[LPNW feed=%s] ts=%s url=%s http=%d len=%d %s',
				$this->get_source_name(),
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
		$this->lpnw_feed_log( $url, $code, $len, 'HTML structure may have changed; snippet=' . $snippet );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function lpnw_http_args( string $referer ): array {
		return array(
			'timeout'    => 35,
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
	 * @param string $html Page HTML.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_lots_from_page( string $html ): array {
		$lots = array();

		if ( '' === trim( $html ) ) {
			return $lots;
		}

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new \DOMXPath( $dom );

		$containers = $xpath->query( "//div[contains(@class, '__lot_container')]" );
		if ( ! $containers || 0 === $containers->length ) {
			return $lots;
		}

		foreach ( $containers as $box ) {
			try {
				$lot = $this->parse_lot_container( $box, $xpath );
				if ( ! empty( $lot['address'] ) ) {
					$lots[] = $lot;
				}
			} catch ( \Throwable $e ) {
				$this->lpnw_feed_log( '(lot node)', 0, 0, 'lot node error: ' . $e->getMessage() );
			}
		}

		return $lots;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parse_lot_container( \DOMNode $box, \DOMXPath $xpath ): array {
		$link_nodes = $xpath->query( ".//div[contains(@class, '__lot_image')]//a[contains(@href, '/lot-overview/')]", $box );
		if ( ! $link_nodes || $link_nodes->length < 1 ) {
			$link_nodes = $xpath->query( ".//a[contains(@href, '/lot-overview/')]", $box );
		}

		$href       = '';
		$title_attr = '';
		if ( $link_nodes && $link_nodes->length > 0 && $link_nodes->item( 0 ) instanceof \DOMElement ) {
			$el         = $link_nodes->item( 0 );
			$href       = trim( $el->getAttribute( 'href' ) );
			$title_attr = trim( $el->getAttribute( 'title' ) );
		}

		$detail_url = $href;
		if ( $detail_url && ! str_starts_with( $detail_url, 'http' ) ) {
			$detail_url = self::SITE_ORIGIN . $detail_url;
		}

		$address = $title_attr;
		if ( '' === $address ) {
			$loc_nodes = $xpath->query( ".//h5[contains(@class, '__location')]", $box );
			if ( $loc_nodes && $loc_nodes->length ) {
				$address = trim( $loc_nodes->item( 0 )->textContent );
			}
		}

		$byline   = '';
		$by_nodes = $xpath->query( ".//h5[contains(@class, '__byline')]//span", $box );
		if ( $by_nodes && $by_nodes->length ) {
			$byline = trim( $by_nodes->item( 0 )->textContent );
		}

		if ( '' !== $byline && '' !== $address && ! str_contains( $address, ',' ) ) {
			$address = $byline . ', ' . $address;
		}

		$raw_price   = '';
		$price_nodes = $xpath->query( ".//h3[contains(@class, '__lot_price_grid')]", $box );
		if ( $price_nodes && $price_nodes->length ) {
			$raw_price = trim( html_entity_decode( $price_nodes->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		$tag_raw   = '';
		$tag_nodes = $xpath->query( ".//div[contains(@class, '__tag')]", $box );
		if ( $tag_nodes && $tag_nodes->length ) {
			$tag_raw = trim( $tag_nodes->item( 0 )->textContent );
		}

		$lot_number = '';
		if ( $href && preg_match( '#/lot-overview/[^/]+/([^/\s]+)/?\s*$#i', $href, $m ) ) {
			$lot_number = strtoupper( $m[1] );
		}

		$channel = '';
		if ( $box instanceof \DOMElement ) {
			$c = ' ' . $box->getAttribute( 'class' ) . ' ';
			if ( str_contains( $c, '__commercial' ) ) {
				$channel = 'commercial';
			} elseif ( str_contains( $c, '__residential' ) ) {
				$channel = 'residential';
			}
		}

		return array(
			'address'         => $address,
			'raw_price'       => $raw_price,
			'detail_url'      => $detail_url,
			'lot_number'      => $lot_number,
			'auction_raw'     => $tag_raw,
			'price_digits'    => preg_replace( '/[^0-9]/', '', $raw_price ),
			'auction_channel' => $channel,
			'listing_blurb'   => trim( $address . ' ' . $raw_price . ' ' . $tag_raw ),
		);
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

		$lot_ref      = ! empty( $raw_item['lot_number'] ) ? (string) $raw_item['lot_number'] : md5( $address );
		$auction_date = $this->normalize_tag_auction_date( (string) ( $raw_item['auction_raw'] ?? '' ) );

		$channel = sanitize_key( (string) ( $raw_item['auction_channel'] ?? '' ) );
		$ptype   = 'Auction lot';
		if ( 'commercial' === $channel ) {
			$ptype = 'Commercial auction lot';
		} elseif ( 'residential' === $channel ) {
			$ptype = 'Residential auction lot';
		}

		$desc = sprintf(
			'Auction lot %s. Guide: %s.',
			$lot_ref,
			! empty( $raw_item['raw_price'] ) ? sanitize_text_field( (string) $raw_item['raw_price'] ) : 'TBC'
		);

		$bed_bath = $this->lpnw_extract_beds_baths_from_text( (string) ( $raw_item['listing_blurb'] ?? '' ) );

		$out = array(
			'source'           => $this->get_source_name(),
			'source_ref'       => sanitize_text_field( 'allsop-' . strtolower( $lot_ref ) ),
			'address'          => $address,
			'postcode'         => $postcode,
			'price'            => $price,
			'property_type'    => $ptype,
			'description'      => $desc,
			'source_url'       => esc_url_raw( $raw_item['detail_url'] ?? '' ),
			'auction_date'     => $auction_date,
			'application_type' => 'sale',
			'raw_data'         => array_merge( $raw_item, array( 'channel' => $channel ) ),
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

		if ( preg_match( '/£\s*([\d,.]+)\s*M\s*\+/i', $raw, $m ) ) {
			return (int) round( (float) str_replace( ',', '', $m[1] ) * 1000000 );
		}
		if ( preg_match( '/£\s*([\d,.]+)\s*K\s*\+/i', $raw, $m ) ) {
			return (int) round( (float) str_replace( ',', '', $m[1] ) * 1000 );
		}
		if ( preg_match( '/£\s*([\d,]+)\s*-\s*£\s*([\d,]+)/', $raw, $m ) ) {
			return (int) ( ( (int) str_replace( ',', '', $m[1] ) + (int) str_replace( ',', '', $m[2] ) ) / 2 );
		}
		if ( preg_match( '/£\s*([\d,]+)/', $raw, $m ) ) {
			return (int) str_replace( ',', '', $m[1] );
		}

		return 0;
	}

	private function normalize_tag_auction_date( string $tag ): string {
		$tag = trim( html_entity_decode( $tag, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $tag ) {
			return '';
		}

		if ( preg_match( '/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+(\d{4})/i', $tag, $m ) ) {
			$try = '1 ' . $m[1] . ' ' . $m[2];
			$ts  = strtotime( $try );
			if ( false !== $ts ) {
				return gmdate( 'Y-m-d', $ts );
			}
		}

		return sanitize_text_field( substr( $tag, 0, 64 ) );
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
