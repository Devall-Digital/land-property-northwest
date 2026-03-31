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
	<p><?php esc_html_e( 'Use these buttons to trigger a feed run immediately (useful for testing).', 'lpnw-alerts' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'lpnw_manual_feed', 'lpnw_nonce' ); ?>
		<input type="hidden" name="action" value="lpnw_run_feed">

		<p>
			<select name="feed_name">
				<option value="planning">Planning Portal</option>
				<option value="epc">EPC Open Data</option>
				<option value="landregistry">Land Registry</option>
				<option value="auctions">Auction Houses</option>
			</select>
			<?php submit_button( __( 'Run Feed Now', 'lpnw-alerts' ), 'secondary', 'submit', false ); ?>
		</p>
	</form>
</div>
