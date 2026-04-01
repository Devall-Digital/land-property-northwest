<?php
/**
 * Rightmove property portal feed.
 *
 * Fetches Rightmove's HTML search results pages and extracts the embedded
 * __NEXT_DATA__ JSON to get property listings across Northwest England.
 * Checks every 15 minutes for new-to-market properties (sales and rentals).
 *
 * Uses individual NW city/area region IDs rather than a broad region to
 * maximise coverage and avoid Rightmove's server-side request blocking
 * on the internal JSON API.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Portal_Rightmove extends LPNW_Feed_Base {

	private const SEARCH_BASE_BUY  = 'https://www.rightmove.co.uk/property-for-sale/find.html';
	private const SEARCH_BASE_RENT = 'https://www.rightmove.co.uk/property-to-rent/find.html';

	/**
	 * NW city/area REGION IDs verified from Rightmove's own search pages.
	 *
	 * Key: numeric region ID used in locationIdentifier=REGION%5E{id}
	 * Value: human-readable area name for logging.
	 */
	private const NW_AREA_REGIONS = array(
		904   => 'Manchester',
		813   => 'Liverpool',
		1097  => 'Preston',
		313   => 'Chester',
		168   => 'Blackpool',
		167   => 'Blackburn',
		252   => 'Burnley',
		1452  => 'Wigan',
		182   => 'Bolton',
		257   => 'Bury',
		1025  => 'Oldham',
		1134  => 'Rochdale',
		1164  => 'Salford',
		1268  => 'Stockport',
		61235 => 'Tameside',
		61424 => 'Trafford',
		1403  => 'Warrington',
		769   => 'Lancaster',
		283   => 'Carlisle',
	);

	private const REQUEST_HEADERS = array(
		'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
		'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
		'Accept-Language'  => 'en-GB,en;q=0.9',
		'Accept-Encoding'  => 'gzip, deflate, br',
		'Cache-Control'    => 'no-cache',
		'Connection'       => 'keep-alive',
		'Sec-Fetch-Dest'   => 'document',
		'Sec-Fetch-Mode'   => 'navigate',
		'Sec-Fetch-Site'   => 'none',
		'Sec-Fetch-User'   => '?1',
		'Upgrade-Insecure-Requests' => '1',
	);

	public function get_source_name(): string {
		return 'rightmove';
	}

	protected function fetch(): array {
		$all_properties = array();
		$area_count     = 0;
		$total_areas    = count( self::NW_AREA_REGIONS ) * 2;

		foreach ( self::NW_AREA_REGIONS as $region_id => $area_name ) {
			foreach ( array( 'BUY', 'RENT' ) as $channel ) {
				++$area_count;

				if ( $area_count > 1 ) {
					$delay = wp_rand( 1000000, 2000000 );
					usleep( $delay );
				}

				error_log(
					sprintf(
						'LPNW Rightmove: fetching %s %s (region %d) [%d/%d]',
						$area_name,
						$channel,
						$region_id,
						$area_count,
						$total_areas
					)
				);

				$properties     = $this->fetch_search( $region_id, $area_name, $channel, 0 );
				$all_properties = array_merge( $all_properties, $properties );

				if ( count( $properties ) >= 24 ) {
					usleep( wp_rand( 800000, 1500000 ) );
					$page2          = $this->fetch_search( $region_id, $area_name, $channel, 24 );
					$all_properties = array_merge( $all_properties, $page2 );
				}
			}
		}

		$all_properties = $this->deduplicate( $all_properties );

		error_log(
			sprintf(
				'LPNW Rightmove: finished all areas. Total properties: %d',
				count( $all_properties )
			)
		);

		return $all_properties;
	}

	/**
	 * Fetch one page of HTML search results and extract properties from
	 * the embedded __NEXT_DATA__ JSON.
	 *
	 * @param int    $region_id Rightmove numeric region ID.
	 * @param string $area_name Human-readable area name for logging.
	 * @param string $channel   BUY or RENT.
	 * @param int    $index     Pagination offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_search( int $region_id, string $area_name, string $channel, int $index ): array {
		$base_url = 'BUY' === $channel ? self::SEARCH_BASE_BUY : self::SEARCH_BASE_RENT;

		$url = add_query_arg(
			array(
				'locationIdentifier'        => 'REGION^' . $region_id,
				'sortType'                  => 6,
				'numberOfPropertiesPerPage' => 24,
				'index'                     => $index,
				'propertyTypes'             => '',
				'includeSSTC'               => 'false',
				'mustHave'                  => '',
				'dontShow'                  => '',
				'furnishTypes'              => '',
				'keywords'                  => '',
			),
			$base_url
		);

		$headers            = self::REQUEST_HEADERS;
		$headers['Referer'] = 'https://www.rightmove.co.uk/';

		$response = wp_remote_get( $url, array(
			'timeout'    => 30,
			'headers'    => $headers,
			'decompress' => true,
		) );

		if ( is_wp_error( $response ) ) {
			error_log(
				sprintf(
					'LPNW Rightmove: HTTP error for %s %s (region %d): %s',
					$area_name,
					$channel,
					$region_id,
					$response->get_error_message()
				)
			);
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		error_log(
			sprintf(
				'LPNW Rightmove: %s %s (region %d) index %d - HTTP %d, body length %d',
				$area_name,
				$channel,
				$region_id,
				$index,
				$code,
				strlen( $body )
			)
		);

		if ( 200 !== $code ) {
			if ( 403 === $code || 429 === $code ) {
				error_log( 'LPNW Rightmove: rate limited or blocked, backing off 5s' );
				sleep( 5 );
			}
			return array();
		}

		$data = $this->extract_properties_from_html( $body, $area_name, $channel, $region_id );

		foreach ( $data as &$item ) {
			if ( empty( $item['channel'] ) ) {
				$item['channel'] = $channel;
			}
		}
		unset( $item );

		error_log(
			sprintf(
				'LPNW Rightmove: %s %s (region %d) - extracted %d properties',
				$area_name,
				$channel,
				$region_id,
				count( $data )
			)
		);

		return $data;
	}

	/**
	 * Extract property data from Rightmove HTML search results page.
	 *
	 * Rightmove embeds a __NEXT_DATA__ JSON blob in a script tag containing
	 * the full search results. Falls back to __PRELOADED_STATE__ if present.
	 *
	 * @param string $html      Raw HTML response body.
	 * @param string $area_name Area name for logging.
	 * @param string $channel   BUY or RENT.
	 * @param int    $region_id Region ID for logging.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_properties_from_html( string $html, string $area_name, string $channel, int $region_id ): array {
		if ( preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			error_log(
				sprintf(
					'LPNW Rightmove: found __NEXT_DATA__ in HTML for %s %s (region %d), JSON length %d',
					$area_name,
					$channel,
					$region_id,
					strlen( $matches[1] )
				)
			);

			$next_data = json_decode( $matches[1], true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				error_log( 'LPNW Rightmove: JSON decode error: ' . json_last_error_msg() );
				return array();
			}

			if ( isset( $next_data['props']['pageProps']['properties'] ) ) {
				return $next_data['props']['pageProps']['properties'];
			}

			if ( isset( $next_data['props']['pageProps']['searchResults']['properties'] ) ) {
				return $next_data['props']['pageProps']['searchResults']['properties'];
			}

			error_log( 'LPNW Rightmove: __NEXT_DATA__ found but no properties key at expected paths' );
			return array();
		}

		if ( preg_match( '/window\.__PRELOADED_STATE__\s*=\s*({.*?});/s', $html, $matches ) ) {
			error_log( 'LPNW Rightmove: using __PRELOADED_STATE__ fallback' );
			$preloaded = json_decode( $matches[1], true );

			if ( $preloaded && isset( $preloaded['searchResults']['properties'] ) ) {
				return $preloaded['searchResults']['properties'];
			}
		}

		$has_script_tag = ( strpos( $html, '__NEXT_DATA__' ) !== false );
		$has_preloaded  = ( strpos( $html, '__PRELOADED_STATE__' ) !== false );

		error_log(
			sprintf(
				'LPNW Rightmove: no property data found in HTML for %s %s (region %d). __NEXT_DATA__ tag present: %s, __PRELOADED_STATE__ present: %s, HTML snippet: %s',
				$area_name,
				$channel,
				$region_id,
				$has_script_tag ? 'yes' : 'no',
				$has_preloaded ? 'yes' : 'no',
				substr( $html, 0, 500 )
			)
		);

		return array();
	}

	/**
	 * Remove duplicate properties that may appear in multiple area searches.
	 *
	 * @param array<int, array<string, mixed>> $properties Raw property list.
	 * @return array<int, array<string, mixed>>
	 */
	private function deduplicate( array $properties ): array {
		$seen   = array();
		$unique = array();

		foreach ( $properties as $prop ) {
			$id = $prop['id'] ?? null;
			if ( null === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$unique[]    = $prop;
		}

		$dupes_removed = count( $properties ) - count( $unique );
		if ( $dupes_removed > 0 ) {
			error_log(
				sprintf( 'LPNW Rightmove: deduplicated %d duplicate properties', $dupes_removed )
			);
		}

		return $unique;
	}

	/**
	 * @param array<string, mixed> $raw_item Raw property from Rightmove JSON.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$address  = sanitize_text_field( $raw_item['displayAddress'] ?? '' );
		$postcode = $this->extract_postcode_from_address( $address );

		if ( ! empty( $postcode ) && ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		$price = 0;
		if ( isset( $raw_item['price']['amount'] ) ) {
			$price = absint( $raw_item['price']['amount'] );
		} elseif ( isset( $raw_item['price']['displayPrices'][0]['displayPrice'] ) ) {
			$price_str = $raw_item['price']['displayPrices'][0]['displayPrice'];
			$price     = absint( preg_replace( '/[^0-9]/', '', $price_str ) );
		}

		$property_url = '';
		if ( ! empty( $raw_item['propertyUrl'] ) ) {
			$property_url = 'https://www.rightmove.co.uk' . $raw_item['propertyUrl'];
		}

		$rm_id = sanitize_text_field( (string) ( $raw_item['id'] ?? '' ) );
		if ( empty( $rm_id ) ) {
			return array();
		}

		$lat = null;
		$lng = null;
		if ( isset( $raw_item['location']['latitude'] ) ) {
			$lat = floatval( $raw_item['location']['latitude'] );
			$lng = floatval( $raw_item['location']['longitude'] );
		}

		$beds  = isset( $raw_item['bedrooms'] ) ? absint( $raw_item['bedrooms'] ) : null;
		$baths = isset( $raw_item['bathrooms'] ) ? absint( $raw_item['bathrooms'] ) : null;
		$type  = sanitize_text_field( $raw_item['propertySubType'] ?? $raw_item['propertyTypeFullDescription'] ?? '' );

		$desc_parts = array();
		if ( $beds ) {
			$desc_parts[] = $beds . ' bed';
		}
		if ( $baths ) {
			$desc_parts[] = $baths . ' bath';
		}
		if ( $type ) {
			$desc_parts[] = strtolower( $type );
		}
		if ( ! empty( $raw_item['summary'] ) ) {
			$desc_parts[] = wp_trim_words( $raw_item['summary'], 20, '...' );
		}

		$channel = '';
		if ( ! empty( $raw_item['channel'] ) ) {
			$channel = 'RENT' === strtoupper( $raw_item['channel'] ) ? 'To let' : 'For sale';
		}
		if ( $channel ) {
			$desc_parts[] = $channel;
		}

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => 'rm-' . $rm_id,
			'address'       => $address,
			'postcode'      => $postcode,
			'latitude'      => $lat,
			'longitude'     => $lng,
			'price'         => $price > 0 ? $price : null,
			'property_type' => $type,
			'description'   => implode( '. ', $desc_parts ),
			'source_url'    => esc_url_raw( $property_url ),
			'raw_data'      => $raw_item,
		);
	}

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
