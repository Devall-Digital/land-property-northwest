<?php
/**
 * Saved properties template.
 *
 * @package LPNW_Property_Alerts
 * @var array<object> $saved Saved property rows with property data joined.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $saved ) ) : ?>
	<p>You have not saved any properties yet. Browse your <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">alerts</a> or the <a href="<?php echo esc_url( home_url( '/map/' ) ); ?>">property map</a> to find and save properties.</p>
<?php else : ?>
	<div class="lpnw-property-list">
		<?php foreach ( $saved as $item ) : ?>
			<div class="lpnw-property-card">
				<h3 class="lpnw-property-card__address"><?php echo esc_html( $item->address ); ?></h3>
				<div class="lpnw-property-card__meta">
					<span><?php echo esc_html( $item->postcode ); ?></span>
					<?php if ( $item->price ) : ?>
						<span>&pound;<?php echo esc_html( number_format( (int) $item->price ) ); ?></span>
					<?php endif; ?>
					<span class="lpnw-source-badge lpnw-source-badge--<?php echo esc_attr( explode( '_', $item->source )[0] ); ?>">
						<?php echo esc_html( ucfirst( str_replace( '_', ' ', $item->source ) ) ); ?>
					</span>
					<span>Saved <?php echo esc_html( human_time_diff( strtotime( $item->saved_at ), time() ) ); ?> ago</span>
				</div>
				<?php if ( $item->notes ) : ?>
					<p class="lpnw-property-card__description"><em><?php echo esc_html( $item->notes ); ?></em></p>
				<?php endif; ?>
				<div class="lpnw-property-card__actions">
					<?php if ( $item->source_url ) : ?>
						<a href="<?php echo esc_url( $item->source_url ); ?>" class="lpnw-btn lpnw-btn--outline" target="_blank" rel="noopener">View Source</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
