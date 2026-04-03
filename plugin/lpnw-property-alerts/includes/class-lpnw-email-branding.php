<?php
/**
 * From name / address for transactional wp_mail sends (alerts, contact notifications).
 *
 * Defaults use addresses on the site's primary domain so messages look like your brand.
 * Hosts must create matching mailboxes (or forwarding) and pass SPF/DKIM for best delivery.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Branded From headers for wp_mail (alerts and contact notifications).
 */
class LPNW_Email_Branding {

	/**
	 * Context: property alert emails.
	 */
	public const CONTEXT_ALERTS = 'alerts';

	/**
	 * Context: contact form notification to admin.
	 */
	public const CONTEXT_CONTACT = 'contact';

	/**
	 * Context: inbound contact notification recipient (admin mailbox).
	 */
	public const CONTEXT_ADMIN_NOTIFY = 'admin_notify';

	/**
	 * Primary hostname from home URL (lowercase).
	 */
	public static function get_site_domain(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Local part only (before @). Sanitized.
	 *
	 * @param string $context self::CONTEXT_* .
	 */
	public static function get_local_part( string $context ): string {
		if ( self::CONTEXT_ADMIN_NOTIFY === $context ) {
			$default = 'admin';
		} elseif ( self::CONTEXT_CONTACT === $context ) {
			$default = 'hello';
		} else {
			$default = 'alerts';
		}
		if ( self::CONTEXT_ALERTS === $context ) {
			$local = apply_filters( 'lpnw_alert_mail_from_local_part', $default );
		} elseif ( self::CONTEXT_ADMIN_NOTIFY === $context ) {
			$local = apply_filters( 'lpnw_admin_notify_mail_local_part', $default );
		} else {
			$local = apply_filters( 'lpnw_contact_mail_from_local_part', $default );
		}
		$local = is_string( $local ) ? preg_replace( '/[^a-z0-9._+-]/i', '', $local ) : $default;
		if ( '' === $local ) {
			$local = $default;
		}
		return $local;
	}

	/**
	 * Full From email for wp_mail, or admin_email if domain missing / invalid.
	 *
	 * @param string $context self::CONTEXT_* .
	 */
	public static function get_from_email( string $context ): string {
		$domain = self::get_site_domain();
		$local  = self::get_local_part( $context );
		$email  = $domain ? $local . '@' . $domain : '';

		if ( self::CONTEXT_ALERTS === $context ) {
			$email = apply_filters( 'lpnw_alert_mail_from_email', $email );
		} elseif ( self::CONTEXT_ADMIN_NOTIFY === $context ) {
			$email = apply_filters( 'lpnw_admin_notify_mail_to_email', $email );
		} else {
			$email = apply_filters( 'lpnw_contact_mail_from_email', $email );
		}

		if ( ! is_string( $email ) || '' === $email || ! is_email( $email ) ) {
			$fallback = get_option( 'admin_email' );
			return is_string( $fallback ) && is_email( $fallback ) ? $fallback : '';
		}

		return $email;
	}

	/**
	 * Display name for the From header.
	 */
	public static function get_from_name(): string {
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$name = apply_filters( 'lpnw_mail_from_name', $name );
		return is_string( $name ) && '' !== $name ? $name : 'Land & Property Northwest';
	}

	/**
	 * RFC-style From header value: "Display Name" <addr@domain>.
	 */
	public static function format_from_header(): string {
		$mail = self::get_from_email( self::CONTEXT_ALERTS );
		if ( '' === $mail || ! is_email( $mail ) ) {
			return '';
		}
		$name = self::get_from_name();
		$q    = '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\"' ), $name ) . '"';
		return sprintf( '%s <%s>', $q, $mail );
	}

	/**
	 * Headers for HTML mail (alerts).
	 *
	 * @return string[]
	 */
	public static function get_alert_mail_headers(): array {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from    = self::format_from_header();
		if ( '' !== $from ) {
			$headers[] = 'From: ' . $from;
		}
		return $headers;
	}

	/**
	 * Headers for plain-text contact notification (Reply-To stays the visitor).
	 *
	 * @return string[]
	 */
	public static function get_contact_mail_headers(): array {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$mail    = self::get_from_email( self::CONTEXT_CONTACT );
		$name    = self::get_from_name();
		if ( '' !== $mail && is_email( $mail ) ) {
			$q         = '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\"' ), $name ) . '"';
			$headers[] = sprintf( 'From: %s <%s>', $q, $mail );
		}
		return $headers;
	}

	/**
	 * Where contact-form notifications are delivered (typically admin@yourdomain).
	 */
	public static function get_contact_notification_to_email(): string {
		return self::get_from_email( self::CONTEXT_ADMIN_NOTIFY );
	}
}
