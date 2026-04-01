<?php
/**
 * Planning Portal data feed.
 *
 * Pulls planning application data from planning.data.gov.uk API
 * filtered to Northwest England local authorities.
 *
 * API docs: https://www.planning.data.gov.uk/docs/
 * No authentication required. Rate limit: be polite, add delays.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Planning extends LPNW_Feed_Base {

	private const API_BASE = 'https://www.planning.data.gov.uk/entity.json';

	/**
	 * NW England local planning authority boundary entity IDs.
	 *
	 * Each value is the `entity` field from the `local-planning-authority` dataset
	 * (626xxx series), not ONS codes and not planning-application `organisation-entity`.
	 *
	 * List from: /entity.json?dataset=local-planning-authority&limit=500&field=name&field=entity
	 *
	 * Scope planning applications to each LPA using `geometry_entity` (the boundary
	 * entity id) and `geometry_relation=within`, per Planning Data documentation.
	 * The `organisation_entity` parameter expects a different publisher id and must not
	 * be set to these LPA boundary values.
	 */
	private const NW_LPA_ENTITIES = array(
		// Verified against planning.data.gov.uk on 1 April 2026
		// Greater Manchester
		626025, // Bolton
		626026, // Bury
		626027, // Manchester
		626028, // Oldham
		626029, // Rochdale
		626030, // Salford
		626031, // Stockport
		626032, // Tameside
		626033, // Trafford
		626034, // Wigan

		// Merseyside
		626047, // Knowsley
		626048, // Liverpool
		626050, // St. Helens
		626049, // Sefton
		626051, // Wirral

		// Cheshire / Warrington / Halton
		626017, // Halton
		626018, // Warrington
		626015, // Cheshire East
		626016, // Cheshire West and Chester

		// Lancashire
		626013, // Blackburn with Darwen
		626014, // Blackpool
		626035, // Burnley
		626036, // Chorley
		626037, // Fylde
		626038, // Hyndburn
		626039, // Lancaster
		626040, // Pendle
		626041, // Preston
		626042, // Ribble Valley
		626043, // Rossendale
		626044, // South Ribble
		626045, // West Lancashire
		626046, // Wyre

		// Cumbria
		626334, // Cumberland
		626335, // Westmorland and Furness
	);

	public function get_source_name(): string {
		return 'planning';
	}

	protected function fetch(): array {
		$all_results = array();
		$since_date  = new \DateTime( '-7 days', new \DateTimeZone( 'UTC' ) );

		foreach ( self::NW_LPA_ENTITIES as $lpa_entity ) {
			$page_results = $this->fetch_authority( $lpa_entity, $since_date );
			$all_results  = array_merge( $all_results, $page_results );

			usleep( 300000 );
		}

		return $all_results;
	}

	/**
	 * Fetch planning applications for a single LPA, handling pagination.
	 *
	 * @param int       $lpa_entity LPA entity ID.
	 * @param \DateTime $since      Fetch applications since this date.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_authority( int $lpa_entity, \DateTime $since ): array {
		$results = array();
		$offset  = 0;
		$limit   = 500;

		do {
			$url = add_query_arg(
				array(
					'dataset'           => 'planning-application',
					'geometry_entity'   => $lpa_entity,
					'geometry_relation' => 'within',
					'start_date_year'   => $since->format( 'Y' ),
					'start_date_month'  => $since->format( 'n' ),
					'start_date_day'    => $since->format( 'j' ),
					'start_date_match'  => 'since',
					'limit'             => $limit,
					'offset'            => $offset,
				),
				self::API_BASE
			);

			$response = wp_remote_get( $url, array( 'timeout' => 45 ) );

			if ( is_wp_error( $response ) ) {
				error_log( 'LPNW Planning feed error for entity ' . $lpa_entity . ': ' . $response->get_error_message() );
				break;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				error_log( 'LPNW Planning feed HTTP ' . $code . ' for entity ' . $lpa_entity );
				break;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body ) || ! isset( $body['entities'] ) ) {
				break;
			}

			$entities = $body['entities'];
			$results  = array_merge( $results, $entities );

			$has_more = ! empty( $body['links']['next'] ) && count( $entities ) >= $limit;
			$offset  += $limit;

			if ( $has_more ) {
				usleep( 200000 );
			}
		} while ( $has_more );

		return $results;
	}

	/**
	 * @param array<string, mixed> $raw_item Raw entity from Planning Data API.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$postcode = $this->extract_postcode( $raw_item );

		$lat = null;
		$lng = null;
		if ( ! empty( $raw_item['point'] ) && is_string( $raw_item['point'] ) ) {
			if ( preg_match( '/POINT\s*\(\s*([-\d.]+)\s+([-\d.]+)\s*\)/', $raw_item['point'], $m ) ) {
				$lng = floatval( $m[1] );
				$lat = floatval( $m[2] );
			}
		} elseif ( ! empty( $raw_item['point']['coordinates'] ) ) {
			$lng = floatval( $raw_item['point']['coordinates'][0] );
			$lat = floatval( $raw_item['point']['coordinates'][1] );
		}

		return array(
			'source'           => $this->get_source_name(),
			'source_ref'       => sanitize_text_field( (string) ( $raw_item['reference'] ?? $raw_item['entity'] ?? '' ) ),
			'address'          => sanitize_text_field( $raw_item['address'] ?? $raw_item['name'] ?? '' ),
			'postcode'         => $postcode,
			'latitude'         => $lat,
			'longitude'        => $lng,
			'price'            => null,
			'property_type'    => sanitize_text_field( $raw_item['planning-application-type'] ?? '' ),
			'description'      => wp_kses_post( $raw_item['description'] ?? '' ),
			'application_type' => sanitize_text_field( $raw_item['planning-application-type'] ?? '' ),
			'source_url'       => esc_url_raw( $raw_item['document-url'] ?? '' ),
			'raw_data'         => $raw_item,
		);
	}

	private function extract_postcode( array $item ): string {
		if ( ! empty( $item['postcode'] ) ) {
			return strtoupper( trim( $item['postcode'] ) );
		}

		$address = $item['address'] ?? $item['name'] ?? '';
		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $matches ) ) {
			return strtoupper( $matches[1] );
		}

		return '';
	}
}
