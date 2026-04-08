<?php
/**
 * Postcode geocoder.
 *
 * Converts UK postcodes to latitude/longitude, and reverse geocodes coordinates
 * to the nearest postcode, using the free postcodes.io API.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Geocoder {

	private const API_URL = 'https://api.postcodes.io/postcodes/';

	/**
	 * How long reverse geocode results are cached (seconds).
	 */
	private const REVERSE_GEOCODE_TTL = 30 * DAY_IN_SECONDS;

	/**
	 * Clean parish label from postcodes.io (drops ", unparished area" suffix).
	 */
	private static function clean_parish_name( ?string $parish ): string {
		if ( null === $parish || '' === trim( $parish ) ) {
			return '';
		}
		$p = trim( $parish );
		$p = preg_replace( '/,\s*unparished area$/i', '', $p );
		return is_string( $p ) ? trim( $p ) : '';
	}

	/**
	 * Geocode a single UK postcode.
	 *
	 * @param string $postcode UK postcode.
	 * @return array{
	 *   latitude: float,
	 *   longitude: float,
	 *   admin_district?: string,
	 *   admin_ward?: string,
	 *   parish?: string,
	 *   nuts?: string,
	 *   outcode?: string
	 * }|null
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

		$r        = $body['result'];
		$result   = array(
			'latitude'  => (float) $r['latitude'],
			'longitude' => (float) $r['longitude'],
		);
		$district = isset( $r['admin_district'] ) ? sanitize_text_field( (string) $r['admin_district'] ) : '';
		if ( '' !== $district ) {
			$result['admin_district'] = $district;
		}
		$ward = isset( $r['admin_ward'] ) ? sanitize_text_field( (string) $r['admin_ward'] ) : '';
		if ( '' !== $ward ) {
			$result['admin_ward'] = $ward;
		}
		$parish = isset( $r['parish'] ) ? self::clean_parish_name( (string) $r['parish'] ) : '';
		if ( '' !== $parish ) {
			$result['parish'] = $parish;
		}
		$nuts = isset( $r['nuts'] ) ? sanitize_text_field( (string) $r['nuts'] ) : '';
		if ( '' !== $nuts ) {
			$result['nuts'] = $nuts;
		}
		$outcode = isset( $r['outcode'] ) ? strtoupper( trim( (string) $r['outcode'] ) ) : '';
		if ( '' !== $outcode ) {
			$result['outcode'] = $outcode;
		}

		set_transient( $cache_key, $result, WEEK_IN_SECONDS );

		return $result;
	}

	/**
	 * Reverse geocode coordinates to the nearest UK postcode via postcodes.io.
	 *
	 * Results are cached by coordinates rounded to four decimal places.
	 *
	 * @param float $lat Latitude (WGS84).
	 * @param float $lng Longitude (WGS84).
	 * @return string|null Postcode or null if not resolved.
	 */
	public static function reverse_geocode( float $lat, float $lng ): ?string {
		if ( ! is_finite( $lat ) || ! is_finite( $lng ) ) {
			return null;
		}

		$lat_key   = round( $lat, 4 );
		$lng_key   = round( $lng, 4 );
		$cache_key = 'lpnw_rgeo_' . $lat_key . '_' . $lng_key;

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return '' === $cached ? null : $cached;
		}

		$url = add_query_arg(
			array(
				'lon'   => $lng,
				'lat'   => $lat,
				'limit' => 1,
			),
			'https://api.postcodes.io/postcodes'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['result'] ) || ! is_array( $body['result'] ) ) {
			set_transient( $cache_key, '', self::REVERSE_GEOCODE_TTL );
			return null;
		}

		$first = $body['result'][0];
		if ( empty( $first['postcode'] ) || ! is_string( $first['postcode'] ) ) {
			set_transient( $cache_key, '', self::REVERSE_GEOCODE_TTL );
			return null;
		}

		$postcode = strtoupper( trim( $first['postcode'] ) );
		set_transient( $cache_key, $postcode, self::REVERSE_GEOCODE_TTL );

		return $postcode;
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

				$pc       = strtoupper( $item['query'] );
				$ir       = $item['result'];
				$coords   = array(
					'latitude'  => (float) $ir['latitude'],
					'longitude' => (float) $ir['longitude'],
				);
				$district = isset( $ir['admin_district'] ) ? sanitize_text_field( (string) $ir['admin_district'] ) : '';
				if ( '' !== $district ) {
					$coords['admin_district'] = $district;
				}
				$ward = isset( $ir['admin_ward'] ) ? sanitize_text_field( (string) $ir['admin_ward'] ) : '';
				if ( '' !== $ward ) {
					$coords['admin_ward'] = $ward;
				}
				$parish = isset( $ir['parish'] ) ? self::clean_parish_name( (string) $ir['parish'] ) : '';
				if ( '' !== $parish ) {
					$coords['parish'] = $parish;
				}
				$nuts = isset( $ir['nuts'] ) ? sanitize_text_field( (string) $ir['nuts'] ) : '';
				if ( '' !== $nuts ) {
					$coords['nuts'] = $nuts;
				}
				$outcode = isset( $ir['outcode'] ) ? strtoupper( trim( (string) $ir['outcode'] ) ) : '';
				if ( '' !== $outcode ) {
					$coords['outcode'] = $outcode;
				}

				$results[ $pc ] = $coords;
				set_transient( 'lpnw_geo_' . md5( $pc ), $coords, WEEK_IN_SECONDS );
			}
		}

		return $results;
	}
}
