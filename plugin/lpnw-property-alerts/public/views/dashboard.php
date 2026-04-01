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
$alert_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue aq
		 INNER JOIN {$wpdb->prefix}lpnw_subscriber_preferences sp ON sp.id = aq.subscriber_id
		 WHERE sp.user_id = %d AND aq.status = 'sent'",
		$user_id
	)
);
$saved_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_saved_properties WHERE user_id = %d",
		$user_id
	)
);
$tier_label = strtoupper( $tier );
?>

<div class="lpnw-dashboard lpnw-dashboard--subscriber">
	<h2 class="lpnw-dashboard__title">
		<?php
		if ( $prefs ) {
			esc_html_e( 'Welcome back', 'lpnw-alerts' );
		} else {
			esc_html_e( 'Welcome back. Set up your alert preferences to get started.', 'lpnw-alerts' );
		}
		?>
	</h2>

	<div class="lpnw-dashboard-stats">
		<div class="lpnw-stat-card">
			<div class="lpnw-stat-card__number"><?php echo esc_html( $tier_label ); ?></div>
			<div class="lpnw-stat-card__label"><?php esc_html_e( 'Your plan', 'lpnw-alerts' ); ?></div>
		</div>
		<div class="lpnw-stat-card">
			<div class="lpnw-stat-card__number"><?php echo esc_html( number_format_i18n( $alert_count ) ); ?></div>
			<div class="lpnw-stat-card__label"><?php esc_html_e( 'Alerts received', 'lpnw-alerts' ); ?></div>
		</div>
		<div class="lpnw-stat-card">
			<div class="lpnw-stat-card__number"><?php echo esc_html( number_format_i18n( $saved_count ) ); ?></div>
			<div class="lpnw-stat-card__label"><?php esc_html_e( 'Saved properties', 'lpnw-alerts' ); ?></div>
		</div>
	</div>

	<?php if ( 'free' === $tier ) : ?>
		<div class="lpnw-cta-banner lpnw-cta-banner--dashboard" role="region" aria-labelledby="lpnw-dashboard-cta-heading">
			<div class="lpnw-cta-banner__inner">
				<h3 class="lpnw-cta-banner__title" id="lpnw-dashboard-cta-heading"><?php esc_html_e( 'Get instant alerts', 'lpnw-alerts' ); ?></h3>
				<p class="lpnw-cta-banner__text"><?php esc_html_e( 'You are on the Free plan with weekly digests. Upgrade to Pro for daily instant alerts, or VIP for priority access and off-market deals.', 'lpnw-alerts' ); ?></p>
				<div class="lpnw-cta-banner__actions">
					<a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="lpnw-btn lpnw-btn--primary"><?php esc_html_e( 'View pricing', 'lpnw-alerts' ); ?></a>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<h3 class="lpnw-dashboard__section-heading"><?php esc_html_e( 'Quick links', 'lpnw-alerts' ); ?></h3>
	<ul class="lpnw-dashboard__links">
		<li class="lpnw-dashboard__links-item">
			<a class="lpnw-dashboard__link" href="<?php echo esc_url( home_url( '/preferences/' ) ); ?>"><?php esc_html_e( 'Edit alert preferences', 'lpnw-alerts' ); ?></a>
		</li>
		<li class="lpnw-dashboard__links-item">
			<a class="lpnw-dashboard__link" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>"><?php esc_html_e( 'Saved properties', 'lpnw-alerts' ); ?></a>
		</li>
		<li class="lpnw-dashboard__links-item">
			<a class="lpnw-dashboard__link" href="<?php echo esc_url( home_url( '/map/' ) ); ?>"><?php esc_html_e( 'Property map', 'lpnw-alerts' ); ?></a>
		</li>
		<li class="lpnw-dashboard__links-item">
			<a class="lpnw-dashboard__link" href="<?php echo esc_url( home_url( '/email-preview/' ) ); ?>"><?php esc_html_e( 'Preview your alerts', 'lpnw-alerts' ); ?></a>
		</li>
	</ul>

	<h3 class="lpnw-dashboard__section-heading"><?php esc_html_e( 'Latest alerts', 'lpnw-alerts' ); ?></h3>
	<?php echo do_shortcode( '[lpnw_alert_feed limit="10"]' ); ?>
</div>
