<?php
/**
 * EPC Open Data feed.
 *
 * Pulls Energy Performance Certificate data from the
 * Open Data Communities API (opendatacommunities.org).
 * New EPCs indicate property sales, lettings, or renovations.
 *
 * API reference: https://epc.opendatacommunities.org/docs/api/domestic
 * Authentication: HTTP Basic with Base64( email:api_key ). The API requires
 * both the registered account email and API key; store them in
 * lpnw_settings['epc_api_email'] and lpnw_settings['epc_api_key'], or a single
 * "email@example.com:apikey" value in lpnw_settings['epc_api_key'] (legacy).
 *
 * NW filtering: fetch uses LPNW_NW_POSTCODES prefixes; parse() returns an
 * empty array for any row outside NW England.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Feed_EPC extends LPNW_Feed_Base {

	private const API_BASE = 'https://epc.opendatacommunities.org/api/v1/domestic/search';

	/** Maximum page size allowed by the domestic search API. */
	private const PAGE_SIZE = 5000;

	/** Safety cap on search-after pages per postcode prefix. */
	private const MAX_PAGES_PER_PREFIX = 50;

	public function get_source_name(): string {
		return 'epc';
	}

	protected function fetch(): array {
		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$creds = $this->lpnw_get_epc_credentials( $settings );

		if ( '' === $creds['email'] || '' === $creds['api_key'] ) {
			$missing = array();
			if ( '' === $creds['email'] ) {
				$missing[] = 'epc_api_email';
			}
			if ( '' === $creds['api_key'] ) {
				$missing[] = 'epc_api_key';
			}
			$this->lpnw_log(
				sprintf(
					'No EPC API credentials configured (missing %s). Open Data Communities requires HTTP Basic auth (email + API key). Skipping fetch.',
					implode( ' and ', $missing )
				),
				0,
				0
			);
			return array();
		}

		$auth_header = $this->lpnw_build_authorization_header( $creds['email'], $creds['api_key'] );
		$date_args   = $this->lpnw_lodgement_date_query_args();

		$all_results = array();

		foreach ( LPNW_NW_POSTCODES as $prefix ) {
			$prefix = sanitize_text_field( (string) $prefix );
			if ( '' === $prefix ) {
				continue;
			}

			$results = $this->lpnw_fetch_by_postcode( $prefix, $date_args, $auth_header );
			if ( ! empty( $results ) ) {
				$all_results = array_merge( $all_results, $results );
			}
			// Light throttle between prefix requests.
			usleep( 500000 );
		}

		return $all_results;
	}

	/**
	 * Resolve EPC API email and key from settings.
	 *
	 * Supports legacy single-field format "email@example.com:apikey" stored in epc_api_key.
	 *
	 * @param array<string, mixed> $settings Plugin settings option.
	 * @return array{email: string, api_key: string}
	 */
	private function lpnw_get_epc_credentials( array $settings ): array {
		$email   = isset( $settings['epc_api_email'] ) ? sanitize_email( (string) $settings['epc_api_email'] ) : '';
		$api_key = isset( $settings['epc_api_key'] ) ? sanitize_text_field( (string) $settings['epc_api_key'] ) : '';

		if ( ( '' === $email || '' === $api_key ) && str_contains( $api_key, '@' ) && str_contains( $api_key, ':' ) ) {
			$colon = strpos( $api_key, ':' );
			if ( false !== $colon ) {
				$maybe_email = substr( $api_key, 0, $colon );
				$maybe_key   = substr( $api_key, $colon + 1 );
				if ( is_email( $maybe_email ) && '' !== $maybe_key ) {
					return array(
						'email'   => $maybe_email,
						'api_key' => $maybe_key,
					);
				}
			}
		}

		return array(
			'email'   => $email,
			'api_key' => $api_key,
		);
	}

	/**
	 * Lodgement date filter: rolling window (API uses from-year, from-month, to-year, to-month; months are 1–12).
	 *
	 * @return array<string, int>
	 */
	private function lpnw_lodgement_date_query_args(): array {
		$now  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$from = $now->modify( '-2 months' );

		return array(
			'from-year'  => (int) $from->format( 'Y' ),
			'from-month' => (int) $from->format( 'n' ),
			'to-year'    => (int) $now->format( 'Y' ),
			'to-month'   => (int) $now->format( 'n' ),
		);
	}

	/**
	 * @param string $email   Registered account email.
	 * @param string $api_key API key from the account footer.
	 * @return string Value for the Authorization header (includes "Basic " prefix).
	 */
	private function lpnw_build_authorization_header( string $email, string $api_key ): string {
		$credentials = $email . ':' . $api_key;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for EPC API Basic auth per official docs.
		return 'Basic ' . base64_encode( $credentials );
	}

	/**
	 * Fetch all pages for one postcode prefix using search-after pagination.
	 *
	 * @param string             $prefix      Postcode prefix (e.g. M, PR).
	 * @param array<string, int> $date_args   from-year, from-month, to-year, to-month.
	 * @param string             $auth_header Full Authorization header value.
	 * @return array<int, array<string, mixed>>
	 */
	private function lpnw_fetch_by_postcode( string $prefix, array $date_args, string $auth_header ): array {
		$all_rows     = array();
		$search_after = '';
		$truncated    = false;

		for ( $page = 1; $page <= self::MAX_PAGES_PER_PREFIX; ++$page ) {
			$query_args = array_merge(
				array(
					'postcode' => $prefix,
					'size'     => self::PAGE_SIZE,
				),
				$date_args
			);

			if ( '' !== $search_after ) {
				$query_args['search-after'] = $search_after;
			}

			$url = add_query_arg( $query_args, self::API_BASE );

			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 90,
					'redirection' => 3,
					'headers'     => array(
						'Authorization' => $auth_header,
						'Accept'        => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->lpnw_log(
					sprintf(
						'request failed for prefix %s (page %d): %s',
						$prefix,
						$page,
						$response->get_error_message()
					),
					0,
					0
				);
				break;
			}

			$code     = (int) wp_remote_retrieve_response_code( $response );
			$body_raw = wp_remote_retrieve_body( $response );
			$body_raw = is_string( $body_raw ) ? $body_raw : '';
			$body_len = strlen( $body_raw );

			if ( 401 === $code || 403 === $code ) {
				$this->lpnw_log(
					sprintf(
						'authentication or authorisation failed for prefix %s. Verify epc_api_email and epc_api_key match your Open Data Communities account.',
						$prefix
					),
					$code,
					$body_len
				);
				break;
			}

			if ( 429 === $code ) {
				$this->lpnw_log(
					sprintf(
						'rate limited for prefix %s page %d. Stopping this prefix; retry later.',
						$prefix,
						$page
					),
					$code,
					$body_len
				);
				break;
			}

			if ( 200 !== $code ) {
				$snippet = wp_strip_all_tags( substr( $body_raw, 0, 300 ) );
				$this->lpnw_log(
					sprintf(
						'unexpected HTTP %d for prefix %s page %d. Body snippet: %s',
						$code,
						$prefix,
						$page,
						$snippet
					),
					$code,
					$body_len
				);
				break;
			}

			if ( '' === $body_raw ) {
				$this->lpnw_log( sprintf( 'empty response body for prefix %s page %d.', $prefix, $page ), $code, 0 );
				break;
			}

			$body = json_decode( $body_raw, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$this->lpnw_log(
					sprintf(
						'JSON decode error for prefix %s page %d: %s',
						$prefix,
						$page,
						json_last_error_msg()
					),
					$code,
					$body_len
				);
				break;
			}

			if ( ! is_array( $body ) ) {
				$this->lpnw_log( sprintf( 'decoded body is not an array for prefix %s page %d.', $prefix, $page ), $code, $body_len );
				break;
			}

			$rows = $this->lpnw_normalize_epc_rows( $body );
			if ( array() === $rows ) {
				if ( ! array_key_exists( 'rows', $body ) || ! is_array( $body['rows'] ) ) {
					$this->lpnw_log( sprintf( 'response missing or invalid rows key for prefix %s page %d.', $prefix, $page ), $code, $body_len );
				}
				break;
			}

			$all_rows = array_merge( $all_rows, $rows );

			$next = wp_remote_retrieve_header( $response, 'x-next-search-after' );
			$next = is_string( $next ) ? trim( $next ) : '';

			if ( '' === $next ) {
				break;
			}

			if ( $page === self::MAX_PAGES_PER_PREFIX ) {
				$truncated = true;
				break;
			}

			$search_after = $next;
		}

		if ( $truncated ) {
			$this->lpnw_log(
				sprintf(
					'prefix %s hit max page cap (%d); some rows may be missing.',
					$prefix,
					self::MAX_PAGES_PER_PREFIX
				),
				0,
				0
			);
		}

		return $all_rows;
	}

	/**
	 * Log feed issues (no credentials or PII).
	 *
	 * @param string $message      Log message.
	 * @param int    $http_code    Last HTTP status or 0 if N/A.
	 * @param int    $response_len Response body length or 0.
	 */
	private function lpnw_log( string $message, int $http_code = 0, int $response_len = 0 ): void {
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
	 * Normalise JSON rows to associative arrays (API may return rows as objects or as parallel arrays with column-names).
	 *
	 * @param array<string, mixed> $body Decoded JSON body.
	 * @return array<int, array<string, mixed>>
	 */
	private function lpnw_normalize_epc_rows( array $body ): array {
		$rows = $body['rows'] ?? null;
		if ( ! is_array( $rows ) || array() === $rows ) {
			return array();
		}

		$first = $rows[0];
		if ( ! is_array( $first ) ) {
			return array();
		}

		if ( ! $this->lpnw_is_zero_indexed_list( $first ) ) {
			/** @var array<int, array<string, mixed>> $rows */
			return $rows;
		}

		$columns = $body['column-names'] ?? $body['column_names'] ?? null;
		if ( ! is_array( $columns ) || array() === $columns ) {
			$this->lpnw_log( 'JSON rows are indexed arrays but column-names are missing; cannot map fields.', 0, 0 );
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || count( $row ) !== count( $columns ) ) {
				continue;
			}
			$assoc = array_combine( $columns, $row );
			if ( false !== $assoc ) {
				$out[] = $assoc;
			}
		}

		return $out;
	}

	/**
	 * @param array<int|string, mixed> $arr Array to inspect.
	 */
	private function lpnw_is_zero_indexed_list( array $arr ): bool {
		$expected = 0;
		foreach ( $arr as $key => $_unused ) {
			if ( $key !== $expected ) {
				return false;
			}
			++$expected;
		}
		return true;
	}

	/**
	 * Read a field from an EPC row (hyphenated or underscored keys).
	 *
	 * @param array<string, mixed> $row       Normalised row.
	 * @param string               $hyphen_key Primary API key form (e.g. lmk-key).
	 * @return mixed
	 */
	private function lpnw_epc_field( array $row, string $hyphen_key ) {
		if ( array_key_exists( $hyphen_key, $row ) ) {
			return $row[ $hyphen_key ];
		}

		$underscore = str_replace( '-', '_', $hyphen_key );
		return $row[ $underscore ] ?? null;
	}

	/**
	 * @param array<string, mixed> $raw_item Raw EPC record.
	 * @return array<string, mixed>
	 */
	protected function parse( array $raw_item ): array {
		$postcode = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'postcode' ) ?? '' ) );

		if ( ! $this->is_nw_postcode( $postcode ) ) {
			return array();
		}

		$lmk_key = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'lmk-key' ) ?? '' ) );
		if ( '' === $lmk_key ) {
			return array();
		}

		$hash       = $this->lpnw_epc_field( $raw_item, 'certificate-hash' );
		$source_url = '';
		$hash_clean = is_string( $hash ) ? trim( $hash ) : '';
		if ( '' !== $hash_clean ) {
			$source_url = 'https://find-energy-certificate.service.gov.uk/energy-certificate/' . rawurlencode( $hash_clean );
		}

		$rating     = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'current-energy-rating' ) ?? '' ) );
		$efficiency = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'current-energy-efficiency' ) ?? '' ) );
		$floor_area = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'total-floor-area' ) ?? '' ) );
		$txn        = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'transaction-type' ) ?? '' ) );

		$address = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'address' ) ?? '' ) );
		$ptype   = sanitize_text_field( (string) ( $this->lpnw_epc_field( $raw_item, 'property-type' ) ?? '' ) );

		return array(
			'source'        => $this->get_source_name(),
			'source_ref'    => $lmk_key,
			'address'       => $address,
			'postcode'      => $postcode,
			'price'         => null,
			'property_type' => $ptype,
			'description'   => sprintf(
				/* translators: 1: EPC band, 2: efficiency score, 3: floor area sqm, 4: transaction type */
				__( 'EPC Rating: %1$s (%2$s). Floor area: %3$s sqm. %4$s', 'lpnw-alerts' ),
				'' !== $rating ? $rating : 'N/A',
				$efficiency,
				'' !== $floor_area ? $floor_area : 'N/A',
				$txn
			),
			'source_url'    => esc_url_raw( $source_url ),
			'raw_data'      => $raw_item,
		);
	}
}
