<?php
/**
 * Alert delivery log page.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$page     = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$per_page = 50;
$offset   = ( $page - 1 ) * $per_page;

$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue" );

$alerts = $wpdb->get_results( $wpdb->prepare(
	"SELECT aq.*, p.address, p.postcode, p.source as property_source, u.user_email
	 FROM {$wpdb->prefix}lpnw_alert_queue aq
	 LEFT JOIN {$wpdb->prefix}lpnw_properties p ON p.id = aq.property_id
	 LEFT JOIN {$wpdb->prefix}lpnw_subscriber_preferences sp ON sp.id = aq.subscriber_id
	 LEFT JOIN {$wpdb->users} u ON u.ID = sp.user_id
	 ORDER BY aq.queued_at DESC
	 LIMIT %d OFFSET %d",
	$per_page,
	$offset
) );

$total_pages = ceil( $total / $per_page );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Alert Delivery Log', 'lpnw-alerts' ); ?></h1>

	<p>
		<?php
		printf(
			esc_html__( 'Showing %1$d of %2$d total alerts.', 'lpnw-alerts' ),
			count( $alerts ),
			$total
		);
		?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Subscriber', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Property', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Tier', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Status', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Queued', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Sent', 'lpnw-alerts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $alerts ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No alerts in the queue yet.', 'lpnw-alerts' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $alerts as $alert ) : ?>
					<tr>
						<td><?php echo esc_html( $alert->id ); ?></td>
						<td><?php echo esc_html( $alert->user_email ?? 'Unknown' ); ?></td>
						<td><?php echo esc_html( ( $alert->address ?? '' ) . ' ' . ( $alert->postcode ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( strtoupper( $alert->tier ) ); ?></code></td>
						<td>
							<?php if ( 'sent' === $alert->status ) : ?>
								<span style="color:#059669;">Sent</span>
							<?php elseif ( 'queued' === $alert->status ) : ?>
								<span style="color:#D97706;">Queued</span>
							<?php elseif ( 'skipped' === $alert->status ) : ?>
								<span style="color:#6B7280;"><?php esc_html_e( 'Skipped (alerts off)', 'lpnw-alerts' ); ?></span>
							<?php else : ?>
								<span style="color:#DC2626;">Failed</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $alert->queued_at ); ?></td>
						<td><?php echo $alert->sent_at ? esc_html( $alert->sent_at ) : '-'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $page,
					'total'   => $total_pages,
				) );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
