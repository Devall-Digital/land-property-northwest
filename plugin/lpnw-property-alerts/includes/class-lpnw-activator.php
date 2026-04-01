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
			KEY idx_property_type (property_type)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}lpnw_subscriber_preferences (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			areas TEXT DEFAULT NULL,
			min_price BIGINT UNSIGNED DEFAULT NULL,
			max_price BIGINT UNSIGNED DEFAULT NULL,
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

	private static function set_default_options(): void {
		$defaults = array(
			'planning_enabled'     => true,
			'portals_enabled'      => true,
			'epc_enabled'          => true,
			'epc_api_email'        => '',
			'epc_api_key'          => '',
			'landregistry_enabled' => true,
			'auctions_enabled'     => true,
			'mautic_api_url'       => '',
			'mautic_api_user'      => '',
			'mautic_api_password'  => '',
		);

		if ( false === get_option( 'lpnw_settings' ) ) {
			add_option( 'lpnw_settings', $defaults );
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
			wp_schedule_event( time(), 'daily', 'lpnw_cron_auctions' );
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
	}
}
