<?php
/**
 * LPNW one-time setup script.
 *
 * Access via: https://land-property-northwest.co.uk/lpnw-setup.php?key=lpnw2026setup
 * DELETE THIS FILE after running it.
 */

if ( ($_GET['key'] ?? '') !== 'lpnw2026setup' ) {
	http_response_code(404);
	die('Not found.');
}

require_once __DIR__ . '/wp-load.php';

$output = [];

$output[] = '<h1>LPNW Setup</h1>';

// 1. Activate our plugin
$plugin = 'lpnw-property-alerts/lpnw-property-alerts.php';
if ( ! is_plugin_active( $plugin ) ) {
	$result = activate_plugin( $plugin );
	if ( is_wp_error( $result ) ) {
		$output[] = '<p style="color:red;">Plugin activation failed: ' . esc_html( $result->get_error_message() ) . '</p>';
	} else {
		$output[] = '<p style="color:green;">LPNW Property Alerts plugin activated.</p>';
	}
} else {
	$output[] = '<p>LPNW Property Alerts plugin already active.</p>';
}

// 2. Check if GeneratePress parent theme exists
$gp_theme = wp_get_theme('generatepress');
if ( $gp_theme->exists() ) {
	$output[] = '<p>GeneratePress theme found.</p>';
	// Activate our child theme
	$child = wp_get_theme('lpnw-theme');
	if ( $child->exists() ) {
		switch_theme('lpnw-theme');
		$output[] = '<p style="color:green;">LPNW child theme activated.</p>';
	} else {
		$output[] = '<p style="color:orange;">LPNW child theme not found in /wp-content/themes/lpnw-theme/.</p>';
	}
} else {
	$output[] = '<p style="color:orange;">GeneratePress not installed yet. Install it from Appearance > Themes before activating LPNW theme.</p>';
	$output[] = '<p>For now, the default theme will remain active.</p>';
}

// 3. Update basic settings
update_option('blogname', 'Land & Property Northwest');
update_option('blogdescription', 'NW Property Intelligence & Alerts');
update_option('timezone_string', 'Europe/London');
update_option('date_format', 'j F Y');
update_option('time_format', 'H:i');
update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules();
$output[] = '<p style="color:green;">Site settings updated (name, timezone, permalinks).</p>';

// 4. Discourage search engines during setup
update_option('blog_public', '0');
$output[] = '<p>Search engine indexing disabled (turn on when ready to launch).</p>';

// 5. Delete default content
$hello_world = get_page_by_title('Hello world!', OBJECT, 'post');
if ($hello_world) {
	wp_delete_post($hello_world->ID, true);
	$output[] = '<p>Deleted default "Hello world!" post.</p>';
}
$sample_page = get_page_by_title('Sample Page', OBJECT, 'page');
if ($sample_page) {
	wp_delete_post($sample_page->ID, true);
	$output[] = '<p>Deleted default "Sample Page".</p>';
}

// 6. Create core pages
$pages = [
	'Home' => '[lpnw_latest_properties limit="5"]',
	'Dashboard' => '[lpnw_dashboard]',
	'Preferences' => '[lpnw_preferences]',
	'Property Map' => '[lpnw_property_map height="600px"]',
	'Saved Properties' => '[lpnw_saved_properties]',
	'Pricing' => '<!-- Pricing page content to be added -->',
	'About' => '<!-- About page content to be added -->',
	'Contact' => '<!-- Contact form to be added -->',
	'Privacy Policy' => '<!-- Privacy policy to be added -->',
	'Terms of Service' => '<!-- Terms to be added -->',
];

foreach ($pages as $title => $content) {
	$existing = get_page_by_title($title, OBJECT, 'page');
	if (!$existing) {
		$id = wp_insert_post([
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		]);
		$output[] = '<p>Created page: ' . esc_html($title) . '</p>';
	} else {
		$output[] = '<p>Page exists: ' . esc_html($title) . '</p>';
	}
}

// 7. Set Home as front page
$home_page = get_page_by_title('Home', OBJECT, 'page');
if ($home_page) {
	update_option('show_on_front', 'page');
	update_option('page_on_front', $home_page->ID);
	$output[] = '<p style="color:green;">Home page set as static front page.</p>';
}

// 8. Enable user registration
update_option('users_can_register', 1);
update_option('default_role', 'subscriber');
$output[] = '<p style="color:green;">User registration enabled (subscriber role).</p>';

// 9. Report plugin DB tables
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}lpnw_%'");
if (!empty($tables)) {
	$output[] = '<p style="color:green;">Plugin database tables created: ' . esc_html(implode(', ', $tables)) . '</p>';
} else {
	$output[] = '<p style="color:red;">Plugin database tables NOT found. Try deactivating and reactivating the plugin.</p>';
}

// 10. Show admin login link
$output[] = '<hr>';
$output[] = '<p><strong>Setup complete.</strong></p>';
$output[] = '<p><a href="' . admin_url() . '">Go to WordPress Admin</a></p>';
$output[] = '<p style="color:red;"><strong>IMPORTANT: Delete this file (lpnw-setup.php) from the server after use!</strong></p>';

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>LPNW Setup</title>';
echo '<style>body{font-family:sans-serif;max-width:700px;margin:40px auto;padding:0 20px;line-height:1.6;}</style>';
echo '</head><body>';
echo implode("\n", $output);
echo '</body></html>';
