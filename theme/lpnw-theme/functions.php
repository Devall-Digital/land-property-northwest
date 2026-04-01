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

/**
 * Whether Rank Math SEO is active (outputs its own Article/NewsArticle JSON-LD for posts).
 *
 * @return bool
 */
function lpnw_schema_rank_math_active(): bool {
	return defined( 'RANK_MATH_VERSION' );
}

/**
 * Base site URL for schema (trailing slash).
 *
 * @return string
 */
function lpnw_schema_site_url(): string {
	return trailingslashit( home_url( '/' ) );
}

/**
 * Encode and print one JSON-LD object inside a script tag.
 *
 * @param array<string, mixed> $data Schema.org document.
 */
function lpnw_schema_print_ld_json( array $data ): void {
	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	$json  = wp_json_encode( $data, $flags );
	if ( false === $json ) {
		return;
	}
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD; wp_json_encode with JSON_HEX_* for script safety.
	);
}

/**
 * Organization schema (all public views).
 *
 * @return array<string, mixed>
 */
function lpnw_schema_get_organization(): array {
	return array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => 'Land & Property Northwest',
		'url'         => lpnw_schema_site_url(),
		'description' => 'Property intelligence and instant alert service for Northwest England',
		'areaServed'  => array(
			'@type' => 'Place',
			'name'  => 'Northwest England',
		),
	);
}

/**
 * WebSite with SearchAction (front page only).
 *
 * @return array<string, mixed>
 */
function lpnw_schema_get_website_with_search(): array {
	$home = lpnw_schema_site_url();
	return array(
		'@context' => 'https://schema.org',
		'@type'    => 'WebSite',
		'name'     => 'Land & Property Northwest',
		'url'      => $home,
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => $home . '?s={search_term_string}',
			),
			'query-input' => 'required name=search_term_string',
		),
	);
}

/**
 * SoftwareApplication for the alert service (front page only).
 *
 * @return array<string, mixed>
 */
function lpnw_schema_get_software_application(): array {
	return array(
		'@context'            => 'https://schema.org',
		'@type'               => 'SoftwareApplication',
		'name'                => 'Land & Property Northwest',
		'applicationCategory' => 'BusinessApplication',
		'operatingSystem'     => 'Web',
		'url'                 => lpnw_schema_site_url(),
		'description'         => 'Paid subscription service with instant property and land alerts for Northwest England: listings, planning, auctions, EPC signals, and Land Registry data in one place.',
		'offers'              => array(
			'@type'         => 'AggregateOffer',
			'lowPrice'      => '0',
			'highPrice'     => '79.99',
			'priceCurrency' => 'GBP',
			'offerCount'    => 3,
			'url'           => esc_url_raw( home_url( '/pricing/' ) ),
		),
	);
}

/**
 * Product schemas for pricing tiers (pricing page).
 *
 * @return array<int, array<string, mixed>>
 */
function lpnw_schema_get_pricing_products(): array {
	$pricing_url = esc_url_raw( home_url( '/pricing/' ) );
	$brand       = array(
		'@type' => 'Brand',
		'name'  => 'Land & Property Northwest',
	);

	return array(
		array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => 'Land & Property Northwest - Free',
			'description' => 'Weekly digest email for Northwest England property intelligence. Build your watch list before upgrading.',
			'brand'       => $brand,
			'url'         => $pricing_url,
			'offers'      => array(
				'@type'         => 'Offer',
				'url'           => $pricing_url,
				'price'         => '0',
				'priceCurrency' => 'GBP',
				'availability'  => 'https://schema.org/InStock',
			),
		),
		array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => 'Land & Property Northwest - Pro',
			'description' => 'Instant alerts, full filtering, and all data sources for Northwest England.',
			'brand'       => $brand,
			'url'         => $pricing_url,
			'offers'      => array(
				'@type'              => 'Offer',
				'url'                => $pricing_url,
				'price'              => '19.99',
				'priceCurrency'      => 'GBP',
				'availability'       => 'https://schema.org/InStock',
				'priceSpecification' => array(
					'@type'           => 'UnitPriceSpecification',
					'price'           => '19.99',
					'priceCurrency'   => 'GBP',
					'billingDuration' => 'P1M',
				),
			),
		),
		array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => 'Land & Property Northwest - Investor VIP',
			'description' => 'Priority alerts, off-market deals, and direct introductions for serious Northwest investors.',
			'brand'       => $brand,
			'url'         => $pricing_url,
			'offers'      => array(
				'@type'              => 'Offer',
				'url'                => $pricing_url,
				'price'              => '79.99',
				'priceCurrency'      => 'GBP',
				'availability'       => 'https://schema.org/InStock',
				'priceSpecification' => array(
					'@type'           => 'UnitPriceSpecification',
					'price'           => '79.99',
					'priceCurrency'   => 'GBP',
					'billingDuration' => 'P1M',
				),
			),
		),
	);
}

/**
 * Article schema for single posts when Rank Math is not providing it.
 *
 * @return array<string, mixed>|null
 */
function lpnw_schema_get_article(): ?array {
	if ( ! is_singular( 'post' ) ) {
		return null;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$data = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'headline'         => get_the_title( $post ),
		'datePublished'    => get_post_time( 'c', true, $post ),
		'dateModified'     => get_post_modified_time( 'c', true, $post ),
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => esc_url_raw( get_permalink( $post ) ),
		),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => 'Land & Property Northwest',
			'url'   => lpnw_schema_site_url(),
		),
	);

	$excerpt = get_the_excerpt( $post );
	if ( is_string( $excerpt ) && '' !== trim( $excerpt ) ) {
		$data['description'] = wp_strip_all_tags( $excerpt );
	}

	if ( has_post_thumbnail( $post ) ) {
		$img = wp_get_attachment_image_url( (int) get_post_thumbnail_id( $post ), 'full' );
		if ( is_string( $img ) && '' !== $img ) {
			$data['image'] = array( esc_url_raw( $img ) );
		}
	}

	$author_id = (int) $post->post_author;
	if ( $author_id > 0 ) {
		$data['author'] = array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $author_id ),
		);
	}

	return $data;
}

/**
 * Whether the current page is an area landing page for LocalBusiness schema.
 *
 * Default: child of a page with slug `areas`. Extend via `lpnw_theme_is_schema_area_page`.
 *
 * @param WP_Post $page Current page object.
 * @return bool
 */
function lpnw_schema_is_area_page( WP_Post $page ): bool {
	if ( $page->post_parent > 0 ) {
		$parent = get_post( $page->post_parent );
		if ( $parent instanceof WP_Post && 'areas' === $parent->post_name ) {
			return true;
		}
	}

	/**
	 * Filters whether the current page should receive LocalBusiness area schema.
	 *
	 * @param bool    $is_area False by default (unless parent slug is `areas`).
	 * @param WP_Post $page    Queried page.
	 */
	return (bool) apply_filters( 'lpnw_theme_is_schema_area_page', false, $page );
}

/**
 * LocalBusiness-style schema for area landing pages (if used).
 *
 * @return array<string, mixed>|null
 */
function lpnw_schema_get_area_local_business(): ?array {
	if ( ! is_page() ) {
		return null;
	}

	$page = get_queried_object();
	if ( ! $page instanceof WP_Post ) {
		return null;
	}

	if ( ! lpnw_schema_is_area_page( $page ) ) {
		return null;
	}

	$area_name = get_the_title( $page );
	$page_url  = get_permalink( $page );

	return array(
		'@context'    => 'https://schema.org',
		'@type'       => 'LocalBusiness',
		'name'        => 'Land & Property Northwest - ' . $area_name,
		'url'         => $page_url ? esc_url_raw( $page_url ) : lpnw_schema_site_url(),
		'description' => sprintf(
			/* translators: %s: geographic area name (e.g. city or region). */
			__( 'Property intelligence and instant alerts for %s and the wider Northwest England market.', 'lpnw-theme' ),
			$area_name
		),
		'areaServed' => array(
			'@type' => 'Place',
			'name'  => $area_name,
		),
		'parentOrganization' => array(
			'@type' => 'Organization',
			'name'  => 'Land & Property Northwest',
			'url'   => lpnw_schema_site_url(),
		),
	);
}

/**
 * Output JSON-LD in the head for Organization (global), plus page-specific graphs.
 */
function lpnw_output_json_ld_schema(): void {
	if ( is_admin() || wp_is_json_request() || is_feed() || is_embed() ) {
		return;
	}

	lpnw_schema_print_ld_json( lpnw_schema_get_organization() );

	if ( is_front_page() ) {
		lpnw_schema_print_ld_json( lpnw_schema_get_website_with_search() );
		lpnw_schema_print_ld_json( lpnw_schema_get_software_application() );
	}

	if ( is_page( 'pricing' ) ) {
		foreach ( lpnw_schema_get_pricing_products() as $product ) {
			lpnw_schema_print_ld_json( $product );
		}
	}

	if ( is_singular( 'post' ) && ! lpnw_schema_rank_math_active() ) {
		$article = lpnw_schema_get_article();
		if ( is_array( $article ) ) {
			lpnw_schema_print_ld_json( $article );
		}
	}

	$area_ld = lpnw_schema_get_area_local_business();
	if ( is_array( $area_ld ) ) {
		lpnw_schema_print_ld_json( $area_ld );
	}
}
add_action( 'wp_head', 'lpnw_output_json_ld_schema', 5 );

