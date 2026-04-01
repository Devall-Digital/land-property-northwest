<?php
/**
 * Pugh Auctions data feed.
 *
 * Scrapes upcoming auction lots from pugh-auctions.com.
 * Pugh is a major NW-focused property auction house.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Auction_Pugh extends LPNW_Feed_Base {

	private const CATALOGUE_URL = 'https://www.pugh-auctions.com/lots';

	public function get_source_name(): string {
		return 'auction_pugh';
	}

	protected function fetch(): array {
		$response = wp_remote_get(
			self::CATALOGUE_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent'      => 'Mozilla/5.0 (compatible; LPNW-PropertyAlerts/1.0; +https://land-property-northwest.co.uk)',
					'Accept'          => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
					'Accept-Encoding' => 'gzip, deflate',
					'Accept-Language' => 'en-GB,en;q=0.9',
				),
				'decompress' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->lpnw_diag_log( 'WP_Error: ' . $response->get_error_message(), 0, 0 );
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$html = wp_remote_retrieve_body( $response );
		$html = is_string( $html ) ? $html : '';
		$len  = strlen( $html );

		if ( 200 !== $code ) {
			$this->lpnw_diag_log(
				sprintf( 'non-200 response for catalogue %s; skipping parse', self::CATALOGUE_URL ),
				$code,
				$len
			);
			return array();
		}

		$lots = $this->extract_lots( $html );

		if ( array() === $lots && $len > 400 ) {
			$this->lpnw_diag_log(
				'HTTP 200 but no lot cards matched XPath; Pugh site HTML structure may have changed (check lot-card / lot selectors).',
				$code,
				$len
			);
		} elseif ( array() === $lots && $len <= 400 ) {
			$this->lpnw_diag_log(
				'HTTP 200 but body very small; possible block or error page.',
				$code,
				$len
			);
		}

		return $lots;
	}

	/**
	 * @param string $message   Context.
	 * @param int    $http_code HTTP status or 0.
	 * @param int    $resp_len  Body length.
	 */
	private function lpnw_diag_log( string $message, int $http_code, int $resp_len ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational feed diagnostics.
		error_log(
			sprintf(
				'[LPNW feed=%s] ts=%s http=%d len=%d %s',
				$this->get_source_name(),
				gmdate( 'c' ),
				$http_code,
				$resp_len,
				$message
			)
		);
	}

	/**
	 * Extract lot data from the catalogue HTML.
	 *
	 * @param string $html Raw HTML from the lots page.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_lots( string $html ): array {
		$lots = array();

		if ( empty( $html ) ) {
			return $lots;
		}

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new \DOMXPath( $dom );

		$lot_nodes = $xpath->query( "//div[contains(@class, 'lot-card')] | //article[contains(@class, 'lot')]" );

		if ( ! $lot_nodes || 0 === $lot_nodes->length ) {
			return $lots;
		}

		foreach ( $lot_nodes as $node ) {
			$lot = $this->parse_lot_node( $node, $xpath );
			if ( ! empty( $lot['address'] ) ) {
				$lots[] = $lot;
			}
		}

		return $lots;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parse_lot_node( \DOMNode $node, \DOMXPath $xpath ): array {
		$title_node = $xpath->query( ".//h2 | .//h3 | .//a[contains(@class, 'title')]", $node );
		$price_node = $xpath->query( ".//*[contains(@class, 'guide-price')] | .//*[contains(@class, 'price')]", $node );
		$link_node  = $xpath->query( ".//a[contains(@href, '/lot/')]", $node );
		$lot_node   = $xpath->query( ".//*[contains(@class, 'lot-number')]", $node );

		$address = $title_node->length ? trim( $title_node->item( 0 )->textContent ) : '';
		$price   = $price_node->length ? trim( $price_node->item( 0 )->textContent ) : '';
		$link    = $link_node->length ? $link_node->item( 0 )->getAttribute( 'href' ) : '';
		$lot_num = $lot_node->length ? trim( $lot_node->item( 0 )->textContent ) : '';

		$price_clean = preg_replace( '/[^0-9]/', '', $price );

		if ( $link && ! str_starts_with( $link, 'http' ) ) {
			$link = 'https://www.pugh-auctions.com' . $link;
		}

		return array(
			'address'    => $address,
			'price'      => $price_clean,
			'source_url' => $link,
			'lot_number' => $lot_num,
			'raw_price'  => $price,
		);
	}

	/**
	 * @param array<string, mixed> $raw_item Extracted lot data.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$address  = sanitize_text_field( $raw_item['address'] ?? '' );
		$postcode = '';

		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $matches ) ) {
			$postcode = strtoupper( $matches[1] );
		}

		if ( ! empty( $postcode ) && ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => sanitize_text_field( 'pugh-' . ( $raw_item['lot_number'] ?? md5( $address ) ) ),
			'address'       => $address,
			'postcode'      => $postcode,
			'price'         => absint( $raw_item['price'] ?? 0 ),
			'property_type' => 'Auction lot',
			'description'   => sprintf(
				'Auction lot%s. Guide price: %s.',
				! empty( $raw_item['lot_number'] ) ? ' #' . $raw_item['lot_number'] : '',
				! empty( $raw_item['raw_price'] ) ? $raw_item['raw_price'] : 'TBC'
			),
			'source_url'    => esc_url_raw( $raw_item['source_url'] ?? '' ),
			'raw_data'      => $raw_item,
		);
	}
}
