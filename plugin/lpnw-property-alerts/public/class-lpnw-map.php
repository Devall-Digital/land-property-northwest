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

	/**
	 * Whether frontend assets for the map have been enqueued this request.
	 *
	 * @var bool
	 */
	private static bool $assets_enqueued = false;

	public static function init(): void {
		add_shortcode( 'lpnw_property_map', array( __CLASS__, 'render_map' ) );
		add_action( 'wp_ajax_lpnw_map_properties', array( __CLASS__, 'ajax_map_properties' ) );
		add_action( 'wp_ajax_nopriv_lpnw_map_properties', array( __CLASS__, 'ajax_map_properties' ) );
	}

	/**
	 * Allowed map filter keys (empty string = all sources).
	 *
	 * @return array<int, string>
	 */
	private static function allowed_source_filters(): array {
		return array(
			'',
			'rightmove',
			'zoopla',
			'onthemarket',
			'planning',
			'epc',
			'landregistry',
			'auction',
		);
	}

	/**
	 * Auction feed source values stored in the database.
	 *
	 * @return array<int, string>
	 */
	private static function auction_source_values(): array {
		return array( 'auction_pugh', 'auction_sdl', 'auction_ahnw', 'auction_allsop' );
	}

	/**
	 * Hex colour for a property row by source.
	 */
	private static function marker_color_for_source( string $source ): string {
		if ( 'rightmove' === $source ) {
			return '#2563eb';
		}
		if ( 'zoopla' === $source ) {
			return '#16a34a';
		}
		if ( 'onthemarket' === $source ) {
			return '#9333ea';
		}
		if ( 'planning' === $source ) {
			return '#ea580c';
		}
		if ( 'epc' === $source ) {
			return '#0d9488';
		}
		if ( 'landregistry' === $source ) {
			return '#6b7280';
		}
		if ( str_starts_with( $source, 'auction' ) ) {
			return '#dc2626';
		}
		return '#6b7280';
	}

	/**
	 * Human-readable label for badge (single line).
	 */
	private static function source_display_label( string $source ): string {
		return ucfirst( str_replace( '_', ' ', $source ) );
	}

	/**
	 * Query properties that have coordinates, ordered by newest first.
	 *
	 * @param string $source_key      Filter: '' or exact source, or 'auction' for all auction feeds.
	 * @param int    $limit           Max rows.
	 * @param int    $offset          Offset.
	 * @param string $postcode_prefix NW outward code, or '' for all.
	 * @return array<int, object>
	 */
	private static function query_properties_with_coordinates( string $source_key, int $limit, int $offset, string $postcode_prefix = '' ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lpnw_properties';
		$where = array(
			'latitude IS NOT NULL',
			'longitude IS NOT NULL',
			'latitude != 0',
			'longitude != 0',
		);
		$args  = array();

		if ( 'auction' === $source_key ) {
			$auctions = self::auction_source_values();
			$ph       = implode( ',', array_fill( 0, count( $auctions ), '%s' ) );
			$where[]  = "source IN ({$ph})"; // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$args     = array_merge( $args, $auctions );
		} elseif ( '' !== $source_key ) {
			$where[] = 'source = %s';
			$args[]  = $source_key;
		}

		if ( '' !== $postcode_prefix ) {
			LPNW_Property::append_postcode_prefix_sql( 'UPPER(TRIM(postcode))', $postcode_prefix, $where, $args );
		}

		$where_sql = implode( ' AND ', $where );
		$args[]    = $limit;
		$args[]    = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				...$args
			)
		);
	}

	/**
	 * Fetch up to $limit rows; reports whether more rows exist.
	 *
	 * @param string $postcode_prefix NW outward code or ''.
	 * @return array{0: array<int, object>, 1: bool}
	 */
	private static function fetch_map_page( string $source_key, int $limit, int $offset, string $postcode_prefix = '' ): array {
		$rows = self::query_properties_with_coordinates( $source_key, $limit + 1, $offset, $postcode_prefix );
		$more = count( $rows ) > $limit;
		if ( $more ) {
			$rows = array_slice( $rows, 0, $limit );
		}
		return array( $rows, $more );
	}

	/**
	 * Build marker payload for the frontend.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function property_row_to_marker( object $prop ): ?array {
		if ( empty( $prop->latitude ) || empty( $prop->longitude ) ) {
			return null;
		}

		$source = (string) $prop->source;
		$color  = self::marker_color_for_source( $source );
		$label  = self::source_display_label( $source );

		$price_line = '';
		if ( ! empty( $prop->price ) ) {
			$price_line = '&pound;' . esc_html( number_format_i18n( (int) $prop->price ) );
		}

		$ptype = isset( $prop->property_type ) ? (string) $prop->property_type : '';
		$url   = isset( $prop->source_url ) ? (string) $prop->source_url : '';

		$link_html = '';
		if ( '' !== $url ) {
			$link_html = sprintf(
				'<p class="lpnw-map-popup__link"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url( $url ),
				esc_html__( 'View listing', 'lpnw-alerts' )
			);
		}

		$popup = sprintf(
			'<div class="lpnw-map-popup"><p class="lpnw-map-popup__addr"><strong>%s</strong></p><p class="lpnw-map-popup__meta">%s</p>%s%s<p class="lpnw-map-popup__badge"><span class="lpnw-map-popup__badge-inner" style="background:%s;color:#fff;">%s</span></p>%s</div>',
			esc_html( (string) $prop->address ),
			esc_html( (string) $prop->postcode ),
			$price_line ? '<p class="lpnw-map-popup__price">' . $price_line . '</p>' : '',
			$ptype ? '<p class="lpnw-map-popup__type">' . esc_html( $ptype ) . '</p>' : '',
			esc_attr( $color ),
			esc_html( $label ),
			$link_html
		);

		return array(
			'lat'    => (float) $prop->latitude,
			'lng'    => (float) $prop->longitude,
			'source' => $source,
			'color'  => $color,
			'popup'  => $popup,
		);
	}

	/**
	 * @param array<int, object> $rows
	 * @return array<int, array<string, mixed>>
	 */
	private static function rows_to_markers( array $rows ): array {
		$markers = array();
		foreach ( $rows as $prop ) {
			$m = self::property_row_to_marker( $prop );
			if ( null !== $m ) {
				$markers[] = $m;
			}
		}
		return $markers;
	}

	/**
	 * AJAX: return JSON markers for pagination / filter changes.
	 */
	public static function ajax_map_properties(): void {
		check_ajax_referer( 'lpnw_map', 'nonce' );

		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		if ( ! in_array( $source, self::allowed_source_filters(), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid source.' ) );
		}

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 500;
		$limit  = min( 500, max( 1, $limit ) );

		$pc_prefix = isset( $_POST['postcode_prefix'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['postcode_prefix'] ) ) ) ) : '';
		if ( '' !== $pc_prefix ) {
			$ok = class_exists( 'LPNW_NW_Postcodes' )
				? LPNW_NW_Postcodes::is_valid_area_or_district( $pc_prefix )
				: in_array( $pc_prefix, LPNW_NW_POSTCODES, true );
			if ( ! $ok ) {
				$pc_prefix = '';
			}
		}

		list( $rows, $has_more ) = self::fetch_map_page( $source, $limit, $offset, $pc_prefix );
		$markers                 = self::rows_to_markers( $rows );

		wp_send_json_success(
			array(
				'markers'  => $markers,
				'has_more' => $has_more,
			)
		);
	}

	private static function enqueue_map_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		wp_enqueue_style(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);

		wp_enqueue_style(
			'leaflet-markercluster',
			'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
			array( 'leaflet' ),
			'1.5.3'
		);

		wp_enqueue_style(
			'leaflet-markercluster-default',
			'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
			array( 'leaflet-markercluster' ),
			'1.5.3'
		);

		wp_enqueue_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_enqueue_script(
			'leaflet-markercluster',
			'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
			array( 'leaflet' ),
			'1.5.3',
			true
		);

		wp_register_script(
			'lpnw-property-map',
			false,
			array( 'leaflet', 'leaflet-markercluster' ),
			defined( 'LPNW_VERSION' ) ? LPNW_VERSION : '1.0.0',
			true
		);
		wp_enqueue_script( 'lpnw-property-map' );

		$inline = <<<'JS'
(function () {
	function buildClusterGroup() {
		return L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });
	}
	function markerIcon(color) {
		return L.divIcon({
			className: 'lpnw-map-marker',
			html: '<div class="lpnw-map-marker__dot" style="background:' + color + ';"></div>',
			iconSize: [16, 16],
			iconAnchor: [8, 8]
		});
	}
	function addMarkers(cluster, items) {
		items.forEach(function (m) {
			L.marker([m.lat, m.lng], { icon: markerIcon(m.color) }).bindPopup(m.popup).addTo(cluster);
		});
	}
	function setBusy(root, on) {
		var btn = root.querySelector('.lpnw-map-load-more');
		var sel = root.querySelector('.lpnw-map-source');
		if (btn) { btn.disabled = !!on; }
		if (sel) { sel.disabled = !!on; }
	}
	function fetchMarkers(root, cluster, cfg, source, offset, append) {
		setBusy(root, true);
		var maxTotal = typeof cfg.maxTotalMarkers === 'number' ? cfg.maxTotalMarkers : 500;
		var chunk = Math.min(cfg.batchSize, Math.max(0, maxTotal - offset));
		if (chunk < 1) {
			setBusy(root, false);
			var moreBtnEmpty = root.querySelector('.lpnw-map-load-more');
			if (moreBtnEmpty) { moreBtnEmpty.hidden = true; }
			return;
		}
		var body = new URLSearchParams();
		body.set('action', 'lpnw_map_properties');
		body.set('nonce', cfg.nonce);
		body.set('source', source);
		body.set('offset', String(offset));
		body.set('limit', String(chunk));
		if (cfg.postcodePrefix) {
			body.set('postcode_prefix', cfg.postcodePrefix);
		}
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) { return r.json(); }).then(function (data) {
			setBusy(root, false);
			if (!data || !data.success || !data.data) { return; }
			var markers = data.data.markers || [];
			if (!append) { cluster.clearLayers(); }
			addMarkers(cluster, markers);
			var newOffset = offset + markers.length;
			root.dataset.lpnwOffset = String(newOffset);
			root.dataset.lpnwSource = source;
			var canMore = !!(data.data.has_more && newOffset < maxTotal);
			root.dataset.lpnwHasMore = canMore ? '1' : '0';
			var moreBtn = root.querySelector('.lpnw-map-load-more');
			if (moreBtn) { moreBtn.hidden = !canMore; }
		}).catch(function () { setBusy(root, false); });
	}
	function initRoot(root) {
		var cfg = JSON.parse(root.getAttribute('data-lpnw-map-config'));
		if (!cfg || !cfg.mapId) { return; }
		if (!document.getElementById(cfg.mapId)) { return; }
		var initLat = typeof cfg.initialLat === 'number' ? cfg.initialLat : 53.48;
		var initLng = typeof cfg.initialLng === 'number' ? cfg.initialLng : -2.24;
		var initZoom = typeof cfg.initialZoom === 'number' ? cfg.initialZoom : 9;
		var map = L.map(cfg.mapId).setView([initLat, initLng], initZoom);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
			maxZoom: 18
		}).addTo(map);
		var cluster = buildClusterGroup();
		cluster.addTo(map);
		addMarkers(cluster, cfg.initialMarkers || []);
		var initLen = (cfg.initialMarkers || []).length;
		root.dataset.lpnwOffset = String(initLen);
		root.dataset.lpnwSource = cfg.initialSource || '';
		root.dataset.lpnwMaxTotal = String(typeof cfg.maxTotalMarkers === 'number' ? cfg.maxTotalMarkers : 500);
		root.dataset.lpnwHasMore = cfg.initialHasMore ? '1' : '0';
		var moreBtn = root.querySelector('.lpnw-map-load-more');
		if (moreBtn) {
			moreBtn.hidden = !cfg.initialHasMore;
			moreBtn.addEventListener('click', function () {
				if (root.dataset.lpnwHasMore !== '1') { return; }
				fetchMarkers(root, cluster, cfg, root.dataset.lpnwSource || '', parseInt(root.dataset.lpnwOffset, 10) || 0, true);
			});
		}
		var sel = root.querySelector('.lpnw-map-source');
		if (sel) {
			sel.addEventListener('change', function () {
				var v = sel.value || '';
				root.dataset.lpnwOffset = '0';
				fetchMarkers(root, cluster, cfg, v, 0, false);
			});
		}
	}
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.lpnw-property-map-widget').forEach(initRoot);
	});
})();
JS;

		wp_add_inline_script( 'lpnw-property-map', $inline );
	}

	/**
	 * [lpnw_property_map] - Interactive Leaflet map of NW properties.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 */
	public static function render_map( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'height'          => '500px',
				'source'          => '',
				'limit'           => 500,
				'batch_size'      => 500,
				'lat'             => '',
				'lng'             => '',
				'zoom'            => '',
				'postcode_prefix' => '',
			),
			$atts,
			'lpnw_property_map'
		);

		$max_total = min( 500, max( 1, (int) $atts['limit'] ) );
		$batch     = (int) $atts['batch_size'];
		$batch     = min( 500, max( 1, $batch ) );
		$batch     = min( $batch, $max_total );

		$source_filter = (string) $atts['source'];
		if ( ! in_array( $source_filter, self::allowed_source_filters(), true ) ) {
			$source_filter = '';
		}

		$postcode_prefix = strtoupper( trim( (string) $atts['postcode_prefix'] ) );
		if ( '' !== $postcode_prefix ) {
			$ok = class_exists( 'LPNW_NW_Postcodes' )
				? LPNW_NW_Postcodes::is_valid_area_or_district( $postcode_prefix )
				: in_array( $postcode_prefix, LPNW_NW_POSTCODES, true );
			if ( ! $ok ) {
				$postcode_prefix = '';
			}
		}

		$initial_lat  = null;
		$initial_lng  = null;
		$initial_zoom = null;
		if ( '' !== $atts['lat'] && '' !== $atts['lng'] && is_numeric( $atts['lat'] ) && is_numeric( $atts['lng'] ) ) {
			$initial_lat  = round( (float) $atts['lat'], 6 );
			$initial_lng  = round( (float) $atts['lng'], 6 );
			$initial_zoom = ( '' !== $atts['zoom'] && is_numeric( $atts['zoom'] ) )
				? max( 4, min( 16, (int) $atts['zoom'] ) )
				: 11;
		}

		self::enqueue_map_assets();

		list( $rows, $has_more ) = self::fetch_map_page( $source_filter, $batch, 0, $postcode_prefix );
		$markers                 = self::rows_to_markers( $rows );
		$row_count               = count( $rows );
		$initial_has_more        = $has_more && $row_count < $max_total;

		$map_id = 'lpnw-map-' . wp_rand( 10000, 99999 );

		$height_attr = (string) $atts['height'];
		$map_height  = 'min(62vh, 500px)';
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*(px|vh|vw|rem|em|%)$/i', trim( $height_attr ), $hm ) ) {
			$map_height = $hm[1] . $hm[2];
		}

		$config = array(
			'mapId'           => $map_id,
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'lpnw_map' ),
			'batchSize'       => $batch,
			'maxTotalMarkers' => $max_total,
			'initialSource'   => $source_filter,
			'initialMarkers'  => $markers,
			'initialHasMore'  => $initial_has_more,
			'postcodePrefix'  => $postcode_prefix,
			'initialLat'      => $initial_lat,
			'initialLng'      => $initial_lng,
			'initialZoom'     => $initial_zoom,
		);

		$legend_items = array(
			array(
				'color' => '#2563eb',
				'label' => __( 'Rightmove', 'lpnw-alerts' ),
			),
			array(
				'color' => '#16a34a',
				'label' => __( 'Zoopla', 'lpnw-alerts' ),
			),
			array(
				'color' => '#9333ea',
				'label' => __( 'OnTheMarket', 'lpnw-alerts' ),
			),
			array(
				'color' => '#ea580c',
				'label' => __( 'Planning', 'lpnw-alerts' ),
			),
			array(
				'color' => '#dc2626',
				'label' => __( 'Auction', 'lpnw-alerts' ),
			),
			array(
				'color' => '#0d9488',
				'label' => __( 'EPC', 'lpnw-alerts' ),
			),
			array(
				'color' => '#6b7280',
				'label' => __( 'Land Registry', 'lpnw-alerts' ),
			),
		);

		ob_start();
		?>
		<div class="lpnw-property-map-widget" data-lpnw-map-config="<?php echo esc_attr( wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) ); ?>">
			<div class="lpnw-map-toolbar">
				<label class="lpnw-map-toolbar__field">
					<span class="lpnw-map-toolbar__label"><?php echo esc_html__( 'Source', 'lpnw-alerts' ); ?></span>
					<select class="lpnw-map-source lpnw-map-toolbar__select" name="lpnw_map_source">
						<option value="" <?php selected( $source_filter, '' ); ?>><?php echo esc_html__( 'All sources', 'lpnw-alerts' ); ?></option>
						<option value="rightmove" <?php selected( $source_filter, 'rightmove' ); ?>><?php echo esc_html__( 'Rightmove', 'lpnw-alerts' ); ?></option>
						<option value="zoopla" <?php selected( $source_filter, 'zoopla' ); ?>><?php echo esc_html__( 'Zoopla', 'lpnw-alerts' ); ?></option>
						<option value="onthemarket" <?php selected( $source_filter, 'onthemarket' ); ?>><?php echo esc_html__( 'OnTheMarket', 'lpnw-alerts' ); ?></option>
						<option value="planning" <?php selected( $source_filter, 'planning' ); ?>><?php echo esc_html__( 'Planning', 'lpnw-alerts' ); ?></option>
						<option value="auction" <?php selected( $source_filter, 'auction' ); ?>><?php echo esc_html__( 'Auction', 'lpnw-alerts' ); ?></option>
						<option value="epc" <?php selected( $source_filter, 'epc' ); ?>><?php echo esc_html__( 'EPC', 'lpnw-alerts' ); ?></option>
						<option value="landregistry" <?php selected( $source_filter, 'landregistry' ); ?>><?php echo esc_html__( 'Land Registry', 'lpnw-alerts' ); ?></option>
					</select>
				</label>
				<button type="button" class="lpnw-map-load-more button lpnw-map-toolbar__load-more" hidden><?php echo esc_html__( 'Load more', 'lpnw-alerts' ); ?></button>
			</div>
			<div class="lpnw-map-body">
				<div id="<?php echo esc_attr( $map_id ); ?>" class="lpnw-map-canvas" style="height:<?php echo esc_attr( $map_height ); ?>;"></div>
				<div class="lpnw-map-legend lpnw-map-legend--widget">
					<strong class="lpnw-map-legend__title"><?php echo esc_html__( 'Legend', 'lpnw-alerts' ); ?></strong>
					<ul class="lpnw-map-legend__list" role="list">
						<?php foreach ( $legend_items as $item ) : ?>
							<li class="lpnw-map-legend__item">
								<span class="lpnw-map-legend__swatch" style="background:<?php echo esc_attr( $item['color'] ); ?>;"></span>
								<?php echo esc_html( $item['label'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<style>
			.lpnw-map-marker { background: transparent !important; border: none !important; }
			.lpnw-map-marker__dot { width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.35); }
			.lpnw-map-popup { margin: 4px 0; min-width: 200px; max-width: 280px; }
			.lpnw-map-popup__addr { margin: 0 0 6px; }
			.lpnw-map-popup__meta { margin: 0 0 4px; color: #4b5563; font-size: 13px; }
			.lpnw-map-popup__price { margin: 0 0 4px; font-weight: 600; }
			.lpnw-map-popup__type { margin: 0 0 6px; font-size: 13px; }
			.lpnw-map-popup__badge { margin: 8px 0 0; }
			.lpnw-map-popup__badge-inner { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
			.lpnw-map-popup__link { margin: 8px 0 0; }
		</style>
		<?php
		return ob_get_clean();
	}
}

add_action( 'init', array( LPNW_Map::class, 'init' ) );
