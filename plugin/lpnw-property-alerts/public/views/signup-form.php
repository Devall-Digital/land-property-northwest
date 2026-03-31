<?php
/**
 * Alert signup form for non-logged-in visitors.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) : ?>
	<div style="text-align:center;padding:24px;">
		<p>You are already signed in. <a href="<?php echo esc_url( home_url( '/preferences/' ) ); ?>">Set your alert preferences</a> or <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">go to your dashboard</a>.</p>
	</div>
<?php else : ?>
	<div style="max-width:480px;margin:0 auto;text-align:center;">
		<h3>Get NW Property Alerts</h3>
		<p>Create a free account to start receiving weekly property alerts for the Northwest. Upgrade anytime for instant alerts.</p>
		<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="lpnw-btn lpnw-btn--primary" style="margin:8px;">Sign Up Free</a>
		<a href="<?php echo esc_url( wp_login_url( home_url( '/preferences/' ) ) ); ?>" class="lpnw-btn lpnw-btn--outline" style="margin:8px;">Log In</a>
	</div>
<?php endif; ?>
