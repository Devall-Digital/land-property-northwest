<?php
/**
 * LPNW Theme functions.
 *
 * GeneratePress child theme for Land & Property Northwest.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

require_once get_stylesheet_directory() . '/inc/class-lpnw-og-card.php';
require_once get_stylesheet_directory() . '/inc/class-lpnw-open-graph.php';
require_once get_stylesheet_directory() . '/inc/class-lpnw-favicons.php';
LPNW_OG_Card::bootstrap();
LPNW_Open_Graph::bootstrap();
LPNW_Favicons::bootstrap();

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
 * Public URL for the shared brand logo PNG (opaque navy squircle; no transparency artefacts).
 *
 * Appends ?ver=filemtime so CDN and browsers refetch after theme deploy.
 *
 * @return string
 */
function lpnw_theme_get_brand_logo_url(): string {
	$path = get_stylesheet_directory() . '/assets/img/lpnw-brand-logo.png';
	$uri  = get_stylesheet_directory_uri() . '/assets/img/lpnw-brand-logo.png';
	if ( is_readable( $path ) ) {
		return $uri . '?ver=' . rawurlencode( (string) filemtime( $path ) );
	}
	return $uri;
}

/**
 * Enqueue parent and child styles, plus Google Fonts.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$child_theme = wp_get_theme();
		$style_path  = get_stylesheet_directory() . '/style.css';
		$script_path = get_stylesheet_directory() . '/assets/js/theme.js';
		$asset_ver   = $child_theme->get( 'Version' );
		if ( is_readable( $style_path ) ) {
			$asset_ver = (string) filemtime( $style_path );
		}

		wp_enqueue_style(
			'generatepress-parent',
			get_template_directory_uri() . '/style.css',
			array(),
			wp_get_theme( 'generatepress' )->get( 'Version' )
		);

		wp_enqueue_style(
			'lpnw-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'lpnw-child',
			get_stylesheet_uri(),
			array( 'generatepress-parent', 'lpnw-fonts' ),
			$asset_ver
		);

		$script_ver = is_readable( $script_path ) ? (string) filemtime( $script_path ) : $child_theme->get( 'Version' );

		wp_enqueue_script(
			'lpnw-theme',
			get_stylesheet_directory_uri() . '/assets/js/theme.js',
			array(),
			$script_ver,
			true
		);
	},
	20
);

/**
 * Inline glassmorphism interactions: scroll header state, reveal, hero parallax, stat counters, primary button shine.
 *
 * Attached to lpnw-theme; respects prefers-reduced-motion; passive listeners where applicable.
 */
function lpnw_theme_enqueue_glass_interactions_js(): void {
	if ( is_admin() ) {
		return;
	}

	$inline = <<<'JS'
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var scrollThreshold = 50;

		window.addEventListener('scroll', function () {
			var header = document.querySelector('.site-header');
			if (header) {
				header.classList.toggle('scrolled', window.scrollY > scrollThreshold);
			}
		}, { passive: true });

		var revealEls = document.querySelectorAll('.lpnw-reveal, .lpnw-trust-bar, .lpnw-stats-bar, .lpnw-how-it-works, .lpnw-home-feed, .lpnw-pricing-section, .lpnw-cta-banner:not(.lpnw-cta-banner--dashboard)');
		if (reduceMotion) {
			revealEls.forEach(function (el) {
				el.classList.add('lpnw-visible');
			});
		} else if ('IntersectionObserver' in window) {
			var revealObs = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						entry.target.classList.add('lpnw-visible');
						revealObs.unobserve(entry.target);
					}
				});
			}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
			revealEls.forEach(function (el) {
				revealObs.observe(el);
			});
		} else {
			revealEls.forEach(function (el) {
				el.classList.add('lpnw-visible');
			});
		}

		if (!reduceMotion && window.matchMedia('(hover: hover) and (min-width: 768px)').matches) {
			var hero = document.querySelector('.lpnw-hero');
			if (hero) {
				var shapes = hero.querySelectorAll('.lpnw-hero__cityscape .lpnw-hero__shape');
				var clouds = hero.querySelectorAll('.lpnw-hero__cloud');
				var scene = hero.querySelector('.lpnw-hero__scene');
				var svgIll = hero.querySelector('.lpnw-hero__illustration');
				var parallax = hero.querySelector('.lpnw-hero__parallax');
				var hasLayeredSvg = !!(parallax && parallax.querySelector('.lpnw-hero__layer--back'));
				var needsHeroMouse = shapes.length || clouds.length || (scene && (svgIll || hasLayeredSvg)) || hasLayeredSvg;
				if (needsHeroMouse) {
					hero.addEventListener('mousemove', function (e) {
						var rect = hero.getBoundingClientRect();
						var x = (e.clientX - rect.left) / rect.width - 0.5;
						var y = (e.clientY - rect.top) / rect.height - 0.5;
						if (shapes.length) {
							shapes.forEach(function (shape, i) {
								var depth = (i + 1) * 12;
								shape.style.transform = 'translate(' + (x * depth) + 'px, ' + (y * depth * 0.35) + 'px)';
							});
						}
						if (clouds.length) {
							clouds.forEach(function (cloud, j) {
								var cd = (j + 1) * 6;
								cloud.style.transform = 'translate(' + (x * cd) + 'px, ' + (y * cd * 0.5) + 'px)';
							});
						}
						if (hasLayeredSvg) {
							hero.style.setProperty('--lpnw-hero-mx', x.toFixed(4));
							hero.style.setProperty('--lpnw-hero-my', y.toFixed(4));
						}
						if (scene && (svgIll || hasLayeredSvg)) {
							var rx = (-y * 1.35).toFixed(2);
							var ry = (x * 2.05).toFixed(2);
							scene.style.setProperty('--lpnw-tilt-rx', rx + 'deg');
							scene.style.setProperty('--lpnw-tilt-ry', ry + 'deg');
						}
					}, { passive: true });
				}
			}
		}

		if (!reduceMotion) {
			var heroScroll = document.querySelector('.lpnw-hero');
			if (heroScroll) {
				var sky = heroScroll.querySelector('.lpnw-hero__sky');
				var city = heroScroll.querySelector('.lpnw-hero__cityscape');
				var sceneEl = heroScroll.querySelector('.lpnw-hero__scene');
				var parallax = heroScroll.querySelector('.lpnw-hero__parallax');
				var parallaxFallback = !parallax && sceneEl && sceneEl.querySelector('.lpnw-hero__layer--back') ? sceneEl : null;
				var parallaxTarget = parallax || parallaxFallback;
				var scrollHandler = function () {
					var rect = heroScroll.getBoundingClientRect();
					if (rect.bottom < 0 || rect.top > window.innerHeight) {
						return;
					}
					var p = Math.max(0, Math.min(1, 1 - (rect.top + rect.height * 0.22) / window.innerHeight));
					p = Math.pow(p, 0.85);
					if (parallaxTarget) {
						parallaxTarget.style.setProperty('--lpnw-parallax-p', String(p));
					}
					if (sky) {
						sky.style.transform = 'translateY(' + (p * 12) + 'px)';
					}
					if (city) {
						city.style.transform = 'translateY(' + (p * 8) + 'px)';
					}
					if (sceneEl) {
						sceneEl.style.setProperty('--lpnw-scene-y', (p * 20) + 'px');
					}
				};
				window.addEventListener('scroll', scrollHandler, { passive: true });
				scrollHandler();
			}
		}

		if (!reduceMotion && 'IntersectionObserver' in window) {
			var parseCounterEl = function (el) {
				var raw = el.textContent.replace(/,/g, '').trim();
				var mPlus = raw.match(/^(\d+)\+$/);
				var mNum = raw.match(/^(\d+)$/);
				if (mPlus) {
					return { target: parseInt(mPlus[1], 10), suffix: '+' };
				}
				if (mNum) {
					return { target: parseInt(mNum[1], 10), suffix: '' };
				}
				return null;
			};

			var counterObs = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting || entry.target.dataset.counted) {
						return;
					}
					var el = entry.target;
					var parsed = parseCounterEl(el);
					if (!parsed || !parsed.target) {
						return;
					}
					el.dataset.counted = '1';
					counterObs.unobserve(el);
					var target = parsed.target;
					var suffix = parsed.suffix;
					el.textContent = (0).toLocaleString() + suffix;
					var current = 0;
					var step = Math.ceil(target / 40);
					var timer = setInterval(function () {
						current += step;
						if (current >= target) {
							current = target;
							clearInterval(timer);
						}
						el.textContent = current.toLocaleString() + suffix;
					}, 30);
				});
			}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

			document.querySelectorAll('.lpnw-stats-bar__value').forEach(function (wrap) {
				var counterEl = wrap.querySelector('.lpnw-property-count') || wrap;
				if (parseCounterEl(counterEl)) {
					counterObs.observe(counterEl);
				}
			});
		}

		if (!reduceMotion) {
			document.querySelectorAll('.lpnw-btn--primary').forEach(function (btn) {
				btn.addEventListener('mousemove', function (e) {
					var r = btn.getBoundingClientRect();
					var x = ((e.clientX - r.left) / r.width) * 100;
					var y = ((e.clientY - r.top) / r.height) * 100;
					btn.style.setProperty('--lpnw-shine-x', x + '%');
					btn.style.setProperty('--lpnw-shine-y', y + '%');
				}, { passive: true });
			});
		}
	});
})();
JS;

	wp_add_inline_script( 'lpnw-theme', $inline, 'after' );
}
add_action( 'wp_enqueue_scripts', 'lpnw_theme_enqueue_glass_interactions_js', 21 );

/**
 * Set up theme support.
 */
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
			)
		);

		// Subscriber-only nav; assign the "Subscriber" menu in Appearance > Menus (or via tools/lpnw-woo-setup.php).
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
	},
	11
);

/**
 * Register widget areas.
 */
add_action(
	'widgets_init',
	function () {
		register_sidebar(
			array(
				'name'          => __( 'Footer Column 1', 'lpnw-theme' ),
				'id'            => 'lpnw-footer-1',
				'before_widget' => '<div class="widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h4 class="widget-title">',
				'after_title'   => '</h4>',
			)
		);

		register_sidebar(
			array(
				'name'          => __( 'Footer Column 2', 'lpnw-theme' ),
				'id'            => 'lpnw-footer-2',
				'before_widget' => '<div class="widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h4 class="widget-title">',
				'after_title'   => '</h4>',
			)
		);

		register_sidebar(
			array(
				'name'          => __( 'Footer Column 3', 'lpnw-theme' ),
				'id'            => 'lpnw-footer-3',
				'before_widget' => '<div class="widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h4 class="widget-title">',
				'after_title'   => '</h4>',
			)
		);
	}
);

/**
 * Add custom body classes.
 *
 * @param array<string> $classes Existing body classes.
 * @return array<string>
 */
add_filter(
	'body_class',
	function ( array $classes ): array {
		$classes[] = 'lpnw-site';

		if ( is_user_logged_in() ) {
			$tier      = class_exists( 'LPNW_Subscriber' ) ? LPNW_Subscriber::get_tier( get_current_user_id() ) : 'free';
			$classes[] = 'lpnw-tier-' . $tier;
		}

		return $classes;
	}
);

/**
 * Login / register screen: LPNW brand fonts and layout (matches site hero and CTAs).
 */
add_action(
	'login_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'lpnw-login-fonts',
			'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
			array(),
			null
		);

		$css = '
body.login {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	background-color: #1B2A4A;
	min-height: 100vh;
	margin: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 24px 16px;
	box-sizing: border-box;
	position: relative;
}
body.login::before {
	content: "";
	position: fixed;
	inset: 0;
	z-index: 0;
	pointer-events: none;
	background-image:
		repeating-linear-gradient(
			-32deg,
			rgba(255, 255, 255, 0.03) 0px,
			rgba(255, 255, 255, 0.03) 1px,
			transparent 1px,
			transparent 10px
		),
		repeating-linear-gradient(
			122deg,
			rgba(255, 255, 255, 0.02) 0px,
			rgba(255, 255, 255, 0.02) 1px,
			transparent 1px,
			transparent 14px
		),
		radial-gradient(ellipse 90% 55% at 50% -10%, rgba(245, 192, 74, 0.12), transparent 55%),
		linear-gradient(135deg, #1B2A4A 0%, #2D4470 55%, #1B2A4A 100%);
}
body.login #login {
	position: relative;
	z-index: 1;
	width: 100%;
	max-width: 400px;
	margin: 0 auto;
	padding: 2rem 2rem 1.75rem;
	background: #fff;
	border-radius: 12px;
	box-shadow:
		0 16px 48px rgba(27, 42, 74, 0.35),
		0 4px 16px rgba(0, 0, 0, 0.12);
	box-sizing: border-box;
}
#login h1 {
	margin: 0 0 1.5rem;
	padding: 0;
	text-align: center;
}
#login h1 a {
	background-image: none !important;
	font-size: 0;
	font-weight: 700;
	color: transparent;
	font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif;
	text-indent: 0;
	width: auto;
	height: auto;
	display: inline-block;
	line-height: 1.25;
	text-align: center;
	text-decoration: none;
	padding: 0;
	outline: none;
	box-shadow: none;
}
#login h1 a:focus-visible {
	outline: 2px solid #E8A317;
	outline-offset: 4px;
}
#login h1 a::after {
	content: "Land & Property Northwest";
	font-size: 1.35rem;
	color: #1B2A4A;
	display: block;
}
.login form,
.login form.shake {
	margin-top: 0;
	padding: 0;
	border: none;
	background: transparent;
	box-shadow: none !important;
}
.login form p {
	margin-bottom: 1rem;
}
.login form p.submit {
	margin-bottom: 0;
	margin-top: 1.25rem;
}
.login label {
	font-size: 0.875rem;
	font-weight: 600;
	color: #374151;
}
.login form .input,
.login input[type="text"],
.login input[type="password"],
.login input[type="email"] {
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
	border: 1px solid #E5E7EB;
	border-radius: 8px;
	padding: 12px 14px;
	font-size: 16px;
	line-height: 1.4;
	transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.login form .input:focus,
.login input[type="text"]:focus,
.login input[type="password"]:focus,
.login input[type="email"]:focus {
	border-color: #E8A317;
	outline: none;
	box-shadow: 0 0 0 3px rgba(232, 163, 23, 0.22);
}
.login .user-pass-wrap {
	position: relative;
}
.wp-core-ui .button.wp-hide-pw {
	color: #1B2A4A;
	border-radius: 6px;
}
.wp-core-ui .button.wp-hide-pw:hover {
	color: #E8A317;
}
.login .forgetmenot {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 0 !important;
}
.login .forgetmenot input[type="checkbox"] {
	width: 18px;
	height: 18px;
	margin: 0;
	accent-color: #E8A317;
	cursor: pointer;
	flex-shrink: 0;
}
.login .forgetmenot label {
	font-weight: 500;
	cursor: pointer;
	margin: 0;
	line-height: 1.3;
}
.wp-core-ui .button.button-primary.button-large,
.wp-core-ui .button.button-primary,
#wp-submit {
	width: 100%;
	box-sizing: border-box;
	background: #E8A317 !important;
	border: none !important;
	border-radius: 8px !important;
	color: #fff !important;
	padding: 12px 16px !important;
	font-size: 1rem !important;
	font-weight: 700 !important;
	font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif !important;
	line-height: 1.4 !important;
	height: auto !important;
	min-height: 0 !important;
	text-shadow: none !important;
	box-shadow: none !important;
	transition: background 0.15s ease, filter 0.15s ease;
}
.wp-core-ui .button.button-primary.button-large:hover,
.wp-core-ui .button.button-primary:hover,
#wp-submit:hover,
#wp-submit:focus {
	background: #cf900f !important;
	color: #fff !important;
	filter: none;
}
.wp-core-ui .button.button-primary.button-large:focus,
#wp-submit:focus {
	box-shadow: 0 0 0 3px rgba(232, 163, 23, 0.35) !important;
}
#login #nav,
#login #backtoblog {
	margin: 1.125rem 0 0;
	padding: 0;
	text-align: center;
	font-size: 0.9375rem;
}
#login #backtoblog {
	margin-top: 0.75rem;
}
.login #nav a,
.login #backtoblog a,
.login .privacy-policy-page-link a {
	color: #1B2A4A;
	font-weight: 600;
	text-decoration: none;
	transition: color 0.15s ease;
}
.login #nav a:hover,
.login #nav a:focus,
.login #backtoblog a:hover,
.login #backtoblog a:focus,
.login .privacy-policy-page-link a:hover,
.login .privacy-policy-page-link a:focus {
	color: #E8A317;
}
.login #login_error,
.login .notice-error {
	border: none !important;
	border-left: 4px solid #DC2626 !important;
	background: #FEF2F2 !important;
	color: #991B1B !important;
	box-shadow: none !important;
	padding: 12px 14px !important;
	border-radius: 0 8px 8px 0 !important;
	font-size: 0.9375rem;
	line-height: 1.45;
}
.login #login_error p,
.login .notice-error p {
	margin: 0;
	padding: 0;
}
.login .message:not(.notice-error),
.login .notice-success,
.login .notice-info {
	border: none !important;
	border-left: 4px solid #059669 !important;
	background: #ECFDF5 !important;
	color: #065F46 !important;
	box-shadow: none !important;
	padding: 12px 14px !important;
	border-radius: 0 8px 8px 0 !important;
	font-size: 0.9375rem;
	line-height: 1.45;
}
.login .pw-weak,
.login #pass-strength-result {
	border-radius: 8px;
}
.login .language-switcher {
	margin-top: 1.25rem;
	text-align: center;
	position: relative;
	z-index: 1;
}
.login .language-switcher select {
	border-radius: 8px;
	border: 1px solid #E5E7EB;
	padding: 8px 12px;
	font-size: 0.875rem;
}
#login h1 a::before {
	content: "";
	display: block;
	width: 72px;
	height: 72px;
	margin: 0 auto 0.75rem;
	background: url(' . esc_url( lpnw_theme_get_brand_logo_url() ) . ') center / contain no-repeat;
}
';

		wp_add_inline_style( 'lpnw-login-fonts', $css );
	}
);

add_filter(
	'login_headerurl',
	function () {
		return home_url();
	}
);

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
		if ( is_page( array( 'dashboard', 'preferences', 'saved', 'saved-properties', 'map', 'email-preview', 'pricing', 'properties', 'contact' ) ) ) {
			return false;
		}
		return $show;
	}
);

/**
 * GeneratePress: do not render Customizer logo slot (mark + title come from generate_site_title_output).
 */
add_filter( 'generate_has_logo_site_branding', '__return_false' );

add_filter(
	'generate_header_items_order',
	function ( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}

		return array_values( array_diff( $order, array( 'logo', 'header-widget' ) ) );
	}
);

/**
 * URL for the Browse Properties page (published page if present, else canonical path).
 *
 * @return string
 */
function lpnw_theme_get_browse_properties_url(): string {
	$page = get_page_by_path( 'properties' );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		$permalink = get_permalink( $page );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}

	$page = get_page_by_path( 'browse-properties' );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		$permalink = get_permalink( $page );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}

	return home_url( '/properties/' );
}

/**
 * Whether a nav item is the static front page / home link.
 *
 * @param object $item Menu item object.
 * @return bool
 */
function lpnw_theme_nav_item_is_home( $item ): bool {
	if ( ! is_object( $item ) ) {
		return false;
	}

	$front_id = (int) get_option( 'page_on_front' );
	if ( $front_id > 0 && isset( $item->object_id, $item->type, $item->object )
		&& 'post_type' === $item->type && 'page' === $item->object
		&& (int) $item->object_id === $front_id ) {
		return true;
	}

	if ( ! empty( $item->url ) && is_string( $item->url ) ) {
		$item_url = untrailingslashit( $item->url );
		$home     = untrailingslashit( home_url( '/' ) );
		if ( $item_url === $home ) {
			return true;
		}
	}

	if ( ! empty( $item->classes ) && is_array( $item->classes ) && in_array( 'menu-item-home', $item->classes, true ) ) {
		return true;
	}

	return false;
}

/**
 * Whether a nav item is Browse Properties.
 *
 * @param object $item Menu item object.
 * @return bool
 */
function lpnw_theme_nav_item_is_browse_properties( $item ): bool {
	if ( ! is_object( $item ) ) {
		return false;
	}

	if ( isset( $item->object_id, $item->type, $item->object )
		&& 'post_type' === $item->type && 'page' === $item->object ) {
		$page = get_post( (int) $item->object_id );
		if ( $page instanceof WP_Post && in_array( $page->post_name, array( 'properties', 'browse-properties' ), true ) ) {
			return true;
		}
	}

	if ( ! empty( $item->url ) && is_string( $item->url ) ) {
		$path = wp_parse_url( $item->url, PHP_URL_PATH );
		if ( is_string( $path ) && preg_match( '#(^|/)(properties|browse-properties)/?$#', $path ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether a nav item is the Pricing page.
 *
 * @param object $item Menu item object.
 * @return bool
 */
function lpnw_theme_nav_item_is_pricing( $item ): bool {
	if ( ! is_object( $item ) ) {
		return false;
	}

	if ( isset( $item->object_id, $item->type, $item->object )
		&& 'post_type' === $item->type && 'page' === $item->object ) {
		$page = get_post( (int) $item->object_id );
		if ( $page instanceof WP_Post && 'pricing' === $page->post_name ) {
			return true;
		}
	}

	if ( ! empty( $item->url ) && is_string( $item->url ) ) {
		$path = wp_parse_url( $item->url, PHP_URL_PATH );
		if ( is_string( $path ) && false !== strpos( $path, 'pricing' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Synthetic primary-menu item: Browse Properties.
 *
 * @return stdClass
 */
function lpnw_theme_nav_browse_properties_item(): stdClass {
	$url  = lpnw_theme_get_browse_properties_url();
	$item = new stdClass();

	$item->ID                    = 91000001;
	$item->db_id                 = 91000001;
	$item->menu_item_parent      = 0;
	$item->object_id             = 91000001;
	$item->post_parent           = 0;
	$item->type                  = 'custom';
	$item->object                = 'custom';
	$item->type_label            = __( 'Custom Link', 'lpnw-theme' );
	$item->title                 = __( 'Browse Properties', 'lpnw-theme' );
	$item->url                   = $url;
	$item->target                = '';
	$item->attr_title            = '';
	$item->description           = '';
	$item->xfn                   = '';
	$item->classes               = array(
		'menu-item',
		'menu-item-type-custom',
		'menu-item-object-custom',
		'lpnw-menu-browse-properties',
	);
	$item->current               = false;
	$item->current_item_ancestor = false;
	$item->current_item_parent   = false;

	return $item;
}

/**
 * Primary menu: Home, Browse Properties, Pricing, then other items in original order.
 *
 * @param array<int, object> $items Sorted menu items.
 * @param object             $args  wp_nav_menu() arguments.
 * @return array<int, object>
 */
function lpnw_theme_nav_order_primary( array $items, $args ): array {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $items;
	}

	$home_items    = array();
	$browse_items  = array();
	$pricing_items = array();
	$rest          = array();

	foreach ( $items as $item ) {
		if ( ! is_object( $item ) || ! isset( $item->ID ) ) {
			continue;
		}
		if ( lpnw_theme_nav_item_is_home( $item ) ) {
			$home_items[] = $item;
		} elseif ( lpnw_theme_nav_item_is_browse_properties( $item ) ) {
			$browse_items[] = $item;
		} elseif ( lpnw_theme_nav_item_is_pricing( $item ) ) {
			$pricing_items[] = $item;
		} else {
			$rest[] = $item;
		}
	}

	$ordered = array();

	if ( ! empty( $home_items ) ) {
		$ordered[] = $home_items[0];
		for ( $i = 1, $c = count( $home_items ); $i < $c; $i++ ) {
			$rest[] = $home_items[ $i ];
		}
	}

	if ( ! empty( $browse_items ) ) {
		$ordered[] = $browse_items[0];
		for ( $i = 1, $c = count( $browse_items ); $i < $c; $i++ ) {
			$rest[] = $browse_items[ $i ];
		}
	} else {
		$ordered[] = lpnw_theme_nav_browse_properties_item();
	}

	if ( ! empty( $pricing_items ) ) {
		$ordered[] = $pricing_items[0];
		for ( $i = 1, $c = count( $pricing_items ); $i < $c; $i++ ) {
			$rest[] = $pricing_items[ $i ];
		}
	}

	return array_merge( $ordered, $rest );
}

add_filter( 'wp_nav_menu_objects', 'lpnw_theme_nav_order_primary', 15, 2 );

/**
 * Primary menu: Log in for guests; Dashboard and Log out for logged-in users.
 *
 * @param string $items HTML list items.
 * @param object $args  wp_nav_menu() arguments.
 * @return string
 */
function lpnw_theme_nav_append_auth_items( string $items, $args ): string {
	if ( ! is_object( $args ) ) {
		return $items;
	}

	$is_primary = ! empty( $args->theme_location ) && 'primary' === $args->theme_location;
	$menu_slug  = '';
	if ( ! empty( $args->menu ) && is_object( $args->menu ) && isset( $args->menu->slug ) ) {
		$menu_slug = (string) $args->menu->slug;
	}
	$slug_is_primary = '' !== $menu_slug && in_array( strtolower( $menu_slug ), array( 'primary', 'primary-menu' ), true );

	if ( ! $is_primary && ! $slug_is_primary ) {
		return $items;
	}

	if ( ! is_user_logged_in() ) {
		$items .= '<li class="menu-item lpnw-nav-login"><a href="' . esc_url( wp_login_url( home_url( '/dashboard/' ) ) ) . '">' . esc_html__( 'Log in', 'lpnw-theme' ) . '</a></li>';
	} else {
		$items .= '<li class="menu-item lpnw-nav-dashboard"><a href="' . esc_url( home_url( '/dashboard/' ) ) . '">' . esc_html__( 'Dashboard', 'lpnw-theme' ) . '</a></li>';
		$items .= '<li class="menu-item lpnw-nav-logout"><a href="' . esc_url( wp_logout_url( home_url() ) ) . '">' . esc_html__( 'Log out', 'lpnw-theme' ) . '</a></li>';
	}

	return $items;
}
add_filter( 'wp_nav_menu_items', 'lpnw_theme_nav_append_auth_items', 10, 2 );

/**
 * GeneratePress: site title with shared PNG brand mark (same asset as favicon).
 *
 * @param string $output Default title HTML.
 * @return string
 */
add_filter(
	'generate_site_title_output',
	function ( $output ) {
		$schema  = function_exists( 'generate_get_schema_type' ) && 'microdata' === generate_get_schema_type() ? ' itemprop="headline"' : '';
		$tag     = ( is_front_page() && is_home() ) ? 'h1' : 'p';
		$href    = esc_url( apply_filters( 'generate_site_title_href', home_url( '/' ) ) );
		$name    = esc_html__( 'Land & Property Northwest', 'lpnw-theme' );
		$markurl = esc_url( lpnw_theme_get_brand_logo_url() );

		return sprintf(
			'<%1$s class="main-title lpnw-site-title"%4$s><a href="%2$s" class="lpnw-site-title__link" rel="home"><img src="%5$s" alt="" class="lpnw-site-title__mark" width="48" height="48" decoding="async" loading="eager" /><span class="lpnw-site-title__text">%3$s</span></a></%1$s>',
			tag_escape( $tag ),
			$href,
			$name,
			$schema,
			$markurl
		);
	},
	10,
	1
);

/**
 * Blog / posts archive URL for footer and menus.
 *
 * @return string
 */
function lpnw_theme_get_blog_url(): string {
	$posts_page_id = (int) get_option( 'page_for_posts' );
	if ( $posts_page_id > 0 ) {
		$permalink = get_permalink( $posts_page_id );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}

	$archive = get_post_type_archive_link( 'post' );
	if ( is_string( $archive ) && '' !== $archive ) {
		return $archive;
	}

	return home_url( '/blog/' );
}

/**
 * Output the three-column footer block (before GeneratePress copyright row).
 */
function lpnw_theme_render_footer_mega(): void {
	$year      = (int) gmdate( 'Y' );
	$copyright = sprintf(
		/* translators: %d: current year (Gregorian). */
		esc_html__( '© %d Land & Property Northwest. NW Property Intelligence & Alerts.', 'lpnw-theme' ),
		$year
	);

	$blog_url = lpnw_theme_get_blog_url();
	$register = wp_registration_url();
	if ( '' === $register ) {
		$register = home_url( '/pricing/' );
	}

	$quick_links = array(
		array(
			'url'   => home_url( '/' ),
			'label' => __( 'Home', 'lpnw-theme' ),
		),
		array(
			'url'   => lpnw_theme_get_browse_properties_url(),
			'label' => __( 'Browse Properties', 'lpnw-theme' ),
		),
		array(
			'url'   => home_url( '/pricing/' ),
			'label' => __( 'Pricing', 'lpnw-theme' ),
		),
		array(
			'url'   => home_url( '/about/' ),
			'label' => __( 'About', 'lpnw-theme' ),
		),
		array(
			'url'   => home_url( '/contact/' ),
			'label' => __( 'Contact', 'lpnw-theme' ),
		),
		array(
			'url'   => $blog_url,
			'label' => __( 'Blog', 'lpnw-theme' ),
		),
	);

	$subscriber_links = array(
		array(
			'url'   => home_url( '/dashboard/' ),
			'label' => __( 'Dashboard', 'lpnw-theme' ),
		),
		array(
			'url'   => home_url( '/preferences/' ),
			'label' => __( 'Alert Preferences', 'lpnw-theme' ),
		),
		array(
			'url'   => home_url( '/map/' ),
			'label' => __( 'Property Map', 'lpnw-theme' ),
		),
		array(
			'url'   => home_url( '/saved/' ),
			'label' => __( 'Saved Properties', 'lpnw-theme' ),
		),
	);

	if ( is_user_logged_in() ) {
		$subscriber_links[] = array(
			'url'   => wp_logout_url( home_url() ),
			'label' => __( 'Log out', 'lpnw-theme' ),
		);
	} else {
		$subscriber_links[] = array(
			'url'   => $register,
			'label' => __( 'Register', 'lpnw-theme' ),
		);
		$subscriber_links[] = array(
			'url'   => wp_login_url( home_url( '/dashboard/' ) ),
			'label' => __( 'Log in', 'lpnw-theme' ),
		);
	}
	?>
	<div class="lpnw-footer-mega">
		<div class="lpnw-footer-mega__inner">
			<div class="lpnw-footer-mega__col lpnw-footer-mega__col--brand">
				<p class="lpnw-footer-mega__brand-title"><?php echo esc_html__( 'Land & Property Northwest', 'lpnw-theme' ); ?></p>
				<p class="lpnw-footer-mega__tagline"><?php echo esc_html__( 'Instant property alerts for Northwest England. Get notified when properties match your criteria.', 'lpnw-theme' ); ?></p>
				<p class="lpnw-footer-mega__copyright"><?php echo esc_html( $copyright ); ?></p>
			</div>
			<nav class="lpnw-footer-mega__col" aria-label="<?php echo esc_attr__( 'Quick links', 'lpnw-theme' ); ?>">
				<p class="lpnw-footer-mega__heading"><?php echo esc_html__( 'Quick Links', 'lpnw-theme' ); ?></p>
				<ul class="lpnw-footer-mega__list">
					<?php foreach ( $quick_links as $item ) : ?>
						<li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<nav class="lpnw-footer-mega__col" aria-label="<?php echo esc_attr__( 'Subscriber links', 'lpnw-theme' ); ?>">
				<p class="lpnw-footer-mega__heading"><?php echo esc_html__( 'For Subscribers', 'lpnw-theme' ); ?></p>
				<ul class="lpnw-footer-mega__list">
					<?php foreach ( $subscriber_links as $item ) : ?>
						<li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</div>
	</div>
	<?php
}
add_action( 'generate_before_copyright', 'lpnw_theme_render_footer_mega', 4 );

/**
 * GeneratePress: hide default copyright row (brand and links live in lpnw_theme_render_footer_mega).
 *
 * @return string
 */
add_filter( 'generate_copyright', '__return_empty_string' );

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
		'logo'        => esc_url_raw( lpnw_theme_get_brand_logo_url() ),
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
		'@context'        => 'https://schema.org',
		'@type'           => 'WebSite',
		'name'            => 'Land & Property Northwest',
		'url'             => $home,
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
			'logo'  => array(
				'@type' => 'ImageObject',
				'url'   => esc_url_raw( lpnw_theme_get_brand_logo_url() ),
			),
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
		'@context'           => 'https://schema.org',
		'@type'              => 'LocalBusiness',
		'name'               => 'Land & Property Northwest - ' . $area_name,
		'url'                => $page_url ? esc_url_raw( $page_url ) : lpnw_schema_site_url(),
		'description'        => sprintf(
			/* translators: %s: geographic area name (e.g. city or region). */
			__( 'Property intelligence and instant alerts for %s and the wider Northwest England market.', 'lpnw-theme' ),
			$area_name
		),
		'areaServed'         => array(
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

/**
 * Subscriber-only pages where sitewide signup prompts should not appear.
 *
 * @return bool
 */
function lpnw_theme_is_subscriber_shell_page(): bool {
	if ( ! is_page() ) {
		return false;
	}

	return is_page( array( 'dashboard', 'preferences', 'saved' ) );
}

/**
 * Human-readable area phrase for post CTAs (title keyword match, else Northwest).
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function lpnw_theme_get_post_cta_area_label( WP_Post $post ): string {
	$title = strtolower( get_the_title( $post ) );

	$pairs = array(
		'liverpool'          => __( 'Liverpool and Merseyside', 'lpnw-theme' ),
		'merseyside'         => __( 'Merseyside', 'lpnw-theme' ),
		'lancashire'         => __( 'Lancashire', 'lpnw-theme' ),
		'greater manchester' => __( 'Greater Manchester', 'lpnw-theme' ),
		'manchester'         => __( 'Greater Manchester', 'lpnw-theme' ),
		'bolton'             => __( 'Bolton and Greater Manchester', 'lpnw-theme' ),
		'stockport'          => __( 'Stockport and Cheshire', 'lpnw-theme' ),
		'chester'            => __( 'Cheshire', 'lpnw-theme' ),
		'cheshire'           => __( 'Cheshire', 'lpnw-theme' ),
		'warrington'         => __( 'Warrington', 'lpnw-theme' ),
		'preston'            => __( 'Preston and Lancashire', 'lpnw-theme' ),
		'blackpool'          => __( 'Blackpool and the Fylde', 'lpnw-theme' ),
		'carlisle'           => __( 'Carlisle and Cumbria', 'lpnw-theme' ),
		'cumbria'            => __( 'Cumbria', 'lpnw-theme' ),
		'wigan'              => __( 'Wigan', 'lpnw-theme' ),
		'auction'            => __( 'the Northwest', 'lpnw-theme' ),
	);

	foreach ( $pairs as $needle => $label ) {
		if ( str_contains( $title, $needle ) ) {
			return $label;
		}
	}

	return __( 'the Northwest', 'lpnw-theme' );
}

/**
 * Inline styles for sticky signup bar and conversion CTAs.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() ) {
			return;
		}

		$css = '
:root {
	--lpnw-cookie-offset: 0px;
	--lpnw-sticky-cta-pad: 5.5rem;
}
.lpnw-sticky-cta-bar {
	position: fixed;
	left: 0;
	right: 0;
	bottom: var(--lpnw-cookie-offset, 0px);
	z-index: 99990;
	background: linear-gradient(135deg, var(--lpnw-navy) 0%, var(--lpnw-navy-light) 100%);
	color: var(--lpnw-white);
	padding: 0.65rem 1rem calc(0.65rem + env(safe-area-inset-bottom, 0));
	box-shadow: 0 -4px 24px rgba(27, 42, 74, 0.25);
	box-sizing: border-box;
	transform: translateY(110%);
	opacity: 0;
	pointer-events: none;
	transition: transform 0.4s ease, opacity 0.4s ease;
}
.lpnw-sticky-cta-bar.lpnw-sticky-cta-bar--visible {
	transform: translateY(0);
	opacity: 1;
	pointer-events: auto;
}
.lpnw-sticky-cta-bar__inner {
	max-width: min(72rem, 100%);
	margin: 0 auto;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	gap: 0.5rem 1rem;
}
.lpnw-sticky-cta-bar__text {
	margin: 0;
	font-size: 0.875rem;
	font-weight: 600;
	line-height: 1.35;
	text-align: center;
	max-width: 20rem;
}
.lpnw-sticky-cta-bar__dismiss {
	flex-shrink: 0;
	min-width: 2.25rem;
	height: 2.25rem;
	padding: 0;
	border: 1px solid rgba(255,255,255,0.35);
	border-radius: 6px;
	background: transparent;
	color: var(--lpnw-white);
	font-size: 1.25rem;
	line-height: 1;
	cursor: pointer;
	transition: background 0.15s ease, border-color 0.15s ease;
}
.lpnw-sticky-cta-bar__dismiss:hover {
	background: rgba(255,255,255,0.1);
	border-color: rgba(255,255,255,0.55);
}
.lpnw-sticky-cta-bar__dismiss:focus-visible {
	outline: 2px solid var(--lpnw-amber);
	outline-offset: 2px;
}
body.lpnw-sticky-cta-visible {
	padding-bottom: calc(var(--lpnw-sticky-cta-pad) + var(--lpnw-cookie-offset, 0px));
}
@media (max-width: 600px) {
	:root {
		--lpnw-sticky-cta-pad: 6.75rem;
	}
	.lpnw-sticky-cta-bar__inner {
		flex-direction: column;
		text-align: center;
		gap: 0.5rem;
	}
	.lpnw-sticky-cta-bar__text {
		max-width: none;
	}
	.lpnw-sticky-cta-bar .lpnw-btn {
		width: 100%;
		max-width: 20rem;
	}
}
.lpnw-property-list--grid + .lpnw-latest-properties-signup-cta {
	margin-top: 1.5rem;
}
.lpnw-post-signup-cta {
	margin-top: 2rem;
}
';

		wp_add_inline_style( 'lpnw-child', $css );
	},
	25
);

/**
 * Sticky bottom signup bar for guests (dismissible via cookie).
 */
add_action(
	'wp_footer',
	function () {
		if ( is_admin() || wp_is_json_request() || is_feed() || is_embed() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		if ( lpnw_theme_is_subscriber_shell_page() ) {
			return;
		}

		if ( ! empty( $_COOKIE['lpnw_sticky_cta_dismiss'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$register = wp_registration_url();
		if ( '' === $register ) {
			$register = home_url( '/pricing/' );
		}
		?>
		<div id="lpnw-sticky-cta-bar" class="lpnw-sticky-cta-bar" role="region" aria-label="<?php echo esc_attr__( 'Sign up for property alerts', 'lpnw-theme' ); ?>">
			<div class="lpnw-sticky-cta-bar__inner">
				<p class="lpnw-sticky-cta-bar__text"><?php esc_html_e( 'Get instant NW property alerts', 'lpnw-theme' ); ?></p>
				<a class="lpnw-btn lpnw-btn--primary" href="<?php echo esc_url( $register ); ?>"><?php esc_html_e( 'Start free', 'lpnw-theme' ); ?></a>
				<button type="button" class="lpnw-sticky-cta-bar__dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'lpnw-theme' ); ?>">&times;</button>
			</div>
		</div>
		<script>
		(function () {
			var bar = document.getElementById('lpnw-sticky-cta-bar');
			if (!bar) return;
			var root = document.documentElement;
			var revealMs = 5000;
			var cookieSelectors = [
				'#cookie-notice.cookie-notice',
				'#cookie-notice.cn-position-bottom',
				'#cmplz-cookiebanner-container',
				'.cmplz-cookiebanner'
			];
			function lpnwCookieBannerHeight() {
				var maxH = 0;
				var vh = window.innerHeight || 0;
				cookieSelectors.forEach(function (sel) {
					var nodes = document.querySelectorAll(sel);
					for (var i = 0; i < nodes.length; i++) {
						var el = nodes[i];
						var st = window.getComputedStyle(el);
						if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity) === 0) {
							continue;
						}
						var rect = el.getBoundingClientRect();
						if (rect.height < 8) {
							continue;
						}
						if (rect.bottom >= vh - 4) {
							maxH = Math.max(maxH, Math.ceil(rect.height));
						}
					}
				});
				return maxH;
			}
			function lpnwSyncCookieOffset() {
				root.style.setProperty('--lpnw-cookie-offset', lpnwCookieBannerHeight() + 'px');
			}
			function lpnwRevealBar() {
				lpnwSyncCookieOffset();
				bar.classList.add('lpnw-sticky-cta-bar--visible');
				document.body.classList.add('lpnw-sticky-cta-visible');
			}
			var btn = bar.querySelector('.lpnw-sticky-cta-bar__dismiss');
			if (btn) {
				btn.addEventListener('click', function () {
					bar.classList.remove('lpnw-sticky-cta-bar--visible');
					document.body.classList.remove('lpnw-sticky-cta-visible');
					var maxAge = 180 * 24 * 60 * 60;
					document.cookie = 'lpnw_sticky_cta_dismiss=1; path=/; max-age=' + maxAge + '; SameSite=Lax';
					setTimeout(lpnwSyncCookieOffset, 350);
				});
			}
			window.addEventListener('resize', function () {
				if (document.body.classList.contains('lpnw-sticky-cta-visible')) {
					lpnwSyncCookieOffset();
				}
			});
			var mo = new MutationObserver(function () {
				if (document.body.classList.contains('lpnw-sticky-cta-visible')) {
					lpnwSyncCookieOffset();
				}
			});
			mo.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'style' ] });
			setTimeout(lpnwRevealBar, revealMs);
		})();
		</script>
		<?php
	},
	5
);

/**
 * CTA after single post content for guests.
 *
 * @param string $content Post content HTML.
 * @return string
 */
add_filter(
	'the_content',
	function ( string $content ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( is_user_logged_in() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return $content;
		}

		$area   = lpnw_theme_get_post_cta_area_label( $post );
		$signup = wp_registration_url();
		if ( '' === $signup ) {
			$signup = home_url( '/pricing/' );
		}

		$cta = sprintf(
			'<aside class="lpnw-post-signup-cta lpnw-cta-banner" role="complementary" aria-labelledby="lpnw-post-signup-cta-heading"><div class="lpnw-cta-banner__inner"><h3 class="lpnw-cta-banner__title" id="lpnw-post-signup-cta-heading">%s</h3><div class="lpnw-cta-banner__actions"><a class="lpnw-btn lpnw-btn--primary" href="%s">%s</a></div></div></aside>',
			sprintf(
				/* translators: %s: geographic area phrase (e.g. "the Northwest"). */
				esc_html__( 'Want to know about new properties in %s before anyone else? Set up free alerts now.', 'lpnw-theme' ),
				esc_html( $area )
			),
			esc_url( $signup ),
			esc_html__( 'Start free', 'lpnw-theme' )
		);

		return $content . $cta;
	},
	20
);

