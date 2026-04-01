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
	 * (626xxx series), not ONS codes.
	 *
	 * List from: /entity.json?dataset=local-planning-authority&limit=500&field=name&field=entity
	 *
	 * Scope planning applications to each LPA using `geometry_entity` (the boundary
	 * entity id) and `geometry_relation=within`, per Planning Data documentation.
	 * If `geometry_entity` returns no rows for an LPA, we retry with `organisation_entity`
	 * as a fallback (same numeric id); results may overlap or be empty depending on provider data.
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

	/**
	 * Human labels for feed logs (entity id => name).
	 *
	 * @var array<int, string>
	 */
	private const NW_LPA_ENTITY_LABELS = array(
		626025 => 'Bolton',
		626026 => 'Bury',
		626027 => 'Manchester',
		626028 => 'Oldham',
		626029 => 'Rochdale',
		626030 => 'Salford',
		626031 => 'Stockport',
		626032 => 'Tameside',
		626033 => 'Trafford',
		626034 => 'Wigan',
		626047 => 'Knowsley',
		626048 => 'Liverpool',
		626050 => 'St. Helens',
		626049 => 'Sefton',
		626051 => 'Wirral',
		626017 => 'Halton',
		626018 => 'Warrington',
		626015 => 'Cheshire East',
		626016 => 'Cheshire West and Chester',
		626013 => 'Blackburn with Darwen',
		626014 => 'Blackpool',
		626035 => 'Burnley',
		626036 => 'Chorley',
		626037 => 'Fylde',
		626038 => 'Hyndburn',
		626039 => 'Lancaster',
		626040 => 'Pendle',
		626041 => 'Preston',
		626042 => 'Ribble Valley',
		626043 => 'Rossendale',
		626044 => 'South Ribble',
		626045 => 'West Lancashire',
		626046 => 'Wyre',
		626334 => 'Cumberland',
		626335 => 'Westmorland and Furness',
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
		$lpa_name = self::NW_LPA_ENTITY_LABELS[ $lpa_entity ] ?? (string) $lpa_entity;

		$date_args = array(
			'start_date_year'  => $since->format( 'Y' ),
			'start_date_month' => $since->format( 'n' ),
			'start_date_day'   => $since->format( 'j' ),
			'start_date_match' => 'since',
		);

		$geom_pack = $this->fetch_planning_application_pages(
			array_merge(
				array(
					'dataset'           => 'planning-application',
					'geometry_entity'   => $lpa_entity,
					'geometry_relation' => 'within',
				),
				$date_args
			),
			$lpa_entity,
			$lpa_name,
			'geometry_entity'
		);

		$n_geom = count( $geom_pack['entities'] );
		$this->lpnw_diag_log(
			sprintf(
				'LPA %s (entity %d) geometry_entity returned %d row(s)',
				$lpa_name,
				$lpa_entity,
				$n_geom
			),
			$geom_pack['last_http'],
			$geom_pack['last_len']
		);

		if ( $n_geom > 0 ) {
			return $geom_pack['entities'];
		}

		$this->lpnw_diag_log(
			sprintf(
				'LPA %s (entity %d) geometry empty; trying organisation_entity fallback',
				$lpa_name,
				$lpa_entity
			),
			$geom_pack['last_http'],
			$geom_pack['last_len']
		);

		$org_pack = $this->fetch_planning_application_pages(
			array_merge(
				array(
					'dataset'             => 'planning-application',
					'organisation_entity' => $lpa_entity,
				),
				$date_args
			),
			$lpa_entity,
			$lpa_name,
			'organisation_entity'
		);

		$n_org = count( $org_pack['entities'] );
		$this->lpnw_diag_log(
			sprintf(
				'LPA %s (entity %d) organisation_entity returned %d row(s)',
				$lpa_name,
				$lpa_entity,
				$n_org
			),
			$org_pack['last_http'],
			$org_pack['last_len']
		);

		return $org_pack['entities'];
	}

	/**
	 * Paginate planning-application entities for one query shape.
	 *
	 * @param array<string, scalar> $query_base Dataset + filters (excluding limit/offset).
	 * @param int                   $lpa_entity Entity id for logs.
	 * @param string                $lpa_name   Human label.
	 * @param string                $mode       geometry_entity|organisation_entity (log only).
	 * @return array{entities: array<int, array<string, mixed>>, last_http: int, last_len: int}
	 */
	private function fetch_planning_application_pages( array $query_base, int $lpa_entity, string $lpa_name, string $mode ): array {
		$results    = array();
		$offset     = 0;
		$limit      = 500;
		$last_http  = 0;
		$last_len   = 0;

		$headers = array(
			'User-Agent' => 'LPNW-PropertyAlerts/1.0 (+https://land-property-northwest.co.uk)',
			'Accept'     => 'application/json',
		);

		do {
			$url = add_query_arg(
				array_merge(
					$query_base,
					array(
						'limit'  => $limit,
						'offset' => $offset,
					)
				),
				self::API_BASE
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 45,
					'headers' => $headers,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->lpnw_diag_log(
					sprintf(
						'WP_Error LPA %s entity %d mode=%s: %s',
						$lpa_name,
						$lpa_entity,
						$mode,
						$response->get_error_message()
					),
					0,
					0
				);
				break;
			}

			$last_http = (int) wp_remote_retrieve_response_code( $response );
			$raw       = wp_remote_retrieve_body( $response );
			$raw       = is_string( $raw ) ? $raw : '';
			$last_len  = strlen( $raw );

			if ( 200 !== $last_http ) {
				$this->lpnw_diag_log(
					sprintf(
						'non-200 LPA %s entity %d mode=%s',
						$lpa_name,
						$lpa_entity,
						$mode
					),
					$last_http,
					$last_len
				);
				break;
			}

			$body = json_decode( $raw, true );
			if ( empty( $body ) || ! isset( $body['entities'] ) || ! is_array( $body['entities'] ) ) {
				$this->lpnw_diag_log(
					sprintf(
						'missing entities key or empty JSON LPA %s entity %d mode=%s',
						$lpa_name,
						$lpa_entity,
						$mode
					),
					$last_http,
					$last_len
				);
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

		return array(
			'entities'   => $results,
			'last_http'  => $last_http,
			'last_len'   => $last_len,
		);
	}

	/**
	 * @param string $message   Context.
	 * @param int    $http_code Last HTTP status or 0.
	 * @param int    $resp_len  Raw body length.
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
