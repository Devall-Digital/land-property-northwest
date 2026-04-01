<?php
/**
 * Alert preferences form template.
 *
 * @package LPNW_Property_Alerts
 * @var object|null $prefs Current preferences.
 * @var string      $tier  Subscription tier.
 */

defined( 'ABSPATH' ) || exit;

$areas          = $prefs ? $prefs->areas : array();
$areas          = is_array( $areas ) ? $areas : array();
if ( isset( $_GET['lpnw_area'] ) ) {
	$hint = strtoupper( sanitize_text_field( wp_unslash( $_GET['lpnw_area'] ) ) );
	if ( in_array( $hint, LPNW_NW_POSTCODES, true ) && ! in_array( $hint, $areas, true ) ) {
		$areas[] = $hint;
	}
}
$property_types = $prefs ? $prefs->property_types : array();
$alert_types    = $prefs ? $prefs->alert_types : array();
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

$available_types = array(
	'Detached'         => 'Detached',
	'Semi-detached'    => 'Semi-detached',
	'Terraced'         => 'Terraced',
	'Flat/Maisonette'  => 'Flat/Maisonette',
	'Auction lot'      => 'Auction Lot',
	'Other'            => 'Other/Land',
);

$available_alert_types = array(
	'listing'  => 'New Property Listings (Rightmove, Zoopla, OnTheMarket)',
	'planning' => 'Planning Applications',
	'epc'      => 'EPC / Property Activity',
	'price'    => 'Price Paid / Transactions',
	'auction'  => 'Auction Lots',
);
?>

<div class="lpnw-preferences-form">
	<h2>Alert Preferences</h2>
	<p class="lpnw-preferences-form__intro">Set your criteria below and we will match new properties as they come in.</p>

	<form id="lpnw-preferences-form">

		<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-areas">
			<legend class="lpnw-fieldset__legend">Areas</legend>
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

		<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-price">
			<legend class="lpnw-fieldset__legend">Price range (GBP)</legend>
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

		<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-types">
			<legend class="lpnw-fieldset__legend">Property types</legend>
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
					<label for="lpnw-max-bedrooms"><?php esc_html_e( 'Max bedrooms', 'lpnw-alerts' ); ?></label>
					<input type="number" id="lpnw-max-bedrooms" name="max_bedrooms" value="<?php echo esc_attr( $max_bedrooms_display ); ?>" min="0" max="10" step="1" placeholder="<?php esc_attr_e( 'No maximum', 'lpnw-alerts' ); ?>">
				</div>
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

		<fieldset class="lpnw-fieldset" aria-describedby="lpnw-help-alert-types">
			<legend class="lpnw-fieldset__legend">Alert types</legend>
			<p class="lpnw-field__help" id="lpnw-help-alert-types">
				Choose which data sources can trigger an email. Listings are homes and plots on the major portals; other options cover planning, EPCs, sold prices, and auctions.
				If none are ticked, we treat that as no filter and you may get every type you are entitled to on your plan.
			</p>
			<div class="lpnw-checkbox-group">
				<?php foreach ( $available_alert_types as $value => $label ) : ?>
					<label class="lpnw-checkbox-group__item">
						<input type="checkbox" name="alert_types[]" value="<?php echo esc_attr( $value ); ?>"
							<?php checked( in_array( $value, $alert_types, true ) ); ?>>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
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

		<button type="submit" class="lpnw-btn lpnw-btn--primary"><?php esc_html_e( 'Save Preferences', 'lpnw-alerts' ); ?></button>
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
