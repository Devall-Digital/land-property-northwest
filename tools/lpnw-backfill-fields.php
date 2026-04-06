<?php
/**
 * MU-plugin: backfill structured columns on lpnw_properties from raw_data JSON.
 *
 * Copy to wp-content/mu-plugins/ then open (once or repeatedly):
 * ?lpnw_backfill_fields=run&key=YOUR_LPNW_SECRET (see docs/DEPLOYMENT.md)
 *
 * Processes up to 500 rows per request in batches of 200. Does not delete itself.
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

/**
 * Square feet from a portal display-size style string.
 *
 * @param mixed $display_size Raw string.
 * @return int|null
 */
function lpnw_backfill_fields_floor_area_from_string( $display_size ) {
	if ( ! is_scalar( $display_size ) ) {
		return null;
	}
	$s  = trim( (string) $display_size );
	$sl = strtolower( $s );
	if ( '' === $s ) {
		return null;
	}
	if ( preg_match( '/([\d][\d,\.]*)\s*sq\.?\s*m(?:\b|²|2|\s)/iu', $s, $m )
		|| preg_match( '/([\d][\d,\.]*)\s*sqm\b/iu', $s, $m ) ) {
		$n = (float) str_replace( ',', '', $m[1] );
		if ( $n < 0.01 ) {
			return null;
		}
		return (int) round( $n * 10.764 );
	}
	if ( preg_match( '/([\d][\d,\.]*)\s*sq\.?\s*ft\b/iu', $s, $m ) ) {
		$n = (float) str_replace( ',', '', $m[1] );
		return $n > 0 ? (int) round( $n ) : null;
	}
	if ( preg_match( '/([\d][\d,\.]*)/', $s, $m ) ) {
		$n = (float) str_replace( ',', '', $m[1] );
		if ( $n < 1 ) {
			return null;
		}
		$sqm_only = ( str_contains( $sl, 'sq m' ) || str_contains( $sl, 'sqm' ) )
			&& ! str_contains( $sl, 'sq ft' )
			&& ! str_contains( $sl, 'sqft' );
		if ( $sqm_only ) {
			return (int) round( $n * 10.764 );
		}
		return (int) round( $n );
	}
	return null;
}

/**
 * Floor area in sq ft from numeric fields and text blobs in raw JSON.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return int|null
 */
function lpnw_backfill_fields_floor_area_sqft( array $raw ) {
	foreach ( array( 'floor-area-sq-ft', 'floorAreaSqFt', 'size-square-feet', 'square-feet' ) as $k ) {
		if ( isset( $raw[ $k ] ) && is_numeric( $raw[ $k ] ) ) {
			$n = absint( $raw[ $k ] );
			return $n > 0 ? $n : null;
		}
	}
	$attrs = isset( $raw['attributes'] ) && is_array( $raw['attributes'] ) ? $raw['attributes'] : array();
	foreach ( array( 'floorAreaSquareFeet', 'floorAreaSqFeet', 'squareFeet', 'floorAreaInSqft' ) as $k ) {
		if ( isset( $attrs[ $k ] ) && is_numeric( $attrs[ $k ] ) ) {
			$n = absint( $attrs[ $k ] );
			return $n > 0 ? $n : null;
		}
	}
	if ( isset( $attrs['floorArea'] ) ) {
		$fa = $attrs['floorArea'];
		if ( is_numeric( $fa ) ) {
			$n = absint( $fa );
			return $n > 0 ? $n : null;
		}
		if ( is_string( $fa ) && preg_match( '/([\d][\d,\.]*)\s*sq\.?\s*ft\b/i', $fa, $m ) ) {
			$n = absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
			return $n > 0 ? $n : null;
		}
		if ( is_array( $fa ) ) {
			$val  = $fa['value'] ?? $fa['amount'] ?? null;
			$unit = strtolower( (string) ( $fa['unit'] ?? '' ) );
			if ( is_numeric( $val ) ) {
				$n = absint( $val );
				if ( $n < 1 ) {
					return null;
				}
				if ( '' !== $unit && ( str_contains( $unit, 'metre' ) || str_contains( $unit, 'sqm' ) || str_contains( $unit, 'm²' ) || str_contains( $unit, 'm2' ) ) ) {
					return (int) round( $n * 10.764 );
				}
				return $n;
			}
		}
	}
	foreach ( array( 'displaySize', 'display_size', 'display-size', 'size-summary', 'sizeSummary', 'property-size' ) as $k ) {
		if ( isset( $raw[ $k ] ) ) {
			$parsed = lpnw_backfill_fields_floor_area_from_string( $raw[ $k ] );
			if ( null !== $parsed ) {
				return $parsed;
			}
		}
	}
	return null;
}

/**
 * Normalise Zoopla-style pricing label to a frequency token.
 *
 * @param string $label Raw label.
 * @return string|null
 */
function lpnw_backfill_fields_zoopla_frequency_token( string $label ) {
	$l = strtolower( trim( $label ) );
	if ( '' === $l ) {
		return null;
	}
	if ( str_contains( $l, 'pcm' ) || str_contains( $l, 'per month' ) || str_contains( $l, '/month' ) || 'monthly' === $l ) {
		return 'monthly';
	}
	if ( str_contains( $l, 'pw' ) || str_contains( $l, 'per week' ) || str_contains( $l, '/week' ) || 'weekly' === $l ) {
		return 'weekly';
	}
	if ( strlen( $l ) <= 20 && preg_match( '/^[a-z0-9\s\-\/]+$/', $l ) ) {
		return sanitize_text_field( $l );
	}
	return null;
}

/**
 * First listed date as Y-m-d.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string|null
 */
function lpnw_backfill_fields_first_listed_ymd( array $raw ) {
	$candidates = array();
	if ( isset( $raw['firstVisibleDate'] ) && is_scalar( $raw['firstVisibleDate'] ) ) {
		$candidates[] = (string) $raw['firstVisibleDate'];
	}
	if ( isset( $raw['listingUpdate']['listingUpdateDate'] ) && is_scalar( $raw['listingUpdate']['listingUpdateDate'] ) ) {
		$candidates[] = (string) $raw['listingUpdate']['listingUpdateDate'];
	}
	if ( isset( $raw['listingDates'] ) && is_array( $raw['listingDates'] ) ) {
		foreach ( array( 'firstVisibleDate', 'publishedDate' ) as $k ) {
			if ( isset( $raw['listingDates'][ $k ] ) && is_scalar( $raw['listingDates'][ $k ] ) ) {
				$candidates[] = (string) $raw['listingDates'][ $k ];
			}
		}
	}
	foreach ( array( 'publicationDate', 'publishedDate', 'createdDate', 'first-published-date', 'firstPublishedDate' ) as $k ) {
		if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
			$candidates[] = (string) $raw[ $k ];
		}
	}
	if ( isset( $raw['dates'] ) && is_array( $raw['dates'] ) ) {
		foreach ( array( 'published', 'firstPublished' ) as $k ) {
			if ( isset( $raw['dates'][ $k ] ) && is_scalar( $raw['dates'][ $k ] ) ) {
				$candidates[] = (string) $raw['dates'][ $k ];
			}
		}
	}
	foreach ( $candidates as $str ) {
		$str = trim( $str );
		if ( '' === $str ) {
			continue;
		}
		$ts = strtotime( $str );
		if ( false !== $ts ) {
			return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d', $ts ) : gmdate( 'Y-m-d', $ts );
		}
	}
	return null;
}

/**
 * Agent display name from common portal shapes.
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string|null
 */
function lpnw_backfill_fields_agent_name( array $raw ) {
	$customer = $raw['customer'] ?? null;
	if ( is_array( $customer ) ) {
		$brand = isset( $customer['brandTradingName'] ) ? trim( (string) $customer['brandTradingName'] ) : '';
		if ( '' !== $brand ) {
			return sanitize_text_field( $brand );
		}
		$branch = isset( $customer['branchDisplayName'] ) ? trim( (string) $customer['branchDisplayName'] ) : '';
		if ( '' !== $branch ) {
			return sanitize_text_field( $branch );
		}
	}
	foreach ( array( 'branch-name', 'agent-name', 'agentName' ) as $k ) {
		if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
			$a = trim( (string) $raw[ $k ] );
			if ( '' !== $a ) {
				return sanitize_text_field( $a );
			}
		}
	}
	$paths = array(
		array( 'branch', 'name' ),
		array( 'branch', 'branchName' ),
		array( 'branchName' ),
		array( 'formattedBranchName' ),
		array( 'listingCompany', 'name' ),
		array( 'agent', 'branchName' ),
		array( 'agent', 'name' ),
	);
	foreach ( $paths as $path ) {
		$v = $raw;
		foreach ( $path as $p ) {
			if ( ! is_array( $v ) || ! isset( $v[ $p ] ) ) {
				continue 2;
			}
			$v = $v[ $p ];
		}
		if ( is_scalar( $v ) ) {
			$name = trim( (string) $v );
			if ( '' !== $name ) {
				return sanitize_text_field( $name );
			}
		}
	}
	return null;
}

/**
 * Key features joined with "|" (all items).
 *
 * @param array<string, mixed> $raw Decoded raw_data.
 * @return string|null
 */
function lpnw_backfill_fields_key_features_text( array $raw ) {
	$lists = array();
	if ( ! empty( $raw['keyFeatures'] ) && is_array( $raw['keyFeatures'] ) ) {
		$lists[] = $raw['keyFeatures'];
	}
	if ( ! empty( $raw['features'] ) && is_array( $raw['features'] ) ) {
		$lists[] = $raw['features'];
	}
	$attrs = isset( $raw['attributes'] ) && is_array( $raw['attributes'] ) ? $raw['attributes'] : array();
	if ( ! empty( $attrs['features'] ) && is_array( $attrs['features'] ) ) {
		$lists[] = $attrs['features'];
	}
	foreach ( array( 'highlights', 'bulletPoints', 'featureSummary' ) as $k ) {
		if ( ! empty( $raw[ $k ] ) && is_array( $raw[ $k ] ) ) {
			$lists[] = $raw[ $k ];
		}
	}
	$out = array();
	foreach ( $lists as $list ) {
		foreach ( $list as $item ) {
			if ( is_array( $item ) ) {
				$item = $item['text'] ?? $item['value'] ?? $item['label'] ?? '';
			}
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$t = sanitize_text_field( trim( (string) $item ) );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
	}
	if ( empty( $out ) ) {
		return null;
	}
	return implode( '|', $out );
}

/**
 * Extract structured fields from decoded raw_data.
 *
 * @param array<string, mixed> $raw Decoded JSON.
 * @return array<string, mixed>
 */
function lpnw_backfill_fields_extract( array $raw ) {
	$bedrooms = null;
	if ( array_key_exists( 'bedrooms', $raw ) && ( is_numeric( $raw['bedrooms'] ) || ( is_string( $raw['bedrooms'] ) && '' !== trim( $raw['bedrooms'] ) ) ) ) {
		$bedrooms = absint( $raw['bedrooms'] );
	} elseif ( isset( $raw['attributes']['bedrooms'] ) ) {
		$bedrooms = absint( $raw['attributes']['bedrooms'] );
	} elseif ( isset( $raw['num_bedrooms'] ) ) {
		$bedrooms = absint( $raw['num_bedrooms'] );
	} elseif ( isset( $raw['beds'] ) && '' !== (string) $raw['beds'] ) {
		$n = absint( preg_replace( '/[^0-9]/', '', (string) $raw['beds'] ) );
		if ( $n > 0 ) {
			$bedrooms = $n;
		}
	}
	if ( null === $bedrooms ) {
		$bedrooms = 0;
	}
	if ( $bedrooms > 255 ) {
		$bedrooms = 255;
	}

	$bathrooms = null;
	if ( array_key_exists( 'bathrooms', $raw ) && ( is_numeric( $raw['bathrooms'] ) || ( is_string( $raw['bathrooms'] ) && '' !== trim( $raw['bathrooms'] ) ) ) ) {
		$bathrooms = absint( $raw['bathrooms'] );
	} elseif ( isset( $raw['attributes']['bathrooms'] ) ) {
		$bathrooms = absint( $raw['attributes']['bathrooms'] );
	} elseif ( isset( $raw['num_bathrooms'] ) ) {
		$bathrooms = absint( $raw['num_bathrooms'] );
	}
	if ( null === $bathrooms ) {
		$bathrooms = 0;
	}
	if ( $bathrooms > 255 ) {
		$bathrooms = 255;
	}

	$tenure_type = null;
	if ( isset( $raw['tenure'] ) && is_array( $raw['tenure'] ) && isset( $raw['tenure']['tenureType'] ) && is_scalar( $raw['tenure']['tenureType'] ) ) {
		$t = strtolower( trim( (string) $raw['tenure']['tenureType'] ) );
		if ( '' !== $t ) {
			$tenure_type = sanitize_text_field( $t );
		}
	}
	if ( null === $tenure_type || '' === $tenure_type ) {
		foreach ( array( 'tenure', 'tenure-type', 'tenureType' ) as $k ) {
			if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
				$t = strtolower( trim( (string) $raw[ $k ] ) );
				if ( '' !== $t ) {
					$tenure_type = sanitize_text_field( $t );
					break;
				}
			}
		}
	}
	if ( ( null === $tenure_type || '' === $tenure_type ) && isset( $raw['attributes']['tenure'] ) && is_scalar( $raw['attributes']['tenure'] ) ) {
		$t = strtolower( trim( (string) $raw['attributes']['tenure'] ) );
		if ( '' !== $t ) {
			$tenure_type = sanitize_text_field( $t );
		}
	}
	if ( is_string( $tenure_type ) && '' !== $tenure_type ) {
		$tenure_type = mb_substr( $tenure_type, 0, 32 );
	} else {
		$tenure_type = null;
	}

	$price_frequency = null;
	if ( isset( $raw['price'] ) && is_array( $raw['price'] ) && isset( $raw['price']['frequency'] ) && is_scalar( $raw['price']['frequency'] ) ) {
		$f = strtolower( trim( (string) $raw['price']['frequency'] ) );
		if ( '' !== $f ) {
			$price_frequency = sanitize_text_field( $f );
		}
	}
	if ( null === $price_frequency || '' === $price_frequency ) {
		if ( isset( $raw['pricing']['label'] ) && is_scalar( $raw['pricing']['label'] ) ) {
			$price_frequency = lpnw_backfill_fields_zoopla_frequency_token( (string) $raw['pricing']['label'] );
		}
	}
	if ( null === $price_frequency || '' === $price_frequency ) {
		foreach ( array( 'price-frequency', 'rent-frequency', 'rental-frequency' ) as $k ) {
			if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
				$f = strtolower( trim( (string) $raw[ $k ] ) );
				if ( '' !== $f ) {
					$price_frequency = sanitize_text_field( $f );
					break;
				}
			}
		}
	}
	if ( is_string( $price_frequency ) && '' !== $price_frequency ) {
		$price_frequency = mb_substr( $price_frequency, 0, 20 );
	} else {
		$price_frequency = null;
	}

	$floor_area_sqft = lpnw_backfill_fields_floor_area_sqft( $raw );

	$first_listed_date = lpnw_backfill_fields_first_listed_ymd( $raw );

	$agent_name = lpnw_backfill_fields_agent_name( $raw );
	if ( is_string( $agent_name ) && '' !== $agent_name ) {
		$agent_name = mb_substr( $agent_name, 0, 255 );
	} else {
		$agent_name = null;
	}

	$key_features_text = lpnw_backfill_fields_key_features_text( $raw );
	if ( is_string( $key_features_text ) && '' !== $key_features_text ) {
		$key_features_text = mb_substr( $key_features_text, 0, 65530 );
	} else {
		$key_features_text = null;
	}

	return array(
		'bedrooms'          => $bedrooms,
		'bathrooms'         => $bathrooms,
		'tenure_type'       => $tenure_type,
		'price_frequency'   => $price_frequency,
		'floor_area_sqft'   => $floor_area_sqft,
		'first_listed_date' => $first_listed_date,
		'agent_name'        => $agent_name,
		'key_features_text' => $key_features_text,
	);
}

/**
 * Apply UPDATE for one property row.
 *
 * @param wpdb                   $wpdb  WordPress DB object.
 * @param string                 $table Properties table name.
 * @param int                    $id    Row id.
 * @param array<string, mixed>   $f     Extracted fields.
 * @return int|false Rows affected.
 */
function lpnw_backfill_fields_update_row( $wpdb, string $table, int $id, array $f ) {
	$id    = absint( $id );
	$parts = array();

	$parts[] = $wpdb->prepare( '`bedrooms` = %d', absint( $f['bedrooms'] ) );
	$parts[] = $wpdb->prepare( '`bathrooms` = %d', absint( $f['bathrooms'] ) );

	if ( null === $f['floor_area_sqft'] ) {
		$parts[] = '`floor_area_sqft` = NULL';
	} else {
		$parts[] = $wpdb->prepare( '`floor_area_sqft` = %d', absint( $f['floor_area_sqft'] ) );
	}

	if ( null === $f['first_listed_date'] || '' === $f['first_listed_date'] ) {
		$parts[] = '`first_listed_date` = NULL';
	} else {
		$parts[] = $wpdb->prepare( '`first_listed_date` = %s', $f['first_listed_date'] );
	}

	foreach ( array( 'tenure_type', 'price_frequency', 'agent_name', 'key_features_text' ) as $col ) {
		$v = $f[ $col ];
		if ( null === $v || '' === $v ) {
			$parts[] = '`' . $col . '` = NULL';
		} else {
			$parts[] = $wpdb->prepare( '`' . $col . '` = %s', $v );
		}
	}

	$sql = "UPDATE {$table} SET " . implode( ', ', $parts ) . $wpdb->prepare( ' WHERE id = %d', $id );
	return $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

add_action(
	'init',
	static function () {
		if ( empty( $_GET['lpnw_backfill_fields'] ) || 'run' !== $_GET['lpnw_backfill_fields'] ) {
			return;
		}
		$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
		if ( ! lpnw_tool_query_key_ok( $key ) ) {
			return;
		}

		set_time_limit( 300 );
		header( 'Content-Type: text/plain; charset=utf-8' );

		global $wpdb;
		$table       = $wpdb->prefix . 'lpnw_properties';
		$max_per_run = 500;
		$batch_size  = 200;
		$updated     = 0;
		$json_errors = 0;

		while ( $updated < $max_per_run ) {
			$limit = min( $batch_size, $max_per_run - $updated );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, raw_data FROM {$table}
					WHERE raw_data IS NOT NULL AND TRIM(raw_data) != ''
					AND bedrooms IS NULL
					ORDER BY id ASC
					LIMIT %d",
					$limit
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$raw = json_decode( (string) $row->raw_data, true );
				if ( ! is_array( $raw ) ) {
					++$json_errors;
					continue;
				}

				$fields = lpnw_backfill_fields_extract( $raw );
				lpnw_backfill_fields_update_row( $wpdb, $table, (int) $row->id, $fields );
				++$updated;
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}
			WHERE raw_data IS NOT NULL AND TRIM(raw_data) != ''
			AND bedrooms IS NULL"
		);

		echo "LPNW backfill structured fields\n";
		echo "Updated this run: {$updated}\n";
		if ( $json_errors > 0 ) {
			echo "Skipped (invalid JSON): {$json_errors}\n";
		}
		echo "Remaining (raw_data set, bedrooms NULL): {$remaining}\n";
		echo "\nRun again until remaining is 0 if needed.\n";
		exit;
	},
	1
);
