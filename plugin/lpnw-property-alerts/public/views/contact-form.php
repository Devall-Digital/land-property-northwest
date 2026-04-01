<?php
/**
 * Public contact form markup (AJAX submit).
 *
 * @package LPNW_Property_Alerts
 * @var string $nonce Nonce field HTML from wp_nonce_field(..., false ).
 */

defined( 'ABSPATH' ) || exit;
?>
<form id="lpnw-contact-form" class="lpnw-contact-form lpnw-preferences-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" novalidate>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core nonce field HTML.
	echo $nonce;
	?>
	<input type="hidden" name="action" value="lpnw_contact_form" />

	<div class="lpnw-field">
		<label for="lpnw-contact-name"><?php esc_html_e( 'Name', 'lpnw-alerts' ); ?> <span class="lpnw-field__required" aria-hidden="true">*</span></label>
		<input type="text" id="lpnw-contact-name" name="name" required autocomplete="name" maxlength="200" />
	</div>

	<div class="lpnw-field">
		<label for="lpnw-contact-email"><?php esc_html_e( 'Email', 'lpnw-alerts' ); ?> <span class="lpnw-field__required" aria-hidden="true">*</span></label>
		<input type="email" id="lpnw-contact-email" name="email" required autocomplete="email" maxlength="200" />
	</div>

	<div class="lpnw-field">
		<label for="lpnw-contact-subject"><?php esc_html_e( 'Subject', 'lpnw-alerts' ); ?> <span class="lpnw-field__optional">(<?php esc_html_e( 'optional', 'lpnw-alerts' ); ?>)</span></label>
		<input type="text" id="lpnw-contact-subject" name="subject" autocomplete="off" maxlength="200" />
	</div>

	<div class="lpnw-field">
		<label for="lpnw-contact-message"><?php esc_html_e( 'Message', 'lpnw-alerts' ); ?> <span class="lpnw-field__required" aria-hidden="true">*</span></label>
		<textarea id="lpnw-contact-message" name="message" required rows="6" maxlength="10000"></textarea>
	</div>

	<p class="lpnw-contact-form__feedback" role="status" aria-live="polite" hidden></p>

	<button type="submit" class="lpnw-btn lpnw-btn--primary" id="lpnw-contact-submit">
		<?php esc_html_e( 'Send message', 'lpnw-alerts' ); ?>
	</button>
</form>
