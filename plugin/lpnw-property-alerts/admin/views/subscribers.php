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
$items              = $data['items'];
$total              = (int) $data['total'];
$paged              = (int) $data['paged'];
$per_page           = (int) $data['per_page'];
$search             = (string) $data['search'];
$tier_counts        = $data['tier_counts'];
$no_profile_count   = isset( $data['no_profile_count'] ) ? (int) $data['no_profile_count'] : 0;
$no_profile_sample  = isset( $data['no_profile_sample'] ) && is_array( $data['no_profile_sample'] ) ? $data['no_profile_sample'] : array();

$total_pages = (int) max( 1, ceil( $total / $per_page ) );
$base_url    = admin_url( 'admin.php?page=lpnw-subscribers' );
?>

<div class="wrap lpnw-admin-subscribers">
	<h1><?php esc_html_e( 'Subscribers', 'lpnw-alerts' ); ?></h1>
	<div class="notice notice-info inline" style="margin:12px 0;padding:8px 12px;">
		<p style="margin:0;">
			<?php esc_html_e( 'Open the Help panel (tab at the top of this screen) for a full guide to each column and how to change tiers.', 'lpnw-alerts' ); ?>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Subscribers with a preferences row. New signups get default NW listing coverage until they save the form; Setup shows whether they have clicked Save at least once. Tier follows WooCommerce when Pro or VIP is paid; otherwise use the user profile override.', 'lpnw-alerts' ); ?>
	</p>

	<?php if ( $no_profile_count > 0 && '' === $search ) : ?>
		<div class="notice notice-warning inline" style="margin:12px 0;padding:10px 12px;">
			<p style="margin:0 0 8px;">
				<?php
				printf(
					/* translators: %d: number of WordPress users without lpnw_subscriber_preferences row */
					esc_html__( '%d registered user(s) have no alert profile yet (never hit preferences or dashboard after deploy). They will get defaults on next login.', 'lpnw-alerts' ),
					$no_profile_count
				);
				?>
			</p>
			<?php if ( ! empty( $no_profile_sample ) ) : ?>
				<ul style="margin:0;padding-left:18px;">
					<?php foreach ( $no_profile_sample as $u ) : ?>
						<li>
							<a href="<?php echo esc_url( get_edit_user_link( (int) $u->ID ) ); ?>"><?php echo esc_html( (string) $u->user_email ); ?></a>
							<span class="description"> — <?php echo esc_html( (string) $u->user_registered ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

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
				<th scope="col">
					<?php esc_html_e( 'Tier', 'lpnw-alerts' ); ?>
					<?php
					LPNW_Admin_Help::tip_icon(
						__( 'Tier used for sending alerts. Paid Pro or VIP from WooCommerce always wins over a profile override.', 'lpnw-alerts' )
					);
					?>
				</th>
				<th scope="col">
					<?php esc_html_e( 'From billing', 'lpnw-alerts' ); ?>
					<?php
					LPNW_Admin_Help::tip_icon(
						__( 'Paid tier from active WooCommerce Subscriptions when enabled in settings, otherwise from completed or processing orders with Pro/VIP products. Ignores admin comps.', 'lpnw-alerts' )
					);
					?>
				</th>
				<th scope="col">
					<?php esc_html_e( 'Override', 'lpnw-alerts' ); ?>
					<?php
					LPNW_Admin_Help::tip_icon(
						__( 'Optional comp or trial set under Users: LPNW alert tier. Only applies when there is no qualifying paid Pro/VIP order.', 'lpnw-alerts' )
					);
					?>
				</th>
				<th scope="col"><?php esc_html_e( 'Frequency', 'lpnw-alerts' ); ?></th>
				<th scope="col">
					<?php esc_html_e( 'Setup', 'lpnw-alerts' ); ?>
					<?php
					LPNW_Admin_Help::tip_icon(
						__( 'Done = user clicked Save on the preferences form at least once. Pending = still on default onboarding path.', 'lpnw-alerts' )
					);
					?>
				</th>
				<th scope="col"><?php esc_html_e( 'Active', 'lpnw-alerts' ); ?></th>
				<th scope="col">
					<?php esc_html_e( 'Orders', 'lpnw-alerts' ); ?>
					<?php
					LPNW_Admin_Help::tip_icon(
						__( 'Opens WooCommerce orders for this customer. Use refunds or new orders to change paid tier.', 'lpnw-alerts' )
					);
					?>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr>
					<td colspan="8"><?php esc_html_e( 'No subscribers match your search.', 'lpnw-alerts' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $items as $row ) : ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php echo esc_html( $row['display_name'] ?: $row['email'] ); ?></a></strong><br />
							<span class="description"><?php echo esc_html( $row['email'] ); ?></span>
						</td>
						<td><strong><?php echo esc_html( strtoupper( $row['tier'] ) ); ?></strong></td>
						<td><?php echo esc_html( strtoupper( $row['from_billing'] ) ); ?></td>
						<td><?php echo $row['override'] ? esc_html( strtoupper( $row['override'] ) ) : '—'; ?></td>
						<td><?php echo esc_html( $row['frequency'] ); ?></td>
						<td>
							<?php if ( ! empty( $row['setup_complete'] ) ) : ?>
								<span style="color:#059669;"><?php esc_html_e( 'Done', 'lpnw-alerts' ); ?></span>
							<?php elseif ( ! empty( $row['redirect_pending'] ) ) : ?>
								<?php esc_html_e( 'Pending', 'lpnw-alerts' ); ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Defaults', 'lpnw-alerts' ); ?></span>
							<?php endif; ?>
						</td>
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
