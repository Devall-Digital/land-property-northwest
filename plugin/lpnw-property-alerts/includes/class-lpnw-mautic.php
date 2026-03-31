<?php
/**
 * Mautic API client.
 *
 * Handles communication with the self-hosted Mautic instance
 * for sending alert emails and managing contacts.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Mautic {

	private string $api_url  = '';
	private string $username = '';
	private string $password = '';

	public function __construct() {
		$settings       = get_option( 'lpnw_settings', array() );
		$this->api_url  = trailingslashit( $settings['mautic_api_url'] ?? '' );
		$this->username = $settings['mautic_api_user'] ?? '';
		$this->password = $settings['mautic_api_password'] ?? '';
	}

	public function is_configured(): bool {
		return ! empty( $this->api_url ) && ! empty( $this->username ) && ! empty( $this->password );
	}

	/**
	 * Create or update a Mautic contact.
	 *
	 * @param string               $email Contact email.
	 * @param array<string, mixed> $data  Additional contact fields.
	 * @return int|false Mautic contact ID or false on failure.
	 */
	public function sync_contact( string $email, array $data = array() ) {
		$payload = array_merge(
			array( 'email' => $email ),
			$data
		);

		$response = $this->request( 'POST', 'api/contacts/new', $payload );

		if ( $response && isset( $response['contact']['id'] ) ) {
			return (int) $response['contact']['id'];
		}

		return false;
	}

	/**
	 * Send an alert email to a contact via Mautic's email API.
	 *
	 * @param string        $email      Recipient email.
	 * @param array<object> $properties Properties to include in the email.
	 * @param string        $tier       Subscriber tier (free, pro, vip).
	 * @return bool
	 */
	public function send_alert( string $email, array $properties, string $tier ): bool {
		$contact_id = $this->get_contact_id_by_email( $email );
		if ( ! $contact_id ) {
			$contact_id = $this->sync_contact( $email );
		}

		if ( ! $contact_id ) {
			return false;
		}

		$segment_map = array(
			'vip'  => 'lpnw-vip-alerts',
			'pro'  => 'lpnw-pro-alerts',
			'free' => 'lpnw-free-digest',
		);

		$segment = $segment_map[ $tier ] ?? $segment_map['free'];

		$email_id = $this->get_email_id_for_tier( $tier );
		if ( ! $email_id ) {
			return false;
		}

		$response = $this->request(
			'POST',
			sprintf( 'api/emails/%d/contact/%d/send', $email_id, $contact_id )
		);

		return ! empty( $response['success'] );
	}

	/**
	 * @return int|false
	 */
	private function get_contact_id_by_email( string $email ) {
		$response = $this->request( 'GET', 'api/contacts', array(
			'search' => 'email:' . $email,
		) );

		if ( $response && ! empty( $response['contacts'] ) ) {
			$contact = reset( $response['contacts'] );
			return (int) $contact['id'];
		}

		return false;
	}

	/**
	 * Get the Mautic email template ID for a given tier.
	 * These are stored in plugin settings after Mautic setup.
	 *
	 * @return int|false
	 */
	private function get_email_id_for_tier( string $tier ) {
		$settings = get_option( 'lpnw_settings', array() );
		$key      = "mautic_email_{$tier}";
		$id       = $settings[ $key ] ?? 0;

		return $id ? (int) $id : false;
	}

	/**
	 * Make an authenticated request to the Mautic API.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $endpoint API endpoint (relative to base URL).
	 * @param array<string, mixed> $data     Request data.
	 * @return array<string, mixed>|null
	 */
	private function request( string $method, string $endpoint, array $data = array() ): ?array {
		$url = $this->api_url . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		} elseif ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'LPNW Mautic API error: ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			error_log( sprintf( 'LPNW Mautic API HTTP %d: %s', $code, $body ) );
			return null;
		}

		return json_decode( $body, true );
	}
}
