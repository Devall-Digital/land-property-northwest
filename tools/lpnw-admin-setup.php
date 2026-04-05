<?php
/**
 * Comprehensive admin configuration: logo, nav menus, layout, GeneratePress settings.
 * Run once from wp-admin context (we're now logged in).
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_action( 'admin_init', function() {
	if ( empty( $_GET['lpnw_admin_setup'] ) || 'run' !== $_GET['lpnw_admin_setup'] ) { return; }
	if ( empty( $_GET['key'] ) || 'lpnw2026setup' !== $_GET['key'] ) { return; }
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	header( 'Content-Type: text/plain; charset=utf-8' );
	$out = array();

	// 1. Upload logo as PNG and set as custom logo
	$logo_svg_path = get_stylesheet_directory() . '/assets/img/logo-full.svg';
	if ( file_exists( $logo_svg_path ) ) {
		// WordPress doesn't natively support SVG logos in all themes.
		// Copy the SVG to uploads and create a media attachment.
		$upload_dir = wp_upload_dir();
		$target     = $upload_dir['path'] . '/lpnw-logo.svg';

		if ( ! file_exists( $target ) ) {
			copy( $logo_svg_path, $target );
		}

		// Check if attachment already exists
		$existing = get_posts( array(
			'post_type'  => 'attachment',
			'meta_key'   => '_wp_attached_file',
			'meta_value' => str_replace( $upload_dir['basedir'] . '/', '', $target ),
			'numberposts' => 1,
		) );

		if ( empty( $existing ) ) {
			$attachment_id = wp_insert_attachment( array(
				'post_title'     => 'LPNW Logo',
				'post_mime_type' => 'image/svg+xml',
				'post_status'    => 'inherit',
			), $target );

			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$metadata = wp_generate_attachment_metadata( $attachment_id, $target );
				wp_update_attachment_metadata( $attachment_id, $metadata );
				set_theme_mod( 'custom_logo', $attachment_id );
				$out[] = "Logo uploaded and set (attachment ID {$attachment_id}).";
			} else {
				$out[] = "Logo upload failed.";
			}
		} else {
			$attachment_id = $existing[0]->ID;
			set_theme_mod( 'custom_logo', $attachment_id );
			$out[] = "Logo already uploaded, set as custom logo (ID {$attachment_id}).";
		}
	} else {
		$out[] = "Logo SVG not found at {$logo_svg_path}.";
	}

	// 2. Upload site icon (simplified favicon SVG if present, else full logo mark)
	$icon_svg_path = get_stylesheet_directory() . '/assets/img/favicons/favicon-source.svg';
	if ( ! file_exists( $icon_svg_path ) ) {
		$icon_svg_path = get_stylesheet_directory() . '/assets/img/logo-icon.svg';
	}
	if ( file_exists( $icon_svg_path ) ) {
		$upload_dir = wp_upload_dir();
		$icon_target = $upload_dir['path'] . '/lpnw-icon.svg';
		if ( ! file_exists( $icon_target ) ) {
			copy( $icon_svg_path, $icon_target );
		}

		$icon_existing = get_posts( array(
			'post_type'  => 'attachment',
			'meta_key'   => '_wp_attached_file',
			'meta_value' => str_replace( $upload_dir['basedir'] . '/', '', $icon_target ),
			'numberposts' => 1,
		) );

		if ( empty( $icon_existing ) ) {
			$icon_id = wp_insert_attachment( array(
				'post_title'     => 'LPNW Icon',
				'post_mime_type' => 'image/svg+xml',
				'post_status'    => 'inherit',
			), $icon_target );
			if ( $icon_id ) {
				update_option( 'site_icon', $icon_id );
				$out[] = "Site icon set (ID {$icon_id}).";
			}
		} else {
			update_option( 'site_icon', $icon_existing[0]->ID );
			$out[] = "Site icon already uploaded, set (ID {$icon_existing[0]->ID}).";
		}
	}

	// 3. Allow SVG uploads
	add_filter( 'upload_mimes', function( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	} );

	// 4. Configure GeneratePress settings
	// Hide site title (logo replaces it)
	set_theme_mod( 'hide_title', true );
	set_theme_mod( 'hide_tagline', true );
	$out[] = "Site title and tagline hidden (logo only in header).";

	// 5. Set header background colour to navy
	set_theme_mod( 'header_background_color', '#1B2A4A' );
	set_theme_mod( 'header_text_color', '#FFFFFF' );
	set_theme_mod( 'header_link_color', '#FFFFFF' );
	set_theme_mod( 'header_link_hover_color', '#E8A317' );
	$out[] = "Header colours set (navy background, white text, amber hover).";

	// 6. Set navigation colours
	set_theme_mod( 'navigation_background_color', '#1B2A4A' );
	set_theme_mod( 'navigation_text_color', '#FFFFFF' );
	set_theme_mod( 'navigation_text_hover_color', '#E8A317' );
	set_theme_mod( 'navigation_background_hover_color', '#2D4470' );
	$out[] = "Navigation colours set.";

	// 7. Configure container width
	set_theme_mod( 'container_width', 1200 );
	$out[] = "Container width set to 1200px.";

	// 8. Enable search engine indexing (was set to noindex during build)
	update_option( 'blog_public', '1' );
	$out[] = "Search engine indexing ENABLED.";

	// 9. Purge StackCache if available
	if ( function_exists( 'stackcache_purge_all' ) ) {
		stackcache_purge_all();
		$out[] = "StackCache purged.";
	}

	// 10. Flush rewrite rules
	flush_rewrite_rules();
	$out[] = "Rewrite rules flushed.";

	echo "LPNW Admin Setup\n";
	echo str_repeat( '=', 50 ) . "\n\n";
	echo implode( "\n", $out );
	echo "\n\nDone.\n";

	@unlink( __FILE__ );
	exit;
}, 1 );
