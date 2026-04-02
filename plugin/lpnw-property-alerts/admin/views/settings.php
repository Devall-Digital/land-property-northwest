<?php
/**
 * Admin settings page.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1><?php esc_html_e( 'LPNW Alert Settings', 'lpnw-alerts' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'lpnw_settings_group' ); ?>
		<?php do_settings_sections( 'lpnw-settings' ); ?>
		<?php submit_button(); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Manual Feed Run', 'lpnw-alerts' ); ?></h2>
	<p><?php esc_html_e( 'Trigger a feed run immediately (useful for testing).', 'lpnw-alerts' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( LPNW_Admin::FEED_RUN_NONCE_ACTION ); ?>
		<input type="hidden" name="action" value="lpnw_run_feed">
		<input type="hidden" name="lpnw_redirect_to" value="settings">

		<p>
			<label for="lpnw_manual_feed" class="screen-reader-text"><?php esc_html_e( 'Select feed', 'lpnw-alerts' ); ?></label>
			<select name="feed" id="lpnw_manual_feed">
				<optgroup label="<?php esc_attr_e( 'Portals', 'lpnw-alerts' ); ?>">
					<option value="rightmove"><?php esc_html_e( 'Rightmove', 'lpnw-alerts' ); ?></option>
					<option value="zoopla"><?php esc_html_e( 'Zoopla', 'lpnw-alerts' ); ?></option>
					<option value="onthemarket"><?php esc_html_e( 'OnTheMarket', 'lpnw-alerts' ); ?></option>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'Other data', 'lpnw-alerts' ); ?>">
					<option value="planning"><?php esc_html_e( 'Planning Portal', 'lpnw-alerts' ); ?></option>
					<option value="epc"><?php esc_html_e( 'EPC Open Data', 'lpnw-alerts' ); ?></option>
					<option value="landregistry"><?php esc_html_e( 'Land Registry', 'lpnw-alerts' ); ?></option>
					<option value="auctions"><?php esc_html_e( 'All auction feeds (Pugh, SDL, AHNW, Allsop)', 'lpnw-alerts' ); ?></option>
				</optgroup>
			</select>
			<?php submit_button( __( 'Run Feed Now', 'lpnw-alerts' ), 'secondary', 'submit', false ); ?>
		</p>
	</form>
</div>
