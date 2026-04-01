<?php
/**
 * Rightmove property portal feed.
 *
 * Monitors Rightmove's internal search API for new property listings
 * across Northwest England. Checks every 15 minutes for new-to-market
 * properties (sales and rentals).
 *
 * Uses the same JSON endpoints that Rightmove's own website calls.
 * No authentication required.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Portal_Rightmove extends LPNW_Feed_Base {

	private const API_BASE = 'https://www.rightmove.co.uk/api/_search';

	/**
	 * Rightmove OUTCODE location identifiers for NW postcode areas.
	 *
	 * Format: OUTCODE^{id} where id is Rightmove's internal numeric code.
	 * These are resolved from Rightmove's typeahead/search system.
	 *
	 * We also include the broad "North West England" region as a catch-all.
	 */
	private const NW_SEARCHES = array(
		'REGION^92812',  // North West England (broad)
	);

	/**
	 * Fallback: search by individual NW city/area REGION IDs.
	 * Used if the broad region returns too many or too few results.
	 */
	private const NW_AREA_REGIONS = array(
		'REGION^904'  => 'Manchester',
		'REGION^813'  => 'Liverpool',
		'REGION^1097' => 'Preston',
		'REGION^313'  => 'Chester',
		'REGION^168'  => 'Blackpool',
		'REGION^167'  => 'Blackburn',
		'REGION^252'  => 'Burnley',
		'REGION^1452' => 'Wigan',
	);

	public function get_source_name(): string {
		return 'rightmove';
	}

	protected function fetch(): array {
		$all_properties = array();

		foreach ( array( 'BUY', 'RENT' ) as $channel ) {
			$properties     = $this->fetch_channel( $channel );
			$all_properties = array_merge( $all_properties, $properties );
		}

		return $all_properties;
	}

	/**
	 * Fetch newest listings for a channel (BUY or RENT) across the NW region.
	 *
	 * @param string $channel BUY or RENT.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_channel( string $channel ): array {
		$results = array();

		$location = 'REGION^92812';
		$pages    = $this->fetch_search( $location, $channel, 0 );
		$results  = array_merge( $results, $pages );

		if ( count( $pages ) >= 24 ) {
			usleep( 500000 );
			$page2   = $this->fetch_search( $location, $channel, 24 );
			$results = array_merge( $results, $page2 );
		}

		usleep( 300000 );

		return $results;
	}

	/**
	 * Make a single search API request.
	 *
	 * @param string $location_id Rightmove location identifier.
	 * @param string $channel     BUY or RENT.
	 * @param int    $index       Pagination offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_search( string $location_id, string $channel, int $index ): array {
		$url = add_query_arg(
			array(
				'locationIdentifier'       => $location_id,
				'numberOfPropertiesPerPage' => 24,
				'radius'                    => '0.0',
				'sortType'                  => 6,
				'index'                     => $index,
				'includeSSTC'               => 'false',
				'viewType'                  => 'LIST',
				'channel'                   => $channel,
				'areaSizeUnit'              => 'sqft',
				'currencyCode'              => 'GBP',
				'isFetching'                => 'false',
				'viewport'                  => '',
			),
			self::API_BASE
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 30,
			'headers' => array(
				'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
				'Accept'          => 'application/json, text/javascript, */*; q=0.01',
				'Accept-Language' => 'en-GB,en;q=0.9',
				'Referer'         => 'https://www.rightmove.co.uk/property-for-sale/find.html?locationIdentifier=' . rawurlencode( $location_id ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'LPNW Rightmove feed error: ' . $response->get_error_message() );
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			error_log( 'LPNW Rightmove feed HTTP ' . $code . ' for ' . $location_id . ' ' . $channel );

			if ( 403 === $code || 429 === $code ) {
				sleep( 5 );
			}

			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || json_last_error() !== JSON_ERROR_NONE ) {
			$data = $this->try_extract_from_html( $body );
		}

		return $data['properties'] ?? array();
	}

	/**
	 * If the API returns HTML instead of JSON (redirect to search page),
	 * try to extract the embedded Next.js JSON data.
	 *
	 * @param string $html Raw HTML body.
	 * @return array<string, mixed>
	 */
	private function try_extract_from_html( string $html ): array {
		if ( preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			$next_data = json_decode( $matches[1], true );
			if ( $next_data && isset( $next_data['props']['pageProps']['searchResults']['properties'] ) ) {
				return array( 'properties' => $next_data['props']['pageProps']['searchResults']['properties'] );
			}
		}

		if ( preg_match( '/window\.__PRELOADED_STATE__\s*=\s*({.*?});/s', $html, $matches ) ) {
			$preloaded = json_decode( $matches[1], true );
			if ( $preloaded && isset( $preloaded['searchResults']['properties'] ) ) {
				return array( 'properties' => $preloaded['searchResults']['properties'] );
			}
		}

		return array();
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
