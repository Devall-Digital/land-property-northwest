<?php
/**
 * Plugin Name: LPNW Property Alerts
 * Plugin URI: https://land-property-northwest.co.uk
 * Description: Property intelligence and alert engine for Northwest England. Aggregates planning applications, EPC data, Land Registry transactions, and auction listings into automated subscriber alerts.
 * Version: 1.0.32
 * Author: Land & Property Northwest
 * Author URI: https://land-property-northwest.co.uk
 * License: Proprietary
 * Text Domain: lpnw-alerts
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

define( 'LPNW_VERSION', '1.0.32' );
define( 'LPNW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LPNW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LPNW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * NW England postcode prefixes used for filtering data.
 */
define( 'LPNW_NW_POSTCODES', array(
	'M', 'L', 'PR', 'BB', 'LA', 'BL', 'OL', 'SK',
	'WA', 'WN', 'CW', 'CH', 'CA', 'FY',
) );

require_once LPNW_PLUGIN_DIR . 'includes/class-lpnw-activator.php';
require_once LPNW_PLUGIN_DIR . 'includes/class-lpnw-deactivator.php';

register_activation_hook( __FILE__, array( 'LPNW_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LPNW_Deactivator', 'deactivate' ) );

/**
 * Main plugin class. Singleton pattern.
 */
final class LPNW_Property_Alerts {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies(): void {
		$includes = LPNW_PLUGIN_DIR . 'includes/';
		$feeds    = LPNW_PLUGIN_DIR . 'feeds/';

		require_once $includes . 'class-lpnw-cron.php';
		require_once $includes . 'class-lpnw-cron-http.php';
		require_once $includes . 'class-lpnw-cron-request.php';
		require_once $includes . 'class-lpnw-traffic-cron.php';
		require_once $includes . 'class-lpnw-hero-media.php';
		require_once $includes . 'class-lpnw-outcode-labels.php';
		require_once $includes . 'class-lpnw-nw-postcodes.php';
		require_once $includes . 'class-lpnw-property.php';
		require_once $includes . 'class-lpnw-subscriber.php';
		require_once $includes . 'class-lpnw-woocommerce-store.php';
		require_once $includes . 'class-lpnw-user-tier-profile.php';
		require_once $includes . 'class-lpnw-free-tier-instant.php';
		require_once $includes . 'class-lpnw-matcher.php';
		require_once $includes . 'class-lpnw-dispatcher.php';
		require_once $includes . 'class-lpnw-email-branding.php';
		require_once $includes . 'class-lpnw-woocommerce-notices.php';
		require_once $includes . 'class-lpnw-mautic.php';
		require_once $includes . 'class-lpnw-mautic-sync.php';
		require_once $includes . 'class-lpnw-page-content-sync.php';
		require_once $includes . 'class-lpnw-geocoder.php';
		require_once $includes . 'class-lpnw-area-pages.php';

		require_once $feeds . 'class-lpnw-feed-base.php';
		require_once $feeds . 'class-lpnw-feed-planning.php';
		require_once $feeds . 'class-lpnw-feed-epc.php';
		require_once $feeds . 'class-lpnw-feed-landregistry.php';
		require_once $feeds . 'class-lpnw-feed-auction-pugh.php';
		require_once $feeds . 'class-lpnw-feed-auction-sdl.php';
		require_once $feeds . 'class-lpnw-feed-auction-ahnw.php';
		require_once $feeds . 'class-lpnw-feed-auction-allsop.php';
		require_once $feeds . 'class-lpnw-feed-portal-rightmove.php';
		require_once $feeds . 'class-lpnw-feed-portal-zoopla.php';
		require_once $feeds . 'class-lpnw-feed-portal-onthemarket.php';

		if ( is_admin() ) {
			require_once LPNW_PLUGIN_DIR . 'admin/class-lpnw-admin-help.php';
			require_once LPNW_PLUGIN_DIR . 'admin/class-lpnw-admin.php';
			require_once LPNW_PLUGIN_DIR . 'admin/class-lpnw-admin-subscribers.php';
		}

		require_once LPNW_PLUGIN_DIR . 'includes/class-lpnw-off-market-submit.php';

		require_once LPNW_PLUGIN_DIR . 'public/class-lpnw-public.php';
		require_once LPNW_PLUGIN_DIR . 'public/class-lpnw-dashboard.php';
		require_once LPNW_PLUGIN_DIR . 'public/class-lpnw-map.php';
	}

	private function init_hooks(): void {
		add_action( 'init', array( $this, 'on_init' ) );

		LPNW_Cron_Request::init();
		LPNW_Cron::init();
		LPNW_Traffic_Cron::init();
		LPNW_Hero_Media::init();
		LPNW_Mautic_Sync::init();
		LPNW_Page_Content_Sync::init();
		LPNW_WooCommerce_Notices::init();
		LPNW_WooCommerce_Store::init();
		LPNW_User_Tier_Profile::init();
		LPNW_Off_Market_Submit::init();

		if ( is_admin() ) {
			LPNW_Admin::init();
		}

		LPNW_Public::init();
		LPNW_Dashboard::init();
		LPNW_Map::init();
	}

	public function on_init(): void {
		load_plugin_textdomain( 'lpnw-alerts', false, dirname( LPNW_PLUGIN_BASENAME ) . '/languages' );
	}
}

/**
 * Boot the plugin after all plugins have loaded.
 */
add_action( 'plugins_loaded', function () {
	LPNW_Property_Alerts::instance();
}, 10 );

add_action( 'plugins_loaded', function () {
	LPNW_Activator::maybe_reschedule_auction_cron();
}, 20 );
