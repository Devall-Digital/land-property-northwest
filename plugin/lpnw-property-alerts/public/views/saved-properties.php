<?php
/**
 * Saved properties template.
 *
 * @package LPNW_Property_Alerts
 * @var array<object> $saved Saved property rows with property data joined.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $saved ) ) : ?>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: alerts URL, 2: map URL */
				__( 'You have not saved any properties yet. Browse your <a href="%1$s">alerts</a> or the <a href="%2$s">property map</a> to find and save properties.', 'lpnw-alerts' ),
				esc_url( home_url( '/dashboard/' ) ),
				esc_url( home_url( '/map/' ) )
			),
			array(
				'a' => array(
					'href' => array(),
				),
			)
		);
		?>
	</p>
<?php else : ?>
	<ul class="lpnw-property-list lpnw-property-list--grid" id="lpnw-saved-properties-list">
		<?php foreach ( $saved as $item ) : ?>
			<?php
			$prop_id  = isset( $item->property_id ) ? absint( $item->property_id ) : 0;
			$title_id = 'lpnw-saved-card-title-' . $prop_id;

			$beds_raw  = isset( $item->bedrooms ) ? trim( (string) $item->bedrooms ) : '';
			$baths_raw = isset( $item->bathrooms ) ? trim( (string) $item->bathrooms ) : '';
			$beds      = ( '' !== $beds_raw ) ? (int) $beds_raw : null;
			$baths     = ( '' !== $baths_raw ) ? (int) $baths_raw : null;

			$tenure_badge_label = '';
			$tenure_raw         = trim( (string) ( $item->tenure_type ?? '' ) );
			if ( '' !== $tenure_raw ) {
				$tlow = strtolower( $tenure_raw );
				if ( false !== strpos( $tlow, 'leasehold' ) ) {
					$tenure_badge_label = __( 'Leasehold', 'lpnw-alerts' );
				} elseif ( false !== strpos( $tlow, 'freehold' ) ) {
					$tenure_badge_label = __( 'Freehold', 'lpnw-alerts' );
				} else {
					$tenure_badge_label = ucwords( str_replace( '_', ' ', $tenure_raw ) );
				}
			}

			$key_feature_tags = array();
			if ( ! empty( $item->key_features_text ) ) {
				$key_feature_tags = array_slice(
					array_values(
						array_filter(
							array_map( 'trim', explode( '|', (string) $item->key_features_text ) )
						)
					),
					0,
					4
				);
			}

			$agent_line = trim( (string) ( $item->agent_name ?? '' ) );

			$listed_label = '';
			if ( ! empty( $item->first_listed_date ) ) {
				$listed_ts = strtotime( (string) $item->first_listed_date );
				if ( false !== $listed_ts ) {
					$listed_day = wp_date( 'Y-m-d', $listed_ts );
					$today_day  = current_time( 'Y-m-d' );
					$tz         = wp_timezone();
					$listed_dt  = date_create_immutable( $listed_day, $tz );
					$today_dt   = date_create_immutable( $today_day, $tz );
					if ( $listed_dt && $today_dt && $listed_dt <= $today_dt ) {
						if ( $listed_day === $today_day ) {
							$listed_label = __( 'Listed today', 'lpnw-alerts' );
						} else {
							$cal_days = (int) $listed_dt->diff( $today_dt )->days;
							if ( 1 === $cal_days ) {
								$listed_label = __( 'Listed yesterday', 'lpnw-alerts' );
							} elseif ( $cal_days > 1 ) {
								$listed_label = sprintf(
									/* translators: %d: number of days since listing */
									_n( 'Listed %d day ago', 'Listed %d days ago', $cal_days, 'lpnw-alerts' ),
									$cal_days
								);
							}
						}
					}
				}
			}
			$price_raw   = isset( $item->price ) ? (int) $item->price : 0;
			$is_pcm      = 'rent' === strtolower( trim( (string) ( $item->application_type ?? '' ) ) );
			$source      = sanitize_key( $item->source ?? '' );
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
			$type_label         = trim( (string) ( $item->property_type ?? '' ) );

			$raw       = json_decode( (string) ( isset( $item->raw_data ) ? $item->raw_data : '' ), true );
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
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->address ); ?>" loading="lazy">
						<?php else : ?>
							<div class="lpnw-property-card__image-placeholder"><?php echo esc_html( '' !== $type_label ? $type_label : __( 'Property', 'lpnw-alerts' ) ); ?></div>
						<?php endif; ?>
					</div>
					<header class="lpnw-property-card__header">
						<h3 class="lpnw-property-card__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $item->address ); ?></h3>
						<?php if ( ! empty( $item->postcode ) ) : ?>
							<p class="lpnw-property-card__postcode"><?php echo esc_html( $item->postcode ); ?></p>
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
							<?php if ( '' !== $tenure_badge_label ) : ?>
								<span class="lpnw-tenure-badge"><?php echo esc_html( $tenure_badge_label ); ?></span>
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
							echo esc_html( implode( ', ', $room_parts ) );
							?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $key_feature_tags ) ) : ?>
						<div class="lpnw-property-card__features" role="list">
							<?php foreach ( $key_feature_tags as $lpnw_feature ) : ?>
								<span class="lpnw-feature-tag" role="listitem"><?php echo esc_html( $lpnw_feature ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<p class="lpnw-property-card__saved-meta">
						<?php
						printf(
							/* translators: %s: human-readable time difference */
							esc_html__( 'Saved %s ago', 'lpnw-alerts' ),
							esc_html( human_time_diff( strtotime( $item->saved_at ), time() ) )
						);
						?>
					</p>

					<?php if ( ! empty( $item->notes ) ) : ?>
						<p class="lpnw-property-card__description lpnw-property-card__description--notes"><em><?php echo esc_html( $item->notes ); ?></em></p>
					<?php endif; ?>

					<?php if ( empty( $item->notes ) && ! empty( $item->description ) ) : ?>
						<p class="lpnw-property-card__description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $item->description ), 50 ) ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $agent_line ) : ?>
						<p class="lpnw-property-card__agent"><?php echo esc_html( sprintf( __( 'via %s', 'lpnw-alerts' ), $agent_line ) ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $listed_label ) : ?>
						<p class="lpnw-property-card__listed"><?php echo esc_html( $listed_label ); ?></p>
					<?php endif; ?>

					<footer class="lpnw-property-card__actions">
						<?php if ( ! empty( $item->source_url ) ) : ?>
							<a href="<?php echo esc_url( $item->source_url ); ?>" class="lpnw-btn lpnw-btn--amber-outline" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $view_label ); ?></a>
						<?php endif; ?>
						<button type="button" class="lpnw-btn lpnw-btn--outline lpnw-unsave-property" data-property-id="<?php echo esc_attr( (string) $prop_id ); ?>" aria-label="<?php echo esc_attr__( 'Remove this property from your saved list', 'lpnw-alerts' ); ?>">
							<?php esc_html_e( 'Unsave', 'lpnw-alerts' ); ?>
						</button>
					</footer>
					<?php
					$lpnw_share_url = ! empty( $item->source_url ) ? esc_url_raw( (string) $item->source_url ) : '';
					$lpnw_wa_text   = (string) $item->address;
					if ( $price_raw > 0 ) {
						$lpnw_wa_text .= ' - ' . ( $is_pcm ? '£' . number_format( $price_raw ) . ' pcm' : '£' . number_format_i18n( $price_raw ) );
					}
					if ( '' !== $lpnw_share_url ) {
						$lpnw_wa_text .= ' ' . $lpnw_share_url;
					}
					$lpnw_mail_body = (string) $item->address;
					if ( $price_raw > 0 ) {
						$lpnw_mail_body .= "\n" . ( $is_pcm ? '£' . number_format( $price_raw ) . ' pcm' : '£' . number_format_i18n( $price_raw ) );
					}
					if ( '' !== $lpnw_share_url ) {
						$lpnw_mail_body .= "\n" . $lpnw_share_url;
					}
					/* translators: %s: property address */
					$lpnw_share_subject = sprintf( __( 'Property: %s', 'lpnw-alerts' ), $item->address );
					?>
					<div class="lpnw-property-card__share">
						<a href="https://wa.me/?text=<?php echo rawurlencode( $lpnw_wa_text ); ?>" target="_blank" rel="noopener noreferrer" class="lpnw-share-link" title="<?php echo esc_attr__( 'Share on WhatsApp', 'lpnw-alerts' ); ?>"><?php esc_html_e( 'WhatsApp', 'lpnw-alerts' ); ?></a>
						<a href="mailto:?subject=<?php echo rawurlencode( $lpnw_share_subject ); ?>&body=<?php echo rawurlencode( $lpnw_mail_body ); ?>" class="lpnw-share-link" title="<?php echo esc_attr__( 'Share via email', 'lpnw-alerts' ); ?>"><?php esc_html_e( 'Email', 'lpnw-alerts' ); ?></a>
					</div>
				</article>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
