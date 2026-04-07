<?php
/**
 * Suppresses listing alerts for portal properties whose published listed date is stale.
 *
 * Ingestion is "new to our database" when a row is first inserted; Rightmove may show
 * firstVisibleDate from weeks or months ago. Skipping the matcher for those rows avoids
 * emails that feel like false positives while still storing the property for browse/map.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Portal_Listing_Alert_Gate {

	/**
	 * Default: do not email listing alerts when the portal's first-listed date is older than this many whole calendar days.
	 */
	private const DEFAULT_MAX_LISTED_AGE_DAYS = 14;

	/**
	 * Whether to skip match_and_queue for this property (all subscribers).
	 *
	 * Non-portal sources are never suppressed. When the portal omits a listed date, we do not suppress.
	 * Filter {@see 'lpnw_portal_listing_alert_max_listed_age_days'}: return 0 or a negative value to disable the gate.
	 *
	 * @param object $property Row from {@see LPNW_Property::get()}.
	 */
	public static function should_suppress_listing_alerts( object $property ): bool {
		if ( ! LPNW_Property::is_portal_listing_row( $property ) ) {
			return false;
		}

		$max_age = (int) apply_filters(
			'lpnw_portal_listing_alert_max_listed_age_days',
			self::DEFAULT_MAX_LISTED_AGE_DAYS
		);

		if ( $max_age <= 0 ) {
			return false;
		}

		$first = isset( $property->first_listed_date ) ? trim( (string) $property->first_listed_date ) : '';
		if ( '' === $first ) {
			return false;
		}

		$tz = wp_timezone();

		$listed_day = \DateTimeImmutable::createFromFormat( 'Y-m-d', $first, $tz );
		if ( ! $listed_day instanceof \DateTimeImmutable ) {
			$ts = strtotime( $first );
			if ( false === $ts ) {
				return false;
			}
			$listed_day = ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz )->setTime( 0, 0, 0 );
		} else {
			$listed_day = $listed_day->setTime( 0, 0, 0 );
		}

		$today = ( new \DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0, 0 );

		if ( $listed_day > $today ) {
			return false;
		}

		$days_old = (int) $listed_day->diff( $today )->days;

		return $days_old > $max_age;
	}
}
