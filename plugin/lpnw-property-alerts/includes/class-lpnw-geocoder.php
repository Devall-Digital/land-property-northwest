<?php
/**
 * Postcode geocoder.
 *
 * Converts UK postcodes to latitude/longitude using the free postcodes.io API.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Geocoder {

	private const API_URL = 'https://api.postcodes.io/postcodes/';

	/**
	 * Geocode a single UK postcode.
	 *
	 * @param string $postcode UK postcode.
	 * @return array{latitude: float, longitude: float}|null
	 */
	public static function geocode( string $postcode ): ?array {
		$postcode = strtoupper( trim( $postcode ) );
		if ( empty( $postcode ) ) {
			return null;
		}

		$cache_key = 'lpnw_geo_' . md5( $postcode );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url      = self::API_URL . rawurlencode( $postcode );
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['result'] ) ) {
			return null;
		}

		$result = array(
			'latitude'  => (float) $body['result']['latitude'],
			'longitude' => (float) $body['result']['longitude'],
		);

		set_transient( $cache_key, $result, WEEK_IN_SECONDS );

		return $result;
	}

	/**
	 * Bulk geocode up to 100 postcodes in a single API call.
	 *
	 * @param array<string> $postcodes Array of UK postcodes.
	 * @return array<string, array{latitude: float, longitude: float}>
	 */
	public static function bulk_geocode( array $postcodes ): array {
		$results  = array();
		$to_fetch = array();

		foreach ( $postcodes as $pc ) {
			$pc        = strtoupper( trim( $pc ) );
			$cache_key = 'lpnw_geo_' . md5( $pc );
			$cached    = get_transient( $cache_key );

			if ( false !== $cached ) {
				$results[ $pc ] = $cached;
			} else {
				$to_fetch[] = $pc;
			}
		}

		if ( empty( $to_fetch ) ) {
			return $results;
		}

		$chunks = array_chunk( $to_fetch, 100 );

		foreach ( $chunks as $chunk ) {
			$response = wp_remote_post(
				'https://api.postcodes.io/postcodes',
				array(
					'timeout' => 30,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array( 'postcodes' => $chunk ) ),
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $body['result'] ) ) {
				continue;
			}

			foreach ( $body['result'] as $item ) {
				if ( empty( $item['result'] ) ) {
					continue;
				}

				$pc     = strtoupper( $item['query'] );
				$coords = array(
					'latitude'  => (float) $item['result']['latitude'],
					'longitude' => (float) $item['result']['longitude'],
				);

				$results[ $pc ] = $coords;
				set_transient( 'lpnw_geo_' . md5( $pc ), $coords, WEEK_IN_SECONDS );
			}
		}

		return $results;
	}
}
