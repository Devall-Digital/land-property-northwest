<?php
/**
 * Property search / browse shortcode template.
 *
 * @package LPNW_Property_Alerts
 * @var array<string, mixed>   $lpnw_search_form        Current filter values for the form.
 * @var array<string, mixed>   $filters                 Active query filters (for latest-properties partial).
 * @var array<int, object>     $properties              Result rows.
 * @var int                    $lpnw_search_total       Total matching rows.
 * @var int                    $lpnw_search_page        Current page number (for UI).
 * @var int                    $lpnw_search_total_pages Total pages.
 * @var int                    $lpnw_search_per_page    Page size.
 * @var bool                   $lpnw_search_gated       True when guest should see blur + gate (page 2+ and results exist).
 * @var int                    $lpnw_search_range_start First result index (1-based), or 0 if gated/empty.
 * @var int                    $lpnw_search_range_end   Last result index, or 0.
 * @var string                 $lpnw_search_base_url    Page permalink for form and links.
 * @var array<string, string>  $lpnw_area_labels        NW area names.
 * @var bool                   $lpnw_show_latest_cta    False: do not show homepage teaser CTA.
 */

defined( 'ABSPATH' ) || exit;

$f           = $lpnw_search_form;
$type_opts   = array(
	'Detached',
	'Semi-detached',
	'Terraced',
	'Flat',
	'Other',
);
$source_opts = array(
	'rightmove'   => __( 'Rightmove', 'lpnw-alerts' ),
	'zoopla'      => __( 'Zoopla', 'lpnw-alerts' ),
	'onthemarket' => __( 'OnTheMarket', 'lpnw-alerts' ),
	'planning'    => __( 'Planning', 'lpnw-alerts' ),
	'auction'     => __( 'Auction', 'lpnw-alerts' ),
);

$build_url = static function ( array $extra = array() ) use ( $f, $lpnw_search_base_url ): string {
	$args = array_merge(
		array(
			'area'      => $f['area'],
			'type'      => $f['type'],
			'channel'   => $f['channel'],
			'min_price' => $f['min_price'],
			'max_price' => $f['max_price'],
			'source'    => $f['source'],
			'bedrooms'  => $f['bedrooms'],
			'tenure'    => $f['tenure'],
		),
		$extra
	);
	return LPNW_Public::property_search_url( $lpnw_search_base_url, $args );
};

$signup_url = wp_registration_url();
if ( '' === $signup_url ) {
	$signup_url = home_url( '/pricing/' );
}
$signup_url = add_query_arg(
	'redirect_to',
	rawurlencode( $build_url( array( 'page' => 1 ) ) ),
	$signup_url
);
?>
<div class="lpnw-property-search" data-lpnw-property-search>
	<details class="lpnw-property-search__filters-shell" id="lpnw-property-search-filters">
		<summary class="lpnw-property-search__filters-toggle">
			<span class="lpnw-property-search__filters-toggle-label"><?php esc_html_e( 'Filters', 'lpnw-alerts' ); ?></span>
			<span class="lpnw-property-search__filters-toggle-hint" aria-hidden="true"><?php esc_html_e( 'Show or hide', 'lpnw-alerts' ); ?></span>
		</summary>
	<form class="lpnw-property-search__filters" method="get" action="<?php echo esc_url( $lpnw_search_base_url ); ?>">
		<div class="lpnw-property-search__filters-row">
			<div class="lpnw-property-search__field">
				<label class="lpnw-property-search__label" for="lpnw-search-area"><?php esc_html_e( 'Area', 'lpnw-alerts' ); ?></label>
				<select class="lpnw-property-search__control" name="area" id="lpnw-search-area">
					<option value=""><?php esc_html_e( 'All NW', 'lpnw-alerts' ); ?></option>
					<?php foreach ( LPNW_NW_POSTCODES as $code ) : ?>
						<?php
						$label = isset( $lpnw_area_labels[ $code ] ) ? $lpnw_area_labels[ $code ] : $code;
						?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $f['area'], $code ); ?>><?php echo esc_html( sprintf( '%s — all %s', $label, $code ) ); ?></option>
					<?php endforeach; ?>
					<?php if ( class_exists( 'LPNW_NW_Postcodes' ) ) : ?>
						<?php foreach ( LPNW_NW_Postcodes::get_districts_by_area() as $bucket => $districts ) : ?>
							<?php
							$grp = isset( $lpnw_area_labels[ $bucket ] ) ? $lpnw_area_labels[ $bucket ] : $bucket;
							?>
							<optgroup label="<?php echo esc_attr( sprintf( '%s (%s)', $grp, $bucket ) ); ?>">
								<?php foreach ( $districts as $dist ) : ?>
									<?php
									$lpnw_dist_lbl = class_exists( 'LPNW_NW_Postcodes' )
										? LPNW_NW_Postcodes::get_area_or_district_label( $dist )
										: $dist;
									?>
									<option value="<?php echo esc_attr( $dist ); ?>" <?php selected( $f['area'], $dist ); ?>><?php echo esc_html( $dist . ( '' !== $lpnw_dist_lbl ? ' — ' . $lpnw_dist_lbl : '' ) ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>
			<div class="lpnw-property-search__field">
				<label class="lpnw-property-search__label" for="lpnw-search-type"><?php esc_html_e( 'Type', 'lpnw-alerts' ); ?></label>
				<select class="lpnw-property-search__control" name="type" id="lpnw-search-type">
					<option value=""><?php esc_html_e( 'All', 'lpnw-alerts' ); ?></option>
					<?php foreach ( $type_opts as $opt ) : ?>
						<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $f['type'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="lpnw-property-search__field">
				<label class="lpnw-property-search__label" for="lpnw-search-channel"><?php esc_html_e( 'Channel', 'lpnw-alerts' ); ?></label>
				<select class="lpnw-property-search__control" name="channel" id="lpnw-search-channel">
					<option value=""><?php esc_html_e( 'All', 'lpnw-alerts' ); ?></option>
					<option value="sale" <?php selected( $f['channel'], 'sale' ); ?>><?php esc_html_e( 'For sale', 'lpnw-alerts' ); ?></option>
					<option value="rent" <?php selected( $f['channel'], 'rent' ); ?>><?php esc_html_e( 'To rent', 'lpnw-alerts' ); ?></option>
				</select>
			</div>
			<div class="lpnw-property-search__field lpnw-property-search__field--price">
				<label class="lpnw-property-search__label" for="lpnw-search-min"><?php esc_html_e( 'Min price', 'lpnw-alerts' ); ?></label>
				<input class="lpnw-property-search__control" type="number" name="min_price" id="lpnw-search-min" min="0" step="1" placeholder="0" value="<?php echo $f['min_price'] > 0 ? esc_attr( (string) $f['min_price'] ) : ''; ?>">
			</div>
			<div class="lpnw-property-search__field lpnw-property-search__field--price">
				<label class="lpnw-property-search__label" for="lpnw-search-max"><?php esc_html_e( 'Max price', 'lpnw-alerts' ); ?></label>
				<input class="lpnw-property-search__control" type="number" name="max_price" id="lpnw-search-max" min="0" step="1" placeholder="0" value="<?php echo $f['max_price'] > 0 ? esc_attr( (string) $f['max_price'] ) : ''; ?>">
			</div>
			<div class="lpnw-property-search__field">
				<label class="lpnw-property-search__label" for="lpnw-search-source"><?php esc_html_e( 'Source', 'lpnw-alerts' ); ?></label>
				<select class="lpnw-property-search__control" name="source" id="lpnw-search-source">
					<option value=""><?php esc_html_e( 'All', 'lpnw-alerts' ); ?></option>
					<?php foreach ( $source_opts as $val => $slabel ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['source'], $val ); ?>><?php echo esc_html( $slabel ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="lpnw-property-search__field">
				<label class="lpnw-property-search__label" for="lpnw-search-bedrooms"><?php esc_html_e( 'Bedrooms', 'lpnw-alerts' ); ?></label>
				<select class="lpnw-property-search__control" name="bedrooms" id="lpnw-search-bedrooms">
					<option value=""><?php esc_html_e( 'Any', 'lpnw-alerts' ); ?></option>
					<option value="1" <?php selected( $f['bedrooms'], '1' ); ?>><?php esc_html_e( '1', 'lpnw-alerts' ); ?></option>
					<option value="2" <?php selected( $f['bedrooms'], '2' ); ?>><?php esc_html_e( '2', 'lpnw-alerts' ); ?></option>
					<option value="3" <?php selected( $f['bedrooms'], '3' ); ?>><?php esc_html_e( '3', 'lpnw-alerts' ); ?></option>
					<option value="4" <?php selected( $f['bedrooms'], '4' ); ?>><?php esc_html_e( '4', 'lpnw-alerts' ); ?></option>
					<option value="5" <?php selected( $f['bedrooms'], '5' ); ?>><?php esc_html_e( '5+', 'lpnw-alerts' ); ?></option>
				</select>
			</div>
			<div class="lpnw-property-search__field">
				<label class="lpnw-property-search__label" for="lpnw-search-tenure"><?php esc_html_e( 'Tenure', 'lpnw-alerts' ); ?></label>
				<select class="lpnw-property-search__control" name="tenure" id="lpnw-search-tenure">
					<option value=""><?php esc_html_e( 'Any', 'lpnw-alerts' ); ?></option>
					<option value="freehold" <?php selected( $f['tenure'], 'freehold' ); ?>><?php esc_html_e( 'Freehold', 'lpnw-alerts' ); ?></option>
					<option value="leasehold" <?php selected( $f['tenure'], 'leasehold' ); ?>><?php esc_html_e( 'Leasehold', 'lpnw-alerts' ); ?></option>
				</select>
			</div>
			<div class="lpnw-property-search__field lpnw-property-search__field--submit">
				<span class="lpnw-property-search__label lpnw-property-search__label--spacer" aria-hidden="true">&nbsp;</span>
				<button type="submit" class="lpnw-btn lpnw-btn--secondary lpnw-property-search__submit"><?php esc_html_e( 'Search', 'lpnw-alerts' ); ?></button>
			</div>
		</div>
	</form>
	</details>
	<script>
	(function () {
		var el = document.getElementById('lpnw-property-search-filters');
		if (!el || typeof window.matchMedia !== 'function') {
			return;
		}
		if (!window.matchMedia('(max-width: 639px)').matches) {
			el.setAttribute('open', '');
		}
	})();
	</script>

	<?php if ( $lpnw_search_total > 0 ) : ?>
		<p class="lpnw-property-search__count<?php echo $lpnw_search_gated ? ' lpnw-property-search__count--gated' : ''; ?>">
			<?php echo LPNW_Public::render_live_activity(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			printf(
				/* translators: 1: range start, 2: range end, 3: total (all formatted numbers). */
				esc_html__( 'Showing %1$s-%2$s of %3$s properties', 'lpnw-alerts' ),
				esc_html( number_format_i18n( $lpnw_search_range_start ) ),
				esc_html( number_format_i18n( $lpnw_search_range_end ) ),
				esc_html( number_format_i18n( $lpnw_search_total ) )
			);
			?>
			<?php if ( $lpnw_search_gated ) : ?>
				<span class="lpnw-property-search__count-suffix">
					<?php esc_html_e( 'Preview only. Sign up or log in to unlock every page.', 'lpnw-alerts' ); ?>
				</span>
			<?php endif; ?>
		</p>
	<?php else : ?>
		<p class="lpnw-property-search__count lpnw-property-search__count--empty"><?php esc_html_e( 'No properties match these filters.', 'lpnw-alerts' ); ?></p>
	<?php endif; ?>

	<?php if ( $lpnw_search_total > 0 ) : ?>
		<div class="lpnw-property-search__results-wrap<?php echo $lpnw_search_gated ? ' lpnw-property-search__results-wrap--gated' : ''; ?>">
			<?php if ( $lpnw_search_gated ) : ?>
				<div class="lpnw-property-search__gate" role="region" aria-labelledby="lpnw-property-search-gate-heading">
					<div class="lpnw-property-search__gate-backdrop" aria-hidden="true"></div>
					<div class="lpnw-property-search__gate-panel">
						<h2 class="lpnw-property-search__gate-title" id="lpnw-property-search-gate-heading"><?php esc_html_e( 'Sign up to browse all properties', 'lpnw-alerts' ); ?></h2>
						<p class="lpnw-property-search__gate-text">
							<?php
							printf(
								/* translators: %d: page number requested in the URL (2 or higher). */
								esc_html__( 'Page %d is for members. Create a free account or log in to browse every page, save listings, and get instant alerts.', 'lpnw-alerts' ),
								absint( $lpnw_search_page )
							);
							?>
						</p>
						<div class="lpnw-property-search__gate-actions">
							<a class="lpnw-btn lpnw-btn--primary" href="<?php echo esc_url( $signup_url ); ?>"><?php esc_html_e( 'Sign up free', 'lpnw-alerts' ); ?></a>
							<a class="lpnw-btn lpnw-btn--outline" href="<?php echo esc_url( wp_login_url( $build_url( array( 'page' => $lpnw_search_page ) ) ) ); ?>"><?php esc_html_e( 'Log in', 'lpnw-alerts' ); ?></a>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="lpnw-property-search__grid<?php echo $lpnw_search_gated ? ' lpnw-property-search__grid--blurred' : ''; ?>">
				<?php include LPNW_PLUGIN_DIR . 'public/views/latest-properties.php'; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $lpnw_search_total > 0 || $lpnw_search_page > 1 ) : ?>
		<nav class="lpnw-property-search__pagination" aria-label="<?php esc_attr_e( 'Property results pages', 'lpnw-alerts' ); ?>">
			<?php
			$prev_page = max( 1, $lpnw_search_page - 1 );
			$next_page = min( $lpnw_search_total_pages, $lpnw_search_page + 1 );
			$prev_url  = $build_url( array( 'page' => $prev_page ) );
			$next_url  = $build_url( array( 'page' => $next_page ) );
			?>
			<?php if ( $lpnw_search_page > 1 ) : ?>
				<a class="lpnw-btn lpnw-btn--outline lpnw-property-search__page-link" href="<?php echo esc_url( $prev_url ); ?>"><?php esc_html_e( 'Previous', 'lpnw-alerts' ); ?></a>
			<?php else : ?>
				<span class="lpnw-property-search__page-link lpnw-property-search__page-link--disabled"><?php esc_html_e( 'Previous', 'lpnw-alerts' ); ?></span>
			<?php endif; ?>

			<?php if ( $lpnw_search_page < $lpnw_search_total_pages ) : ?>
				<a class="lpnw-btn lpnw-btn--outline lpnw-property-search__page-link" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Next', 'lpnw-alerts' ); ?></a>
			<?php else : ?>
				<span class="lpnw-property-search__page-link lpnw-property-search__page-link--disabled"><?php esc_html_e( 'Next', 'lpnw-alerts' ); ?></span>
			<?php endif; ?>
			<?php if ( $lpnw_search_total_pages > 1 ) : ?>
				<p class="lpnw-property-search__pagination-hint">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$s of %2$s — use Previous and Next to see more listings.', 'lpnw-alerts' ),
						(int) $lpnw_search_page,
						(int) $lpnw_search_total_pages
					);
					?>
				</p>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div>
