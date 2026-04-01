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

		$placeholders = implode( ',', array_fill( 0, count( $other_sources ), '%s' ) );
		$args         = array_merge( $other_sources, array( $postcode, $price ) );

		$match = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table}
			 WHERE source IN ({$placeholders})
			 AND postcode = %s
			 AND price = %d
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
