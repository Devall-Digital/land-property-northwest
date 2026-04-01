<?php
/**
 * OnTheMarket property portal feed.
 *
 * Fetches HTML search pages and reads embedded __NEXT_DATA__ JSON
 * (Redux search results). Falls back to DOM parsing of listing links if needed.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Portal_OnTheMarket extends LPNW_Feed_Base {

	private const BASE_URL = 'https://www.onthemarket.com';

	/**
	 * NW location URL slugs (lowercase) and labels for logging.
	 *
	 * @var array<string, string>
	 */
	private const NW_AREA_SLUGS = array(
		'manchester'  => 'Manchester',
		'liverpool'   => 'Liverpool',
		'bolton'      => 'Bolton',
		'bury'        => 'Bury',
		'oldham'      => 'Oldham',
		'rochdale'    => 'Rochdale',
		'salford'     => 'Salford',
		'stockport'   => 'Stockport',
		'wigan'       => 'Wigan',
		'blackpool'   => 'Blackpool',
		'blackburn'   => 'Blackburn',
		'preston'     => 'Preston',
		'chester'     => 'Chester',
		'warrington'  => 'Warrington',
		'lancaster'   => 'Lancaster',
		'burnley'     => 'Burnley',
		'carlisle'    => 'Carlisle',
	);

	private const REQUEST_HEADERS = array(
		'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
		'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
		'Accept-Language'           => 'en-GB,en;q=0.9',
		'Accept-Encoding'           => 'gzip, deflate, br',
		'Cache-Control'             => 'no-cache',
		'Connection'                => 'keep-alive',
		'Sec-Fetch-Dest'            => 'document',
		'Sec-Fetch-Mode'            => 'navigate',
		'Sec-Fetch-Site'            => 'none',
		'Sec-Fetch-User'            => '?1',
		'Upgrade-Insecure-Requests' => '1',
	);

	public function get_source_name(): string {
		return 'onthemarket';
	}

	/**
	 * Fetch raw listing rows for all NW areas, sale and rent.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch(): array {
		$all    = array();
		$done   = 0;
		$total  = count( self::NW_AREA_SLUGS ) * 2;
		$first  = true;

		foreach ( self::NW_AREA_SLUGS as $slug => $label ) {
			foreach ( array( 'for-sale', 'to-rent' ) as $section ) {
				if ( ! $first ) {
					usleep( wp_rand( 1000000, 2000000 ) );
				}
				$first = false;
				++$done;

				try {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Feed diagnostics.
					error_log(
						sprintf(
							'LPNW OnTheMarket: fetching %s %s [%d/%d]',
							$label,
							$section,
							$done,
							$total
						)
					);

					$rows = $this->fetch_area_section( $slug, $label, $section );
					foreach ( $rows as $row ) {
						$row['_otm_section'] = $section;
						$all[]               = $row;
					}
				} catch ( \Throwable $e ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						'LPNW OnTheMarket: ' . $label . ' ' . $section . ' — ' . $e->getMessage()
					);
				}
			}
		}

		$all = $this->deduplicate( $all );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'LPNW OnTheMarket: finished. Total raw listings: %d',
				count( $all )
			)
		);

		return $all;
	}

	/**
	 * @param string $slug    URL path segment (lowercase).
	 * @param string $label   Human label for logs.
	 * @param string $section for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_area_section( string $slug, string $label, string $section ): array {
		$path     = '/' . $section . '/property/' . rawurlencode( $slug ) . '/';
		$url      = self::BASE_URL . $path;
		$url      = add_query_arg(
			array(
				'sort' => 'recently-added',
			),
			$url
		);
		$headers  = self::REQUEST_HEADERS;
		$headers['Referer'] = self::BASE_URL . '/';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'headers'    => $headers,
				'decompress' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				'LPNW OnTheMarket HTTP error ' . $label . ' ' . $section . ': ' . $response->get_error_message()
			);
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			if ( 403 === $code || 429 === $code ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'LPNW OnTheMarket: HTTP ' . $code . ', backing off 5s' );
				sleep( 5 );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'LPNW OnTheMarket: HTTP %d for %s %s',
						$code,
						$label,
						$section
					)
				);
			}
			return array();
		}

		$listings = $this->extract_from_next_data( $body );

		if ( empty( $listings ) ) {
			$listings = $this->extract_from_html_dom( $body, $section );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'LPNW OnTheMarket: %s %s — extracted %d listings',
				$label,
				$section,
				count( $listings )
			)
		);

		return $listings;
	}

	/**
	 * Parse __NEXT_DATA__ script for Redux results list.
	 *
	 * @param string $html Full HTML document.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_from_next_data( string $html ): array {
		if ( ! preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*type="application\/json"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			if ( ! preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
				return array();
			}
		}

		$data = json_decode( $matches[1], true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'LPNW OnTheMarket: __NEXT_DATA__ JSON decode error: ' . json_last_error_msg() );
			return array();
		}

		$list = $data['props']['initialReduxState']['results']['list'] ?? null;
		if ( ! is_array( $list ) ) {
			$list = $data['props']['pageProps']['initialReduxState']['results']['list'] ?? null;
		}

		if ( ! is_array( $list ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'LPNW OnTheMarket: __NEXT_DATA__ present but no results.list path' );
			return array();
		}

		return $list;
	}

	/**
	 * Fallback: approximate listings from /details/{id}/ anchors.
	 *
	 * @param string $html    HTML body.
	 * @param string $section for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_from_html_dom( string $html, string $section ): array {
		$out = array();
		$dom = new \DOMDocument();
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
		$xpath   = new \DOMXPath( $dom );
		$anchors = $xpath->query( "//a[contains(@href,'/details/')]" );

		if ( ! $anchors || 0 === $anchors->length ) {
			return $out;
		}

		$seen = array();

		foreach ( $anchors as $a ) {
			if ( ! $a instanceof \DOMElement ) {
				continue;
			}
			$href = $a->getAttribute( 'href' );
			if ( ! preg_match( '#/details/(\d+)/#', $href, $mid ) ) {
				continue;
			}
			$pid = $mid[1];
			if ( isset( $seen[ $pid ] ) ) {
				continue;
			}
			$seen[ $pid ] = true;

			$address = '';
			for ( $p = $a->parentNode; $p && $p instanceof \DOMElement; $p = $p->parentNode ) {
				$text = trim( preg_replace( '/\s+/', ' ', $p->textContent ) );
				if ( strlen( $text ) > 15 && strlen( $text ) < 400 ) {
					$address = $text;
					break;
				}
			}

			$out[] = array(
				'id'                      => $pid,
				'address'                 => $address,
				'details-url'             => $href,
				'price'                   => '',
				'humanised-property-type' => '',
				'_dom_fallback'           => true,
				'_otm_section'            => $section,
			);
		}

		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function deduplicate( array $rows ): array {
		$seen = array();
		$uniq = array();

		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$uniq[]      = $row;
		}

		return $uniq;
	}

	/**
	 * @param array<string, mixed> $raw_item Row from JSON or DOM fallback.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$address = '';
		if ( isset( $raw_item['address'] ) && is_string( $raw_item['address'] ) ) {
			$address = sanitize_text_field( $raw_item['address'] );
		}

		if ( '' === $address ) {
			return array();
		}

		$postcode = $this->extract_postcode_from_address( $address );
		if ( '' !== $postcode && ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		$otm_id = sanitize_text_field( (string) ( $raw_item['id'] ?? '' ) );
		if ( '' === $otm_id ) {
			return array();
		}

		$details = $raw_item['details-url'] ?? '';
		if ( is_string( $details ) && '' !== $details ) {
			$path = $details;
			if ( str_starts_with( $path, 'http' ) ) {
				$property_url = $path;
			} else {
				$property_url = self::BASE_URL . $path;
			}
		} else {
			$property_url = self::BASE_URL . '/details/' . $otm_id . '/';
		}

		$price_raw = $raw_item['price'] ?? $raw_item['short-price'] ?? '';
		$price     = 0;
		if ( is_string( $price_raw ) || is_numeric( $price_raw ) ) {
			$price = absint( preg_replace( '/[^0-9]/', '', (string) $price_raw ) );
		}

		$prop_type = '';
		if ( isset( $raw_item['humanised-property-type'] ) && is_string( $raw_item['humanised-property-type'] ) ) {
			$prop_type = sanitize_text_field( $raw_item['humanised-property-type'] );
		}

		$lat = null;
		$lng = null;
		if ( isset( $raw_item['location'] ) && is_array( $raw_item['location'] ) ) {
			if ( isset( $raw_item['location']['lat'] ) ) {
				$lat = floatval( $raw_item['location']['lat'] );
			}
			if ( isset( $raw_item['location']['lon'] ) ) {
				$lng = floatval( $raw_item['location']['lon'] );
			}
		}

		$beds = isset( $raw_item['bedrooms'] ) ? absint( $raw_item['bedrooms'] ) : null;
		$baths = isset( $raw_item['bathrooms'] ) ? absint( $raw_item['bathrooms'] ) : null;

		$section = $raw_item['_otm_section'] ?? '';
		$channel = 'to-rent' === $section ? 'To let' : 'For sale';

		$desc_parts = array();
		if ( isset( $raw_item['property-title'] ) && is_string( $raw_item['property-title'] ) ) {
			$desc_parts[] = sanitize_text_field( $raw_item['property-title'] );
		}
		if ( $beds ) {
			$desc_parts[] = $beds . ' bed';
		}
		if ( $baths ) {
			$desc_parts[] = $baths . ' bath';
		}
		if ( $prop_type ) {
			$desc_parts[] = strtolower( $prop_type );
		}
		if ( ! empty( $raw_item['features'] ) && is_array( $raw_item['features'] ) ) {
			$feat = array_slice( $raw_item['features'], 0, 3 );
			$feat = array_map( 'sanitize_text_field', $feat );
			$feat = array_filter( $feat );
			if ( ! empty( $feat ) ) {
				$desc_parts[] = implode( '; ', $feat );
			}
		}
		$desc_parts[] = $channel;

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => 'otm-' . $otm_id,
			'address'       => $address,
			'postcode'      => $postcode,
			'latitude'      => $lat,
			'longitude'     => $lng,
			'price'         => $price > 0 ? $price : null,
			'property_type' => $prop_type,
			'description'   => implode( '. ', $desc_parts ),
			'source_url'    => esc_url_raw( $property_url ),
			'raw_data'      => $raw_item,
		);
	}

	/**
	 * @param string $address Full address line.
	 */
	private function extract_postcode_from_address( string $address ): string {
		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $matches ) ) {
			return strtoupper( trim( $matches[1] ) );
		}

		foreach ( LPNW_NW_POSTCODES as $prefix ) {
			if ( preg_match( '/\b(' . preg_quote( $prefix, '/' ) . '\d[A-Z\d]?\s*\d[A-Z]{2})\b/i', $address, $m ) ) {
				return strtoupper( trim( $m[1] ) );
			}
		}

		return '';
	}
}
