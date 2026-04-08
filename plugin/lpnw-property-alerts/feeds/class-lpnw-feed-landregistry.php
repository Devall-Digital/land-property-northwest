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

	/**
	 * Option: Last-Modified header value from the last successful CSV download.
	 * Used to skip re-download when the monthly file has not changed.
	 */
	private const OPTION_CSV_LAST_MODIFIED = 'lpnw_lr_csv_last_modified';

	/** Minimum expected columns per Price Paid row. */
	private const COLUMN_COUNT = 14;

	/** HTTP timeout for large CSV download (seconds). */
	private const DOWNLOAD_TIMEOUT = 600;

	/** Timeout for HEAD probe (seconds). */
	private const HEAD_TIMEOUT = 45;

	public function get_source_name(): string {
		return 'landregistry';
	}

	protected function fetch(): array {
		if ( $this->lpnw_lr_should_skip_download() ) {
			return array();
		}

		$tmp_file = wp_normalize_path(
			trailingslashit( get_temp_dir() ) . 'lpnw-lr-' . wp_generate_password( 16, false, false ) . '.csv'
		);

		$results = array();

		try {
			$response = wp_remote_get(
				self::CSV_URL,
				array(
					'timeout'     => self::DOWNLOAD_TIMEOUT,
					'stream'      => true,
					'filename'    => $tmp_file,
					'sslverify'   => true,
					'redirection' => 5,
					'headers'     => array(
						'User-Agent' => 'LPNW-PropertyAlerts/1.0 (+https://land-property-northwest.co.uk)',
						'Accept'     => 'text/csv,*/*;q=0.8',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->lpnw_log( 'Download failed: ' . $response->get_error_message(), 0, 0 );
				return array();
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$hdr  = wp_remote_retrieve_header( $response, 'content-length' );
			$clen = is_numeric( $hdr ) ? (int) $hdr : 0;

			if ( 200 !== $code ) {
				$body = wp_remote_retrieve_body( $response );
				$blen = is_string( $body ) ? strlen( $body ) : 0;
				$this->lpnw_log(
					sprintf(
						'CSV URL %s returned HTTP %d (file may have moved; check HM Land Registry Price Paid open data).',
						self::CSV_URL,
						$code
					),
					$code,
					$blen > 0 ? $blen : $clen
				);
				return array();
			}

			if ( ! is_string( $tmp_file ) || '' === $tmp_file ) {
				$this->lpnw_log( 'Invalid temp file path after download.', $code, $clen );
				return array();
			}

			if ( ! is_readable( $tmp_file ) ) {
				$this->lpnw_log( 'Temp file missing or not readable after download.', $code, $clen );
				return array();
			}

			$size = @filesize( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- file may be absent in race conditions.
			if ( false === $size || $size < 32 ) {
				$this->lpnw_log( 'Downloaded file is empty or too small.', $code, (int) $size );
				return array();
			}

			$results = $this->lpnw_parse_csv_file( $tmp_file, $code, $size );

			$last_mod = wp_remote_retrieve_header( $response, 'last-modified' );
			$last_mod = is_string( $last_mod ) ? trim( $last_mod ) : '';
			if ( '' !== $last_mod ) {
				update_option( self::OPTION_CSV_LAST_MODIFIED, $last_mod, false );
			}
		} catch ( \Throwable $e ) {
			$this->lpnw_log( 'Unexpected error during download or parse: ' . $e->getMessage(), 0, 0 );
			$results = array();
		} finally {
			$this->lpnw_delete_temp_file( $tmp_file );
		}

		return $results;
	}

	/**
	 * If Last-Modified matches the last successful run, skip streaming the CSV again.
	 */
	private function lpnw_lr_should_skip_download(): bool {
		$stored = get_option( self::OPTION_CSV_LAST_MODIFIED, '' );
		$stored = is_string( $stored ) ? trim( $stored ) : '';

		$head = wp_remote_head(
			self::CSV_URL,
			array(
				'timeout'     => self::HEAD_TIMEOUT,
				'sslverify'   => true,
				'redirection' => 5,
				'headers'     => array(
					'User-Agent' => 'LPNW-PropertyAlerts/1.0 (+https://land-property-northwest.co.uk)',
				),
			)
		);

		if ( is_wp_error( $head ) ) {
			$this->lpnw_log(
				'HEAD probe failed (will attempt full GET): ' . $head->get_error_message(),
				0,
				0
			);
			return false;
		}

		$hcode = (int) wp_remote_retrieve_response_code( $head );
		$hbody = wp_remote_retrieve_body( $head );
		$hlen  = is_string( $hbody ) ? strlen( $hbody ) : 0;

		if ( 404 === $hcode ) {
			$this->lpnw_log(
				sprintf(
					'HEAD returned 404 for %s; URL may be invalid or relocated.',
					self::CSV_URL
				),
				$hcode,
				$hlen
			);
			return false;
		}

		if ( 200 !== $hcode ) {
			$this->lpnw_log(
				sprintf( 'HEAD returned HTTP %d; proceeding with GET.', $hcode ),
				$hcode,
				$hlen
			);
			return false;
		}

		$lm = wp_remote_retrieve_header( $head, 'last-modified' );
		$lm = is_string( $lm ) ? trim( $lm ) : '';

		if ( '' !== $stored && '' !== $lm && $stored === $lm ) {
			$this->lpnw_log(
				sprintf(
					'Skipping CSV download: Last-Modified unchanged (%s). Monthly file already processed.',
					$lm
				),
				$hcode,
				$hlen
			);
			return true;
		}

		return false;
	}

	/**
	 * @param string $filepath Absolute path to CSV.
	 * @param int    $http_code HTTP status from download response.
	 * @param int    $file_size Downloaded file size in bytes.
	 * @return array<int, array<string, mixed>>
	 */
	private function lpnw_parse_csv_file( string $filepath, int $http_code, int $file_size ): array {
		$results = array();

		$handle = fopen( $filepath, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming large gov.uk CSV.
		if ( false === $handle ) {
			$this->lpnw_log( 'Could not open CSV for reading.', $http_code, $file_size );
			return $results;
		}

		$line         = 0;
		$matched      = 0;
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
					$this->lpnw_log( 'Warning: fclose failed for CSV handle.', $http_code, $file_size );
				}
			}
		}

		if ( 0 === $line ) {
			$this->lpnw_log( 'CSV contained no rows.', $http_code, $file_size );
		} elseif ( 0 === $matched ) {
			$this->lpnw_log(
				sprintf( 'CSV had %d rows but none matched NW postcode prefixes.', $line ),
				$http_code,
				$file_size
			);
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
				$this->lpnw_log( 'Could not remove temp CSV file; delete manually if disk space is a concern.', 0, 0 );
			}
		}
	}

	/**
	 * Log feed issues (no PII).
	 *
	 * @param string $message      Log message.
	 * @param int    $http_code    HTTP status or 0.
	 * @param int    $response_len Body or file length for context.
	 */
	private function lpnw_log( string $message, int $http_code, int $response_len ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational feed diagnostics.
		error_log(
			sprintf(
				'[LPNW feed=%s] ts=%s http=%d len=%d %s',
				$this->get_source_name(),
				gmdate( 'c' ),
				$http_code,
				$response_len,
				$message
			)
		);
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
