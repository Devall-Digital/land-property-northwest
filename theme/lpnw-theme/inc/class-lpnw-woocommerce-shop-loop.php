<?php
/**
 * Shop archive loop: text-first cards aligned with pricing page copy.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Feature bullets and billing period hints for product loop cards.
 */
final class LPNW_WooCommerce_Shop_Loop {

	/**
	 * Allowed HTML in product short description when rendered as rich text.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function short_description_allowed_tags(): array {
		return array(
			'ul' => array( 'class' => true ),
			'ol' => array( 'class' => true ),
			'li' => array(),
			'p'  => array(),
			'br' => array(),
		);
	}

	/**
	 * Billing cadence under the price (subscription products).
	 *
	 * @param WC_Product $product Product in the loop.
	 */
	public static function get_period_label( WC_Product $product ): string {
		if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
			return __( 'per month', 'lpnw-theme' );
		}
		return '';
	}

	/**
	 * Default bullet lines when short description is empty (mirrors pricing page tiers).
	 *
	 * @param WC_Product $product Product in the loop.
	 * @return array<int, string>
	 */
	private static function get_fallback_feature_strings( WC_Product $product ): array {
		$slug = $product->get_slug();
		if ( 'lpnw-pro' === $slug ) {
			return array(
				__( 'Instant email alerts when properties match your criteria (or daily, your choice)', 'lpnw-theme' ),
				__( 'Filter by area, bedrooms, price, property type, tenure, and features', 'lpnw-theme' ),
				__( 'Dashboard for preferences and saved properties, including the property map', 'lpnw-theme' ),
			);
		}
		if ( 'lpnw-vip' === $slug ) {
			return array(
				__( 'Everything in Pro, plus priority processing', 'lpnw-theme' ),
				__( 'Your alerts are queued 30 minutes ahead of Pro subscribers', 'lpnw-theme' ),
				__( 'First to know means first to act on competitive listings', 'lpnw-theme' ),
				__( 'Off-market property alerts (when available)', 'lpnw-theme' ),
			);
		}
		return array(
			__( 'Full subscriber dashboard and email alerts for Northwest England.', 'lpnw-theme' ),
			__( 'Secure checkout with Stripe. Cancel any time from your account.', 'lpnw-theme' ),
		);
	}

	/**
	 * Echo feature list for the shop card (short description, newline text, or slug fallback).
	 *
	 * @param WC_Product $product Product in the loop.
	 */
	public static function render_loop_features( WC_Product $product ): void {
		$raw = $product->get_short_description();
		$raw = is_string( $raw ) ? trim( $raw ) : '';

		if ( '' === $raw ) {
			self::render_feature_ul( self::get_fallback_feature_strings( $product ) );
			return;
		}

		if ( false !== stripos( $raw, '<ul' ) || false !== stripos( $raw, '<ol' ) || false !== stripos( $raw, '<li' ) ) {
			echo '<div class="lpnw-wc-shop-card__features lpnw-wc-shop-card__features--rich">';
			echo wp_kses( $raw, self::short_description_allowed_tags() );
			echo '</div>';
			return;
		}

		$plain = wp_strip_all_tags( $raw );
		$split = preg_split( '/\r\n|\r|\n/', $plain );
		$split = is_array( $split ) ? $split : array();
		$lines = array_filter( array_map( 'trim', $split ) );

		if ( count( $lines ) >= 2 ) {
			self::render_feature_ul( $lines );
			return;
		}

		if ( count( $lines ) === 1 ) {
			echo '<p class="lpnw-wc-shop-card__summary">' . esc_html( $lines[0] ) . '</p>';
			return;
		}

		self::render_feature_ul( self::get_fallback_feature_strings( $product ) );
	}

	/**
	 * Output a simple unordered feature list.
	 *
	 * @param array<int, string> $items Feature lines.
	 */
	private static function render_feature_ul( array $items ): void {
		if ( array() === $items ) {
			return;
		}
		echo '<ul class="lpnw-wc-shop-card__features" role="list">';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}
}
