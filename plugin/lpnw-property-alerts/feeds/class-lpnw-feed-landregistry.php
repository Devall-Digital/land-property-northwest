<?php
/**
 * HM Land Registry Price Paid data feed.
 *
 * Downloads monthly CSV from gov.uk and filters to NW postcodes.
 * Data is published around the 20th of each month for the previous month.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_LandRegistry extends LPNW_Feed_Base {

	private const CSV_URL = 'http://prod.publicdata.landregistry.gov.uk/pp-monthly-update-new-version.csv';

	public function get_source_name(): string {
		return 'landregistry';
	}

	protected function fetch(): array {
		$upload_dir = wp_upload_dir();
		$tmp_file   = $upload_dir['basedir'] . '/lpnw-landregistry-temp.csv';

		$response = wp_remote_get( self::CSV_URL, array(
			'timeout'  => 120,
			'stream'   => true,
			'filename' => $tmp_file,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'LPNW Land Registry feed error: ' . $response->get_error_message() );
			return array();
		}

		$results = $this->parse_csv( $tmp_file );

		wp_delete_file( $tmp_file );

		return $results;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_csv( string $filepath ): array {
		$results = array();

		if ( ! file_exists( $filepath ) ) {
			return $results;
		}

		$handle = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return $results;
		}

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( count( $row ) < 14 ) {
				continue;
			}

			$postcode = trim( $row[3] ?? '' );

			if ( ! $this->is_nw_postcode( $postcode ) ) {
				continue;
			}

			$results[] = array(
				'transaction_id' => $row[0] ?? '',
				'price'          => $row[1] ?? '',
				'date'           => $row[2] ?? '',
				'postcode'       => $postcode,
				'property_type'  => $row[4] ?? '',
				'new_build'      => $row[5] ?? '',
				'tenure'         => $row[6] ?? '',
				'paon'           => $row[7] ?? '',
				'saon'           => $row[8] ?? '',
				'street'         => $row[9] ?? '',
				'locality'       => $row[10] ?? '',
				'town'           => $row[11] ?? '',
				'district'       => $row[12] ?? '',
				'county'         => $row[13] ?? '',
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $results;
	}

	/**
	 * @param array<string, mixed> $raw_item Parsed CSV row.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$type_map = array(
			'D' => 'Detached',
			'S' => 'Semi-detached',
			'T' => 'Terraced',
			'F' => 'Flat/Maisonette',
			'O' => 'Other',
		);

		$address_parts = array_filter( array(
			$raw_item['saon'] ?? '',
			$raw_item['paon'] ?? '',
			$raw_item['street'] ?? '',
			$raw_item['locality'] ?? '',
			$raw_item['town'] ?? '',
		) );

		$property_type = $type_map[ $raw_item['property_type'] ?? '' ] ?? $raw_item['property_type'] ?? '';
		$is_new        = ( 'Y' === ( $raw_item['new_build'] ?? '' ) ) ? 'New build' : 'Existing';

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => sanitize_text_field( trim( $raw_item['transaction_id'] ?? '', '{}' ) ),
			'address'       => sanitize_text_field( implode( ', ', $address_parts ) ),
			'postcode'      => sanitize_text_field( $raw_item['postcode'] ?? '' ),
			'price'         => absint( $raw_item['price'] ?? 0 ),
			'property_type' => $property_type,
			'description'   => sprintf(
				'%s %s sold for %s on %s. Tenure: %s.',
				$is_new,
				strtolower( $property_type ),
				number_format( absint( $raw_item['price'] ?? 0 ) ),
				sanitize_text_field( $raw_item['date'] ?? '' ),
				sanitize_text_field( $raw_item['tenure'] ?? '' )
			),
			'source_url'    => '',
			'raw_data'      => $raw_item,
		);
	}
}
