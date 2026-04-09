<?php
// phpcs:ignoreFile -- Standalone CLI maintenance script; not loaded by WordPress.
/**
 * Update an existing Mautic email template body via REST API (customHtml).
 *
 * Usage:
 *   php tools/mautic-patch-email-html.php <emailId> <file>
 *
 * <file> may be raw .html/.htm or a JSON file containing a "value" string (e.g. tools/_mautic-fill-pro.json).
 *
 * Env or repo root .env: MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS.
 *
 * @package LPNW_Tools
 */

$root = dirname( __DIR__ );

if ( $argc < 3 ) {
	fwrite( STDERR, "Usage: php tools/mautic-patch-email-html.php <emailId> <html-or-json-file>\n" );
	exit( 1 );
}

$email_id    = (int) $argv[1];
$source_file = $argv[2];
if ( ! is_readable( $source_file ) && is_readable( $root . '/' . ltrim( $source_file, '/' ) ) ) {
	$source_file = $root . '/' . ltrim( $source_file, '/' );
}
if ( $email_id < 1 || ! is_readable( $source_file ) ) {
	fwrite( STDERR, "Invalid id or unreadable file.\n" );
	exit( 1 );
}

$mautic_url = getenv( 'MAUTIC_URL' );
$user       = getenv( 'MAUTIC_USER' );
$pass       = getenv( 'MAUTIC_PASS' );

if ( is_readable( $root . '/.env' ) ) {
	foreach ( file( $root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		if ( preg_match( '/^([A-Z_]+)=(.*)$/', $line, $m ) ) {
			$k = $m[1];
			$v = $m[2];
			if ( 'MAUTIC_URL' === $k && ! $mautic_url ) {
				$mautic_url = $v;
			}
			if ( 'MAUTIC_USER' === $k && ! $user ) {
				$user = $v;
			}
			if ( 'MAUTIC_PASS' === $k && ! $pass ) {
				$pass = $v;
			}
		}
	}
}

if ( ! $mautic_url || ! $user || ! $pass ) {
	fwrite( STDERR, "Set MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS (or .env).\n" );
	exit( 1 );
}

$base = rtrim( $mautic_url, '/' ) . '/';

$raw = file_get_contents( $source_file );
if ( false === $raw ) {
	fwrite( STDERR, "Could not read file.\n" );
	exit( 1 );
}
if ( strncmp( $raw, "\xEF\xBB\xBF", 3 ) === 0 ) {
	$raw = substr( $raw, 3 );
}

$lower = strtolower( $source_file );
if ( str_ends_with( $lower, '.json' ) ) {
	$dec = json_decode( $raw, true );
	if ( ! is_array( $dec ) || ! isset( $dec['value'] ) || ! is_string( $dec['value'] ) ) {
		fwrite( STDERR, "JSON file must contain a string \"value\" key.\n" );
		exit( 1 );
	}
	$html = $dec['value'];
} else {
	$html = $raw;
}

$payload = json_encode(
	array( 'customHtml' => $html ),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if ( false === $payload ) {
	fwrite( STDERR, "JSON encode failed.\n" );
	exit( 1 );
}

/**
 * @param string $method PATCH|PUT|POST.
 */
function lpnw_mautic_json_request( string $user, string $pass, string $method, string $url, string $body ): array {
	if ( ! function_exists( 'curl_init' ) ) {
		fwrite( STDERR, "PHP curl extension required.\n" );
		exit( 1 );
	}
	$ch = curl_init( $url );
	if ( false === $ch ) {
		return array( 0, '' );
	}
	$opts = array(
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_POSTFIELDS     => $body,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERPWD        => $user . ':' . $pass,
		CURLOPT_HTTPHEADER     => array(
			'Accept: application/json',
			'Content-Type: application/json; charset=utf-8',
		),
		CURLOPT_TIMEOUT        => 60,
	);
	// Windows PHP often lacks a CA bundle; set LPNW_TOOLS_CURL_INSECURE=1 to skip verify (dev only).
	if ( '1' === getenv( 'LPNW_TOOLS_CURL_INSECURE' ) ) {
		$opts[ CURLOPT_SSL_VERIFYPEER ] = false;
		$opts[ CURLOPT_SSL_VERIFYHOST ] = 0;
	}
	curl_setopt_array( $ch, $opts );
	$resp = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$err  = curl_error( $ch );
	curl_close( $ch );
	if ( '' !== $err ) {
		fwrite( STDERR, "cURL: {$err}\n" );
	}
	return array( $code, is_string( $resp ) ? $resp : '' );
}

// Mautic REST: PATCH .../api/emails/{id}/edit
$url  = $base . 'api/emails/' . $email_id . '/edit';
list( $code, $resp ) = lpnw_mautic_json_request( $user, $pass, 'PATCH', $url, $payload );

if ( 405 === $code || 404 === $code ) {
	list( $code, $resp ) = lpnw_mautic_json_request( $user, $pass, 'PUT', $url, $payload );
}

echo "PATCH/PUT email {$email_id} -> HTTP {$code}\n";
if ( $code < 200 || $code >= 300 ) {
	echo $resp . "\n";
	exit( 1 );
}

echo "OK\n";
