<?php
/**
 * Interactive property map.
 *
 * Renders a Leaflet.js map showing property locations from the database.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

class LPNW_Map {

	public static function init(): void {
		add_shortcode( 'lpnw_property_map', array( __CLASS__, 'render_map' ) );
	}

	/**
	 * [lpnw_property_map] - Interactive Leaflet map of NW properties.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 */
	public static function render_map( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'height' => '500px',
			'source' => '',
			'limit'  => 200,
		), $atts );

		self::enqueue_leaflet();

		$filters = array();
		if ( ! empty( $atts['source'] ) ) {
			$filters['source'] = $atts['source'];
		}

		$properties = LPNW_Property::query( $filters, (int) $atts['limit'] );

		$markers = array();
		foreach ( $properties as $prop ) {
			if ( empty( $prop->latitude ) || empty( $prop->longitude ) ) {
				continue;
			}

			$markers[] = array(
				'lat'     => (float) $prop->latitude,
				'lng'     => (float) $prop->longitude,
				'title'   => $prop->address,
				'popup'   => sprintf(
					'<strong>%s</strong><br>%s<br>%s%s<a href="%s" target="_blank">View source</a>',
					esc_html( $prop->address ),
					esc_html( $prop->postcode ),
					$prop->price ? '&pound;' . number_format( (int) $prop->price ) . '<br>' : '',
					esc_html( ucfirst( $prop->source ) ) . '<br>',
					esc_url( $prop->source_url )
				),
				'source'  => $prop->source,
			);
		}

		$map_id = 'lpnw-map-' . wp_rand();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $map_id ); ?>" style="height:<?php echo esc_attr( $atts['height'] ); ?>; width:100%; border-radius:8px;"></div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var map = L.map('<?php echo esc_js( $map_id ); ?>').setView([53.75, -2.5], 8);

			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; OpenStreetMap contributors',
				maxZoom: 18
			}).addTo(map);

			var sourceColors = {
				'planning': '#2563EB',
				'epc': '#059669',
				'landregistry': '#7C3AED',
				'auction_pugh': '#DC2626',
				'auction_sdl': '#DC2626',
				'auction_ahnw': '#DC2626',
				'auction_allsop': '#DC2626'
			};

			var markers = <?php echo wp_json_encode( $markers ); ?>;

			markers.forEach(function(m) {
				var color = sourceColors[m.source] || '#6B7280';
				var icon = L.divIcon({
					className: 'lpnw-marker',
					html: '<div style="background:' + color + ';width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.3);"></div>',
					iconSize: [16, 16],
					iconAnchor: [8, 8]
				});

				L.marker([m.lat, m.lng], { icon: icon })
					.bindPopup(m.popup)
					.addTo(map);
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	private static function enqueue_leaflet(): void {
		wp_enqueue_style(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);

		wp_enqueue_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);
	}
}
