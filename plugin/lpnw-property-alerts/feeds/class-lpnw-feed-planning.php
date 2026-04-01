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
	 * NW England local planning authority entity IDs.
	 *
	 * These are the numeric entity IDs used by planning.data.gov.uk,
	 * NOT ONS codes. To find entity IDs for an LPA, query:
	 * /entity.json?dataset=local-planning-authority&field=name&field=entity
	 *
	 * The API accepts organisation_entity (underscore) as the parameter name.
	 * The values below are sourced from the planning.data.gov.uk dataset.
	 * Some LPAs may not yet publish data via this platform.
	 */
	private const NW_LPA_ENTITIES = array(
		// Greater Manchester
		64,   // Bolton
		66,   // Bury
		159,  // Manchester
		176,  // Oldham
		186,  // Rochdale
		192,  // Salford
		200,  // Stockport
		203,  // Tameside
		209,  // Trafford
		220,  // Wigan

		// Merseyside
		139,  // Knowsley
		151,  // Liverpool
		197,  // St Helens
		194,  // Sefton
		222,  // Wirral

		// Cheshire / Warrington / Halton
		72,   // Cheshire East
		73,   // Cheshire West and Chester
		115,  // Halton
		216,  // Warrington

		// Lancashire
		55,   // Blackburn with Darwen
		57,   // Blackpool
		68,   // Burnley
		75,   // Chorley
		96,   // Fylde
		120,  // Hyndburn
		143,  // Lancaster
		178,  // Pendle
		184,  // Preston
		187,  // Ribble Valley
		189,  // Rossendale
		196,  // South Ribble
		219,  // West Lancashire
		224,  // Wyre

		// Cumbria
		86,   // Cumberland
		221,  // Westmorland and Furness
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
					'dataset'            => 'planning-application',
					'organisation_entity' => $lpa_entity,
					'start_date_year'    => $since->format( 'Y' ),
					'start_date_month'   => $since->format( 'n' ),
					'start_date_day'     => $since->format( 'j' ),
					'start_date_match'   => 'since',
					'limit'              => $limit,
					'offset'             => $offset,
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
