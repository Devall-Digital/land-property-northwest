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

	public const FEED_RUN_NONCE_ACTION = 'lpnw_run_feed';

	public const TRANSIENT_FEED_NOTICE = 'lpnw_feed_run_notice';

	public const FEED_LOG_PER_PAGE = 20;

	public const ADD_OFF_MARKET_NONCE_ACTION = 'lpnw_add_off_market';

	public const TRANSIENT_ADD_OFF_MARKET_NOTICE = 'lpnw_add_off_market_notice';

	/** @var array<string, string> */
	private static array $manual_feed_class_map = array(
		'rightmove'       => LPNW_Feed_Portal_Rightmove::class,
		'zoopla'          => LPNW_Feed_Portal_Zoopla::class,
		'onthemarket'     => LPNW_Feed_Portal_OnTheMarket::class,
		'planning'        => LPNW_Feed_Planning::class,
		'epc'             => LPNW_Feed_EPC::class,
		'landregistry'    => LPNW_Feed_LandRegistry::class,
		'auction_pugh'    => LPNW_Feed_Auction_Pugh::class,
		'auction_sdl'     => LPNW_Feed_Auction_SDL::class,
		'auction_ahnw'    => LPNW_Feed_Auction_AHNW::class,
		'auction_allsop'  => LPNW_Feed_Auction_Allsop::class,
	);

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_wp_dashboard_widget' ) );
		add_action( 'admin_post_lpnw_run_feed', array( __CLASS__, 'handle_manual_feed_run' ) );
		add_action( 'admin_post_lpnw_add_off_market', array( __CLASS__, 'handle_add_off_market' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_feed_run_admin_notice' ) );
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

		add_submenu_page(
			'lpnw-dashboard',
			__( 'Add Off-Market Deal', 'lpnw-alerts' ),
			__( 'Add Off-Market Deal', 'lpnw-alerts' ),
			'manage_options',
			'lpnw-off-market',
			array( __CLASS__, 'render_add_off_market' )
		);
	}

	public static function register_wp_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'lpnw_status_widget',
			__( 'LPNW Property Alerts', 'lpnw-alerts' ),
			array( __CLASS__, 'render_wp_dashboard_widget' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	/**
	 * Main WordPress dashboard widget (at-a-glance stats).
	 */
	public static function render_wp_dashboard_widget(): void {
		$snapshot = self::get_dashboard_snapshot();
		$run_url  = admin_url( 'admin-post.php' );
		?>
		<div class="lpnw-wp-dashboard-widget">
			<ul class="lpnw-wp-dashboard-widget__stats" style="margin:0 0 12px; list-style:none; padding:0;">
				<li style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f0f0f1;">
					<span><?php esc_html_e( 'Properties tracked', 'lpnw-alerts' ); ?></span>
					<strong><?php echo esc_html( number_format( $snapshot['property_count'] ) ); ?></strong>
				</li>
				<li style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f0f0f1;">
					<span><?php esc_html_e( 'Added in last 24 hours', 'lpnw-alerts' ); ?></span>
					<strong><?php echo esc_html( number_format( $snapshot['properties_24h'] ) ); ?></strong>
				</li>
				<li style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f0f0f1;">
					<span><?php esc_html_e( 'Last feed run', 'lpnw-alerts' ); ?></span>
					<span>
						<?php
						if ( ! empty( $snapshot['last_feed']['started_at'] ) ) {
							echo esc_html(
								sprintf(
									/* translators: 1: datetime, 2: feed name, 3: status */
									__( '%1$s (%2$s) - %3$s', 'lpnw-alerts' ),
									$snapshot['last_feed']['started_at'],
									$snapshot['last_feed']['feed_name'],
									$snapshot['last_feed']['status_label']
								)
							);
						} else {
							esc_html_e( 'No runs yet', 'lpnw-alerts' );
						}
						?>
					</span>
				</li>
				<li style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f0f0f1;">
					<span><?php esc_html_e( 'Active subscribers', 'lpnw-alerts' ); ?></span>
					<strong><?php echo esc_html( number_format( $snapshot['subscriber_count'] ) ); ?></strong>
				</li>
				<li style="display:flex; justify-content:space-between; padding:4px 0;">
					<span><?php esc_html_e( 'Alerts sent today', 'lpnw-alerts' ); ?></span>
					<strong><?php echo esc_html( number_format( $snapshot['alerts_sent_today'] ) ); ?></strong>
				</li>
			</ul>
			<form method="post" action="<?php echo esc_url( $run_url ); ?>" style="margin:0;">
				<?php wp_nonce_field( self::FEED_RUN_NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="lpnw_run_feed" />
				<input type="hidden" name="feed" value="rightmove" />
				<input type="hidden" name="lpnw_redirect_to" value="wp_dashboard" />
				<?php
				submit_button(
					__( 'Run Rightmove feed now', 'lpnw-alerts' ),
					'secondary',
					'submit',
					false,
					array( 'id' => '' )
				);
				?>
			</form>
			<p style="margin:12px 0 0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=lpnw-dashboard' ) ); ?>">
					<?php esc_html_e( 'Open LPNW dashboard', 'lpnw-alerts' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle manual feed run from admin-post.php.
	 */
	public static function handle_manual_feed_run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run feeds.', 'lpnw-alerts' ) );
		}

		check_admin_referer( self::FEED_RUN_NONCE_ACTION );

		global $wpdb;

		$feed_key = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : 'rightmove';

		$max_before = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}lpnw_feed_log" );

		if ( 'auctions' === $feed_key ) {
			$auction_keys = array( 'auction_pugh', 'auction_sdl', 'auction_ahnw', 'auction_allsop' );
			foreach ( $auction_keys as $key ) {
				$class = self::$manual_feed_class_map[ $key ];
				$feed  = new $class();
				$feed->run();
			}
			$feed_key  = 'auctions';
			$feed_name = __( 'All auction feeds', 'lpnw-alerts' );
		} else {
			if ( ! isset( self::$manual_feed_class_map[ $feed_key ] ) ) {
				$feed_key = 'rightmove';
			}
			$class = self::$manual_feed_class_map[ $feed_key ];
			$feed  = new $class();
			$feed->run();
			$feed_name = method_exists( $feed, 'get_source_name' ) ? $feed->get_source_name() : $feed_key;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT properties_found, properties_new FROM {$wpdb->prefix}lpnw_feed_log WHERE id > %d ORDER BY id DESC LIMIT 1",
				$max_before
			)
		);

		$found = $row ? (int) $row->properties_found : 0;
		$new   = $row ? (int) $row->properties_new : 0;

		set_transient(
			self::TRANSIENT_FEED_NOTICE . '_' . get_current_user_id(),
			array(
				'found'     => $found,
				'new'       => $new,
				'feed_key'  => $feed_key,
				'feed_name' => $feed_name,
			),
			120
		);

		$redirect_target = isset( $_POST['lpnw_redirect_to'] ) ? sanitize_key( wp_unslash( $_POST['lpnw_redirect_to'] ) ) : 'lpnw';
		if ( 'wp_dashboard' === $redirect_target ) {
			$url = add_query_arg( 'lpnw_feed_notice', '1', admin_url( 'index.php' ) );
		} elseif ( 'settings' === $redirect_target ) {
			$url = add_query_arg( 'lpnw_feed_notice', '1', admin_url( 'admin.php?page=lpnw-settings' ) );
		} else {
			$url = add_query_arg( 'lpnw_feed_notice', '1', admin_url( 'admin.php?page=lpnw-dashboard' ) );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Success notice after manual feed run.
	 */
	public static function render_feed_run_admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_wp_dashboard = $screen && 'dashboard' === $screen->id;
		$on_lpnw         = isset( $_GET['page'] ) && 'lpnw-dashboard' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$on_settings     = isset( $_GET['page'] ) && 'lpnw-settings' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $_GET['lpnw_feed_notice'] ) || ( ! $on_wp_dashboard && ! $on_lpnw && ! $on_settings ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$data = get_transient( self::TRANSIENT_FEED_NOTICE . '_' . get_current_user_id() );
		if ( ! is_array( $data ) ) {
			return;
		}

		delete_transient( self::TRANSIENT_FEED_NOTICE . '_' . get_current_user_id() );

		$found = isset( $data['found'] ) ? (int) $data['found'] : 0;
		$new   = isset( $data['new'] ) ? (int) $data['new'] : 0;
		$name  = isset( $data['feed_name'] ) ? sanitize_text_field( $data['feed_name'] ) : '';

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: feed name, 2: properties found count, 3: new properties count */
					__( 'Feed "%1$s" finished. Properties found: %2$d. New: %3$d.', 'lpnw-alerts' ),
					$name,
					$found,
					$new
				)
			)
		);
	}

	/**
	 * Snapshot for widget and shared metrics.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_dashboard_snapshot(): array {
		global $wpdb;

		$property_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_properties" );

		$since_24h = date( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - DAY_IN_SECONDS );
		$properties_24h = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_properties WHERE created_at >= %s",
				$since_24h
			)
		);

		$subscriber_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_subscriber_preferences WHERE is_active = 1"
		);

		$today_start = date( 'Y-m-d 00:00:00', (int) current_time( 'timestamp' ) );
		$alerts_sent_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = %s AND sent_at IS NOT NULL AND sent_at >= %s",
				'sent',
				$today_start
			)
		);

		$last = $wpdb->get_row(
			"SELECT feed_name, started_at, status FROM {$wpdb->prefix}lpnw_feed_log ORDER BY id DESC LIMIT 1"
		);

		$last_feed = array(
			'feed_name'    => '',
			'started_at'   => '',
			'status'       => '',
			'status_label' => '',
		);

		if ( $last ) {
			$last_feed['feed_name']    = (string) $last->feed_name;
			$last_feed['started_at']   = (string) $last->started_at;
			$last_feed['status']       = (string) $last->status;
			$last_feed['status_label'] = self::feed_status_label( $last->status );
		}

		return array(
			'property_count'      => $property_count,
			'properties_24h'      => $properties_24h,
			'subscriber_count'    => $subscriber_count,
			'alerts_sent_today'   => $alerts_sent_today,
			'last_feed'           => $last_feed,
		);
	}

	/**
	 * @param mixed $status Raw status from DB.
	 */
	public static function feed_status_label( $status ): string {
		switch ( (string) $status ) {
			case 'completed':
				return __( 'Completed', 'lpnw-alerts' );
			case 'running':
				return __( 'Running', 'lpnw-alerts' );
			case 'failed':
				return __( 'Failed', 'lpnw-alerts' );
			default:
				return (string) $status;
		}
	}

	/**
	 * Whether wp-cron is disabled (expects external cron).
	 */
	public static function is_wp_cron_disabled(): bool {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	}

	/**
	 * Next run timestamps for each scheduled LPNW cron hook.
	 *
	 * @return array<string, array{label: string, next: int|false}>
	 */
	public static function get_cron_schedule_summary(): array {
		$hooks = array(
			'lpnw_cron_portals'         => __( 'Property portals (Rightmove, Zoopla, OnTheMarket)', 'lpnw-alerts' ),
			'lpnw_cron_planning'        => __( 'Planning Portal', 'lpnw-alerts' ),
			'lpnw_cron_epc'             => __( 'EPC Open Data', 'lpnw-alerts' ),
			'lpnw_cron_landregistry'    => __( 'Land Registry', 'lpnw-alerts' ),
			'lpnw_cron_auctions'        => __( 'Auction feeds', 'lpnw-alerts' ),
			'lpnw_cron_dispatch_alerts' => __( 'Alert dispatch', 'lpnw-alerts' ),
			'lpnw_cron_free_digest'     => __( 'Free digest email', 'lpnw-alerts' ),
		);

		$out = array();
		foreach ( $hooks as $hook => $label ) {
			$out[ $hook ] = array(
				'label' => $label,
				'next'  => wp_next_scheduled( $hook ),
			);
		}

		return $out;
	}

	/**
	 * Lightweight Mautic API reachability check (uses plugin settings).
	 *
	 * @return array{ok: bool, message: string, code: int|null}
	 */
	public static function get_mautic_connection_status(): array {
		$settings = get_option( 'lpnw_settings', array() );
		$base     = isset( $settings['mautic_api_url'] ) ? trim( (string) $settings['mautic_api_url'] ) : '';
		$user     = isset( $settings['mautic_api_user'] ) ? (string) $settings['mautic_api_user'] : '';
		$pass     = isset( $settings['mautic_api_password'] ) ? (string) $settings['mautic_api_password'] : '';

		if ( '' === $base || '' === $user || '' === $pass ) {
			return array(
				'ok'      => false,
				'message' => __( 'Not configured (URL, user, or password missing).', 'lpnw-alerts' ),
				'code'    => null,
			);
		}

		$url = trailingslashit( $base ) . 'api/contacts';
		$url = add_query_arg( array( 'limit' => 1 ), $url );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard HTTP Basic auth.
					'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
				'code'    => null,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected (HTTP 2xx).', 'lpnw-alerts' ),
				'code'    => $code,
			);
		}

		return array(
			'ok'      => false,
			/* translators: %d: HTTP status code */
			'message' => sprintf( __( 'HTTP error %d', 'lpnw-alerts' ), (int) $code ),
			'code'    => $code,
		);
	}

	/**
	 * Feed log rows with sort and pagination for the LPNW dashboard page.
	 *
	 * @return array{items: array<int, object>, total: int, orderby: string, order: string, paged: int, per_page: int}
	 */
	public static function get_feed_log_page(): array {
		global $wpdb;

		$allowed_orderby = array(
			'feed_name'        => 'feed_name',
			'started_at'       => 'started_at',
			'completed_at'     => 'completed_at',
			'status'           => 'status',
			'properties_found' => 'properties_found',
			'properties_new'   => 'properties_new',
			'errors'           => 'errors',
		);

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'started_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $allowed_orderby[ $orderby ] ) ) {
			$orderby = 'started_at';
		}

		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'ASC' !== $order && 'DESC' !== $order ) {
			$order = 'DESC';
		}

		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = self::FEED_LOG_PER_PAGE;
		$offset   = ( $paged - 1 ) * $per_page;

		$table = $wpdb->prefix . 'lpnw_feed_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( 'errors' === $orderby ) {
			$order_column = 'CHAR_LENGTH(COALESCE(errors, \'\'))';
		} else {
			$order_column = $allowed_orderby[ $orderby ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column names are whitelisted above.
		$sql = "SELECT * FROM {$table} ORDER BY {$order_column} {$order} LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ) );

		return array(
			'items'    => is_array( $items ) ? $items : array(),
			'total'    => $total,
			'orderby'  => $orderby,
			'order'    => $order,
			'paged'    => $paged,
			'per_page' => $per_page,
		);
	}

	/**
	 * Build sort URL for feed log table headers.
	 *
	 * @param string      $column          Orderby key.
	 * @param string|null $current_orderby Current orderby (pass from page data to avoid extra queries).
	 * @param string|null $current_order   ASC or DESC.
	 */
	public static function feed_log_sort_url( string $column, ?string $current_orderby = null, ?string $current_order = null ): string {
		if ( null === $current_orderby || null === $current_order ) {
			$page              = self::get_feed_log_page();
			$current_orderby   = $page['orderby'];
			$current_order     = $page['order'];
		}

		$desc_default = array( 'started_at', 'completed_at', 'properties_found', 'properties_new', 'errors' );

		if ( $column === $current_orderby ) {
			$new_order = ( 'ASC' === $current_order ) ? 'DESC' : 'ASC';
		} else {
			$new_order = in_array( $column, $desc_default, true ) ? 'DESC' : 'ASC';
		}

		return add_query_arg(
			array(
				'page'    => 'lpnw-dashboard',
				'orderby' => $column,
				'order'   => $new_order,
				'paged'   => 1,
			),
			admin_url( 'admin.php' )
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
			array( __CLASS__, 'render_mautic_section_intro' ),
			'lpnw-settings'
		);

		$feed_fields = array(
			'portals_enabled'      => 'Enable portal feeds (Rightmove, Zoopla, OnTheMarket)',
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
	 * Help text under Mautic settings: merge tokens for alert emails.
	 */
	public static function render_mautic_section_intro(): void {
		echo '<p class="description">';
		esc_html_e(
			'When sending via Mautic, your email templates can use these tokens on send: {lpnw_subscriber_first_name}, {lpnw_alert_count}, {lpnw_tier}, {lpnw_properties_html} (listing summary). Add matching tokens in Mautic or use the HTML block token for the full list.',
			'lpnw-alerts'
		);
		echo '</p>';
	}

	/**
	 * @param array<string, mixed> $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $input ): array {
		$prev = get_option( 'lpnw_settings', array() );
		if ( ! is_array( $prev ) ) {
			$prev = array();
		}

		$sanitized = array();

		$checkboxes = array(
			'portals_enabled',
			'planning_enabled',
			'epc_enabled',
			'landregistry_enabled',
			'auctions_enabled',
		);
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

		return array_merge( $prev, $sanitized );
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
			if ( 'portals_enabled' === $key && ! array_key_exists( 'portals_enabled', $settings ) ) {
				$value = true;
			}
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
		$snapshot   = self::get_dashboard_snapshot();
		$feed_log   = self::get_feed_log_page();
		$cron_rows  = self::get_cron_schedule_summary();
		$mautic     = self::get_mautic_connection_status();
		$wp_cron_off = self::is_wp_cron_disabled();

		global $wpdb;
		$alerts_queued = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'queued'" );
		$alerts_sent_all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'sent'" );

		include LPNW_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function render_settings(): void {
		$lpnw_mautic_email_catalog = array();
		if ( class_exists( 'LPNW_Mautic' ) ) {
			$lpnw_mautic_client = new LPNW_Mautic();
			if ( $lpnw_mautic_client->is_configured() ) {
				$lpnw_mautic_email_catalog = $lpnw_mautic_client->list_channel_emails_for_admin( 50 );
			}
		}

		include LPNW_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public static function render_feeds(): void {
		include LPNW_PLUGIN_DIR . 'admin/views/feeds.php';
	}

	public static function render_alert_log(): void {
		include LPNW_PLUGIN_DIR . 'admin/views/alert-log.php';
	}

	public static function render_add_off_market(): void {
		$lpnw_om_notice = get_transient( self::TRANSIENT_ADD_OFF_MARKET_NOTICE . '_' . get_current_user_id() );
		if ( is_array( $lpnw_om_notice ) ) {
			delete_transient( self::TRANSIENT_ADD_OFF_MARKET_NOTICE . '_' . get_current_user_id() );
		} else {
			$lpnw_om_notice = null;
		}

		include LPNW_PLUGIN_DIR . 'admin/views/add-off-market.php';
	}

	/**
	 * Create off_market property row and queue matching alerts.
	 */
	public static function handle_add_off_market(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to add off-market deals.', 'lpnw-alerts' ) );
		}

		check_admin_referer( self::ADD_OFF_MARKET_NONCE_ACTION );

		$redirect_base = admin_url( 'admin.php?page=lpnw-off-market' );

		$address = isset( $_POST['lpnw_address'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_address'] ) ) : '';
		$postcode = isset( $_POST['lpnw_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_postcode'] ) ) : '';

		if ( '' === $address || '' === $postcode ) {
			self::set_add_off_market_notice(
				'error',
				__( 'Address and postcode are required.', 'lpnw-alerts' )
			);
			wp_safe_redirect( $redirect_base );
			exit;
		}

		if ( ! self::postcode_is_nw( $postcode ) ) {
			self::set_add_off_market_notice(
				'error',
				__( 'Postcode must be in the Northwest coverage area.', 'lpnw-alerts' )
			);
			wp_safe_redirect( $redirect_base );
			exit;
		}

		$allowed_types = array( 'Detached', 'Semi-detached', 'Terraced', 'Flat/Maisonette', 'Auction lot', 'Other' );
		$property_type = isset( $_POST['lpnw_property_type'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_property_type'] ) ) : '';
		if ( '' === $property_type || ! in_array( $property_type, $allowed_types, true ) ) {
			self::set_add_off_market_notice(
				'error',
				__( 'Please choose a property type.', 'lpnw-alerts' )
			);
			wp_safe_redirect( $redirect_base );
			exit;
		}

		$allowed_tenure = array( '', 'freehold', 'leasehold', 'share of freehold' );
		$tenure = isset( $_POST['lpnw_tenure'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_tenure'] ) ) : '';
		if ( ! in_array( $tenure, $allowed_tenure, true ) ) {
			$tenure = '';
		}

		$application_type = isset( $_POST['lpnw_application_type'] ) ? sanitize_key( wp_unslash( $_POST['lpnw_application_type'] ) ) : 'sale';
		if ( 'rent' !== $application_type ) {
			$application_type = 'sale';
		}

		$price = isset( $_POST['lpnw_price'] ) ? absint( $_POST['lpnw_price'] ) : 0;
		$price = $price > 0 ? $price : null;

		$bedrooms = null;
		if ( isset( $_POST['lpnw_bedrooms'] ) && '' !== $_POST['lpnw_bedrooms'] ) {
			$bedrooms = min( 50, max( 0, absint( $_POST['lpnw_bedrooms'] ) ) );
		}

		$bathrooms = null;
		if ( isset( $_POST['lpnw_bathrooms'] ) && '' !== $_POST['lpnw_bathrooms'] ) {
			$bathrooms = min( 50, max( 0, absint( $_POST['lpnw_bathrooms'] ) ) );
		}

		$description = isset( $_POST['lpnw_description'] ) ? wp_kses_post( wp_unslash( $_POST['lpnw_description'] ) ) : '';
		$agent_name = isset( $_POST['lpnw_agent_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_agent_name'] ) ) : '';
		$agent_contact = isset( $_POST['lpnw_agent_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['lpnw_agent_contact'] ) ) : '';
		$off_reason = isset( $_POST['lpnw_off_market_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lpnw_off_market_reason'] ) ) : '';
		$image_url = isset( $_POST['lpnw_image_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['lpnw_image_url'] ) ) ) : '';

		$raw_payload = array(
			'lpnw_off_market'   => true,
			'agent_contact'     => $agent_contact,
			'off_market_reason' => $off_reason,
		);
		if ( '' !== $image_url ) {
			$raw_payload['imageUrl'] = $image_url;
		}

		$source_ref = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'om_', true );

		$data = array(
			'source'           => 'off_market',
			'source_ref'       => $source_ref,
			'address'          => $address,
			'postcode'         => $postcode,
			'price'            => $price,
			'property_type'    => $property_type,
			'bedrooms'         => $bedrooms,
			'bathrooms'        => $bathrooms,
			'tenure_type'      => $tenure,
			'description'      => $description,
			'application_type' => $application_type,
			'agent_name'       => $agent_name,
			'key_features_text' => '',
			'source_url'       => '',
			'raw_data'         => $raw_payload,
		);

		if ( 'rent' === $application_type ) {
			$data['price_frequency'] = 'pcm';
		}

		if ( ! empty( $data['postcode'] ) && class_exists( 'LPNW_Geocoder' ) ) {
			$coords = LPNW_Geocoder::geocode( $data['postcode'] );
			if ( null !== $coords ) {
				$data['latitude']  = $coords['latitude'];
				$data['longitude'] = $coords['longitude'];
			}
		}

		$property_id = LPNW_Property::upsert( $data );

		if ( ! $property_id ) {
			self::set_add_off_market_notice(
				'error',
				__( 'Could not save the property. Check required fields and try again.', 'lpnw-alerts' )
			);
			wp_safe_redirect( $redirect_base );
			exit;
		}

		$matcher = new LPNW_Matcher();
		$queued  = $matcher->match_and_queue( array( (int) $property_id ) );

		self::set_add_off_market_notice(
			'success',
			sprintf(
				/* translators: 1: property ID, 2: number of queued alert rows */
				__( 'Off-market deal saved (ID %1$d). Matcher queued %2$d alert row(s).', 'lpnw-alerts' ),
				(int) $property_id,
				(int) $queued
			)
		);

		wp_safe_redirect( $redirect_base );
		exit;
	}

	/**
	 * @param string $type success|error.
	 */
	private static function set_add_off_market_notice( string $type, string $text ): void {
		set_transient(
			self::TRANSIENT_ADD_OFF_MARKET_NOTICE . '_' . get_current_user_id(),
			array(
				'type' => $type,
				'text' => $text,
			),
			120
		);
	}

	/**
	 * Whether a postcode outward code is in LPNW_NW_POSTCODES.
	 */
	private static function postcode_is_nw( string $postcode ): bool {
		$postcode = strtoupper( trim( $postcode ) );
		if ( '' === $postcode ) {
			return false;
		}

		foreach ( LPNW_NW_POSTCODES as $prefix ) {
			if ( ! str_starts_with( $postcode, $prefix ) ) {
				continue;
			}
			$next = substr( $postcode, strlen( $prefix ), 1 );
			if ( ctype_digit( $next ) || ' ' === $next ) {
				return true;
			}
		}

		return false;
	}
}
