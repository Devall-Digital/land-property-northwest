<?php
/**
 * Saved properties template.
 *
 * @package LPNW_Property_Alerts
 * @var array<object> $saved Saved property rows with property data joined.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $saved ) ) : ?>
	<div class="lpnw-empty-state lpnw-empty-state--saved" role="status">
		<p class="lpnw-empty-state__title"><?php esc_html_e( 'No saved properties yet', 'lpnw-alerts' ); ?></p>
		<p class="lpnw-empty-state__text">
			<?php esc_html_e( 'Save listings from your alert feed or the property map to build a shortlist you can revisit anytime.', 'lpnw-alerts' ); ?>
		</p>
		<p class="lpnw-empty-state__cta">
			<a class="lpnw-btn lpnw-btn--primary" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>"><?php esc_html_e( 'View alert feed', 'lpnw-alerts' ); ?></a>
			<a class="lpnw-btn lpnw-btn--outline" href="<?php echo esc_url( home_url( '/map/' ) ); ?>"><?php esc_html_e( 'Open property map', 'lpnw-alerts' ); ?></a>
		</p>
	</div>
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

			$lpnw_recency = class_exists( 'LPNW_Property' )
				? LPNW_Property::get_card_listing_recency( $item )
				: array(
					'label'     => '',
					'is_urgent' => false,
					'is_new'    => false,
				);
			$listed_label   = $lpnw_recency['label'];
			$is_new_listing = $lpnw_recency['is_new'];
			$is_urgent      = $lpnw_recency['is_urgent'];
			$listed_class   = $is_urgent
				? 'lpnw-property-card__listed lpnw-property-card__listed--urgent'
				: ( $is_new_listing
					? 'lpnw-property-card__listed lpnw-property-card__listed--recent'
					: 'lpnw-property-card__listed' );

			$price_raw   = isset( $item->price ) ? (int) $item->price : 0;
			$is_pcm      = 'rent' === strtolower( trim( (string) ( $item->application_type ?? '' ) ) );
			$source      = sanitize_key( $item->source ?? '' );
			$source_root = '' !== $source ? explode( '_', $source, 2 )[0] : '';
			$is_auction  = ( '' !== $source && str_starts_with( $source, 'auction_' ) );

			$lpnw_ctx          = class_exists( 'LPNW_Property' ) ? LPNW_Property::get_card_context( $item ) : array(
				'raw'               => array(),
				'image_url'         => '',
				'is_off_market'     => false,
				'agent_contact'     => '',
				'off_market_reason' => '',
				'contact_email'     => '',
				'contact_tel_href'  => '',
			);
			$raw               = $lpnw_ctx['raw'];
			$image_url         = $lpnw_ctx['image_url'];
			$is_off_market     = $lpnw_ctx['is_off_market'];
			$off_contact       = $lpnw_ctx['agent_contact'];
			$off_reason        = $lpnw_ctx['off_market_reason'];
			$contact_email     = $lpnw_ctx['contact_email'];
			$contact_tel_href  = $lpnw_ctx['contact_tel_href'];

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
			if ( $is_auction ) {
				$ah_suffix = preg_replace( '/^auction_/', '', $source );
				$source_badge_label = '' !== $ah_suffix ? strtoupper( $ah_suffix ) : $source_badge_label;
			}
			$type_label = trim( (string) ( $item->property_type ?? '' ) );

			$auction_date_raw = isset( $item->auction_date ) ? trim( (string) $item->auction_date ) : '';
			if ( '' === $auction_date_raw && $is_auction && is_array( $raw ) ) {
				foreach ( array( 'auction_date', 'detail_auction_date' ) as $lpnw_ad_key ) {
					if ( empty( $raw[ $lpnw_ad_key ] ) ) {
						continue;
					}
					$lpnw_ad_candidate = trim( (string) $raw[ $lpnw_ad_key ] );
					try {
						$lpnw_ad_dt = new DateTimeImmutable( $lpnw_ad_candidate, wp_timezone() );
						$auction_date_raw = $lpnw_ad_dt->format( 'Y-m-d' );
						break;
					} catch ( Exception $e ) {
						unset( $e );
						$lpnw_ad_ts = strtotime( $lpnw_ad_candidate );
						if ( false !== $lpnw_ad_ts ) {
							$auction_date_raw = wp_date( 'Y-m-d', $lpnw_ad_ts );
							break;
						}
					}
				}
			}

			$lpnw_auction_date_html  = '';
			$lpnw_auction_date_class = '';
			if ( $is_auction && '' !== $auction_date_raw ) {
				try {
					$ad_dt       = new DateTimeImmutable( $auction_date_raw, wp_timezone() );
					$auction_day = $ad_dt->format( 'Y-m-d' );
					$today_day   = current_time( 'Y-m-d' );
					if ( $auction_day < $today_day ) {
						$lpnw_auction_date_html  = __( 'Auction ended', 'lpnw-alerts' );
						$lpnw_auction_date_class = 'lpnw-auction-date lpnw-auction-date--ended';
					} else {
						$lpnw_auction_date_html = sprintf(
							/* translators: %s: auction date (DD/MM/YYYY). */
							__( 'Auction: %s', 'lpnw-alerts' ),
							wp_date( 'd/m/Y', $ad_dt->getTimestamp() )
						);
						$lpnw_auction_date_class = 'lpnw-auction-date';
					}
				} catch ( Exception $e ) {
					unset( $e );
				}
			}
			?>
			<li class="lpnw-property-list__item">
				<article class="lpnw-property-card<?php echo $is_off_market ? ' lpnw-property-card--off-market' : ''; ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
					<div class="lpnw-property-card__image">
						<?php if ( $is_urgent ) : ?>
							<span class="lpnw-new-badge lpnw-new-badge--urgent"><?php esc_html_e( 'JUST LISTED', 'lpnw-alerts' ); ?></span>
						<?php elseif ( $is_new_listing ) : ?>
							<span class="lpnw-new-badge"><?php esc_html_e( 'NEW', 'lpnw-alerts' ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->address ); ?>" loading="lazy">
						<?php else : ?>
							<div class="lpnw-property-card__image-placeholder"><?php echo esc_html( '' !== $type_label ? $type_label : __( 'Property', 'lpnw-alerts' ) ); ?></div>
						<?php endif; ?>
					</div>
					<header class="lpnw-property-card__header">
						<h3 class="lpnw-property-card__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $item->address ); ?></h3>
						<?php if ( ! empty( $item->postcode ) ) : ?>
							<?php $lpnw_pc_cap = class_exists( 'LPNW_Property' ) ? LPNW_Property::format_postcode_caption( $item ) : ''; ?>
							<p class="lpnw-property-card__postcode"><?php echo esc_html( $item->postcode ); ?><?php echo '' !== $lpnw_pc_cap ? ' — ' . esc_html( $lpnw_pc_cap ) : ''; ?></p>
						<?php endif; ?>
					</header>

					<div class="lpnw-property-card__top">
						<div class="lpnw-property-card__badges">
							<?php if ( '' !== $type_label ) : ?>
								<span class="lpnw-property-card__type-badge"><?php echo esc_html( $type_label ); ?></span>
							<?php endif; ?>
							<?php if ( $is_off_market ) : ?>
								<span class="lpnw-off-market-badge"><?php esc_html_e( 'OFF-MARKET', 'lpnw-alerts' ); ?></span>
							<?php elseif ( '' !== $source ) : ?>
								<span class="lpnw-source-badge lpnw-source-badge--<?php echo esc_attr( $source_root ); ?>"><?php echo esc_html( $source_badge_label ); ?></span>
							<?php endif; ?>
							<?php if ( $is_auction ) : ?>
								<span class="lpnw-auction-badge"><?php esc_html_e( 'Auction', 'lpnw-alerts' ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $tenure_badge_label ) : ?>
								<span class="lpnw-tenure-badge"><?php echo esc_html( $tenure_badge_label ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $price_raw > 0 ) : ?>
							<p class="lpnw-property-card__price<?php echo $is_pcm ? ' lpnw-property-card__price--pcm' : ' lpnw-property-card__price--sale'; ?><?php echo $is_auction ? ' lpnw-guide-price' : ''; ?>">
								<?php if ( $is_auction ) : ?>
									<span class="lpnw-guide-price__label"><?php esc_html_e( 'Guide:', 'lpnw-alerts' ); ?></span>
								<?php endif; ?>
								<span class="lpnw-property-card__price-currency">&pound;<?php echo esc_html( number_format_i18n( $price_raw ) ); ?></span>
								<?php if ( $is_pcm ) : ?>
									<span class="lpnw-property-card__price-suffix">pcm</span>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>
					<?php if ( '' !== $lpnw_auction_date_html ) : ?>
						<p class="<?php echo esc_attr( $lpnw_auction_date_class ); ?> lpnw-property-card__auction-date"><?php echo esc_html( $lpnw_auction_date_html ); ?></p>
					<?php endif; ?>

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

					<?php
					$lpnw_card_desc = '';
					if ( empty( $item->notes ) && ! empty( $item->description ) ) {
						$lpnw_card_desc = wp_strip_all_tags( (string) $item->description );
						if ( $is_auction && preg_match( '/^\s*auction\s+lot\b/i', $lpnw_card_desc ) ) {
							$lpnw_card_desc = preg_replace( '/^\s*auction\s+lot[^.]*\.\s*/i', '', $lpnw_card_desc );
							$lpnw_card_desc = trim( $lpnw_card_desc );
						}
						$lpnw_card_desc = wp_trim_words( $lpnw_card_desc, 50 );
					}
					?>
					<?php if ( '' !== $lpnw_card_desc ) : ?>
						<p class="lpnw-property-card__description"><?php echo esc_html( $lpnw_card_desc ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $agent_line ) : ?>
						<p class="lpnw-property-card__agent"><?php echo esc_html( sprintf( __( 'via %s', 'lpnw-alerts' ), $agent_line ) ); ?></p>
					<?php endif; ?>

					<?php if ( $is_off_market && '' !== $off_contact ) : ?>
						<p class="lpnw-property-card__contact"><?php echo esc_html( $off_contact ); ?></p>
					<?php endif; ?>

					<?php if ( $is_off_market && '' !== $off_reason ) : ?>
						<p class="lpnw-property-card__off-market-note"><?php echo esc_html( $off_reason ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $listed_label ) : ?>
						<p class="<?php echo esc_attr( $listed_class ); ?>"><?php echo esc_html( $listed_label ); ?></p>
					<?php endif; ?>

					<footer class="lpnw-property-card__actions">
						<?php if ( $is_off_market ) : ?>
							<?php if ( '' !== $contact_tel_href ) : ?>
								<a href="<?php echo esc_url( $contact_tel_href ); ?>" class="lpnw-btn lpnw-btn--amber-outline"><?php echo esc_html( $off_contact ); ?></a>
							<?php elseif ( '' !== $contact_email ) : ?>
								<a href="<?php echo esc_url( 'mailto:' . $contact_email ); ?>" class="lpnw-btn lpnw-btn--amber-outline"><?php esc_html_e( 'Contact agent', 'lpnw-alerts' ); ?></a>
							<?php else : ?>
								<span class="lpnw-btn lpnw-btn--amber-outline lpnw-btn--off-market-static"><?php esc_html_e( 'Contact agent', 'lpnw-alerts' ); ?></span>
							<?php endif; ?>
						<?php elseif ( ! empty( $item->source_url ) ) : ?>
							<a href="<?php echo esc_url( $item->source_url ); ?>" class="lpnw-btn lpnw-btn--amber-outline" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $view_label ); ?></a>
						<?php endif; ?>
						<button type="button" class="lpnw-btn lpnw-btn--outline lpnw-unsave-property" data-property-id="<?php echo esc_attr( (string) $prop_id ); ?>" aria-label="<?php echo esc_attr__( 'Remove this property from your saved list', 'lpnw-alerts' ); ?>">
							<?php esc_html_e( 'Unsave', 'lpnw-alerts' ); ?>
						</button>
					</footer>
					<?php
					$lpnw_share_url = ! empty( $item->source_url ) ? esc_url_raw( (string) $item->source_url ) : '';
					$lpnw_wa_text   = (string) $item->address;
					if ( $is_off_market && '' !== $off_contact ) {
						$lpnw_wa_text .= ' — ' . $off_contact;
					}
					if ( $price_raw > 0 ) {
						if ( $is_pcm ) {
							$lpnw_wa_text .= ' - £' . number_format( $price_raw ) . ' pcm';
						} elseif ( $is_auction ) {
							$lpnw_wa_text .= ' - ' . sprintf(
								/* translators: %s: formatted price e.g. £120,000 */
								__( 'Guide %s', 'lpnw-alerts' ),
								'£' . number_format_i18n( $price_raw )
							);
						} else {
							$lpnw_wa_text .= ' - £' . number_format_i18n( $price_raw );
						}
					}
					if ( '' !== $lpnw_share_url ) {
						$lpnw_wa_text .= ' ' . $lpnw_share_url;
					}
					$lpnw_mail_body = (string) $item->address;
					if ( $price_raw > 0 ) {
						if ( $is_pcm ) {
							$lpnw_mail_body .= "\n" . '£' . number_format( $price_raw ) . ' pcm';
						} elseif ( $is_auction ) {
							$lpnw_mail_body .= "\n" . sprintf(
								__( 'Guide %s', 'lpnw-alerts' ),
								'£' . number_format_i18n( $price_raw )
							);
						} else {
							$lpnw_mail_body .= "\n" . '£' . number_format_i18n( $price_raw );
						}
					}
					if ( '' !== $lpnw_share_url ) {
						$lpnw_mail_body .= "\n" . $lpnw_share_url;
					}
					if ( $is_off_market && '' !== $off_contact ) {
						$lpnw_mail_body .= "\n" . $off_contact;
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
