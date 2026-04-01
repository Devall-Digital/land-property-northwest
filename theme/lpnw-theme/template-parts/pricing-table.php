<?php
/**
 * Three-tier pricing: Free, Pro, VIP.
 *
 * Optional variables:
 * - string $section_id      ID for section (default lpnw-pricing).
 * - string $section_title   Visible heading (translatable default).
 * - string $free_cta_url, $pro_cta_url, $vip_cta_url Override checkout or pricing links.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

$section_id    = isset( $section_id ) ? sanitize_html_class( $section_id ) : 'lpnw-pricing';
$section_title = isset( $section_title ) ? $section_title : __( 'Simple pricing', 'lpnw-theme' );

$default_pricing = home_url( '/pricing/' );
$free_cta_url    = isset( $free_cta_url ) ? $free_cta_url : wp_registration_url();
$pro_cta_url     = isset( $pro_cta_url ) ? $pro_cta_url : $default_pricing;
$vip_cta_url     = isset( $vip_cta_url ) ? $vip_cta_url : $default_pricing;

$tiers = array(
	array(
		'name'     => __( 'Free', 'lpnw-theme' ),
		'price'    => __( '£0', 'lpnw-theme' ),
		'period'   => __( 'forever', 'lpnw-theme' ),
		'featured' => false,
		'features' => array(
			__( 'Weekly digest email', 'lpnw-theme' ),
			__( 'Sample of what we track', 'lpnw-theme' ),
			__( 'Upgrade any time', 'lpnw-theme' ),
		),
		'cta'      => __( 'Sign up free', 'lpnw-theme' ),
		'cta_url'  => $free_cta_url,
	),
	array(
		'name'     => __( 'Pro', 'lpnw-theme' ),
		'price'    => __( '£19.99', 'lpnw-theme' ),
		'period'   => __( 'per month', 'lpnw-theme' ),
		'featured' => true,
		'features' => array(
			__( 'Instant email alerts', 'lpnw-theme' ),
			__( 'Full filters: area, price, type, source', 'lpnw-theme' ),
			__( 'All data sources: planning, EPC, Land Registry, auctions', 'lpnw-theme' ),
		),
		'cta'      => __( 'Choose Pro', 'lpnw-theme' ),
		'cta_url'  => $pro_cta_url,
	),
	array(
		'name'     => __( 'VIP', 'lpnw-theme' ),
		'price'    => __( '£79.99', 'lpnw-theme' ),
		'period'   => __( 'per month', 'lpnw-theme' ),
		'featured' => false,
		'features' => array(
			__( 'Priority alerts (30 minutes before Pro)', 'lpnw-theme' ),
			__( 'Off-market and premium opportunities', 'lpnw-theme' ),
			__( 'Direct introductions where we can add them', 'lpnw-theme' ),
		),
		'cta'      => __( 'Choose VIP', 'lpnw-theme' ),
		'cta_url'  => $vip_cta_url,
	),
);

if ( isset( $tiers_override ) && is_array( $tiers_override ) ) {
	$tiers = $tiers_override;
}
?>
<section class="lpnw-pricing-section" id="<?php echo esc_attr( $section_id ); ?>" aria-labelledby="<?php echo esc_attr( $section_id ); ?>-title">
	<h2 id="<?php echo esc_attr( $section_id ); ?>-title" class="lpnw-pricing-section__title"><?php echo esc_html( $section_title ); ?></h2>
	<div class="lpnw-pricing">
		<?php foreach ( $tiers as $index => $tier ) : ?>
			<?php
			$card_classes = array( 'lpnw-pricing-card' );
			if ( ! empty( $tier['featured'] ) ) {
				$card_classes[] = 'lpnw-pricing-card--featured';
			}
			$tier_heading_id = $section_id . '-tier-' . (int) $index;
			?>
			<article class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" aria-labelledby="<?php echo esc_attr( $tier_heading_id ); ?>">
				<h3 id="<?php echo esc_attr( $tier_heading_id ); ?>" class="lpnw-pricing-card__name"><?php echo esc_html( $tier['name'] ); ?></h3>
				<p class="lpnw-pricing-card__price"><?php echo esc_html( $tier['price'] ); ?></p>
				<p class="lpnw-pricing-card__period"><?php echo esc_html( $tier['period'] ); ?></p>
				<ul class="lpnw-pricing-card__features" role="list">
					<?php foreach ( $tier['features'] as $feature ) : ?>
						<li><?php echo esc_html( $feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<a class="lpnw-btn <?php echo ! empty( $tier['featured'] ) ? 'lpnw-btn--primary' : 'lpnw-btn--secondary'; ?>" href="<?php echo esc_url( $tier['cta_url'] ); ?>"><?php echo esc_html( $tier['cta'] ); ?></a>
			</article>
		<?php endforeach; ?>
	</div>
</section>
