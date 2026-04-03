<?php
/**
 * Hero: layered cityscape, clouds, particles, CTA (premium motion respects reduced-motion).
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
$cta_url     = isset( $cta_url ) ? $cta_url : wp_registration_url();
$pricing_url = isset( $pricing_url ) ? $pricing_url : home_url( '/pricing/' );

$primary_url   = $cta_url;
$primary_label = $cta_text;
$ghost_url     = $pricing_url;
$ghost_label   = __( 'See pricing', 'lpnw-theme' );
if ( is_user_logged_in() ) {
	$primary_url   = home_url( '/dashboard/' );
	$primary_label = __( 'Open dashboard', 'lpnw-theme' );
	$ghost_url     = home_url( '/preferences/' );
	$ghost_label   = __( 'Alert preferences', 'lpnw-theme' );
}
?>
<section class="lpnw-hero" aria-labelledby="lpnw-hero-heading">
	<div class="lpnw-hero__bg" aria-hidden="true">
		<div class="lpnw-hero__sky"></div>
		<div class="lpnw-hero__cloud lpnw-hero__cloud--a"></div>
		<div class="lpnw-hero__cloud lpnw-hero__cloud--b"></div>
		<div class="lpnw-hero__cloud lpnw-hero__cloud--c"></div>
		<div class="lpnw-hero__cityscape">
			<span class="lpnw-hero__shape lpnw-hero__shape--1"></span>
			<span class="lpnw-hero__shape lpnw-hero__shape--2"></span>
			<span class="lpnw-hero__shape lpnw-hero__shape--3"></span>
			<span class="lpnw-hero__shape lpnw-hero__shape--4"></span>
			<span class="lpnw-hero__shape lpnw-hero__shape--5"></span>
			<span class="lpnw-hero__shape lpnw-hero__shape--6"></span>
			<span class="lpnw-hero__shape lpnw-hero__shape--7"></span>
			<span class="lpnw-hero__shape lpnw-hero__shape--8"></span>
		</div>
		<div class="lpnw-hero__orb"></div>
		<div class="lpnw-hero__particles"></div>
		<div class="lpnw-hero__vignette"></div>
	</div>
	<div class="lpnw-hero__content">
		<h1 id="lpnw-hero-heading" class="lpnw-hero__title"><?php echo esc_html( $heading ); ?></h1>
		<p class="lpnw-hero__subtitle"><?php echo esc_html( $subheading ); ?></p>
		<div class="lpnw-hero__actions">
			<a class="lpnw-btn lpnw-btn--primary" href="<?php echo esc_url( $primary_url ); ?>"><?php echo esc_html( $primary_label ); ?></a>
			<a class="lpnw-btn lpnw-btn--ghost" href="<?php echo esc_url( $ghost_url ); ?>"><?php echo esc_html( $ghost_label ); ?></a>
		</div>
	</div>
</section>
