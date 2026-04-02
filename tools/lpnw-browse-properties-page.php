<?php
/**
 * Plugin Name: LPNW Browse Properties Page
 * Description: Creates the Browse Properties page (slug properties) with [lpnw_property_search] and adds it to the primary menu once.
 * Version: 1.0.0
 * Author: Land & Property Northwest
 *
 * Deploy: copy this file to wp-content/mu-plugins/ on the WordPress host (must-use plugins load automatically).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether bootstrap has already completed.
 */
function lpnw_browse_properties_mu_is_done(): bool {
	return get_option( 'lpnw_browse_properties_mu_v1', '' ) === '1';
}

/**
 * Resolve the menu ID assigned to the theme's primary navigation.
 */
function lpnw_browse_properties_mu_primary_menu_id(): int {
	$locations = get_nav_menu_locations();
	$candidates = array( 'primary', 'menu-1', 'main', 'header' );

	foreach ( $candidates as $loc ) {
		if ( ! empty( $locations[ $loc ] ) ) {
			return (int) $locations[ $loc ];
		}
	}

	return 0;
}

/**
 * Create the page and append a menu item (first admin request after upload).
 */
function lpnw_browse_properties_mu_bootstrap(): void {
	if ( lpnw_browse_properties_mu_is_done() ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$page_id = 0;
	$existing = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
			'name'                   => 'properties',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache'   => false,
		)
	);

	if ( ! empty( $existing ) ) {
		$page_id = (int) $existing[0];
		$updated = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_title'   => __( 'Browse Properties', 'lpnw-alerts' ),
				'post_name'    => 'properties',
				'post_content' => '[lpnw_property_search]',
				'post_status'  => 'publish',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return;
		}
	} else {
		$page_id = (int) wp_insert_post(
			array(
				'post_title'   => __( 'Browse Properties', 'lpnw-alerts' ),
				'post_name'    => 'properties',
				'post_content' => '[lpnw_property_search]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
			),
			true
		);

		if ( is_wp_error( $page_id ) || $page_id < 1 ) {
			return;
		}
	}

	$menu_id = lpnw_browse_properties_mu_primary_menu_id();
	if ( $menu_id > 0 && $page_id > 0 ) {
		$items = wp_get_nav_menu_items( $menu_id );
		$already = false;
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( (int) $item->object_id === $page_id && 'page' === $item->object ) {
					$already = true;
					break;
				}
			}
		}
		if ( ! $already ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => __( 'Browse Properties', 'lpnw-alerts' ),
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
		}
	}

	update_option( 'lpnw_browse_properties_mu_v1', '1', false );
}

add_action( 'admin_init', 'lpnw_browse_properties_mu_bootstrap', 30 );
