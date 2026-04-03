<?php
/**
 * Human-readable labels for NW outward postcode districts (bundled dataset).
 *
 * Data derived from postcodes.io outcode metadata (admin districts / parishes).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Outcode_Labels {

	/**
	 * Manual labels for outcodes with no API row (or rare codes).
	 *
	 * @var array<string, string>
	 */
	private static array $fallback_labels = array(
		'BB0' => 'Blackburn area',
		'FY0' => 'Blackpool area',
		'L73' => 'Liverpool',
		'M90' => 'Manchester Airport',
	);

	/**
	 * @var array<string, string>|null
	 */
	private static ?array $map = null;

	/**
	 * @return array<string, string> Outward code => short place label.
	 */
	private static function load_map(): array {
		if ( null !== self::$map ) {
			return self::$map;
		}

		$path = LPNW_PLUGIN_DIR . 'data/lpnw-outcode-labels.json';
		if ( ! is_readable( $path ) ) {
			self::$map = array();
			return self::$map;
		}

		$json = file_get_contents( $path );
		if ( false === $json ) {
			self::$map = array();
			return self::$map;
		}

		$decoded = json_decode( $json, true );
		self::$map = is_array( $decoded ) ? $decoded : array();

		return self::$map;
	}

	/**
	 * Place label for an outward code (e.g. OL2), or empty string.
	 */
	public static function get_label_for_outcode( string $outcode ): string {
		$o = strtoupper( trim( $outcode ) );
		if ( '' === $o ) {
			return '';
		}
		if ( isset( self::$fallback_labels[ $o ] ) ) {
			return self::$fallback_labels[ $o ];
		}
		$map = self::load_map();
		return isset( $map[ $o ] ) ? (string) $map[ $o ] : '';
	}

	/**
	 * Label from a full postcode string.
	 */
	public static function get_label_for_postcode( string $postcode ): string {
		if ( ! class_exists( 'LPNW_NW_Postcodes' ) ) {
			return '';
		}
		$out = LPNW_NW_Postcodes::extract_outward_code( $postcode );
		return self::get_label_for_outcode( $out );
	}
}
