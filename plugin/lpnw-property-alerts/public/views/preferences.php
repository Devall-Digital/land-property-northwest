<?php
/**
 * Alert preferences form template.
 *
 * @package LPNW_Property_Alerts
 * @var object|null $prefs Current preferences.
 * @var string      $tier  Subscription tier.
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() && isset( $_GET['lpnw_reset_prefs'], $_GET['_wpnonce'] ) && '1' === $_GET['lpnw_reset_prefs'] ) {
	$lpnw_reset_uid = get_current_user_id();
	if ( $lpnw_reset_uid && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'lpnw_reset_prefs' ) && class_exists( 'LPNW_Subscriber' ) ) {
		LPNW_Subscriber::save_preferences(
			$lpnw_reset_uid,
			array(
				'areas'              => array(),
				'property_types'     => array(),
				'alert_types'        => array(),
				'listing_channels'   => array(),
				'tenure_preferences' => array(),
				'required_features'  => array(),
				'frequency'          => 'weekly',
				'is_active'          => 1,
			)
		);
		wp_safe_redirect( home_url( '/preferences/' ) );
		exit;
	}
}

$areas          = $prefs ? $prefs->areas : array();
$areas          = is_array( $areas ) ? $areas : array();
if ( isset( $_GET['lpnw_area'] ) ) {
	$hint = strtoupper( sanitize_text_field( wp_unslash( $_GET['lpnw_area'] ) ) );
	if ( class_exists( 'LPNW_NW_Postcodes' ) && LPNW_NW_Postcodes::is_valid_area_or_district( $hint ) && ! in_array( $hint, $areas, true ) ) {
		$areas[] = $hint;
	} elseif ( in_array( $hint, LPNW_NW_POSTCODES, true ) && ! in_array( $hint, $areas, true ) ) {
		$areas[] = $hint;
	}
}
$property_types = $prefs ? $prefs->property_types : array();
$property_types = is_array( $property_types ) ? $property_types : array();
$alert_types    = $prefs ? $prefs->alert_types : array();
$alert_types    = is_array( $alert_types ) ? $alert_types : array();
$frequency      = $prefs ? $prefs->frequency : 'daily';
$min_price      = $prefs ? $prefs->min_price : '';
$max_price      = $prefs ? $prefs->max_price : '';

$min_bedrooms_display = '';
$max_bedrooms_display = '';
if ( $prefs ) {
	if ( null !== $prefs->min_bedrooms && '' !== $prefs->min_bedrooms ) {
		$min_bedrooms_display = (string) (int) $prefs->min_bedrooms;
	}
	if ( null !== $prefs->max_bedrooms && '' !== $prefs->max_bedrooms ) {
		$max_bedrooms_display = (string) (int) $prefs->max_bedrooms;
	}
}
$listing_channels   = ( $prefs && is_array( $prefs->listing_channels ) ) ? $prefs->listing_channels : array();
$tenure_preferences = ( $prefs && is_array( $prefs->tenure_preferences ) ) ? $prefs->tenure_preferences : array();
$required_features  = ( $prefs && is_array( $prefs->required_features ) ) ? $prefs->required_features : array();
$lpnw_alerts_active = ! $prefs || ! empty( $prefs->is_active );

$lpnw_coverage_count     = null;
$lpnw_coverage_sample_cap = 2500;
if ( class_exists( 'LPNW_Matcher' ) && class_exists( 'LPNW_Property' ) ) {
	global $wpdb;
	$since_7d = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
	$prop_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}lpnw_properties WHERE created_at >= %s ORDER BY id DESC LIMIT %d",
			$since_7d,
			$lpnw_coverage_sample_cap
		)
	);
	$min_bed_m = null;
	$max_bed_m = null;
	if ( $prefs ) {
		if ( null !== $prefs->min_bedrooms && '' !== $prefs->min_bedrooms ) {
			$min_bed_m = (int) $prefs->min_bedrooms;
		}
		if ( null !== $prefs->max_bedrooms && '' !== $prefs->max_bedrooms ) {
			$max_bed_m = (int) $prefs->max_bedrooms;
		}
	}
	$min_p_m = ( $prefs && isset( $prefs->min_price ) && null !== $prefs->min_price && '' !== $prefs->min_price ) ? (int) $prefs->min_price : null;
	$max_p_m = ( $prefs && isset( $prefs->max_price ) && null !== $prefs->max_price && '' !== $prefs->max_price ) ? (int) $prefs->max_price : null;

	$sub_row = (object) array(
		'user_id'             => get_current_user_id(),
		'areas'               => wp_json_encode( $areas ),
		'property_types'      => wp_json_encode( is_array( $property_types ) ? $property_types : array() ),
		'alert_types'         => wp_json_encode( is_array( $alert_types ) ? $alert_types : array() ),
		'min_price'           => $min_p_m,
		'max_price'           => $max_p_m,
		'listing_channels'    => wp_json_encode( $listing_channels ),
		'tenure_preferences'  => wp_json_encode( $tenure_preferences ),
		'required_features'   => wp_json_encode( $required_features ),
		'min_bedrooms'        => $min_bed_m,
		'max_bedrooms'        => $max_bed_m,
	);

	$matcher = new LPNW_Matcher();
	$c       = 0;
	if ( is_array( $prop_ids ) ) {
		foreach ( $prop_ids as $pid ) {
			$property = LPNW_Property::get( (int) $pid );
			if ( ! $property ) {
				continue;
			}
			if ( $matcher->property_matches_subscriber( $property, $sub_row ) ) {
				++$c;
			}
		}
	}
	$lpnw_coverage_count = $c;
}

$listing_channel_options = array(
	'sale' => __( 'For sale', 'lpnw-alerts' ),
	'rent' => __( 'To let', 'lpnw-alerts' ),
);

$tenure_options = array(
	'freehold'           => __( 'Freehold', 'lpnw-alerts' ),
	'leasehold'          => __( 'Leasehold', 'lpnw-alerts' ),
	'share_of_freehold'  => __( 'Share of freehold', 'lpnw-alerts' ),
);

$feature_options = array(
	'garden'     => __( 'Garden', 'lpnw-alerts' ),
	'parking'    => __( 'Parking', 'lpnw-alerts' ),
	'garage'     => __( 'Garage', 'lpnw-alerts' ),
	'new_build'  => __( 'New Build', 'lpnw-alerts' ),
	'chain_free' => __( 'Chain Free', 'lpnw-alerts' ),
);

$lpnw_nw_area_labels = LPNW_Property::get_nw_area_labels();
$lpnw_districts_by_bucket = class_exists( 'LPNW_NW_Postcodes' ) ? LPNW_NW_Postcodes::get_districts_by_area() : array();

$available_types = array(
	'Detached'         => 'Detached',
	'Semi-detached'    => 'Semi-detached',
	'Terraced'         => 'Terraced',
	'Flat/Maisonette'  => 'Flat/Maisonette',
	'Auction lot'      => 'Auction Lot',
	'Other'            => 'Other/Land',
);

$available_alert_types = array(
	'listing'    => 'New Property Listings (Rightmove and OnTheMarket)',
	'planning'   => 'Planning Applications',
	'epc'        => 'EPC / Property Activity',
	'price'      => 'Price Paid / Transactions',
	'auction'    => 'Auction Lots',
	'off_market' => 'Off-Market Deals (VIP exclusive)',
);

$reset_url = wp_nonce_url( add_query_arg( 'lpnw_reset_prefs', '1', home_url( '/preferences/' ) ), 'lpnw_reset_prefs' );
?>

<div class="lpnw-preferences-form lpnw-subscriber-area">
	<div class="lpnw-preferences-coverage" role="region" aria-labelledby="lpnw-prefs-coverage-heading">
		<h2 class="lpnw-preferences-coverage__title" id="lpnw-prefs-coverage-heading"><?php esc_html_e( 'Coverage preview', 'lpnw-alerts' ); ?></h2>
		<?php if ( null !== $lpnw_coverage_count ) : ?>
			<p class="lpnw-preferences-coverage__stat">
				<?php esc_html_e( 'Based on your current filters, you would match approximately', 'lpnw-alerts' ); ?>
				<strong><?php echo esc_html( number_format_i18n( $lpnw_coverage_count ) ); ?></strong>
				<?php esc_html_e( 'properties from the last 7 days.', 'lpnw-alerts' ); ?>
			</p>
			<p class="lpnw-preferences-coverage__note">
				<?php
				printf(
					/* translators: %d: max properties scanned */
					esc_html__( 'Estimated using the same rules as live alerts, scanning up to %d of the newest records in our database.', 'lpnw-alerts' ),
					(int) $lpnw_coverage_sample_cap
				);
				?>
			</p>
		<?php else : ?>
			<p class="lpnw-preferences-coverage__stat"><?php esc_html_e( 'Coverage preview is unavailable. Please try again later.', 'lpnw-alerts' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="lpnw-preferences-form__header-row">
		<p class="lpnw-preferences-form__intro"><?php esc_html_e( 'Set your criteria below and we will match new properties as they come in.', 'lpnw-alerts' ); ?></p>
		<p class="lpnw-preferences-form__reset-wrap">
			<a href="<?php echo esc_url( $reset_url ); ?>" class="lpnw-preferences-form__reset"><?php esc_html_e( 'Reset to defaults', 'lpnw-alerts' ); ?></a>
		</p>
	</div>

	<form id="lpnw-preferences-form" class="lpnw-preferences-form__inner">

		<details class="lpnw-prefs-section" open>
			<summary class="lpnw-prefs-section__summary">
				<span class="lpnw-prefs-section__title"><?php esc_html_e( 'Areas', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-prefs-section__hint"><?php esc_html_e( 'Postcode regions in the Northwest', 'lpnw-alerts' ); ?></span>
			</summary>
			<div class="lpnw-prefs-section__body">
				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-areas">
					<legend class="lpnw-sr-only"><?php esc_html_e( 'Areas', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-areas">
						<?php esc_html_e( 'Tick a whole postcode area (for example OL) or open it and pick individual districts (for example OL2 for Shaw and Royton, OL9 for Chadderton). The same rules apply to every feed: we match the property postcode against what you select.', 'lpnw-alerts' ); ?>
					</p>
					<div class="lpnw-area-bulk" role="group" aria-label="<?php esc_attr_e( 'Bulk area selection', 'lpnw-alerts' ); ?>">
						<label class="lpnw-checkbox-group__item lpnw-checkbox-group__item--primary lpnw-area-bulk__all">
							<input type="checkbox" id="lpnw-all-nw" class="lpnw-all-nw-toggle"
								<?php
								$lpnw_all_bucket_codes = LPNW_NW_POSTCODES;
								$all_selected            = ! empty( $areas ) && empty( array_diff( $lpnw_all_bucket_codes, $areas ) );
								checked( $all_selected );
								?>
							>
							<span><?php esc_html_e( 'All NW (whole areas only)', 'lpnw-alerts' ); ?></span>
						</label>
						<p class="lpnw-select-all-label">
							<button type="button" class="lpnw-btn lpnw-btn--outline lpnw-area-bulk__btn" id="lpnw-areas-select-all">
								<?php esc_html_e( 'Select all', 'lpnw-alerts' ); ?>
							</button>
							<button type="button" class="lpnw-btn lpnw-btn--outline lpnw-area-bulk__btn" id="lpnw-areas-deselect-all">
								<?php esc_html_e( 'Deselect all', 'lpnw-alerts' ); ?>
							</button>
						</p>
					</div>
					<div class="lpnw-area-buckets" id="lpnw-areas-root">
						<?php foreach ( LPNW_NW_POSTCODES as $bucket_code ) : ?>
							<?php
							$bucket_label = isset( $lpnw_nw_area_labels[ $bucket_code ] ) ? $lpnw_nw_area_labels[ $bucket_code ] : $bucket_code;
							$districts    = isset( $lpnw_districts_by_bucket[ $bucket_code ] ) ? $lpnw_districts_by_bucket[ $bucket_code ] : array();
							?>
							<details class="lpnw-area-bucket" <?php echo ! empty( $districts ) ? 'open' : ''; ?>>
								<summary class="lpnw-area-bucket__summary">
									<label class="lpnw-area-bucket__whole">
										<input type="checkbox" name="areas[]" class="lpnw-area-bucket-cb" value="<?php echo esc_attr( $bucket_code ); ?>"
											data-lpnw-bucket="<?php echo esc_attr( $bucket_code ); ?>"
											<?php checked( in_array( $bucket_code, $areas, true ) ); ?>>
										<span><?php echo esc_html( sprintf( '%s (%s)', $bucket_label, $bucket_code ) ); ?></span>
									</label>
								</summary>
								<?php if ( ! empty( $districts ) ) : ?>
									<div class="lpnw-checkbox-group lpnw-area-bucket__districts" data-lpnw-bucket-districts="<?php echo esc_attr( $bucket_code ); ?>">
										<?php foreach ( $districts as $dist ) : ?>
											<?php
											$lpnw_dist_label = class_exists( 'LPNW_NW_Postcodes' )
												? LPNW_NW_Postcodes::get_area_or_district_label( $dist )
												: $dist;
											?>
											<label class="lpnw-checkbox-group__item">
												<input type="checkbox" name="areas[]" class="lpnw-area-district-cb" value="<?php echo esc_attr( $dist ); ?>"
													data-lpnw-parent-bucket="<?php echo esc_attr( $bucket_code ); ?>"
													<?php checked( in_array( $dist, $areas, true ) ); ?>>
												<span><?php echo esc_html( $dist . ( '' !== $lpnw_dist_label ? ' — ' . $lpnw_dist_label : '' ) ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</details>
						<?php endforeach; ?>
					</div>
				</fieldset>
			</div>
		</details>

		<details class="lpnw-prefs-section" open>
			<summary class="lpnw-prefs-section__summary">
				<span class="lpnw-prefs-section__title"><?php esc_html_e( 'Budget and size', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-prefs-section__hint"><?php esc_html_e( 'Price range and bedrooms', 'lpnw-alerts' ); ?></span>
			</summary>
			<div class="lpnw-prefs-section__body">
				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-price">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Price range (GBP)', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-price">
						Optional caps so you are not alerted to properties far outside your budget. Leave blank if you do not want a floor or ceiling.
					</p>
					<div class="lpnw-price-range">
						<div class="lpnw-price-range__field">
							<label for="lpnw-min-price"><?php esc_html_e( 'Minimum', 'lpnw-alerts' ); ?></label>
							<input type="number" id="lpnw-min-price" name="min_price" value="<?php echo esc_attr( $min_price ); ?>" min="0" step="1000" placeholder="<?php esc_attr_e( 'No minimum', 'lpnw-alerts' ); ?>">
						</div>
						<div class="lpnw-price-range__field">
							<label for="lpnw-max-price"><?php esc_html_e( 'Maximum', 'lpnw-alerts' ); ?></label>
							<input type="number" id="lpnw-max-price" name="max_price" value="<?php echo esc_attr( $max_price ); ?>" min="0" step="1000" placeholder="<?php esc_attr_e( 'No maximum', 'lpnw-alerts' ); ?>">
						</div>
					</div>
				</fieldset>

				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-bedrooms">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Bedrooms', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-bedrooms">
						<?php esc_html_e( 'Filter by number of bedrooms. Leave blank to see all sizes.', 'lpnw-alerts' ); ?>
					</p>
					<div class="lpnw-price-range">
						<div class="lpnw-price-range__field">
							<label for="lpnw-min-bedrooms"><?php esc_html_e( 'Min bedrooms', 'lpnw-alerts' ); ?></label>
							<input type="number" id="lpnw-min-bedrooms" name="min_bedrooms" value="<?php echo esc_attr( $min_bedrooms_display ); ?>" min="0" max="10" step="1" placeholder="<?php esc_attr_e( 'No minimum', 'lpnw-alerts' ); ?>">
						</div>
						<div class="lpnw-price-range__field">
							<label for="lpnw-max-bedrooms"><?php echo esc_html_e( 'Max bedrooms', 'lpnw-alerts' ); ?></label>
							<input type="number" id="lpnw-max-bedrooms" name="max_bedrooms" value="<?php echo esc_attr( $max_bedrooms_display ); ?>" min="0" max="10" step="1" placeholder="<?php esc_attr_e( 'No maximum', 'lpnw-alerts' ); ?>">
						</div>
					</div>
				</fieldset>
			</div>
		</details>

		<details class="lpnw-prefs-section">
			<summary class="lpnw-prefs-section__summary">
				<span class="lpnw-prefs-section__title"><?php esc_html_e( 'Property details', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-prefs-section__hint"><?php esc_html_e( 'Type, tenure, listing channel, features', 'lpnw-alerts' ); ?></span>
			</summary>
			<div class="lpnw-prefs-section__body">
				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-types">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Property types', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-types">
						Tick the kinds of property you want to hear about. If you leave this empty, we do not filter by type and you may see a wider mix of alerts.
					</p>
					<div class="lpnw-checkbox-group">
						<?php foreach ( $available_types as $value => $label ) : ?>
							<label class="lpnw-checkbox-group__item">
								<input type="checkbox" name="property_types[]" value="<?php echo esc_attr( $value ); ?>"
									<?php checked( in_array( $value, $property_types, true ) ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-listing-channel">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Listing channel', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-listing-channel">
						<?php esc_html_e( 'Choose which listing types you want alerts for. Leave both unchecked to see everything.', 'lpnw-alerts' ); ?>
					</p>
					<div class="lpnw-checkbox-group">
						<?php foreach ( $listing_channel_options as $value => $label ) : ?>
							<label class="lpnw-checkbox-group__item">
								<input type="checkbox" name="listing_channels[]" value="<?php echo esc_attr( $value ); ?>"
									<?php checked( in_array( $value, $listing_channels, true ) ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-tenure">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Tenure', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-tenure">
						<?php esc_html_e( 'Filter by tenure type. Leave unchecked to see all tenure types.', 'lpnw-alerts' ); ?>
					</p>
					<div class="lpnw-checkbox-group">
						<?php foreach ( $tenure_options as $value => $label ) : ?>
							<label class="lpnw-checkbox-group__item">
								<input type="checkbox" name="tenure_preferences[]" value="<?php echo esc_attr( $value ); ?>"
									<?php checked( in_array( $value, $tenure_preferences, true ) ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-features">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Features', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-features">
						<?php esc_html_e( 'Only show properties that have ALL selected features. Leave unchecked to see everything.', 'lpnw-alerts' ); ?>
					</p>
					<div class="lpnw-checkbox-group">
						<?php foreach ( $feature_options as $value => $label ) : ?>
							<label class="lpnw-checkbox-group__item">
								<input type="checkbox" name="required_features[]" value="<?php echo esc_attr( $value ); ?>"
									<?php checked( in_array( $value, $required_features, true ) ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			</div>
		</details>

		<details class="lpnw-prefs-section" open>
			<summary class="lpnw-prefs-section__summary">
				<span class="lpnw-prefs-section__title"><?php esc_html_e( 'Alerts and delivery', 'lpnw-alerts' ); ?></span>
				<span class="lpnw-prefs-section__hint"><?php esc_html_e( 'Sources and email frequency', 'lpnw-alerts' ); ?></span>
			</summary>
			<div class="lpnw-prefs-section__body">
				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-alerts-master">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Email alerts', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-alerts-master">
						<?php esc_html_e( 'Turn this off to stop all property alert emails. Your saved filters stay in place so you can switch back on anytime.', 'lpnw-alerts' ); ?>
					</p>
					<label class="lpnw-checkbox-group__item">
						<input type="checkbox" name="lpnw_alerts_active" value="1" <?php checked( $lpnw_alerts_active ); ?> />
						<span><?php esc_html_e( 'Send me email alerts', 'lpnw-alerts' ); ?></span>
					</label>
				</fieldset>

				<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-alert-types">
					<legend class="lpnw-fieldset__legend"><?php esc_html_e( 'Alert types', 'lpnw-alerts' ); ?></legend>
					<p class="lpnw-field__help" id="lpnw-help-alert-types">
						Choose which data sources can trigger an email. Listings are homes and plots on the major portals; other options cover planning, EPCs, sold prices, and auctions.
						If none are ticked, we treat that as no filter and you may get every type you are entitled to on your plan.
					</p>
					<div class="lpnw-checkbox-group">
						<?php foreach ( $available_alert_types as $value => $label ) : ?>
							<?php
							$lpnw_off_market_locked = ( 'off_market' === $value && 'vip' !== $tier );
							?>
							<label class="lpnw-checkbox-group__item<?php echo $lpnw_off_market_locked ? ' lpnw-checkbox-group__item--muted' : ''; ?>">
								<input type="checkbox" name="alert_types[]" value="<?php echo esc_attr( $value ); ?>"
									<?php checked( in_array( $value, $alert_types, true ) ); ?>
									<?php disabled( $lpnw_off_market_locked ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<?php if ( 'vip' !== $tier ) : ?>
						<p class="lpnw-field__help lpnw-field__help--vip-off-market" id="lpnw-help-off-market">
							<?php esc_html_e( 'Off-market deals are only available on the Investor VIP plan. Upgrade to VIP to receive alerts for exclusive network listings.', 'lpnw-alerts' ); ?>
						</p>
					<?php endif; ?>
				</fieldset>

				<div class="lpnw-field">
					<label for="lpnw-frequency"><?php esc_html_e( 'Alert frequency', 'lpnw-alerts' ); ?></label>
					<p class="lpnw-field__help" id="lpnw-help-frequency">
						Controls how often we bundle and send alerts. Instant and daily options may require a paid tier.
					</p>
					<select id="lpnw-frequency" name="frequency" aria-describedby="lpnw-help-frequency">
						<option value="instant" <?php selected( $frequency, 'instant' ); ?> <?php disabled( 'free' === $tier ); ?>>
							<?php esc_html_e( 'Instant', 'lpnw-alerts' ); ?><?php echo 'free' === $tier ? ' ' . esc_html__( '(Pro/VIP only)', 'lpnw-alerts' ) : ''; ?>
						</option>
						<option value="daily" <?php selected( $frequency, 'daily' ); ?> <?php disabled( 'free' === $tier ); ?>>
							<?php esc_html_e( 'Daily Digest', 'lpnw-alerts' ); ?><?php echo 'free' === $tier ? ' ' . esc_html__( '(Pro/VIP only)', 'lpnw-alerts' ) : ''; ?>
						</option>
						<option value="weekly" <?php selected( $frequency, 'weekly' ); ?>>
							<?php esc_html_e( 'Weekly Digest', 'lpnw-alerts' ); ?>
						</option>
					</select>
				</div>
			</div>
		</details>

		<div class="lpnw-preferences-form__footer-sticky">
			<button type="submit" class="lpnw-btn lpnw-btn--primary lpnw-preferences-form__submit"><?php esc_html_e( 'Save preferences', 'lpnw-alerts' ); ?></button>
		</div>
	</form>
</div>

<script>
(function () {
	'use strict';
	var master = document.getElementById('lpnw-all-nw');
	var root = document.getElementById('lpnw-areas-root');
	var btnAll = document.getElementById('lpnw-areas-select-all');
	var btnNone = document.getElementById('lpnw-areas-deselect-all');
	if (!master || !root) {
		return;
	}
	function allAreaInputs() {
		return root.querySelectorAll('input[name="areas[]"]');
	}
	function bucketCodes() {
		return Array.prototype.map.call(root.querySelectorAll('.lpnw-area-bucket-cb'), function (el) {
			return el.getAttribute('data-lpnw-bucket') || '';
		}).filter(Boolean);
	}
	function syncBucketFromDistricts(bucket) {
		var b = root.querySelector('.lpnw-area-bucket-cb[data-lpnw-bucket="' + bucket + '"]');
		if (!b) {
			return;
		}
		var kids = root.querySelectorAll('.lpnw-area-district-cb[data-lpnw-parent-bucket="' + bucket + '"]');
		if (!kids.length) {
			return;
		}
		var total = kids.length;
		var on = 0;
		kids.forEach(function (k) {
			if (k.checked) {
				on++;
			}
		});
		b.checked = on === total;
		b.indeterminate = on > 0 && on < total;
	}
	function setDistrictsForBucket(bucket, checked) {
		root.querySelectorAll('.lpnw-area-district-cb[data-lpnw-parent-bucket="' + bucket + '"]').forEach(function (k) {
			k.checked = checked;
		});
	}
	function syncMaster() {
		var codes = bucketCodes();
		var n = codes.length;
		var c = 0;
		codes.forEach(function (code) {
			var b = root.querySelector('.lpnw-area-bucket-cb[data-lpnw-bucket="' + code + '"]');
			if (b && b.checked && !b.indeterminate) {
				c++;
			}
		});
		master.checked = n > 0 && c === n;
		master.indeterminate = c > 0 && c < n;
	}
	function setAll(checked) {
		allAreaInputs().forEach(function (el) {
			el.checked = checked;
			el.indeterminate = false;
		});
		syncMaster();
	}
	master.addEventListener('change', function () {
		bucketCodes().forEach(function (code) {
			var b = root.querySelector('.lpnw-area-bucket-cb[data-lpnw-bucket="' + code + '"]');
			if (b) {
				b.checked = master.checked;
				b.indeterminate = false;
			}
			setDistrictsForBucket(code, master.checked);
		});
		syncMaster();
	});
	if (btnAll) {
		btnAll.addEventListener('click', function () {
			setAll(true);
		});
	}
	if (btnNone) {
		btnNone.addEventListener('click', function () {
			setAll(false);
		});
	}
	root.querySelectorAll('.lpnw-area-bucket-cb').forEach(function (b) {
		b.addEventListener('change', function () {
			var bucket = b.getAttribute('data-lpnw-bucket');
			if (bucket) {
				b.indeterminate = false;
				setDistrictsForBucket(bucket, b.checked);
			}
			syncMaster();
		});
	});
	root.querySelectorAll('.lpnw-area-district-cb').forEach(function (k) {
		k.addEventListener('change', function () {
			var bucket = k.getAttribute('data-lpnw-parent-bucket');
			if (bucket) {
				syncBucketFromDistricts(bucket);
			}
			syncMaster();
		});
	});
	bucketCodes().forEach(syncBucketFromDistricts);
	syncMaster();
})();
</script>
