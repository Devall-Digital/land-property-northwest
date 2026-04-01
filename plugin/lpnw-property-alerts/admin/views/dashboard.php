<?php
/**
 * Admin dashboard overview page.
 *
 * Variables supplied by LPNW_Admin::render_dashboard(): $snapshot, $feed_log,
 * $cron_rows, $mautic, $wp_cron_off, $alerts_queued, $alerts_sent_all.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string, mixed> $snapshot */
/** @var array<string, mixed> $feed_log */
/** @var array<string, array{label: string, next: int|false}> $cron_rows */
/** @var array{ok: bool, message: string, code: int|null} $mautic */
/** @var bool $wp_cron_off */
/** @var int $alerts_queued */
/** @var int $alerts_sent_all */

$ob  = $feed_log['orderby'];
$ord = $feed_log['order'];

/**
 * Sortable column header link with direction indicator.
 *
 * @param string $column   Orderby key.
 * @param string $label    Header text.
 */
$lpnw_render_sort_th = static function ( string $column, string $label ) use ( $ob, $ord ): void {
	$url   = LPNW_Admin::feed_log_sort_url( $column, $ob, $ord );
	$arrow = '';
	if ( $column === $ob ) {
		$sym   = ( 'ASC' === $ord ) ? '▲' : '▼';
		$arrow = ' <span aria-hidden="true">' . esc_html( $sym ) . '</span>';
	}
	printf(
		'<th scope="col" class="manage-column column-%1$s sortable %2$s"><a href="%3$s"><span>%4$s</span><span class="sorting-indicators">%5$s</span></a></th>',
		esc_attr( $column ),
		esc_attr( $column === $ob ? 'sorted' : '' ),
		esc_url( $url ),
		esc_html( $label ),
		$arrow // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $arrow is built with esc_html( $sym ).
	);
};

$total_pages = (int) ceil( (int) $feed_log['total'] / (int) $feed_log['per_page'] );
$pagination  = '';
if ( $total_pages > 1 ) {
	$pagination = paginate_links(
		array(
			'base'      => add_query_arg(
				array(
					'paged'   => '%#%',
					'orderby' => $ob,
					'order'   => $ord,
				),
				admin_url( 'admin.php?page=lpnw-dashboard' )
			),
			'format'    => '',
			'current'   => (int) $feed_log['paged'],
			'total'     => $total_pages,
			'prev_text' => __( '&laquo; Previous', 'lpnw-alerts' ),
			'next_text' => __( 'Next &raquo;', 'lpnw-alerts' ),
			'type'      => 'plain',
		)
	);
}
?>

<div class="wrap lpnw-admin-dashboard">
	<h1><?php esc_html_e( 'LPNW Property Alerts', 'lpnw-alerts' ); ?></h1>

	<div class="metabox-holder" style="margin-top:18px;">
		<div class="postbox" style="max-width:1200px;">
			<h2 class="hndle"><span><?php esc_html_e( 'Overview', 'lpnw-alerts' ); ?></span></h2>
			<div class="inside">
				<div class="lpnw-stat-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">
					<div class="card" style="margin:0;padding:12px;">
						<h3 style="margin:0 0 6px;font-size:1.6em;line-height:1.2;"><?php echo esc_html( number_format( (int) $snapshot['property_count'] ) ); ?></h3>
						<p style="margin:0;color:#646970;"><?php esc_html_e( 'Properties tracked', 'lpnw-alerts' ); ?></p>
					</div>
					<div class="card" style="margin:0;padding:12px;">
						<h3 style="margin:0 0 6px;font-size:1.6em;line-height:1.2;"><?php echo esc_html( number_format( (int) $snapshot['properties_24h'] ) ); ?></h3>
						<p style="margin:0;color:#646970;"><?php esc_html_e( 'Added in last 24 hours', 'lpnw-alerts' ); ?></p>
					</div>
					<div class="card" style="margin:0;padding:12px;">
						<h3 style="margin:0 0 6px;font-size:1.6em;line-height:1.2;"><?php echo esc_html( number_format( (int) $snapshot['subscriber_count'] ) ); ?></h3>
						<p style="margin:0;color:#646970;"><?php esc_html_e( 'Active subscribers', 'lpnw-alerts' ); ?></p>
					</div>
					<div class="card" style="margin:0;padding:12px;">
						<h3 style="margin:0 0 6px;font-size:1.6em;line-height:1.2;"><?php echo esc_html( number_format( (int) $snapshot['alerts_sent_today'] ) ); ?></h3>
						<p style="margin:0;color:#646970;"><?php esc_html_e( 'Alerts sent today', 'lpnw-alerts' ); ?></p>
					</div>
					<div class="card" style="margin:0;padding:12px;">
						<h3 style="margin:0 0 6px;font-size:1.6em;line-height:1.2;"><?php echo esc_html( number_format( $alerts_sent_all ) ); ?></h3>
						<p style="margin:0;color:#646970;"><?php esc_html_e( 'Alerts sent (all time)', 'lpnw-alerts' ); ?></p>
					</div>
					<div class="card" style="margin:0;padding:12px;">
						<h3 style="margin:0 0 6px;font-size:1.6em;line-height:1.2;"><?php echo esc_html( number_format( $alerts_queued ) ); ?></h3>
						<p style="margin:0;color:#646970;"><?php esc_html_e( 'Alerts queued', 'lpnw-alerts' ); ?></p>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;padding-top:16px;border-top:1px solid #dcdcde;">
					<?php wp_nonce_field( LPNW_Admin::FEED_RUN_NONCE_ACTION ); ?>
					<input type="hidden" name="action" value="lpnw_run_feed" />
					<input type="hidden" name="feed" value="rightmove" />
					<input type="hidden" name="lpnw_redirect_to" value="lpnw" />
					<?php
					submit_button(
						__( 'Run Rightmove feed now', 'lpnw-alerts' ),
						'secondary',
						'submit',
						false
					);
					?>
					<p class="description" style="margin-top:8px;">
						<?php esc_html_e( 'Runs one Rightmove batch (same as the portal cron). Use for a quick refresh after changing data.', 'lpnw-alerts' ); ?>
					</p>
				</form>
			</div>
		</div>

		<div class="postbox" style="max-width:1200px;">
			<h2 class="hndle"><span><?php esc_html_e( 'System status', 'lpnw-alerts' ); ?></span></h2>
			<div class="inside">
				<table class="widefat striped" style="max-width:720px;">
					<tbody>
						<tr>
							<th scope="row" style="width:220px;"><?php esc_html_e( 'DISABLE_WP_CRON', 'lpnw-alerts' ); ?></th>
							<td>
								<?php if ( $wp_cron_off ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color:#00a32a;" aria-hidden="true"></span>
									<?php esc_html_e( 'Defined and true (use server or external cron to hit wp-cron.php).', 'lpnw-alerts' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-warning" style="color:#dba617;" aria-hidden="true"></span>
									<?php esc_html_e( 'Not set or false (WordPress will trigger cron on page loads).', 'lpnw-alerts' ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Mautic API', 'lpnw-alerts' ); ?></th>
							<td>
								<?php if ( ! empty( $mautic['ok'] ) ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color:#00a32a;" aria-hidden="true"></span>
									<?php echo esc_html( $mautic['message'] ); ?>
									<?php if ( null !== $mautic['code'] ) : ?>
										<?php echo ' '; ?>
										<code><?php echo esc_html( (string) $mautic['code'] ); ?></code>
									<?php endif; ?>
								<?php else : ?>
									<span class="dashicons dashicons-dismiss" style="color:#d63638;" aria-hidden="true"></span>
									<?php echo esc_html( $mautic['message'] ); ?>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<h3 style="margin:16px 0 8px;"><?php esc_html_e( 'Next scheduled cron runs', 'lpnw-alerts' ); ?></h3>
				<table class="widefat striped" style="max-width:720px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Job', 'lpnw-alerts' ); ?></th>
							<th><?php esc_html_e( 'Next run (site time)', 'lpnw-alerts' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cron_rows as $hook => $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['label'] ); ?></td>
								<td>
									<?php
									if ( ! empty( $row['next'] ) && is_int( $row['next'] ) ) {
										echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['next'] ) );
									} else {
										esc_html_e( 'Not scheduled', 'lpnw-alerts' );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="postbox" style="max-width:1200px;">
			<h2 class="hndle"><span><?php esc_html_e( 'Feed activity log', 'lpnw-alerts' ); ?></span></h2>
			<div class="inside">
				<p class="description" style="margin-top:0;">
					<?php
					printf(
						/* translators: 1: total rows, 2: per page */
						esc_html__( 'Showing %1$s entries (up to %2$s per page). Click a column header to sort.', 'lpnw-alerts' ),
						esc_html( number_format( (int) $feed_log['total'] ) ),
						esc_html( (string) (int) $feed_log['per_page'] )
					);
					?>
				</p>

				<table class="wp-list-table widefat fixed striped table-view-list lpnw-feed-log-table">
					<thead>
						<tr>
							<?php $lpnw_render_sort_th( 'feed_name', __( 'Feed', 'lpnw-alerts' ) ); ?>
							<?php $lpnw_render_sort_th( 'started_at', __( 'Started', 'lpnw-alerts' ) ); ?>
							<?php $lpnw_render_sort_th( 'completed_at', __( 'Completed', 'lpnw-alerts' ) ); ?>
							<?php $lpnw_render_sort_th( 'status', __( 'Status', 'lpnw-alerts' ) ); ?>
							<?php $lpnw_render_sort_th( 'properties_found', __( 'Found', 'lpnw-alerts' ) ); ?>
							<?php $lpnw_render_sort_th( 'properties_new', __( 'New', 'lpnw-alerts' ) ); ?>
							<?php $lpnw_render_sort_th( 'errors', __( 'Errors', 'lpnw-alerts' ) ); ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $feed_log['items'] ) ) : ?>
							<tr>
								<td colspan="7"><?php esc_html_e( 'No feed activity yet. Feeds will run automatically via cron.', 'lpnw-alerts' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $feed_log['items'] as $feed ) : ?>
								<?php
								$errors      = $feed->errors ? json_decode( $feed->errors, true ) : array();
								$error_count = is_array( $errors ) ? count( $errors ) : 0;
								?>
								<tr>
									<td><strong><?php echo esc_html( $feed->feed_name ); ?></strong></td>
									<td><?php echo esc_html( $feed->started_at ); ?></td>
									<td><?php echo esc_html( $feed->completed_at ? $feed->completed_at : '-' ); ?></td>
									<td>
										<?php if ( 'completed' === $feed->status ) : ?>
											<span style="color:#00a32a;">&#10003; <?php esc_html_e( 'Completed', 'lpnw-alerts' ); ?></span>
										<?php elseif ( 'running' === $feed->status ) : ?>
											<span style="color:#dba617;">&#8635; <?php esc_html_e( 'Running', 'lpnw-alerts' ); ?></span>
										<?php else : ?>
											<span style="color:#d63638;">&#10007; <?php esc_html_e( 'Failed', 'lpnw-alerts' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( (string) (int) $feed->properties_found ); ?></td>
									<td><?php echo esc_html( (string) (int) $feed->properties_new ); ?></td>
									<td><?php echo $error_count ? esc_html( (string) $error_count ) : esc_html( '-' ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ( $pagination ) : ?>
					<div class="tablenav bottom" style="margin-top:12px;">
						<div class="tablenav-pages">
							<?php echo wp_kses_post( $pagination ); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
