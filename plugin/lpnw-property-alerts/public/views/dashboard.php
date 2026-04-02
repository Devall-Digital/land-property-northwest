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
$user        = wp_get_current_user();
$display     = $user && $user->exists() ? $user->display_name : '';
$week_ago     = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
$week_matches = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue aq
		 INNER JOIN {$wpdb->prefix}lpnw_subscriber_preferences sp ON sp.id = aq.subscriber_id
		 WHERE sp.user_id = %d AND aq.status = 'sent' AND aq.sent_at IS NOT NULL AND aq.sent_at >= %s",
		$user_id,
		$week_ago
	)
);
$saved_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_saved_properties WHERE user_id = %d",
		$user_id
	)
);

$show_alert_schedule_tip = is_user_logged_in() && in_array( strtolower( (string) $tier ), array( 'pro', 'vip' ), true )
	? LPNW_WooCommerce_Notices::consume_alert_schedule_tip( $user_id )
	: false;

$tier_key = strtolower( (string) $tier );
switch ( $tier_key ) {
	case 'vip':
		$tier_label       = __( 'Investor VIP', 'lpnw-alerts' );
		$tier_short       = 'VIP';
		$tier_description = __( 'Priority alerts and full coverage', 'lpnw-alerts' );
		break;
	case 'pro':
		$tier_label       = __( 'Pro', 'lpnw-alerts' );
		$tier_short       = 'PRO';
		$tier_description = __( 'Instant alerts and full filters', 'lpnw-alerts' );
		break;
	default:
		$tier_label       = __( 'Free', 'lpnw-alerts' );
		$tier_short       = __( 'FREE', 'lpnw-alerts' );
		$tier_description = __( 'Weekly digest', 'lpnw-alerts' );
		break;
}

$nw_total        = count( LPNW_NW_POSTCODES );
$areas_selected  = ( $prefs && is_array( $prefs->areas ) ) ? count( $prefs->areas ) : 0;
$types_selected  = ( $prefs && is_array( $prefs->property_types ) ) ? count( $prefs->property_types ) : 0;
$type_slots      = 6;
$area_pct        = $areas_selected > 0 ? min( 100, (int) round( 100 * $areas_selected / $nw_total ) ) : 100;
$type_pct        = $types_selected > 0 ? min( 100, (int) round( 100 * $types_selected / $type_slots ) ) : 100;
$area_bar_label  = $areas_selected > 0
	? sprintf(
		/* translators: 1: number of areas, 2: total NW area codes */
		__( '%1$d of %2$d regions', 'lpnw-alerts' ),
		$areas_selected,
		$nw_total
	)
	: __( 'All regions (no filter)', 'lpnw-alerts' );
$type_bar_label  = $types_selected > 0
	? sprintf(
		/* translators: 1: number of selected property types */
		_n( '%d property type', '%d property types', $types_selected, 'lpnw-alerts' ),
		$types_selected
	)
	: __( 'All property types (no filter)', 'lpnw-alerts' );
?>

<div class="lpnw-dashboard lpnw-dashboard--subscriber lpnw-subscriber-area">
	<?php if ( $show_alert_schedule_tip ) : ?>
		<div class="lpnw-dashboard-notice lpnw-dashboard-notice--info" role="status">
			<p class="lpnw-dashboard-notice__title"><?php esc_html_e( 'How your alerts are sent', 'lpnw-alerts' ); ?></p>
			<p class="lpnw-dashboard-notice__text">
				<?php
				if ( 'vip' === $tier_key ) {
					esc_html_e(
						'Thank you for upgrading. Alerts are sent when new listings match your saved preferences. You can choose instant emails or a daily digest under Alert preferences. Weekly digest is not used on VIP; if you had weekly selected, we use daily instead.',
						'lpnw-alerts'
					);
				} else {
					esc_html_e(
						'Thank you for upgrading. Alerts are sent when new listings match your saved preferences. Choose instant emails or a daily digest under Alert preferences. If you expected instant mail and see a delay, check that frequency is set to instant and allow a few minutes after checkout.',
						'lpnw-alerts'
					);
				}
				?>
			</p>
			<p class="lpnw-dashboard-notice__cta">
				<a class="lpnw-btn lpnw-btn--outline" href="<?php echo esc_url( home_url( '/preferences/' ) ); ?>"><?php esc_html_e( 'Review alert preferences', 'lpnw-alerts' ); ?></a>
			</p>
		</div>
	<?php endif; ?>
	<section class="lpnw-dashboard-welcome" aria-labelledby="lpnw-dashboard-welcome-heading">
		<div class="lpnw-dashboard-welcome__main">
			<p class="lpnw-dashboard-welcome__kicker"><?php esc_html_e( 'Subscriber dashboard', 'lpnw-alerts' ); ?></p>
			<h2 class="lpnw-dashboard-welcome__title" id="lpnw-dashboard-welcome-heading">
				<?php
				printf(
					/* translators: %s: user's display name */
					esc_html__( 'Welcome back, %s', 'lpnw-alerts' ),
					esc_html( $display ? $display : __( 'there', 'lpnw-alerts' ) )
				);
				?>
			</h2>
			<?php if ( ! $prefs ) : ?>
				<p class="lpnw-dashboard-welcome__hint"><?php esc_html_e( 'Set up your alert preferences to start receiving matched properties.', 'lpnw-alerts' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="lpnw-dashboard-welcome__tier" role="status">
			<span class="lpnw-dashboard-welcome__tier-label"><?php esc_html_e( 'Your plan', 'lpnw-alerts' ); ?></span>
			<span class="lpnw-dashboard-welcome__tier-badge lpnw-dashboard-welcome__tier-badge--<?php echo esc_attr( $tier_key ); ?>"><?php echo esc_html( $tier_short ); ?></span>
			<span class="lpnw-dashboard-welcome__tier-name"><?php echo esc_html( $tier_label ); ?></span>
			<span class="lpnw-dashboard-welcome__tier-meta"><?php echo esc_html( $tier_description ); ?></span>
		</div>
	</section>

	<p class="lpnw-dashboard-summary">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: number of alerts sent this week */
				_n(
					'You have %d new match this week.',
					'You have %d new matches this week.',
					$week_matches,
					'lpnw-alerts'
				),
				$week_matches
			)
		);
		?>
	</p>

	<section class="lpnw-dashboard-coverage" aria-labelledby="lpnw-dashboard-coverage-heading">
		<h3 class="lpnw-dashboard-coverage__heading" id="lpnw-dashboard-coverage-heading"><?php esc_html_e( 'Your alert coverage', 'lpnw-alerts' ); ?></h3>
		<div class="lpnw-dashboard-coverage__row">
			<div class="lpnw-dashboard-coverage__label">
				<span class="lpnw-dashboard-coverage__title"><?php esc_html_e( 'Areas', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-dashboard-coverage__value"><?php echo esc_html( $area_bar_label ); ?></span>
			</div>
			<div class="lpnw-progress" role="presentation">
				<div class="lpnw-progress__track">
					<div class="lpnw-progress__fill lpnw-progress__fill--navy" style="width: <?php echo esc_attr( (string) $area_pct ); ?>%;"></div>
				</div>
				<span class="lpnw-progress__pct"><?php echo esc_html( (string) $area_pct ); ?>%</span>
			</div>
		</div>
		<div class="lpnw-dashboard-coverage__row">
			<div class="lpnw-dashboard-coverage__label">
				<span class="lpnw-dashboard-coverage__title"><?php esc_html_e( 'Property types', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-dashboard-coverage__value"><?php echo esc_html( $type_bar_label ); ?></span>
			</div>
			<div class="lpnw-progress" role="presentation">
				<div class="lpnw-progress__track">
					<div class="lpnw-progress__fill lpnw-progress__fill--amber" style="width: <?php echo esc_attr( (string) $type_pct ); ?>%;"></div>
				</div>
				<span class="lpnw-progress__pct"><?php echo esc_html( (string) $type_pct ); ?>%</span>
			</div>
		</div>
	</section>

	<div class="lpnw-dashboard-stats">
		<div class="lpnw-stat-card lpnw-stat-card--navy">
			<span class="lpnw-stat-card__icon lpnw-stat-card__icon--plan" aria-hidden="true"></span>
			<div class="lpnw-stat-card__number"><?php echo esc_html( $tier_short ); ?></div>
			<div class="lpnw-stat-card__label"><?php esc_html_e( 'Plan tier', 'lpnw-alerts' ); ?></div>
		</div>
		<div class="lpnw-stat-card lpnw-stat-card--amber">
			<span class="lpnw-stat-card__icon lpnw-stat-card__icon--alerts" aria-hidden="true"></span>
			<div class="lpnw-stat-card__number"><?php echo esc_html( number_format_i18n( $week_matches ) ); ?></div>
			<div class="lpnw-stat-card__label"><?php esc_html_e( 'Alerts this week', 'lpnw-alerts' ); ?></div>
		</div>
		<div class="lpnw-stat-card lpnw-stat-card--green">
			<span class="lpnw-stat-card__icon lpnw-stat-card__icon--saved" aria-hidden="true"></span>
			<div class="lpnw-stat-card__number"><?php echo esc_html( number_format_i18n( $saved_count ) ); ?></div>
			<div class="lpnw-stat-card__label"><?php esc_html_e( 'Saved properties', 'lpnw-alerts' ); ?></div>
		</div>
	</div>

	<?php if ( 'free' === $tier_key ) : ?>
		<div class="lpnw-cta-banner lpnw-cta-banner--dashboard lpnw-cta-banner--premium" role="region" aria-labelledby="lpnw-dashboard-cta-heading">
			<div class="lpnw-cta-banner__accent" aria-hidden="true"></div>
			<div class="lpnw-cta-banner__inner">
				<p class="lpnw-cta-banner__eyebrow"><?php esc_html_e( 'Go beyond the weekly digest', 'lpnw-alerts' ); ?></p>
				<h3 class="lpnw-cta-banner__title" id="lpnw-dashboard-cta-heading"><?php esc_html_e( 'Unlock instant Northwest property alerts', 'lpnw-alerts' ); ?></h3>
				<p class="lpnw-cta-banner__text"><?php esc_html_e( 'Upgrade to Pro for daily instant alerts and full filtering, or Investor VIP for priority access and off-market opportunities.', 'lpnw-alerts' ); ?></p>
				<div class="lpnw-cta-banner__actions">
					<a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="lpnw-btn lpnw-btn--primary"><?php esc_html_e( 'View plans', 'lpnw-alerts' ); ?></a>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php
	$lpnw_myaccount_url = '';
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$lpnw_myaccount_url = (string) wc_get_page_permalink( 'myaccount' );
	}
	if ( '' === $lpnw_myaccount_url ) {
		$lpnw_myaccount_url = home_url( '/my-account/' );
	}
	?>
	<h3 class="lpnw-dashboard__section-heading"><?php esc_html_e( 'Quick actions', 'lpnw-alerts' ); ?></h3>
	<ul class="lpnw-dashboard__action-cards">
		<?php if ( 'pro' === $tier_key || 'vip' === $tier_key ) : ?>
		<li class="lpnw-dashboard__action-cards-item">
			<a class="lpnw-action-card" href="<?php echo esc_url( $lpnw_myaccount_url ); ?>">
				<span class="lpnw-action-card__icon lpnw-action-card__icon--preview" aria-hidden="true"></span>
				<span class="lpnw-action-card__title"><?php esc_html_e( 'Manage subscription', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-action-card__desc"><?php esc_html_e( 'Billing, payment method, and WooCommerce account details.', 'lpnw-alerts' ); ?></span>
			</a>
		</li>
		<?php endif; ?>
		<li class="lpnw-dashboard__action-cards-item">
			<a class="lpnw-action-card" href="<?php echo esc_url( home_url( '/preferences/' ) ); ?>">
				<span class="lpnw-action-card__icon lpnw-action-card__icon--prefs" aria-hidden="true"></span>
				<span class="lpnw-action-card__title"><?php esc_html_e( 'Alert preferences', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-action-card__desc"><?php esc_html_e( 'Refine areas, price, types, and what hits your inbox.', 'lpnw-alerts' ); ?></span>
			</a>
		</li>
		<li class="lpnw-dashboard__action-cards-item">
			<a class="lpnw-action-card" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>">
				<span class="lpnw-action-card__icon lpnw-action-card__icon--saved" aria-hidden="true"></span>
				<span class="lpnw-action-card__title"><?php esc_html_e( 'Saved properties', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-action-card__desc"><?php esc_html_e( 'Review listings you have bookmarked for follow-up.', 'lpnw-alerts' ); ?></span>
			</a>
		</li>
		<li class="lpnw-dashboard__action-cards-item">
			<a class="lpnw-action-card" href="<?php echo esc_url( home_url( '/map/' ) ); ?>">
				<span class="lpnw-action-card__icon lpnw-action-card__icon--map" aria-hidden="true"></span>
				<span class="lpnw-action-card__title"><?php esc_html_e( 'Property map', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-action-card__desc"><?php esc_html_e( 'Explore the latest stock across the Northwest on a map.', 'lpnw-alerts' ); ?></span>
			</a>
		</li>
		<li class="lpnw-dashboard__action-cards-item">
			<a class="lpnw-action-card" href="<?php echo esc_url( home_url( '/email-preview/' ) ); ?>">
				<span class="lpnw-action-card__icon lpnw-action-card__icon--preview" aria-hidden="true"></span>
				<span class="lpnw-action-card__title"><?php esc_html_e( 'Preview your alerts', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-action-card__desc"><?php esc_html_e( 'See how matched properties look in your next email.', 'lpnw-alerts' ); ?></span>
			</a>
		</li>
	</ul>

	<h3 class="lpnw-dashboard__section-heading"><?php esc_html_e( 'Latest alerts', 'lpnw-alerts' ); ?></h3>
	<?php echo do_shortcode( '[lpnw_alert_feed limit="10"]' ); ?>
</div>
