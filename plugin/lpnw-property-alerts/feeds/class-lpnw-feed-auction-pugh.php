<?php
/**
 * Pugh Auctions data feed.
 *
 * Scrapes upcoming auction lots from pugh-auctions.com (catalogue URLs tried in order;
 * live listings are on property-search; /lots is legacy).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_Pugh extends LPNW_Feed_Base {

	private const SITE_ORIGIN = 'https://www.pugh-auctions.com';

	/**
	 * Catalogue URLs tried in order until one returns parseable lot markup.
	 *
	 * @var array<int, string>
	 */
	private const CATALOGUE_URLS = array(
		'https://www.pugh-auctions.com/property-search?include-sold=off',
		'https://www.pugh-auctions.com/property-search',
		'https://www.pugh-auctions.com/auctions',
		'https://www.pugh-auctions.com/lots',
		'https://www.pugh-auctions.com/catalogue',
		'https://www.pugh-auctions.com/upcoming-auctions',
	);

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
		return 'auction_pugh';
	}

	protected function fetch(): array {
		$last_html = '';
		$last_code = 0;
		$last_len  = 0;

		foreach ( self::CATALOGUE_URLS as $try_url ) {
			$response = wp_remote_get( $try_url, $this->lpnw_http_args( $try_url ) );

			if ( is_wp_error( $response ) ) {
				$this->lpnw_feed_log( $try_url, 0, 0, 'WP_Error: ' . $response->get_error_message() );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$html = is_string( $body ) ? $body : '';
			$len  = strlen( $html );

			$last_html = $html;
			$last_code = $code;
			$last_len  = $len;

			$this->lpnw_feed_log( $try_url, $code, $len, 'catalogue fetch attempt' );

			if ( 200 !== $code ) {
				continue;
			}

			$lots = $this->extract_lots( $html );

			if ( array() !== $lots ) {
				$this->lpnw_feed_log( $try_url, $code, $len, 'catalogue URL succeeded; properties parsed' );
				return $this->enrich_nw_lots_from_detail_pages( $lots );
			}

			$this->lpnw_feed_log( $try_url, $code, $len, 'HTTP 200 but no properties matched selectors' );
		}

		if ( 200 === $last_code && $last_len > 400 ) {
			$this->lpnw_log_html_structure_hint( $last_html, $last_code, $last_len );
		} elseif ( 200 === $last_code && $last_len <= 400 ) {
			$this->lpnw_feed_log( '(last url)', $last_code, $last_len, 'HTTP 200 but body very small; possible block or error page' );
		}

		return array();
	}

	/**
	 * For NW postcodes only, load the property page for auction date, type, beds/baths.
	 *
	 * @param array<int, array<string, mixed>> $lots Raw lots from listing HTML.
	 * @return array<int, array<string, mixed>>
	 */
	private function enrich_nw_lots_from_detail_pages( array $lots ): array {
		foreach ( $lots as $i => $lot ) {
			$address = isset( $lot['address'] ) ? (string) $lot['address'] : '';
			$postcode = $this->lpnw_postcode_from_address( $address );
			if ( '' === $postcode || ! $this->is_nw_postcode( $postcode ) ) {
				continue;
			}
			$url = isset( $lot['source_url'] ) ? (string) $lot['source_url'] : '';
			if ( '' === $url || ! str_contains( $url, 'pugh-auctions.com' ) ) {
				continue;
			}
			$extras = $this->fetch_pugh_property_extras( $url );
			$lots[ $i ] = array_merge( $lot, $extras );
		}

		return $lots;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fetch_pugh_property_extras( string $property_url ): array {
		$out = array(
			'detail_auction_date' => '',
			'detail_property_type' => '',
			'detail_description_snippet' => '',
		);

		$response = wp_remote_get( $property_url, $this->lpnw_http_args( self::SITE_ORIGIN . '/' ) );

		if ( is_wp_error( $response ) ) {
			$this->lpnw_feed_log( $property_url, 0, 0, 'detail WP_Error: ' . $response->get_error_message() );
			return $out;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$html = is_string( wp_remote_retrieve_body( $response ) ) ? wp_remote_retrieve_body( $response ) : '';
		$len  = strlen( $html );
		$this->lpnw_feed_log( $property_url, $code, $len, 'property detail fetch' );

		if ( 200 !== $code || '' === trim( $html ) ) {
			return $out;
		}

		if ( preg_match( '/data-begin="([^"]+)"/', $html, $m ) ) {
			$out['detail_auction_date'] = $this->lpnw_normalize_auction_date_string( $m[1] );
		}

		if ( preg_match( '/<title>([^<]+)<\/title>/i', $html, $tm ) ) {
			$t = html_entity_decode( $tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( preg_match( '/\|\s*([^|]+?)\s+for\s+auction\b/i', $t, $pt ) ) {
				$out['detail_property_type'] = trim( $pt[1] );
			}
		}

		$plain = wp_strip_all_tags( $html );
		$plain = preg_replace( '/\s+/', ' ', $plain );
		if ( is_string( $plain ) ) {
			$out['detail_description_snippet'] = substr( $plain, 0, 2000 );
		}

		return $out;
	}

	/**
	 * @param string $message   Context.
	 * @param int    $http_code HTTP status or 0.
	 * @param int    $resp_len  Body length.
	 */
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

	private function lpnw_log_html_structure_hint( string $html, int $code, int $len ): void {
		$snippet = function_exists( 'mb_substr' )
			? mb_substr( preg_replace( '/\s+/', ' ', $html ), 0, 500 )
			: substr( preg_replace( '/\s+/', ' ', $html ), 0, 500 );
		$this->lpnw_feed_log( '(aggregate)', $code, $len, 'HTML structure may have changed; snippet=' . $snippet );
	}

	/**
	 * @param string $for_url Full request URL (Referer base derived from origin).
	 * @return array<string, mixed>
	 */
	private function lpnw_http_args( string $for_url ): array {
		$referer = self::SITE_ORIGIN . '/';
		$parts   = wp_parse_url( $for_url );
		if ( is_array( $parts ) && ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			$referer = $parts['scheme'] . '://' . $parts['host'] . '/';
		}

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
	 * @param string $html Raw HTML from a catalogue page.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_lots( string $html ): array {
		$lots  = array();
		$seen  = array();

		if ( empty( $html ) ) {
			return $lots;
		}

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new \DOMXPath( $dom );

		$card_roots = $xpath->query( "//div[contains(concat(' ', normalize-space(@class), ' '), ' h-full ')][contains(concat(' ', normalize-space(@class), ' '), ' mb-8 ')]" );
		if ( ! $card_roots || 0 === $card_roots->length ) {
			$card_roots = $xpath->query( "//div[contains(@class, 'lot-card')] | //article[contains(@class, 'lot')]" );
		}

		if ( ! $card_roots || 0 === $card_roots->length ) {
			return $lots;
		}

		foreach ( $card_roots as $node ) {
			$lot = $this->parse_lot_card( $node, $xpath );
			if ( empty( $lot['address'] ) || empty( $lot['source_url'] ) ) {
				continue;
			}
			$key = $lot['source_url'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$lots[]       = $lot;
		}

		return $lots;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parse_lot_card( \DOMNode $node, \DOMXPath $xpath ): array {
		$link_nodes = $xpath->query( ".//a[contains(@href, '/property/')]", $node );
		$href       = '';
		if ( $link_nodes ) {
			foreach ( $link_nodes as $ln ) {
				if ( ! $ln instanceof \DOMElement ) {
					continue;
				}
				$h = trim( $ln->getAttribute( 'href' ) );
				if ( '' !== $h && str_contains( $h, '/property/' ) ) {
					$href = $h;
					break;
				}
			}
		}

		$address = '';
		$addr_q  = $xpath->query( ".//div[contains(@class,'uppercase') and contains(@class,'text-lg')]//a[contains(@href,'/property/')]", $node );
		if ( $addr_q && $addr_q->length > 0 ) {
			$address = trim( preg_replace( '/\s+/', ' ', $addr_q->item( 0 )->textContent ) );
		}

		$raw_price = '';
		$price_q   = $xpath->query( ".//p[contains(@class,'text-secondary') and contains(@class,'font-bold')]", $node );
		if ( $price_q && $price_q->length > 0 ) {
			$raw_price = trim( html_entity_decode( $price_q->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		$status_badge = '';
		$badge_q      = $xpath->query( ".//div[contains(@class,'bg-secondary') and contains(@class,'text-white')]", $node );
		if ( $badge_q && $badge_q->length > 0 ) {
			$status_badge = trim( preg_replace( '/\s+/', ' ', $badge_q->item( 0 )->textContent ) );
		}

		$link = $href;
		if ( $link && ! str_starts_with( $link, 'http' ) ) {
			$link = self::SITE_ORIGIN . $link;
		}

		$lot_number = '';
		if ( $href && preg_match( '#/property/([^/"\'\s]+)/?#i', $href, $m ) ) {
			$lot_number = $m[1];
		}

		$price_clean = preg_replace( '/[^0-9]/', '', $raw_price );

		return array(
			'address'       => $address,
			'price'         => $price_clean,
			'source_url'    => $link,
			'lot_number'    => $lot_number,
			'raw_price'     => $raw_price,
			'status_badge'  => $status_badge,
			'listing_blurb' => $raw_price . ( $status_badge ? ' ' . $status_badge : '' ),
		);
	}

	private function lpnw_postcode_from_address( string $address ): string {
		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $matches ) ) {
			return strtoupper( preg_replace( '/\s+/', ' ', trim( $matches[1] ) ) );
		}
		return '';
	}

	private function lpnw_normalize_auction_date_string( string $text ): string {
		$text = trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $text ) {
			return '';
		}
		$ts = strtotime( $text );
		if ( false !== $ts ) {
			return gmdate( 'Y-m-d', $ts );
		}
		return sanitize_text_field( substr( $text, 0, 64 ) );
	}

	/**
	 * @param array<string, mixed> $raw_item Extracted lot data.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$address  = sanitize_text_field( $raw_item['address'] ?? '' );
		$postcode = $this->lpnw_postcode_from_address( $address );

		if ( '' !== $postcode && ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		if ( '' === $postcode ) {
			return array();
		}

		$bed_bath = $this->lpnw_extract_beds_baths_from_text(
			( $raw_item['listing_blurb'] ?? '' ) . ' ' . ( $raw_item['detail_description_snippet'] ?? '' )
		);

		$property_type = sanitize_text_field( $raw_item['detail_property_type'] ?? '' );
		if ( '' === $property_type ) {
			$property_type = 'Auction lot';
		}

		$auction_date = sanitize_text_field( $raw_item['detail_auction_date'] ?? '' );

		$channel = '';
		$badge   = strtolower( (string) ( $raw_item['status_badge'] ?? '' ) );
		if ( str_contains( $badge, 'sold' ) ) {
			$channel = 'sold_or_post_auction';
		} elseif ( str_contains( $badge, 'guide' ) ) {
			$channel = 'for_sale';
		}

		$status_note = '' !== $channel ? sanitize_text_field( $channel ) . '. ' : '';

		$out = array(
			'source'           => $this->get_source_name(),
			'source_ref'       => sanitize_text_field( 'pugh-' . ( $raw_item['lot_number'] ?? md5( $address ) ) ),
			'address'          => $address,
			'postcode'         => $postcode,
			'price'            => absint( $raw_item['price'] ?? 0 ),
			'property_type'    => $property_type,
			'description'      => sprintf(
				'Auction lot%s. %sGuide price: %s.',
				! empty( $raw_item['lot_number'] ) ? ' #' . sanitize_text_field( (string) $raw_item['lot_number'] ) : '',
				$status_note,
				! empty( $raw_item['raw_price'] ) ? sanitize_text_field( (string) $raw_item['raw_price'] ) : 'TBC'
			),
			'source_url'       => esc_url_raw( $raw_item['source_url'] ?? '' ),
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
