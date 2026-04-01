<?php
/**
 * LPNW Theme functions.
 *
 * GeneratePress child theme for Land & Property Northwest.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load a template part from `template-parts/{name}.php` with extracted variables.
 *
 * Keys in `$args` become variables in the template scope (same pattern as scoped `extract`
 * before `require`, since `get_template_part()` does not inherit parent scope).
 *
 * @param string               $name Template basename without `.php` (e.g. `hero`, `pricing-table`). Alphanumeric, hyphens, underscores only.
 * @param array<string, mixed> $args Variables to extract into the template.
 */
function lpnw_get_template_part( string $name, array $args = array() ): void {
	$name = preg_replace( '/\.php$/i', '', $name );
	if ( ! preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
		return;
	}

	$file = locate_template( 'template-parts/' . $name . '.php' );
	if ( ! $file || ! is_readable( $file ) ) {
		return;
	}

	if ( ! empty( $args ) ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Intentional template-local scope for theme partials.
		extract( $args, EXTR_SKIP );
	}

	require $file;
}

/**
 * Enqueue parent and child styles, plus Google Fonts.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'generatepress-parent',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( 'generatepress' )->get( 'Version' )
	);

	wp_enqueue_style(
		'lpnw-fonts',
		'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'lpnw-child',
		get_stylesheet_uri(),
		array( 'generatepress-parent', 'lpnw-fonts' ),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'lpnw-theme',
		get_stylesheet_directory_uri() . '/assets/js/theme.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}, 20 );

/**
 * Set up theme support.
 */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption',
	) );

	// Subscriber-only nav; assign the "Subscriber" menu in Appearance > Menus (or via lpnw-woo-setup.php).
	register_nav_menu( 'lpnw_subscriber', __( 'Subscriber', 'lpnw-theme' ) );
}, 11 );

/**
 * Register widget areas.
 */
add_action( 'widgets_init', function () {
	register_sidebar( array(
		'name'          => __( 'Footer Column 1', 'lpnw-theme' ),
		'id'            => 'lpnw-footer-1',
		'before_widget' => '<div class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	) );

	register_sidebar( array(
		'name'          => __( 'Footer Column 2', 'lpnw-theme' ),
		'id'            => 'lpnw-footer-2',
		'before_widget' => '<div class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	) );

	register_sidebar( array(
		'name'          => __( 'Footer Column 3', 'lpnw-theme' ),
		'id'            => 'lpnw-footer-3',
		'before_widget' => '<div class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	) );
} );

/**
 * Add custom body classes.
 *
 * @param array<string> $classes Existing body classes.
 * @return array<string>
 */
add_filter( 'body_class', function ( array $classes ): array {
	$classes[] = 'lpnw-site';

	if ( is_user_logged_in() ) {
		$tier      = class_exists( 'LPNW_Subscriber' ) ? LPNW_Subscriber::get_tier( get_current_user_id() ) : 'free';
		$classes[] = 'lpnw-tier-' . $tier;
	}

	return $classes;
} );

/**
 * Customise the login logo to show LPNW branding.
 */
add_action( 'login_enqueue_scripts', function () {
	?>
	<style>
		#login h1 a {
			background-image: none;
			font-size: 24px;
			font-weight: 700;
			font-family: 'Plus Jakarta Sans', sans-serif;
			color: #1B2A4A;
			text-indent: 0;
			width: auto;
			height: auto;
		}
		#login h1 a::after {
			content: 'Land & Property Northwest';
		}
	</style>
	<?php
} );

add_filter( 'login_headerurl', function () {
	return home_url();
} );
