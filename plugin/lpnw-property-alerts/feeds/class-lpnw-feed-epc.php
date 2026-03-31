<?php
/**
 * EPC Open Data feed.
 *
 * Pulls Energy Performance Certificate data from the
 * Open Data Communities API (opendatacommunities.org).
 * New EPCs indicate property sales, lettings, or renovations.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_EPC extends LPNW_Feed_Base {

	private const API_BASE = 'https://epc.opendatacommunities.org/api/v1/domestic/search';

	public function get_source_name(): string {
		return 'epc';
	}

	protected function fetch(): array {
		$all_results = array();
		$since       = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$settings    = get_option( 'lpnw_settings', array() );
		$api_key     = $settings['epc_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			error_log( 'LPNW EPC feed: No API key configured.' );
			return array();
		}

		foreach ( LPNW_NW_POSTCODES as $prefix ) {
			$results     = $this->fetch_by_postcode( $prefix, $since, $api_key );
			$all_results = array_merge( $all_results, $results );

			usleep( 500000 );
		}

		return $all_results;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_by_postcode( string $prefix, string $since, string $api_key ): array {
		$url = add_query_arg(
			array(
				'postcode' => $prefix,
				'from-month' => gmdate( 'Y-m', strtotime( '-1 month' ) ),
				'size'     => 1000,
			),
			self::API_BASE
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $api_key . ':' ),
				'Accept'        => 'application/json',
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'LPNW EPC feed error for ' . $prefix . ': ' . $response->get_error_message() );
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['rows'] ?? array();
	}

	/**
	 * @param array<string, mixed> $raw_item Raw EPC record.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$postcode = sanitize_text_field( $raw_item['postcode'] ?? '' );

		if ( ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => sanitize_text_field( $raw_item['lmk-key'] ?? '' ),
			'address'       => sanitize_text_field( $raw_item['address'] ?? '' ),
			'postcode'      => $postcode,
			'price'         => null,
			'property_type' => sanitize_text_field( $raw_item['property-type'] ?? '' ),
			'description'   => sprintf(
				'EPC Rating: %s (%s). Floor area: %s sqm. %s',
				$raw_item['current-energy-rating'] ?? 'N/A',
				$raw_item['current-energy-efficiency'] ?? '',
				$raw_item['total-floor-area'] ?? 'N/A',
				$raw_item['transaction-type'] ?? ''
			),
			'source_url'    => esc_url_raw( $raw_item['certificate-hash']
				? 'https://find-energy-certificate.service.gov.uk/energy-certificate/' . $raw_item['certificate-hash']
				: ''
			),
			'raw_data'      => $raw_item,
		);
	}
}
