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
 * Outputs hero photo markup; replaces legacy SVG placeholder in front-page content.
 */
class LPNW_Hero_Media {

	public const VERSION = '7';

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

		$has_scene = str_contains( $content, 'lpnw-hero__scene' );
		$has_svg   = str_contains( $content, 'lpnw-hero__illustration' );

		if ( $has_svg ) {
			$out = preg_replace(
				'/<svg\b[^>]*\bclass="[^"]*\blpnw-hero__illustration\b[^"]*"[^>]*>[\s\S]*?<\/svg>/i',
				$new,
				$content,
				1
			);
			if ( is_string( $out ) && $out !== $content ) {
				$unwrapped = preg_replace(
					'/<div\s+class="lpnw-hero__scene"(?:\s+aria-hidden="true")?>\s*(<div\s+class="lpnw-hero__parallax"[\s\S]*?<\/div>)\s*<\/div>/i',
					'$1',
					$out,
					1
				);
				self::$replaced_hero = true;

				return is_string( $unwrapped ) ? $unwrapped : $out;
			}
		}

		if ( $has_scene ) {
			$out = preg_replace(
				'/<div\s+class="lpnw-hero__scene"[^>]*>[\s\S]*?<\/div>/i',
				$new,
				$content,
				1
			);
			if ( is_string( $out ) && $out !== $content ) {
				self::$replaced_hero = true;
				return $out;
			}
		}

		if ( str_contains( $content, 'lpnw-hero__content' ) ) {
			$out = preg_replace(
				'/(<div\s+class="lpnw-hero__content")/i',
				$new . '$1',
				$content,
				1
			);
			if ( is_string( $out ) && $out !== $content ) {
				self::$replaced_hero = true;
				return $out;
			}
		}

		return $content;
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

		$v = esc_attr( self::VERSION );
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
