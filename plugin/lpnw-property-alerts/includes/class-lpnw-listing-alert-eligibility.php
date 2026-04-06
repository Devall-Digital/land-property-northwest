<?php
/**
 * Whether a portal listing should generate subscriber alerts.
 *
 * Crawls are batched (shared hosting limits), so we often discover listings days
 * after the portal's own "first listed" date. Alerts should not claim recency
 * beyond a configurable portal-age threshold when that date is known.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Listing_Alert_Eligibility {

	/**
	 * Sources treated as portal listings for age checks (prefix match on source column).
	 *
	 * @var array<int, string>
	 */
	private const LISTING_SOURCE_PREFIXES = array(
		'rightmove',
		'zoopla',
		'onthemarket',
	);

	/**
	 * Whether this property row should be queued for instant / digest alerts.
	 *
	 * Non-portal sources always return true. For listing portals, when
	 * first_listed_date is set and max age is positive, listings older than
	 * that many calendar days (vs site timezone "today") are skipped.
	 *
	 * @param object $property Row from {@see LPNW_Property::get()}.
	 */
	public static function should_queue_alerts( object $property ): bool {
		if ( ! self::is_listing_portal_source( (string) ( $property->source ?? '' ) ) ) {
			return true;
		}

		$settings = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$max_days = isset( $settings['listing_alert_max_portal_age_days'] )
			? absint( $settings['listing_alert_max_portal_age_days'] )
			: 2;

		if ( $max_days <= 0 ) {
			return true;
		}

		$portal_day = self::parse_first_listed_date( $property->first_listed_date ?? null );
		if ( null === $portal_day ) {
			return true;
		}

		$tz    = wp_timezone();
		$today = new \DateTimeImmutable( 'today', $tz );

		if ( $portal_day > $today ) {
			return true;
		}

		$age_days = (int) $portal_day->diff( $today )->days;

		return $age_days <= $max_days;
	}

	/**
	 * @param string $source Property source column.
	 */
	private static function is_listing_portal_source( string $source ): bool {
		$source = trim( $source );
		if ( '' === $source ) {
			return false;
		}

		foreach ( self::LISTING_SOURCE_PREFIXES as $prefix ) {
			if ( str_starts_with( $source, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed $value Stored DATE or Y-m-d string.
	 */
	private static function parse_first_listed_date( $value ): ?\DateTimeImmutable {
		if ( null === $value ) {
			return null;
		}

		$s = trim( (string) $value );
		if ( '' === $s ) {
			return null;
		}

		$tz = wp_timezone();

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $s, $tz );
		if ( $dt instanceof \DateTimeImmutable ) {
			return $dt;
		}

		$ts = strtotime( $s );
		if ( false === $ts ) {
			return null;
		}

		return ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz );
	}
}
