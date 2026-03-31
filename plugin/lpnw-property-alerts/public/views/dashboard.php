<?php
/**
 * Subscriber dashboard template.
 *
 * @package LPNW_Property_Alerts
 * @var string      $tier  Subscription tier (free, pro, vip).
 * @var object|null $prefs Subscriber preferences.
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$user_id     = get_current_user_id();
$alert_count = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue aq
	 INNER JOIN {$wpdb->prefix}lpnw_subscriber_preferences sp ON sp.id = aq.subscriber_id
	 WHERE sp.user_id = %d AND aq.status = 'sent'",
	$user_id
) );
$saved_count = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_saved_properties WHERE user_id = %d",
	$user_id
) );
$tier_label = strtoupper( $tier );
?>

<div class="lpnw-dashboard">
	<h2>Welcome back<?php echo $prefs ? '' : ' - set up your alert preferences to get started'; ?></h2>

	<div class="lpnw-dashboard-stats">
		<div class="lpnw-stat-card">
			<div class="lpnw-stat-card__number"><?php echo esc_html( $tier_label ); ?></div>
			<div class="lpnw-stat-card__label">Your Plan</div>
		</div>
		<div class="lpnw-stat-card">
			<div class="lpnw-stat-card__number"><?php echo esc_html( number_format( $alert_count ) ); ?></div>
			<div class="lpnw-stat-card__label">Alerts Received</div>
		</div>
		<div class="lpnw-stat-card">
			<div class="lpnw-stat-card__number"><?php echo esc_html( number_format( $saved_count ) ); ?></div>
			<div class="lpnw-stat-card__label">Saved Properties</div>
		</div>
	</div>

	<?php if ( 'free' === $tier ) : ?>
		<div style="background:linear-gradient(135deg,#1B2A4A,#2D4470);color:#fff;padding:24px;border-radius:8px;margin-bottom:24px;">
			<h3 style="color:#fff;margin:0 0 8px;">Get instant alerts</h3>
			<p style="margin:0 0 16px;opacity:0.9;">You are on the Free plan with weekly digests. Upgrade to Pro for daily instant alerts, or VIP for priority access and off-market deals.</p>
			<a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="lpnw-btn lpnw-btn--primary">View Pricing</a>
		</div>
	<?php endif; ?>

	<h3>Quick Links</h3>
	<ul>
		<li><a href="<?php echo esc_url( home_url( '/preferences/' ) ); ?>">Edit Alert Preferences</a></li>
		<li><a href="<?php echo esc_url( home_url( '/saved/' ) ); ?>">Saved Properties</a></li>
		<li><a href="<?php echo esc_url( home_url( '/map/' ) ); ?>">Property Map</a></li>
	</ul>

	<h3>Latest Alerts</h3>
	<?php echo do_shortcode( '[lpnw_alert_feed limit="10"]' ); ?>
</div>
