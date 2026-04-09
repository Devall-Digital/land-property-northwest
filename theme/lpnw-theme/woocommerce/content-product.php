<?php
/**
 * Shop loop item: text-first card (pricing-style), no product image.
 *
 * @package LPNW_Theme
 * @see https://woocommerce.com/document/template-structure/
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) ) {
	return;
}
?>
<li <?php wc_product_class( '', $product ); ?>>
	<?php woocommerce_show_product_loop_sale_flash(); ?>
	<div class="lpnw-wc-shop-card">
		<a class="lpnw-wc-shop-card__link" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<h2 class="woocommerce-loop-product__title lpnw-wc-shop-card__title"><?php echo esc_html( $product->get_name() ); ?></h2>
			<?php
			$period_label = LPNW_WooCommerce_Shop_Loop::get_period_label( $product );
			if ( '' !== $period_label ) {
				echo '<p class="lpnw-wc-shop-card__period">' . esc_html( $period_label ) . '</p>';
			}
			LPNW_WooCommerce_Shop_Loop::render_loop_features( $product );
			?>
			<div class="lpnw-wc-shop-card__price-wrap">
				<?php woocommerce_template_loop_price(); ?>
			</div>
		</a>
		<div class="lpnw-wc-shop-card__actions">
			<?php woocommerce_template_loop_add_to_cart(); ?>
		</div>
	</div>
</li>
