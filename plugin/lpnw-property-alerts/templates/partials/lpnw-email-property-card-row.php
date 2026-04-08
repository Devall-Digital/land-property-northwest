<?php
/**
 * One property card as outer table rows (email-safe). Included by LPNW_Email_Property_Card only.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $lpnw_prop ) || ! is_object( $lpnw_prop ) ) {
	return;
}

$prop              = $lpnw_prop;
$lpnw_include_desc = ! empty( $lpnw_include_description );
$lpnw_compact_card = ! empty( $lpnw_compact );
$lpnw_inner_pad    = $lpnw_compact_card ? '16px 18px 18px' : '18px 18px 20px';
$lpnw_h3_size      = $lpnw_compact_card ? '16px' : '17px';
$lpnw_rooms_margin = $lpnw_compact_card ? '0 0 14px' : '0 0 10px';
$lpnw_meta_margin  = $lpnw_compact_card ? '0 0 12px' : '0 0 10px';

$raw = json_decode( (string) ( $prop->raw_data ?? '' ), true );
$img = '';
if ( is_array( $raw ) ) {
	$pi  = isset( $raw['propertyImages'] ) && is_array( $raw['propertyImages'] ) ? $raw['propertyImages'] : array();
	$img = isset( $pi['mainImageSrc'] ) ? (string) $pi['mainImageSrc'] : '';
	if ( '' === $img && isset( $pi['images'][0]['srcUrl'] ) ) {
		$img = (string) $pi['images'][0]['srcUrl'];
	}
	if ( '' === $img && isset( $raw['images'][0]['srcUrl'] ) ) {
		$img = (string) $raw['images'][0]['srcUrl'];
	}
	if ( '' === $img && isset( $raw['images'][0]['url'] ) ) {
		$img = (string) $raw['images'][0]['url'];
	}
	if ( '' === $img && isset( $raw['image'] ) && is_scalar( $raw['image'] ) ) {
		$img = (string) $raw['image'];
	}
	if ( '' === $img && isset( $raw['imageUrl'] ) && is_scalar( $raw['imageUrl'] ) ) {
		$img = (string) $raw['imageUrl'];
	}
}

$beds_raw  = isset( $prop->bedrooms ) ? trim( (string) $prop->bedrooms ) : '';
$baths_raw = isset( $prop->bathrooms ) ? trim( (string) $prop->bathrooms ) : '';
$beds      = ( '' !== $beds_raw ) ? (int) $beds_raw : null;
$baths     = ( '' !== $baths_raw ) ? (int) $baths_raw : null;

$tenure_badge_label = '';
$tenure_raw         = trim( (string) ( $prop->tenure_type ?? '' ) );
if ( '' !== $tenure_raw ) {
	$tlow = strtolower( $tenure_raw );
	if ( false !== strpos( $tlow, 'leasehold' ) ) {
		$tenure_badge_label = __( 'Leasehold', 'lpnw-alerts' );
	} elseif ( false !== strpos( $tlow, 'freehold' ) ) {
		$tenure_badge_label = __( 'Freehold', 'lpnw-alerts' );
	} else {
		$tenure_badge_label = ucwords( str_replace( '_', ' ', $tenure_raw ) );
	}
}

$lpnw_channel_label = class_exists( 'LPNW_Property' ) ? LPNW_Property::get_listing_channel_label( $prop ) : '';
?>
<tr>
	<td style="padding:8px 32px;">
		<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;">
			<?php if ( '' !== $img ) : ?>
			<tr>
				<td style="padding:0;background:#E5E7EB;">
					<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $prop->address ); ?>" width="536" style="width:100%;max-width:536px;height:auto;display:block;border-radius:8px 8px 0 0;" loading="lazy">
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td style="padding:<?php echo esc_attr( $lpnw_inner_pad ); ?>;">
					<?php
					$lpnw_portal_ctx = LPNW_PLUGIN_DIR . 'templates/email-portal-context.php';
					if ( file_exists( $lpnw_portal_ctx ) ) {
						include $lpnw_portal_ctx;
					}
					?>
					<h3 style="margin:0 0 6px;font-size:<?php echo esc_attr( $lpnw_h3_size ); ?>;line-height:1.3;color:#1B2A4A;font-weight:700;">
						<?php echo esc_html( $prop->address ); ?>
					</h3>
					<p style="margin:<?php echo esc_attr( $lpnw_meta_margin ); ?>;font-size:14px;line-height:1.5;color:#6B7280;">
						<?php echo esc_html( $prop->postcode ); ?>
						<?php
						if ( class_exists( 'LPNW_Property' ) ) {
							$lpnw_cap = LPNW_Property::format_postcode_caption( $prop );
							if ( '' !== $lpnw_cap ) {
								echo ' — ' . esc_html( $lpnw_cap );
							}
						}
						?>
						<?php if ( $prop->price ) : ?>
							&bull; &pound;<?php echo esc_html( number_format( (int) $prop->price ) ); ?>
							<?php if ( 'rent' === strtolower( trim( (string) ( $prop->application_type ?? '' ) ) ) ) : ?>
								<?php esc_html_e( 'pcm', 'lpnw-alerts' ); ?>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( '' !== $lpnw_channel_label ) : ?>
							&bull; <?php echo esc_html( $lpnw_channel_label ); ?>
						<?php endif; ?>
						&bull; <?php echo esc_html( ucfirst( str_replace( '_', ' ', $prop->source ) ) ); ?>
						<?php if ( '' !== $tenure_badge_label ) : ?>
							&bull; <?php echo esc_html( $tenure_badge_label ); ?>
						<?php endif; ?>
					</p>
					<?php if ( null !== $beds || null !== $baths ) : ?>
					<p style="margin:<?php echo esc_attr( $lpnw_rooms_margin ); ?>;font-size:14px;line-height:1.5;color:#374151;font-weight:500;">
						<?php
						$room_parts = array();
						if ( null !== $beds ) {
							$room_parts[] = sprintf(
								/* translators: %d: bedroom count */
								_n( '%d bed', '%d beds', $beds, 'lpnw-alerts' ),
								$beds
							);
						}
						if ( null !== $baths ) {
							$room_parts[] = sprintf(
								/* translators: %d: bathroom count */
								_n( '%d bath', '%d baths', $baths, 'lpnw-alerts' ),
								$baths
							);
						}
						echo esc_html( implode( ', ', $room_parts ) );
						?>
					</p>
					<?php endif; ?>
					<?php if ( $lpnw_include_desc && ! empty( $prop->description ) ) : ?>
					<p style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#374151;">
						<?php echo esc_html( wp_trim_words( (string) $prop->description, 25 ) ); ?>
					</p>
					<?php endif; ?>
					<?php if ( $prop->source_url ) : ?>
						<a href="<?php echo esc_url( $prop->source_url ); ?>" style="display:inline-block;padding:14px 32px;background:#E8A317;color:#FFFFFF;text-decoration:none;border-radius:8px;font-size:16px;font-weight:700;line-height:1.3;border:2px solid #C48A0C;"><?php esc_html_e( 'View details', 'lpnw-alerts' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</td>
</tr>
