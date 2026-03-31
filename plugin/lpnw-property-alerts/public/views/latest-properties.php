<?php
/**
 * Latest properties shortcode template.
 *
 * @package LPNW_Property_Alerts
 * @var array<object> $properties
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $properties ) ) : ?>
	<p><?php esc_html_e( 'No properties found yet. Data feeds are running and will populate shortly.', 'lpnw-alerts' ); ?></p>
<?php else : ?>
	<div class="lpnw-property-list">
		<?php foreach ( $properties as $prop ) : ?>
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
					<?php if ( is_user_logged_in() ) : ?>
						<button class="lpnw-btn lpnw-btn--outline lpnw-save-property" data-property-id="<?php echo esc_attr( $prop->id ); ?>">Save</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
