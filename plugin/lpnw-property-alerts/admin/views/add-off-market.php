<?php
/**
 * Add off-market deal (admin).
 *
 * @package LPNW_Property_Alerts
 * @var array{type:string,text:string}|null $lpnw_om_notice Dismissible admin notice (consumed in render).
 */

defined( 'ABSPATH' ) || exit;

$notice = isset( $lpnw_om_notice ) && is_array( $lpnw_om_notice ) ? $lpnw_om_notice : null;

$property_type_options = array(
	'Detached'        => __( 'Detached', 'lpnw-alerts' ),
	'Semi-detached'   => __( 'Semi-detached', 'lpnw-alerts' ),
	'Terraced'        => __( 'Terraced', 'lpnw-alerts' ),
	'Flat/Maisonette' => __( 'Flat / maisonette', 'lpnw-alerts' ),
	'Auction lot'     => __( 'Auction lot', 'lpnw-alerts' ),
	'Other'           => __( 'Other / land', 'lpnw-alerts' ),
);

$tenure_options = array(
	'freehold'          => __( 'Freehold', 'lpnw-alerts' ),
	'leasehold'         => __( 'Leasehold', 'lpnw-alerts' ),
	'share of freehold' => __( 'Share of freehold', 'lpnw-alerts' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Add Off-Market Deal', 'lpnw-alerts' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'VIP-exclusive listings from your network. Matching runs immediately so subscribers with off-market alerts enabled are queued.', 'lpnw-alerts' ); ?>
	</p>

	<?php if ( $notice && ! empty( $notice['text'] ) ) : ?>
		<div class="notice <?php echo 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : 'notice-success'; ?> is-dismissible">
			<p><?php echo esc_html( (string) $notice['text'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lpnw-add-off-market-form">
		<?php wp_nonce_field( LPNW_Admin::ADD_OFF_MARKET_NONCE_ACTION ); ?>
		<input type="hidden" name="action" value="lpnw_add_off_market" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lpnw_om_address"><?php esc_html_e( 'Address', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_address" id="lpnw_om_address" type="text" class="large-text" required maxlength="500" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_postcode"><?php esc_html_e( 'Postcode', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_postcode" id="lpnw_om_postcode" type="text" class="regular-text" required maxlength="12" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_price"><?php esc_html_e( 'Price (GBP)', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_price" id="lpnw_om_price" type="number" class="small-text" min="0" step="1" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sale or rent', 'lpnw-alerts' ); ?></th>
				<td>
					<fieldset>
						<label><input type="radio" name="lpnw_application_type" value="sale" checked /> <?php esc_html_e( 'For sale', 'lpnw-alerts' ); ?></label>
						&nbsp;&nbsp;
						<label><input type="radio" name="lpnw_application_type" value="rent" /> <?php esc_html_e( 'To let', 'lpnw-alerts' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_bedrooms"><?php esc_html_e( 'Bedrooms', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_bedrooms" id="lpnw_om_bedrooms" type="number" class="small-text" min="0" max="50" step="1" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_bathrooms"><?php esc_html_e( 'Bathrooms', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_bathrooms" id="lpnw_om_bathrooms" type="number" class="small-text" min="0" max="50" step="1" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_property_type"><?php esc_html_e( 'Property type', 'lpnw-alerts' ); ?></label></th>
				<td>
					<select name="lpnw_property_type" id="lpnw_om_property_type">
						<option value=""><?php esc_html_e( '— Select —', 'lpnw-alerts' ); ?></option>
						<?php foreach ( $property_type_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_tenure"><?php esc_html_e( 'Tenure', 'lpnw-alerts' ); ?></label></th>
				<td>
					<select name="lpnw_tenure" id="lpnw_om_tenure">
						<option value=""><?php esc_html_e( '— Select —', 'lpnw-alerts' ); ?></option>
						<?php foreach ( $tenure_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_description"><?php esc_html_e( 'Description', 'lpnw-alerts' ); ?></label></th>
				<td><textarea name="lpnw_description" id="lpnw_om_description" class="large-text" rows="6"></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_agent"><?php esc_html_e( 'Agent / source name', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_agent_name" id="lpnw_om_agent" type="text" class="large-text" maxlength="255" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_contact"><?php esc_html_e( 'Contact (phone or email)', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_agent_contact" id="lpnw_om_contact" type="text" class="large-text" maxlength="500" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_why"><?php esc_html_e( 'Why it is off-market', 'lpnw-alerts' ); ?></label></th>
				<td><textarea name="lpnw_off_market_reason" id="lpnw_om_why" class="large-text" rows="3" maxlength="2000"></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="lpnw_om_image"><?php esc_html_e( 'Image URL (optional)', 'lpnw-alerts' ); ?></label></th>
				<td><input name="lpnw_image_url" id="lpnw_om_image" type="url" class="large-text" maxlength="500" placeholder="https://" /></td>
			</tr>
		</table>

		<?php submit_button( __( 'Add deal and run matcher', 'lpnw-alerts' ) ); ?>
	</form>
</div>
