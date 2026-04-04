<?php
/**
 * WordPress admin help tabs and short contextual tips for LPNW screens.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers Screen Options help (?) tabs and reusable tip markup.
 */
final class LPNW_Admin_Help {

	public static function init(): void {
		add_action( 'load-toplevel_page_lpnw-dashboard', array( __CLASS__, 'on_load_dashboard' ) );
		add_action( 'load-lpnw-dashboard_page_lpnw-settings', array( __CLASS__, 'on_load_settings' ) );
		add_action( 'load-lpnw-dashboard_page_lpnw-feeds', array( __CLASS__, 'on_load_feeds' ) );
		add_action( 'load-lpnw-dashboard_page_lpnw-alert-log', array( __CLASS__, 'on_load_alert_log' ) );
		add_action( 'load-lpnw-dashboard_page_lpnw-off-market', array( __CLASS__, 'on_load_off_market' ) );
		add_action( 'load-lpnw-dashboard_page_lpnw-subscribers', array( __CLASS__, 'on_load_subscribers' ) );
		add_action( 'load-user-edit.php', array( __CLASS__, 'on_load_user_edit' ) );
		add_action( 'load-profile.php', array( __CLASS__, 'on_load_profile' ) );
	}

	/**
	 * Visible hint with native tooltip (title) and screen-reader text.
	 *
	 * @param string $tip   Short explanation (also used as title tooltip).
	 * @param string $label Optional visible label before the icon.
	 */
	public static function tip( string $tip, string $label = '' ): void {
		$tip_esc = esc_attr( $tip );
		printf(
			'<span class="lpnw-admin-tip" style="display:inline-flex;align-items:center;gap:4px;vertical-align:middle;">%s<button type="button" class="button-link" style="padding:0;min-height:0;line-height:1;" title="%s" aria-label="%s"><span class="dashicons dashicons-editor-help" style="font-size:16px;width:16px;height:16px;color:#646970;" aria-hidden="true"></span></button></span>',
			'' !== $label ? '<span class="description">' . esc_html( $label ) . '</span>' : '',
			$tip_esc,
			$tip_esc
		);
	}

	/**
	 * Compact help icon for table headers (hover for tooltip).
	 *
	 * @param string $tip Tooltip and accessible name.
	 */
	public static function tip_icon( string $tip ): void {
		$tip_esc = esc_attr( $tip );
		printf(
			'<button type="button" class="button-link lpnw-tip-icon" style="padding:0 0 0 4px;min-height:0;line-height:1;vertical-align:middle;" title="%1$s" aria-label="%1$s"><span class="dashicons dashicons-editor-help" style="font-size:16px;width:16px;height:16px;color:#646970;" aria-hidden="true"></span></button>',
			$tip_esc
		);
	}

	/**
	 * @param WP_Screen $screen Current admin screen.
	 * @param string    $id     Tab id.
	 * @param string    $title  Tab title.
	 * @param string    $html   Tab body HTML.
	 */
	private static function add_tab( $screen, string $id, string $title, string $html ): void {
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}
		$screen->add_help_tab(
			array(
				'id'      => $id,
				'title'   => $title,
				'content' => $html,
			)
		);
	}

	/**
	 * Shared sidebar with links.
	 *
	 * @param WP_Screen $screen Current screen.
	 */
	private static function set_sidebar( $screen ): void {
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}
		$wc_orders = admin_url( 'edit.php?post_type=shop_order' );
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$wc_orders = admin_url( 'admin.php?page=wc-orders' );
		}

		$html  = '<p><strong>' . esc_html__( 'Quick links', 'lpnw-alerts' ) . '</strong></p>';
		$html .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=lpnw-subscribers' ) ) . '">' . esc_html__( 'Subscribers', 'lpnw-alerts' ) . '</a></p>';
		$html .= '<p><a href="' . esc_url( admin_url( 'users.php' ) ) . '">' . esc_html__( 'All users', 'lpnw-alerts' ) . '</a></p>';
		$html .= '<p><a href="' . esc_url( $wc_orders ) . '">' . esc_html__( 'WooCommerce orders', 'lpnw-alerts' ) . '</a></p>';
		$html .= '<p>' . esc_html__( 'Tip: use the Help tab (question mark in the admin toolbar) on each LPNW screen for what the numbers and columns mean.', 'lpnw-alerts' ) . '</p>';

		$screen->set_help_sidebar( $html );
	}

	public static function on_load_dashboard(): void {
		$screen = get_current_screen();
		self::add_tab(
			$screen,
			'lpnw_dashboard_overview',
			__( 'Overview', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'This page is your operations centre: property volume, how many subscribers have active alert preferences, alert queue health, feed run history, and when crons will run next.', 'lpnw-alerts' ) . '</p>' .
			'<p>' . esc_html__( 'Users with alert preferences on counts distinct WordPress users who saved preferences and left alerts switched on. Paid Pro/VIP (WooCommerce) counts customers with a qualifying order; they can appear here even if they have not saved alert preferences yet. The free / pro / vip split applies only to users with active preferences. LPNW Alerts > Subscribers lists everyone with a preferences row.', 'lpnw-alerts' ) . '</p>'
		);
		self::add_tab(
			$screen,
			'lpnw_dashboard_feed_log',
			__( 'Feed log', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'Click column headers to sort. Use this to confirm each source ran recently and to spot failed runs or error messages.', 'lpnw-alerts' ) . '</p>'
		);
		self::set_sidebar( $screen );
	}

	public static function on_load_settings(): void {
		$screen = get_current_screen();
		self::add_tab(
			$screen,
			'lpnw_settings_intro',
			__( 'Settings', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'Toggle feeds, set the EPC API credentials, and configure Mautic so instant alerts and the weekly digest can send. After changing Mautic template names, open this page once so IDs can sync if your plugin version supports it.', 'lpnw-alerts' ) . '</p>'
		);
		self::set_sidebar( $screen );
	}

	public static function on_load_feeds(): void {
		$screen = get_current_screen();
		self::add_tab(
			$screen,
			'lpnw_feeds_intro',
			__( 'Feed status', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'Per-feed status and controls live here. If a source shows zero new rows for a long time, check the main dashboard feed log for errors or upstream blocking.', 'lpnw-alerts' ) . '</p>'
		);
		self::set_sidebar( $screen );
	}

	public static function on_load_alert_log(): void {
		$screen = get_current_screen();
		self::add_tab(
			$screen,
			'lpnw_alert_log_intro',
			__( 'Alert log', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'Review individual alert rows: queued, sent, or failed. Large queued counts usually mean dispatch or email delivery needs attention (Mautic IDs, wp_mail, or cron).', 'lpnw-alerts' ) . '</p>'
		);
		self::set_sidebar( $screen );
	}

	public static function on_load_off_market(): void {
		$screen = get_current_screen();
		self::add_tab(
			$screen,
			'lpnw_om_intro',
			__( 'Off-market', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'Adds a manual property row and runs the matcher so VIP (and matching) subscribers can be queued like any other listing.', 'lpnw-alerts' ) . '</p>'
		);
		self::set_sidebar( $screen );
	}

	public static function on_load_subscribers(): void {
		$screen = get_current_screen();
		self::add_tab(
			$screen,
			'lpnw_subscribers_intro',
			__( 'Subscribers', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'Everyone listed here has saved alert preferences at least once. Search by email or display name.', 'lpnw-alerts' ) . '</p>' .
			'<ul>' .
			'<li><strong>' . esc_html__( 'Tier', 'lpnw-alerts' ) . '</strong> — ' . esc_html__( 'Effective tier used for alerts: WooCommerce Pro/VIP orders (completed or processing) always win.', 'lpnw-alerts' ) . '</li>' .
			'<li><strong>' . esc_html__( 'From orders', 'lpnw-alerts' ) . '</strong> — ' . esc_html__( 'What tier their order history alone would give (ignores admin comp overrides).', 'lpnw-alerts' ) . '</li>' .
			'<li><strong>' . esc_html__( 'Override', 'lpnw-alerts' ) . '</strong> — ' . esc_html__( 'Optional comp or trial set on the user profile; only applies when there is no qualifying paid Pro/VIP order.', 'lpnw-alerts' ) . '</li>' .
			'<li><strong>' . esc_html__( 'Orders', 'lpnw-alerts' ) . '</strong> — ' . esc_html__( 'Opens WooCommerce filtered to this customer. Refunds and order status change what “from orders” shows.', 'lpnw-alerts' ) . '</li>' .
			'</ul>'
		);
		self::add_tab(
			$screen,
			'lpnw_subscribers_tiers',
			__( 'Changing tier', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'Paid upgrades and downgrades: use WooCommerce (new order, refund, or cancel) so billing stays honest.', 'lpnw-alerts' ) . '</p>' .
			'<p>' . esc_html__( 'Free trials or comps without a card: edit the user and use “Admin tier override” under LPNW alert tier (support).', 'lpnw-alerts' ) . '</p>'
		);
		self::set_sidebar( $screen );
	}

	public static function on_load_user_edit(): void {
		self::maybe_add_user_tier_help();
	}

	public static function on_load_profile(): void {
		self::maybe_add_user_tier_help();
	}

	private static function maybe_add_user_tier_help(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$screen = get_current_screen();
		self::add_tab(
			$screen,
			'lpnw_user_tier',
			__( 'LPNW tier', 'lpnw-alerts' ),
			'<p>' . esc_html__( 'The LPNW section sets alert tier when WooCommerce does not already grant Pro or VIP from a completed or processing order.', 'lpnw-alerts' ) . '</p>' .
			'<p>' . esc_html__( 'If “Tier from orders” is PRO or VIP, the override dropdown is disabled so you do not strip paid access by mistake. Adjust the real order in WooCommerce instead.', 'lpnw-alerts' ) . '</p>' .
			'<p>' . esc_html__( 'Product slugs lpnw-pro and lpnw-vip in orders drive automatic tier detection.', 'lpnw-alerts' ) . '</p>'
		);
	}
}
