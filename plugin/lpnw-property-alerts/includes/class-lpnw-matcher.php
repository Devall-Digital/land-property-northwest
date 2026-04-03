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
		$tier_cache  = array();

		foreach ( $property_ids as $property_id ) {
			$property = LPNW_Property::get( $property_id );
			if ( ! $property ) {
				continue;
			}

			foreach ( $subscribers as $subscriber ) {
				if ( $this->matches( $property, $subscriber ) ) {
					$uid = (int) $subscriber->user_id;
					if ( ! isset( $tier_cache[ $uid ] ) ) {
						$tier_cache[ $uid ] = LPNW_Subscriber::get_tier( $uid );
					}
					$this->queue_alert( $subscriber->id, $property_id, $tier_cache[ $uid ] );
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
	 *                           alert_types, listing_channels, tenure_preferences, required_features as JSON strings
	 *                           (or decoded arrays when from {@see LPNW_Subscriber::get_preferences()});
	 *                           min_price, max_price, min_bedrooms, max_bedrooms optional.
	 */
	public function property_matches_subscriber( object $property, object $subscriber ): bool {
		return $this->matches( $property, $subscriber );
	}

	/**
	 * Check if a property matches a subscriber's preferences.
	 */
	private function matches( object $property, object $subscriber ): bool {
		$src = trim( (string) ( $property->source ?? '' ) );
		if ( 'off_market' === $src ) {
			$uid = isset( $subscriber->user_id ) ? (int) $subscriber->user_id : 0;
			if ( $uid <= 0 || 'vip' !== LPNW_Subscriber::get_tier( $uid ) ) {
				return false;
			}
		}

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

		if ( ! $this->matches_bedrooms( $property, $subscriber ) ) {
			return false;
		}

		if ( ! $this->matches_channel( $property, $subscriber ) ) {
			return false;
		}

		if ( ! $this->matches_tenure( $property, $subscriber ) ) {
			return false;
		}

		if ( ! $this->matches_features( $property, $subscriber ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Decode a subscriber column that may be a JSON string (raw DB row) or an array (get_preferences).
	 *
	 * @param mixed $value Stored value.
	 * @return array<int|string, mixed>
	 */
	private function decode_subscriber_json_array( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	/**
	 * Match bedroom count when both subscriber bounds and property data exist.
	 *
	 * @param object $property   Property row.
	 * @param object $subscriber Preferences row.
	 * @return bool
	 */
	private function matches_bedrooms( object $property, object $subscriber ): bool {
		$prop_beds = $property->bedrooms ?? null;
		if ( null === $prop_beds || '' === $prop_beds ) {
			return true;
		}

		$prop_beds = (int) $prop_beds;

		$min = $subscriber->min_bedrooms ?? null;
		if ( null !== $min && '' !== $min ) {
			$min = (int) $min;
			if ( $prop_beds < $min ) {
				return false;
			}
		}

		$max = $subscriber->max_bedrooms ?? null;
		if ( null !== $max && '' !== $max ) {
			$max = (int) $max;
			if ( $prop_beds > $max ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Match listing channel (sale/rent) when subscriber restricts channels.
	 *
	 * @param object $property   Property row.
	 * @param object $subscriber Preferences row.
	 * @return bool
	 */
	private function matches_channel( object $property, object $subscriber ): bool {
		$channels = $this->decode_subscriber_json_array( $subscriber->listing_channels ?? null );
		if ( empty( $channels ) ) {
			return true;
		}

		$app = strtolower( trim( (string) ( $property->application_type ?? '' ) ) );
		$allowed = array();
		foreach ( $channels as $ch ) {
			$allowed[] = strtolower( trim( (string) $ch ) );
		}
		$allowed = array_filter( $allowed );

		if ( empty( $allowed ) ) {
			return true;
		}

		return in_array( $app, $allowed, true );
	}

	/**
	 * Match tenure when subscriber restricts tenure; unknown property tenure passes.
	 *
	 * @param object $property   Property row.
	 * @param object $subscriber Preferences row.
	 * @return bool
	 */
	private function matches_tenure( object $property, object $subscriber ): bool {
		$prefs = $this->decode_subscriber_json_array( $subscriber->tenure_preferences ?? null );
		if ( empty( $prefs ) ) {
			return true;
		}

		$tenure = trim( (string) ( $property->tenure_type ?? '' ) );
		if ( '' === $tenure ) {
			return true;
		}

		$tenure_cmp = strtolower( str_replace( '_', ' ', $tenure ) );
		foreach ( $prefs as $pref ) {
			$pref_cmp = strtolower( trim( str_replace( '_', ' ', (string) $pref ) ) );
			if ( $pref_cmp === $tenure_cmp ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match required feature substrings against pipe-delimited key_features_text.
	 *
	 * @param object $property   Property row.
	 * @param object $subscriber Preferences row.
	 * @return bool
	 */
	private function matches_features( object $property, object $subscriber ): bool {
		$required = $this->decode_subscriber_json_array( $subscriber->required_features ?? null );
		if ( empty( $required ) ) {
			return true;
		}

		$haystack = trim( (string) ( $property->key_features_text ?? '' ) );
		if ( '' === $haystack ) {
			return false;
		}

		$haystack_lc = strtolower( $haystack );
		foreach ( $required as $feat ) {
			$needle = strtolower( trim( (string) $feat ) );
			if ( '' === $needle ) {
				continue;
			}
			if ( false === strpos( $haystack_lc, $needle ) ) {
				return false;
			}
		}

		return true;
	}

	private function matches_area( object $property, object $subscriber ): bool {
		$raw_areas = $subscriber->areas ?? null;
		$areas     = is_array( $raw_areas )
			? $raw_areas
			: ( is_string( $raw_areas ) ? ( json_decode( $raw_areas, true ) ?: array() ) : array() );
		if ( empty( $areas ) ) {
			return true;
		}

		$postcode = strtoupper( trim( $property->postcode ?? '' ) );
		if ( empty( $postcode ) ) {
			return false;
		}

		$outward = '';
		if ( preg_match( '/^([A-Z]{1,2}[0-9][0-9A-Z]?)\s/i', $postcode, $m ) ) {
			$outward = strtoupper( $m[1] );
		} else {
			$outward = strtoupper( preg_replace( '/\s.*$/', '', $postcode ) );
		}

		if ( '' === $outward ) {
			return false;
		}

		$area_letter = preg_replace( '/[0-9].*$/', '', $outward );

		foreach ( $areas as $pref ) {
			$pref = strtoupper( trim( $pref ) );
			if ( '' === $pref ) {
				continue;
			}
			if ( $pref === $outward ) {
				return true;
			}
			if ( $pref === $area_letter ) {
				return true;
			}
		}

		return false;
	}

	private function matches_price( object $property, object $subscriber ): bool {
		$price = $property->price ?? null;
		if ( null === $price || '' === $price ) {
			return true;
		}

		$price = (int) $price;
		$min   = isset( $subscriber->min_price ) ? (int) $subscriber->min_price : 0;
		$max   = isset( $subscriber->max_price ) ? (int) $subscriber->max_price : 0;

		// Sale and rent both use the same price column (PCM for lets); apply min/max in both cases.
		if ( $min > 0 && $price < $min ) {
			return false;
		}

		if ( $max > 0 && $price > $max ) {
			return false;
		}

		return true;
	}

	private function matches_type( object $property, object $subscriber ): bool {
		$raw_types = $subscriber->property_types ?? null;
		$types     = is_array( $raw_types )
			? $raw_types
			: ( is_string( $raw_types ) ? ( json_decode( $raw_types, true ) ?: array() ) : array() );
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
		$raw_alert_types = $subscriber->alert_types ?? null;
		$alert_types     = is_array( $raw_alert_types )
			? $raw_alert_types
			: ( is_string( $raw_alert_types ) ? ( json_decode( $raw_alert_types, true ) ?: array() ) : array() );

		$source = (string) ( $property->source ?? '' );
		if ( str_starts_with( $source, 'off_market' ) ) {
			return ! empty( $alert_types ) && in_array( 'off_market', $alert_types, true );
		}

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
			'off_market'   => 'off_market',
		);

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

		$result = $wpdb->insert(
			$wpdb->prefix . 'lpnw_alert_queue',
			array(
				'subscriber_id' => $subscriber_id,
				'property_id'   => $property_id,
				'tier'          => $tier,
				'status'        => 'queued',
			)
		);

		if ( false === $result ) {
			error_log(
				sprintf(
					'LPNW Matcher: failed to queue alert for subscriber %d, property %d: %s',
					$subscriber_id,
					$property_id,
					$wpdb->last_error
				)
			);
		}
	}
}
