<?php
/**
 * Auction House North West data feed.
 *
 * Current lots are published on the national Auction House site under the
 * North West branch. Scrapes the grid listing and lot detail pages for
 * guide price and auction date.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_AHNW extends LPNW_Feed_Base {

	private const LIST_URL = 'https://www.auctionhouse.co.uk/northwest';

	private const SITE_ORIGIN = 'https://www.auctionhouse.co.uk';

	private const USER_AGENT = 'LPNW-PropertyAlerts/1.0 (land-property-northwest.co.uk)';

	public function get_source_name(): string {
		return 'auction_ahnw';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch(): array {
		$lots = array();

		try {
			$response = wp_remote_get(
				self::LIST_URL,
				array(
					'timeout' => 35,
					'headers' => array(
						'User-Agent' => self::USER_AGENT,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'LPNW AHNW feed HTTP error: ' . $response->get_error_message() );
				return $lots;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				error_log( 'LPNW AHNW feed HTTP status: ' . (string) $code );
				return $lots;
			}

			$html = wp_remote_retrieve_body( $response );
			$lots = $this->extract_lots_from_listing( $html );
		} catch ( \Throwable $e ) {
			error_log( 'LPNW AHNW feed error: ' . $e->getMessage() );
		}

		return $lots;
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
				error_log( 'LPNW AHNW listing row error: ' . $e->getMessage() );
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

		$lot_number = '';
		if ( $href && preg_match( '#(?:lot/redirect/|northwest/auction/lot/|/lot/)(\d+)\b#', $href, $m ) ) {
			$lot_number = $m[1];
		}

		return array(
			'address'     => $address,
			'raw_price'   => $raw_price,
			'detail_url'  => $detail_url,
			'lot_number'  => $lot_number,
			'price_digits' => preg_replace( '/[^0-9]/', '', $raw_price ),
			'auction_raw' => '',
		);
	}

	/**
	 * @param string $detail_url Absolute lot URL on auctionhouse.co.uk.
	 * @return array<string, string>
	 */
	private function fetch_lot_detail_extras( string $detail_url ): array {
		$out = array(
			'auction_raw' => '',
		);

		if ( '' === $detail_url || ! str_contains( $detail_url, 'auctionhouse.co.uk' ) ) {
			return $out;
		}

		try {
			$response = wp_remote_get(
				$detail_url,
				array(
					'timeout' => 20,
					'headers' => array(
						'User-Agent' => self::USER_AGENT,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'LPNW AHNW detail HTTP error: ' . $response->get_error_message() );
				return $out;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				return $out;
			}

			$html = wp_remote_retrieve_body( $response );
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
		} catch ( \Throwable $e ) {
			error_log( 'LPNW AHNW detail parse error: ' . $e->getMessage() );
		}

		return $out;
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

		$desc = sprintf(
			'Auction lot #%s. Guide: %s.',
			$lot_ref,
			'' !== $raw_price ? sanitize_text_field( $raw_price ) : 'TBC'
		);

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => sanitize_text_field( 'ahnw-' . $lot_ref ),
			'address'       => $address,
			'postcode'      => $postcode,
			'price'         => $price,
			'property_type' => 'Auction lot',
			'description'   => $desc,
			'source_url'    => esc_url_raw( $raw_item['detail_url'] ?? '' ),
			'auction_date'  => $auction_date,
			'raw_data'      => $raw_item,
		);
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
}
