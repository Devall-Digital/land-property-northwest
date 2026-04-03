<?php
/**
 * Abstract base class for all data feeds.
 *
 * Each feed (planning, EPC, Land Registry, auctions) extends this
 * and implements fetch() and parse().
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

abstract class LPNW_Feed_Base {

	protected int $log_id = 0;

	abstract protected function fetch(): array;

	/**
	 * @param array<string, mixed> $raw_item Single raw item from the source.
	 * @return array<string, mixed> Normalised property data.
	 */
	abstract protected function parse( array $raw_item ): array;

	abstract public function get_source_name(): string;

	/**
	 * Run the full feed cycle: log start, fetch, parse, upsert, match, log end.
	 */
	public function run(): void {
		$start_time = microtime( true );
		$this->log_start();

		try {
			$raw_items = $this->fetch();

			$new_ids         = array();
			$ids_for_matcher = array();
			$upsert_ok       = 0;
			$errors          = array();

			foreach ( $raw_items as $raw_item ) {
				try {
					$parsed = $this->parse( $raw_item );

					if ( empty( $parsed ) ) {
						continue;
					}

					if ( empty( $parsed['source'] ) ) {
						$parsed['source'] = $this->get_source_name();
					}

					if ( ! empty( $parsed['postcode'] ) ) {
						$raw_geo = array();
						if ( ! empty( $parsed['raw_data'] ) ) {
							if ( is_array( $parsed['raw_data'] ) ) {
								$raw_geo = $parsed['raw_data'];
							} else {
								$raw_geo = json_decode( (string) $parsed['raw_data'], true );
								$raw_geo = is_array( $raw_geo ) ? $raw_geo : array();
							}
						}
						$need_geo = empty( $raw_geo['lpnw_geography'] ) || ! is_array( $raw_geo['lpnw_geography'] );
						$need_lat = empty( $parsed['latitude'] );
						if ( $need_geo || $need_lat ) {
							$coords = LPNW_Geocoder::geocode( (string) $parsed['postcode'] );
							if ( $coords ) {
								if ( $need_lat ) {
									$parsed['latitude']  = $coords['latitude'];
									$parsed['longitude'] = $coords['longitude'];
								}
								if ( $need_geo ) {
									$parsed['raw_data'] = LPNW_Property::merge_geography_into_raw_data( $raw_geo, $coords );
								}
							}
						}
					}

					$postcode_trimmed = isset( $parsed['postcode'] ) ? trim( (string) $parsed['postcode'] ) : '';
					if ( '' === $postcode_trimmed && empty( $parsed['latitude'] ) && empty( $parsed['longitude'] ) ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational feed diagnostics.
						error_log(
							sprintf(
								'LPNW feed base (%s): skipped item with no postcode and no coordinates.',
								$this->get_source_name()
							)
						);
						continue;
					}

					$inserted_new = false;
					$property_id  = LPNW_Property::upsert( $parsed, $inserted_new );

					if ( $property_id ) {
						++$upsert_ok;
						if ( $inserted_new ) {
							$new_ids[]         = $property_id;
							$ids_for_matcher[] = $property_id;
						}
					}
				} catch ( \Throwable $e ) {
					$errors[] = $e->getMessage();
				}
			}

			if ( ! empty( $ids_for_matcher ) ) {
				$matcher = new LPNW_Matcher();
				$matcher->match_and_queue( $ids_for_matcher );
			}

			$elapsed = round( microtime( true ) - $start_time, 1 );
			$this->log_end( count( $raw_items ), count( $new_ids ), $upsert_ok, $errors, 'completed', $elapsed );
		} catch ( \Throwable $e ) {
			$elapsed = round( microtime( true ) - $start_time, 1 );
			$this->log_end( 0, 0, 0, array( $e->getMessage() ), 'failed', $elapsed );
		}
	}

	/**
	 * Check if a postcode falls within the NW England area.
	 */
	protected function is_nw_postcode( string $postcode ): bool {
		$postcode = strtoupper( trim( $postcode ) );
		if ( empty( $postcode ) ) {
			return false;
		}

		foreach ( LPNW_NW_POSTCODES as $prefix ) {
			if ( str_starts_with( $postcode, $prefix ) ) {
				$next_char = substr( $postcode, strlen( $prefix ), 1 );
				if ( is_numeric( $next_char ) || ' ' === $next_char ) {
					return true;
				}
			}
		}

		return false;
	}

	private function log_start(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'lpnw_feed_log',
			array(
				'feed_name' => $this->get_source_name(),
				'status'    => 'running',
			)
		);

		$this->log_id = (int) $wpdb->insert_id;
	}

	/**
	 * Persist feed run summary to lpnw_feed_log.
	 *
	 * @param int           $found            Raw items from fetch().
	 * @param int           $new_count        New property rows (first insert) this run.
	 * @param int           $updated          Successful upserts (insert + update).
	 * @param array<string> $errors           Error messages.
	 * @param string        $status           completed|failed.
	 * @param float|null    $elapsed_seconds  Duration for error_log line.
	 */
	private function log_end( int $found, int $new_count, int $updated, array $errors = array(), string $status = 'completed', ?float $elapsed_seconds = null ): void {
		global $wpdb;

		if ( ! $this->log_id ) {
			return;
		}

		if ( null !== $elapsed_seconds ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational feed performance monitoring.
			error_log(
				sprintf(
					'LPNW feed %s finished (%s) in %.1fs — found %d, new %d, updated %d.',
					$this->get_source_name(),
					$status,
					$elapsed_seconds,
					$found,
					$new_count,
					$updated
				)
			);
		}

		$wpdb->update(
			$wpdb->prefix . 'lpnw_feed_log',
			array(
				'completed_at'       => current_time( 'mysql' ),
				'properties_found'   => $found,
				'properties_new'     => $new_count,
				'properties_updated' => $updated,
				'errors'             => ! empty( $errors ) ? wp_json_encode( $errors ) : null,
				'status'             => $status,
			),
			array( 'id' => $this->log_id )
		);
	}
}
