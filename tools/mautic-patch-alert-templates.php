<?php
/**
 * PATCH existing Mautic template emails with branded HTML from mautic-seed-alert-emails.php.
 * Reads MAUTIC_* from env or repo .env. Does not create new emails or change IDs.
 *
 * Run: php tools/mautic-patch-alert-templates.php
 *
 * @package LPNW_Tools
 */

$root = dirname( __DIR__ );

$mautic_url = getenv( 'MAUTIC_URL' );
$user       = getenv( 'MAUTIC_USER' );
$pass       = getenv( 'MAUTIC_PASS' );

if ( is_readable( $root . '/.env' ) ) {
	foreach ( file( $root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || str_starts_with( $line, '#' ) ) {
			continue;
		}
		if ( ! preg_match( '/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m ) ) {
			continue;
		}
		$k = strtoupper( $m[1] );
		$v = trim( $m[2] );
		if ( ( str_starts_with( $v, '"' ) && str_ends_with( $v, '"' ) ) || ( str_starts_with( $v, "'" ) && str_ends_with( $v, "'" ) ) ) {
			$v = substr( $v, 1, -1 );
		}
		if ( ( 'MAUTIC_URL' === $k || 'MAUTIC_API_URL' === $k ) && ! $mautic_url ) {
			$mautic_url = $v;
		}
		if ( ( 'MAUTIC_USER' === $k || 'MAUTIC_API_USER' === $k ) && ! $user ) {
			$user = $v;
		}
		if ( ( 'MAUTIC_PASS' === $k || 'MAUTIC_API_PASSWORD' === $k || 'MAUTIC_API_PASS' === $k ) && ! $pass ) {
			$pass = $v;
		}
	}
}

if ( ! $mautic_url || ! $user || ! $pass ) {
	fwrite( STDERR, "Set MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS (or .env).\n" );
	exit( 1 );
}

require_once __DIR__ . '/mautic-seed-alert-emails.php';

$base = rtrim( $mautic_url, '/' ) . '/';

/**
 * Normalize template name for matching (same idea as LPNW_Mautic::normalize_email_name_key).
 */
function lpnw_patch_normalize_name_key( string $name ): string {
	$n = strtolower( $name );
	$n = str_replace( array( '—', '–', '-' ), '', $n );
	$n = preg_replace( '/\s+/', '', $n );

	return is_string( $n ) ? $n : '';
}

/**
 * @return array{code:int,body:string}
 */
function lpnw_mautic_request( string $user, string $pass, string $method, string $url, ?array $json = null ): array {
	$ch = curl_init( $url );
	$opts = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERPWD        => $user . ':' . $pass,
		CURLOPT_HTTPHEADER     => array( 'Accept: application/json' ),
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_CUSTOMREQUEST  => $method,
	);
	if ( null !== $json ) {
		$opts[ CURLOPT_HTTPHEADER ][] = 'Content-Type: application/json; charset=utf-8';
		$opts[ CURLOPT_POSTFIELDS ]    = json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
	curl_setopt_array( $ch, $opts );
	$body = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	return array(
		'code' => $code,
		'body' => is_string( $body ) ? $body : '',
	);
}

$targets = array(
	'vip'  => array(
		'match' => static function ( string $key ): bool {
			return str_contains( $key, 'lpnwalertvip' ) || ( str_contains( $key, 'alert' ) && str_contains( $key, 'vip' ) );
		},
		'html'    => lpnw_mautic_body( 'VIP' ),
		'subject' => 'VIP: {lpnw_alert_count} new property match(es)',
	),
	'pro'  => array(
		'match' => static function ( string $key ): bool {
			return str_contains( $key, 'lpnwalertpro' ) || ( str_contains( $key, 'alert' ) && str_contains( $key, 'pro' ) && ! str_contains( $key, 'prospect' ) );
		},
		'html'    => lpnw_mautic_body( 'Pro' ),
		'subject' => 'Pro: {lpnw_alert_count} new property match(es)',
	),
	'free' => array(
		'match' => static function ( string $key ): bool {
			return str_contains( $key, 'weeklydigest' ) && str_contains( $key, 'free' );
		},
		'html'    => lpnw_mautic_body_free(),
		'subject' => 'Your weekly NW property digest',
	),
);

$list = lpnw_mautic_request( $user, $pass, 'GET', $base . 'api/emails?limit=200' );
if ( $list['code'] < 200 || $list['code'] >= 300 ) {
	fwrite( STDERR, 'List emails failed HTTP ' . $list['code'] . "\n" . $list['body'] . "\n" );
	exit( 1 );
}

$decoded = json_decode( $list['body'], true );
if ( ! is_array( $decoded ) || empty( $decoded['emails'] ) ) {
	fwrite( STDERR, "No emails in API response.\n" );
	exit( 1 );
}

$found = array( 'vip' => 0, 'pro' => 0, 'free' => 0 );

foreach ( $decoded['emails'] as $row ) {
	if ( ! is_array( $row ) ) {
		continue;
	}
	$id   = isset( $row['id'] ) ? (int) $row['id'] : 0;
	$name = isset( $row['name'] ) ? (string) $row['name'] : '';
	if ( $id < 1 || '' === $name ) {
		continue;
	}
	$key = lpnw_patch_normalize_name_key( $name );

	foreach ( $targets as $tier => $def ) {
		if ( $found[ $tier ] > 0 ) {
			continue;
		}
		if ( ! $def['match']( $key ) ) {
			continue;
		}

		$payload = array(
			'customHtml' => $def['html'],
			'subject'    => $def['subject'],
		);

		$edit = lpnw_mautic_request( $user, $pass, 'PATCH', $base . 'api/emails/' . $id . '/edit', $payload );
		echo sprintf( "PATCH id=%d name=%s -> HTTP %d\n", $id, $name, $edit['code'] );
		if ( $edit['code'] < 200 || $edit['code'] >= 300 ) {
			echo $edit['body'] . "\n";
		}
		$found[ $tier ] = $id;
		break;
	}
}

foreach ( $found as $tier => $id ) {
	if ( $id < 1 ) {
		fwrite( STDERR, "Warning: no email matched for tier {$tier}. Create or rename in Mautic to match LPNW naming.\n" );
	}
}

exit( 0 );
