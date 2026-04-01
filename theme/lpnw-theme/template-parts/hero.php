<?php
/**
 * Hero section: navy gradient, heading, subheading, primary CTA.
 *
 * Variables (via lpnw_get_template_part or set before include):
 * - string $heading   Main H1 text.
 * - string $subheading Supporting paragraph.
 * - string $cta_text  CTA label.
 * - string $cta_url   CTA href.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

$heading    = isset( $heading ) ? $heading : __( 'Northwest property alerts before the crowd', 'lpnw-theme' );
$subheading = isset( $subheading ) ? $subheading : __( 'Planning, auctions, EPC signals, and Land Registry activity in one feed. Set your criteria and get there first.', 'lpnw-theme' );
$cta_text   = isset( $cta_text ) ? $cta_text : __( 'Get started', 'lpnw-theme' );
$cta_url    = isset( $cta_url ) ? $cta_url : wp_registration_url();
?>
<section class="lpnw-hero" aria-labelledby="lpnw-hero-heading">
	<h1 id="lpnw-hero-heading"><?php echo esc_html( $heading ); ?></h1>
	<p><?php echo esc_html( $subheading ); ?></p>
	<a class="lpnw-btn lpnw-btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
</section>
