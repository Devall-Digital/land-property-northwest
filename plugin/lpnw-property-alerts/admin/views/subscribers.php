<?php
/**
 * LPNW Subscribers admin list.
 *
 * Variables from LPNW_Admin_Subscribers::render_page(): $data with keys
 * items, total, paged, per_page, search, tier_counts.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string, mixed> $data */
$items       = $data['items'];
$total       = (int) $data['total'];
$paged       = (int) $data['paged'];
$per_page    = (int) $data['per_page'];
$search      = (string) $data['search'];
$tier_counts = $data['tier_counts'];

$total_pages = (int) max( 1, ceil( $total / $per_page ) );
$base_url    = admin_url( 'admin.php?page=lpnw-subscribers' );
?>

<div class="wrap lpnw-admin-subscribers">
	<h1><?php esc_html_e( 'Subscribers', 'lpnw-alerts' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Users who have saved alert preferences. Tier follows WooCommerce orders when Pro or VIP is paid; otherwise you can set an override on the user profile.', 'lpnw-alerts' ); ?>
	</p>

	<div class="lpnw-tier-summary" style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;">
		<div class="card" style="margin:0;padding:12px;min-width:120px;">
			<strong style="font-size:1.4em;"><?php echo esc_html( number_format( (int) $tier_counts['total'] ) ); ?></strong>
			<div class="description" style="margin:4px 0 0;"><?php esc_html_e( 'With preferences', 'lpnw-alerts' ); ?></div>
		</div>
		<div class="card" style="margin:0;padding:12px;min-width:120px;">
			<strong style="font-size:1.4em;"><?php echo esc_html( number_format( (int) $tier_counts['vip'] ) ); ?></strong>
			<div class="description" style="margin:4px 0 0;"><?php esc_html_e( 'VIP (effective)', 'lpnw-alerts' ); ?></div>
		</div>
		<div class="card" style="margin:0;padding:12px;min-width:120px;">
			<strong style="font-size:1.4em;"><?php echo esc_html( number_format( (int) $tier_counts['pro'] ) ); ?></strong>
			<div class="description" style="margin:4px 0 0;"><?php esc_html_e( 'Pro (effective)', 'lpnw-alerts' ); ?></div>
		</div>
		<div class="card" style="margin:0;padding:12px;min-width:120px;">
			<strong style="font-size:1.4em;"><?php echo esc_html( number_format( (int) $tier_counts['free'] ) ); ?></strong>
			<div class="description" style="margin:4px 0 0;"><?php esc_html_e( 'Free (effective)', 'lpnw-alerts' ); ?></div>
		</div>
	</div>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-form" style="margin:12px 0;">
		<input type="hidden" name="page" value="lpnw-subscribers" />
		<p class="search-box">
			<label class="screen-reader-text" for="lpnw-subscriber-search"><?php esc_html_e( 'Search subscribers', 'lpnw-alerts' ); ?></label>
			<input type="search" id="lpnw-subscriber-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email or name', 'lpnw-alerts' ); ?>" />
			<?php submit_button( __( 'Search', 'lpnw-alerts' ), '', '', false ); ?>
		</p>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'User', 'lpnw-alerts' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Tier', 'lpnw-alerts' ); ?></th>
				<th scope="col"><?php esc_html_e( 'From orders', 'lpnw-alerts' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Override', 'lpnw-alerts' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Frequency', 'lpnw-alerts' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Active', 'lpnw-alerts' ); ?></th>
				<th scope="col"><?php esc_html_e( 'WooCommerce', 'lpnw-alerts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No subscribers match your search.', 'lpnw-alerts' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $items as $row ) : ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php echo esc_html( $row['display_name'] ?: $row['email'] ); ?></a></strong><br />
							<span class="description"><?php echo esc_html( $row['email'] ); ?></span>
						</td>
						<td><strong><?php echo esc_html( strtoupper( $row['tier'] ) ); ?></strong></td>
						<td><?php echo esc_html( strtoupper( $row['from_orders'] ) ); ?></td>
						<td><?php echo $row['override'] ? esc_html( strtoupper( $row['override'] ) ) : '—'; ?></td>
						<td><?php echo esc_html( $row['frequency'] ); ?></td>
						<td><?php echo ! empty( $row['is_active'] ) ? esc_html__( 'Yes', 'lpnw-alerts' ) : esc_html__( 'No', 'lpnw-alerts' ); ?></td>
						<td>
							<?php if ( '' !== $row['orders_url'] ) : ?>
								<a href="<?php echo esc_url( $row['orders_url'] ); ?>"><?php esc_html_e( 'Orders', 'lpnw-alerts' ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php
	if ( $total_pages > 1 ) {
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg(
						array(
							'paged' => '%#%',
							's'     => $search,
						),
						$base_url
					),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => __( '&laquo; Previous', 'lpnw-alerts' ),
					'next_text' => __( 'Next &raquo;', 'lpnw-alerts' ),
					'type'      => 'plain',
				)
			)
		);
	}
	?>
</div>
