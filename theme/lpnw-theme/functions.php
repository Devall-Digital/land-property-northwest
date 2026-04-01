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

	add_theme_support(
		'custom-logo',
		array(
			'height'      => 72,
			'width'       => 380,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
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

/**
 * GeneratePress: full-width layout on pages and the front page (no sidebar).
 *
 * @param string $layout Sidebar layout slug.
 * @return string
 */
add_filter(
	'generate_sidebar_layout',
	function ( $layout ) {
		if ( is_page() || is_front_page() ) {
			return 'no-sidebar';
		}
		return $layout;
	}
);

/**
 * GeneratePress: hide the redundant default page title on the static front page (hero supplies the heading).
 *
 * @param bool $show Whether to show the title.
 * @return bool
 */
add_filter(
	'generate_show_title',
	function ( $show ) {
		if ( is_front_page() ) {
			return false;
		}
		return $show;
	}
);

/**
 * GeneratePress: output one logo in the site title area (Customizer logo or bundled SVG).
 * Avoid duplicate marks: GP otherwise prints custom logo inside branding and again via the header "logo" item.
 */
add_filter( 'generate_has_logo_site_branding', '__return_false' );

add_filter(
	'generate_header_items_order',
	function ( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}

		return array_values( array_diff( $order, array( 'logo' ) ) );
	}
);

/**
 * GeneratePress: site title area shows the Custom Logo if set, otherwise the bundled SVG.
 *
 * @param string $output Default title HTML.
 * @return string
 */
add_filter(
	'generate_site_title_output',
	function ( $output ) {
		$schema = function_exists( 'generate_get_schema_type' ) && 'microdata' === generate_get_schema_type() ? ' itemprop="headline"' : '';
		$tag    = ( is_front_page() && is_home() ) ? 'h1' : 'p';
		$href   = esc_url( apply_filters( 'generate_site_title_href', home_url( '/' ) ) );

		if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
			$logo = get_custom_logo();
			if ( '' !== $logo ) {
				return sprintf(
					'<%1$s class="main-title lpnw-site-logo"%3$s>%2$s</%1$s>',
					tag_escape( $tag ),
					$logo, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core returns escaped custom logo HTML.
					$schema
				);
			}
		}

		$logo_url = get_stylesheet_directory_uri() . '/assets/img/logo-full.svg';
		$alt      = esc_attr( get_bloginfo( 'name', 'display' ) );

		return sprintf(
			'<%1$s class="main-title lpnw-site-logo"%5$s><a href="%2$s" rel="home" class="lpnw-site-logo__link"><img src="%3$s" alt="%4$s" class="lpnw-site-logo__img" width="380" height="72" loading="eager" decoding="async" /></a></%1$s>',
			tag_escape( $tag ),
			$href,
			esc_url( $logo_url ),
			$alt,
			$schema
		);
	},
	10,
	1
);

/**
 * GeneratePress: branded copyright line plus key links; remove theme credit.
 *
 * @return string
 */
add_filter(
	'generate_copyright',
	function () {
		$year = (int) gmdate( 'Y' );
		$text = sprintf(
			/* translators: %d: current year (Gregorian). */
			esc_html__( '&copy; %d Land & Property Northwest. NW Property Intelligence & Alerts.', 'lpnw-theme' ),
			$year
		);

		$links_inner = sprintf(
			'<a href="%1$s">%2$s</a><span class="lpnw-footer-dot" aria-hidden="true"> · </span><a href="%3$s">%4$s</a><span class="lpnw-footer-dot" aria-hidden="true"> · </span><a href="%5$s">%6$s</a>',
			esc_url( home_url( '/pricing/' ) ),
			esc_html__( 'Pricing', 'lpnw-theme' ),
			esc_url( home_url( '/about/' ) ),
			esc_html__( 'About', 'lpnw-theme' ),
			esc_url( home_url( '/contact/' ) ),
			esc_html__( 'Contact', 'lpnw-theme' )
		);

		$nav = sprintf(
			'<nav class="lpnw-footer-links" aria-label="%1$s">%2$s</nav>',
			esc_attr__( 'Footer quick links', 'lpnw-theme' ),
			wp_kses(
				$links_inner,
				array(
					'a'    => array( 'href' => true ),
					'span' => array( 'class' => true, 'aria-hidden' => true ),
				)
			)
		);

		return '<span class="copyright">' . $text . '</span> ' . $nav;
	}
);

