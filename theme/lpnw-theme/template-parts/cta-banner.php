<?php
/**
 * Full-width CTA banner for upgrades and conversions.
 *
 * Variables:
 * - string $heading    Banner title (H2).
 * - string $subheading Supporting text.
 * - string $cta_text   Button label.
 * - string $cta_url   Button href.
 * - string $banner_heading_id Optional stable ID for aria-labelledby (default: unique per render).
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

$heading    = isset( $heading ) ? $heading : __( 'Ready for instant alerts?', 'lpnw-theme' );
$subheading = isset( $subheading ) ? $subheading : __( 'Upgrade to Pro for same-day notifications on every source we track.', 'lpnw-theme' );
$cta_text   = isset( $cta_text ) ? $cta_text : __( 'View plans', 'lpnw-theme' );
$cta_url    = isset( $cta_url ) ? $cta_url : home_url( '/pricing/' );

$banner_heading_id = isset( $banner_heading_id ) ? sanitize_html_class( $banner_heading_id ) : wp_unique_id( 'lpnw-cta-banner-h-' );
?>
<aside class="lpnw-cta-banner" aria-labelledby="<?php echo esc_attr( $banner_heading_id ); ?>">
	<h2 id="<?php echo esc_attr( $banner_heading_id ); ?>"><?php echo esc_html( $heading ); ?></h2>
	<p><?php echo esc_html( $subheading ); ?></p>
	<a class="lpnw-btn lpnw-btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
</aside>
