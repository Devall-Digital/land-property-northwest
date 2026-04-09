<?php
/**
 * Create segment + segment email "LPNW Prospect intro" if missing (prospect blast).
 *
 * Uses curl binary (curl.exe on Windows) for HTTPS — PHP's curl may fail TLS on some hosts.
 *
 * Env or repo root .env: MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS.
 * Optional: MAUTIC_PROSPECT_SEGMENT_ID (default: looks up alias lpnw-mailable-prospects-intro, else 2).
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
	fwrite( STDERR, "Set MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS (or .env).\n" );
	exit( 1 );
}

$base = rtrim( $mautic_url, '/' ) . '/';

/**
 * Run curl -u user:pass URL, return [ http_code, body ].
 *
 * @param string $method GET|POST.
 * @param string $url    Full URL.
 * @param string $body   Optional JSON body.
 */
function lpnw_mautic_curl( string $user, string $pass, string $method, string $url, string $body = '' ): array {
	$bin = ( 0 === stripos( PHP_OS, 'WIN' ) ) ? 'curl.exe' : 'curl';
	$tmp = '';
	$args = array(
		$bin,
		'-sS',
		'-w', "\n%{http_code}",
		'-u', $user . ':' . $pass,
		'-H', 'Accept: application/json',
	);
	if ( 'POST' === $method ) {
		$tmp = tempnam( sys_get_temp_dir(), 'lpnwmj' );
		file_put_contents( $tmp, $body );
		$args[] = '-X';
		$args[] = 'POST';
		$args[] = '-H';
		$args[] = 'Content-Type: application/json; charset=utf-8';
		$args[] = '--data-binary';
		$args[] = '@' . $tmp;
	}
	$args[] = $url;
	$cmd    = '';
	foreach ( $args as $a ) {
		$cmd .= ( '' === $cmd ? '' : ' ' ) . escapeshellarg( $a );
	}
	$out = shell_exec( $cmd );
	if ( $tmp && is_file( $tmp ) ) {
		unlink( $tmp );
	}
	if ( ! is_string( $out ) || '' === $out ) {
		return array( 0, '' );
	}
	$pos  = strrpos( $out, "\n" );
	$body_out = false !== $pos ? substr( $out, 0, $pos ) : $out;
	$code     = false !== $pos ? (int) trim( substr( $out, $pos + 1 ) ) : 0;
	return array( $code, $body_out );
}

list( $ec, $raw_emails ) = lpnw_mautic_curl( $user, $pass, 'GET', $base . 'api/emails?limit=200' );
$ed = json_decode( $raw_emails, true );
if ( is_array( $ed ) && ! empty( $ed['emails'] ) ) {
	foreach ( $ed['emails'] as $e ) {
		if ( ! is_array( $e ) ) {
			continue;
		}
		if ( isset( $e['name'] ) && false !== stripos( (string) $e['name'], 'prospect intro' ) ) {
			echo "Already exists: {$e['name']} (id " . (int) ( $e['id'] ?? 0 ) . ")\n";
			exit( 0 );
		}
	}
}

$segment_id = (int) getenv( 'MAUTIC_PROSPECT_SEGMENT_ID' );
if ( $segment_id < 1 ) {
	list( $sc, $raw_seg ) = lpnw_mautic_curl( $user, $pass, 'GET', $base . 'api/segments?limit=200' );
	$sd = json_decode( $raw_seg, true );
	if ( is_array( $sd ) && ! empty( $sd['lists'] ) ) {
		foreach ( $sd['lists'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['alias'] ) && 'lpnw-mailable-prospects-intro' === $row['alias'] ) {
				$segment_id = (int) ( $row['id'] ?? 0 );
				break;
			}
		}
	}
}
if ( $segment_id < 1 ) {
	$segment_id = 2;
}

$html_file = $root . '/docs/mautic-prospect-intro-email.html';
if ( ! is_readable( $html_file ) ) {
	fwrite( STDERR, "Missing $html_file\n" );
	exit( 1 );
}
$html_raw = (string) file_get_contents( $html_file );
$html_raw = preg_replace( '/^<!--.*?-->/s', '', $html_raw, 1 );
$html_raw = trim( $html_raw );

$payload = array(
	'name'        => 'LPNW Prospect intro',
	'subject'     => 'Northwest land and property alerts for your inbox',
	'emailType'   => 'list',
	'isPublished' => true,
	'customHtml'  => $html_raw,
	'lists'       => array( $segment_id ),
);
$json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

list( $pc, $pr ) = lpnw_mautic_curl( $user, $pass, 'POST', $base . 'api/emails/new', $json );
$pd  = json_decode( $pr, true );
$id  = is_array( $pd ) && isset( $pd['email']['id'] ) ? (int) $pd['email']['id'] : 0;
echo sprintf( "LPNW Prospect intro -> HTTP %d, id=%d (segment %d)\n", $pc, $id, $segment_id );
if ( $pc < 200 || $pc >= 300 || $id < 1 ) {
	echo $pr . "\n";
	exit( 1 );
}
