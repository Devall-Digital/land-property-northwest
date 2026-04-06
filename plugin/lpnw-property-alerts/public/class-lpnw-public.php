<?php
/**
 * Public-facing functionality.
 *
 * Registers shortcodes, enqueues frontend assets, and handles AJAX endpoints.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Public {

	private const CONTACT_RATE_TRANSIENT_PREFIX = 'lpnw_contact_form_';

	private const CONTACT_RATE_LIMIT = 8;

	private const CONTACT_RATE_WINDOW = 3600;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_shortcode( 'lpnw_alert_feed', array( __CLASS__, 'render_alert_feed' ) );
		add_shortcode( 'lpnw_property_count', array( __CLASS__, 'render_property_count' ) );
		add_shortcode( 'lpnw_signup_form', array( __CLASS__, 'render_signup_form' ) );
		add_shortcode( 'lpnw_latest_properties', array( __CLASS__, 'render_latest_properties' ) );
		add_shortcode( 'lpnw_contact_form', array( __CLASS__, 'render_contact_form' ) );
		add_shortcode( 'lpnw_area_stats', array( __CLASS__, 'render_area_stats' ) );
		add_shortcode( 'lpnw_total_sources', array( __CLASS__, 'render_total_sources' ) );
		add_shortcode( 'lpnw_property_search', array( __CLASS__, 'render_property_search' ) );
		add_shortcode( 'lpnw_live_activity', array( __CLASS__, 'render_live_activity' ) );

		add_action( 'wp_ajax_lpnw_save_preferences', array( __CLASS__, 'ajax_save_preferences' ) );
		add_action( 'wp_ajax_lpnw_contact_form', array( __CLASS__, 'ajax_contact_form' ) );
		add_action( 'wp_ajax_nopriv_lpnw_contact_form', array( __CLASS__, 'ajax_contact_form' ) );
		add_action( 'wp_ajax_lpnw_save_property', array( __CLASS__, 'ajax_save_property' ) );
		add_action( 'wp_ajax_lpnw_unsave_property', array( __CLASS__, 'ajax_unsave_property' ) );
		add_action( 'wp_ajax_lpnw_load_properties', array( __CLASS__, 'ajax_load_properties' ) );
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'lpnw-public',
			LPNW_PLUGIN_URL . 'public/css/lpnw-public.css',
			array(),
			LPNW_VERSION
		);

		wp_enqueue_script(
			'lpnw-public',
			LPNW_PLUGIN_URL . 'public/js/lpnw-public.js',
			array(),
			LPNW_VERSION,
			true
		);

		wp_localize_script( 'lpnw-public', 'lpnwData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'lpnw_public' ),
			'homeUrl' => home_url(),
		) );
	}

	/**
	 * [lpnw_alert_feed] - Displays the user's matched alert feed.
	 */
	public static function render_alert_feed( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your alerts.</p>';
		}

		$atts = shortcode_atts( array( 'limit' => 20 ), $atts );

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/alert-feed.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_property_count] - Live total rows in lpnw_properties (no transient; matches DB on each render).
	 *
	 * Attributes: plus — if "1", append + after the number (e.g. 2,938+).
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function render_property_count( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'plus' => '0',
			),
			$atts,
			'lpnw_property_count'
		);

		global $wpdb;
		$table = $wpdb->prefix . 'lpnw_properties';
		// Table name is from $wpdb->prefix only; no user input.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$out = number_format_i18n( $count );
		if ( in_array( strtolower( (string) $atts['plus'] ), array( '1', 'true', 'yes' ), true ) ) {
			$out .= '+';
		}

		return '<span class="lpnw-property-count">' . esc_html( $out ) . '</span>';
	}

	/**
	 * [lpnw_area_stats] - Grid of property counts by NW postcode area.
	 *
	 * @return string HTML.
	 */
	public static function render_area_stats(): string {
		$rows   = LPNW_Property::count_by_nw_area();
		$labels = LPNW_Property::get_nw_area_labels();

		if ( empty( $rows ) ) {
			return '<p class="lpnw-area-stats lpnw-area-stats--empty">' . esc_html__( 'Area breakdown will appear once we have postcode data.', 'lpnw-alerts' ) . '</p>';
		}

		$list = '';
		foreach ( $rows as $row ) {
			$code  = isset( $row->area ) ? sanitize_text_field( (string) $row->area ) : '';
			$count = isset( $row->cnt ) ? (int) $row->cnt : 0;
			if ( '' === $code || $count < 1 ) {
				continue;
			}
			$name = isset( $labels[ $code ] ) ? $labels[ $code ] : $code;
			$line = sprintf(
				/* translators: 1: area name (e.g. Manchester), 2: property count */
				__( '%1$s: %2$s properties', 'lpnw-alerts' ),
				$name,
				number_format_i18n( $count )
			);
			$list .= '<li class="lpnw-area-stats__item"><span class="lpnw-area-stats__text">' . esc_html( $line ) . '</span></li>';
		}

		if ( '' === $list ) {
			return '<p class="lpnw-area-stats lpnw-area-stats--empty">' . esc_html__( 'Area breakdown will appear once we have postcode data.', 'lpnw-alerts' ) . '</p>';
		}

		return '<div class="lpnw-area-stats" role="region" aria-label="' . esc_attr__( 'Property counts by northwest area', 'lpnw-alerts' ) . '">'
			. '<ul class="lpnw-area-stats__grid" role="list">' . $list . '</ul>'
			. '</div>';
	}

	/**
	 * [lpnw_total_sources] - Distinct feed sources with rows in the property table (live query).
	 *
	 * Attributes: format — "block" (default) full paragraph, or "stat" for number only (wrap with markup in the page).
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML or plain number (escaped) when format=stat.
	 */
	public static function render_total_sources( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'format' => 'block',
			),
			$atts,
			'lpnw_total_sources'
		);

		global $wpdb;

		$table = $wpdb->prefix . 'lpnw_properties';
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT source) FROM {$table} WHERE TRIM(COALESCE(source, '')) <> ''" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( 'stat' === $atts['format'] ) {
			return esc_html( (string) max( 0, $count ) );
		}

		if ( $count < 1 ) {
			return '<p class="lpnw-total-sources lpnw-total-sources--empty">' . esc_html__( 'Source counts will appear as feeds add data.', 'lpnw-alerts' ) . '</p>';
		}

		$text = sprintf(
			/* translators: %d: number of distinct data sources */
			_n( 'Data from %d source', 'Data from %d sources', $count, 'lpnw-alerts' ),
			$count
		);

		return '<p class="lpnw-total-sources">' . esc_html( $text ) . '</p>';
	}

	/**
	 * [lpnw_live_activity] - Pulsing live badge with recent ingest count.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function render_live_activity( $atts = array() ): string {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_properties WHERE created_at >= %s",
				$since
			)
		);

		if ( $count < 1 ) {
			$since_day = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
			$count     = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_properties WHERE created_at >= %s",
					$since_day
				)
			);
			if ( $count < 1 ) {
				return '';
			}
			$label = sprintf(
				/* translators: %s: formatted count */
				__( '%s new today', 'lpnw-alerts' ),
				number_format_i18n( $count )
			);
		} else {
			$label = sprintf(
				/* translators: %s: formatted count */
				__( '%s new in the last hour', 'lpnw-alerts' ),
				number_format_i18n( $count )
			);
		}

		return '<span class="lpnw-live-pulse"><span class="lpnw-live-pulse__dot" aria-hidden="true"></span>' .
			esc_html__( 'Live', 'lpnw-alerts' ) . ' &middot; ' .
			esc_html( $label ) . '</span>';
	}

	/**
	 * [lpnw_signup_form] - Alert signup/preferences form.
	 */
	public static function render_signup_form(): string {
		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/signup-form.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_contact_form] - Native contact form (AJAX to admin-ajax.php).
	 *
	 * @return string HTML.
	 */
	public static function render_contact_form(): string {
		ob_start();
		$nonce = wp_nonce_field( 'lpnw_contact', 'nonce', true, false );
		include LPNW_PLUGIN_DIR . 'public/views/contact-form.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_latest_properties] - Shows recent properties (teaser for non-subscribers).
	 *
	 * Attributes: limit, source, postcode_prefix (NW outward code, e.g. M, CH).
	 */
	public static function render_latest_properties( array $atts = array() ): string {
		defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', true );

		$atts = shortcode_atts( array(
			'limit'             => 5,
			'source'            => '',
			'postcode_prefix'   => '',
		), $atts );

		$filters = array();
		if ( ! empty( $atts['source'] ) ) {
			$filters['source'] = $atts['source'];
		}
		if ( ! empty( $atts['postcode_prefix'] ) ) {
			$pp = strtoupper( trim( sanitize_text_field( $atts['postcode_prefix'] ) ) );
			if ( class_exists( 'LPNW_NW_Postcodes' ) && LPNW_NW_Postcodes::is_valid_area_or_district( $pp ) ) {
				$filters['postcode_prefix'] = $pp;
			} elseif ( in_array( $pp, LPNW_NW_POSTCODES, true ) ) {
				$filters['postcode_prefix'] = $pp;
			}
		}

		$limit = (int) $atts['limit'];
		if ( ! empty( $filters['postcode_prefix'] ) ) {
			$properties = LPNW_Property::query( $filters, $limit, 0 );
		} else {
			$properties = LPNW_Property::query_diverse( $filters, $limit );
		}

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/latest-properties.php';
		return ob_get_clean();
	}

	/**
	 * [lpnw_property_search] - Filterable property browser (URL query params, paginated).
	 *
	 * @param array<string, mixed> $atts Shortcode attributes (unused).
	 * @return string HTML.
	 */
	public static function render_property_search( array $atts = array() ): string {
		defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', true );

		$per_page = 12;
		$state    = self::get_property_search_state( $per_page );

		$filters                 = $state['filters'];
		$properties              = $state['properties'];
		$lpnw_show_latest_cta    = false;
		$lpnw_search_form        = $state['form_values'];
		$lpnw_search_total       = $state['total'];
		$lpnw_search_page        = $state['page'];
		$lpnw_search_total_pages = $state['total_pages'];
		$lpnw_search_per_page    = $per_page;
		$lpnw_search_gated       = $state['gate_overlay'];
		$lpnw_search_range_start = $state['range_start'];
		$lpnw_search_range_end   = $state['range_end'];
		$lpnw_search_base_url    = $state['base_url'];
		$lpnw_area_labels        = LPNW_Property::get_nw_area_labels();

		ob_start();
		include LPNW_PLUGIN_DIR . 'public/views/property-search.php';
		return ob_get_clean();
	}

	/**
	 * Parse GET params and run the property search query.
	 *
	 * @param int $per_page Results per page.
	 * @return array<string, mixed>
	 */
	private static function get_property_search_state( int $per_page ): array {
		$area = isset( $_GET['area'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_GET['area'] ) ) ) ) : '';
		if ( '' !== $area ) {
			$area_ok = class_exists( 'LPNW_NW_Postcodes' )
				? LPNW_NW_Postcodes::is_valid_area_or_district( $area )
				: in_array( $area, LPNW_NW_POSTCODES, true );
			if ( ! $area_ok ) {
				$area = '';
			}
		}

		$type_allowed = array( 'Detached', 'Semi-detached', 'Terraced', 'Flat', 'Other' );
		$type         = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
		if ( ! in_array( $type, $type_allowed, true ) ) {
			$type = '';
		}

		$channel = isset( $_GET['channel'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_GET['channel'] ) ) ) ) : '';
		if ( ! in_array( $channel, array( 'sale', 'rent' ), true ) ) {
			$channel = '';
		}

		$min_price = isset( $_GET['min_price'] ) ? absint( wp_unslash( $_GET['min_price'] ) ) : 0;
		$max_price = isset( $_GET['max_price'] ) ? absint( wp_unslash( $_GET['max_price'] ) ) : 0;

		$source         = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$source_allowed = array( 'rightmove', 'zoopla', 'onthemarket', 'planning', 'auction' );
		if ( '' !== $source && ! in_array( $source, $source_allowed, true ) ) {
			$source = '';
		}

		$bedrooms         = isset( $_GET['bedrooms'] ) ? sanitize_text_field( wp_unslash( $_GET['bedrooms'] ) ) : '';
		$bedrooms_allowed = array( '1', '2', '3', '4', '5' );
		if ( ! in_array( $bedrooms, $bedrooms_allowed, true ) ) {
			$bedrooms = '';
		}

		$tenure = isset( $_GET['tenure'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_GET['tenure'] ) ) ) ) : '';
		if ( ! in_array( $tenure, array( 'freehold', 'leasehold' ), true ) ) {
			$tenure = '';
		}

		$raw_page = isset( $_GET['page'] ) ? max( 1, absint( wp_unslash( $_GET['page'] ) ) ) : 1;

		$filters = array();
		if ( '' !== $area ) {
			$filters['postcode_prefix'] = $area;
		}
		if ( '' !== $type ) {
			$filters['property_type_category'] = $type;
		}
		if ( '' !== $channel ) {
			$filters['channel'] = $channel;
		}
		if ( $min_price > 0 ) {
			$filters['min_price'] = $min_price;
		}
		if ( $max_price > 0 ) {
			$filters['max_price'] = $max_price;
		}
		if ( 'auction' === $source ) {
			$filters['auction_sources'] = true;
		} elseif ( '' !== $source ) {
			$filters['source'] = $source;
		}
		if ( '' !== $bedrooms ) {
			$filters['bedrooms'] = (int) $bedrooms;
		}
		if ( '' !== $tenure ) {
			$filters['tenure'] = 'freehold' === $tenure ? 'Freehold' : 'Leasehold';
		}

		$logged_in = is_user_logged_in();
		$gated     = ! $logged_in && $raw_page > 1;

		$total       = LPNW_Property::count_with_filters( $filters );
		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$page        = $raw_page;
		if ( $page > $total_pages && $total_pages > 0 ) {
			$page = $total_pages;
		}

		$properties    = array();
		$gate_overlay  = $gated && $total > 0;
		if ( $gate_overlay ) {
			// Guests on page 2+ still see the first page of results (blurred) as a preview.
			$properties = LPNW_Property::query( $filters, $per_page, 0 );
		} elseif ( ! $gated ) {
			$offset     = ( $page - 1 ) * $per_page;
			$properties = LPNW_Property::query( $filters, $per_page, $offset );
		}

		$range_start = 0;
		$range_end   = 0;
		if ( $total > 0 ) {
			if ( $gate_overlay ) {
				$range_start = 1;
				$range_end   = min( $total, $per_page );
			} else {
				$range_start = ( $page - 1 ) * $per_page + 1;
				$range_end   = min( $total, $page * $per_page );
			}
		}

		$base_url = '';
		if ( is_singular() ) {
			$base_url = get_permalink();
		}
		if ( ! is_string( $base_url ) || '' === $base_url ) {
			$base_url = home_url( '/' );
		}

		return array(
			'form_values' => array(
				'area'       => $area,
				'type'       => $type,
				'channel'    => $channel,
				'min_price'  => $min_price,
				'max_price'  => $max_price,
				'source'     => $source,
				'bedrooms'   => $bedrooms,
				'tenure'     => $tenure,
			),
			'filters'     => $filters,
			'properties'  => $properties,
			'total'       => $total,
			'page'        => $gated ? $raw_page : $page,
			'total_pages'  => $total_pages,
			'gated'        => $gated,
			'gate_overlay' => $gate_overlay,
			'range_start'  => $range_start,
			'range_end'   => $range_end,
			'base_url'    => $base_url,
		);
	}

	/**
	 * Build URL for property search with given query args (strips empties).
	 *
	 * @param string               $base Base URL.
	 * @param array<string, mixed> $args Query args; page omitted resets to 1 when merging.
	 * @return string
	 */
	public static function property_search_url( string $base, array $args ): string {
		$clean = array();
		foreach ( $args as $key => $val ) {
			if ( 'page' === $key && 1 === (int) $val ) {
				continue;
			}
			if ( '' === $val || null === $val || 0 === $val ) {
				continue;
			}
			$clean[ $key ] = $val;
		}
		return esc_url( add_query_arg( $clean, $base ) );
	}

	/**
	 * Normalise multi-value POST fields: proper arrays, a single scalar, or comma-separated strings.
	 *
	 * @param mixed $raw Unslashed $_POST fragment.
	 * @return array<int, string>
	 */
	private static function normalize_post_string_array( $raw ): array {
		if ( is_array( $raw ) ) {
			$out = array();
			foreach ( $raw as $item ) {
				if ( is_string( $item ) ) {
					$out[] = $item;
				} elseif ( is_scalar( $item ) ) {
					$out[] = (string) $item;
				}
			}
			return $out;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$parts = array_map( 'trim', explode( ',', $raw ) );
			return array_values( array_filter( $parts, 'strlen' ) );
		}
		return array();
	}

	public static function ajax_save_preferences(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in.' );
		}

		$user_id = get_current_user_id();
		$tier    = LPNW_Subscriber::get_tier( $user_id );

		$listing_raw = self::normalize_post_string_array(
			isset( $_POST['listing_channels'] ) ? wp_unslash( $_POST['listing_channels'] ) : array()
		);
		$allowed_listing = array( 'sale', 'rent' );
		$listing_channels = array_values( array_intersect( $allowed_listing, array_map( 'sanitize_text_field', $listing_raw ) ) );

		$tenure_raw = self::normalize_post_string_array(
			isset( $_POST['tenure_preferences'] ) ? wp_unslash( $_POST['tenure_preferences'] ) : array()
		);
		$allowed_tenure = array( 'freehold', 'leasehold', 'share_of_freehold' );
		$tenure_preferences = array_values( array_intersect( $allowed_tenure, array_map( 'sanitize_text_field', $tenure_raw ) ) );

		$features_raw = self::normalize_post_string_array(
			isset( $_POST['required_features'] ) ? wp_unslash( $_POST['required_features'] ) : array()
		);
		$allowed_features = array( 'garden', 'parking', 'garage', 'new_build', 'chain_free' );
		$required_features = array_values( array_intersect( $allowed_features, array_map( 'sanitize_text_field', $features_raw ) ) );

		$allowed_alert_types = array( 'listing', 'planning', 'epc', 'price', 'auction' );
		if ( 'vip' === $tier ) {
			$allowed_alert_types[] = 'off_market';
		}
		$alert_raw = self::normalize_post_string_array(
			isset( $_POST['alert_types'] ) ? wp_unslash( $_POST['alert_types'] ) : array()
		);
		$alert_types_sanitized = array_values( array_intersect( $allowed_alert_types, array_map( 'sanitize_text_field', $alert_raw ) ) );

		$areas_raw = self::normalize_post_string_array(
			isset( $_POST['areas'] ) ? wp_unslash( $_POST['areas'] ) : array()
		);
		$areas_sanitized = class_exists( 'LPNW_NW_Postcodes' )
			? LPNW_NW_Postcodes::sanitize_areas_array( $areas_raw )
			: array_values( array_intersect( array_map( 'sanitize_text_field', $areas_raw ), LPNW_NW_POSTCODES ) );
		$ptypes_raw = self::normalize_post_string_array(
			isset( $_POST['property_types'] ) ? wp_unslash( $_POST['property_types'] ) : array()
		);
		$property_types_sanitized = LPNW_Subscriber::sanitize_preference_property_types( $ptypes_raw );

		$prefs = array(
			'areas'                => $areas_sanitized,
			'min_price'            => absint( $_POST['min_price'] ?? 0 ),
			'max_price'            => absint( $_POST['max_price'] ?? 0 ),
			'property_types'       => $property_types_sanitized,
			'alert_types'          => $alert_types_sanitized,
			'listing_channels'     => $listing_channels,
			'tenure_preferences'   => $tenure_preferences,
			'required_features'    => $required_features,
			'frequency'            => self::clamp_frequency_for_tier(
				sanitize_text_field( wp_unslash( $_POST['frequency'] ?? 'weekly' ) ),
				$tier
			),
		);

		$min_bedrooms_raw = isset( $_POST['min_bedrooms'] ) ? sanitize_text_field( wp_unslash( $_POST['min_bedrooms'] ) ) : '';
		if ( '' !== $min_bedrooms_raw ) {
			$prefs['min_bedrooms'] = min( 10, max( 0, absint( $min_bedrooms_raw ) ) );
		}

		$max_bedrooms_raw = isset( $_POST['max_bedrooms'] ) ? sanitize_text_field( wp_unslash( $_POST['max_bedrooms'] ) ) : '';
		if ( '' !== $max_bedrooms_raw ) {
			$prefs['max_bedrooms'] = min( 10, max( 0, absint( $max_bedrooms_raw ) ) );
		}

		$saved = LPNW_Subscriber::save_preferences( $user_id, $prefs );

		if ( $saved ) {
			wp_send_json_success( 'Preferences saved.' );
		} else {
			wp_send_json_error( 'Could not save preferences.' );
		}
	}

	public static function ajax_save_property(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in.' );
		}

		global $wpdb;

		$property_id = absint( $_POST['property_id'] ?? 0 );
		$notes       = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		if ( ! $property_id ) {
			wp_send_json_error( 'Invalid property.' );
		}

		$result = $wpdb->replace(
			$wpdb->prefix . 'lpnw_saved_properties',
			array(
				'user_id'     => get_current_user_id(),
				'property_id' => $property_id,
				'notes'       => $notes,
			)
		);

		if ( false === $result ) {
			wp_send_json_error( 'Could not save property.' );
		}

		wp_send_json_success( 'Property saved.' );
	}

	public static function ajax_unsave_property(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in.' );
		}

		global $wpdb;

		$property_id = absint( $_POST['property_id'] ?? 0 );

		if ( ! $property_id ) {
			wp_send_json_error( 'Invalid property.' );
		}

		$deleted = $wpdb->delete(
			$wpdb->prefix . 'lpnw_saved_properties',
			array(
				'user_id'     => get_current_user_id(),
				'property_id' => $property_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( 'Could not remove saved property.' );
		}

		wp_send_json_success( 'Property removed from saved list.' );
	}

	/**
	 * Handle public contact form submission.
	 */
	public static function ajax_contact_form(): void {
		check_ajax_referer( 'lpnw_contact', 'nonce' );

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$rk = self::CONTACT_RATE_TRANSIENT_PREFIX . md5( $ip );
		$n  = (int) get_transient( $rk );
		if ( $n >= self::CONTACT_RATE_LIMIT ) {
			wp_send_json_error(
				array(
					'message' => __( 'Too many messages sent from your connection recently. Please try again later.', 'lpnw-alerts' ),
				)
			);
		}

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === $name || '' === $message || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your name, a valid email, and a message.', 'lpnw-alerts' ),
				)
			);
		}

		$admin_email = LPNW_Email_Branding::get_contact_notification_to_email();
		if ( '' === $admin_email || ! is_email( $admin_email ) ) {
			$admin_email = get_option( 'admin_email' );
		}
		if ( ! is_email( $admin_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The site cannot accept messages right now. Please try again later.', 'lpnw-alerts' ),
				)
			);
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( '' !== $subject ) {
			/* translators: 1: site name, 2: user-supplied subject line */
			$mail_subject = sprintf( __( '[%1$s] %2$s', 'lpnw-alerts' ), $site_name, $subject );
		} else {
			/* translators: %s: site name */
			$mail_subject = sprintf( __( '[%s] Contact form', 'lpnw-alerts' ), $site_name );
		}

		$body = sprintf(
			"%s\n%s: %s\n%s: %s\n\n%s\n",
			/* translators: email body header */
			__( 'New message from the website contact form.', 'lpnw-alerts' ),
			/* translators: email label */
			__( 'Name', 'lpnw-alerts' ),
			$name,
			/* translators: email label */
			__( 'Email', 'lpnw-alerts' ),
			$email,
			$message
		);

		$headers   = LPNW_Email_Branding::get_contact_mail_headers();
		$headers[] = 'Reply-To: ' . $email;

		$sent = wp_mail( $admin_email, $mail_subject, $body, $headers );

		if ( $sent ) {
			set_transient( $rk, $n + 1, self::CONTACT_RATE_WINDOW );
		}

		if ( ! $sent ) {
			wp_send_json_error(
				array(
					'message' => __( 'Could not send your message. Please try again later.', 'lpnw-alerts' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Thank you. We have received your message and will reply as soon as we can.', 'lpnw-alerts' ),
			)
		);
	}

	public static function ajax_load_properties(): void {
		check_ajax_referer( 'lpnw_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Login required.' );
		}

		$filters = array(
			'source'          => sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ),
			'postcode_prefix' => sanitize_text_field( wp_unslash( $_POST['postcode_prefix'] ?? '' ) ),
			'min_price'       => absint( $_POST['min_price'] ?? 0 ),
			'max_price'       => absint( $_POST['max_price'] ?? 0 ),
			'property_type'   => sanitize_text_field( wp_unslash( $_POST['property_type'] ?? '' ) ),
		);

		$filters = array_filter( $filters );
		$limit   = min( absint( $_POST['limit'] ?? 50 ), 100 );
		$offset  = absint( $_POST['offset'] ?? 0 );

		$properties = LPNW_Property::query( $filters, $limit, $offset );

		foreach ( $properties as &$p ) {
			unset( $p->raw_data );
		}
		unset( $p );

		wp_send_json_success( $properties );
	}

	/**
	 * Restrict alert frequency to values allowed for the subscription tier.
	 *
	 * Free: weekly only. Pro: daily or instant (or weekly). VIP: any known value.
	 *
	 * @param string $frequency Requested frequency (instant, daily, weekly).
	 * @param string $tier      One of free, pro, vip.
	 * @return string Clamped frequency.
	 */
	private static function clamp_frequency_for_tier( string $frequency, string $tier ): string {
		$valid = array( 'instant', 'daily', 'weekly' );
		if ( ! in_array( $frequency, $valid, true ) ) {
			$frequency = 'weekly';
		}

		$tier = strtolower( $tier );

		if ( 'free' === $tier ) {
			return 'weekly';
		}

		// Pro or VIP: any valid frequency (instant, daily, weekly).
		return $frequency;
	}
}
