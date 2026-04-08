<?php
/**
 * Comprehensive admin configuration: logo, nav menus, layout, GeneratePress settings.
 * Run once from wp-admin context (we're now logged in).
 */
if ( ! defined( 'ABSPATH' ) ) { return; }
require_once WPMU_PLUGIN_DIR . '/lpnw-tool-auth-loader.php';

add_action( 'admin_init', function() {
	if ( empty( $_GET['lpnw_admin_setup'] ) || 'run' !== $_GET['lpnw_admin_setup'] ) { return; }
	$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
	if ( ! lpnw_tool_query_key_ok( $key ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	header( 'Content-Type: text/plain; charset=utf-8' );
	$out = array();

	// 1. Brand SVG: Customizer logo + site icon (same file as theme header / favicon).
	$brand_path = get_stylesheet_directory() . '/assets/img/lpnw-brand-logo.svg';
	if ( ! file_exists( $brand_path ) ) {
		$out[] = "Brand logo SVG not found at {$brand_path}.";
	} else {
		$upload_dir = wp_upload_dir();
		$target     = $upload_dir['path'] . '/lpnw-brand-logo.svg';

		if ( ! file_exists( $target ) ) {
			copy( $brand_path, $target );
		}

		$existing = get_posts( array(
			'post_type'   => 'attachment',
			'meta_key'    => '_wp_attached_file',
			'meta_value'  => str_replace( $upload_dir['basedir'] . '/', '', $target ),
			'numberposts' => 1,
		) );

		if ( empty( $existing ) ) {
			$attachment_id = wp_insert_attachment( array(
				'post_title'     => 'LPNW Brand Logo',
				'post_mime_type' => 'image/svg+xml',
				'post_status'    => 'inherit',
			), $target );

			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$metadata = wp_generate_attachment_metadata( $attachment_id, $target );
				wp_update_attachment_metadata( $attachment_id, $metadata );
				set_theme_mod( 'custom_logo', $attachment_id );
				update_option( 'site_icon', $attachment_id );
				$out[] = "Brand SVG uploaded: custom logo + site icon (ID {$attachment_id}).";
			} else {
				$out[] = 'Brand SVG upload failed.';
			}
		} else {
			$attachment_id = (int) $existing[0]->ID;
			set_theme_mod( 'custom_logo', $attachment_id );
			update_option( 'site_icon', $attachment_id );
			$out[] = "Brand SVG already in media; custom logo + site icon set (ID {$attachment_id}).";
		}
	}

	// 2. Allow SVG uploads (optional content).
	add_filter( 'upload_mimes', function( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	} );

	// 3. Configure GeneratePress settings.
	set_theme_mod( 'hide_title', false );
	set_theme_mod( 'hide_tagline', true );
	$out[] = 'Site title visible with brand mark; tagline hidden.';

	// 4. Set header background colour to navy.
	set_theme_mod( 'header_background_color', '#1B2A4A' );
	set_theme_mod( 'header_text_color', '#FFFFFF' );
	set_theme_mod( 'header_link_color', '#FFFFFF' );
	set_theme_mod( 'header_link_hover_color', '#E8A317' );
	$out[] = "Header colours set (navy background, white text, amber hover).";

	// 5. Set navigation colours.
	set_theme_mod( 'navigation_background_color', '#1B2A4A' );
	set_theme_mod( 'navigation_text_color', '#FFFFFF' );
	set_theme_mod( 'navigation_text_hover_color', '#E8A317' );
	set_theme_mod( 'navigation_background_hover_color', '#2D4470' );
	$out[] = 'Navigation colours set.';

	// 6. Configure container width.
	set_theme_mod( 'container_width', 1200 );
	$out[] = 'Container width set to 1200px.';

	// 7. Enable search engine indexing (was set to noindex during build).
	update_option( 'blog_public', '1' );
	$out[] = 'Search engine indexing ENABLED.';

	// 8. Purge StackCache if available.
	if ( function_exists( 'stackcache_purge_all' ) ) {
		stackcache_purge_all();
		$out[] = 'StackCache purged.';
	}

	// 9. Flush rewrite rules.
	flush_rewrite_rules();
	$out[] = 'Rewrite rules flushed.';

	echo "LPNW Admin Setup\n";
	echo str_repeat( '=', 50 ) . "\n\n";
	echo implode( "\n", $out );
	echo "\n\nDone.\n";

	@unlink( __FILE__ );
	exit;
}, 1 );
