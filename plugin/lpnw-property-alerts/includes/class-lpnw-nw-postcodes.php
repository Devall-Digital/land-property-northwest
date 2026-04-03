<?php
/**
 * Northwest England postcode districts for subscriber areas and filters.
 *
 * Broad buckets (M, L, OL, …) match any property in that bucket. District codes
 * (OL2, M40, CH41, …) match only that outward postcode area.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_NW_Postcodes {

	/**
	 * District outward codes grouped by the same bucket as LPNW_NW_POSTCODES.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function get_districts_by_area(): array {
		return array(
			'M'  => array( 'M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8', 'M9', 'M11', 'M12', 'M13', 'M14', 'M15', 'M16', 'M17', 'M18', 'M19', 'M20', 'M21', 'M22', 'M23', 'M24', 'M25', 'M26', 'M27', 'M28', 'M29', 'M30', 'M31', 'M32', 'M33', 'M34', 'M35', 'M38', 'M40', 'M41', 'M43', 'M44', 'M45', 'M46', 'M50', 'M60', 'M90' ),
			'BL' => array( 'BL0', 'BL1', 'BL2', 'BL3', 'BL4', 'BL5', 'BL6', 'BL7', 'BL8', 'BL9', 'BL11' ),
			'OL' => array( 'OL1', 'OL2', 'OL3', 'OL4', 'OL5', 'OL6', 'OL7', 'OL8', 'OL9', 'OL10', 'OL11', 'OL12', 'OL13', 'OL14', 'OL15', 'OL16' ),
			'SK' => array( 'SK1', 'SK2', 'SK3', 'SK4', 'SK5', 'SK6', 'SK7', 'SK8', 'SK9', 'SK10', 'SK11', 'SK12', 'SK13', 'SK14', 'SK15', 'SK16', 'SK17', 'SK22', 'SK23' ),
			'WA' => array( 'WA1', 'WA2', 'WA3', 'WA4', 'WA5', 'WA6', 'WA7', 'WA8', 'WA9', 'WA10', 'WA11', 'WA12', 'WA13', 'WA14', 'WA15', 'WA16' ),
			'WN' => array( 'WN1', 'WN2', 'WN3', 'WN4', 'WN5', 'WN6', 'WN7', 'WN8' ),
			'L'  => array( 'L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8', 'L9', 'L10', 'L11', 'L12', 'L13', 'L14', 'L15', 'L16', 'L17', 'L18', 'L19', 'L20', 'L21', 'L22', 'L23', 'L24', 'L25', 'L26', 'L27', 'L28', 'L29', 'L30', 'L31', 'L32', 'L33', 'L34', 'L35', 'L36', 'L37', 'L38', 'L39', 'L40', 'L67', 'L68', 'L69', 'L70', 'L71', 'L72', 'L73', 'L74', 'L75' ),
			'CH' => array( 'CH1', 'CH2', 'CH3', 'CH4', 'CH5', 'CH6', 'CH7', 'CH41', 'CH42', 'CH43', 'CH44', 'CH45', 'CH46', 'CH47', 'CH48', 'CH49', 'CH60', 'CH61', 'CH62', 'CH63', 'CH64', 'CH65', 'CH66' ),
			'CW' => array( 'CW1', 'CW2', 'CW3', 'CW4', 'CW5', 'CW6', 'CW7', 'CW8', 'CW9', 'CW10', 'CW11', 'CW12' ),
			'PR' => array( 'PR0', 'PR1', 'PR2', 'PR3', 'PR4', 'PR5', 'PR6', 'PR7', 'PR8', 'PR9', 'PR25', 'PR26' ),
			'BB' => array( 'BB0', 'BB1', 'BB2', 'BB3', 'BB4', 'BB5', 'BB6', 'BB7', 'BB8', 'BB9', 'BB10', 'BB11', 'BB12', 'BB18' ),
			'FY' => array( 'FY0', 'FY1', 'FY2', 'FY3', 'FY4', 'FY5', 'FY6', 'FY7', 'FY8' ),
			'LA' => array( 'LA1', 'LA2', 'LA3', 'LA4', 'LA5', 'LA6', 'LA7', 'LA8', 'LA9', 'LA10', 'LA11', 'LA12', 'LA13', 'LA14', 'LA15', 'LA16', 'LA17', 'LA18', 'LA19', 'LA20', 'LA21', 'LA22', 'LA23' ),
			'CA' => array( 'CA1', 'CA2', 'CA3', 'CA4', 'CA5', 'CA6', 'CA7', 'CA8', 'CA9', 'CA10', 'CA11', 'CA12', 'CA13', 'CA14', 'CA15', 'CA16', 'CA17', 'CA18', 'CA19', 'CA20', 'CA22', 'CA25', 'CA26', 'CA27', 'CA28' ),
		);
	}

	/**
	 * Every allowed outward code for preferences and filters (broad + districts), unique, sorted.
	 *
	 * @return array<int, string>
	 */
	public static function get_all_selectable_codes(): array {
		$out = array_merge( LPNW_NW_POSTCODES, self::get_all_districts_flat() );
		$out = array_values( array_unique( array_map( 'strval', $out ) ) );
		sort( $out, SORT_STRING );
		return $out;
	}

	/**
	 * @return array<int, string>
	 */
	public static function get_all_districts_flat(): array {
		$flat = array();
		foreach ( self::get_districts_by_area() as $districts ) {
			foreach ( $districts as $d ) {
				$flat[] = strtoupper( $d );
			}
		}
		return $flat;
	}

	/**
	 * Whether this string is a broad NW bucket or a known district outward code.
	 */
	public static function is_valid_area_or_district( string $code ): bool {
		$c = strtoupper( trim( $code ) );
		if ( '' === $c ) {
			return false;
		}
		if ( in_array( $c, LPNW_NW_POSTCODES, true ) ) {
			return true;
		}
		return in_array( $c, self::get_all_districts_flat(), true );
	}

	/**
	 * Sanitize a list of area codes from user input (preferences, URL).
	 *
	 * @param array<int, mixed> $raw Raw values.
	 * @return array<int, string>
	 */
	public static function sanitize_areas_array( array $raw ): array {
		$out = array();
		foreach ( $raw as $item ) {
			$c = strtoupper( trim( sanitize_text_field( (string) $item ) ) );
			if ( self::is_valid_area_or_district( $c ) ) {
				$out[] = $c;
			}
		}
		$out = array_values( array_unique( $out ) );
		sort( $out, SORT_STRING );
		return $out;
	}

	/**
	 * Extract UK outward code from a full postcode (uppercase).
	 */
	public static function extract_outward_code( string $postcode ): string {
		$postcode = strtoupper( trim( $postcode ) );
		if ( '' === $postcode ) {
			return '';
		}
		if ( preg_match( '/^([A-Z]{1,2}[0-9][0-9A-Z]?)\s/', $postcode, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/^([A-Z]{1,2}[0-9][0-9A-Z]?)$/', $postcode, $m ) ) {
			return $m[1];
		}
		return strtoupper( preg_replace( '/\s.*$/', '', $postcode ) );
	}

	/**
	 * Broad NW bucket for an outward code (M, L, OL, …) or empty if not in coverage.
	 */
	public static function get_bucket_for_outward( string $outward ): string {
		$outward = strtoupper( trim( $outward ) );
		if ( '' === $outward ) {
			return '';
		}
		foreach ( self::get_districts_by_area() as $bucket => $districts ) {
			foreach ( $districts as $d ) {
				if ( strtoupper( $d ) === $outward ) {
					return $bucket;
				}
			}
		}
		if ( preg_match( '/^M[0-9]/', $outward ) ) {
			return 'M';
		}
		if ( preg_match( '/^L[0-9]/', $outward ) ) {
			return 'L';
		}
		foreach ( LPNW_NW_POSTCODES as $prefix ) {
			$prefix = (string) $prefix;
			if ( strlen( $prefix ) >= 2 && str_starts_with( $outward, $prefix ) ) {
				$next = strlen( $outward ) > strlen( $prefix ) ? $outward[ strlen( $prefix ) ] : '';
				if ( '' === $next || ctype_digit( $next ) ) {
					return $prefix;
				}
			}
		}
		return '';
	}

	/**
	 * True if the property postcode falls within at least one selected area or district.
	 *
	 * @param array<int, string> $selected Saved subscriber areas (broad and/or district codes).
	 */
	public static function postcode_matches_selected_areas( string $property_postcode, array $selected ): bool {
		if ( empty( $selected ) ) {
			return true;
		}
		$outward = self::extract_outward_code( $property_postcode );
		if ( '' === $outward ) {
			return false;
		}
		$bucket = self::get_bucket_for_outward( $outward );
		foreach ( $selected as $pref ) {
			$pref = strtoupper( trim( $pref ) );
			if ( '' === $pref ) {
				continue;
			}
			if ( $pref === $outward ) {
				return true;
			}
			if ( $pref === $bucket && '' !== $bucket ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * SQL fragment: property postcode matches this broad bucket or exact outward district.
	 *
	 * @param string              $postcode_expr e.g. UPPER(TRIM(postcode)).
	 * @param string              $code          Broad (M, OL) or district (OL2).
	 * @param array<int, string>  $where         WHERE fragments.
	 * @param array<int, mixed>   $args          prepare args.
	 */
	public static function append_area_filter_sql( string $postcode_expr, string $code, array &$where, array &$args ): void {
		$c = strtoupper( trim( sanitize_text_field( $code ) ) );
		if ( '' === $c || ! self::is_valid_area_or_district( $c ) ) {
			return;
		}
		if ( in_array( $c, LPNW_NW_POSTCODES, true ) ) {
			LPNW_Property::append_broad_nw_bucket_sql( $postcode_expr, $c, $where, $args );
			return;
		}
		self::append_exact_outward_sql( $postcode_expr, $c, $where, $args );
	}

	/**
	 * Match only properties in this outward district (not OL20 when filtering OL2).
	 *
	 * @param string              $postcode_expr SQL expression.
	 * @param string              $outward       Normalised outward, e.g. OL2.
	 * @param array<int, string>  $where         WHERE fragments.
	 * @param array<int, mixed>   $args          prepare args.
	 */
	public static function append_exact_outward_sql( string $postcode_expr, string $outward, array &$where, array &$args ): void {
		$o = strtoupper( trim( $outward ) );
		if ( '' === $o || ! preg_match( '/^[A-Z]{1,2}[0-9][0-9A-Z]?$/', $o ) ) {
			return;
		}
		$where[] = "( {$postcode_expr} LIKE %s OR {$postcode_expr} LIKE %s )";
		$args[]  = $o . ' %';
		$args[]  = $o;
	}
}
