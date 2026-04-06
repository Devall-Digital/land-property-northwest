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
	 * @param bool|null            $inserted_new If a non-null variable is passed, set to true when a new row was inserted, false when an existing row was updated.
	 * @return int|false Property ID on success, false on failure.
	 */
	public static function upsert( array $data, ?bool &$inserted_new = null ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'lpnw_properties';
		$source = sanitize_text_field( $data['source'] ?? '' );
		$ref    = sanitize_text_field( $data['source_ref'] ?? '' );

		if ( null !== $inserted_new ) {
			$inserted_new = false;
		}

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
			'bedrooms'         => isset( $data['bedrooms'] ) ? absint( $data['bedrooms'] ) : null,
			'bathrooms'        => isset( $data['bathrooms'] ) ? absint( $data['bathrooms'] ) : null,
			'tenure_type'      => sanitize_text_field( $data['tenure_type'] ?? '' ),
			'price_frequency'  => sanitize_text_field( $data['price_frequency'] ?? '' ),
			'floor_area_sqft'  => isset( $data['floor_area_sqft'] ) ? absint( $data['floor_area_sqft'] ) : null,
			'first_listed_date' => isset( $data['first_listed_date'] ) ? sanitize_text_field( $data['first_listed_date'] ) : null,
			'agent_name'       => sanitize_text_field( $data['agent_name'] ?? '' ),
			'key_features_text' => sanitize_text_field( $data['key_features_text'] ?? '' ),
			'description'      => wp_kses_post( $data['description'] ?? '' ),
			'application_type' => sanitize_text_field( $data['application_type'] ?? '' ),
			'auction_date'     => isset( $data['auction_date'] ) ? sanitize_text_field( $data['auction_date'] ) : null,
			'source_url'       => esc_url_raw( $data['source_url'] ?? '' ),
			'raw_data'         => isset( $data['raw_data'] ) ? wp_json_encode( $data['raw_data'] ) : null,
		);

		if ( $existing ) {
			$row['first_listed_date'] = self::resolve_first_listed_date_for_update(
				(int) $existing,
				$table,
				$row['first_listed_date']
			);
			$updated = $wpdb->update( $table, $row, array( 'id' => $existing ) );
			return false === $updated ? false : (int) $existing;
		}

		$wpdb->insert( $table, $row );
		$new_id = $wpdb->insert_id ? (int) $wpdb->insert_id : false;
		if ( $new_id && null !== $inserted_new ) {
			$inserted_new = true;
		}
		return $new_id;
	}

	/**
	 * Keep the best first_listed_date on update: never wipe with an empty fetch,
	 * and use the earlier of incoming vs stored when both are present (cross-portal dedupe).
	 *
	 * @param int         $id       Existing property row ID.
	 * @param string      $table    Full lpnw_properties table name.
	 * @param string|null $incoming Y-m-d from the feed or null.
	 * @return string|null         Value to write (DATE column).
	 */
	private static function resolve_first_listed_date_for_update( int $id, string $table, $incoming ): ?string {
		global $wpdb;

		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT first_listed_date FROM {$table} WHERE id = %d",
				$id
			)
		);

		$inc = is_string( $incoming ) ? trim( $incoming ) : '';
		$cur = ( null !== $current && '' !== (string) $current ) ? trim( (string) $current ) : '';

		if ( '' === $inc ) {
			return '' !== $cur ? $cur : null;
		}

		if ( '' === $cur ) {
			return $inc;
		}

		$t_inc = strtotime( $inc );
		$t_cur = strtotime( $cur );
		if ( false === $t_inc ) {
			return $cur;
		}
		if ( false === $t_cur ) {
			return $inc;
		}

		return $t_inc <= $t_cur ? $inc : $cur;
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
		list( $where_clause, $args ) = self::build_filter_where_clause( $filters );
		$args[] = $limit;
		$args[] = $offset;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$args
		) );
	}

	/**
	 * Count properties matching the same filters as query().
	 *
	 * @param array<string, mixed> $filters Same keys as query().
	 * @return int
	 */
	public static function count_with_filters( array $filters = array() ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'lpnw_properties';
		list( $where_clause, $args ) = self::build_filter_where_clause( $filters );

		if ( empty( $args ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$args
		) );
	}

	/**
	 * Build WHERE clause and placeholder args for property filters.
	 *
	 * @param array<string, mixed> $filters source, auction_sources, postcode_prefix, min_price, max_price,
	 *                                        property_type, property_type_category, channel (sale|rent), since,
	 *                                        bedrooms (1-5; 5 means five or more), tenure (Freehold|Leasehold).
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private static function build_filter_where_clause( array $filters ): array {
		global $wpdb;

		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $filters['auction_sources'] ) ) {
			$where[] = 'source LIKE %s';
			$args[]  = $wpdb->esc_like( 'auction_' ) . '%';
		} elseif ( ! empty( $filters['source'] ) ) {
			$where[] = 'source = %s';
			$args[]  = sanitize_text_field( $filters['source'] );
		}

		if ( ! empty( $filters['postcode_prefix'] ) ) {
			self::append_postcode_prefix_sql( 'UPPER(TRIM(postcode))', $filters['postcode_prefix'], $where, $args );
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

		if ( ! empty( $filters['property_type_category'] ) ) {
			self::append_property_type_category_sql( $filters['property_type_category'], $where, $args );
		}

		if ( ! empty( $filters['channel'] ) ) {
			$ch = strtolower( trim( sanitize_text_field( $filters['channel'] ) ) );
			if ( 'rent' === $ch ) {
				$where[] = 'LOWER(TRIM(COALESCE(application_type, \'\'))) = %s';
				$args[]  = 'rent';
			} elseif ( 'sale' === $ch ) {
				$where[] = 'COALESCE(LOWER(TRIM(application_type)), \'\') <> %s';
				$args[]  = 'rent';
			}
		}

		if ( isset( $filters['bedrooms'] ) && '' !== $filters['bedrooms'] && null !== $filters['bedrooms'] ) {
			$br = absint( $filters['bedrooms'] );
			if ( $br >= 1 && $br <= 4 ) {
				$where[] = 'bedrooms = %d';
				$args[]  = $br;
			} elseif ( 5 === $br ) {
				$where[] = 'bedrooms >= %d';
				$args[]  = 5;
			}
		}

		if ( ! empty( $filters['tenure'] ) ) {
			$where[] = 'tenure_type = %s';
			$args[]  = sanitize_text_field( $filters['tenure'] );
		}

		if ( ! empty( $filters['since'] ) ) {
			$where[] = 'created_at >= %s';
			$args[]  = sanitize_text_field( $filters['since'] );
		}

		return array( implode( ' AND ', $where ), $args );
	}

	/**
	 * Restrict by broad property type category (portal-style wording).
	 *
	 * @param string               $category One of Detached, Semi-detached, Terraced, Flat, Other.
	 * @param array<int, string>   $where    WHERE fragments.
	 * @param array<int, mixed>    $args     prepare args.
	 */
	private static function append_property_type_category_sql( string $category, array &$where, array &$args ): void {
		$category = trim( $category );
		$allowed  = array( 'Detached', 'Semi-detached', 'Terraced', 'Flat', 'Other' );
		if ( ! in_array( $category, $allowed, true ) ) {
			return;
		}

		$pt = 'LOWER(TRIM(COALESCE(property_type, \'\')))';

		if ( 'Detached' === $category ) {
			$where[] = "({$pt} LIKE %s AND {$pt} NOT LIKE %s)";
			$args[]  = '%detached%';
			$args[]  = '%semi%';
			return;
		}

		if ( 'Semi-detached' === $category ) {
			$where[] = "({$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s)";
			$args[]  = '%semi-detached%';
			$args[]  = '%semi detached%';
			$args[]  = '%semi-detached %';
			return;
		}

		if ( 'Terraced' === $category ) {
			$where[] = "({$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s)";
			$args[]  = '%terraced%';
			$args[]  = '%terrace%';
			$args[]  = '%end of terrace%';
			$args[]  = '%town house%';
			return;
		}

		if ( 'Flat' === $category ) {
			$where[] = "({$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s)";
			$args[]  = '%flat%';
			$args[]  = '%apartment%';
			$args[]  = '%maisonette%';
			$args[]  = '%penthouse%';
			return;
		}

		// Other: has a type label but not one of the main portal buckets above.
		$where[] = "TRIM(COALESCE(property_type, '')) <> '' AND NOT (
			(({$pt} LIKE %s AND {$pt} NOT LIKE %s))
			OR ({$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s)
			OR ({$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s)
			OR ({$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s OR {$pt} LIKE %s)
		)";
		$args[] = '%detached%';
		$args[] = '%semi%';
		$args[] = '%semi-detached%';
		$args[] = '%semi detached%';
		$args[] = '%semi-detached %';
		$args[] = '%terraced%';
		$args[] = '%terrace%';
		$args[] = '%end of terrace%';
		$args[] = '%town house%';
		$args[] = '%flat%';
		$args[] = '%apartment%';
		$args[] = '%maisonette%';
		$args[] = '%penthouse%';
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
			$sql           = "SELECT ranked.id, ranked.source, ranked.source_ref, ranked.address, ranked.postcode, ranked.latitude, ranked.longitude, ranked.price, ranked.property_type, ranked.bedrooms, ranked.bathrooms, ranked.tenure_type, ranked.price_frequency, ranked.floor_area_sqft, ranked.first_listed_date, ranked.agent_name, ranked.key_features_text, ranked.description, ranked.application_type, ranked.auction_date, ranked.source_url, ranked.raw_data, ranked.created_at, ranked.updated_at
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
	 * NW postcode bucket codes to human-readable area names (marketing / stats).
	 *
	 * @return array<string, string>
	 */
	public static function get_nw_area_labels(): array {
		return array(
			'M'  => 'Manchester',
			'L'  => 'Liverpool',
			'PR' => 'Preston',
			'BB' => 'Blackburn',
			'LA' => 'Lancaster',
			'BL' => 'Bolton',
			'OL' => 'Oldham',
			'SK' => 'Stockport',
			'WA' => 'Warrington',
			'WN' => 'Wigan',
			'CW' => 'Crewe/Cheshire',
			'CH' => 'Chester',
			'CA' => 'Carlisle',
			'FY' => 'Blackpool',
		);
	}

	/**
	 * Property counts grouped by NW postcode bucket (same rules as alert area matching).
	 *
	 * @return array<int, object{area: string, cnt: string}>
	 */
	public static function count_by_nw_area(): array {
		global $wpdb;

		$table       = $wpdb->prefix . 'lpnw_properties';
		$pc          = 'UPPER(TRIM(postcode))';
		$bucket_case = self::get_nw_postcode_bucket_case_sql( $pc );

		$sql = "SELECT bucket_area AS area, COUNT(*) AS cnt FROM (
			SELECT ({$bucket_case}) AS bucket_area
			FROM {$table}
			WHERE TRIM(postcode) <> ''
		) AS lpnw_area_buckets
		WHERE bucket_area <> ''
		GROUP BY bucket_area
		ORDER BY cnt DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results( $sql );

		return is_array( $rows ) ? $rows : array();
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
	public static function get_new_since( string $since, int $limit = 5000 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_properties';

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE created_at >= %s ORDER BY created_at ASC LIMIT %d",
			$since,
			max( 1, $limit )
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

		$portal_sources = array( 'rightmove', 'zoopla', 'onthemarket' );
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

	/**
	 * Property IDs that likely refer to the same physical listing as the given row.
	 *
	 * Matches when: same postcode + similar address + same price + same application_type, OR
	 * same price + same application_type + lat/lng within 0.001 degrees.
	 *
	 * @param int $property_id Seed property ID.
	 * @return array<int>
	 */
	public static function find_same_property( int $property_id ): array {
		$prop = self::get( $property_id );
		if ( ! $prop ) {
			return array();
		}

		global $wpdb;

		$table    = $wpdb->prefix . 'lpnw_properties';
		$postcode = trim( (string) ( $prop->postcode ?? '' ) );
		$price    = isset( $prop->price ) ? absint( $prop->price ) : 0;
		$app_type = (string) ( $prop->application_type ?? '' );

		$lat = ( isset( $prop->latitude ) && '' !== $prop->latitude && null !== $prop->latitude ) ? (float) $prop->latitude : null;
		$lng = ( isset( $prop->longitude ) && '' !== $prop->longitude && null !== $prop->longitude ) ? (float) $prop->longitude : null;

		if ( '' === $postcode && ( null === $lat || null === $lng ) ) {
			return array( $property_id );
		}

		if ( 0 === $price ) {
			return array( $property_id );
		}

		$candidates = array();

		if ( '' !== $postcode ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, address, postcode, latitude, longitude, price, application_type FROM {$table}
				WHERE postcode = %s AND price = %d AND COALESCE(application_type, '') = %s
				LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$postcode,
				$price,
				$app_type
			) );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$candidates[ (int) $row->id ] = $row;
				}
			}
		}

		if ( null !== $lat && null !== $lng ) {
			$rows2 = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, address, postcode, latitude, longitude, price, application_type FROM {$table}
				WHERE price = %d AND COALESCE(application_type, '') = %s
				AND latitude IS NOT NULL AND longitude IS NOT NULL
				AND ABS(latitude - %f) <= 0.001 AND ABS(longitude - %f) <= 0.001
				LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$price,
				$app_type,
				$lat,
				$lng
			) );
			if ( is_array( $rows2 ) ) {
				foreach ( $rows2 as $row ) {
					$candidates[ (int) $row->id ] = $row;
				}
			}
		}

		$matches = array();
		foreach ( $candidates as $row ) {
			$ok = false;
			if ( '' !== $postcode && (string) $row->postcode === $postcode && self::addresses_similar( (string) $row->address, (string) $prop->address ) ) {
				$ok = true;
			}
			if ( null !== $lat && null !== $lng && null !== $row->latitude && null !== $row->longitude && '' !== $row->latitude && '' !== $row->longitude ) {
				$rlat = (float) $row->latitude;
				$rlng = (float) $row->longitude;
				if ( abs( $rlat - $lat ) <= 0.001 && abs( $rlng - $lng ) <= 0.001 ) {
					$ok = true;
				}
			}
			if ( $ok ) {
				$matches[] = (int) $row->id;
			}
		}

		$matches[] = $property_id;
		$matches = array_values( array_unique( $matches ) );
		sort( $matches, SORT_NUMERIC );

		return $matches;
	}

	/**
	 * Human-readable source names for all listings matching the same physical property as this ID.
	 *
	 * @param int $property_id Property ID.
	 * @return array<int, string> Unique display labels (e.g. Rightmove, Zoopla).
	 */
	public static function get_source_list( int $property_id ): array {
		$ids = self::find_same_property( $property_id );
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;

		$table        = $wpdb->prefix . 'lpnw_properties';
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sources      = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT source FROM {$table} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$ids
		) );

		if ( ! is_array( $sources ) ) {
			return array();
		}

		$labels = array();
		foreach ( $sources as $src ) {
			$labels[] = self::source_to_display_name( (string) $src );
		}

		$labels = array_values( array_unique( $labels ) );
		sort( $labels, SORT_STRING );

		return $labels;
	}

	/**
	 * Normalise address text for comparison.
	 */
	private static function normalize_address_for_match( string $address ): string {
		$a = strtoupper( trim( preg_replace( '/\s+/', ' ', $address ) ) );
		$a = preg_replace( '/[^A-Z0-9 ]/', '', $a );
		return is_string( $a ) ? $a : '';
	}

	/**
	 * Lightweight similarity check for cross-portal address strings.
	 */
	private static function addresses_similar( string $a, string $b ): bool {
		$na = self::normalize_address_for_match( $a );
		$nb = self::normalize_address_for_match( $b );
		if ( '' === $na || '' === $nb ) {
			return false;
		}
		if ( $na === $nb ) {
			return true;
		}
		$min = (int) min( strlen( $na ), strlen( $nb ) );
		if ( $min < 8 ) {
			return false;
		}
		similar_text( $na, $nb, $pct );
		return $pct >= 85.0;
	}

	/**
	 * Map internal source keys to portal-style labels for UI copy.
	 */
	private static function source_to_display_name( string $source ): string {
		$map = array(
			'rightmove'   => 'Rightmove',
			'zoopla'      => 'Zoopla',
			'onthemarket' => 'OnTheMarket',
		);
		if ( isset( $map[ $source ] ) ) {
			return $map[ $source ];
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', $source ) );
	}

	/**
	 * Restrict a WHERE clause to a broad NW bucket (M/L use regex so LA does not match L).
	 *
	 * @param string              $postcode_expr SQL expression, e.g. UPPER(TRIM(postcode)).
	 * @param string              $prefix        Bucket: M, L, PR, BB, etc.
	 * @param array<int, string>  $where         WHERE fragments (modified).
	 * @param array<int, mixed>   $args          prepare args (modified when placeholders used).
	 */
	public static function append_broad_nw_bucket_sql( string $postcode_expr, string $prefix, array &$where, array &$args ): void {
		$p = strtoupper( trim( sanitize_text_field( $prefix ) ) );
		if ( '' === $p || ! in_array( $p, LPNW_NW_POSTCODES, true ) ) {
			return;
		}
		if ( 'M' === $p ) {
			$where[] = "{$postcode_expr} REGEXP '^M[0-9]'";
			return;
		}
		if ( 'L' === $p ) {
			$where[] = "{$postcode_expr} REGEXP '^L[0-9]'";
			return;
		}
		$where[] = "{$postcode_expr} LIKE %s";
		$args[]  = $p . '%';
	}

	/**
	 * Restrict a WHERE clause to an NW bucket or a specific outward district (e.g. OL2, CH41).
	 *
	 * @param string              $postcode_expr SQL expression, e.g. UPPER(TRIM(postcode)).
	 * @param string              $prefix        Broad bucket or district outward code.
	 * @param array<int, string>  $where         WHERE fragments (modified).
	 * @param array<int, mixed>   $args          prepare args (modified when placeholders used).
	 */
	public static function append_postcode_prefix_sql( string $postcode_expr, string $prefix, array &$where, array &$args ): void {
		if ( class_exists( 'LPNW_NW_Postcodes' ) ) {
			LPNW_NW_Postcodes::append_area_filter_sql( $postcode_expr, $prefix, $where, $args );
			return;
		}
		self::append_broad_nw_bucket_sql( $postcode_expr, $prefix, $where, $args );
	}

	private static function clean_postcode( string $postcode ): string {
		$postcode = strtoupper( trim( $postcode ) );
		$clean    = preg_replace( '/[^A-Z0-9 ]/', '', $postcode );
		return is_string( $clean ) ? $clean : $postcode;
	}

	/**
	 * Decode raw_data and derive image URL plus off-market card fields for public templates.
	 *
	 * @param object $prop Property row from the database.
	 * @return array{raw: array<string, mixed>, image_url: string, is_off_market: bool, agent_contact: string, off_market_reason: string, contact_email: string, contact_tel_href: string}
	 */
	public static function get_card_context( object $prop ): array {
		$raw = json_decode( (string) ( $prop->raw_data ?? '' ), true );
		$raw = is_array( $raw ) ? $raw : array();

		$image_url = '';
		if ( ! empty( $raw['propertyImages']['images'][0]['srcUrl'] ) ) {
			$image_url = (string) $raw['propertyImages']['images'][0]['srcUrl'];
		} elseif ( ! empty( $raw['propertyImages']['mainImageSrc'] ) ) {
			$image_url = (string) $raw['propertyImages']['mainImageSrc'];
		} elseif ( ! empty( $raw['images'][0]['srcUrl'] ) ) {
			$image_url = (string) $raw['images'][0]['srcUrl'];
		}
		if ( '' === $image_url && ! empty( $raw['images'][0]['url'] ) ) {
			$image_url = (string) $raw['images'][0]['url'];
		}
		if ( '' === $image_url && ! empty( $raw['media'][0]['url'] ) ) {
			$image_url = (string) $raw['media'][0]['url'];
		}
		if ( '' === $image_url && ! empty( $raw['imageUrl'] ) ) {
			$image_url = (string) $raw['imageUrl'];
		}
		if ( '' === $image_url && ! empty( $raw['photos'][0] ) ) {
			$image_url = is_string( $raw['photos'][0] ) ? (string) $raw['photos'][0] : (string) ( $raw['photos'][0]['url'] ?? '' );
		}
		if ( '' === $image_url ) {
			$image_url = self::discover_image_url_in_raw( $raw );
		}

		$image_url = '' !== $image_url ? esc_url_raw( $image_url ) : '';

		$source        = sanitize_key( $prop->source ?? '' );
		$is_off_market = ( 'off_market' === $source );

		$agent_contact     = '';
		$off_market_reason = '';
		$contact_email     = '';
		$contact_tel_href  = '';

		if ( $is_off_market ) {
			$agent_contact     = sanitize_text_field( (string) ( $raw['agent_contact'] ?? '' ) );
			$off_market_reason = sanitize_textarea_field( (string) ( $raw['off_market_reason'] ?? '' ) );

			if ( '' !== $agent_contact ) {
				if ( is_email( $agent_contact ) ) {
					$contact_email = $agent_contact;
				} else {
					$digits_only = preg_replace( '/\D/', '', $agent_contact );
					if ( is_string( $digits_only ) && strlen( $digits_only ) >= 10 ) {
						$contact_tel_href = 'tel:' . $digits_only;
					}
				}
			}
		}

		return array(
			'raw'               => $raw,
			'image_url'         => $image_url,
			'is_off_market'     => $is_off_market,
			'agent_contact'     => $agent_contact,
			'off_market_reason' => $off_market_reason,
			'contact_email'     => $contact_email,
			'contact_tel_href'  => $contact_tel_href,
		);
	}

	/**
	 * Portal listing sources where first_listed_date comes from the portal (not our ingest time).
	 *
	 * @return array<int, string>
	 */
	public static function get_portal_property_sources(): array {
		return array( 'rightmove', 'zoopla', 'onthemarket' );
	}

	/**
	 * Whether the property row is a main portal listing (sales/rentals from site scrape).
	 *
	 * @param object $property Row from lpnw_properties.
	 */
	public static function is_portal_listing_row( object $property ): bool {
		$src = isset( $property->source ) ? sanitize_key( (string) $property->source ) : '';

		return in_array( $src, self::get_portal_property_sources(), true );
	}

	/**
	 * Recency for property cards: NEW / JUST LISTED badges follow portal first-listed date when present,
	 * so we do not mark a week-old Rightmove listing "new" just because we only ingested it today.
	 *
	 * Label text still prefers portal date; falls back to created_at when the portal did not give a date.
	 *
	 * @param object $property Row with source, first_listed_date, created_at.
	 * @return array{label: string, is_urgent: bool, is_new: bool}
	 */
	public static function get_card_listing_recency( object $property ): array {
		$first = isset( $property->first_listed_date ) ? trim( (string) $property->first_listed_date ) : '';
		$created = isset( $property->created_at ) ? trim( (string) $property->created_at ) : '';

		$label_date = '' !== $first ? $first : $created;

		if ( self::is_portal_listing_row( $property ) ) {
			if ( '' !== $first ) {
				return self::get_listed_label( $first );
			}

			if ( '' === $created ) {
				return array(
					'label'     => __( 'Listing date not supplied by portal', 'lpnw-alerts' ),
					'is_urgent' => false,
					'is_new'    => false,
				);
			}

			$sub = self::get_listed_label( $created );

			return array(
				'label'     => '' !== $sub['label']
					? sprintf(
						/* translators: %s: human time since we first ingested this listing (portal did not give a listed date). */
						__( 'First seen in LPNW: %s', 'lpnw-alerts' ),
						$sub['label']
					)
					: __( 'First seen in LPNW (portal did not publish a listed date)', 'lpnw-alerts' ),
				'is_urgent' => false,
				'is_new'    => false,
			);
		}

		if ( '' === $label_date ) {
			return array(
				'label'     => '',
				'is_urgent' => false,
				'is_new'    => false,
			);
		}

		return self::get_listed_label( $label_date );
	}

	/**
	 * Human-readable recency label from a date string (created_at or first_listed_date).
	 *
	 * Returns granular labels: "Just now", "X minutes ago", "X hours ago", "Listed today",
	 * "Listed yesterday", "Listed X days ago".
	 *
	 * @param string $date_string MySQL datetime or date string.
	 * @return array{label: string, is_urgent: bool, is_new: bool}
	 */
	public static function get_listed_label( string $date_string ): array {
		$result = array(
			'label'     => '',
			'is_urgent' => false,
			'is_new'    => false,
		);

		if ( '' === trim( $date_string ) ) {
			return $result;
		}

		$ts = strtotime( $date_string );
		if ( false === $ts ) {
			return $result;
		}

		$now  = time();
		$diff = $now - $ts;

		if ( $diff < 0 ) {
			return $result;
		}

		$result['is_new'] = $diff < ( 2 * DAY_IN_SECONDS );

		if ( $diff < 3600 ) {
			$mins = max( 1, (int) floor( $diff / 60 ) );
			if ( $mins < 5 ) {
				$result['label']     = __( 'Just listed', 'lpnw-alerts' );
			} else {
				$result['label'] = sprintf(
					/* translators: %d: number of minutes */
					_n( '%d minute ago', '%d minutes ago', $mins, 'lpnw-alerts' ),
					$mins
				);
			}
			$result['is_urgent'] = true;
			return $result;
		}

		if ( $diff < DAY_IN_SECONDS ) {
			$hours = max( 1, (int) floor( $diff / 3600 ) );
			$result['label'] = sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour ago', '%d hours ago', $hours, 'lpnw-alerts' ),
				$hours
			);
			$result['is_urgent'] = ( $hours <= 4 );
			return $result;
		}

		$tz        = wp_timezone();
		$listed_dt = date_create_immutable( wp_date( 'Y-m-d', $ts ), $tz );
		$today_dt  = date_create_immutable( current_time( 'Y-m-d' ), $tz );

		if ( ! $listed_dt || ! $today_dt || $listed_dt > $today_dt ) {
			return $result;
		}

		$cal_days = (int) $listed_dt->diff( $today_dt )->days;

		if ( 0 === $cal_days ) {
			$result['label']     = __( 'Listed today', 'lpnw-alerts' );
			$result['is_urgent'] = true;
		} elseif ( 1 === $cal_days ) {
			$result['label'] = __( 'Listed yesterday', 'lpnw-alerts' );
		} elseif ( $cal_days > 1 ) {
			$result['label'] = sprintf(
				/* translators: %d: number of days */
				_n( 'Listed %d day ago', 'Listed %d days ago', $cal_days, 'lpnw-alerts' ),
				$cal_days
			);
		}

		return $result;
	}

	/**
	 * Fallback when known keys are empty (e.g. Rightmove __NEXT_DATA__ shape drift).
	 *
	 * @param array<string, mixed> $raw Decoded raw_data.
	 */
	private static function discover_image_url_in_raw( array $raw ): string {
		$found = self::walk_raw_for_image_url( $raw, 0 );
		return is_string( $found ) ? $found : '';
	}

	/**
	 * Depth-limited walk for https URLs that look like listing photos.
	 *
	 * @param mixed $node Current node.
	 * @param int   $depth Recursion depth.
	 * @return string
	 */
	private static function walk_raw_for_image_url( $node, int $depth ): string {
		if ( $depth > 8 ) {
			return '';
		}
		if ( is_string( $node ) ) {
			$t = trim( $node );
			if ( '' === $t || ! self::is_likely_property_image_url( $t ) ) {
				return '';
			}
			return $t;
		}
		if ( ! is_array( $node ) ) {
			return '';
		}
		$prefer = array( 'propertyImages', 'images', 'photos', 'media', 'gallery', 'image', 'thumbnails' );
		foreach ( $prefer as $k ) {
			if ( isset( $node[ $k ] ) ) {
				$u = self::walk_raw_for_image_url( $node[ $k ], $depth + 1 );
				if ( '' !== $u ) {
					return $u;
				}
			}
		}
		foreach ( $node as $v ) {
			$u = self::walk_raw_for_image_url( $v, $depth + 1 );
			if ( '' !== $u ) {
				return $u;
			}
		}
		return '';
	}

	/**
	 * Avoid picking arbitrary external links (agent sites, etc.).
	 *
	 * @param string $url Candidate URL.
	 */
	private static function is_likely_property_image_url( string $url ): bool {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}
		$l = strtolower( $url );
		if ( preg_match( '#\.(jpe?g|png|gif|webp)(\?|#|$)#i', $url ) ) {
			return true;
		}
		return (bool) preg_match(
			'#(rightmove|rmmedia|zoopla|onthemarket|primelocation|mouseprice|stripcdn|cloudinary|epc\.|opendatacommunities|pugh|sdl|allsop|ahnw)#i',
			$l
		);
	}

	/**
	 * One-line caption after postcode: area names from bundled outcode data or geocoder metadata.
	 *
	 * @param object $prop Property row (postcode, raw_data).
	 */
	public static function format_postcode_caption( object $prop ): string {
		$pc = trim( (string) ( $prop->postcode ?? '' ) );
		if ( '' === $pc ) {
			return '';
		}
		$raw = json_decode( (string) ( $prop->raw_data ?? '' ), true );
		$raw = is_array( $raw ) ? $raw : array();
		$geo = isset( $raw['lpnw_geography'] ) && is_array( $raw['lpnw_geography'] ) ? $raw['lpnw_geography'] : array();

		$parts = array();
		if ( ! empty( $geo['parish'] ) && is_string( $geo['parish'] ) ) {
			$parts[] = trim( $geo['parish'] );
		}
		if ( ! empty( $geo['admin_ward'] ) && is_string( $geo['admin_ward'] ) ) {
			$w = trim( $geo['admin_ward'] );
			if ( '' !== $w && ! in_array( $w, $parts, true ) ) {
				$parts[] = $w;
			}
		}
		if ( ! empty( $geo['admin_district'] ) && is_string( $geo['admin_district'] ) ) {
			$d = trim( $geo['admin_district'] );
			if ( '' !== $d && ! in_array( $d, $parts, true ) ) {
				$parts[] = $d;
			}
		}

		if ( empty( $parts ) && class_exists( 'LPNW_Outcode_Labels' ) ) {
			$lbl = LPNW_Outcode_Labels::get_label_for_postcode( $pc );
			if ( '' !== $lbl ) {
				$parts[] = $lbl;
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		return implode( ', ', array_slice( $parts, 0, 3 ) );
	}

	/**
	 * Merge LPNW geography (from geocoder) into raw_data before upsert.
	 *
	 * @param array<string, mixed> $raw_data Decoded or empty.
	 * @param array<string, mixed> $geo      Keys: admin_district, admin_ward, parish, nuts, outcode (optional).
	 * @return array<string, mixed>
	 */
	public static function merge_geography_into_raw_data( array $raw_data, array $geo ): array {
		$keep = array();
		foreach ( array( 'admin_district', 'admin_ward', 'parish', 'nuts', 'outcode' ) as $k ) {
			if ( empty( $geo[ $k ] ) || ! is_string( $geo[ $k ] ) ) {
				continue;
			}
			$v = trim( $geo[ $k ] );
			if ( '' !== $v ) {
				$keep[ $k ] = sanitize_text_field( $v );
			}
		}
		if ( empty( $keep ) ) {
			return $raw_data;
		}
		$raw_data['lpnw_geography'] = $keep;
		return $raw_data;
	}
}
