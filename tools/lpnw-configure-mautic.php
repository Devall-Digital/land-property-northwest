<?php
/**
 * Plugin Name: LPNW One-Shot: Mautic & Contact Form
 * Description: Configure Mautic API settings, verify API access, create a WPForms contact form, and fix the Contact page shortcode. Deletes itself after a successful run.
 *
 * Install: copy this file to wp-content/mu-plugins/ then visit once:
 * https://your-site.example/?lpnw_config=mautic&key=lpnw2026setup
 *
 * @package LPNW
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run one-shot setup when query args match.
 *
 * @return void
 */
function lpnw_configure_mautic_run(): void {
	if ( ! isset( $_GET['lpnw_config'], $_GET['key'] ) ) {
		return;
	}

	if ( 'mautic' !== sanitize_key( wp_unslash( $_GET['lpnw_config'] ) ) ) {
		return;
	}

	$provided = (string) wp_unslash( $_GET['key'] );
	if ( ! hash_equals( 'lpnw2026setup', $provided ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );

	$lines   = array();
	$lines[] = 'LPNW Mautic / contact form setup';
	$lines[] = str_repeat( '-', 40 );

	$settings = get_option( 'lpnw_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$settings['mautic_api_url']      = 'https://marketing.land-property-northwest.co.uk';
	$settings['mautic_api_user']     = 'admin';
	$settings['mautic_api_password'] = 'vZ^zurHP13KVnI7KJB';

	update_option( 'lpnw_settings', $settings );
	$lines[] = 'lpnw_settings: Mautic fields merged and saved.';

	$api_base = trailingslashit( $settings['mautic_api_url'] );
	$auth     = base64_encode( $settings['mautic_api_user'] . ':' . $settings['mautic_api_password'] );

	$mautic_response = wp_remote_get(
		$api_base . 'api/contacts?limit=1',
		array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . $auth,
				'Accept'        => 'application/json',
			),
		)
	);

	if ( is_wp_error( $mautic_response ) ) {
		$lines[] = 'Mautic API test: FAILED — ' . $mautic_response->get_error_message();
	} else {
		$code = wp_remote_retrieve_response_code( $mautic_response );
		if ( $code >= 200 && $code < 300 ) {
			$lines[] = 'Mautic API test: OK (HTTP ' . $code . ').';
		} else {
			$body    = wp_remote_retrieve_body( $mautic_response );
			$snippet = function_exists( 'mb_substr' )
				? mb_substr( wp_strip_all_tags( $body ), 0, 200 )
				: substr( wp_strip_all_tags( $body ), 0, 200 );
			$lines[] = 'Mautic API test: FAILED (HTTP ' . $code . '). Body (truncated): ' . $snippet;
		}
	}

	$form_id = lpnw_configure_mautic_get_or_create_wpforms_contact_form( $lines );

	if ( $form_id ) {
		$page = get_page_by_path( 'contact' );
		if ( $page instanceof WP_Post ) {
			$content = $page->post_content;
			$new     = str_replace(
				array( '[wpforms id="X"]', "[wpforms id='X']" ),
				sprintf( '[wpforms id="%d"]', $form_id ),
				$content
			);
			if ( $new !== $content ) {
				wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => $new,
					)
				);
				$lines[] = 'Contact page updated: shortcode now uses wpforms id=' . $form_id . '.';
			} else {
				$lines[] = 'Contact page: placeholder [wpforms id="X"] not found; content unchanged.';
			}
		} else {
			$lines[] = 'Contact page: no page with slug "contact" found.';
		}
	} else {
		$lines[] = 'WPForms: form not created; ensure WPForms is active and check messages above.';
	}

	$self = __FILE__;
	if ( is_string( $self ) && '' !== $self && file_exists( $self ) && is_writable( $self ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- intentional one-shot self-removal.
		if ( unlink( $self ) ) {
			$lines[] = 'This mu-plugin file was deleted from the server.';
		} else {
			$lines[] = 'Warning: could not delete this file; remove it manually from mu-plugins.';
		}
	} else {
		$lines[] = 'Warning: could not delete this file; remove it manually from mu-plugins.';
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain diagnostic output.
	echo implode( "\n", $lines );
	exit;
}

/**
 * Get existing LPNW contact form or create fields + post.
 *
 * @param array<int, string> $lines Log lines (by reference).
 * @return int Form post ID or 0.
 */
function lpnw_configure_mautic_get_or_create_wpforms_contact_form( array &$lines ): int {
	global $wpdb;

	if ( ! post_type_exists( 'wpforms' ) ) {
		$lines[] = 'WPForms: post type "wpforms" not registered (is WPForms active?).';
		return 0;
	}

	$title = 'LPNW Contact';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-shot setup, no API for exact title match.
	$existing_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_title = %s LIMIT 1",
			'wpforms',
			'publish',
			$title
		)
	);

	if ( $existing_id > 0 ) {
		$lines[] = 'WPForms: using existing form "' . $title . '" (ID ' . $existing_id . ').';
		return $existing_id;
	}

	$encode = static function ( array $data ): string {
		if ( function_exists( 'wpforms_encode' ) ) {
			return wpforms_encode( $data );
		}
		return wp_json_encode( $data );
	};

	$form_data = array(
		'id'       => '',
		'field_id' => '4',
		'fields'   => array(
			'1' => array(
				'id'       => '1',
				'type'     => 'text',
				'label'    => 'Name',
				'required' => '1',
				'size'     => 'medium',
			),
			'2' => array(
				'id'       => '2',
				'type'     => 'email',
				'label'    => 'Email',
				'required' => '1',
				'size'     => 'medium',
			),
			'3' => array(
				'id'       => '3',
				'type'     => 'textarea',
				'label'    => 'Message',
				'required' => '1',
				'size'     => 'medium',
			),
		),
		'settings' => array(
			'form_title'             => $title,
			'form_desc'              => '',
			'submit_text'            => 'Submit',
			'submit_text_processing' => 'Sending...',
			'notification_enable'    => '1',
			'notifications'          => array(
				'1' => array(
					'email'          => '{admin_email}',
					'subject'        => 'New Entry: ' . $title,
					'sender_name'    => get_bloginfo( 'name' ),
					'sender_address' => '{admin_email}',
					'message'        => '{all_fields}',
				),
			),
			'confirmations'          => array(
				'1' => array(
					'type'           => 'message',
					'message'        => 'Thanks for contacting us! We will be in touch with you shortly.',
					'message_scroll' => '1',
				),
			),
		),
	);

	$cap_filter = static function ( $allcaps ) {
		$allcaps['wpforms_create_forms']      = true;
		$allcaps['wpforms_edit_own_forms']    = true;
		$allcaps['wpforms_edit_others_forms'] = true;
		return $allcaps;
	};

	add_filter( 'user_has_cap', $cap_filter, 999 );

	$form_id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_type'    => 'wpforms',
			'post_content' => $encode( $form_data ),
		),
		true
	);

	if ( is_wp_error( $form_id ) ) {
		remove_filter( 'user_has_cap', $cap_filter, 999 );
		$lines[] = 'WPForms: wp_insert_post failed — ' . $form_id->get_error_message();
		return 0;
	}

	$form_id = (int) $form_id;
	if ( $form_id <= 0 ) {
		remove_filter( 'user_has_cap', $cap_filter, 999 );
		$lines[] = 'WPForms: wp_insert_post returned invalid ID.';
		return 0;
	}

	$form_data['id'] = $form_id;

	$updated = wp_update_post(
		array(
			'ID'           => $form_id,
			'post_content' => $encode( $form_data ),
		),
		true
	);

	remove_filter( 'user_has_cap', $cap_filter, 999 );

	if ( is_wp_error( $updated ) ) {
		$lines[] = 'WPForms: wp_update_post failed — ' . $updated->get_error_message();
		return $form_id;
	}

	$lines[] = 'WPForms: created form "' . $title . '" (ID ' . $form_id . ').';
	return $form_id;
}

add_action( 'init', 'lpnw_configure_mautic_run', 1 );
