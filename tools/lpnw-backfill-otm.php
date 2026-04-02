<?php
/**
 * OnTheMarket backfill: postcodes (reverse geocode), structured fields and application_type from raw_data.
 *
 * Upload to wp-content/mu-plugins/ (or load from a bootstrap that defines ABSPATH), then open:
 * ?lpnw_backfill_otm=run&key=lpnw2026setup
 *
 * Processes up to 200 rows per run. Does not delete itself.
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Infer sale vs rent from OnTheMarket raw JSON.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string 'sale' or 'rent'.
 */
function lpnw_backfill_otm_infer_application_type( array $raw ): string {
	$section = isset( $raw['_otm_section'] ) ? trim( (string) $raw['_otm_section'] ) : '';
	if ( 'to-rent' === $section ) {
		return 'rent';
	}
	if ( 'for-sale' === $section ) {
		return 'sale';
	}

	$rent_freq = isset( $raw['rent-frequency'] ) ? trim( (string) $raw['rent-frequency'] ) : '';
	if ( '' !== $rent_freq ) {
		return 'rent';
	}

	$price_freq = isset( $raw['price-frequency'] ) ? strtolower( trim( (string) $raw['price-frequency'] ) ) : '';
	if ( '' !== $price_freq && preg_match( '/pcm|per\s*month|monthly|per\s*week|weekly|\bpw\b|p\.w\.|\/week|\/month/', $price_freq ) ) {
		return 'rent';
	}

	$details = isset( $raw['details-url'] ) ? (string) $raw['details-url'] : '';
	if ( str_contains( $details, 'to-rent' ) ) {
		return 'rent';
	}
	if ( str_contains( $details, 'for-sale' ) ) {
		return 'sale';
	}

	foreach ( array( 'short-price', 'price' ) as $k ) {
		if ( ! isset( $raw[ $k ] ) ) {
			continue;
		}
		$blob = strtolower( (string) $raw[ $k ] );
		if ( preg_match( '/\bpcm\b|\bpw\b|per\s*month|per\s*week|\/\s*month|\/\s*week/', $blob ) ) {
			return 'rent';
		}
	}

	return 'sale';
}

/**
 * Extract tenure string from OTM-style raw keys.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string|null
 */
function lpnw_backfill_otm_extract_tenure_type( array $raw ): ?string {
	if ( isset( $raw['tenure'] ) && is_array( $raw['tenure'] ) && isset( $raw['tenure']['tenureType'] ) && is_scalar( $raw['tenure']['tenureType'] ) ) {
		$t = strtolower( trim( (string) $raw['tenure']['tenureType'] ) );
		if ( '' !== $t ) {
			return sanitize_text_field( mb_substr( $t, 0, 32 ) );
		}
	}
	foreach ( array( 'tenure', 'tenure-type', 'tenureType' ) as $k ) {
		if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
			$t = strtolower( trim( (string) $raw[ $k ] ) );
			if ( '' !== $t ) {
				return sanitize_text_field( mb_substr( $t, 0, 32 ) );
			}
		}
	}
	return null;
}

/**
 * Extract agent display name.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string|null
 */
function lpnw_backfill_otm_extract_agent_name( array $raw ): ?string {
	foreach ( array( 'branch-name', 'agent-name', 'agentName' ) as $k ) {
		if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
			$a = trim( (string) $raw[ $k ] );
			if ( '' !== $a ) {
				$name = sanitize_text_field( mb_substr( $a, 0, 255 ) );
				return '' !== $name ? $name : null;
			}
		}
	}
	return null;
}

/**
 * Key features from OTM features array.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string|null Pipe-separated.
 */
function lpnw_backfill_otm_extract_key_features( array $raw ): ?string {
	if ( empty( $raw['features'] ) || ! is_array( $raw['features'] ) ) {
		return null;
	}
	$parts = array();
	foreach ( $raw['features'] as $feat ) {
		if ( is_array( $feat ) ) {
			$feat = $feat['text'] ?? $feat['value'] ?? $feat['label'] ?? '';
		}
		if ( ! is_scalar( $feat ) ) {
			continue;
		}
		$t = sanitize_text_field( trim( (string) $feat ) );
		if ( '' !== $t ) {
			$parts[] = $t;
		}
	}
	if ( empty( $parts ) ) {
		return null;
	}
	$out = implode( '|', $parts );
	return mb_substr( $out, 0, 65530 );
}

/**
 * Price frequency token.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string|null
 */
function lpnw_backfill_otm_extract_price_frequency( array $raw ): ?string {
	foreach ( array( 'price-frequency', 'rent-frequency', 'rental-frequency' ) as $k ) {
		if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
			$f = strtolower( trim( (string) $raw[ $k ] ) );
			if ( '' !== $f ) {
				return sanitize_text_field( mb_substr( $f, 0, 20 ) );
			}
		}
	}
	return null;
}

/**
 * SQL fragment: OnTheMarket rows that may still need backfill.
 *
 * @return string WHERE body (without WHERE keyword).
 */
function lpnw_backfill_otm_candidate_where_sql(): string {
	return "source = 'onthemarket'
AND (
	((postcode IS NULL OR TRIM(postcode) = '')
		AND latitude IS NOT NULL AND longitude IS NOT NULL
		AND latitude != 0 AND longitude != 0)
	OR (raw_data IS NOT NULL AND TRIM(raw_data) != '' AND (
		bedrooms IS NULL OR bathrooms IS NULL
		OR NULLIF(TRIM(COALESCE(tenure_type, '')), '') IS NULL
		OR NULLIF(TRIM(COALESCE(agent_name, '')), '') IS NULL
		OR NULLIF(TRIM(COALESCE(key_features_text, '')), '') IS NULL
		OR NULLIF(TRIM(COALESCE(price_frequency, '')), '') IS NULL
		OR NULLIF(TRIM(COALESCE(application_type, '')), '') IS NULL
	))
	OR (raw_data IS NOT NULL AND TRIM(raw_data) != ''
		AND application_type = 'sale'
		AND (
			raw_data LIKE '%\"_otm_section\":\"to-rent\"%'
			OR raw_data LIKE '%\"rent-frequency\"%'
			OR (
				raw_data LIKE '%\"price-frequency\"%'
				AND (
					raw_data LIKE '%pcm%' OR raw_data LIKE '%pw%' OR raw_data LIKE '%per week%' OR raw_data LIKE '%per month%'
				)
			)
		)
	)
)";
}

add_action(
	'init',
	static function () {
		if ( empty( $_GET['lpnw_backfill_otm'] ) || 'run' !== $_GET['lpnw_backfill_otm'] ) {
			return;
		}
		if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) {
			return;
		}

		set_time_limit( 300 );
		header( 'Content-Type: text/plain; charset=utf-8' );

		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_properties';
		$where = lpnw_backfill_otm_candidate_where_sql();

		if ( ! class_exists( 'LPNW_Geocoder', false ) ) {
			$geocoder_path = WP_PLUGIN_DIR . '/lpnw-property-alerts/includes/class-lpnw-geocoder.php';
			if ( is_readable( $geocoder_path ) ) {
				require_once $geocoder_path;
			}
		}
		$geocoder_ok = class_exists( 'LPNW_Geocoder', false );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix; WHERE is static.
		$rows = $wpdb->get_results(
			"SELECT id, postcode, latitude, longitude, bedrooms, bathrooms, tenure_type, agent_name, key_features_text, price_frequency, application_type, raw_data
			FROM {$table}
			WHERE {$where}
			ORDER BY id ASC
			LIMIT 200"
		);

		$updated_rows     = 0;
		$json_errors      = 0;
		$geocode_fail     = 0;
		$fields_increment = array(
			'postcode'          => 0,
			'bedrooms'          => 0,
			'bathrooms'         => 0,
			'tenure_type'       => 0,
			'agent_name'        => 0,
			'key_features_text' => 0,
			'price_frequency'   => 0,
			'application_type'  => 0,
		);

		foreach ( $rows as $row ) {
			$update  = array();
			$formats = array();

			$postcode_empty = ( null === $row->postcode || '' === trim( (string) $row->postcode ) );
			$lat            = isset( $row->latitude ) ? (float) $row->latitude : 0.0;
			$lng            = isset( $row->longitude ) ? (float) $row->longitude : 0.0;

			if ( $postcode_empty && $geocoder_ok && 0.0 !== $lat && 0.0 !== $lng && is_finite( $lat ) && is_finite( $lng ) ) {
				$resolved = LPNW_Geocoder::reverse_geocode( $lat, $lng );
				if ( null !== $resolved && '' !== $resolved ) {
					$update['postcode'] = $resolved;
					$formats[]          = '%s';
					++$fields_increment['postcode'];
				} else {
					++$geocode_fail;
				}
				usleep( 50000 );
			}

			$raw         = null;
			$raw_trimmed = is_string( $row->raw_data ) ? trim( $row->raw_data ) : '';
			if ( '' !== $raw_trimmed ) {
				$raw = json_decode( $row->raw_data, true );
				if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $raw ) ) {
					++$json_errors;
					$raw = null;
				}
			}

			if ( is_array( $raw ) ) {
				$inferred_type = lpnw_backfill_otm_infer_application_type( $raw );
				$current_app   = strtolower( trim( (string) ( $row->application_type ?? '' ) ) );
				if ( $inferred_type !== $current_app ) {
					$update['application_type'] = $inferred_type;
					$formats[]                  = '%s';
					++$fields_increment['application_type'];
				}

				if ( null === $row->bedrooms && array_key_exists( 'bedrooms', $raw ) && ( is_numeric( $raw['bedrooms'] ) || ( is_string( $raw['bedrooms'] ) && '' !== trim( $raw['bedrooms'] ) ) ) ) {
					$b = absint( $raw['bedrooms'] );
					if ( $b > 255 ) {
						$b = 255;
					}
					$update['bedrooms'] = $b;
					$formats[]          = '%d';
					++$fields_increment['bedrooms'];
				}

				if ( null === $row->bathrooms && array_key_exists( 'bathrooms', $raw ) && ( is_numeric( $raw['bathrooms'] ) || ( is_string( $raw['bathrooms'] ) && '' !== trim( $raw['bathrooms'] ) ) ) ) {
					$bth = absint( $raw['bathrooms'] );
					if ( $bth > 255 ) {
						$bth = 255;
					}
					$update['bathrooms'] = $bth;
					$formats[]           = '%d';
					++$fields_increment['bathrooms'];
				}

				$tenure_current = null !== $row->tenure_type ? trim( (string) $row->tenure_type ) : '';
				if ( '' === $tenure_current ) {
					$t = lpnw_backfill_otm_extract_tenure_type( $raw );
					if ( null !== $t && '' !== $t ) {
						$update['tenure_type'] = $t;
						$formats[]             = '%s';
						++$fields_increment['tenure_type'];
					}
				}

				$agent_current = null !== $row->agent_name ? trim( (string) $row->agent_name ) : '';
				if ( '' === $agent_current ) {
					$a = lpnw_backfill_otm_extract_agent_name( $raw );
					if ( null !== $a && '' !== $a ) {
						$update['agent_name'] = $a;
						$formats[]            = '%s';
						++$fields_increment['agent_name'];
					}
				}

				$kf_current = null !== $row->key_features_text ? trim( (string) $row->key_features_text ) : '';
				if ( '' === $kf_current ) {
					$kf = lpnw_backfill_otm_extract_key_features( $raw );
					if ( null !== $kf && '' !== $kf ) {
						$update['key_features_text'] = $kf;
						$formats[]                   = '%s';
						++$fields_increment['key_features_text'];
					}
				}

				$pf_current = null !== $row->price_frequency ? trim( (string) $row->price_frequency ) : '';
				if ( '' === $pf_current ) {
					$pf = lpnw_backfill_otm_extract_price_frequency( $raw );
					if ( null !== $pf && '' !== $pf ) {
						$update['price_frequency'] = $pf;
						$formats[]                 = '%s';
						++$fields_increment['price_frequency'];
					}
				}
			}

			if ( ! empty( $update ) ) {
				$ok = $wpdb->update( $table, $update, array( 'id' => (int) $row->id ), $formats, array( '%d' ) );
				if ( false !== $ok && (int) $ok > 0 ) {
					++$updated_rows;
				}
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );

		echo "LPNW OnTheMarket backfill\n";
		echo "Processed this run: " . count( $rows ) . "\n";
		echo "Rows updated: {$updated_rows}\n";
		echo "Fields filled (incremental): postcode={$fields_increment['postcode']}, bedrooms={$fields_increment['bedrooms']}, bathrooms={$fields_increment['bathrooms']}, tenure_type={$fields_increment['tenure_type']}, agent_name={$fields_increment['agent_name']}, key_features_text={$fields_increment['key_features_text']}, price_frequency={$fields_increment['price_frequency']}, application_type={$fields_increment['application_type']}\n";
		if ( ! $geocoder_ok ) {
			echo "Warning: LPNW_Geocoder not loaded; postcode reverse geocode skipped.\n";
		}
		if ( $geocode_fail > 0 ) {
			echo "Reverse geocode no result: {$geocode_fail}\n";
		}
		if ( $json_errors > 0 ) {
			echo "Invalid or non-object JSON (raw_data): {$json_errors}\n";
		}
		echo "Remaining (candidates matching backfill criteria): {$remaining}\n";
		echo "\nRun again until remaining is 0 if needed.\n";
		exit;
	},
	1
);
