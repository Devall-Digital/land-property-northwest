<?php
/**
 * Alert signup form for non-logged-in visitors.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) : ?>
	<div class="lpnw-login-prompt lpnw-signup-form lpnw-signup-form--logged-in">
		<p class="lpnw-signup-form__lead">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: preferences URL, 2: dashboard URL */
					__( 'You are already signed in. <a href="%1$s">Set your alert preferences</a> or <a href="%2$s">go to your dashboard</a>.', 'lpnw-alerts' ),
					esc_url( home_url( '/preferences/' ) ),
					esc_url( home_url( '/dashboard/' ) )
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			);
			?>
		</p>
	</div>
<?php else : ?>
	<div class="lpnw-login-prompt lpnw-signup-form lpnw-signup-form--guest">
		<h3 class="lpnw-signup-form__title"><?php esc_html_e( 'Get NW property alerts', 'lpnw-alerts' ); ?></h3>
		<p class="lpnw-signup-form__intro"><?php esc_html_e( 'Create a free account in a minute. After you register, you will be taken to your preferences page to choose areas and alert types. Free accounts get a weekly digest; upgrade when you want instant alerts.', 'lpnw-alerts' ); ?></p>
		<div class="lpnw-signup-form__actions">
			<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="lpnw-btn lpnw-btn--primary"><?php esc_html_e( 'Sign up free', 'lpnw-alerts' ); ?></a>
			<a href="<?php echo esc_url( wp_login_url( home_url( '/preferences/' ) ) ); ?>" class="lpnw-btn lpnw-btn--outline"><?php esc_html_e( 'Log in', 'lpnw-alerts' ); ?></a>
		</div>
		<p class="lpnw-signup-form__foot">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: pricing URL, 2: contact URL */
					__( 'Compare <a href="%1$s">plans and pricing</a>. Questions? <a href="%2$s">Contact us</a>.', 'lpnw-alerts' ),
					esc_url( home_url( '/pricing/' ) ),
					esc_url( home_url( '/contact/' ) )
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			);
			?>
		</p>
	</div>
<?php endif; ?>
