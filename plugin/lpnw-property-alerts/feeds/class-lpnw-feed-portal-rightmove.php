<?php
/**
 * Rightmove property portal feed.
 *
 * Fetches Rightmove's HTML search results pages and extracts the embedded
 * __NEXT_DATA__ JSON to get property listings across Northwest England.
 * Invoked from lpnw_cron_portals with other portals (typically every 15 minutes). Each run
 * processes a batch of region/channel pairs (see cursor option lpnw_rightmove_cursor) so
 * execution stays within shared-hosting time limits. If WordPress is only woken every 15 minutes,
 * a faster cron schedule alone does not shorten discovery time; do more work per tick instead.
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

	/**
	 * WordPress option storing the zero-based index of the last processed
	 * region+channel pair. Next run continues at (last + 1) % total_pairs.
	 */
	private const OPTION_CURSOR = 'lpnw_rightmove_cursor';

	/**
	 * Stop starting new pairs if elapsed time approaches this (seconds).
	 * Leaves room for parsing, deduplication, and shutdown.
	 */
	private const TIME_BUDGET_SECONDS = 28.0;

	/**
	 * Base headers for HTML search requests (User-Agent added per request via rotation).
	 */
	private const REQUEST_HEADERS_BASE = array(
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

	/**
	 * Realistic browser User-Agent strings rotated per request to reduce pattern-based blocking.
	 *
	 * @var array<int, string>
	 */
	private const REQUEST_USER_AGENTS = array(
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
	);

	public function get_source_name(): string {
		return 'rightmove';
	}

	/**
	 * Max region+channel pairs to process in one fetch() invocation.
	 *
	 * @return int Positive count.
	 */
	protected function get_batch_size(): int {
		return 8;
	}

	/**
	 * Flat list of region/channel pairs in stable crawl order.
	 *
	 * @return array<int, array{region_id: int, area_name: string, channel: string}>
	 */
	private function get_region_channel_pairs(): array {
		$pairs = array();

		foreach ( self::NW_AREA_REGIONS as $region_id => $area_name ) {
			foreach ( array( 'BUY', 'RENT' ) as $channel ) {
				$pairs[] = array(
					'region_id' => $region_id,
					'area_name' => $area_name,
					'channel'   => $channel,
				);
			}
		}

		return $pairs;
	}

	protected function fetch(): array {
		$all_properties = array();
		$pairs          = $this->get_region_channel_pairs();
		$total_pairs    = count( $pairs );

		if ( $total_pairs < 1 ) {
			return array();
		}

		$last_processed = (int) get_option( self::OPTION_CURSOR, -1 );
		if ( $last_processed < -1 || $last_processed >= $total_pairs ) {
			$last_processed = -1;
		}

		$start_index  = ( $last_processed + 1 ) % $total_pairs;
		$batch_size   = $this->get_batch_size();
		$time_started = microtime( true );
		$processed    = 0;
		$new_last     = $last_processed;

		for ( $n = 0; $n < $batch_size; $n++ ) {
			if ( ( microtime( true ) - $time_started ) >= self::TIME_BUDGET_SECONDS ) {
				error_log( 'LPNW Rightmove: stopping batch early (time budget)' );
				break;
			}

			$idx  = ( $start_index + $n ) % $total_pairs;
			$pair = $pairs[ $idx ];

			if ( $processed > 0 ) {
				usleep( wp_rand( 1000000, 2000000 ) );
			}

			if ( ( microtime( true ) - $time_started ) >= self::TIME_BUDGET_SECONDS ) {
				error_log( 'LPNW Rightmove: stopping batch early (time budget before fetch)' );
				break;
			}

			error_log(
				sprintf(
					'LPNW Rightmove: fetching %s %s (region %d) [pair %d/%d, batch %d/%d]',
					$pair['area_name'],
					$pair['channel'],
					$pair['region_id'],
					$idx + 1,
					$total_pairs,
					$processed + 1,
					$batch_size
				)
			);

			$properties     = $this->fetch_search( $pair['region_id'], $pair['area_name'], $pair['channel'], 0 );
			$all_properties = array_merge( $all_properties, $properties );

			if ( count( $properties ) >= 24 ) {
				if ( ( microtime( true ) - $time_started ) >= self::TIME_BUDGET_SECONDS ) {
					error_log( 'LPNW Rightmove: skipping page 2 (time budget); will retry pair next run' );
					$new_last = $idx - 1;
					update_option( self::OPTION_CURSOR, $new_last, false );
					break;
				}
				usleep( wp_rand( 800000, 1500000 ) );
				if ( ( microtime( true ) - $time_started ) >= self::TIME_BUDGET_SECONDS ) {
					error_log( 'LPNW Rightmove: skipping page 2 (time budget after delay); will retry pair next run' );
					$new_last = $idx - 1;
					update_option( self::OPTION_CURSOR, $new_last, false );
					break;
				}
				$page2          = $this->fetch_search( $pair['region_id'], $pair['area_name'], $pair['channel'], 24 );
				$all_properties = array_merge( $all_properties, $page2 );
			}

			$new_last = $idx;
			update_option( self::OPTION_CURSOR, $new_last, false );
			++$processed;
		}

		$all_properties = $this->deduplicate( $all_properties );

		$next_idx = ( $new_last + 1 ) % $total_pairs;
		error_log(
			sprintf(
				'LPNW Rightmove: batch done. Pairs this run: %d, last index: %d, next start: %d, properties: %d',
				$processed,
				$new_last,
				$next_idx,
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

		$headers            = $this->get_search_request_headers();
		$headers['Referer'] = 'https://www.rightmove.co.uk/';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'headers'    => $headers,
				'decompress' => true,
			)
		);

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

		if ( ! is_array( $data ) ) {
			return array();
		}
		if ( isset( $data['properties'] ) ) {
			if ( ! is_array( $data['properties'] ) ) {
				return array();
			}
			$data = $data['properties'];
		}

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

		$lat = null;
		$lng = null;
		if ( isset( $raw_item['location']['latitude'], $raw_item['location']['longitude'] ) ) {
			$lat = floatval( $raw_item['location']['latitude'] );
			$lng = floatval( $raw_item['location']['longitude'] );
		}

		if ( '' === $postcode && null !== $lat && null !== $lng && is_finite( $lat ) && is_finite( $lng ) ) {
			$resolved = LPNW_Geocoder::reverse_geocode( $lat, $lng );
			if ( null !== $resolved && '' !== $resolved ) {
				$postcode = $resolved;
			}
		}

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

		$beds  = isset( $raw_item['bedrooms'] ) ? absint( $raw_item['bedrooms'] ) : null;
		$baths = isset( $raw_item['bathrooms'] ) ? absint( $raw_item['bathrooms'] ) : null;
		$type  = sanitize_text_field( $raw_item['propertySubType'] ?? $raw_item['propertyTypeFullDescription'] ?? '' );

		$channel_raw = strtoupper( (string) ( $raw_item['channel'] ?? '' ) );
		$price_freq  = strtolower( (string) ( $raw_item['price']['frequency'] ?? '' ) );

		$application_type = 'sale';
		$desc_prefix      = 'For sale. ';
		if ( 'RENT' === $channel_raw || 'monthly' === $price_freq ) {
			$application_type = 'rent';
			$desc_prefix      = 'To let. ';
		}

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

		$listed_label = $this->format_rightmove_listed_date_label( $raw_item );
		if ( '' !== $listed_label ) {
			$desc_parts[] = 'Listed: ' . $listed_label;
		}

		$agent_name = $this->extract_rightmove_agent_name( $raw_item );
		if ( '' !== $agent_name ) {
			$desc_parts[] = 'Agent: ' . $agent_name;
		}

		$features_text = $this->extract_rightmove_key_features_text( $raw_item );
		if ( '' !== $features_text ) {
			$desc_parts[] = $features_text;
		}

		if ( ! empty( $raw_item['summary'] ) ) {
			$desc_parts[] = wp_trim_words( $raw_item['summary'], 20, '...' );
		}

		$description = $desc_prefix . implode( '. ', $desc_parts );

		$out = array(
			'source'           => $this->get_source_name(),
			'source_ref'       => 'rm-' . $rm_id,
			'address'          => $address,
			'postcode'         => $postcode,
			'latitude'         => $lat,
			'longitude'        => $lng,
			'price'            => $price > 0 ? $price : null,
			'property_type'    => $type,
			'description'      => $description,
			'application_type' => $application_type,
			'source_url'       => esc_url_raw( $property_url ),
			'raw_data'         => $raw_item,
		);

		if ( isset( $raw_item['bedrooms'] ) ) {
			$out['bedrooms'] = absint( $raw_item['bedrooms'] );
		}
		if ( isset( $raw_item['bathrooms'] ) ) {
			$out['bathrooms'] = absint( $raw_item['bathrooms'] );
		}
		if ( isset( $raw_item['tenure'] ) && is_array( $raw_item['tenure'] ) && isset( $raw_item['tenure']['tenureType'] ) && is_scalar( $raw_item['tenure']['tenureType'] ) ) {
			$tt = strtolower( trim( (string) $raw_item['tenure']['tenureType'] ) );
			if ( '' !== $tt ) {
				$out['tenure_type'] = sanitize_text_field( $tt );
			}
		}
		if ( '' !== $price_freq ) {
			$out['price_frequency'] = sanitize_text_field( $price_freq );
		}
		$sqft = $this->parse_rightmove_display_size_sqft( $raw_item );
		if ( null !== $sqft ) {
			$out['floor_area_sqft'] = $sqft;
		}
		$listed_ymd = $this->parse_rightmove_first_listed_date_ymd( $raw_item );
		if ( '' !== $listed_ymd ) {
			$out['first_listed_date'] = $listed_ymd;
		}
		if ( '' !== $agent_name ) {
			$out['agent_name'] = $agent_name;
		}
		$key_pipe = $this->extract_rightmove_key_features_pipe( $raw_item );
		if ( '' !== $key_pipe ) {
			$out['key_features_text'] = $key_pipe;
		}

		return $out;
	}

	/**
	 * Headers for a single search HTML request, including a rotated User-Agent.
	 *
	 * @return array<string, string>
	 */
	private function get_search_request_headers(): array {
		$headers               = self::REQUEST_HEADERS_BASE;
		$headers['User-Agent'] = self::REQUEST_USER_AGENTS[ wp_rand( 0, count( self::REQUEST_USER_AGENTS ) - 1 ) ];
		return $headers;
	}

	/**
	 * Build DD/MM/YYYY label from firstVisibleDate or listingUpdate.listingUpdateDate.
	 *
	 * @param array<string, mixed> $raw_item Raw property from Rightmove JSON.
	 */
	private function format_rightmove_listed_date_label( array $raw_item ): string {
		$raw = $raw_item['firstVisibleDate'] ?? '';
		if ( ( '' === $raw || null === $raw ) && isset( $raw_item['listingUpdate'] ) && is_array( $raw_item['listingUpdate'] ) ) {
			$raw = $raw_item['listingUpdate']['listingUpdateDate'] ?? '';
		}
		if ( '' === $raw || null === $raw ) {
			return '';
		}
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$str = trim( (string) $raw );
		if ( '' === $str ) {
			return '';
		}
		$ts = strtotime( $str );
		if ( false === $ts ) {
			return '';
		}
		return wp_date( 'd/m/Y', $ts );
	}

	/**
	 * Estate agent display name from customer branch fields.
	 *
	 * @param array<string, mixed> $raw_item Raw property from Rightmove JSON.
	 */
	private function extract_rightmove_agent_name( array $raw_item ): string {
		$customer = $raw_item['customer'] ?? null;
		if ( ! is_array( $customer ) ) {
			return '';
		}
		$brand = isset( $customer['brandTradingName'] ) ? trim( (string) $customer['brandTradingName'] ) : '';
		if ( '' !== $brand ) {
			return sanitize_text_field( $brand );
		}
		$branch = isset( $customer['branchDisplayName'] ) ? trim( (string) $customer['branchDisplayName'] ) : '';
		if ( '' !== $branch ) {
			return sanitize_text_field( $branch );
		}
		return '';
	}

	/**
	 * First three key features as comma-separated text.
	 *
	 * @param array<string, mixed> $raw_item Raw property from Rightmove JSON.
	 */
	private function extract_rightmove_key_features_text( array $raw_item ): string {
		if ( empty( $raw_item['keyFeatures'] ) || ! is_array( $raw_item['keyFeatures'] ) ) {
			return '';
		}
		$out = array();
		foreach ( $raw_item['keyFeatures'] as $feature ) {
			if ( count( $out ) >= 3 ) {
				break;
			}
			if ( is_array( $feature ) ) {
				$feature = $feature['text'] ?? $feature['value'] ?? '';
			}
			if ( ! is_scalar( $feature ) ) {
				continue;
			}
			$text = sanitize_text_field( trim( (string) $feature ) );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}
		if ( empty( $out ) ) {
			return '';
		}
		return implode( ', ', $out );
	}

	/**
	 * Parse displaySize (e.g. "1,200 sq ft") to square feet integer.
	 *
	 * @param array<string, mixed> $raw_item Raw property from Rightmove JSON.
	 * @return int|null Positive sq ft or null.
	 */
	private function parse_rightmove_display_size_sqft( array $raw_item ): ?int {
		$s = $raw_item['displaySize'] ?? '';
		if ( ! is_string( $s ) ) {
			return null;
		}
		$s = trim( $s );
		if ( '' === $s ) {
			return null;
		}
		if ( ! preg_match( '/([\d][\d,\.]*)\s*sq\.?\s*ft\b/i', $s, $m ) ) {
			return null;
		}
		$n = absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
		return $n > 0 ? $n : null;
	}

	/**
	 * First listed date as Y-m-d from firstVisibleDate or listing update date.
	 *
	 * @param array<string, mixed> $raw_item Raw property from Rightmove JSON.
	 */
	private function parse_rightmove_first_listed_date_ymd( array $raw_item ): string {
		$raw = $raw_item['firstVisibleDate'] ?? '';
		if ( ( '' === $raw || null === $raw ) && isset( $raw_item['listingUpdate'] ) && is_array( $raw_item['listingUpdate'] ) ) {
			$raw = $raw_item['listingUpdate']['listingUpdateDate'] ?? '';
		}
		if ( '' === $raw || null === $raw || ! is_scalar( $raw ) ) {
			return '';
		}
		$str = trim( (string) $raw );
		if ( '' === $str ) {
			return '';
		}
		$ts = strtotime( $str );
		if ( false === $ts ) {
			return '';
		}
		return wp_date( 'Y-m-d', $ts );
	}

	/**
	 * All key features joined with "|" for structured storage.
	 *
	 * @param array<string, mixed> $raw_item Raw property from Rightmove JSON.
	 */
	private function extract_rightmove_key_features_pipe( array $raw_item ): string {
		if ( empty( $raw_item['keyFeatures'] ) || ! is_array( $raw_item['keyFeatures'] ) ) {
			return '';
		}
		$out = array();
		foreach ( $raw_item['keyFeatures'] as $feature ) {
			if ( is_array( $feature ) ) {
				$feature = $feature['text'] ?? $feature['value'] ?? '';
			}
			if ( ! is_scalar( $feature ) ) {
				continue;
			}
			$text = sanitize_text_field( trim( (string) $feature ) );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}
		if ( empty( $out ) ) {
			return '';
		}
		return implode( '|', $out );
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
