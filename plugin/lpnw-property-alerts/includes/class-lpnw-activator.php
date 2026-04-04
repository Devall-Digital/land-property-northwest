<?php
/**
 * Plugin activation handler.
 *
 * Creates custom database tables and sets default options.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Activator {

	public static function activate(): void {
		self::create_tables();
		self::maybe_migrate();
		self::set_default_options();
		self::schedule_cron();

		update_option( 'lpnw_version', LPNW_VERSION );
		flush_rewrite_rules();
	}

	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sql = "
		CREATE TABLE {$wpdb->prefix}lpnw_properties (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(50) NOT NULL,
			source_ref VARCHAR(255) NOT NULL,
			address TEXT NOT NULL,
			postcode VARCHAR(10) DEFAULT NULL,
			latitude DECIMAL(10,7) DEFAULT NULL,
			longitude DECIMAL(10,7) DEFAULT NULL,
			price BIGINT UNSIGNED DEFAULT NULL,
			property_type VARCHAR(100) DEFAULT NULL,
			bedrooms TINYINT UNSIGNED DEFAULT NULL,
			bathrooms TINYINT UNSIGNED DEFAULT NULL,
			tenure_type VARCHAR(32) DEFAULT NULL,
			price_frequency VARCHAR(20) DEFAULT NULL,
			floor_area_sqft INT UNSIGNED DEFAULT NULL,
			first_listed_date DATE DEFAULT NULL,
			agent_name VARCHAR(255) DEFAULT NULL,
			key_features_text TEXT DEFAULT NULL,
			description TEXT DEFAULT NULL,
			application_type VARCHAR(100) DEFAULT NULL,
			auction_date DATE DEFAULT NULL,
			source_url VARCHAR(500) DEFAULT NULL,
			raw_data LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY source_ref_unique (source, source_ref),
			KEY idx_postcode (postcode),
			KEY idx_source (source),
			KEY idx_created (created_at),
			KEY idx_property_type (property_type),
			KEY idx_bedrooms (bedrooms),
			KEY idx_tenure (tenure_type),
			KEY idx_first_listed (first_listed_date)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}lpnw_subscriber_preferences (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			areas TEXT DEFAULT NULL,
			min_price BIGINT UNSIGNED DEFAULT NULL,
			max_price BIGINT UNSIGNED DEFAULT NULL,
			min_bedrooms TINYINT UNSIGNED DEFAULT NULL,
			max_bedrooms TINYINT UNSIGNED DEFAULT NULL,
			listing_channels TEXT DEFAULT NULL,
			tenure_preferences TEXT DEFAULT NULL,
			required_features TEXT DEFAULT NULL,
			property_types TEXT DEFAULT NULL,
			alert_types TEXT DEFAULT NULL,
			frequency VARCHAR(20) NOT NULL DEFAULT 'daily',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id),
			KEY idx_active (is_active)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}lpnw_alert_queue (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			tier VARCHAR(20) NOT NULL DEFAULT 'free',
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			sent_at DATETIME DEFAULT NULL,
			mautic_email_id VARCHAR(100) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY idx_subscriber (subscriber_id),
			KEY idx_status (status),
			KEY idx_tier_status (tier, status)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}lpnw_saved_properties (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			notes TEXT DEFAULT NULL,
			saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_property (user_id, property_id),
			KEY idx_user (user_id)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}lpnw_feed_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			feed_name VARCHAR(100) NOT NULL,
			started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at DATETIME DEFAULT NULL,
			properties_found INT UNSIGNED DEFAULT 0,
			properties_new INT UNSIGNED DEFAULT 0,
			properties_updated INT UNSIGNED DEFAULT 0,
			errors TEXT DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'running',
			PRIMARY KEY (id),
			KEY idx_feed (feed_name),
			KEY idx_started (started_at)
		) {$charset};
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static function maybe_migrate(): void {
		$current_db_version = get_option( 'lpnw_db_version', '1.0' );

		if ( version_compare( $current_db_version, '2.0', '>=' ) ) {
			return;
		}

		global $wpdb;

		$props_table = $wpdb->prefix . 'lpnw_properties';
		$prefs_table = $wpdb->prefix . 'lpnw_subscriber_preferences';

		$prop_columns = array(
			'bedrooms'          => 'TINYINT UNSIGNED DEFAULT NULL',
			'bathrooms'         => 'TINYINT UNSIGNED DEFAULT NULL',
			'tenure_type'       => 'VARCHAR(32) DEFAULT NULL',
			'price_frequency'   => 'VARCHAR(20) DEFAULT NULL',
			'floor_area_sqft'   => 'INT UNSIGNED DEFAULT NULL',
			'first_listed_date' => 'DATE DEFAULT NULL',
			'agent_name'        => 'VARCHAR(255) DEFAULT NULL',
			'key_features_text' => 'TEXT DEFAULT NULL',
		);

		foreach ( $prop_columns as $col => $definition ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$props_table,
				$col
			) );
			if ( ! $exists ) {
				$wpdb->query( "ALTER TABLE {$props_table} ADD COLUMN {$col} {$definition} AFTER property_type" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$prop_indexes = array(
			'idx_bedrooms'     => 'bedrooms',
			'idx_tenure'       => 'tenure_type',
			'idx_first_listed' => 'first_listed_date',
		);

		foreach ( $prop_indexes as $idx_name => $idx_col ) {
			$idx_exists = $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
				DB_NAME,
				$props_table,
				$idx_name
			) );
			if ( ! $idx_exists ) {
				$wpdb->query( "ALTER TABLE {$props_table} ADD INDEX {$idx_name} ({$idx_col})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$pref_columns = array(
			'min_bedrooms'       => 'TINYINT UNSIGNED DEFAULT NULL',
			'max_bedrooms'       => 'TINYINT UNSIGNED DEFAULT NULL',
			'listing_channels'   => 'TEXT DEFAULT NULL',
			'tenure_preferences' => 'TEXT DEFAULT NULL',
			'required_features'  => 'TEXT DEFAULT NULL',
		);

		foreach ( $pref_columns as $col => $definition ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$prefs_table,
				$col
			) );
			if ( ! $exists ) {
				$wpdb->query( "ALTER TABLE {$prefs_table} ADD COLUMN {$col} {$definition} AFTER max_price" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		update_option( 'lpnw_db_version', '2.0' );
	}

	private static function set_default_options(): void {
		// Launch focus: portals + auctions + alerts. Planning / EPC / Land Registry are optional
		// "intelligence" feeds; enable in LPNW Settings when running a Land Insight-style product tier.
		$defaults = array(
			'planning_enabled'     => false,
			'portals_enabled'      => true,
			'epc_enabled'          => false,
			'epc_api_email'        => '',
			'epc_api_key'          => '',
			'landregistry_enabled' => false,
			'auctions_enabled'     => true,
			'mautic_api_url'       => '',
			'mautic_api_user'      => '',
			'mautic_api_password'  => '',
			'retention_days'       => 180,
			'free_tier_weekly_instant_alerts' => 5,
		);

		if ( false === get_option( 'lpnw_settings' ) ) {
			add_option( 'lpnw_settings', $defaults );
		} else {
			$settings = get_option( 'lpnw_settings', array() );
			$merged   = wp_parse_args( $settings, $defaults );
			if ( $merged !== $settings ) {
				update_option( 'lpnw_settings', $merged );
			}
		}
	}

	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'lpnw_cron_planning' ) ) {
			wp_schedule_event( time(), 'lpnw_six_hours', 'lpnw_cron_planning' );
		}
		if ( ! wp_next_scheduled( 'lpnw_cron_epc' ) ) {
			wp_schedule_event( time(), 'daily', 'lpnw_cron_epc' );
		}
		if ( ! wp_next_scheduled( 'lpnw_cron_landregistry' ) ) {
			wp_schedule_event( time(), 'daily', 'lpnw_cron_landregistry' );
		}
		if ( ! wp_next_scheduled( 'lpnw_cron_auctions' ) ) {
			wp_schedule_event( time(), 'lpnw_fifteen_min', 'lpnw_cron_auctions' );
		}
		if ( ! wp_next_scheduled( 'lpnw_cron_portals' ) ) {
			wp_schedule_event( time(), 'lpnw_fifteen_min', 'lpnw_cron_portals' );
		}
		if ( ! wp_next_scheduled( 'lpnw_cron_dispatch_alerts' ) ) {
			wp_schedule_event( time(), 'lpnw_fifteen_min', 'lpnw_cron_dispatch_alerts' );
		}
		if ( ! wp_next_scheduled( 'lpnw_cron_free_digest' ) ) {
			wp_schedule_event( time(), 'weekly', 'lpnw_cron_free_digest' );
		}
		if ( ! wp_next_scheduled( 'lpnw_cron_data_retention' ) ) {
			wp_schedule_event( time(), 'daily', 'lpnw_cron_data_retention' );
		}
	}

	/**
	 * One-time migration: auction feed was daily; align with portal cadence (15 min).
	 */
	public static function maybe_reschedule_auction_cron(): void {
		if ( get_option( 'lpnw_auctions_cron_15m', '' ) === '1' ) {
			return;
		}

		wp_clear_scheduled_hook( 'lpnw_cron_auctions' );
		wp_schedule_event( time(), 'lpnw_fifteen_min', 'lpnw_cron_auctions' );
		update_option( 'lpnw_auctions_cron_15m', '1', false );
	}
}
