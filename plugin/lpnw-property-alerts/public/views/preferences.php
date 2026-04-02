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
	if ( in_array( $hint, LPNW_NW_POSTCODES, true ) && ! in_array( $hint, $areas, true ) ) {
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

$nw_areas = array(
	'M'  => 'Manchester (M)',
	'BL' => 'Bolton (BL)',
	'OL' => 'Oldham (OL)',
	'SK' => 'Stockport (SK)',
	'WA' => 'Warrington (WA)',
	'WN' => 'Wigan (WN)',
	'L'  => 'Liverpool (L)',
	'CH' => 'Chester (CH)',
	'CW' => 'Crewe (CW)',
	'PR' => 'Preston (PR)',
	'BB' => 'Blackburn (BB)',
	'FY' => 'Blackpool (FY)',
	'LA' => 'Lancaster (LA)',
	'CA' => 'Carlisle (CA)',
);

$nw_districts = array(
	'M'  => array( 'M1','M2','M3','M4','M5','M6','M7','M8','M9','M11','M12','M13','M14','M15','M16','M17','M18','M19','M20','M21','M22','M23','M24','M25','M26','M27','M28','M29','M30','M31','M32','M33','M34','M35','M38','M40','M41','M43','M44','M45','M46','M50','M60','M90' ),
	'BL' => array( 'BL0','BL1','BL2','BL3','BL4','BL5','BL6','BL7','BL8','BL9','BL11' ),
	'OL' => array( 'OL1','OL2','OL3','OL4','OL5','OL6','OL7','OL8','OL9','OL10','OL11','OL12','OL13','OL14','OL15','OL16' ),
	'SK' => array( 'SK1','SK2','SK3','SK4','SK5','SK6','SK7','SK8','SK9','SK10','SK11','SK12','SK13','SK14','SK15','SK16','SK17','SK22','SK23' ),
	'WA' => array( 'WA1','WA2','WA3','WA4','WA5','WA6','WA7','WA8','WA9','WA10','WA11','WA12','WA13','WA14','WA15','WA16' ),
	'WN' => array( 'WN1','WN2','WN3','WN4','WN5','WN6','WN7','WN8' ),
	'L'  => array( 'L1','L2','L3','L4','L5','L6','L7','L8','L9','L10','L11','L12','L13','L14','L15','L16','L17','L18','L19','L20','L21','L22','L23','L24','L25','L26','L27','L28','L29','L30','L31','L32','L33','L34','L35','L36','L37','L38','L39','L40','L67','L68','L69','L70','L71','L72','L73','L74','L75' ),
	'CH' => array( 'CH1','CH2','CH3','CH4','CH5','CH6','CH7','CH41','CH42','CH43','CH44','CH45','CH46','CH47','CH48','CH49','CH60','CH61','CH62','CH63','CH64','CH65','CH66' ),
	'CW' => array( 'CW1','CW2','CW3','CW4','CW5','CW6','CW7','CW8','CW9','CW10','CW11','CW12' ),
	'PR' => array( 'PR0','PR1','PR2','PR3','PR4','PR5','PR6','PR7','PR8','PR9','PR25','PR26' ),
	'BB' => array( 'BB0','BB1','BB2','BB3','BB4','BB5','BB6','BB7','BB8','BB9','BB10','BB11','BB12','BB18' ),
	'FY' => array( 'FY0','FY1','FY2','FY3','FY4','FY5','FY6','FY7','FY8' ),
	'LA' => array( 'LA1','LA2','LA3','LA4','LA5','LA6','LA7','LA8','LA9','LA10','LA11','LA12','LA13','LA14','LA15','LA16','LA17','LA18','LA19','LA20','LA21','LA22','LA23' ),
	'CA' => array( 'CA1','CA2','CA3','CA4','CA5','CA6','CA7','CA8','CA9','CA10','CA11','CA12','CA13','CA14','CA15','CA16','CA17','CA18','CA19','CA20','CA22','CA25','CA26','CA27','CA28' ),
);

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
						We use the outward part of the postcode (for example M or CH) to decide whether a property is in your patch.
						Only listings and other alerts in at least one area you tick will be sent to you.
					</p>
					<div class="lpnw-area-bulk" role="group" aria-label="<?php esc_attr_e( 'Bulk area selection', 'lpnw-alerts' ); ?>">
						<label class="lpnw-checkbox-group__item lpnw-checkbox-group__item--primary">
							<input type="checkbox" id="lpnw-all-nw" class="lpnw-all-nw-toggle"
								<?php
								$all_area_codes = array_keys( $nw_areas );
								$all_selected   = ! empty( $areas ) && empty( array_diff( $all_area_codes, $areas ) );
								checked( $all_selected );
								?>
							>
							<span><?php esc_html_e( 'All NW', 'lpnw-alerts' ); ?></span>
						</label>
						<p class="lpnw-select-all-label">
							<button type="button" class="lpnw-link-action" id="lpnw-areas-select-all">
								<?php esc_html_e( 'Select all', 'lpnw-alerts' ); ?>
							</button>
							<span class="lpnw-select-all-label__sep" aria-hidden="true"><?php esc_html_e( '/', 'lpnw-alerts' ); ?></span>
							<button type="button" class="lpnw-link-action" id="lpnw-areas-deselect-all">
								<?php esc_html_e( 'Deselect all', 'lpnw-alerts' ); ?>
							</button>
						</p>
					</div>
					<div class="lpnw-checkbox-group" id="lpnw-areas-checkboxes">
						<?php foreach ( $nw_areas as $code => $name ) : ?>
							<label class="lpnw-checkbox-group__item">
								<input type="checkbox" name="areas[]" value="<?php echo esc_attr( $code ); ?>"
									<?php checked( in_array( $code, $areas, true ) ); ?>>
								<span><?php echo esc_html( $name ); ?></span>
							</label>
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
	var group = document.getElementById('lpnw-areas-checkboxes');
	var btnAll = document.getElementById('lpnw-areas-select-all');
	var btnNone = document.getElementById('lpnw-areas-deselect-all');
	if (!master || !group) {
		return;
	}
	var boxes = function () {
		return group.querySelectorAll('input[name="areas[]"]');
	};
	function setAll(checked) {
		boxes().forEach(function (el) {
			el.checked = checked;
		});
		syncMaster();
	}
	function syncMaster() {
		var list = boxes();
		var n = list.length;
		var c = 0;
		list.forEach(function (el) {
			if (el.checked) {
				c++;
			}
		});
		master.checked = n > 0 && c === n;
		master.indeterminate = c > 0 && c < n;
	}
	master.addEventListener('change', function () {
		setAll(master.checked);
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
	boxes().forEach(function (el) {
		el.addEventListener('change', syncMaster);
	});
	syncMaster();
})();
</script>
