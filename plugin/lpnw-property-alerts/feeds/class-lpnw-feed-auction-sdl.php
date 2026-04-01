<?php
/**
 * SDL Property Auctions data feed.
 *
 * Loads property URLs from the public property sitemap, fetches each listing
 * with GET, parses HTML, and keeps lots in Northwest England postcodes only.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_SDL extends LPNW_Feed_Base {

	private const SITEMAP_URL = 'https://www.sdlauctions.co.uk/property-sitemap.xml';

	private const USER_AGENT = 'LPNW-PropertyAlerts/1.0 (land-property-northwest.co.uk)';

	private const MAX_PROPERTY_PAGES = 200;

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
				return $out;
			}

			foreach ( $urls as $url ) {
				try {
					$response = wp_remote_get(
						$url,
						array(
							'timeout' => 25,
							'headers' => array(
								'User-Agent' => self::USER_AGENT,
							),
						)
					);

					if ( is_wp_error( $response ) ) {
						error_log( 'LPNW SDL feed HTTP error: ' . $response->get_error_message() );
						continue;
					}

					$code = wp_remote_retrieve_response_code( $response );
					if ( $code < 200 || $code >= 300 ) {
						error_log( 'LPNW SDL feed HTTP status ' . (string) $code . ' for ' . $url );
						continue;
					}

					$html = wp_remote_retrieve_body( $response );
					$lot  = $this->parse_property_html( $html, $url );
					if ( ! empty( $lot['address'] ) ) {
						$out[] = $lot;
					}
				} catch ( \Throwable $e ) {
					error_log( 'LPNW SDL feed lot error: ' . $e->getMessage() );
				}
			}
		} catch ( \Throwable $e ) {
			error_log( 'LPNW SDL feed error: ' . $e->getMessage() );
		}

		return $out;
	}

	/**
	 * @return array<int, string>
	 */
	private function fetch_property_urls_from_sitemap(): array {
		$response = wp_remote_get(
			self::SITEMAP_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => self::USER_AGENT,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'LPNW SDL sitemap error: ' . $response->get_error_message() );
			return array();
		}

		$xml = wp_remote_retrieve_body( $response );
		if ( '' === trim( $xml ) ) {
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

		return $urls;
	}

	/**
	 * @param string $html Full property page HTML.
	 * @param string $url  Canonical listing URL.
	 * @return array<string, mixed>
	 */
	private function parse_property_html( string $html, string $url ): array {
		$lot = array(
			'source_url'   => $url,
			'address'      => '',
			'raw_price'    => '',
			'auction_raw'  => '',
			'lot_number'   => '',
			'price_digits' => '',
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

		$guide_nodes = $xpath->query( "//h3[contains(., 'Guide Price')]" );
		if ( $guide_nodes && $guide_nodes->length > 0 ) {
			$lot['raw_price'] = trim( html_entity_decode( $guide_nodes->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		$icon_block = $xpath->query( "//div[@id='property-icons']" );
		if ( $icon_block && $icon_block->length > 0 ) {
			$lot['auction_raw'] = trim( preg_replace( '/\s+/', ' ', $icon_block->item( 0 )->textContent ) );
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

		$auction_date = $this->normalize_auction_date( (string) ( $raw_item['auction_raw'] ?? '' ) );

		$lot_ref = ! empty( $raw_item['lot_number'] ) ? (string) $raw_item['lot_number'] : md5( $address );

		$desc = sprintf(
			'Auction lot #%s. Guide: %s.',
			$lot_ref,
			! empty( $raw_item['raw_price'] ) ? sanitize_text_field( (string) $raw_item['raw_price'] ) : 'TBC'
		);

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => sanitize_text_field( 'sdl-' . $lot_ref ),
			'address'       => $address,
			'postcode'      => $postcode,
			'price'         => $price,
			'property_type' => 'Auction lot',
			'description'   => $desc,
			'source_url'    => esc_url_raw( $raw_item['source_url'] ?? '' ),
			'auction_date'  => $auction_date,
			'raw_data'      => $raw_item,
		);
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

		return sanitize_text_field( substr( $text, 0, 64 ) );
	}
}
