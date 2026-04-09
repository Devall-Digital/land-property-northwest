<?php
/**
 * Minimal Stripe HTTP client (no Composer SDK).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stripe REST calls for LPNW recurring billing.
 */
final class LPNW_Stripe_API {

	private const API_BASE = 'https://api.stripe.com/v1/';

	/**
	 * Secret key: LPNW_STRIPE_SECRET_KEY constant, STRIPE_SECRET_KEY env, or lpnw_settings stripe_api_secret.
	 *
	 * @return string Empty if not configured.
	 */
	public static function get_secret_key(): string {
		if ( defined( 'LPNW_STRIPE_SECRET_KEY' ) && is_string( LPNW_STRIPE_SECRET_KEY ) && '' !== LPNW_STRIPE_SECRET_KEY ) {
			return LPNW_STRIPE_SECRET_KEY;
		}

		$env = getenv( 'STRIPE_SECRET_KEY' );
		if ( is_string( $env ) && '' !== $env ) {
			return $env;
		}

		$settings = get_option( 'lpnw_settings', array() );
		if ( is_array( $settings ) && ! empty( $settings['stripe_api_secret'] ) && is_string( $settings['stripe_api_secret'] ) ) {
			return $settings['stripe_api_secret'];
		}

		return '';
	}

	/**
	 * GET request; returns decoded JSON array or null on failure.
	 *
	 * @param string $path Relative path after /v1/ (e.g. payment_intents/pi_xxx).
	 * @return array<string, mixed>|null
	 */
	public static function get( string $path ): ?array {
		return self::request( 'GET', $path, array() );
	}

	/**
	 * POST application/x-www-form-urlencoded.
	 *
	 * @param string               $path Relative path after /v1/.
	 * @param array<string, mixed> $params Flat or nested keys using Stripe bracket notation in caller via http_build_query.
	 * @return array<string, mixed>|null
	 */
	public static function post_form( string $path, array $params ): ?array {
		return self::request( 'POST', $path, $params );
	}

	/**
	 * DELETE request (e.g. cancel subscription).
	 *
	 * @param string $path Relative path after /v1/.
	 * @return array<string, mixed>|null
	 */
	public static function delete( string $path ): ?array {
		return self::request( 'DELETE', $path, array() );
	}

	/**
	 * Execute a Stripe API request.
	 *
	 * @param string               $method GET, POST, or DELETE.
	 * @param string               $path   Relative to v1.
	 * @param array<string, mixed> $params For POST body; ignored for GET/DELETE.
	 * @return array<string, mixed>|null
	 */
	private static function request( string $method, string $path, array $params ): ?array {
		$secret = self::get_secret_key();
		if ( '' === $secret ) {
			return null;
		}

		$path   = ltrim( $path, '/' );
		$url    = self::API_BASE . $path;
		$method = strtoupper( $method );

		$args = array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret,
			),
			'method'  => $method,
		);

		if ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$args['body']                    = self::flatten_params( $params );
			$response                        = wp_remote_post( $url, $args );
		} elseif ( 'DELETE' === $method ) {
			$response = wp_remote_request( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( $code >= 400 ) {
			return array_merge( array( '_lpnw_http_code' => $code ), $data );
		}

		return $data;
	}

	/**
	 * Flatten nested arrays to Stripe-style items[0][price]=...
	 *
	 * @param array<string, mixed> $params Params.
	 * @return string Query string body.
	 */
	private static function flatten_params( array $params ): string {
		return self::encode_params( $params, '' );
	}

	/**
	 * Recursively encode parameters for application/x-www-form-urlencoded bodies.
	 *
	 * @param array<string, mixed> $params Params.
	 * @param string               $prefix Key prefix.
	 */
	private static function encode_params( array $params, string $prefix ): string {
		$pairs = array();
		foreach ( $params as $key => $value ) {
			$key = (string) $key;
			$k   = '' === $prefix ? $key : $prefix . '[' . $key . ']';
			if ( is_array( $value ) ) {
				$pairs[] = self::encode_params( $value, $k );
			} else {
				$pairs[] = rawurlencode( $k ) . '=' . rawurlencode( (string) $value );
			}
		}
		return implode( '&', array_filter( $pairs ) );
	}
}
