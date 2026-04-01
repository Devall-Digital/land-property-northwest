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

		return in_array( $property->property_type, $types, true );
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
