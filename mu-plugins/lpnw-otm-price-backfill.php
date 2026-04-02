<?php
/**
 * One-time: Re-parse OTM prices from raw_data to fix k-suffix bug.
 * Self-deletes after execution.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_loaded', function () {
	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_properties';

	$rows = $wpdb->get_results(
		"SELECT id, price, raw_data FROM {$table} WHERE source IN ('onthemarket') AND raw_data IS NOT NULL AND raw_data != '' LIMIT 5000"
	);

	if ( empty( $rows ) ) {
		error_log( '[LPNW-backfill] No OTM rows found' );
		@unlink( __FILE__ );
		return;
	}

	$updated = 0;
	foreach ( $rows as $row ) {
		$raw = json_decode( $row->raw_data, true );
		if ( ! is_array( $raw ) ) {
			continue;
		}

		$new_price = 0;

		foreach ( array( 'price-value', 'priceValue', 'numericPrice', 'displayPriceValue' ) as $k ) {
			if ( isset( $raw[ $k ] ) && is_numeric( $raw[ $k ] ) ) {
				$n = (int) round( floatval( $raw[ $k ] ) );
				if ( $n > 5000 ) {
					$new_price = $n;
					break;
				}
				if ( $n > 0 && 0 === $new_price ) {
					$new_price = $n;
				}
			}
		}

		if ( $new_price <= 5000 ) {
			foreach ( array( 'short-price', 'price' ) as $k ) {
				if ( ! isset( $raw[ $k ] ) || ! is_string( $raw[ $k ] ) ) {
					continue;
				}
				$s = trim( $raw[ $k ] );
				$paren = strpos( $s, '(' );
				if ( false !== $paren ) {
					$before = trim( substr( $s, 0, $paren ) );
					if ( '' !== $before ) {
						$s = $before;
					}
				}
				if ( preg_match( '/(?:£|GBP\s*)([0-9][0-9,]*(?:\.[0-9]+)?)\s*([kKmM])?/iu', $s, $m ) ) {
					$n = floatval( preg_replace( '/[^0-9.]/', '', $m[1] ) );
					if ( ! empty( $m[2] ) ) {
						$suffix = strtolower( $m[2] );
						if ( 'k' === $suffix ) { $n *= 1000; }
						elseif ( 'm' === $suffix ) { $n *= 1000000; }
					}
					$result = absint( round( $n ) );
					if ( $result > 0 ) {
						$new_price = $result;
						break;
					}
				}
			}
		}

		if ( $new_price > 0 && (int) $row->price !== $new_price ) {
			$wpdb->update( $table, array( 'price' => $new_price ), array( 'id' => $row->id ), array( '%d' ), array( '%d' ) );
			$updated++;
		}
	}

	error_log( '[LPNW-backfill] Updated ' . $updated . ' OTM prices' );
	@unlink( __FILE__ );
}, 99 );
