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
	 * Recent channel emails from Mautic (IDs for Settings fields).
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array{id: int, name: string, subject: string}>
	 */
	public function list_channel_emails_for_admin( int $limit = 40 ): array {
		if ( ! $this->is_configured() ) {
			return array();
		}

		$response = $this->request(
			'GET',
			'api/emails',
			array(
				'limit'        => $limit,
				'orderBy'      => 'id',
				'orderByDir'   => 'DESC',
			)
		);

		if ( ! is_array( $response ) || empty( $response['emails'] ) ) {
			return array();
		}

		$out = array();
		foreach ( $response['emails'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id < 1 ) {
				continue;
			}
			$name    = isset( $row['name'] ) ? (string) $row['name'] : '';
			$subject = isset( $row['subject'] ) ? (string) $row['subject'] : '';
			$out[]   = array(
				'id'      => $id,
				'name'    => $name,
				'subject' => $subject,
			);
		}

		return $out;
	}

	/**
	 * Map tier keys to Mautic email row IDs using seeded template names (fills empty settings).
	 *
	 * @return array<string, int> Keys: vip, pro, free.
	 */
	public function get_alert_email_ids_by_name(): array {
		if ( ! $this->is_configured() ) {
			return array();
		}

		$response = $this->request(
			'GET',
			'api/emails',
			array(
				'limit'      => 120,
				'orderBy'    => 'id',
				'orderByDir' => 'DESC',
			)
		);

		if ( ! is_array( $response ) || empty( $response['emails'] ) ) {
			return array();
		}

		$vip_id  = 0;
		$pro_id  = 0;
		$free_id = 0;

		foreach ( $response['emails'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id < 1 ) {
				continue;
			}
			$name = isset( $row['name'] ) ? (string) $row['name'] : '';
			$key  = self::normalize_email_name_key( $name );

			if ( str_contains( $key, 'lpnwalertvip' ) && $vip_id < 1 ) {
				$vip_id = $id;
			} elseif ( str_contains( $key, 'lpnwalertpro' ) && $pro_id < 1 ) {
				$pro_id = $id;
			} elseif ( str_contains( $key, 'weeklydigest' ) && str_contains( $key, 'free' ) && $free_id < 1 ) {
				$free_id = $id;
			}
		}

		$out = array();
		if ( $vip_id > 0 ) {
			$out['vip'] = $vip_id;
		}
		if ( $pro_id > 0 ) {
			$out['pro'] = $pro_id;
		}
		if ( $free_id > 0 ) {
			$out['free'] = $free_id;
		}

		return $out;
	}

	/**
	 * Normalize template name for loose matching (Unicode dashes, case).
	 *
	 * @param string $name Raw name from Mautic.
	 */
	private static function normalize_email_name_key( string $name ): string {
		$n = strtolower( $name );
		$n = str_replace( array( '—', '–', '-' ), '', $n );
		$n = preg_replace( '/\s+/', '', $n );

		return is_string( $n ) ? $n : '';
	}

	/**
	 * Whether a Mautic email template ID is set for this tier (required for API send).
	 *
	 * @param string $tier Subscriber tier (free, pro, vip).
	 * @return bool
	 */
	public function has_email_template_for_tier( string $tier ): bool {
		$settings = get_option( 'lpnw_settings', array() );
		$key      = "mautic_email_{$tier}";
		$id       = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;

		return $id > 0;
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
		return null !== $this->send_alert_get_template_id( $email, $properties, $tier );
	}

	/**
	 * Send alert via Mautic; returns template ID used when the API reports success.
	 *
	 * @param string        $email      Recipient email.
	 * @param array<object> $properties Properties to include in the email.
	 * @param string        $tier       Subscriber tier (free, pro, vip).
	 * @return int|null Template ID, or null on failure.
	 */
	public function send_alert_get_template_id( string $email, array $properties, string $tier ): ?int {
		$user         = get_user_by( 'email', $email );
		$sync_payload = array();
		if ( $user instanceof \WP_User ) {
			$sync_payload = LPNW_Dispatcher::get_mautic_contact_fields_for_sync( $user );
		}

		$contact_id = $this->get_contact_id_by_email( $email );
		if ( ! $contact_id ) {
			$contact_id = $this->sync_contact( $email, $sync_payload );
		}

		if ( ! $contact_id ) {
			return null;
		}

		$email_id = $this->get_email_id_for_tier( $tier );
		if ( ! $email_id ) {
			return null;
		}

		$send_body = ! empty( $properties )
			? LPNW_Dispatcher::get_mautic_send_body_with_tokens(
				$user instanceof \WP_User ? $user : null,
				$properties,
				$tier
			)
			: array();

		$response = $this->request(
			'POST',
			sprintf( 'api/emails/%d/contact/%d/send', $email_id, $contact_id ),
			$send_body
		);

		if ( ! empty( $response['success'] ) ) {
			return (int) $email_id;
		}

		return null;
	}

	/**
	 * @return int|false
	 */
	private function get_contact_id_by_email( string $email ) {
		$response = $this->request(
			'GET',
			'api/contacts',
			array(
				'search' => 'email:' . $email,
			)
		);

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
