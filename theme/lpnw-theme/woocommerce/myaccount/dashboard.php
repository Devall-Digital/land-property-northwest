<?php
/**
 * My Account dashboard (billing-focused copy for LPNW).
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

$orders_url         = esc_url( wc_get_endpoint_url( 'orders' ) );
$payment_methods_url = esc_url( wc_get_endpoint_url( 'payment-methods' ) );
$addresses_url      = esc_url( wc_get_endpoint_url( 'edit-address' ) );
$account_url        = esc_url( wc_get_endpoint_url( 'edit-account' ) );
?>
<p class="lpnw-wc-dashboard-intro">
	<?php
	echo wp_kses(
		sprintf(
			/* translators: 1: orders URL, 2: payment methods URL, 3: addresses URL, 4: account details URL */
			__( 'Use this page for billing and your WordPress login details: <a href="%1$s">orders</a>, <a href="%2$s">payment methods</a>, <a href="%3$s">addresses</a>, and <a href="%4$s">account details</a>. Your property alerts, map, and saved listings are under “Your alerts and listings” above.', 'lpnw-theme' ),
			$orders_url,
			$payment_methods_url,
			$addresses_url,
			$account_url
		),
		array(
			'a' => array(
				'href' => array(),
			),
		)
	);
	?>
</p>
<?php
/**
 * My Account dashboard hook.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_dashboard' );
