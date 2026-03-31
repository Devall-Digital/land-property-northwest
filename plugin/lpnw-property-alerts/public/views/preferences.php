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
$property_types = $prefs ? $prefs->property_types : array();
$alert_types    = $prefs ? $prefs->alert_types : array();
$frequency      = $prefs ? $prefs->frequency : 'daily';
$min_price      = $prefs ? $prefs->min_price : '';
$max_price      = $prefs ? $prefs->max_price : '';

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
	'planning' => 'Planning Applications',
	'epc'      => 'EPC / Property Activity',
	'price'    => 'Price Paid / Transactions',
	'auction'  => 'Auction Lots',
);
?>

<div class="lpnw-preferences-form">
	<h2>Alert Preferences</h2>
	<p>Set your criteria below and we will match new properties as they come in.</p>

	<form id="lpnw-preferences-form">

		<div class="lpnw-field">
			<label>Areas (select all that apply)</label>
			<div class="lpnw-checkbox-group">
				<?php foreach ( $nw_areas as $code => $name ) : ?>
					<label>
						<input type="checkbox" name="areas[]" value="<?php echo esc_attr( $code ); ?>"
							<?php checked( in_array( $code, $areas, true ) ); ?>>
						<span><?php echo esc_html( $name ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="lpnw-field">
			<label for="lpnw-min-price">Minimum Price (GBP)</label>
			<input type="number" id="lpnw-min-price" name="min_price" value="<?php echo esc_attr( $min_price ); ?>" min="0" step="1000" placeholder="No minimum">
		</div>

		<div class="lpnw-field">
			<label for="lpnw-max-price">Maximum Price (GBP)</label>
			<input type="number" id="lpnw-max-price" name="max_price" value="<?php echo esc_attr( $max_price ); ?>" min="0" step="1000" placeholder="No maximum">
		</div>

		<div class="lpnw-field">
			<label>Property Types</label>
			<div class="lpnw-checkbox-group">
				<?php foreach ( $available_types as $value => $label ) : ?>
					<label>
						<input type="checkbox" name="property_types[]" value="<?php echo esc_attr( $value ); ?>"
							<?php checked( in_array( $value, $property_types, true ) ); ?>>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="lpnw-field">
			<label>Alert Types</label>
			<div class="lpnw-checkbox-group">
				<?php foreach ( $available_alert_types as $value => $label ) : ?>
					<label>
						<input type="checkbox" name="alert_types[]" value="<?php echo esc_attr( $value ); ?>"
							<?php checked( in_array( $value, $alert_types, true ) ); ?>>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="lpnw-field">
			<label for="lpnw-frequency">Alert Frequency</label>
			<select id="lpnw-frequency" name="frequency">
				<option value="instant" <?php selected( $frequency, 'instant' ); ?> <?php disabled( 'free' === $tier ); ?>>
					Instant <?php echo 'free' === $tier ? '(Pro/VIP only)' : ''; ?>
				</option>
				<option value="daily" <?php selected( $frequency, 'daily' ); ?> <?php disabled( 'free' === $tier ); ?>>
					Daily Digest <?php echo 'free' === $tier ? '(Pro/VIP only)' : ''; ?>
				</option>
				<option value="weekly" <?php selected( $frequency, 'weekly' ); ?>>
					Weekly Digest
				</option>
			</select>
		</div>

		<button type="submit" class="lpnw-btn lpnw-btn--primary">Save Preferences</button>
	</form>
</div>
