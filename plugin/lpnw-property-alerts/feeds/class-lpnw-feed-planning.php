<?php
/**
 * Planning Portal data feed.
 *
 * Pulls planning application data from planning.data.gov.uk API
 * filtered to Northwest England local authorities.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_Planning extends LPNW_Feed_Base {

	private const API_BASE = 'https://www.planning.data.gov.uk/api/v1/';

	/**
	 * NW England local authority district codes.
	 * These are the 3/4-letter ONS codes used by the Planning Data API.
	 */
	private const NW_AUTHORITIES = array(
		'E08000001', // Bolton
		'E08000002', // Bury
		'E08000003', // Manchester
		'E08000004', // Oldham
		'E08000005', // Rochdale
		'E08000006', // Salford
		'E08000007', // Stockport
		'E08000008', // Tameside
		'E08000009', // Trafford
		'E08000010', // Wigan
		'E08000011', // Knowsley
		'E08000012', // Liverpool
		'E08000013', // St Helens
		'E08000014', // Sefton
		'E08000015', // Wirral
		'E06000006', // Halton
		'E06000007', // Warrington
		'E06000049', // Cheshire East
		'E06000050', // Cheshire West and Chester
		'E06000008', // Blackburn with Darwen
		'E06000009', // Blackpool
		'E07000117', // Burnley
		'E07000118', // Chorley
		'E07000119', // Fylde
		'E07000120', // Hyndburn
		'E07000121', // Lancaster
		'E07000122', // Pendle
		'E07000123', // Preston
		'E07000124', // Ribble Valley
		'E07000125', // Rossendale
		'E07000126', // South Ribble
		'E07000127', // West Lancashire
		'E07000128', // Wyre
		'E06000063', // Cumberland
		'E06000064', // Westmorland and Furness
	);

	public function get_source_name(): string {
		return 'planning';
	}

	protected function fetch(): array {
		$all_results = array();
		$since       = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		foreach ( self::NW_AUTHORITIES as $authority ) {
			$results = $this->fetch_authority( $authority, $since );
			$all_results = array_merge( $all_results, $results );

			usleep( 250000 );
		}

		return $all_results;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_authority( string $authority_code, string $since ): array {
		$url = add_query_arg(
			array(
				'dataset'                => 'planning-application',
				'organisation-entity'    => $authority_code,
				'start-date-day-since'   => $since,
				'limit'                  => 100,
			),
			self::API_BASE . 'entity.json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			error_log( 'LPNW Planning feed error for ' . $authority_code . ': ' . $response->get_error_message() );
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['entities'] ?? array();
	}

	/**
	 * @param array<string, mixed> $raw_item Raw entity from Planning Data API.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$postcode = $this->extract_postcode( $raw_item );

		return array(
			'source'           => $this->get_source_name(),
			'source_ref'       => sanitize_text_field( $raw_item['reference'] ?? $raw_item['entity'] ?? '' ),
			'address'          => sanitize_text_field( $raw_item['address'] ?? $raw_item['name'] ?? '' ),
			'postcode'         => $postcode,
			'latitude'         => isset( $raw_item['point'] ) ? floatval( $raw_item['point']['coordinates'][1] ?? 0 ) : null,
			'longitude'        => isset( $raw_item['point'] ) ? floatval( $raw_item['point']['coordinates'][0] ?? 0 ) : null,
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
			return $item['postcode'];
		}

		$address = $item['address'] ?? $item['name'] ?? '';
		if ( preg_match( '/([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})/i', $address, $matches ) ) {
			return strtoupper( $matches[1] );
		}

		return '';
	}
}
