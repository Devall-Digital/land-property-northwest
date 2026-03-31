<?php
/**
 * Alert feed template for logged-in subscribers.
 *
 * @package LPNW_Property_Alerts
 * @var array $atts Shortcode attributes.
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$user_id = get_current_user_id();
$limit   = absint( $atts['limit'] ?? 20 );

$alerts = $wpdb->get_results( $wpdb->prepare(
	"SELECT p.*
	 FROM {$wpdb->prefix}lpnw_alert_queue aq
	 INNER JOIN {$wpdb->prefix}lpnw_properties p ON p.id = aq.property_id
	 INNER JOIN {$wpdb->prefix}lpnw_subscriber_preferences sp ON sp.id = aq.subscriber_id
	 WHERE sp.user_id = %d AND aq.status = 'sent'
	 ORDER BY aq.sent_at DESC
	 LIMIT %d",
	$user_id,
	$limit
) );

if ( empty( $alerts ) ) : ?>
	<p>No alerts yet. Once our data feeds find properties matching your preferences, they will appear here.</p>
<?php else : ?>
	<div class="lpnw-property-list">
		<?php foreach ( $alerts as $prop ) : ?>
			<div class="lpnw-property-card">
				<h3 class="lpnw-property-card__address"><?php echo esc_html( $prop->address ); ?></h3>
				<div class="lpnw-property-card__meta">
					<span><?php echo esc_html( $prop->postcode ); ?></span>
					<?php if ( $prop->price ) : ?>
						<span>&pound;<?php echo esc_html( number_format( (int) $prop->price ) ); ?></span>
					<?php endif; ?>
					<span class="lpnw-source-badge lpnw-source-badge--<?php echo esc_attr( explode( '_', $prop->source )[0] ); ?>">
						<?php echo esc_html( ucfirst( str_replace( '_', ' ', $prop->source ) ) ); ?>
					</span>
				</div>
				<?php if ( $prop->description ) : ?>
					<p class="lpnw-property-card__description"><?php echo esc_html( wp_trim_words( $prop->description, 30 ) ); ?></p>
				<?php endif; ?>
				<div class="lpnw-property-card__actions">
					<?php if ( $prop->source_url ) : ?>
						<a href="<?php echo esc_url( $prop->source_url ); ?>" class="lpnw-btn lpnw-btn--outline" target="_blank" rel="noopener">View Source</a>
					<?php endif; ?>
					<button class="lpnw-btn lpnw-btn--outline lpnw-save-property" data-property-id="<?php echo esc_attr( $prop->id ); ?>">Save</button>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
