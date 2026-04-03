<?php
/**
 * Routes WordPress cron spawn requests through the LPNW mu-plugin endpoint.
 *
 * On hosts where wp-cron.php is blocked, spawn_cron() still fires but the HTTP
 * request fails. This filter swaps the request URL to ?lpnw_cron=tick (same as
 * external cron), preserving doing_wp_cron and adding key when required.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Cron_Request {

	public static function init(): void {
		add_filter( 'cron_request', array( __CLASS__, 'filter_cron_request' ), 10, 1 );
	}

	/**
	 * @param array<string, mixed> $cron_request Array with url, key, args.
	 * @return array<string, mixed>
	 */
	public static function filter_cron_request( array $cron_request ): array {
		$original_url = isset( $cron_request['url'] ) ? (string) $cron_request['url'] : '';
		if ( '' === $original_url ) {
			return $cron_request;
		}

		$parsed = wp_parse_url( $original_url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return $cron_request;
		}

		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! is_string( $site_host ) || '' === $site_host ) {
			return $cron_request;
		}

		if ( ! hash_equals( strtolower( $parsed['host'] ), strtolower( $site_host ) ) ) {
			return $cron_request;
		}

		$path = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
		if ( ! str_ends_with( strtolower( $path ), 'wp-cron.php' ) ) {
			return $cron_request;
		}

		$query_params = array();
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( (string) $parsed['query'], $query_params );
		}

		$new_args = array(
			'lpnw_cron' => 'tick',
		);
		if ( isset( $query_params['doing_wp_cron'] ) && '' !== (string) $query_params['doing_wp_cron'] ) {
			$new_args['doing_wp_cron'] = (string) $query_params['doing_wp_cron'];
		}

		if ( defined( 'LPNW_CRON_SECRET' ) && '' !== (string) LPNW_CRON_SECRET ) {
			$new_args['key'] = (string) LPNW_CRON_SECRET;
		}

		$cron_request['url'] = add_query_arg( $new_args, home_url( '/' ) );

		return $cron_request;
	}

	/**
	 * URL for EasyCron and similar (no doing_wp_cron; wp_cron() runs due hooks).
	 *
	 * @return string
	 */
	public static function get_external_ping_url(): string {
		$args = array( 'lpnw_cron' => 'tick' );
		if ( defined( 'LPNW_CRON_SECRET' ) && '' !== (string) LPNW_CRON_SECRET ) {
			$args['key'] = (string) LPNW_CRON_SECRET;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}
}
