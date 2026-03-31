<?php
/**
 * Feed status page.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$feeds = $wpdb->get_results(
	"SELECT feed_name,
		COUNT(*) as total_runs,
		MAX(started_at) as last_run,
		SUM(properties_new) as total_new,
		SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_runs
	 FROM {$wpdb->prefix}lpnw_feed_log
	 GROUP BY feed_name
	 ORDER BY feed_name ASC"
);
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Feed Status', 'lpnw-alerts' ); ?></h1>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Feed', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Total Runs', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Last Run', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Total New Properties', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Failed Runs', 'lpnw-alerts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $feeds ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No feeds have run yet.', 'lpnw-alerts' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $feeds as $feed ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $feed->feed_name ); ?></strong></td>
						<td><?php echo esc_html( $feed->total_runs ); ?></td>
						<td><?php echo esc_html( $feed->last_run ); ?></td>
						<td><?php echo esc_html( number_format( $feed->total_new ) ); ?></td>
						<td>
							<?php if ( $feed->failed_runs > 0 ) : ?>
								<span style="color:#DC2626;"><?php echo esc_html( $feed->failed_runs ); ?></span>
							<?php else : ?>
								0
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
