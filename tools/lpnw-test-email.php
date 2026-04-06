<?php
/**
 * Plugin Name: LPNW Email Template Test
 * Description: Upload to wp-content/mu-plugins/. Preview: ?lpnw_test_email=preview&key=YOUR_LPNW_SECRET (see docs/DEPLOYMENT.md) — remove after use.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action( 'template_redirect', 'lpnw_test_email_maybe_run', 1 );

/**
 * Handle preview and send test email requests.
 */
function lpnw_test_email_maybe_run(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['lpnw_test_email'], $_GET['key'] ) ) {
		return;
	}

	$tool_key = sanitize_text_field( wp_unslash( $_GET['key'] ) );

	if ( ! lpnw_tool_query_key_ok( $tool_key ) ) {
		return;
	}

	$action = sanitize_text_field( wp_unslash( $_GET['lpnw_test_email'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( 'send' === $action ) {
		lpnw_test_email_send_and_remove();
		return;
	}

	if ( 'preview' !== $action ) {
		return;
	}

	$tpl_dir = WP_PLUGIN_DIR . '/lpnw-property-alerts/templates/';
	if ( ! is_dir( $tpl_dir ) ) {
		wp_die( esc_html__( 'LPNW plugin templates directory not found.', 'lpnw-alerts' ) );
	}

	$properties = lpnw_test_email_fetch_recent_properties( 5 );

	$subscriber_name  = 'Test User';
	$alert_properties = $properties;
	$dashboard_url    = home_url( '/dashboard/' );
	$unsubscribe_url  = home_url( '/dashboard/?tab=preferences' );

	$instant = lpnw_test_email_render_template( $tpl_dir . 'email-instant-alert.html', $subscriber_name, $alert_properties, $dashboard_url, $unsubscribe_url );
	$daily   = lpnw_test_email_render_template( $tpl_dir . 'email-daily-digest.html', $subscriber_name, $alert_properties, $dashboard_url, $unsubscribe_url );
	$weekly  = lpnw_test_email_render_template( $tpl_dir . 'email-weekly-digest.html', $subscriber_name, $alert_properties, $dashboard_url, $unsubscribe_url );

	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );

	$send_url = esc_url(
		add_query_arg(
			array(
				'lpnw_test_email' => 'send',
				'key'             => $tool_key,
			),
			home_url( '/' )
		)
	);

	echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>LPNW email template preview</title>';
	echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;margin:0;padding:24px;background:#e5e7eb;color:#111;}';
	echo '.label{margin:24px 0 8px;font-size:18px;font-weight:700;}hr{border:0;border-top:2px solid #9ca3af;margin:0 0 24px;}iframe{width:100%;min-height:720px;border:1px solid #9ca3af;background:#fff;border-radius:8px;}';
	echo '.tools{margin-top:32px;padding-top:24px;border-top:2px solid #9ca3af;}a.send{color:#1b2a4a;font-weight:600;}</style></head><body>';
	echo '<h1 style="margin-top:0;">LPNW email template preview</h1>';
	echo '<p>Using ' . esc_html( (string) count( $properties ) ) . ' most recent properties from the database.</p>';

	lpnw_test_email_print_preview_block( 'email-instant-alert.html', $instant );
	lpnw_test_email_print_preview_block( 'email-daily-digest.html', $daily );
	lpnw_test_email_print_preview_block( 'email-weekly-digest.html', $weekly );

	echo '<div class="tools"><p><a class="send" href="' . $send_url . '">Send test email to admin</a></p>';
	echo '<p style="font-size:14px;color:#4b5563;">Sends the instant alert HTML via <code>wp_mail</code> to the site admin, then removes this mu-plugin file.</p></div>';
	echo '</body></html>';
	exit;
}

/**
 * Output a labeled preview block with iframe.
 *
 * @param string $filename Template filename for label.
 * @param string $html     Full rendered email HTML.
 */
function lpnw_test_email_print_preview_block( string $filename, string $html ): void {
	echo '<p class="label">' . esc_html( $filename ) . '</p><hr>';
	$srcdoc = htmlspecialchars( $html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	echo '<iframe title="' . esc_attr( $filename ) . '" srcdoc="' . $srcdoc . '"></iframe>';
}

/**
 * Fetch recent properties (same shape as alert emails expect).
 *
 * @param int $limit Row limit.
 * @return array<int, object>
 */
function lpnw_test_email_fetch_recent_properties( int $limit ): array {
	if ( class_exists( 'LPNW_Property' ) ) {
		return LPNW_Property::query( array(), $limit, 0 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_properties';

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$limit
	) );
}

/**
 * Include an email template and return buffered HTML.
 *
 * @param string               $path             Absolute path to template.
 * @param string               $subscriber_name  Greeting name.
 * @param array<int, object>   $alert_properties Property rows.
 * @param string               $dashboard_url    Dashboard URL.
 * @param string               $unsubscribe_url  Preferences / unsubscribe URL.
 */
function lpnw_test_email_render_template( string $path, string $subscriber_name, array $alert_properties, string $dashboard_url, string $unsubscribe_url ): string {
	if ( ! is_readable( $path ) ) {
		return '<p>Missing template: ' . esc_html( basename( $path ) ) . '</p>';
	}

	ob_start();
	include $path;

	return ob_get_clean();
}

/**
 * Send instant alert to admin and delete this file.
 */
function lpnw_test_email_send_and_remove(): void {
	$tpl_dir = WP_PLUGIN_DIR . '/lpnw-property-alerts/templates/';
	$path    = $tpl_dir . 'email-instant-alert.html';

	if ( ! is_readable( $path ) ) {
		wp_die( esc_html__( 'Instant alert template not found.', 'lpnw-alerts' ) );
	}

	$properties = lpnw_test_email_fetch_recent_properties( 5 );

	$subscriber_name  = 'Test User';
	$alert_properties = $properties;
	$dashboard_url    = home_url( '/dashboard/' );
	$unsubscribe_url  = home_url( '/dashboard/?tab=preferences' );

	$body = lpnw_test_email_render_template( $path, $subscriber_name, $alert_properties, $dashboard_url, $unsubscribe_url );

	$count   = count( $properties );
	$subject = sprintf(
		/* translators: %d: number of properties in test email */
		_n( '[LPNW test] %d new NW property alert', '[LPNW test] %d new NW property alerts', $count, 'lpnw-alerts' ),
		$count
	);

	$admin_email = get_option( 'admin_email' );
	$headers     = array( 'Content-Type: text/html; charset=UTF-8' );

	$sent = wp_mail( $admin_email, $subject, $body, $headers );

	$self = __FILE__;
	if ( $sent && is_string( $self ) && is_writable( $self ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- intentional one-off mu-plugin removal.
		unlink( $self );
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );

	if ( $sent ) {
		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Test email sent</title></head><body style="font-family:sans-serif;padding:24px;">';
		echo '<p>Test email sent to <strong>' . esc_html( $admin_email ) . '</strong>.</p>';
		echo '<p>The preview script file has been removed from the server.</p></body></html>';
	} else {
		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Send failed</title></head><body style="font-family:sans-serif;padding:24px;">';
		echo '<p><strong>wp_mail</strong> returned false. The script file was not deleted so you can retry.</p></body></html>';
	}
	exit;
}
