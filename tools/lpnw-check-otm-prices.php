<?php
/**
 * Diagnostic: OnTheMarket rental price parsing vs raw_data.
 *
 * Drop into wp-content/mu-plugins/ then open:
 *   ?lpnw_otm_prices=check&key=YOUR_LPNW_SECRET (see docs/DEPLOYMENT.md)
 * Re-apply corrected parsing (rentals with price > 10000 only):
 *   ?lpnw_otm_prices=fix&key=YOUR_LPNW_SECRET (see docs/DEPLOYMENT.md)
 *
 * Deletes itself after a successful run.
 *
 * @package LPNW
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

/**
 * Parse OTM human-readable price strings (with commas, pcm, optional sq ft in parentheses).
 *
 * Mirrors LPNW_Feed_Portal_OnTheMarket::otm_price_from_display_string().
 *
 * @param string $s Raw display string.
 * @return int 0 if no amount found.
 */
function lpnw_check_otm_price_from_display_string( string $s ): int {
	$s = trim( $s );
	if ( '' === $s ) {
		return 0;
	}

	$paren = strpos( $s, '(' );
	if ( false !== $paren ) {
		$before = trim( substr( $s, 0, $paren ) );
		if ( '' !== $before ) {
			$s = $before;
		}
	}

	if ( preg_match( '/(?:£|GBP\s*)([0-9][0-9,]*(?:\.[0-9]+)?)/iu', $s, $m ) ) {
		return absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
	}

	if ( preg_match( '/^([0-9][0-9,]*(?:\.[0-9]+)?)/', $s, $m ) ) {
		return absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
	}

	if ( preg_match( '/([0-9]{1,3}(?:,[0-9]{3})+|[0-9]{2,})/', $s, $m ) ) {
		return absint( preg_replace( '/[^0-9]/', '', $m[1] ) );
	}

	return 0;
}

/**
 * Normalised listing price from OTM raw row (numeric fields first, then display strings).
 *
 * Mirrors LPNW_Feed_Portal_OnTheMarket::otm_parse_listing_price().
 *
 * @param array<string, mixed> $raw_item Decoded raw_data.
 * @return int 0 if unknown.
 */
function lpnw_check_otm_parse_listing_price( array $raw_item ): int {
	foreach ( array( 'price-value', 'priceValue', 'numericPrice', 'displayPriceValue' ) as $k ) {
		if ( ! isset( $raw_item[ $k ] ) ) {
			continue;
		}
		$v = $raw_item[ $k ];
		if ( is_numeric( $v ) ) {
			$n = (int) round( floatval( $v ) );
			if ( $n > 0 ) {
				return $n;
			}
		}
	}

	$str_candidates = array();
	foreach ( array( 'short-price', 'price' ) as $k ) {
		if ( ! isset( $raw_item[ $k ] ) ) {
			continue;
		}
		$v = $raw_item[ $k ];
		if ( is_string( $v ) && '' !== trim( $v ) ) {
			$str_candidates[] = $v;
		}
	}

	foreach ( $str_candidates as $s ) {
		$n = lpnw_check_otm_price_from_display_string( $s );
		if ( $n > 0 ) {
			return $n;
		}
	}

	return 0;
}

/**
 * @param object{id:int, address:string, price:int|string, application_type:string, raw_data:string|null} $row
 */
function lpnw_check_otm_format_raw_price_fields( object $row ): array {
	$raw = null;
	if ( ! empty( $row->raw_data ) ) {
		$raw = json_decode( $row->raw_data, true );
	}
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$keys   = array( 'price-value', 'priceValue', 'numericPrice', 'displayPriceValue', 'short-price', 'price' );
	$out    = array();
	$subset = array();
	foreach ( $keys as $k ) {
		if ( ! array_key_exists( $k, $raw ) ) {
			continue;
		}
		$v = $raw[ $k ];
		if ( is_scalar( $v ) ) {
			$subset[ $k ] = (string) $v;
		} else {
			$subset[ $k ] = wp_json_encode( $v );
		}
	}
	$out['subset']   = $subset;
	$out['reparsed'] = lpnw_check_otm_parse_listing_price( $raw );
	return $out;
}

add_action(
	'init',
	static function () {
		$mode = isset( $_GET['lpnw_otm_prices'] ) ? sanitize_key( wp_unslash( (string) $_GET['lpnw_otm_prices'] ) ) : '';
		if ( '' === $mode || ! in_array( $mode, array( 'check', 'fix' ), true ) ) {
			return;
		}
		$key = isset( $_GET['key'] ) ? wp_unslash( (string) $_GET['key'] ) : '';
		if ( ! lpnw_tool_query_key_ok( $key ) ) {
			return;
		}

		set_time_limit( 300 );
		header( 'Content-Type: text/plain; charset=utf-8' );

		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_properties';

		if ( 'fix' === $mode ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from prefix.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE source = %s AND application_type = %s AND price > %d ORDER BY id ASC",
					'onthemarket',
					'rent',
					10000
				)
			);
			$updated = 0;
			$skipped = 0;
			foreach ( $ids as $id ) {
				$id = (int) $id;
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, price, raw_data FROM {$table} WHERE id = %d",
						$id
					)
				);
				if ( ! $row || empty( $row->raw_data ) ) {
					++$skipped;
					continue;
				}
				$raw = json_decode( $row->raw_data, true );
				if ( ! is_array( $raw ) ) {
					++$skipped;
					continue;
				}
				$new_price = lpnw_check_otm_parse_listing_price( $raw );
				$old_price = (int) $row->price;
				if ( $new_price <= 0 || $new_price === $old_price ) {
					++$skipped;
					continue;
				}
				$wpdb->update(
					$table,
					array( 'price' => $new_price ),
					array( 'id' => $id ),
					array( '%d' ),
					array( '%d' )
				);
				echo "id {$id}: price {$old_price} -> {$new_price}\n";
				++$updated;
			}
			echo "\nLPNW OTM price fix: updated {$updated}, skipped {$skipped}.\n";
			@unlink( __FILE__ );
			exit;
		}

		// --- check ---
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$high = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, address, price, application_type, raw_data
				FROM {$table}
				WHERE source = %s AND application_type = %s AND price > %d
				ORDER BY price DESC
				LIMIT %d",
				'onthemarket',
				'rent',
				100000,
				10
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$low = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, address, price, application_type, raw_data
				FROM {$table}
				WHERE source = %s AND application_type = %s AND price > 0 AND price < %d
				ORDER BY price DESC
				LIMIT %d",
				'onthemarket',
				'rent',
				5000,
				10
			)
		);

		$suspect_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE source = %s AND application_type = %s AND price > %d",
				'onthemarket',
				'rent',
				10000
			)
		);

		echo "LPNW OnTheMarket rental price diagnostic\n\n";

		echo "=== Sample likely mis-parsed (rent, stored price > 100000) ===\n";
		if ( empty( $high ) ) {
			echo "(none)\n";
		} else {
			foreach ( $high as $row ) {
				$info = lpnw_check_otm_format_raw_price_fields( $row );
				echo "id {$row->id}\n";
				echo "  address: {$row->address}\n";
				echo "  stored price: {$row->price}\n";
				echo "  application_type: {$row->application_type}\n";
				echo '  raw price fields: ' . wp_json_encode( $info['subset'], JSON_UNESCAPED_UNICODE ) . "\n";
				echo "  reparsed from raw_data (feed logic): {$info['reparsed']}\n\n";
			}
		}

		echo "=== Sample likely OK rentals (rent, 0 < price < 5000) ===\n";
		if ( empty( $low ) ) {
			echo "(none)\n";
		} else {
			foreach ( $low as $row ) {
				$info = lpnw_check_otm_format_raw_price_fields( $row );
				echo "id {$row->id}\n";
				echo "  address: {$row->address}\n";
				echo "  stored price: {$row->price}\n";
				echo "  application_type: {$row->application_type}\n";
				echo '  raw price fields: ' . wp_json_encode( $info['subset'], JSON_UNESCAPED_UNICODE ) . "\n";
				echo "  reparsed from raw_data (feed logic): {$info['reparsed']}\n\n";
			}
		}

		echo "=== Count: OnTheMarket + rent + price > 10000 (likely wrong) ===\n";
		echo "{$suspect_count}\n\n";

		if ( $suspect_count > 0 ) {
			$home = home_url( '/' );
			echo "To re-extract price from raw_data for those rows (price > 10000, rent), run once:\n";
			echo "{$home}?lpnw_otm_prices=fix&key=(same as LPNW_CRON_SECRET, LPNW_PAGE_SYNC_SECRET, LPNW_LOGIN_AS_SECRET, or dev lpnw2026setup)\n";
		}

		@unlink( __FILE__ );
		exit;
	},
	1
);
