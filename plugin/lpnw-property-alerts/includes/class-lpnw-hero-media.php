<?php
/**
 * Front-page hero: rotating Northwest England city photos (Unsplash).
 *
 * Photos are hotlinked from images.unsplash.com under the Unsplash License.
 * Credits: see LPNW_Hero_Media::get_photo_attributions() (shown in screen-reader text).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Outputs hero photo markup; injects into front-page content and strips legacy SVG/CSS layers.
 */
class LPNW_Hero_Media {

	public const VERSION = '8';

	/**
	 * @var bool
	 */
	private static $replaced_hero = false;

	public static function init(): void {
		add_action( 'wp', array( __CLASS__, 'register_front_page_content_filter' ) );
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_front_page_hero_ctas' ), 15 );
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function filter_body_class( array $classes ): array {
		if ( is_front_page() ) {
			$classes[] = 'lpnw-home-photo-hero';
		}

		if ( is_front_page() && is_user_logged_in() ) {
			$classes[] = 'lpnw-hero--logged-in';
		}

		return $classes;
	}

	/**
	 * Swap hero CTAs for logged-in visitors (post content only).
	 *
	 * @param string $content Post content.
	 */
	public static function filter_front_page_hero_ctas( string $content ): string {
		if ( ! is_front_page() || is_feed() || ! is_user_logged_in() ) {
			return $content;
		}

		if ( ! str_contains( $content, 'lpnw-hero__actions' ) ) {
			return $content;
		}

		$dash  = esc_url( home_url( '/dashboard/' ) );
		$prefs = esc_url( home_url( '/preferences/' ) );

		$block = '<div class="lpnw-hero__actions">'
			. '<a class="lpnw-btn lpnw-btn--primary" href="' . $dash . '">' . esc_html__( 'Open dashboard', 'lpnw-alerts' ) . '</a>'
			. '<a class="lpnw-btn lpnw-btn--ghost" href="' . $prefs . '">' . esc_html__( 'Alert preferences', 'lpnw-alerts' ) . '</a>'
			. '</div>';

		$out = preg_replace(
			'/<div\s+class="lpnw-hero__actions"[^>]*>[\s\S]*?<\/div>/i',
			$block,
			$content,
			1
		);

		return is_string( $out ) ? $out : $content;
	}

	/**
	 * Front-page static pages may not run `the_content` inside a query loop; register once per request.
	 */
	public static function register_front_page_content_filter(): void {
		if ( is_admin() || ! is_front_page() || is_feed() ) {
			return;
		}
		add_filter( 'the_content', array( __CLASS__, 'filter_replace_illustration' ), 8 );
	}

	public static function filter_replace_illustration( string $content ): string {
		if ( self::$replaced_hero || ! is_front_page() || is_feed() ) {
			return $content;
		}

		$new = self::get_illustration_markup();
		if ( '' === $new ) {
			return $content;
		}

		$work = self::strip_legacy_hero_visuals( $content );

		if ( str_contains( $work, 'lpnw-hero__content' ) && ! str_contains( $work, 'lpnw-hero__photos' ) ) {
			$out = preg_replace(
				'/(<div\s+class="lpnw-hero__content")/i',
				$new . '$1',
				$work,
				1
			);
			if ( is_string( $out ) && $out !== $work ) {
				self::$replaced_hero = true;

				return self::ensure_photo_hero_section_class( $out );
			}
		}

		return $content;
	}

	/**
	 * Remove legacy layered hero (CSS shapes, SVG scene, parallax stack) from stored HTML.
	 *
	 * @param string $content Full post content.
	 * @return string
	 */
	private static function strip_legacy_hero_visuals( string $content ): string {
		$work = $content;
		$work = self::remove_first_div_with_class_token( $work, 'lpnw-hero__scene' );
		$work = self::remove_first_div_with_class_token( $work, 'lpnw-hero__parallax' );
		$work = self::remove_first_div_with_class_token( $work, 'lpnw-hero__bg' );

		return $work;
	}

	/**
	 * Ensure the first .lpnw-hero section includes layout modifier for photo-only heroes.
	 *
	 * @param string $content HTML.
	 * @return string
	 */
	private static function ensure_photo_hero_section_class( string $content ): string {
		if ( str_contains( $content, 'lpnw-hero--photos' ) ) {
			return $content;
		}

		$out = preg_replace(
			'/<section\s+class="([^"]*\blpnw-hero\b)([^"]*)"/i',
			'<section class="$1 lpnw-hero--photos$2"',
			$content,
			1
		);

		return is_string( $out ) ? $out : $content;
	}

	/**
	 * Remove the first opening <div> whose class attribute contains a token, through its closing </div> (nested-aware).
	 *
	 * @param string $html   HTML fragment.
	 * @param string $token  Class substring (e.g. lpnw-hero__bg).
	 * @return string
	 */
	private static function remove_first_div_with_class_token( string $html, string $token ): string {
		$needle = 'class="';
		$pos    = 0;
		$len    = strlen( $html );

		while ( $pos < $len ) {
			$class_pos = stripos( $html, $needle, $pos );
			if ( false === $class_pos ) {
				break;
			}
			$attr_start = strrpos( substr( $html, 0, $class_pos ), '<div' );
			if ( false === $attr_start ) {
				$pos = $class_pos + strlen( $needle );
				continue;
			}

			$quote_end = strpos( $html, '"', $class_pos + strlen( $needle ) );
			if ( false === $quote_end ) {
				break;
			}
			$class_val = substr( $html, $class_pos + strlen( $needle ), $quote_end - $class_pos - strlen( $needle ) );
			if ( false === stripos( $class_val, $token ) ) {
				$pos = $quote_end + 1;
				continue;
			}

			$open_tag_end = strpos( $html, '>', $attr_start );
			if ( false === $open_tag_end ) {
				break;
			}
			$start = $attr_start;
			$i     = $open_tag_end + 1;
			$depth = 1;

			while ( $i < $len && $depth > 0 ) {
				$next_open  = stripos( $html, '<div', $i );
				$next_close = stripos( $html, '</div>', $i );
				if ( false === $next_close ) {
					return $html;
				}
				if ( false !== $next_open && $next_open < $next_close ) {
					++$depth;
					$i = $next_open + 4;
					continue;
				}
				--$depth;
				$i = $next_close + strlen( '</div>' );
			}

			return substr( $html, 0, $start ) . substr( $html, $i );
		}

		return $html;
	}

	/**
	 * Unsplash CDN URLs (Manchester / Liverpool, UK). w=1920 for hero sharpness.
	 *
	 * @return array<int, array{url: string, label: string}>
	 */
	private static function get_slides(): array {
		return array(
			array(
				'url'   => 'https://images.unsplash.com/photo-1668878189176-0ac5966ac969?auto=format&fit=crop&w=1920&q=82',
				'label' => __( 'Manchester city street with Beetham Tower at sunrise', 'lpnw-alerts' ),
			),
			array(
				'url'   => 'https://images.unsplash.com/photo-1692968678752-3f24021a188e?auto=format&fit=crop&w=1920&q=82',
				'label' => __( 'Manchester city skyline', 'lpnw-alerts' ),
			),
			array(
				'url'   => 'https://images.unsplash.com/photo-1761902695169-a256b2d7dfe7?auto=format&fit=crop&w=1920&q=82',
				'label' => __( 'Royal Liver Building, Liverpool waterfront', 'lpnw-alerts' ),
			),
			array(
				'url'   => 'https://images.unsplash.com/photo-1772967306085-30fe19d87f60?auto=format&fit=crop&w=1920&q=82',
				'label' => __( 'Liverpool skyline and historic buildings by the water', 'lpnw-alerts' ),
			),
		);
	}

	/**
	 * Plain-text attribution for accessibility (Unsplash License).
	 */
	private static function get_photo_attributions(): string {
		return __( 'Hero photographs via Unsplash (free licence): Jonny Gios (Manchester street); Courtney Cantu (Manchester skyline); Luke Robinson (Liver Building); Daniel Sturley (Liverpool waterfront).', 'lpnw-alerts' );
	}

	public static function get_illustration_markup(): string {
		$slides = self::get_slides();
		if ( array() === $slides ) {
			return '';
		}

		$v    = esc_attr( self::VERSION );
		$html = '<div class="lpnw-hero__photos" data-lpnw-hero-photos="' . $v . '" aria-hidden="true">';

		foreach ( $slides as $i => $slide ) {
			$url   = esc_url( $slide['url'] );
			$class = 0 === $i ? 'lpnw-hero__photo is-active' : 'lpnw-hero__photo';
			$html .= sprintf(
				'<div class="%s" style="background-image:url(&quot;%s&quot;);"></div>',
				esc_attr( $class ),
				$url
			);
		}

		$html .= '<p class="screen-reader-text">' . esc_html( self::get_photo_attributions() ) . '</p>';
		$html .= '</div>';

		return $html;
	}
}
