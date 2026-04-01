<?php
/**
 * Zoopla property portal feed.
 *
 * Monitors Zoopla search results for new property listings
 * across Northwest England.
 *
 * Zoopla uses Cloudflare protection which may block server-side requests.
 * Each cron run processes a batch of area+section pairs (see lpnw_zoopla_cursor)
 * with a time budget so shared hosting limits are respected. If Cloudflare
 * blocks a request, that pair is skipped and the cursor advances so the same
 * area is not retried indefinitely on the next run.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Portal_Zoopla extends LPNW_Feed_Base {

	private const BASE_URL = 'https://www.zoopla.co.uk';

	/**
	 * Zoopla uses URL path slugs for locations.
	 * We search each NW area separately.
	 */
	private const NW_AREA_SLUGS = array(
		'manchester'  => 'Manchester',
		'liverpool'   => 'Liverpool',
		'preston'     => 'Preston',
		'blackpool'   => 'Blackpool',
		'blackburn'   => 'Blackburn',
		'bolton'      => 'Bolton',
		'bury'        => 'Bury',
		'oldham'      => 'Oldham',
		'rochdale'    => 'Rochdale',
		'salford'     => 'Salford',
		'stockport'   => 'Stockport',
		'wigan'       => 'Wigan',
		'warrington'  => 'Warrington',
		'chester'     => 'Chester',
		'lancaster'   => 'Lancaster',
		'burnley'     => 'Burnley',
		'carlisle'    => 'Carlisle',
	);

	/**
	 * WordPress option: zero-based index of last completed area+section pair.
	 */
	private const OPTION_CURSOR = 'lpnw_zoopla_cursor';

	private const TIME_BUDGET_SECONDS = 25.0;

	private const REQUEST_HEADERS = array(
		'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
		'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'Accept-Language' => 'en-GB,en;q=0.9',
		'Accept-Encoding' => 'gzip, deflate',
		'Connection'      => 'keep-alive',
		'Cache-Control'   => 'no-cache',
	);

	public function get_source_name(): string {
		return 'zoopla';
	}

	/**
	 * Area+section pairs per fetch() invocation (Zoopla is slower / Cloudflare risk).
	 *
	 * @return int
	 */
	protected function get_batch_size(): int {
		return 4;
	}

	/**
	 * Flat list of slug+section pairs in stable crawl order.
	 *
	 * @return array<int, array{slug: string, area_name: string, section: string}>
	 */
	private function get_area_section_pairs(): array {
		$pairs = array();

		foreach ( self::NW_AREA_SLUGS as $slug => $area_name ) {
			foreach ( array( 'for-sale', 'to-rent' ) as $section ) {
				$pairs[] = array(
					'slug'       => $slug,
					'area_name'  => $area_name,
					'section'    => $section,
				);
			}
		}

		return $pairs;
	}

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
				error_log( 'LPNW Zoopla: stopping batch early (time budget)' );
				break;
			}

			$idx  = ( $start_index + $n ) % $total_pairs;
			$pair = $pairs[ $idx ];

			if ( $processed > 0 ) {
				usleep( wp_rand( 800000, 2000000 ) );
			}

			if ( ( microtime( true ) - $time_started ) >= self::TIME_BUDGET_SECONDS ) {
				error_log( 'LPNW Zoopla: stopping batch early (time budget before fetch)' );
				break;
			}

			error_log(
				sprintf(
					'LPNW Zoopla: fetching %s %s [%s] [pair %d/%d, batch %d/%d]',
					$pair['area_name'],
					$pair['section'],
					$pair['slug'],
					$idx + 1,
					$total_pairs,
					$processed + 1,
					$batch_size
				)
			);

			$properties     = $this->fetch_area( $pair['slug'], $pair['area_name'], $pair['section'] );
			$all_properties = array_merge( $all_properties, $properties );

			$new_last = $idx;
			update_option( self::OPTION_CURSOR, $new_last, false );
			++$processed;
		}

		$all_properties = $this->deduplicate( $all_properties );

		$next_idx = ( $new_last + 1 ) % $total_pairs;
		error_log(
			sprintf(
				'LPNW Zoopla: batch done. Pairs this run: %d, last index: %d, next start: %d, properties: %d',
				$processed,
				$new_last,
				$next_idx,
				count( $all_properties )
			)
		);

		return $all_properties;
	}

	/**
	 * Fetch newest listings for a single area+section.
	 *
	 * @param string $slug      Zoopla area slug.
	 * @param string $area_name Human-readable name for logging.
	 * @param string $section   for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_area( string $slug, string $area_name, string $section ): array {
		$url = sprintf(
			'%s/%s/property/%s/?results_sort=newest_listings&search_source=home',
			self::BASE_URL,
			$section,
			rawurlencode( $slug )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => self::REQUEST_HEADERS,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log(
				sprintf(
					'LPNW Zoopla: HTTP error for %s %s (%s): %s',
					$area_name,
					$section,
					$slug,
					$response->get_error_message()
				)
			);
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		error_log(
			sprintf(
				'LPNW Zoopla: %s %s (%s) - HTTP %d, body length %d',
				$area_name,
				$section,
				$slug,
				$code,
				strlen( $body )
			)
		);

		if ( 403 === $code || 503 === $code ) {
			error_log(
				sprintf(
					'LPNW Zoopla: Cloudflare or edge block (HTTP %d) for %s %s — advancing cursor; will retry another cycle',
					$code,
					$area_name,
					$section
				)
			);
			return array();
		}

		if ( 200 !== $code ) {
			error_log(
				sprintf(
					'LPNW Zoopla: non-200 response for %s %s (%s), skipping pair this run',
					$area_name,
					$section,
					$slug
				)
			);
			return array();
		}

		if ( $this->is_cloudflare_challenge( $body ) ) {
			error_log(
				sprintf(
					'LPNW Zoopla: Cloudflare challenge page detected for %s %s (%s) — advancing cursor; not retrying same pair next',
					$area_name,
					$section,
					$slug
				)
			);
			return array();
		}

		$extracted = $this->extract_listings( $body, $section );
		$listings  = $extracted['listings'];
		$method    = $extracted['method'];

		error_log(
			sprintf(
				'LPNW Zoopla: %s %s (%s) - method %s, extracted %d properties',
				$area_name,
				$section,
				$slug,
				$method,
				count( $listings )
			)
		);

		return $listings;
	}

	/**
	 * Check if response body is a Cloudflare challenge page.
	 */
	private function is_cloudflare_challenge( string $body ): bool {
		if ( stripos( $body, 'cf-browser-verification' ) !== false ) {
			return true;
		}
		if ( stripos( $body, 'Checking your browser' ) !== false ) {
			return true;
		}
		if ( stripos( $body, 'cf_chl_opt' ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * Extract listing data from Zoopla HTML search results.
	 *
	 * @param string $html    Raw HTML.
	 * @param string $section for-sale or to-rent.
	 * @return array{listings: array<int, array<string, mixed>>, method: string}
	 */
	private function extract_listings( string $html, string $section ): array {
		$listings = array();
		$method   = 'none';

		if ( preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*type="application\/json"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			error_log(
				sprintf(
					'LPNW Zoopla: found __NEXT_DATA__ in HTML for %s, JSON length %d',
					$section,
					strlen( $matches[1] )
				)
			);

			$next_data = json_decode( $matches[1], true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				error_log( 'LPNW Zoopla: __NEXT_DATA__ JSON decode error: ' . json_last_error_msg() );
			} elseif ( is_array( $next_data ) ) {
				$results = $next_data['props']['pageProps']['regularListingsFormatted'] ?? array();
				if ( empty( $results ) ) {
					$results = $next_data['props']['pageProps']['listings'] ?? array();
				}

				foreach ( $results as $listing ) {
					$listing['_section'] = $section;
					$listings[]          = $listing;
				}
				$method = '__NEXT_DATA__';
			}
		} elseif ( preg_match( '/<script\s+id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			error_log(
				sprintf(
					'LPNW Zoopla: found __NEXT_DATA__ (alt) for %s, JSON length %d',
					$section,
					strlen( $matches[1] )
				)
			);
			$next_data = json_decode( $matches[1], true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				error_log( 'LPNW Zoopla: __NEXT_DATA__ (alt) JSON decode error: ' . json_last_error_msg() );
			} elseif ( is_array( $next_data ) ) {
				$results = $next_data['props']['pageProps']['regularListingsFormatted'] ?? array();
				if ( empty( $results ) ) {
					$results = $next_data['props']['pageProps']['listings'] ?? array();
				}
				foreach ( $results as $listing ) {
					$listing['_section'] = $section;
					$listings[]          = $listing;
				}
				$method = '__NEXT_DATA__';
			}
		}

		if ( empty( $listings ) ) {
			$listings = $this->extract_from_html_dom( $html, $section );
			$method   = empty( $listings ) ? ( 'none' === $method ? 'no_data' : 'DOM_fallback_empty' ) : 'DOM_fallback';
		}

		return array(
			'listings' => $listings,
			'method'   => $method,
		);
	}

	/**
	 * Fallback: parse listing cards from HTML DOM if JSON extraction fails.
	 *
	 * @param string $html    Raw HTML.
	 * @param string $section for-sale or to-rent.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_from_html_dom( string $html, string $section ): array {
		$listings = array();

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new \DOMXPath( $dom );

		$cards = $xpath->query( "//div[@data-testid='search-result'] | //article[contains(@class, 'listing')] | //div[contains(@class, 'srp-listing')]" );

		if ( ! $cards || 0 === $cards->length ) {
			$has_next = ( strpos( $html, '__NEXT_DATA__' ) !== false );
			error_log(
				sprintf(
					'LPNW Zoopla: DOM fallback found no cards; __NEXT_DATA__ string in body: %s, HTML snippet: %s',
					$has_next ? 'yes' : 'no',
					substr( $html, 0, 500 )
				)
			);
			return $listings;
		}

		foreach ( $cards as $card ) {
			$listing = $this->parse_card_node( $card, $xpath, $section );
			if ( ! empty( $listing['address'] ) ) {
				$listings[] = $listing;
			}
		}

		return $listings;
	}

	/**
	 * Parse a single listing card from the DOM.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_card_node( \DOMNode $card, \DOMXPath $xpath, string $section ): array {
		$address_node = $xpath->query( ".//address | .//*[@data-testid='listing-card-address'] | .//h2 | .//a[contains(@class, 'address')]", $card );
		$price_node   = $xpath->query( ".//*[@data-testid='listing-card-price'] | .//*[contains(@class, 'price')] | .//p[contains(@class, 'price')]", $card );
		$link_node    = $xpath->query( ".//a[contains(@href, '/details/')]", $card );
		$beds_node    = $xpath->query( ".//*[contains(@class, 'bed')] | .//*[@data-testid='beds']", $card );
		$type_node    = $xpath->query( ".//*[contains(@class, 'property-type')] | .//*[@data-testid='listing-card-property-type']", $card );

		$address = $address_node->length ? trim( $address_node->item( 0 )->textContent ) : '';
		$price   = $price_node->length ? trim( $price_node->item( 0 )->textContent ) : '';
		$link    = $link_node->length ? $link_node->item( 0 )->getAttribute( 'href' ) : '';
		$beds    = $beds_node->length ? trim( $beds_node->item( 0 )->textContent ) : '';
		$type    = $type_node->length ? trim( $type_node->item( 0 )->textContent ) : '';

		$price_clean = preg_replace( '/[^0-9]/', '', $price );

		if ( $link && ! str_starts_with( $link, 'http' ) ) {
			$link = self::BASE_URL . $link;
		}

		$listing_id = '';
		if ( preg_match( '/\/details\/(\d+)/', $link, $m ) ) {
			$listing_id = $m[1];
		}

		return array(
			'address'    => $address,
			'price'      => $price_clean,
			'source_url' => $link,
			'listing_id' => $listing_id,
			'beds'       => $beds,
			'type'       => $type,
			'raw_price'  => $price,
			'_section'   => $section,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $properties Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function deduplicate( array $properties ): array {
		$seen   = array();
		$unique = array();

		foreach ( $properties as $prop ) {
			$id = '';
			if ( isset( $prop['listingId'] ) ) {
				$id = (string) $prop['listingId'];
			} elseif ( isset( $prop['listing_id'] ) ) {
				$id = (string) $prop['listing_id'];
			} elseif ( isset( $prop['id'] ) ) {
				$id = (string) $prop['id'];
			}
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$unique[]    = $prop;
		}

		$dupes_removed = count( $properties ) - count( $unique );
		if ( $dupes_removed > 0 ) {
			error_log(
				sprintf( 'LPNW Zoopla: deduplicated %d duplicate properties', $dupes_removed )
			);
		}

		return $unique;
	}

	/**
	 * @param array<string, mixed> $raw_item Listing data (JSON or DOM-extracted).
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$address = '';
		if ( isset( $raw_item['address'] ) && is_string( $raw_item['address'] ) ) {
			$address = sanitize_text_field( $raw_item['address'] );
		} elseif ( isset( $raw_item['displayAddress'] ) ) {
			$address = sanitize_text_field( $raw_item['displayAddress'] );
		} elseif ( isset( $raw_item['title'] ) ) {
			$address = sanitize_text_field( $raw_item['title'] );
		}

		if ( empty( $address ) ) {
			return array();
		}

		$postcode = '';
		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $m ) ) {
			$postcode = strtoupper( trim( $m[1] ) );
		}

		if ( ! empty( $postcode ) && ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		$price = 0;
		if ( isset( $raw_item['pricing']['value'] ) ) {
			$price = absint( $raw_item['pricing']['value'] );
		} elseif ( isset( $raw_item['price'] ) && is_numeric( $raw_item['price'] ) ) {
			$price = absint( $raw_item['price'] );
		} elseif ( isset( $raw_item['price'] ) ) {
			$price = absint( preg_replace( '/[^0-9]/', '', (string) $raw_item['price'] ) );
		}

		$listing_id = sanitize_text_field(
			(string) ( $raw_item['listingId'] ?? $raw_item['listing_id'] ?? $raw_item['id'] ?? '' )
		);

		if ( empty( $listing_id ) ) {
			$listing_id = md5( $address . $price );
		}

		$url = '';
		if ( ! empty( $raw_item['listingUri'] ) ) {
			$url = self::BASE_URL . $raw_item['listingUri'];
		} elseif ( ! empty( $raw_item['source_url'] ) ) {
			$url = $raw_item['source_url'];
		} elseif ( ! empty( $raw_item['details_url'] ) ) {
			$url = $raw_item['details_url'];
		}

		$lat = null;
		$lng = null;
		if ( isset( $raw_item['location']['coordinates']['latitude'] ) ) {
			$lat = floatval( $raw_item['location']['coordinates']['latitude'] );
			$lng = floatval( $raw_item['location']['coordinates']['longitude'] );
		} elseif ( isset( $raw_item['latitude'] ) ) {
			$lat = floatval( $raw_item['latitude'] );
			$lng = floatval( $raw_item['longitude'] ?? 0 );
		}

		$beds = '';
		if ( isset( $raw_item['attributes']['bedrooms'] ) ) {
			$beds = absint( $raw_item['attributes']['bedrooms'] );
		} elseif ( isset( $raw_item['num_bedrooms'] ) ) {
			$beds = absint( $raw_item['num_bedrooms'] );
		} elseif ( isset( $raw_item['beds'] ) ) {
			$beds = absint( preg_replace( '/[^0-9]/', '', (string) $raw_item['beds'] ) );
		}

		$prop_type = sanitize_text_field(
			$raw_item['propertyType'] ?? $raw_item['property_type'] ?? $raw_item['type'] ?? ''
		);

		$section_raw = (string) ( $raw_item['_section'] ?? '' );
		$application_type = 'sale';
		$desc_prefix      = 'For sale. ';
		if ( 'to-rent' === $section_raw ) {
			$application_type = 'rent';
			$desc_prefix      = 'To let. ';
		}

		$desc_parts = array();
		if ( $beds ) {
			$desc_parts[] = $beds . ' bed';
		}
		if ( $prop_type ) {
			$desc_parts[] = strtolower( $prop_type );
		}
		if ( ! empty( $raw_item['summary'] ) ) {
			$desc_parts[] = wp_trim_words( sanitize_text_field( $raw_item['summary'] ), 20, '...' );
		}

		$description = $desc_prefix . implode( '. ', $desc_parts );

		$out = array(
			'source'           => $this->get_source_name(),
			'source_ref'       => 'zp-' . $listing_id,
			'address'          => $address,
			'postcode'         => $postcode,
			'latitude'         => $lat,
			'longitude'        => $lng,
			'price'            => $price > 0 ? $price : null,
			'property_type'    => $prop_type,
			'description'      => $description,
			'application_type' => $application_type,
			'source_url'       => esc_url_raw( $url ),
			'raw_data'         => $raw_item,
		);

		return array_merge( $out, $this->zoopla_optional_structured_fields( $raw_item ) );
	}

	/**
	 * Extract optional DB columns from Zoopla JSON or DOM fallback rows.
	 *
	 * @param array<string, mixed> $raw_item Raw listing.
	 * @return array<string, mixed>
	 */
	private function zoopla_optional_structured_fields( array $raw_item ): array {
		$out = array();

		if ( isset( $raw_item['attributes']['bedrooms'] ) ) {
			$out['bedrooms'] = absint( $raw_item['attributes']['bedrooms'] );
		} elseif ( isset( $raw_item['num_bedrooms'] ) ) {
			$out['bedrooms'] = absint( $raw_item['num_bedrooms'] );
		} elseif ( isset( $raw_item['beds'] ) && '' !== (string) $raw_item['beds'] ) {
			$n = absint( preg_replace( '/[^0-9]/', '', (string) $raw_item['beds'] ) );
			if ( $n > 0 ) {
				$out['bedrooms'] = $n;
			}
		}

		if ( isset( $raw_item['attributes']['bathrooms'] ) ) {
			$out['bathrooms'] = absint( $raw_item['attributes']['bathrooms'] );
		} elseif ( isset( $raw_item['num_bathrooms'] ) ) {
			$out['bathrooms'] = absint( $raw_item['num_bathrooms'] );
		}

		if ( isset( $raw_item['pricing']['label'] ) && is_scalar( $raw_item['pricing']['label'] ) ) {
			$freq = $this->zoopla_pricing_label_to_frequency( (string) $raw_item['pricing']['label'] );
			if ( '' !== $freq ) {
				$out['price_frequency'] = $freq;
			}
		}

		$attrs = isset( $raw_item['attributes'] ) && is_array( $raw_item['attributes'] ) ? $raw_item['attributes'] : array();
		if ( isset( $attrs['tenure'] ) && is_scalar( $attrs['tenure'] ) ) {
			$t = strtolower( trim( (string) $attrs['tenure'] ) );
			if ( '' !== $t ) {
				$out['tenure_type'] = sanitize_text_field( $t );
			}
		}

		$sqft = $this->zoopla_extract_floor_area_sqft( $raw_item );
		if ( null !== $sqft ) {
			$out['floor_area_sqft'] = $sqft;
		}

		$listed = $this->zoopla_extract_first_listed_date_ymd( $raw_item );
		if ( '' !== $listed ) {
			$out['first_listed_date'] = $listed;
		}

		$agent = $this->zoopla_extract_agent_name( $raw_item );
		if ( '' !== $agent ) {
			$out['agent_name'] = $agent;
		}

		$features = $this->zoopla_extract_key_features_pipe( $raw_item );
		if ( '' !== $features ) {
			$out['key_features_text'] = $features;
		}

		return $out;
	}

	/**
	 * Map Zoopla pricing label (e.g. pcm, pw) to a normalised frequency token.
	 */
	private function zoopla_pricing_label_to_frequency( string $label ): string {
		$l = strtolower( trim( $label ) );
		if ( '' === $l ) {
			return '';
		}
		if ( str_contains( $l, 'pcm' ) || str_contains( $l, 'per month' ) || str_contains( $l, '/month' ) || 'monthly' === $l ) {
			return 'monthly';
		}
		if ( str_contains( $l, 'pw' ) || str_contains( $l, 'per week' ) || str_contains( $l, '/week' ) || 'weekly' === $l ) {
			return 'weekly';
		}
		if ( strlen( $l ) <= 20 && preg_match( '/^[a-z0-9\s\-\/]+$/', $l ) ) {
			return sanitize_text_field( $l );
		}
		return '';
	}

	/**
	 * Square feet from attributes when present.
	 *
	 * @param array<string, mixed> $raw Raw listing.
	 * @return int|null
	 */
	private function zoopla_extract_floor_area_sqft( array $raw ): ?int {
		$attrs = isset( $raw['attributes'] ) && is_array( $raw['attributes'] ) ? $raw['attributes'] : array();
		foreach ( array( 'floorAreaSquareFeet', 'floorAreaSqFeet', 'squareFeet', 'floorAreaInSqft' ) as $k ) {
			if ( isset( $attrs[ $k ] ) && is_numeric( $attrs[ $k ] ) ) {
				$n = absint( $attrs[ $k ] );
				return $n > 0 ? $n : null;
			}
		}
		if ( isset( $attrs['floorArea'] ) ) {
			$fa = $attrs['floorArea'];
			if ( is_numeric( $fa ) ) {
				$n = absint( $fa );
				return $n > 0 ? $n : null;
			}
			if ( is_string( $fa ) && preg_match( '/([\d][\d,\.]*)\s*sq\.?\s*ft\b/i', $fa, $m ) ) {
				$n = absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
				return $n > 0 ? $n : null;
			}
			if ( is_array( $fa ) ) {
				$val  = $fa['value'] ?? $fa['amount'] ?? null;
				$unit = strtolower( (string) ( $fa['unit'] ?? '' ) );
				if ( is_numeric( $val ) ) {
					$n = absint( $val );
					if ( $n < 1 ) {
						return null;
					}
					if ( '' !== $unit && ( str_contains( $unit, 'metre' ) || str_contains( $unit, 'sqm' ) || str_contains( $unit, 'm²' ) || str_contains( $unit, 'm2' ) ) ) {
						return (int) round( $n * 10.7639 );
					}
					return $n;
				}
			}
		}
		return null;
	}

	/**
	 * First listed / published date as Y-m-d.
	 *
	 * @param array<string, mixed> $raw Raw listing.
	 */
	private function zoopla_extract_first_listed_date_ymd( array $raw ): string {
		$candidates = array();
		if ( isset( $raw['listingDates'] ) && is_array( $raw['listingDates'] ) ) {
			$candidates[] = $raw['listingDates']['firstVisibleDate'] ?? '';
			$candidates[] = $raw['listingDates']['publishedDate'] ?? '';
		}
		$candidates[] = $raw['publicationDate'] ?? '';
		$candidates[] = $raw['publishedDate'] ?? '';
		$candidates[] = $raw['createdDate'] ?? '';
		if ( isset( $raw['dates'] ) && is_array( $raw['dates'] ) ) {
			$candidates[] = $raw['dates']['published'] ?? '';
			$candidates[] = $raw['dates']['firstPublished'] ?? '';
		}
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
	 * Branch or listing company display name.
	 *
	 * @param array<string, mixed> $raw Raw listing.
	 */
	private function zoopla_extract_agent_name( array $raw ): string {
		$paths = array(
			array( 'branch', 'name' ),
			array( 'branch', 'branchName' ),
			array( 'branchName' ),
			array( 'formattedBranchName' ),
			array( 'listingCompany', 'name' ),
			array( 'agent', 'branchName' ),
			array( 'agent', 'name' ),
		);
		foreach ( $paths as $path ) {
			$v = $raw;
			foreach ( $path as $p ) {
				if ( ! is_array( $v ) || ! isset( $v[ $p ] ) ) {
					continue 2;
				}
				$v = $v[ $p ];
			}
			if ( is_scalar( $v ) ) {
				$name = trim( (string) $v );
				if ( '' !== $name ) {
					return sanitize_text_field( $name );
				}
			}
		}
		return '';
	}

	/**
	 * Feature bullets joined with "|" for storage.
	 *
	 * @param array<string, mixed> $raw Raw listing.
	 */
	private function zoopla_extract_key_features_pipe( array $raw ): string {
		$lists = array();
		if ( isset( $raw['attributes']['features'] ) && is_array( $raw['attributes']['features'] ) ) {
			$lists[] = $raw['attributes']['features'];
		}
		if ( isset( $raw['highlights'] ) && is_array( $raw['highlights'] ) ) {
			$lists[] = $raw['highlights'];
		}
		if ( isset( $raw['bulletPoints'] ) && is_array( $raw['bulletPoints'] ) ) {
			$lists[] = $raw['bulletPoints'];
		}
		if ( isset( $raw['featureSummary'] ) && is_array( $raw['featureSummary'] ) ) {
			$lists[] = $raw['featureSummary'];
		}
		$out = array();
		foreach ( $lists as $list ) {
			foreach ( $list as $item ) {
				if ( is_array( $item ) ) {
					$item = $item['text'] ?? $item['value'] ?? $item['label'] ?? '';
				}
				if ( ! is_scalar( $item ) ) {
					continue;
				}
				$t = sanitize_text_field( trim( (string) $item ) );
				if ( '' !== $t ) {
					$out[] = $t;
				}
			}
		}
		if ( empty( $out ) ) {
			return '';
		}
		return implode( '|', $out );
	}
}
