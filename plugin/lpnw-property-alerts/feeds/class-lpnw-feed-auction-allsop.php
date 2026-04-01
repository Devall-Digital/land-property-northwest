<?php
/**
 * Allsop auctions data feed.
 *
 * Scrapes residential and commercial auction carousel tiles on allsop.co.uk,
 * then filters to Northwest England postcodes.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_Allsop extends LPNW_Feed_Base {

	private const PAGE_URLS = array(
		'https://www.allsop.co.uk/auctions/residential-auctions/',
		'https://www.allsop.co.uk/auctions/commercial-auctions/',
	);

	private const SITE_ORIGIN = 'https://www.allsop.co.uk';

	private const USER_AGENT = 'LPNW-PropertyAlerts/1.0 (land-property-northwest.co.uk)';

	public function get_source_name(): string {
		return 'auction_allsop';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch(): array {
		$all = array();
		$seen = array();

		foreach ( self::PAGE_URLS as $page_url ) {
			try {
				$response = wp_remote_get(
					$page_url,
					array(
						'timeout' => 35,
						'headers' => array(
							'User-Agent' => self::USER_AGENT,
						),
					)
				);

				if ( is_wp_error( $response ) ) {
					error_log( 'LPNW Allsop feed HTTP error: ' . $response->get_error_message() );
					continue;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( $code < 200 || $code >= 300 ) {
					error_log( 'LPNW Allsop feed HTTP status: ' . (string) $code . ' for ' . $page_url );
					continue;
				}

				$html  = wp_remote_retrieve_body( $response );
				$chunk = $this->extract_lots_from_page( $html );
				foreach ( $chunk as $lot ) {
					$key = $lot['detail_url'] ?? md5( wp_json_encode( $lot ) );
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$all[]        = $lot;
				}
			} catch ( \Throwable $e ) {
				error_log( 'LPNW Allsop feed page error: ' . $e->getMessage() );
			}
		}

		return $all;
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
				error_log( 'LPNW Allsop lot node error: ' . $e->getMessage() );
			}
		}

		return $lots;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parse_lot_container( \DOMNode $box, \DOMXPath $xpath ): array {
		$link_nodes = $xpath->query( ".//div[contains(@class, '__lot_image')]//a[contains(@href, '/lot-overview/')]", $box );
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

		$byline = '';
		$by_nodes = $xpath->query( ".//h5[contains(@class, '__byline')]//span", $box );
		if ( $by_nodes && $by_nodes->length ) {
			$byline = trim( $by_nodes->item( 0 )->textContent );
		}

		if ( '' !== $byline && '' !== $address && ! str_contains( $address, ',' ) ) {
			$address = $byline . ', ' . $address;
		}

		$raw_price = '';
		$price_nodes = $xpath->query( ".//h3[contains(@class, '__lot_price_grid')]", $box );
		if ( $price_nodes && $price_nodes->length ) {
			$raw_price = trim( html_entity_decode( $price_nodes->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		$tag_raw = '';
		$tag_nodes = $xpath->query( ".//div[contains(@class, '__tag')]", $box );
		if ( $tag_nodes && $tag_nodes->length ) {
			$tag_raw = trim( $tag_nodes->item( 0 )->textContent );
		}

		$lot_number = '';
		if ( $href && preg_match( '#/lot-overview/[^/]+/([^/]+)/?\s*$#i', $href, $m ) ) {
			$lot_number = strtoupper( $m[1] );
		}

		return array(
			'address'      => $address,
			'raw_price'    => $raw_price,
			'detail_url'   => $detail_url,
			'lot_number'   => $lot_number,
			'auction_raw'  => $tag_raw,
			'price_digits' => preg_replace( '/[^0-9]/', '', $raw_price ),
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

		$lot_ref = ! empty( $raw_item['lot_number'] ) ? (string) $raw_item['lot_number'] : md5( $address );
		$auction_date = $this->normalize_tag_auction_date( (string) ( $raw_item['auction_raw'] ?? '' ) );

		$desc = sprintf(
			'Auction lot %s. Guide: %s.',
			$lot_ref,
			! empty( $raw_item['raw_price'] ) ? sanitize_text_field( (string) $raw_item['raw_price'] ) : 'TBC'
		);

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => sanitize_text_field( 'allsop-' . strtolower( $lot_ref ) ),
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
}
