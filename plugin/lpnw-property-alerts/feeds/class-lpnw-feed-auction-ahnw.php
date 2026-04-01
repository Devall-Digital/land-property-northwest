<?php
/**
 * Auction House North West data feed.
 *
 * Current lots are published on the national Auction House site under the
 * North West branch. Tries several listing URLs; parses grid cards and
 * enriches NW lots from detail pages (including online.auctionhouse.co.uk).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_AHNW extends LPNW_Feed_Base {

	private const SITE_ORIGIN = 'https://www.auctionhouse.co.uk';

	/**
	 * Branch listing URLs (first success with parseable cards wins).
	 *
	 * @var array<int, string>
	 */
	private const LIST_URLS = array(
		'https://www.auctionhouse.co.uk/northwest',
		'https://www.auctionhouse.co.uk/northwest/auction',
		'https://www.auctionhouse.co.uk/northwest/lots',
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
		return 'auction_ahnw';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch(): array {
		$last_html = '';
		$last_code = 0;
		$last_len  = 0;

		foreach ( self::LIST_URLS as $list_url ) {
			$response = wp_remote_get( $list_url, $this->lpnw_http_args( self::SITE_ORIGIN . '/' ) );

			if ( is_wp_error( $response ) ) {
				$this->lpnw_feed_log( $list_url, 0, 0, 'WP_Error: ' . $response->get_error_message() );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$html = is_string( wp_remote_retrieve_body( $response ) ) ? wp_remote_retrieve_body( $response ) : '';
			$len  = strlen( $html );

			$last_html = $html;
			$last_code = $code;
			$last_len  = $len;

			$this->lpnw_feed_log( $list_url, $code, $len, 'branch listing fetch' );

			if ( $code < 200 || $code >= 300 ) {
				continue;
			}

			$lots = $this->extract_lots_from_listing( $html );

			if ( array() !== $lots ) {
				$this->lpnw_feed_log( $list_url, $code, $len, 'listing URL succeeded; lot cards parsed' );
				return $lots;
			}

			$this->lpnw_feed_log( $list_url, $code, $len, 'HTTP 200 but no properties matched selectors' );
		}

		if ( 200 === $last_code && $last_len > 400 ) {
			$this->lpnw_log_html_structure_hint( $last_html, $last_code, $last_len );
		}

		return array();
	}

	/**
	 * @param string $html Listing page HTML.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_lots_from_listing( string $html ): array {
		$lots = array();

		if ( '' === trim( $html ) ) {
			return $lots;
		}

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new \DOMXPath( $dom );

		$nodes = $xpath->query( "//div[contains(@class, 'lot-search-result')]" );
		if ( ! $nodes || 0 === $nodes->length ) {
			return $lots;
		}

		foreach ( $nodes as $node ) {
			try {
				$lot = $this->parse_listing_node( $node, $xpath );
				if ( empty( $lot['address'] ) ) {
					continue;
				}

				$postcode = '';
				if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $lot['address'], $pc ) ) {
					$postcode = strtoupper( preg_replace( '/\s+/', ' ', trim( $pc[1] ) ) );
				}

				if ( '' !== $postcode && $this->is_nw_postcode( $postcode ) && ! empty( $lot['detail_url'] ) ) {
					$lot = array_merge( $lot, $this->fetch_lot_detail_extras( $lot['detail_url'] ) );
				}

				$lots[] = $lot;
			} catch ( \Throwable $e ) {
				$this->lpnw_feed_log( '(row)', 0, 0, 'listing row error: ' . $e->getMessage() );
			}
		}

		return $lots;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parse_listing_node( \DOMNode $node, \DOMXPath $xpath ): array {
		$link_nodes = $xpath->query( ".//a[contains(@class, 'home-lot-wrapper-link')]", $node );
		$href       = '';
		if ( $link_nodes && $link_nodes->length > 0 && $link_nodes->item( 0 ) instanceof \DOMElement ) {
			$href = trim( $link_nodes->item( 0 )->getAttribute( 'href' ) );
		}

		$detail_url = $href;
		if ( $detail_url && ! str_starts_with( $detail_url, 'http' ) ) {
			$detail_url = self::SITE_ORIGIN . $detail_url;
		}

		$addr_nodes = $xpath->query( ".//p[contains(@class, 'grid-address')]", $node );
		$address    = $addr_nodes && $addr_nodes->length ? trim( $addr_nodes->item( 0 )->textContent ) : '';

		$price_nodes = $xpath->query( ".//div[contains(@class, 'grid-view-guide')]", $node );
		$raw_price   = $price_nodes && $price_nodes->length ? trim( $price_nodes->item( 0 )->textContent ) : '';

		$channel = $this->lpnw_channel_from_classes( $node, $xpath );

		$listing_blurb = trim( $address . ' ' . $raw_price );

		$lot_number = '';
		if ( $href && preg_match( '#(?:lot/redirect/|northwest/auction/lot/|/lot/)(\d+)\b#', $href, $m ) ) {
			$lot_number = $m[1];
		}

		return array(
			'address'        => $address,
			'raw_price'      => $raw_price,
			'detail_url'     => $detail_url,
			'lot_number'     => $lot_number,
			'price_digits'   => preg_replace( '/[^0-9]/', '', $raw_price ),
			'auction_raw'    => '',
			'auction_channel'=> $channel,
			'listing_blurb'  => $listing_blurb,
		);
	}

	/**
	 * Derive residential / commercial / online channel from badge classes.
	 */
	private function lpnw_channel_from_classes( \DOMNode $node, \DOMXPath $xpath ): string {
		$badges = $xpath->query( ".//*[contains(@class,'lotbg-online') or contains(@class,'lotbg-residential') or contains(@class,'lotbg-commercial')]", $node );
		if ( ! $badges || $badges->length < 1 || ! ( $badges->item( 0 ) instanceof \DOMElement ) ) {
			return '';
		}
		$class = ' ' . $badges->item( 0 )->getAttribute( 'class' ) . ' ';
		if ( str_contains( $class, 'lotbg-commercial' ) ) {
			return 'commercial';
		}
		if ( str_contains( $class, 'lotbg-residential' ) ) {
			return 'residential';
		}
		if ( str_contains( $class, 'lotbg-online' ) ) {
			return 'online';
		}
		return '';
	}

	/**
	 * @param string $detail_url Absolute lot URL on auctionhouse.co.uk or online.auctionhouse.co.uk.
	 * @return array<string, string>
	 */
	private function fetch_lot_detail_extras( string $detail_url ): array {
		$out = array(
			'auction_raw'     => '',
			'detail_blurb'    => '',
		);

		if ( '' === $detail_url || ! preg_match( '#auctionhouse\.co\.uk#i', $detail_url ) ) {
			return $out;
		}

		try {
			$response = wp_remote_get( $detail_url, $this->lpnw_http_args( self::SITE_ORIGIN . '/' ) );

			if ( is_wp_error( $response ) ) {
				$this->lpnw_feed_log( $detail_url, 0, 0, 'detail WP_Error: ' . $response->get_error_message() );
				return $out;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$html = is_string( wp_remote_retrieve_body( $response ) ) ? wp_remote_retrieve_body( $response ) : '';
			$len  = strlen( $html );
			$this->lpnw_feed_log( $detail_url, $code, $len, 'lot detail fetch' );

			if ( $code < 200 || $code >= 300 ) {
				return $out;
			}

			if ( '' === trim( $html ) ) {
				return $out;
			}

			$dom = new \DOMDocument();
			@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$xpath = new \DOMXPath( $dom );

			$headers = $xpath->query( "//p[contains(@class, 'auction-info-header')]" );
			for ( $i = 0; $headers && $i < $headers->length; $i++ ) {
				$h = $headers->item( $i );
				if ( ! $h ) {
					continue;
				}
				$label = trim( $h->textContent );
				if ( 0 !== stripos( $label, 'Auction Date' ) ) {
					continue;
				}
				$next = $h->nextSibling;
				while ( $next && XML_TEXT_NODE === $next->nodeType && '' === trim( $next->textContent ) ) {
					$next = $next->nextSibling;
				}
				if ( $next && XML_ELEMENT_NODE === $next->nodeType ) {
					$out['auction_raw'] = trim( $next->textContent );
				}
				break;
			}

			$out['detail_blurb'] = substr( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ), 0, 2500 );
		} catch ( \Throwable $e ) {
			$this->lpnw_feed_log( $detail_url, 0, 0, 'detail parse error: ' . $e->getMessage() );
		}

		return $out;
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

	private function lpnw_log_html_structure_hint( string $html, int $code, int $len ): void {
		$snippet = function_exists( 'mb_substr' )
			? mb_substr( preg_replace( '/\s+/', ' ', $html ), 0, 500 )
			: substr( preg_replace( '/\s+/', ' ', $html ), 0, 500 );
		$this->lpnw_feed_log( '(snippet)', $code, $len, 'HTML structure may have changed; snippet=' . $snippet );
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
				'Sec-Fetch-Site'            => 'cross-site',
				'Sec-Fetch-User'            => '?1',
				'Upgrade-Insecure-Requests' => '1',
				'User-Agent'                => self::BROWSER_USER_AGENTS[ wp_rand( 0, count( self::BROWSER_USER_AGENTS ) - 1 ) ],
			),
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

		$raw_price = (string) ( $raw_item['raw_price'] ?? '' );
		if ( str_contains( strtolower( $raw_price ), 'withdrawn' ) ) {
			return array();
		}

		$price = absint( $raw_item['price_digits'] ?? 0 );
		if ( $price < 1 && '' !== $raw_price ) {
			$price = $this->parse_money_string( $raw_price );
		}

		$lot_ref = ! empty( $raw_item['lot_number'] ) ? (string) $raw_item['lot_number'] : md5( $address );
		$auction_date = $this->normalize_auction_date( (string) ( $raw_item['auction_raw'] ?? '' ) );

		$channel = sanitize_key( (string) ( $raw_item['auction_channel'] ?? '' ) );
		$ptype   = 'Auction lot';
		if ( 'commercial' === $channel ) {
			$ptype = 'Commercial auction lot';
		} elseif ( 'residential' === $channel ) {
			$ptype = 'Residential auction lot';
		} elseif ( 'online' === $channel ) {
			$ptype = 'Online auction lot';
		}

		$desc = sprintf(
			'Auction lot #%s. Guide: %s.',
			$lot_ref,
			'' !== $raw_price ? sanitize_text_field( $raw_price ) : 'TBC'
		);

		$bed_bath = $this->lpnw_extract_beds_baths_from_text(
			( $raw_item['listing_blurb'] ?? '' ) . ' ' . ( $raw_item['detail_blurb'] ?? '' )
		);

		$out = array(
			'source'           => $this->get_source_name(),
			'source_ref'       => sanitize_text_field( 'ahnw-' . $lot_ref ),
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
		if ( preg_match( '/£\s*([\d,]+)\s*-\s*£\s*([\d,]+)/', $raw, $m ) ) {
			return (int) ( ( (int) str_replace( ',', '', $m[1] ) + (int) str_replace( ',', '', $m[2] ) ) / 2 );
		}
		if ( preg_match( '/£\s*([\d,]+)/', $raw, $m ) ) {
			return (int) str_replace( ',', '', $m[1] );
		}

		return 0;
	}

	private function normalize_auction_date( string $text ): string {
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
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
