<?php
/**
 * VIP off-market submission form (shortcode body).
 *
 * @package LPNW_Property_Alerts
 * @var string $nonce Nonce field HTML from wp_nonce_field.
 */

defined( 'ABSPATH' ) || exit;

$messages = array(
	'ok'        => __( 'Thank you. Your opportunity was submitted and matching VIP alerts are being queued.', 'lpnw-alerts' ),
	'missing'   => __( 'Please enter the address and postcode.', 'lpnw-alerts' ),
	'postcode'  => __( 'Postcode must be in our Northwest coverage area.', 'lpnw-alerts' ),
	'type'      => __( 'Please choose a property type.', 'lpnw-alerts' ),
	'fail'      => __( 'We could not save that submission. Please try again or contact support.', 'lpnw-alerts' ),
	'rate'      => __( 'Too many submissions in a short time. Please wait an hour and try again.', 'lpnw-alerts' ),
	'bad_nonce' => __( 'Your session expired. Refresh the page and try again.', 'lpnw-alerts' ),
	'not_vip'   => __( 'Off-market submissions require an Investor VIP subscription.', 'lpnw-alerts' ),
);

$flash = isset( $_GET['lpnw_om'] ) ? sanitize_key( wp_unslash( $_GET['lpnw_om'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="lpnw-off-market-submit">
	<?php if ( '' !== $flash && isset( $messages[ $flash ] ) ) : ?>
		<p class="lpnw-off-market-submit__notice<?php echo 'ok' === $flash ? ' lpnw-off-market-submit__notice--success' : ' lpnw-off-market-submit__notice--error'; ?>" role="status">
			<?php echo esc_html( $messages[ $flash ] ); ?>
		</p>
	<?php endif; ?>

	<p class="lpnw-off-market-submit__intro">
		<?php esc_html_e( 'Share a genuine off-market or pre-market opportunity with other Investor VIP members. Submissions are subject to our terms and may be removed if they are misleading or duplicate public listings.', 'lpnw-alerts' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lpnw-off-market-submit__form">
		<input type="hidden" name="action" value="lpnw_submit_off_market" />
		<?php
		echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field from wp_nonce_field.
		?>

		<p class="lpnw-field" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
			<label for="lpnw_om_website"><?php esc_html_e( 'Website', 'lpnw-alerts' ); ?></label>
			<input type="text" name="lpnw_om_website" id="lpnw_om_website" value="" tabindex="-1" autocomplete="off" />
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_address"><?php esc_html_e( 'Address or location', 'lpnw-alerts' ); ?> <span class="required">*</span></label>
			<input type="text" name="lpnw_om_address" id="lpnw_om_address" required maxlength="500" class="widefat" />
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_postcode"><?php esc_html_e( 'Postcode', 'lpnw-alerts' ); ?> <span class="required">*</span></label>
			<input type="text" name="lpnw_om_postcode" id="lpnw_om_postcode" required maxlength="12" class="widefat" />
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_property_type"><?php esc_html_e( 'Property type', 'lpnw-alerts' ); ?> <span class="required">*</span></label>
			<select name="lpnw_om_property_type" id="lpnw_om_property_type" required>
				<option value=""><?php esc_html_e( 'Select…', 'lpnw-alerts' ); ?></option>
				<option value="Detached"><?php esc_html_e( 'Detached', 'lpnw-alerts' ); ?></option>
				<option value="Semi-detached"><?php esc_html_e( 'Semi-detached', 'lpnw-alerts' ); ?></option>
				<option value="Terraced"><?php esc_html_e( 'Terraced', 'lpnw-alerts' ); ?></option>
				<option value="Flat/Maisonette"><?php esc_html_e( 'Flat / maisonette', 'lpnw-alerts' ); ?></option>
				<option value="Auction lot"><?php esc_html_e( 'Auction lot', 'lpnw-alerts' ); ?></option>
				<option value="Other"><?php esc_html_e( 'Other', 'lpnw-alerts' ); ?></option>
			</select>
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_application_type"><?php esc_html_e( 'Sale or rent', 'lpnw-alerts' ); ?></label>
			<select name="lpnw_om_application_type" id="lpnw_om_application_type">
				<option value="sale"><?php esc_html_e( 'For sale', 'lpnw-alerts' ); ?></option>
				<option value="rent"><?php esc_html_e( 'To rent', 'lpnw-alerts' ); ?></option>
			</select>
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_price"><?php esc_html_e( 'Price (optional)', 'lpnw-alerts' ); ?></label>
			<input type="number" name="lpnw_om_price" id="lpnw_om_price" min="0" step="1000" class="widefat" />
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_bedrooms"><?php esc_html_e( 'Bedrooms (optional)', 'lpnw-alerts' ); ?></label>
			<input type="number" name="lpnw_om_bedrooms" id="lpnw_om_bedrooms" min="0" max="50" step="1" class="widefat" />
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_description"><?php esc_html_e( 'Brief description', 'lpnw-alerts' ); ?></label>
			<textarea name="lpnw_om_description" id="lpnw_om_description" rows="5" class="widefat" maxlength="8000"></textarea>
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_contact"><?php esc_html_e( 'How others should contact you (email or phone)', 'lpnw-alerts' ); ?></label>
			<input type="text" name="lpnw_om_contact" id="lpnw_om_contact" maxlength="255" class="widefat" />
		</p>

		<p class="lpnw-field">
			<label for="lpnw_om_reason"><?php esc_html_e( 'Why it is off-market (optional)', 'lpnw-alerts' ); ?></label>
			<textarea name="lpnw_om_reason" id="lpnw_om_reason" rows="3" class="widefat" maxlength="2000"></textarea>
		</p>

		<p class="lpnw-field">
			<button type="submit" class="lpnw-btn lpnw-btn--primary"><?php esc_html_e( 'Submit opportunity', 'lpnw-alerts' ); ?></button>
		</p>
	</form>
</div>
