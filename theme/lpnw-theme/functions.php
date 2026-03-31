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
} );

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
