<?php
/**
 * Property data model.
 *
 * Handles CRUD operations for the lpnw_properties table.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Property {

	/**
	 * Insert or update a property record. Returns the property ID.
	 *
	 * @param array<string, mixed> $data Normalised property data.
	 * @return int|false Property ID on success, false on failure.
	 */
	public static function upsert( array $data ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'lpnw_properties';
		$source = sanitize_text_field( $data['source'] ?? '' );
		$ref    = sanitize_text_field( $data['source_ref'] ?? '' );

		if ( empty( $source ) || empty( $ref ) ) {
			return false;
		}

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE source = %s AND source_ref = %s",
			$source,
			$ref
		) );

		if ( ! $existing ) {
			$existing = self::find_cross_portal_duplicate( $data, $table );
		}

		$row = array(
			'source'           => $source,
			'source_ref'       => $ref,
			'address'          => sanitize_text_field( $data['address'] ?? '' ),
			'postcode'         => self::clean_postcode( $data['postcode'] ?? '' ),
			'latitude'         => isset( $data['latitude'] ) ? floatval( $data['latitude'] ) : null,
			'longitude'        => isset( $data['longitude'] ) ? floatval( $data['longitude'] ) : null,
			'price'            => isset( $data['price'] ) ? absint( $data['price'] ) : null,
			'property_type'    => sanitize_text_field( $data['property_type'] ?? '' ),
			'description'      => wp_kses_post( $data['description'] ?? '' ),
			'application_type' => sanitize_text_field( $data['application_type'] ?? '' ),
			'auction_date'     => isset( $data['auction_date'] ) ? sanitize_text_field( $data['auction_date'] ) : null,
			'source_url'       => esc_url_raw( $data['source_url'] ?? '' ),
			'raw_data'         => isset( $data['raw_data'] ) ? wp_json_encode( $data['raw_data'] ) : null,
		);

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => $existing ) );
			return (int) $existing;
		}

		$wpdb->insert( $table, $row );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get properties matching given filters.
	 *
	 * @param array<string, mixed> $filters Filter criteria.
	 * @param int                  $limit   Max results.
	 * @param int                  $offset  Pagination offset.
	 * @return array<int, object>
	 */
	public static function query( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lpnw_properties';
		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $filters['source'] ) ) {
			$where[] = 'source = %s';
			$args[]  = sanitize_text_field( $filters['source'] );
		}

		if ( ! empty( $filters['postcode_prefix'] ) ) {
			$where[] = 'postcode LIKE %s';
			$args[]  = sanitize_text_field( $filters['postcode_prefix'] ) . '%';
		}

		if ( ! empty( $filters['min_price'] ) ) {
			$where[] = 'price >= %d';
			$args[]  = absint( $filters['min_price'] );
		}

		if ( ! empty( $filters['max_price'] ) ) {
			$where[] = 'price <= %d';
			$args[]  = absint( $filters['max_price'] );
		}

		if ( ! empty( $filters['property_type'] ) ) {
			$where[] = 'property_type = %s';
			$args[]  = sanitize_text_field( $filters['property_type'] );
		}

		if ( ! empty( $filters['since'] ) ) {
			$where[] = 'created_at >= %s';
			$args[]  = sanitize_text_field( $filters['since'] );
		}

		$where_clause = implode( ' AND ', $where );
		$args[]       = $limit;
		$args[]       = $offset;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$args
		) );
	}

	/**
	 * Recent properties with at most one row per NW postcode prefix (LPNW_NW_POSTCODES).
	 *
	 * Avoids the homepage clustering that happens when ORDER BY created_at DESC returns
	 * only the last batch from a sequentially processed feed region.
	 *
	 * @param array<string, mixed> $filters Supported keys: source (same as query()).
	 * @param int                  $limit   Max rows after ordering by recency.
	 * @return array<int, object>
	 */
	public static function query_diverse( array $filters = array(), int $limit = 6 ): array {
		global $wpdb;

		$limit = max( 1, min( absint( $limit ), 100 ) );
		$table = $wpdb->prefix . 'lpnw_properties';

		$extra_where = '';
		$prepare_args = array();

		if ( ! empty( $filters['source'] ) ) {
			$extra_where   = ' AND source = %s';
			$prepare_args[] = sanitize_text_field( $filters['source'] );
		}

		if ( self::db_supports_window_functions() ) {
			$pc            = 'UPPER(TRIM(p.postcode))';
			$bucket_case   = self::get_nw_postcode_bucket_case_sql( $pc );
			$sql           = "SELECT ranked.id, ranked.source, ranked.source_ref, ranked.address, ranked.postcode, ranked.latitude, ranked.longitude, ranked.price, ranked.property_type, ranked.description, ranked.application_type, ranked.auction_date, ranked.source_url, ranked.raw_data, ranked.created_at, ranked.updated_at
				FROM (
					SELECT p.*, ROW_NUMBER() OVER (
						PARTITION BY {$bucket_case}
						ORDER BY p.created_at DESC
					) AS lpnw_rn
					FROM {$table} p
					WHERE TRIM(p.postcode) <> ''
					AND ({$bucket_case}) <> ''
					{$extra_where}
				) ranked
				WHERE ranked.lpnw_rn = 1
				ORDER BY ranked.created_at DESC
				LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$prepare_args[] = $limit;

			return $wpdb->get_results( $wpdb->prepare( $sql, ...$prepare_args ) );
		}

		return self::query_diverse_union_fallback( $table, $extra_where, $prepare_args, $limit );
	}

	/**
	 * Whether the database server supports ROW_NUMBER() window functions.
	 */
	private static function db_supports_window_functions(): bool {
		static $supports = null;

		if ( null !== $supports ) {
			return $supports;
		}

		global $wpdb;

		$supports = false;
		$version = $wpdb->get_var( 'SELECT VERSION()' );

		if ( ! is_string( $version ) || '' === $version ) {
			$supports = false;
			return $supports;
		}

		if ( preg_match( '/MariaDB/i', $version ) ) {
			if ( preg_match( '/^(\d+)\.(\d+)/', $version, $m ) ) {
				$major = (int) $m[1];
				$minor = (int) $m[2];
				$supports = ( $major > 10 ) || ( 10 === $major && $minor >= 2 );
			}
		} elseif ( preg_match( '/^(\d+)\.(\d+)/', $version, $m ) ) {
			$supports = (int) $m[1] >= 8;
		}

		return $supports;
	}

	/**
	 * SQL CASE expression mapping a normalised postcode expression to an LPNW_NW_POSTCODES bucket.
	 *
	 * Two-letter prefixes are tested before M and L so LA maps to LA, not L.
	 *
	 * @param string $postcode_expr SQL expression (e.g. UPPER(TRIM(p.postcode))).
	 */
	private static function get_nw_postcode_bucket_case_sql( string $postcode_expr ): string {
		return "CASE
			WHEN {$postcode_expr} LIKE 'BB%' THEN 'BB'
			WHEN {$postcode_expr} LIKE 'BL%' THEN 'BL'
			WHEN {$postcode_expr} LIKE 'CA%' THEN 'CA'
			WHEN {$postcode_expr} LIKE 'CH%' THEN 'CH'
			WHEN {$postcode_expr} LIKE 'CW%' THEN 'CW'
			WHEN {$postcode_expr} LIKE 'FY%' THEN 'FY'
			WHEN {$postcode_expr} LIKE 'LA%' THEN 'LA'
			WHEN {$postcode_expr} LIKE 'OL%' THEN 'OL'
			WHEN {$postcode_expr} LIKE 'PR%' THEN 'PR'
			WHEN {$postcode_expr} LIKE 'SK%' THEN 'SK'
			WHEN {$postcode_expr} LIKE 'WA%' THEN 'WA'
			WHEN {$postcode_expr} LIKE 'WN%' THEN 'WN'
			WHEN {$postcode_expr} REGEXP '^M[0-9]' THEN 'M'
			WHEN {$postcode_expr} REGEXP '^L[0-9]' THEN 'L'
			ELSE ''
		END";
	}

	/**
	 * One recent property per NW prefix via UNION ALL (MySQL before 8.0, MariaDB before 10.2).
	 *
	 * @param string               $table        Full table name.
	 * @param string               $extra_where  Extra AND clause with placeholders (e.g. " AND source = %s").
	 * @param array<int, mixed>    $prepare_args Args for extra_where placeholders only.
	 * @param int                  $limit        Final LIMIT.
	 * @return array<int, object>
	 */
	private static function query_diverse_union_fallback( string $table, string $extra_where, array $prepare_args, int $limit ): array {
		global $wpdb;

		$pc = 'UPPER(TRIM(postcode))';

		$fragments = array(
			"({$pc} LIKE 'BB%')",
			"({$pc} LIKE 'BL%')",
			"({$pc} LIKE 'CA%')",
			"({$pc} LIKE 'CH%')",
			"({$pc} LIKE 'CW%')",
			"({$pc} LIKE 'FY%')",
			"({$pc} LIKE 'LA%')",
			"({$pc} LIKE 'OL%')",
			"({$pc} LIKE 'PR%')",
			"({$pc} LIKE 'SK%')",
			"({$pc} LIKE 'WA%')",
			"({$pc} LIKE 'WN%')",
			"({$pc} REGEXP '^M[0-9]')",
			"({$pc} REGEXP '^L[0-9]')",
		);

		$union_parts = array();
		foreach ( $fragments as $cond ) {
			$union_parts[] = "(SELECT * FROM {$table} WHERE TRIM(postcode) <> '' AND {$cond}{$extra_where} ORDER BY created_at DESC LIMIT 1)";
		}

		$sql = 'SELECT * FROM (' . implode( ' UNION ALL ', $union_parts ) . ') AS lpnw_diverse ORDER BY created_at DESC LIMIT %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$all_args = array();
		if ( ! empty( $prepare_args ) ) {
			$source_val = $prepare_args[0];
			$repeat     = count( $fragments );
			for ( $i = 0; $i < $repeat; $i++ ) {
				$all_args[] = $source_val;
			}
		}
		$all_args[] = $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$all_args ) );
	}

	/**
	 * Get a single property by ID.
	 *
	 * @param int $id Property ID.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_properties';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );
	}

	/**
	 * Get IDs of properties created since a given datetime.
	 *
	 * @param string $since MySQL datetime string.
	 * @return array<int>
	 */
	public static function get_new_since( string $since ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_properties';

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE created_at >= %s ORDER BY created_at ASC",
			$since
		) );

		return array_map( 'intval', $results );
	}

	/**
	 * Check if the same property already exists from a different portal source.
	 *
	 * Matches by normalised postcode + price + address similarity.
	 * Prevents the same property listed on both Rightmove and Zoopla
	 * from generating duplicate alerts.
	 *
	 * @param array<string, mixed> $data  Property data being inserted.
	 * @param string               $table Table name.
	 * @return int|null Existing property ID if duplicate found.
	 */
	private static function find_cross_portal_duplicate( array $data, string $table ): ?int {
		global $wpdb;

		$portal_sources = array( 'rightmove', 'zoopla' );
		$source         = $data['source'] ?? '';

		if ( ! in_array( $source, $portal_sources, true ) ) {
			return null;
		}

		$postcode = self::clean_postcode( $data['postcode'] ?? '' );
		$price    = isset( $data['price'] ) ? absint( $data['price'] ) : 0;

		if ( empty( $postcode ) || 0 === $price ) {
			return null;
		}

		$other_sources = array_diff( $portal_sources, array( $source ) );
		if ( empty( $other_sources ) ) {
			return null;
		}

		$placeholders  = implode( ',', array_fill( 0, count( $other_sources ), '%s' ) );
		$app_type      = sanitize_text_field( $data['application_type'] ?? '' );
		$args          = array_merge( $other_sources, array( $postcode, $price, $app_type ) );

		$match = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table}
			 WHERE source IN ({$placeholders})
			 AND postcode = %s
			 AND price = %d
			 AND COALESCE(application_type, '') = %s
			 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$args
		) );

		return $match ? (int) $match : null;
	}

	private static function clean_postcode( string $postcode ): string {
		$postcode = strtoupper( trim( $postcode ) );
		$postcode = preg_replace( '/[^A-Z0-9 ]/', '', $postcode );
		return $postcode;
	}
}
