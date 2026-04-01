<?php
/**
 * Alert matching engine.
 *
 * Compares new properties against subscriber preferences
 * and queues matching alerts.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Matcher {

	/**
	 * Process newly ingested properties and create alert queue entries.
	 *
	 * @param array<int> $property_ids IDs of newly inserted/updated properties.
	 * @return int Number of alerts queued.
	 */
	public function match_and_queue( array $property_ids ): int {
		if ( empty( $property_ids ) ) {
			return 0;
		}

		$subscribers = LPNW_Subscriber::get_active();
		$queued      = 0;

		foreach ( $property_ids as $property_id ) {
			$property = LPNW_Property::get( $property_id );
			if ( ! $property ) {
				continue;
			}

			foreach ( $subscribers as $subscriber ) {
				if ( $this->matches( $property, $subscriber ) ) {
					$tier = LPNW_Subscriber::get_tier( $subscriber->user_id );
					$this->queue_alert( $subscriber->id, $property_id, $tier );
					++$queued;
				}
			}
		}

		return $queued;
	}

	/**
	 * Check if a property matches a subscriber's preferences.
	 *
	 * @param object $property   Row from {@see LPNW_Property::get()}.
	 * @param object $subscriber Row shaped like lpnw_subscriber_preferences: areas, property_types,
	 *                           alert_types as JSON strings; min_price, max_price optional.
	 */
	public function property_matches_subscriber( object $property, object $subscriber ): bool {
		return $this->matches( $property, $subscriber );
	}

	/**
	 * Check if a property matches a subscriber's preferences.
	 */
	private function matches( object $property, object $subscriber ): bool {
		if ( ! $this->matches_area( $property, $subscriber ) ) {
			return false;
		}

		if ( ! $this->matches_price( $property, $subscriber ) ) {
			return false;
		}

		if ( ! $this->matches_type( $property, $subscriber ) ) {
			return false;
		}

		if ( ! $this->matches_alert_type( $property, $subscriber ) ) {
			return false;
		}

		return true;
	}

	private function matches_area( object $property, object $subscriber ): bool {
		$areas = json_decode( $subscriber->areas, true );
		if ( empty( $areas ) ) {
			return true;
		}

		$postcode = strtoupper( trim( $property->postcode ?? '' ) );
		if ( empty( $postcode ) ) {
			return false;
		}

		foreach ( $areas as $prefix ) {
			if ( str_starts_with( $postcode, strtoupper( $prefix ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function matches_price( object $property, object $subscriber ): bool {
		// Monthly rent shares the same price column as sale prices; do not apply sale filters to lets.
		$application_type = strtolower( trim( (string) ( $property->application_type ?? '' ) ) );
		if ( 'rent' === $application_type ) {
			return true;
		}

		$price = $property->price ?? null;
		if ( null === $price ) {
			return true;
		}

		if ( $subscriber->min_price && $price < $subscriber->min_price ) {
			return false;
		}

		if ( $subscriber->max_price && $price > $subscriber->max_price ) {
			return false;
		}

		return true;
	}

	private function matches_type( object $property, object $subscriber ): bool {
		$types = json_decode( $subscriber->property_types, true );
		if ( empty( $types ) ) {
			return true;
		}

		$raw = trim( (string) ( $property->property_type ?? '' ) );
		if ( '' === $raw ) {
			return false;
		}

		$mapped = $this->map_portal_property_type_to_canonical( $raw );
		$raw_lc = strtolower( $raw );

		foreach ( $types as $pref ) {
			$pref    = (string) $pref;
			$pref_lc = strtolower( $pref );

			foreach ( $mapped as $canon ) {
				if ( strtolower( $canon ) === $pref_lc ) {
					return true;
				}
			}

			// Case-insensitive partial match between portal text and preference label.
			if ( str_contains( $raw_lc, $pref_lc ) || str_contains( $pref_lc, $raw_lc ) ) {
				return true;
			}

			// Preference value is "Other" (label Other/Land); match common portal synonyms.
			if ( 'other' === $pref_lc ) {
				if ( preg_match( '/\b(land|plot|farm|commercial|development|site)\b/i', $raw ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Map portal property type strings (e.g. Rightmove) to canonical preference checkbox values.
	 *
	 * @param string $raw Raw property type from the feed.
	 * @return array<int, string> Canonical values: Detached, Semi-detached, Terraced, Flat/Maisonette, Auction lot, Other.
	 */
	private function map_portal_property_type_to_canonical( string $raw ): array {
		$n = strtolower( trim( $raw ) );
		if ( '' === $n ) {
			return array();
		}

		if ( str_contains( $n, 'auction' ) ) {
			return array( 'Auction lot' );
		}

		if ( str_contains( $n, 'flat' ) || str_contains( $n, 'apartment' ) || str_contains( $n, 'maisonette' )
			|| str_contains( $n, 'penthouse' ) || str_contains( $n, 'studio' ) ) {
			return array( 'Flat/Maisonette' );
		}

		if ( ( str_contains( $n, 'semi' ) && str_contains( $n, 'detached' ) )
			|| str_contains( $n, 'semi-detached' ) || str_contains( $n, 'semi detached' ) ) {
			return array( 'Semi-detached' );
		}

		if ( str_contains( $n, 'terraced' ) || str_contains( $n, 'terrace' )
			|| str_contains( $n, 'end of terrace' ) || str_contains( $n, 'townhouse' )
			|| str_contains( $n, 'town house' ) || str_contains( $n, 'mews' ) ) {
			return array( 'Terraced' );
		}

		if ( str_contains( $n, 'detached' ) && ! str_contains( $n, 'semi' ) ) {
			return array( 'Detached' );
		}

		if ( str_contains( $n, 'bungalow' ) || str_contains( $n, 'house share' )
			|| str_contains( $n, 'hmo' ) || str_contains( $n, 'park home' )
			|| str_contains( $n, 'mobile home' ) || str_contains( $n, 'cottage' )
			|| str_contains( $n, 'character property' ) ) {
			return array( 'Other' );
		}

		return array();
	}

	private function matches_alert_type( object $property, object $subscriber ): bool {
		$alert_types = json_decode( $subscriber->alert_types, true );
		if ( empty( $alert_types ) ) {
			return true;
		}

		$source_map = array(
			'planning'     => 'planning',
			'epc'          => 'epc',
			'landregistry' => 'price',
			'auction'      => 'auction',
			'rightmove'    => 'listing',
			'zoopla'       => 'listing',
			'onthemarket'  => 'listing',
		);

		$source = $property->source ?? '';
		foreach ( $source_map as $source_prefix => $alert_type ) {
			if ( str_starts_with( $source, $source_prefix ) && in_array( $alert_type, $alert_types, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function queue_alert( int $subscriber_id, int $property_id, string $tier ): void {
		global $wpdb;

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}lpnw_alert_queue WHERE subscriber_id = %d AND property_id = %d",
			$subscriber_id,
			$property_id
		) );

		if ( $existing ) {
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'lpnw_alert_queue',
			array(
				'subscriber_id' => $subscriber_id,
				'property_id'   => $property_id,
				'tier'          => $tier,
				'status'        => 'queued',
			)
		);
	}
}
