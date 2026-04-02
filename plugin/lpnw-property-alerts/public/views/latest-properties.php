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
			$prop_id  = isset( $prop->id ) ? absint( $prop->id ) : 0;
			$title_id = 'lpnw-property-card-title-' . $prop_id;

			$beds_raw  = isset( $prop->bedrooms ) ? trim( (string) $prop->bedrooms ) : '';
			$baths_raw = isset( $prop->bathrooms ) ? trim( (string) $prop->bathrooms ) : '';
			$beds      = ( '' !== $beds_raw ) ? (int) $beds_raw : null;
			$baths     = ( '' !== $baths_raw ) ? (int) $baths_raw : null;

			$tenure_badge_label = '';
			$tenure_raw         = trim( (string) ( $prop->tenure_type ?? '' ) );
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
			if ( ! empty( $prop->key_features_text ) ) {
				$key_feature_tags = array_slice(
					array_values(
						array_filter(
							array_map( 'trim', explode( '|', (string) $prop->key_features_text ) )
						)
					),
					0,
					4
				);
			}

			$agent_line = trim( (string) ( $prop->agent_name ?? '' ) );

			$listed_label = '';
			if ( ! empty( $prop->first_listed_date ) ) {
				$listed_ts = strtotime( (string) $prop->first_listed_date );
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

			$is_new_listing = false;
			if ( ! empty( $prop->first_listed_date ) ) {
				$listed_ts_new = strtotime( (string) $prop->first_listed_date );
				$is_new_listing = $listed_ts_new && ( time() - $listed_ts_new ) < 2 * DAY_IN_SECONDS;
			}
			$listed_class = $is_new_listing ? 'lpnw-property-card__listed lpnw-property-card__listed--recent' : 'lpnw-property-card__listed';

			$price_raw   = isset( $prop->price ) ? (int) $prop->price : 0;
			$is_pcm      = 'rent' === strtolower( trim( (string) ( $prop->application_type ?? '' ) ) );
			$source      = sanitize_key( $prop->source ?? '' );
			$source_root = '' !== $source ? explode( '_', $source, 2 )[0] : '';
			$is_auction  = ( '' !== $source && str_starts_with( $source, 'auction_' ) );

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
			$type_label = trim( (string) ( $prop->property_type ?? '' ) );

			$auction_date_raw = isset( $prop->auction_date ) ? trim( (string) $prop->auction_date ) : '';
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
				if ( empty( $image_url ) && ! empty( $raw['images'][0]['url'] ) ) {
					$image_url = $raw['images'][0]['url'];
				}
				if ( empty( $image_url ) && ! empty( $raw['media'][0]['url'] ) ) {
					$image_url = $raw['media'][0]['url'];
				}
				if ( empty( $image_url ) && ! empty( $raw['imageUrl'] ) ) {
					$image_url = $raw['imageUrl'];
				}
				if ( empty( $image_url ) && ! empty( $raw['photos'][0] ) ) {
					$image_url = is_string( $raw['photos'][0] ) ? $raw['photos'][0] : ( $raw['photos'][0]['url'] ?? '' );
				}
			}
			?>
			<li class="lpnw-property-list__item">
				<article class="lpnw-property-card" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
					<div class="lpnw-property-card__image">
						<?php if ( $is_new_listing ) : ?>
							<span class="lpnw-new-badge"><?php esc_html_e( 'NEW', 'lpnw-alerts' ); ?></span>
						<?php endif; ?>
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

					<?php
					$lpnw_card_desc = '';
					if ( ! empty( $prop->description ) ) {
						$lpnw_card_desc = wp_strip_all_tags( (string) $prop->description );
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

					<?php if ( '' !== $listed_label ) : ?>
						<p class="<?php echo esc_attr( $listed_class ); ?>"><?php echo esc_html( $listed_label ); ?></p>
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
					<?php
					$lpnw_share_url = ! empty( $prop->source_url ) ? esc_url_raw( (string) $prop->source_url ) : '';
					$lpnw_wa_text   = (string) $prop->address;
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
					$lpnw_mail_body = (string) $prop->address;
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
					/* translators: %s: property address */
					$lpnw_share_subject = sprintf( __( 'Property: %s', 'lpnw-alerts' ), $prop->address );
					?>
					<div class="lpnw-property-card__share">
						<a href="https://wa.me/?text=<?php echo rawurlencode( $lpnw_wa_text ); ?>" target="_blank" rel="noopener noreferrer" class="lpnw-share-link" title="<?php echo esc_attr__( 'Share on WhatsApp', 'lpnw-alerts' ); ?>"><?php esc_html_e( 'WhatsApp', 'lpnw-alerts' ); ?></a>
						<a href="mailto:?subject=<?php echo rawurlencode( $lpnw_share_subject ); ?>&body=<?php echo rawurlencode( $lpnw_mail_body ); ?>" class="lpnw-share-link" title="<?php echo esc_attr__( 'Share via email', 'lpnw-alerts' ); ?>"><?php esc_html_e( 'Email', 'lpnw-alerts' ); ?></a>
					</div>
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
