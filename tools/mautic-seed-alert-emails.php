<?php
/**
 * Mautic alert template HTML (single source of truth) plus optional API seed.
 *
 * Recommended: run `php tools/mautic-seed-alert-emails.php --dump-html` locally, copy each
 * `---VIP---` / `---PRO---` / `---FREE---` block into Mautic → edit email → Advanced → HTML Code,
 * then Save. Use the browser with a teammate or agent so you see what Mautic actually stores.
 *
 * Optional (empty Mautic only): `MAUTIC_URL=... MAUTIC_USER=... MAUTIC_PASS=... php tools/mautic-seed-alert-emails.php`
 * creates three new template emails via the API. That assigns new numeric IDs; update WordPress
 * LPNW settings to match. Do not re-run on production if templates already exist unless you intend to.
 *
 * @package LPNW_Tools
 */

/**
 * Shared outer layout (matches plugin wp_mail templates: navy header, gold accents).
 *
 * @param string $preheader_hidden Hidden preview text (plain, no HTML).
 * @param string $greeting_rows    HTML <tr>...</tr> blocks only.
 * @param string $after_properties HTML <tr>...</tr> after property token block.
 */
function lpnw_mautic_email_shell( string $preheader_hidden, string $greeting_rows, string $after_properties = '' ): string {
	$pre = htmlspecialchars( $preheader_hidden, ENT_QUOTES, 'UTF-8' );

	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Land &amp; Property Northwest</title></head>'
		. '<body style="margin:0;padding:0;background:#F7F8FA;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">'
		. '<span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">' . $pre . '</span>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F8FA;padding:20px 0;">'
		. '<tr><td align="center">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:8px;overflow:hidden;border-collapse:collapse;">'
		. '<tr><td style="background:#1B2A4A;padding:24px 32px;">'
		. '<h1 style="margin:0;color:#FFFFFF;font-size:20px;font-weight:700;letter-spacing:-0.02em;">Land &amp; Property Northwest</h1>'
		. '<p style="margin:8px 0 0;font-size:13px;color:#B8C5D9;line-height:1.4;">Northwest listing alerts</p>'
		. '</td></tr>'
		. $greeting_rows
		. '<tr><td style="padding:0;">{lpnw_properties_html}</td></tr>'
		. $after_properties
		. '<tr><td style="padding:24px 32px 32px;" align="center">'
		. '<a href="https://land-property-northwest.co.uk/dashboard/" style="display:inline-block;padding:12px 28px;background:#1B2A4A;color:#FFFFFF;text-decoration:none;border-radius:6px;font-size:16px;font-weight:600;">Open your dashboard</a>'
		. '</td></tr>'
		. '<tr><td style="background:#F7F8FA;padding:20px 32px;border-top:1px solid #E5E7EB;">'
		. '<p style="margin:0;font-size:12px;color:#6B7280;text-align:center;line-height:1.55;">You are receiving this because you subscribed to property alerts on Land &amp; Property Northwest.<br>'
		. 'Manage preferences in your dashboard anytime.</p>'
		. '</td></tr>'
		. '</table></td></tr></table></body></html>';
}

/**
 * @param string $label Tier label for copy.
 */
function lpnw_mautic_body( string $label ): string {
	$safe = htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' );

	$greeting = '<tr><td style="padding:32px 32px 8px;">'
		. '<p style="margin:0;font-size:16px;line-height:1.55;color:#374151;">Hi {lpnw_subscriber_first_name},</p>'
		. '<p style="margin:12px 0 0;font-size:16px;line-height:1.55;color:#374151;">You have <strong style="color:#1B2A4A;">{lpnw_alert_count}</strong> new listing(s) on your <strong style="color:#1B2A4A;">' . $safe . '</strong> plan <span style="color:#6B7280;">(tier: {lpnw_tier})</span>.</p>'
		. '</td></tr>';

	return lpnw_mautic_email_shell(
		'{lpnw_alert_count} new property match - Land & Property Northwest',
		$greeting,
		''
	);
}

/**
 * Free weekly digest template.
 */
function lpnw_mautic_body_free(): string {
	$greeting = '<tr><td style="padding:32px 32px 8px;">'
		. '<p style="margin:0;font-size:16px;line-height:1.55;color:#374151;">Hi {lpnw_subscriber_first_name},</p>'
		. '<p style="margin:12px 0 0;font-size:16px;line-height:1.55;color:#374151;">Here is your <strong style="color:#1B2A4A;">weekly digest</strong> with <strong style="color:#1B2A4A;">{lpnw_alert_count}</strong> highlight(s) from the Northwest.</p>'
		. '</td></tr>';

	return lpnw_mautic_email_shell(
		'Your weekly NW property digest - Land & Property Northwest',
		$greeting,
		''
	);
}

if ( isset( $argv[1] ) && '--dump-html' === $argv[1] ) {
	echo "---VIP---\n";
	echo lpnw_mautic_body( 'VIP' );
	echo "\n---PRO---\n";
	echo lpnw_mautic_body( 'Pro' );
	echo "\n---FREE---\n";
	echo lpnw_mautic_body_free();
	echo "\n";
	exit( 0 );
}

$mautic_url = getenv( 'MAUTIC_URL' );
$user       = getenv( 'MAUTIC_USER' );
$pass       = getenv( 'MAUTIC_PASS' );

if ( ! $mautic_url || ! $user || ! $pass ) {
	fwrite( STDERR, "Set MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS.\n" );
	exit( 1 );
}

$base = rtrim( $mautic_url, '/' ) . '/';

$emails = array(
	array(
		'name'    => 'LPNW Alert — VIP',
		'subject' => 'VIP: {lpnw_alert_count} new property match(es)',
		'html'    => lpnw_mautic_body( 'VIP' ),
	),
	array(
		'name'    => 'LPNW Alert — Pro',
		'subject' => 'Pro: {lpnw_alert_count} new property match(es)',
		'html'    => lpnw_mautic_body( 'Pro' ),
	),
	array(
		'name'    => 'LPNW Weekly Digest — Free',
		'subject' => 'Your weekly NW property digest',
		'html'    => lpnw_mautic_body_free(),
	),
);

foreach ( $emails as $def ) {
	$payload = array(
		'name'        => $def['name'],
		'subject'     => $def['subject'],
		'emailType'   => 'template',
		'isPublished' => true,
		'customHtml'  => $def['html'],
	);

	$ch = curl_init( $base . 'api/emails/new' );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Accept: application/json',
			),
			CURLOPT_USERPWD        => $user . ':' . $pass,
			CURLOPT_POSTFIELDS     => json_encode( $payload ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 45,
		)
	);

	$raw  = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	$data = json_decode( (string) $raw, true );
	$id   = is_array( $data ) && isset( $data['email']['id'] ) ? (int) $data['email']['id'] : 0;

	echo sprintf( "%s -> HTTP %d, id=%d\n", $def['name'], $code, $id );
	if ( $code < 200 || $code >= 300 || $id < 1 ) {
		echo $raw . "\n";
	}
}
