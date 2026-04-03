<?php
/**
 * Open Graph and Twitter Card meta for public pages.
 *
 * When Rank Math outputs Open Graph (module on), image URL is supplied via its filters so tags are not duplicated.
 * If Rank Math is active but OG is off, or no SEO plugin handles social meta, this class prints a full fallback on wp_head.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Social sharing meta (Open Graph, Twitter).
 */
final class LPNW_Open_Graph {

	/**
	 * Register hooks.
	 */
	public static function bootstrap(): void {
		add_filter( 'rank_math/opengraph/facebook/image', array( __CLASS__, 'filter_rank_math_image' ), 20, 1 );
		add_filter( 'rank_math/opengraph/twitter/image', array( __CLASS__, 'filter_rank_math_image' ), 20, 1 );
		add_action( 'wp_head', array( __CLASS__, 'maybe_output_fallback_head_tags' ), 1 );
	}

	/**
	 * Adjust og/twitter image URL when Rank Math has none (or on the home page use the branded hero asset).
	 *
	 * @param string $attachment_url URL Rank Math intends to use.
	 * @return string
	 */
	public static function filter_rank_math_image( $attachment_url ): string {
		if ( ! self::is_public_document() ) {
			return (string) $attachment_url;
		}

		$candidate = is_string( $attachment_url ) ? trim( $attachment_url ) : '';
		if ( '' !== $candidate && ! self::is_acceptable_og_image_url( $candidate ) ) {
			$candidate = '';
		}

		$details = self::build_image_details( $candidate );
		return $details['url'];
	}

	/**
	 * Social crawlers often reject SVG; Rank Math may still pass a site logo URL.
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	private static function is_acceptable_og_image_url( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$lower = strtolower( $url );
		if ( str_contains( $lower, '.svg' ) ) {
			return false;
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) || ! in_array( strtolower( (string) $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Print OG/Twitter tags when Rank Math is not handling them.
	 */
	public static function maybe_output_fallback_head_tags(): void {
		if ( ! self::is_public_document() ) {
			return;
		}

		if ( self::seo_plugin_outputs_social_meta() ) {
			return;
		}

		$title       = self::get_og_title();
		$description = self::get_og_description();
		$url         = self::get_canonical_url();
		$image       = self::get_image_details_for_fallback();

		$og_type = is_front_page() ? 'website' : 'article';

		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '"/>' . "\n";
		echo '<meta property="og:locale" content="en_GB"/>' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '"/>' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '"/>' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '"/>' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '"/>' . "\n";
		echo '<meta property="og:image" content="' . esc_url( $image['url'] ) . '"/>' . "\n";

		if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) && (int) $image['width'] > 0 && (int) $image['height'] > 0 ) {
			echo '<meta property="og:image:width" content="' . esc_attr( (string) (int) $image['width'] ) . '"/>' . "\n";
			echo '<meta property="og:image:height" content="' . esc_attr( (string) (int) $image['height'] ) . '"/>' . "\n";
		}

		if ( ! empty( $image['alt'] ) ) {
			echo '<meta property="og:image:alt" content="' . esc_attr( $image['alt'] ) . '"/>' . "\n";
		}

		if ( is_ssl() && 0 === strpos( $image['url'], 'https://' ) ) {
			echo '<meta property="og:image:secure_url" content="' . esc_url( $image['url'] ) . '"/>' . "\n";
		}

		echo '<meta name="twitter:card" content="summary_large_image"/>' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '"/>' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '"/>' . "\n";
		echo '<meta name="twitter:image" content="' . esc_url( $image['url'] ) . '"/>' . "\n";

		if ( ! empty( $image['alt'] ) ) {
			echo '<meta name="twitter:image:alt" content="' . esc_attr( $image['alt'] ) . '"/>' . "\n";
		}
	}

	/**
	 * Whether an SEO plugin will print Open Graph / Twitter tags (avoid duplicates).
	 *
	 * Rank Math can be active with the Open Graph module off; in that case we must still
	 * output our fallback. Detection uses the hooks Rank Math registers when OG is on.
	 *
	 * @return bool
	 */
	private static function seo_plugin_outputs_social_meta(): bool {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return (bool) ( has_action( 'rank_math/opengraph/facebook' ) || has_action( 'rank_math/opengraph/twitter' ) );
		}

		if ( defined( 'WPSEO_VERSION' ) ) {
			// Yoast hooks these when social meta is enabled (approximation; avoids skipping when social is off).
			return (bool) ( has_action( 'wpseo_opengraph' ) || has_action( 'wpseo_twitter' ) );
		}

		return false;
	}

	/**
	 * Front-end HTML document suitable for sharing meta.
	 *
	 * @return bool
	 */
	private static function is_public_document(): bool {
		if ( is_admin() || wp_is_json_request() || is_feed() || is_embed() ) {
			return false;
		}

		return true;
	}

	/**
	 * Canonical URL for og:url.
	 *
	 * @return string
	 */
	private static function get_canonical_url(): string {
		if ( function_exists( 'wp_get_canonical_url' ) ) {
			$canonical = wp_get_canonical_url();
			if ( is_string( $canonical ) && $canonical !== '' ) {
				return $canonical;
			}
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_singular() ) {
			$permalink = get_permalink();
			return is_string( $permalink ) && $permalink !== '' ? $permalink : home_url( '/' );
		}

		global $wp;
		if ( isset( $wp->request ) && is_string( $wp->request ) && $wp->request !== '' ) {
			return home_url( $wp->request );
		}

		return home_url( '/' );
	}

	/**
	 * Sharing title.
	 *
	 * @return string
	 */
	private static function get_og_title(): string {
		$site_name = get_bloginfo( 'name' );

		if ( is_front_page() ) {
			return 'Get NW Property Alerts Before Anyone Else | ' . $site_name;
		}

		if ( is_page( 'pricing' ) ) {
			return 'Pricing | ' . $site_name;
		}

		if ( is_page( 'properties' ) || is_page( 'browse-properties' ) ) {
			return 'Browse Northwest Properties | ' . $site_name;
		}

		if ( is_singular() ) {
			return get_the_title() . ' | ' . $site_name;
		}

		if ( is_archive() ) {
			return wp_get_document_title();
		}

		return $site_name . ' | NW Property Intelligence';
	}

	/**
	 * Sharing description.
	 *
	 * @return string
	 */
	private static function get_og_description(): string {
		$default = 'NW property alerts before anyone else. Instant notifications from Rightmove and more, straight to your inbox.';

		if ( is_page( 'pricing' ) ) {
			return 'Free weekly digest or instant Pro alerts from £19.99/month. Priority access for Investor VIP.';
		}

		if ( is_page( 'properties' ) || is_page( 'browse-properties' ) ) {
			return 'Search thousands of live property listings across Greater Manchester, Merseyside, Lancashire, Cheshire, and Cumbria.';
		}

		// Front page is often a Page; avoid auto-excerpt (credits, "Read more", etc.) in share previews.
		if ( is_front_page() ) {
			return $default;
		}

		if ( is_singular() ) {
			$excerpt = get_the_excerpt();
			if ( is_string( $excerpt ) && $excerpt !== '' ) {
				return wp_strip_all_tags( $excerpt );
			}
		}

		return $default;
	}

	/**
	 * Image meta for fallback head output.
	 *
	 * @return array{url:string,width?:int,height?:int,alt?:string}
	 */
	private static function get_image_details_for_fallback(): array {
		return self::build_image_details( '' );
	}

	/**
	 * Pick image URL and optional dimensions/alt.
	 *
	 * @param string $rank_math_url When non-empty, Rank Math already chose an image.
	 * @return array{url:string,width?:int,height?:int,alt?:string}
	 */
	private static function build_image_details( string $rank_math_url ): array {
		if ( is_front_page() ) {
			return self::get_branded_static_image( 'home' );
		}

		// Key marketing pages: always use the branded message card, not a hero or inline photo.
		if ( is_page( array( 'pricing', 'properties', 'browse-properties', 'about', 'contact' ) ) ) {
			return self::get_branded_static_image( 'default' );
		}

		if ( $rank_math_url !== '' && self::is_acceptable_og_image_url( $rank_math_url ) ) {
			return array(
				'url' => $rank_math_url,
			);
		}

		$featured = self::get_featured_image_details();
		if ( is_array( $featured ) ) {
			return $featured;
		}

		return self::get_branded_static_image( 'default' );
	}

	/**
	 * Branded 1200x630 PNGs in assets/img.
	 *
	 * @param string $which 'home' or 'default'.
	 * @return array{url:string,width:int,height:int,alt:string}
	 */
	private static function get_branded_static_image( string $which ): array {
		if ( self::can_use_dynamic_share_card() ) {
			$alt = ( 'home' === $which )
				/* translators: %s: site name. */
				? sprintf( __( '%s — instant property alerts for Northwest England (listings, planning, auctions, and more)', 'lpnw-theme' ), get_bloginfo( 'name' ) )
				/* translators: %s: site name. */
				: sprintf( __( '%s — Northwest property intelligence and alerts', 'lpnw-theme' ), get_bloginfo( 'name' ) );

			return array(
				'url'    => LPNW_OG_Card::get_image_url( $which ),
				'width'  => LPNW_OG_Card::WIDTH,
				'height' => LPNW_OG_Card::HEIGHT,
				'alt'    => $alt,
			);
		}

		$file     = ( 'home' === $which ) ? 'og-home.png' : 'og-default.png';
		$path     = get_stylesheet_directory() . '/assets/img/' . $file;
		$base_uri = get_stylesheet_directory_uri() . '/assets/img/' . $file;

		// Legacy JPEG path (older deploys) if PNG is missing.
		if ( ! is_readable( $path ) ) {
			$jpg     = get_stylesheet_directory() . '/assets/img/og/og-default.jpg';
			$jpg_uri = get_stylesheet_directory_uri() . '/assets/img/og/og-default.jpg';
			if ( is_readable( $jpg ) ) {
				$ver = (string) filemtime( $jpg );
				$url = add_query_arg( 'v', $ver, $jpg_uri );

				return array(
					'url'    => $url,
					'width'  => 1920,
					'height' => 1080,
					'alt'    => get_bloginfo( 'name' ),
				);
			}
		}

		$ver = is_readable( $path ) ? (string) filemtime( $path ) : '';
		$url = $ver !== '' ? add_query_arg( 'v', $ver, $base_uri ) : $base_uri;

		return array(
			'url'    => $url,
			'width'  => 1200,
			'height' => 630,
			'alt'    => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Dynamic PNG card (headline + value prop) when GD + bundled TTF are available.
	 *
	 * @return bool
	 */
	private static function can_use_dynamic_share_card(): bool {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagettftext' ) ) {
			return false;
		}

		$font = get_stylesheet_directory() . '/assets/fonts/DejaVuSans-Bold.ttf';

		return is_readable( $font );
	}

	/**
	 * Featured image for singular content when no SEO plugin image is set.
	 *
	 * @return array{url:string,width:int,height:int,alt:string}|null
	 */
	private static function get_featured_image_details(): ?array {
		if ( ! is_singular() || ! has_post_thumbnail() ) {
			return null;
		}

		$attachment_id = get_post_thumbnail_id();
		if ( ! $attachment_id ) {
			return null;
		}

		$src = wp_get_attachment_image_src( (int) $attachment_id, 'full' );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			return null;
		}

		$alt = get_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! is_string( $alt ) || $alt === '' ) {
			$alt = get_the_title();
		}

		return array(
			'url'    => $src[0],
			'width'  => isset( $src[1] ) ? (int) $src[1] : 0,
			'height' => isset( $src[2] ) ? (int) $src[2] : 0,
			'alt'    => $alt,
		);
	}
}
