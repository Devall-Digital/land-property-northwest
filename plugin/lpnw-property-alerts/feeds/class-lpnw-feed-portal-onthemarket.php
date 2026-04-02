<?php
/**
 * OnTheMarket property portal feed.
 *
 * Fetches HTML search pages and reads embedded __NEXT_DATA__ JSON
 * (Redux search results). Falls back to DOM parsing of listing links if needed.
 * Each cron run processes a batch of area+section pairs (see lpnw_otm_cursor)
 * with a time budget for shared hosting.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Portal_OnTheMarket extends LPNW_Feed_Base {

	private const BASE_URL = 'https://www.onthemarket.com';

	private const MOBILE_BASE_URL = 'https://m.onthemarket.com';

	/**
	 * Rotate User-Agents to reduce single-fingerprint blocks.
	 *
	 * @var array<string, string>
	 */
	private const USER_AGENTS = array(
		'chrome'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
		'firefox' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
		'safari'  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
	);

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

	private const OPTION_CURSOR = 'lpnw_otm_cursor';

	private const TIME_BUDGET_SECONDS = 25.0;

	public function get_source_name(): string {
		return 'onthemarket';
	}

	/**
	 * Area+section pairs per fetch() invocation.
	 *
	 * @return int
	 */
	protected function get_batch_size(): int {
		return 4;
	}

	/**
	 * @return array<int, array{slug: string, label: string, section: string}>
	 */
	private function get_area_section_pairs(): array {
		$pairs = array();

		foreach ( self::NW_AREA_SLUGS as $slug => $label ) {
			foreach ( array( 'for-sale', 'to-rent' ) as $section ) {
				$pairs[] = array(
					'slug'    => $slug,
					'label'   => $label,
					'section' => $section,
				);
			}
		}

		return $pairs;
	}

	/**
	 * Fetch raw listing rows for a batched subset of NW areas, sale and rent.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch(): array {
		$all_properties = array();
		$pairs          = $this->get_area_section_pairs();
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
				$this->lpnw_diag_log( 'stopping batch early (time budget)', 0, 0 );
				break;
			}

			$idx  = ( $start_index + $n ) % $total_pairs;
			$pair = $pairs[ $idx ];

			if ( $processed > 0 ) {
				usleep( wp_rand( 800000, 2000000 ) );
			}

			if ( ( microtime( true ) - $time_started ) >= self::TIME_BUDGET_SECONDS ) {
				$this->lpnw_diag_log( 'stopping batch early (time budget before fetch)', 0, 0 );
				break;
			}

			$this->lpnw_diag_log(
				sprintf(
					'fetching %s %s [%s] [pair %d/%d, batch %d/%d]',
					$pair['label'],
					$pair['section'],
					$pair['slug'],
					$idx + 1,
					$total_pairs,
					$processed + 1,
					$batch_size
				),
				0,
				0
			);

			$rows = $this->fetch_area_section( $pair['slug'], $pair['label'], $pair['section'] );
			foreach ( $rows as $row ) {
				$row['_otm_section'] = $pair['section'];
				$all_properties[]    = $row;
			}

			$new_last = $idx;
			update_option( self::OPTION_CURSOR, $new_last, false );
			++$processed;
		}

		$all_properties = $this->deduplicate( $all_properties );

		$next_idx = ( $new_last + 1 ) % $total_pairs;
		$this->lpnw_diag_log(
			sprintf(
				'batch done. Pairs this run: %d, last index: %d, next start: %d, properties: %d',
				$processed,
				$new_last,
				$next_idx,
				count( $all_properties )
			),
			0,
			0
		);

		return $all_properties;
	}

	/**
	 * @param string $slug    URL path segment (lowercase).
	 * @param string $label   Human label for logs.
	 * @param string $section for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_area_section( string $slug, string $label, string $section ): array {
		$strategies = array();
		foreach ( array( 'www' => self::BASE_URL, 'mobile' => self::MOBILE_BASE_URL ) as $host_label => $base ) {
			foreach ( self::USER_AGENTS as $ua_label => $ua ) {
				$strategies[] = array(
					'host'     => $host_label,
					'ua_label' => $ua_label,
					'base'     => $base,
					'ua'       => $ua,
				);
			}
		}

		$last_http = 0;
		$last_len  = 0;

		foreach ( $strategies as $s ) {
			$path = '/' . $section . '/property/' . rawurlencode( $slug ) . '/';
			$url  = untrailingslashit( $s['base'] ) . $path;
			$url  = add_query_arg( array( 'sort' => 'recently-added' ), $url );

			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => 30,
					'headers'    => $this->otm_request_headers( $s['ua'], $s['base'] ),
					'decompress' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->lpnw_diag_log(
					sprintf(
						'WP_Error %s %s (%s) host=%s ua=%s: %s',
						$label,
						$section,
						$slug,
						$s['host'],
						$s['ua_label'],
						$response->get_error_message()
					),
					0,
					0
				);
				continue;
			}

			$code      = (int) wp_remote_retrieve_response_code( $response );
			$body      = wp_remote_retrieve_body( $response );
			$body      = is_string( $body ) ? $body : '';
			$last_http = $code;
			$last_len  = strlen( $body );

			$this->lpnw_diag_log(
				sprintf(
					'response %s %s (%s) host=%s ua=%s',
					$label,
					$section,
					$slug,
					$s['host'],
					$s['ua_label']
				),
				$code,
				$last_len
			);

			if ( 403 === $code || 429 === $code ) {
				$this->lpnw_diag_log(
					sprintf(
						'rate limited or blocked (HTTP %d) host=%s ua=%s — backing off 5s then trying next strategy',
						$code,
						$s['host'],
						$s['ua_label']
					),
					$code,
					$last_len
				);
				sleep( 5 );
				continue;
			}

			if ( 200 !== $code ) {
				continue;
			}

			$extracted = $this->extract_listings_with_method( $body, $label, $section, $slug );
			$listings  = $extracted['listings'];
			$method    = $extracted['method'];

			$this->lpnw_diag_log(
				sprintf(
					'parsed %s %s (%s) host=%s ua=%s method=%s count=%d',
					$label,
					$section,
					$slug,
					$s['host'],
					$s['ua_label'],
					$method,
					count( $listings )
				),
				$code,
				$last_len
			);

			return $listings;
		}

		$this->lpnw_diag_log(
			sprintf(
				'all fetch strategies exhausted for %s %s (%s); last http=%d',
				$label,
				$section,
				$slug,
				$last_http
			),
			$last_http,
			$last_len
		);

		return array();
	}

	/**
	 * @param string $user_agent User-Agent string.
	 * @param string $base       Host base URL for Referer.
	 * @return array<string, string>
	 */
	private function otm_request_headers( string $user_agent, string $base ): array {
		return array(
			'User-Agent'                => $user_agent,
			'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
			'Accept-Language'           => 'en-GB,en;q=0.9',
			'Accept-Encoding'           => 'gzip, deflate',
			'Cache-Control'             => 'no-cache',
			'Connection'                => 'keep-alive',
			'Referer'                   => untrailingslashit( $base ) . '/',
			'Sec-Fetch-Dest'            => 'document',
			'Sec-Fetch-Mode'            => 'navigate',
			'Sec-Fetch-Site'            => 'none',
			'Sec-Fetch-User'            => '?1',
			'Upgrade-Insecure-Requests' => '1',
		);
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
	 * Parse __NEXT_DATA__ first, then DOM fallback; report extraction method.
	 *
	 * @param string $html    Full HTML document.
	 * @param string $label   Area label for logging.
	 * @param string $section for-sale or to-rent.
	 * @param string $slug    Area slug for logging.
	 * @return array{listings: array<int, array<string, mixed>>, method: string}
	 */
	private function extract_listings_with_method( string $html, string $label, string $section, string $slug ): array {
		$html_len  = strlen( $html );
		$from_next = $this->extract_from_next_data( $html, $label, $section, $slug, $html_len );

		if ( ! empty( $from_next['listings'] ) ) {
			return array(
				'listings' => $from_next['listings'],
				'method'   => $from_next['method'],
			);
		}

		$listings = $this->extract_from_html_dom( $html, $section );

		if ( empty( $listings ) ) {
			$has_next = ( strpos( $html, '__NEXT_DATA__' ) !== false );
			$this->lpnw_diag_log(
				sprintf(
					'no property data for %s %s (%s). __NEXT_DATA__ present: %s, HTML snippet: %s',
					$label,
					$section,
					$slug,
					$has_next ? 'yes' : 'no',
					substr( $html, 0, 500 )
				),
				0,
				$html_len
			);
			return array(
				'listings' => array(),
				'method'   => ! empty( $from_next['method'] ) ? $from_next['method'] . '_then_empty' : 'no_data',
			);
		}

		return array(
			'listings' => $listings,
			'method'   => 'DOM_fallback',
		);
	}

	/**
	 * Parse __NEXT_DATA__ script for Redux results list.
	 *
	 * @param string $html    Full HTML document.
	 * @param string $label   Area label for logging.
	 * @param string $section Section for logging.
	 * @param string $slug    Slug for logging.
	 * @param int    $html_len Cached strlen( html ).
	 * @return array{listings: array<int, array<string, mixed>>, method: string}
	 */
	private function extract_from_next_data( string $html, string $label, string $section, string $slug, int $html_len ): array {
		$empty = array( 'listings' => array(), 'method' => '' );

		if ( ! preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*type="application\/json"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			if ( ! preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
				return $empty;
			}
		}

		$this->lpnw_diag_log(
			sprintf(
				'found __NEXT_DATA__ for %s %s (%s), JSON length %d',
				$label,
				$section,
				$slug,
				strlen( $matches[1] )
			),
			0,
			$html_len
		);

		$data = json_decode( $matches[1], true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->lpnw_diag_log( '__NEXT_DATA__ JSON decode error: ' . json_last_error_msg(), 0, $html_len );
			return array( 'listings' => array(), 'method' => '__NEXT_DATA___decode_failed' );
		}

		$list = $data['props']['initialReduxState']['results']['list'] ?? null;
		if ( ! is_array( $list ) ) {
			$list = $data['props']['pageProps']['initialReduxState']['results']['list'] ?? null;
		}

		if ( ! is_array( $list ) ) {
			$this->lpnw_diag_log(
				sprintf(
					'__NEXT_DATA__ found but no results.list for %s %s (%s)',
					$label,
					$section,
					$slug
				),
				0,
				$html_len
			);
			return array( 'listings' => array(), 'method' => '__NEXT_DATA__' );
		}

		return array(
			'listings' => $list,
			'method'   => '__NEXT_DATA__',
		);
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

		$dupes_removed = count( $rows ) - count( $uniq );
		if ( $dupes_removed > 0 ) {
			$this->lpnw_diag_log( sprintf( 'deduplicated %d duplicate properties', $dupes_removed ), 0, 0 );
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

		if ( '' === $postcode && ( null === $lat || null === $lng || ! is_finite( $lat ) || ! is_finite( $lng ) ) ) {
			return array();
		}

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

		$price = $this->otm_parse_listing_price( $raw_item );

		$this->otm_attach_card_image_fields( $raw_item );

		$prop_type = '';
		if ( isset( $raw_item['humanised-property-type'] ) && is_string( $raw_item['humanised-property-type'] ) ) {
			$prop_type = sanitize_text_field( $raw_item['humanised-property-type'] );
		}

		$beds  = isset( $raw_item['bedrooms'] ) ? absint( $raw_item['bedrooms'] ) : null;
		$baths = isset( $raw_item['bathrooms'] ) ? absint( $raw_item['bathrooms'] ) : null;

		$section_raw      = (string) ( $raw_item['_otm_section'] ?? '' );
		$application_type = 'sale';
		$desc_prefix      = 'For sale. ';
		if ( 'to-rent' === $section_raw ) {
			$application_type = 'rent';
			$desc_prefix      = 'To let. ';
		}

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

		$description = $desc_prefix . implode( '. ', $desc_parts );

		$out = array(
			'source'           => $this->get_source_name(),
			'source_ref'       => 'otm-' . $otm_id,
			'address'          => $address,
			'postcode'         => $postcode,
			'latitude'         => $lat,
			'longitude'        => $lng,
			'price'            => $price > 0 ? $price : null,
			'property_type'    => $prop_type,
			'description'      => $description,
			'application_type' => $application_type,
			'source_url'       => esc_url_raw( $property_url ),
			'raw_data'         => $raw_item,
		);

		return array_merge( $out, $this->otm_optional_structured_fields( $raw_item ) );
	}

	/**
	 * Extract optional DB columns from OnTheMarket JSON or DOM fallback rows.
	 *
	 * @param array<string, mixed> $raw Raw listing row.
	 * @return array<string, mixed>
	 */
	private function otm_optional_structured_fields( array $raw ): array {
		$out = array();

		if ( isset( $raw['bedrooms'] ) ) {
			$out['bedrooms'] = absint( $raw['bedrooms'] );
		}
		if ( isset( $raw['bathrooms'] ) ) {
			$out['bathrooms'] = absint( $raw['bathrooms'] );
		}

		$tenure = $raw['tenure'] ?? $raw['tenure-type'] ?? $raw['tenureType'] ?? '';
		if ( is_scalar( $tenure ) ) {
			$t = strtolower( trim( (string) $tenure ) );
			if ( '' !== $t ) {
				$out['tenure_type'] = sanitize_text_field( $t );
			}
		}

		$freq = $raw['price-frequency'] ?? $raw['rent-frequency'] ?? $raw['rental-frequency'] ?? '';
		if ( is_scalar( $freq ) ) {
			$f = strtolower( trim( (string) $freq ) );
			if ( '' !== $f ) {
				$out['price_frequency'] = sanitize_text_field( $f );
			}
		}

		$sqft = $this->otm_extract_floor_area_sqft( $raw );
		if ( null !== $sqft ) {
			$out['floor_area_sqft'] = $sqft;
		}

		$listed = $this->otm_extract_first_listed_date_ymd( $raw );
		if ( '' !== $listed ) {
			$out['first_listed_date'] = $listed;
		}

		$agent = $raw['branch-name'] ?? $raw['agent-name'] ?? $raw['agentName'] ?? '';
		if ( is_scalar( $agent ) ) {
			$a = trim( (string) $agent );
			if ( '' !== $a ) {
				$out['agent_name'] = sanitize_text_field( $a );
			}
		}

		if ( ! empty( $raw['features'] ) && is_array( $raw['features'] ) ) {
			$parts = array();
			foreach ( $raw['features'] as $feat ) {
				if ( ! is_scalar( $feat ) ) {
					continue;
				}
				$t = sanitize_text_field( trim( (string) $feat ) );
				if ( '' !== $t ) {
					$parts[] = $t;
				}
			}
			if ( ! empty( $parts ) ) {
				$out['key_features_text'] = implode( '|', $parts );
			}
		}

		return $out;
	}

	/**
	 * Normalised listing price in GBP (sale) or monthly/weekly rent as stored by OTM numeric fields.
	 *
	 * Avoids concatenating all digits in display strings such as "GBP 1,375 pcm (317 sq ft)".
	 *
	 * @param array<string, mixed> $raw_item Row from __NEXT_DATA__ or DOM fallback.
	 * @return int 0 if unknown.
	 */
	private function otm_parse_listing_price( array $raw_item ): int {
		foreach ( array( 'price-value', 'priceValue', 'numericPrice', 'displayPriceValue' ) as $k ) {
			if ( ! isset( $raw_item[ $k ] ) ) {
				continue;
			}
			$v = $raw_item[ $k ];
			if ( is_numeric( $v ) ) {
				$n = (int) round( floatval( $v ) );
				if ( $n > 0 ) {
					return $n;
				}
			}
		}

		$str_candidates = array();
		foreach ( array( 'short-price', 'price' ) as $k ) {
			if ( ! isset( $raw_item[ $k ] ) ) {
				continue;
			}
			$v = $raw_item[ $k ];
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				$str_candidates[] = $v;
			}
		}

		foreach ( $str_candidates as $s ) {
			$n = $this->otm_price_from_display_string( $s );
			if ( $n > 0 ) {
				return $n;
			}
		}

		return 0;
	}

	/**
	 * Parse OTM human-readable price strings (with commas, pcm, optional sq ft in parentheses).
	 *
	 * @param string $s Raw display string.
	 * @return int 0 if no amount found.
	 */
	private function otm_price_from_display_string( string $s ): int {
		$s = trim( $s );
		if ( '' === $s ) {
			return 0;
		}

		$paren = strpos( $s, '(' );
		if ( false !== $paren ) {
			$before = trim( substr( $s, 0, $paren ) );
			if ( '' !== $before ) {
				$s = $before;
			}
		}

		if ( preg_match( '/(?:£|GBP\s*)([0-9][0-9,]*(?:\.[0-9]+)?)/iu', $s, $m ) ) {
			return absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
		}

		if ( preg_match( '/^([0-9][0-9,]*(?:\.[0-9]+)?)/', $s, $m ) ) {
			return absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
		}

		if ( preg_match( '/([0-9]{1,3}(?:,[0-9]{3})+|[0-9]{2,})/', $s, $m ) ) {
			return absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
		}

		return 0;
	}

	/**
	 * First absolute image URL from OTM listing JSON for property card templates.
	 *
	 * @param array<string, mixed> $raw Row from feed.
	 * @return string Empty if none.
	 */
	private function otm_extract_first_image_url( array $raw ): string {
		$string_keys = array(
			'imageUrl',
			'image',
			'mainImage',
			'main-image',
			'mainImageUrl',
			'thumbnail',
			'thumbUrl',
			'heroImage',
			'primaryImage',
			'primary-image-url',
		);
		foreach ( $string_keys as $k ) {
			if ( empty( $raw[ $k ] ) || ! is_string( $raw[ $k ] ) ) {
				continue;
			}
			$url = $this->otm_normalize_media_url( trim( $raw[ $k ] ) );
			if ( '' !== $url ) {
				return $url;
			}
		}

		$list_keys = array( 'images', 'photos', 'gallery', 'property-images', 'media', 'thumbnails' );
		foreach ( $list_keys as $k ) {
			if ( empty( $raw[ $k ] ) || ! is_array( $raw[ $k ] ) ) {
				continue;
			}
			$url = $this->otm_first_url_from_list( $raw[ $k ] );
			if ( '' !== $url ) {
				return $url;
			}
		}

		if ( ! empty( $raw['propertyImages'] ) && is_array( $raw['propertyImages'] ) ) {
			$pi = $raw['propertyImages'];
			if ( ! empty( $pi['mainImageSrc'] ) && is_string( $pi['mainImageSrc'] ) ) {
				$url = $this->otm_normalize_media_url( trim( $pi['mainImageSrc'] ) );
				if ( '' !== $url ) {
					return $url;
				}
			}
			if ( ! empty( $pi['images'] ) && is_array( $pi['images'] ) ) {
				$url = $this->otm_first_url_from_list( $pi['images'] );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * @param array<int|string, mixed> $list Image list from JSON.
	 */
	private function otm_first_url_from_list( array $list ): string {
		foreach ( $list as $item ) {
			if ( is_string( $item ) ) {
				$url = $this->otm_normalize_media_url( trim( $item ) );
				if ( '' !== $url ) {
					return $url;
				}
				continue;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			foreach ( array( 'srcUrl', 'url', 'src', 'uri', 'href', 'path' ) as $uk ) {
				if ( empty( $item[ $uk ] ) || ! is_string( $item[ $uk ] ) ) {
					continue;
				}
				$url = $this->otm_normalize_media_url( trim( $item[ $uk ] ) );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}
		return '';
	}

	/**
	 * @param string $url Raw path or URL from OTM.
	 */
	private function otm_normalize_media_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		} elseif ( str_starts_with( $url, '/' ) ) {
			$url = self::BASE_URL . $url;
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return esc_url_raw( $url );
	}

	/**
	 * Ensure raw_data contains keys the browse card templates read.
	 *
	 * @param array<string, mixed> $raw_item Mutated in place before assignment to raw_data.
	 */
	private function otm_attach_card_image_fields( array &$raw_item ): void {
		$first = $this->otm_extract_first_image_url( $raw_item );
		if ( '' === $first ) {
			return;
		}

		$raw_item['imageUrl'] = $first;

		$has_src = false;
		if ( ! empty( $raw_item['propertyImages']['images'][0]['srcUrl'] ) && is_string( $raw_item['propertyImages']['images'][0]['srcUrl'] ) ) {
			$has_src = true;
		} elseif ( ! empty( $raw_item['propertyImages']['mainImageSrc'] ) && is_string( $raw_item['propertyImages']['mainImageSrc'] ) ) {
			$has_src = true;
		}
		if ( ! $has_src ) {
			$raw_item['propertyImages'] = array(
				'mainImageSrc' => $first,
				'images'       => array(
					array( 'srcUrl' => $first ),
				),
			);
		}

		$has_flat = false;
		if ( ! empty( $raw_item['images'][0]['srcUrl'] ) && is_string( $raw_item['images'][0]['srcUrl'] ) ) {
			$has_flat = true;
		} elseif ( ! empty( $raw_item['images'][0]['url'] ) && is_string( $raw_item['images'][0]['url'] ) ) {
			$has_flat = true;
		}
		if ( ! $has_flat ) {
			$raw_item['images'] = array(
				array(
					'srcUrl' => $first,
					'url'    => $first,
				),
			);
		}

		if ( ! isset( $raw_item['media'] ) || ! is_array( $raw_item['media'] ) ) {
			$raw_item['media'] = array( array( 'url' => $first ) );
		} elseif ( empty( $raw_item['media'][0]['url'] ) || ! is_string( $raw_item['media'][0]['url'] ) ) {
			if ( isset( $raw_item['media'][0] ) && is_array( $raw_item['media'][0] ) ) {
				$raw_item['media'][0]['url'] = $first;
			} else {
				array_unshift( $raw_item['media'], array( 'url' => $first ) );
			}
		}

		if ( empty( $raw_item['photos'] ) || ! is_array( $raw_item['photos'] ) ) {
			$raw_item['photos'] = array( $first );
		} elseif ( empty( $raw_item['photos'][0] ) ) {
			$raw_item['photos'][0] = $first;
		} elseif ( is_array( $raw_item['photos'][0] ) && ( empty( $raw_item['photos'][0]['url'] ) || ! is_string( $raw_item['photos'][0]['url'] ) ) ) {
			$raw_item['photos'][0]['url'] = $first;
		}
	}

	/**
	 * Square feet from numeric fields or size summary text.
	 *
	 * @param array<string, mixed> $raw Raw listing.
	 * @return int|null
	 */
	private function otm_extract_floor_area_sqft( array $raw ): ?int {
		foreach ( array( 'floor-area-sq-ft', 'floorAreaSqFt', 'size-square-feet', 'square-feet' ) as $k ) {
			if ( isset( $raw[ $k ] ) && is_numeric( $raw[ $k ] ) ) {
				$n = absint( $raw[ $k ] );
				return $n > 0 ? $n : null;
			}
		}
		foreach ( array( 'size-summary', 'sizeSummary', 'property-size', 'display-size' ) as $k ) {
			if ( isset( $raw[ $k ] ) && is_string( $raw[ $k ] ) && preg_match( '/([\d][\d,\.]*)\s*sq\.?\s*ft\b/i', $raw[ $k ], $m ) ) {
				$n = absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
				return $n > 0 ? $n : null;
			}
		}
		return null;
	}

	/**
	 * First published / listed date as Y-m-d.
	 *
	 * @param array<string, mixed> $raw Raw listing.
	 */
	private function otm_extract_first_listed_date_ymd( array $raw ): string {
		$candidates = array(
			$raw['first-published-date'] ?? '',
			$raw['firstPublishedDate'] ?? '',
			$raw['published-date'] ?? '',
			$raw['date-added'] ?? '',
			$raw['listed-date'] ?? '',
			$raw['first-visible-date'] ?? '',
		);
		foreach ( $candidates as $raw_date ) {
			if ( ! is_scalar( $raw_date ) ) {
				continue;
			}
			$str = trim( (string) $raw_date );
			if ( '' === $str ) {
				continue;
			}
			$ts = strtotime( $str );
			if ( false !== $ts ) {
				return wp_date( 'Y-m-d', $ts );
			}
		}
		return '';
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
