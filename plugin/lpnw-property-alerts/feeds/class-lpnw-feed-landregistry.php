<?php
/**
 * HM Land Registry Price Paid data feed.
 *
 * Downloads monthly CSV from gov.uk and filters to NW postcodes.
 * Data is published around the 20th of each month for the previous month.
 *
 * CSV has no header row. Column order matches HM Land Registry Price Paid schema.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_LandRegistry extends LPNW_Feed_Base {

	private const CSV_URL = 'http://prod.publicdata.landregistry.gov.uk/pp-monthly-update-new-version.csv';

	/** Minimum expected columns per Price Paid row. */
	private const COLUMN_COUNT = 14;

	/** HTTP timeout for large CSV download (seconds). */
	private const DOWNLOAD_TIMEOUT = 600;

	public function get_source_name(): string {
		return 'landregistry';
	}

	protected function fetch(): array {
		$tmp_file = wp_normalize_path(
			trailingslashit( get_temp_dir() ) . 'lpnw-lr-' . wp_generate_password( 16, false, false ) . '.csv'
		);

		$results = array();

		try {
			$response = wp_remote_get(
				self::CSV_URL,
				array(
					'timeout'   => self::DOWNLOAD_TIMEOUT,
					'stream'    => true,
					'filename'  => $tmp_file,
					'sslverify' => true,
					'redirection' => 5,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->lpnw_log( 'Download failed: ' . $response->get_error_message() );
				return array();
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				$this->lpnw_log( sprintf( 'Download returned HTTP %d.', $code ) );
				return array();
			}

			if ( ! is_string( $tmp_file ) || '' === $tmp_file ) {
				$this->lpnw_log( 'Invalid temp file path after download.' );
				return array();
			}

			if ( ! is_readable( $tmp_file ) ) {
				$this->lpnw_log( 'Temp file missing or not readable after download.' );
				return array();
			}

			$size = @filesize( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- file may be absent in race conditions.
			if ( false === $size || $size < 32 ) {
				$this->lpnw_log( 'Downloaded file is empty or too small.' );
				return array();
			}

			$results = $this->lpnw_parse_csv_file( $tmp_file );
		} catch ( \Throwable $e ) {
			$this->lpnw_log( 'Unexpected error during download or parse: ' . $e->getMessage() );
			$results = array();
		} finally {
			$this->lpnw_delete_temp_file( $tmp_file );
		}

		return $results;
	}

	/**
	 * @param string $filepath Absolute path to CSV.
	 * @return array<int, array<string, mixed>>
	 */
	private function lpnw_parse_csv_file( string $filepath ): array {
		$results = array();

		$handle = fopen( $filepath, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming large gov.uk CSV.
		if ( false === $handle ) {
			$this->lpnw_log( 'Could not open CSV for reading.' );
			return $results;
		}

		$line        = 0;
		$matched     = 0;
		$max_line_len = 65536;

		try {
			while ( ( $row = fgetcsv( $handle, $max_line_len ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				++$line;
				if ( ! is_array( $row ) || count( $row ) < self::COLUMN_COUNT ) {
					continue;
				}

				$postcode = strtoupper( trim( (string) ( $row[3] ?? '' ) ) );
				$postcode = preg_replace( '/\s+/', ' ', $postcode );
				$postcode = is_string( $postcode ) ? $postcode : '';

				if ( '' === $postcode || ! $this->is_nw_postcode( $postcode ) ) {
					continue;
				}

				++$matched;

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
		} finally {
			if ( is_resource( $handle ) ) {
				if ( false === fclose( $handle ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					$this->lpnw_log( 'Warning: fclose failed for CSV handle.' );
				}
			}
		}

		if ( 0 === $line ) {
			$this->lpnw_log( 'CSV contained no rows.' );
		} elseif ( 0 === $matched ) {
			$this->lpnw_log( sprintf( 'CSV had %d rows but none matched NW postcode prefixes.', $line ) );
		}

		return $results;
	}

	/**
	 * @param string $path Temp file path.
	 */
	private function lpnw_delete_temp_file( string $path ): void {
		if ( '' === $path || ! file_exists( $path ) || ! is_string( $path ) ) {
			return;
		}

		wp_delete_file( $path );

		if ( file_exists( $path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Fallback if wp_delete_file failed (permissions).
			@unlink( $path );
			if ( file_exists( $path ) ) {
				$this->lpnw_log( 'Could not remove temp CSV file; delete manually if disk space is a concern.' );
			}
		}
	}

	/**
	 * Log feed issues (no PII).
	 *
	 * @param string $message Log message.
	 */
	private function lpnw_log( string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational feed diagnostics.
		error_log( 'LPNW Land Registry feed: ' . $message );
	}

	/**
	 * Parse Price Paid price cell to a non-negative integer (pence not used; whole pounds).
	 *
	 * @param mixed $raw Price field from CSV.
	 * @return int>=0
	 */
	private function lpnw_parse_price( $raw ): int {
		if ( is_int( $raw ) ) {
			return max( 0, $raw );
		}
		$digits = preg_replace( '/\D/', '', (string) $raw );
		if ( null === $digits || '' === $digits ) {
			return 0;
		}
		return absint( $digits );
	}

	/**
	 * Normalise transaction date to Y-m-d or empty if invalid.
	 *
	 * @param string $raw Date from CSV.
	 * @return string
	 */
	private function lpnw_normalise_transaction_date( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		$dt = DateTimeImmutable::createFromFormat( '!Y-m-d', $raw );
		if ( $dt instanceof DateTimeImmutable ) {
			return $dt->format( 'Y-m-d' );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $raw_item Parsed CSV row.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$postcode = sanitize_text_field( $raw_item['postcode'] ?? '' );
		if ( ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		$type_map = array(
			'D' => 'Detached',
			'S' => 'Semi-detached',
			'T' => 'Terraced',
			'F' => 'Flat/Maisonette',
			'O' => 'Other',
		);

		$address_parts = array_filter(
			array(
				$raw_item['saon'] ?? '',
				$raw_item['paon'] ?? '',
				$raw_item['street'] ?? '',
				$raw_item['locality'] ?? '',
				$raw_item['town'] ?? '',
			),
			static function ( $part ) {
				return is_string( $part ) && '' !== trim( $part );
			}
		);

		$ptype_code    = sanitize_text_field( $raw_item['property_type'] ?? '' );
		$property_type = $type_map[ $ptype_code ] ?? $ptype_code;
		$is_new        = ( 'Y' === ( $raw_item['new_build'] ?? '' ) ) ? __( 'New build', 'lpnw-alerts' ) : __( 'Existing', 'lpnw-alerts' );

		$price  = $this->lpnw_parse_price( $raw_item['price'] ?? 0 );
		$date   = $this->lpnw_normalise_transaction_date( sanitize_text_field( $raw_item['date'] ?? '' ) );
		$tenure = sanitize_text_field( $raw_item['tenure'] ?? '' );

		$txn_raw = sanitize_text_field( (string) ( $raw_item['transaction_id'] ?? '' ) );
		$txn_id  = trim( str_replace( array( '{', '}' ), '', $txn_raw ) );

		if ( '' === $txn_id ) {
			return array();
		}

		$ptype_lower = strtolower( $property_type );
		$date_label  = '' !== $date ? $date : __( 'unknown date', 'lpnw-alerts' );

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => $txn_id,
			'address'       => sanitize_text_field( implode( ', ', $address_parts ) ),
			'postcode'      => $postcode,
			'price'         => $price,
			'property_type' => $property_type,
			'description'   => sprintf(
				/* translators: 1: New or Existing, 2: property type (lowercase), 3: formatted price, 4: date, 5: tenure */
				__( '%1$s %2$s sold for %3$s on %4$s. Tenure: %5$s.', 'lpnw-alerts' ),
				$is_new,
				$ptype_lower,
				number_format_i18n( $price ),
				$date_label,
				$tenure
			),
			'source_url'    => '',
			'raw_data'      => $raw_item,
		);
	}
}
