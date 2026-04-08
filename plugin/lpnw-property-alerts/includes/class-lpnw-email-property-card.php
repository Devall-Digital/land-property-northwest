<?php
/**
 * Renders a single property row for HTML alert emails (wp_mail and Mautic token HTML).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Property card fragment for email templates.
 */
final class LPNW_Email_Property_Card {

	/**
	 * Render one property as outer `<tr>…</tr>` rows (matches email-instant-alert layout).
	 *
	 * @param object               $prop Property object from LPNW_Property::get().
	 * @param array<string, mixed> $opts Optional: include_description (bool), compact (bool) for digest styling.
	 * @return string Safe HTML fragment.
	 */
	public static function render_row( object $prop, array $opts = array() ): string {
		$include = array_key_exists( 'include_description', $opts ) ? (bool) $opts['include_description'] : true;
		$compact = ! empty( $opts['compact'] );

		$lpnw_prop                = $prop;
		$lpnw_include_description = $include;
		$lpnw_compact             = $compact;

		$partial = LPNW_PLUGIN_DIR . 'templates/partials/lpnw-email-property-card-row.php';
		if ( ! file_exists( $partial ) ) {
			return '';
		}

		ob_start();
		include $partial;
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}
}
