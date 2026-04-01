<?php
/**
 * Latest properties shortcode template.
 *
 * @package LPNW_Property_Alerts
 * @var array<object> $properties
 * @var array<string, mixed> $filters Shortcode filters (source, postcode_prefix).
 */

defined( 'ABSPATH' ) || exit;

$lpnw_show_latest_cta = isset( $lpnw_show_latest_cta ) ? (bool) $lpnw_show_latest_cta : true;

if ( empty( $properties ) ) : ?>
	<p><?php esc_html_e( 'No properties found yet. Data feeds are running and will populate shortly.', 'lpnw-alerts' ); ?></p>
<?php else : ?>
	<ul class="lpnw-property-list lpnw-property-list--grid">
		<?php foreach ( $properties as $prop ) : ?>
			<?php
			$prop_id    = isset( $prop->id ) ? absint( $prop->id ) : 0;
			$title_id   = 'lpnw-property-card-title-' . $prop_id;
			$desc_plain = wp_strip_all_tags( (string) ( $prop->description ?? '' ) );
			$beds       = null;
			$baths      = null;
			if ( preg_match( '/\b(\d+)\s*(?:bed(?:room)?s?|br)\b/i', $desc_plain, $bed_m ) ) {
				$beds = (int) $bed_m[1];
			}
			if ( preg_match( '/\b(\d+)\s*bath(?:room)?s?\b/i', $desc_plain, $bath_m ) ) {
				$baths = (int) $bath_m[1];
			}
			$price_raw   = isset( $prop->price ) ? (int) $prop->price : 0;
			$is_pcm      = 'rent' === strtolower( trim( (string) ( $prop->application_type ?? '' ) ) );
			$source      = sanitize_key( $prop->source ?? '' );
			$source_root = '' !== $source ? explode( '_', $source, 2 )[0] : '';

			$view_label = __( 'View source', 'lpnw-alerts' );
			if ( 'rightmove' === $source ) {
				$view_label = __( 'View on Rightmove', 'lpnw-alerts' );
			} elseif ( 'zoopla' === $source ) {
				$view_label = __( 'View on Zoopla', 'lpnw-alerts' );
			} elseif ( 'onthemarket' === $source ) {
				$view_label = __( 'View on OnTheMarket', 'lpnw-alerts' );
			} elseif ( '' !== $source && str_starts_with( $source, 'auction_' ) ) {
				$view_label = __( 'View auction listing', 'lpnw-alerts' );
			} elseif ( 'planning' === $source ) {
				$view_label = __( 'View planning application', 'lpnw-alerts' );
			} elseif ( 'epc' === $source ) {
				$view_label = __( 'View EPC record', 'lpnw-alerts' );
			} elseif ( 'landregistry' === $source ) {
				$view_label = __( 'View Land Registry record', 'lpnw-alerts' );
			}

			$source_badge_label = ucwords( str_replace( '_', ' ', $source ) );
			$type_label         = trim( (string) ( $prop->property_type ?? '' ) );

			$raw       = json_decode( (string) ( $prop->raw_data ?? '' ), true );
			$image_url = '';
			if ( is_array( $raw ) ) {
				if ( ! empty( $raw['propertyImages']['images'][0]['srcUrl'] ) ) {
					$image_url = $raw['propertyImages']['images'][0]['srcUrl'];
				} elseif ( ! empty( $raw['propertyImages']['mainImageSrc'] ) ) {
					$image_url = $raw['propertyImages']['mainImageSrc'];
				} elseif ( ! empty( $raw['images'][0]['srcUrl'] ) ) {
					$image_url = $raw['images'][0]['srcUrl'];
				}
			}
			?>
			<li class="lpnw-property-list__item">
				<article class="lpnw-property-card" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
					<div class="lpnw-property-card__image">
						<?php if ( '' !== $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $prop->address ); ?>" loading="lazy">
						<?php else : ?>
							<div class="lpnw-property-card__image-placeholder"><?php echo esc_html( '' !== $type_label ? $type_label : __( 'Property', 'lpnw-alerts' ) ); ?></div>
						<?php endif; ?>
					</div>
					<header class="lpnw-property-card__header">
						<h3 class="lpnw-property-card__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $prop->address ); ?></h3>
						<?php if ( ! empty( $prop->postcode ) ) : ?>
							<p class="lpnw-property-card__postcode"><?php echo esc_html( $prop->postcode ); ?></p>
						<?php endif; ?>
					</header>

					<div class="lpnw-property-card__top">
						<div class="lpnw-property-card__badges">
							<?php if ( '' !== $type_label ) : ?>
								<span class="lpnw-property-card__type-badge"><?php echo esc_html( $type_label ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $source ) : ?>
								<span class="lpnw-source-badge lpnw-source-badge--<?php echo esc_attr( $source_root ); ?>"><?php echo esc_html( $source_badge_label ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $price_raw > 0 ) : ?>
							<p class="lpnw-property-card__price<?php echo $is_pcm ? ' lpnw-property-card__price--pcm' : ' lpnw-property-card__price--sale'; ?>">
								<span class="lpnw-property-card__price-currency">&pound;<?php echo esc_html( number_format_i18n( $price_raw ) ); ?></span>
								<?php if ( $is_pcm ) : ?>
									<span class="lpnw-property-card__price-suffix">pcm</span>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>

					<?php if ( null !== $beds || null !== $baths ) : ?>
						<p class="lpnw-property-card__rooms">
							<?php
							$room_parts = array();
							if ( null !== $beds ) {
								$room_parts[] = sprintf(
									/* translators: %d: bedroom count */
									_n( '%d bed', '%d beds', $beds, 'lpnw-alerts' ),
									$beds
								);
							}
							if ( null !== $baths ) {
								$room_parts[] = sprintf(
									/* translators: %d: bathroom count */
									_n( '%d bath', '%d baths', $baths, 'lpnw-alerts' ),
									$baths
								);
							}
							echo esc_html( implode( ' · ', $room_parts ) );
							?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $prop->description ) ) : ?>
						<p class="lpnw-property-card__description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $prop->description ), 50 ) ); ?></p>
					<?php endif; ?>

					<footer class="lpnw-property-card__actions">
						<?php if ( ! empty( $prop->source_url ) ) : ?>
							<a href="<?php echo esc_url( $prop->source_url ); ?>" class="lpnw-btn lpnw-btn--amber-outline" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $view_label ); ?></a>
						<?php endif; ?>
						<?php if ( is_user_logged_in() ) : ?>
							<button type="button" class="lpnw-btn lpnw-btn--bookmark lpnw-save-property" data-property-id="<?php echo esc_attr( (string) $prop_id ); ?>" aria-label="<?php echo esc_attr__( 'Save this property to your list', 'lpnw-alerts' ); ?>">
								<span class="lpnw-btn--bookmark__icon" aria-hidden="true"></span>
								<span class="lpnw-btn--bookmark__text"><?php esc_html_e( 'Save', 'lpnw-alerts' ); ?></span>
							</button>
						<?php endif; ?>
					</footer>
				</article>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
	if ( $lpnw_show_latest_cta && ! empty( $properties ) && ! is_user_logged_in() ) {
		global $wpdb;

		$shown = count( $properties );
		$table = $wpdb->prefix . 'lpnw_properties';
		$total = 0;

		if ( ! empty( $filters['postcode_prefix'] ) ) {
			$where = array( '1=1' );
			$args  = array();
			if ( ! empty( $filters['source'] ) ) {
				$where[] = 'source = %s';
				$args[]  = sanitize_text_field( $filters['source'] );
			}
			LPNW_Property::append_postcode_prefix_sql( 'UPPER(TRIM(postcode))', $filters['postcode_prefix'], $where, $args );
			$where_clause = implode( ' AND ', $where );
			if ( ! empty( $args ) ) {
				$total = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$args
				) );
			} else {
				$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		} else {
			$pc      = 'UPPER(TRIM(postcode))';
			$bucket  = "CASE
				WHEN {$pc} LIKE 'BB%' THEN 'BB'
				WHEN {$pc} LIKE 'BL%' THEN 'BL'
				WHEN {$pc} LIKE 'CA%' THEN 'CA'
				WHEN {$pc} LIKE 'CH%' THEN 'CH'
				WHEN {$pc} LIKE 'CW%' THEN 'CW'
				WHEN {$pc} LIKE 'FY%' THEN 'FY'
				WHEN {$pc} LIKE 'LA%' THEN 'LA'
				WHEN {$pc} LIKE 'OL%' THEN 'OL'
				WHEN {$pc} LIKE 'PR%' THEN 'PR'
				WHEN {$pc} LIKE 'SK%' THEN 'SK'
				WHEN {$pc} LIKE 'WA%' THEN 'WA'
				WHEN {$pc} LIKE 'WN%' THEN 'WN'
				WHEN {$pc} REGEXP '^M[0-9]' THEN 'M'
				WHEN {$pc} REGEXP '^L[0-9]' THEN 'L'
				ELSE ''
			END";
			$sql = "SELECT COUNT(*) FROM {$table} WHERE TRIM(postcode) <> '' AND ({$bucket}) <> ''";
			if ( ! empty( $filters['source'] ) ) {
				$sql  .= ' AND source = %s';
				$total = (int) $wpdb->get_var( $wpdb->prepare( $sql, sanitize_text_field( $filters['source'] ) ) );
			} else {
				$total = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		if ( $total < $shown ) {
			$total = $shown;
		}

		$signup_url = wp_registration_url();
		?>
		<aside class="lpnw-latest-properties-signup-cta lpnw-cta-banner" role="complementary" aria-labelledby="lpnw-latest-properties-cta-heading">
			<div class="lpnw-cta-banner__inner">
				<p class="lpnw-cta-banner__text" id="lpnw-latest-properties-cta-heading">
					<?php
					printf(
						/* translators: 1: number of properties shown, 2: total in database for this view. */
						esc_html__( 'Showing %1$s of %2$s Northwest properties. Sign up to set your filters and get instant alerts.', 'lpnw-alerts' ),
						esc_html( number_format_i18n( $shown ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</p>
				<div class="lpnw-cta-banner__actions">
					<a class="lpnw-btn lpnw-btn--primary" href="<?php echo esc_url( $signup_url ); ?>"><?php esc_html_e( 'Start free', 'lpnw-alerts' ); ?></a>
				</div>
			</div>
		</aside>
		<?php
	}
	?>
<?php endif; ?>
