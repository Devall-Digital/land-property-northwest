<?php
/**
 * Plugin Name: LPNW Verify Property Fields
 * Description: One-shot diagnostic. Upload to wp-content/mu-plugins/, open ?lpnw_verify=fields&key=lpnw2026setup, then the file removes itself.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

const LPNW_VERIFY_FIELDS_KEY = 'lpnw2026setup';

/**
 * Email used to resolve the test user (same as lpnw-test-subscriber.php).
 */
const LPNW_VERIFY_TEST_USER_EMAIL = 'admin@codevall.co.uk';

add_action( 'template_redirect', 'lpnw_verify_fields_maybe_run', 1 );

/**
 * Run field population diagnostic and self-delete.
 */
function lpnw_verify_fields_maybe_run(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['lpnw_verify'], $_GET['key'] ) ) {
		return;
	}

	if ( 'fields' !== sanitize_text_field( wp_unslash( $_GET['lpnw_verify'] ) ) ) {
		return;
	}

	if ( ! hash_equals( LPNW_VERIFY_FIELDS_KEY, sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) ) {
		return;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	header( 'Content-Type: text/plain; charset=utf-8' );

	global $wpdb;
	$table = $wpdb->prefix . 'lpnw_properties';

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$counts = $wpdb->get_row(
		"SELECT
			SUM(bedrooms IS NOT NULL) AS c_bedrooms,
			SUM(bathrooms IS NOT NULL) AS c_bathrooms,
			SUM(tenure_type IS NOT NULL AND TRIM(tenure_type) <> '') AS c_tenure,
			SUM(agent_name IS NOT NULL AND TRIM(agent_name) <> '') AS c_agent,
			SUM(first_listed_date IS NOT NULL AND TRIM(first_listed_date) <> '' AND first_listed_date <> '0000-00-00') AS c_listed,
			SUM(key_features_text IS NOT NULL AND TRIM(key_features_text) <> '') AS c_features
		FROM {$table}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	$c_bedrooms  = isset( $counts['c_bedrooms'] ) ? (int) $counts['c_bedrooms'] : 0;
	$c_bathrooms = isset( $counts['c_bathrooms'] ) ? (int) $counts['c_bathrooms'] : 0;
	$c_tenure    = isset( $counts['c_tenure'] ) ? (int) $counts['c_tenure'] : 0;
	$c_agent     = isset( $counts['c_agent'] ) ? (int) $counts['c_agent'] : 0;
	$c_listed    = isset( $counts['c_listed'] ) ? (int) $counts['c_listed'] : 0;
	$c_features  = isset( $counts['c_features'] ) ? (int) $counts['c_features'] : 0;

	echo "LPNW property field diagnostic\n";
	echo str_repeat( '=', 60 ) . "\n\n";

	echo "Population (all rows in {$table}):\n";
	echo "  bedrooms NOT NULL: {$c_bedrooms} of {$total}\n";
	echo "  bathrooms NOT NULL: {$c_bathrooms}\n";
	echo "  tenure_type not empty: {$c_tenure}\n";
	echo "  agent_name not empty: {$c_agent}\n";
	echo "  first_listed_date not empty: {$c_listed}\n";
	echo "  key_features_text not empty: {$c_features}\n\n";

	$sample = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT address, bedrooms, bathrooms, tenure_type, agent_name, first_listed_date, key_features_text
			FROM {$table}
			WHERE bedrooms IS NOT NULL
			ORDER BY id DESC
			LIMIT %d",
			5
		)
	);

	echo "Sample: 5 properties with bedrooms IS NOT NULL (newest first)\n";
	echo str_repeat( '-', 60 ) . "\n";

	if ( empty( $sample ) ) {
		echo "(none)\n\n";
	} else {
		$n = 1;
		foreach ( $sample as $row ) {
			$features_raw = isset( $row->key_features_text ) ? (string) $row->key_features_text : '';
			$parts        = array_filter( array_map( 'trim', explode( '|', $features_raw ) ) );
			$first_three  = array_slice( $parts, 0, 3 );
			$feat_display = $first_three ? implode( ' | ', $first_three ) : '(none)';

			echo "\n#{$n}\n";
			echo '  address: ' . ( isset( $row->address ) ? $row->address : '' ) . "\n";
			echo '  bedrooms: ' . ( isset( $row->bedrooms ) ? (string) $row->bedrooms : '' ) . "\n";
			echo '  bathrooms: ' . ( isset( $row->bathrooms ) ? (string) $row->bathrooms : '' ) . "\n";
			echo '  tenure_type: ' . ( isset( $row->tenure_type ) ? (string) $row->tenure_type : '' ) . "\n";
			echo '  agent_name: ' . ( isset( $row->agent_name ) ? (string) $row->agent_name : '' ) . "\n";
			echo '  first_listed_date: ' . ( isset( $row->first_listed_date ) ? (string) $row->first_listed_date : '' ) . "\n";
			echo '  key_features (first 3): ' . $feat_display . "\n";
			++$n;
		}
		echo "\n";
	}

	echo str_repeat( '=', 60 ) . "\n";
	echo "Test user preferences (" . LPNW_VERIFY_TEST_USER_EMAIL . ")\n";
	echo str_repeat( '-', 60 ) . "\n";

	$user = get_user_by( 'email', LPNW_VERIFY_TEST_USER_EMAIL );
	if ( ! $user ) {
		echo "User not found.\n\n";
	} elseif ( class_exists( 'LPNW_Subscriber' ) ) {
		$prefs = LPNW_Subscriber::get_preferences( (int) $user->ID );
		if ( ! $prefs ) {
			echo "No lpnw_subscriber_preferences row for user ID {$user->ID}.\n\n";
		} else {
			echo "user_id: {$prefs->user_id}\n";
			echo 'areas: ' . wp_json_encode( $prefs->areas ) . "\n";
			echo 'min_price: ' . ( null !== $prefs->min_price ? (string) $prefs->min_price : 'null' ) . "\n";
			echo 'max_price: ' . ( null !== $prefs->max_price ? (string) $prefs->max_price : 'null' ) . "\n";
			echo 'min_bedrooms: ' . ( null !== $prefs->min_bedrooms ? (string) $prefs->min_bedrooms : 'null' ) . "\n";
			echo 'max_bedrooms: ' . ( null !== $prefs->max_bedrooms ? (string) $prefs->max_bedrooms : 'null' ) . "\n";
			echo 'listing_channels: ' . wp_json_encode( $prefs->listing_channels ) . "\n";
			echo 'tenure_preferences: ' . wp_json_encode( $prefs->tenure_preferences ) . "\n";
			echo 'required_features: ' . wp_json_encode( $prefs->required_features ) . "\n";
			echo 'property_types: ' . wp_json_encode( $prefs->property_types ) . "\n";
			echo 'alert_types: ' . wp_json_encode( $prefs->alert_types ) . "\n";
			echo 'frequency: ' . (string) $prefs->frequency . "\n";
			echo 'is_active: ' . (string) $prefs->is_active . "\n\n";
		}
	} else {
		echo "LPNW_Subscriber not loaded (main plugin inactive?).\n\n";
	}

	echo str_repeat( '=', 60 ) . "\n";
	echo "Self-delete: removing this mu-plugin file.\n";

	if ( function_exists( 'wp_ob_end_flush_all' ) ) {
		wp_ob_end_flush_all();
	}
	flush();

	$path = __FILE__;
	if ( is_string( $path ) && $path !== '' && is_readable( $path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- intentional one-shot diagnostic removal.
		@unlink( $path );
	}

	exit;
}
