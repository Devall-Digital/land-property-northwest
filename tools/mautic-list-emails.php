<?php
// phpcs:ignoreFile -- Standalone CLI maintenance script; not loaded by WordPress.
/**
 * List Mautic emails (id + name). Env/.env: MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS.
 * Optional: LPNW_TOOLS_CURL_INSECURE=1 if PHP lacks CA bundle (dev only).
 *
 * @package LPNW_Tools
 */

$root = dirname( __DIR__ );

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
	fwrite( STDERR, "Set MAUTIC_* or .env.\n" );
	exit( 1 );
}

$base = rtrim( $mautic_url, '/' ) . '/';
$ch   = curl_init( $base . 'api/emails?limit=200' );
$opts = array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_USERPWD        => $user . ':' . $pass,
	CURLOPT_HTTPHEADER     => array( 'Accept: application/json' ),
	CURLOPT_TIMEOUT        => 45,
);
if ( '1' === getenv( 'LPNW_TOOLS_CURL_INSECURE' ) ) {
	$opts[ CURLOPT_SSL_VERIFYPEER ] = false;
	$opts[ CURLOPT_SSL_VERIFYHOST ] = 0;
}
curl_setopt_array( $ch, $opts );
$raw = curl_exec( $ch );
curl_close( $ch );
$d = json_decode( (string) $raw, true );
if ( ! is_array( $d ) || empty( $d['emails'] ) ) {
	echo $raw . "\n";
	exit( 1 );
}
foreach ( $d['emails'] as $e ) {
	if ( ! is_array( $e ) || ! isset( $e['id'], $e['name'] ) ) {
		continue;
	}
	echo (int) $e['id'] . "\t" . $e['name'] . "\n";
}
