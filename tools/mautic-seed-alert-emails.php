<?php
/**
 * One-off: create three Mautic template emails for LPNW (uses MAUTIC_* env vars).
 * Run: MAUTIC_URL=... MAUTIC_USER=... MAUTIC_PASS=... php tools/mautic-seed-alert-emails.php
 *
 * @package LPNW_Tools
 */

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

/**
 * @param string $label Tier label for copy.
 */
function lpnw_mautic_body( string $label ): string {
	$safe = htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' );

	return '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;line-height:1.5;color:#1f2937;">'
		. '<p>Hi {lpnw_subscriber_first_name},</p>'
		. '<p>You have <strong>{lpnw_alert_count}</strong> new listing(s) on your <strong>' . $safe . '</strong> plan (tier: {lpnw_tier}).</p>'
		. '{lpnw_properties_html}'
		. '<p style="margin-top:1.5em;"><a href="https://land-property-northwest.co.uk/dashboard/">Open your dashboard</a></p>'
		. '</body></html>';
}

function lpnw_mautic_body_free(): string {
	return '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;line-height:1.5;color:#1f2937;">'
		. '<p>Hi {lpnw_subscriber_first_name},</p>'
		. '<p>Here is your <strong>weekly digest</strong> ({lpnw_alert_count} highlight(s)).</p>'
		. '{lpnw_properties_html}'
		. '<p style="margin-top:1.5em;"><a href="https://land-property-northwest.co.uk/dashboard/">Open your dashboard</a></p>'
		. '</body></html>';
}
