<?php
/**
 * Portal-specific alert context: badges (price drop vs new), listed timeline, price-change line.
 *
 * @package LPNW_Property_Alerts
 * @var object $prop Property row for the current email card.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $prop ) || ! is_object( $prop ) ) {
	return;
}

$lpnw_rec        = class_exists( 'LPNW_Property' ) ? LPNW_Property::get_card_listing_recency( $prop ) : array(
	'label'     => '',
	'is_new'    => false,
	'is_urgent' => false,
);
$lpnw_price_drop = class_exists( 'LPNW_Property' ) && LPNW_Property::is_recent_price_reduction( $prop );
$lpnw_show_new   = ! $lpnw_price_drop && ! empty( $lpnw_rec['is_new'] );
$lpnw_price_line = class_exists( 'LPNW_Property' ) ? LPNW_Property::format_price_change_summary_line( $prop ) : '';
?>

<?php if ( $lpnw_price_drop ) : ?>
<p style="margin:0 0 10px;">
	<span style="display:inline-block;padding:4px 10px;background:#C2410C;color:#FFFFFF;font-size:11px;font-weight:700;letter-spacing:0.06em;border-radius:4px;"><?php esc_html_e( 'PRICE DROP', 'lpnw-alerts' ); ?></span>
</p>
<?php elseif ( $lpnw_show_new ) : ?>
<p style="margin:0 0 10px;">
	<span style="display:inline-block;padding:4px 10px;background:#059669;color:#FFFFFF;font-size:11px;font-weight:700;letter-spacing:0.06em;border-radius:4px;"><?php esc_html_e( 'NEW', 'lpnw-alerts' ); ?></span>
</p>
<?php endif; ?>

<?php if ( '' !== ( $lpnw_rec['label'] ?? '' ) ) : ?>
<p style="margin:0 0 8px;font-size:13px;line-height:1.45;color:#6B7280;">
	<?php echo esc_html( (string) $lpnw_rec['label'] ); ?>
</p>
<?php endif; ?>

<?php if ( '' !== $lpnw_price_line ) : ?>
<p style="margin:0 0 10px;font-size:14px;line-height:1.5;color:#9A3412;font-weight:600;">
	<?php echo esc_html( $lpnw_price_line ); ?>
</p>
<?php endif; ?>
