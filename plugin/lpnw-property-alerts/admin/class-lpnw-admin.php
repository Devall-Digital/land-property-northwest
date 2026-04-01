<?php
/**
 * Admin panel for the LPNW Property Alerts plugin.
 *
 * Registers admin menu pages, settings, and dashboard widgets.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_menu_pages(): void {
		add_menu_page(
			__( 'LPNW Alerts', 'lpnw-alerts' ),
			__( 'LPNW Alerts', 'lpnw-alerts' ),
			'manage_options',
			'lpnw-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-bell',
			30
		);

		add_submenu_page(
			'lpnw-dashboard',
			__( 'Settings', 'lpnw-alerts' ),
			__( 'Settings', 'lpnw-alerts' ),
			'manage_options',
			'lpnw-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_submenu_page(
			'lpnw-dashboard',
			__( 'Feed Status', 'lpnw-alerts' ),
			__( 'Feed Status', 'lpnw-alerts' ),
			'manage_options',
			'lpnw-feeds',
			array( __CLASS__, 'render_feeds' )
		);

		add_submenu_page(
			'lpnw-dashboard',
			__( 'Alert Log', 'lpnw-alerts' ),
			__( 'Alert Log', 'lpnw-alerts' ),
			'manage_options',
			'lpnw-alert-log',
			array( __CLASS__, 'render_alert_log' )
		);
	}

	public static function register_settings(): void {
		register_setting( 'lpnw_settings_group', 'lpnw_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
		) );

		add_settings_section(
			'lpnw_feeds_section',
			__( 'Data Feeds', 'lpnw-alerts' ),
			null,
			'lpnw-settings'
		);

		add_settings_section(
			'lpnw_mautic_section',
			__( 'Mautic Integration', 'lpnw-alerts' ),
			null,
			'lpnw-settings'
		);

		$feed_fields = array(
			'planning_enabled'     => 'Enable Planning Portal feed',
			'epc_enabled'          => 'Enable EPC Open Data feed',
			'epc_api_email'        => 'EPC account email (Basic auth username)',
			'epc_api_key'          => 'EPC API key (Basic auth password)',
			'landregistry_enabled' => 'Enable Land Registry feed',
			'auctions_enabled'     => 'Enable Auction House feeds',
		);

		foreach ( $feed_fields as $key => $label ) {
			$type = 'text';
			if ( str_contains( $key, 'enabled' ) ) {
				$type = 'checkbox';
			} elseif ( 'epc_api_email' === $key ) {
				$type = 'email';
			}
			add_settings_field(
				$key,
				__( $label, 'lpnw-alerts' ),
				array( __CLASS__, 'render_field' ),
				'lpnw-settings',
				'lpnw_feeds_section',
				array( 'key' => $key, 'type' => $type )
			);
		}

		$mautic_fields = array(
			'mautic_api_url'      => 'Mautic URL',
			'mautic_api_user'     => 'Mautic API Username',
			'mautic_api_password' => 'Mautic API Password',
			'mautic_email_vip'    => 'VIP Alert Email ID',
			'mautic_email_pro'    => 'Pro Alert Email ID',
			'mautic_email_free'   => 'Free Digest Email ID',
		);

		foreach ( $mautic_fields as $key => $label ) {
			$type = str_contains( $key, 'password' ) ? 'password' : 'text';
			add_settings_field(
				$key,
				__( $label, 'lpnw-alerts' ),
				array( __CLASS__, 'render_field' ),
				'lpnw-settings',
				'lpnw_mautic_section',
				array( 'key' => $key, 'type' => $type )
			);
		}
	}

	/**
	 * @param array<string, mixed> $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $input ): array {
		$sanitized = array();

		$checkboxes = array( 'planning_enabled', 'epc_enabled', 'landregistry_enabled', 'auctions_enabled' );
		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		$sanitized['epc_api_email'] = sanitize_email( $input['epc_api_email'] ?? '' );

		$text_fields = array(
			'epc_api_key', 'mautic_api_url', 'mautic_api_user', 'mautic_api_password',
			'mautic_email_vip', 'mautic_email_pro', 'mautic_email_free',
		);
		foreach ( $text_fields as $key ) {
			$sanitized[ $key ] = sanitize_text_field( $input[ $key ] ?? '' );
		}

		return $sanitized;
	}

	/**
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_field( array $args ): void {
		$settings = get_option( 'lpnw_settings', array() );
		$key      = $args['key'];
		$type     = $args['type'];
		$value    = $settings[ $key ] ?? '';

		if ( 'checkbox' === $type ) {
			printf(
				'<input type="checkbox" name="lpnw_settings[%s]" value="1" %s />',
				esc_attr( $key ),
				checked( $value, true, false )
			);
		} else {
			printf(
				'<input type="%s" name="lpnw_settings[%s]" value="%s" class="regular-text" />',
				esc_attr( $type ),
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
	}

	public static function render_dashboard(): void {
		include LPNW_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function render_settings(): void {
		include LPNW_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public static function render_feeds(): void {
		include LPNW_PLUGIN_DIR . 'admin/views/feeds.php';
	}

	public static function render_alert_log(): void {
		include LPNW_PLUGIN_DIR . 'admin/views/alert-log.php';
	}
}
