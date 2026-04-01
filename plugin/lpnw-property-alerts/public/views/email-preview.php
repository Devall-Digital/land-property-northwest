<?php
/**
 * Email alert preview (shortcode output).
 *
 * @package LPNW_Property_Alerts
 * @var array<int, object> $email_preview_matching    Property rows shown in the preview.
 * @var string              $email_preview_body_html   Full HTML document for iframe srcdoc.
 * @var string              $email_preview_freq_label  Human-readable layout label.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="lpnw-email-preview">
	<h2 class="lpnw-email-preview__title"><?php esc_html_e( 'Alert email preview', 'lpnw-alerts' ); ?></h2>
	<p class="lpnw-email-preview__intro">
		<?php esc_html_e( 'This is what your alert emails will look like based on your current preferences.', 'lpnw-alerts' ); ?>
	</p>
	<?php if ( ! empty( $email_preview_matching ) ) : ?>
		<p class="lpnw-email-preview__meta">
			<?php
			printf(
				/* translators: 1: number of sample properties (max 5), 2: email layout name e.g. "weekly digest". */
				esc_html__( 'Sample: %1$d recent matching properties using your %2$s layout.', 'lpnw-alerts' ),
				count( $email_preview_matching ),
				esc_html( $email_preview_freq_label )
			);
			?>
		</p>
		<div class="lpnw-email-preview__chrome" role="region" aria-label="<?php esc_attr_e( 'Email preview', 'lpnw-alerts' ); ?>">
			<iframe
				class="lpnw-email-preview__iframe"
				title="<?php esc_attr_e( 'Preview of your property alert email', 'lpnw-alerts' ); ?>"
				sandbox=""
				srcdoc="<?php echo esc_attr( $email_preview_body_html ); ?>"
			></iframe>
		</div>
		<p class="lpnw-email-preview__foot">
			<a class="lpnw-email-preview__link" href="<?php echo esc_url( home_url( '/preferences/' ) ); ?>"><?php esc_html_e( 'Edit alert preferences', 'lpnw-alerts' ); ?></a>
		</p>
	<?php else : ?>
		<div class="lpnw-email-preview__empty">
			<p><?php esc_html_e( 'No properties in the database match your current preferences yet.', 'lpnw-alerts' ); ?></p>
			<p><?php esc_html_e( 'Try widening your area or price range, including more property types, or enabling more alert sources so you are more likely to see matches.', 'lpnw-alerts' ); ?></p>
			<p class="lpnw-email-preview__foot">
				<a class="lpnw-email-preview__link" href="<?php echo esc_url( home_url( '/preferences/' ) ); ?>"><?php esc_html_e( 'Adjust your preferences', 'lpnw-alerts' ); ?></a>
			</p>
		</div>
	<?php endif; ?>
</div>
