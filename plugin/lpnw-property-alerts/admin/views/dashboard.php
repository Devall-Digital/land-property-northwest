<?php
/**
 * Admin dashboard overview page.
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$property_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_properties" );
$subscriber_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_subscriber_preferences WHERE is_active = 1" );
$alerts_sent     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'sent'" );
$alerts_queued   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}lpnw_alert_queue WHERE status = 'queued'" );

$recent_feeds = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}lpnw_feed_log ORDER BY started_at DESC LIMIT 10"
);
?>

<div class="wrap">
	<h1><?php esc_html_e( 'LPNW Property Alerts', 'lpnw-alerts' ); ?></h1>

	<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin:24px 0;">
		<div class="card" style="padding:16px;">
			<h3 style="margin:0 0 8px;"><?php echo esc_html( number_format( $property_count ) ); ?></h3>
			<p style="margin:0; color:#666;">Properties tracked</p>
		</div>
		<div class="card" style="padding:16px;">
			<h3 style="margin:0 0 8px;"><?php echo esc_html( number_format( $subscriber_count ) ); ?></h3>
			<p style="margin:0; color:#666;">Active subscribers</p>
		</div>
		<div class="card" style="padding:16px;">
			<h3 style="margin:0 0 8px;"><?php echo esc_html( number_format( $alerts_sent ) ); ?></h3>
			<p style="margin:0; color:#666;">Alerts sent</p>
		</div>
		<div class="card" style="padding:16px;">
			<h3 style="margin:0 0 8px;"><?php echo esc_html( number_format( $alerts_queued ) ); ?></h3>
			<p style="margin:0; color:#666;">Alerts queued</p>
		</div>
	</div>

	<h2><?php esc_html_e( 'Recent Feed Activity', 'lpnw-alerts' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Feed', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Started', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Status', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Found', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'New', 'lpnw-alerts' ); ?></th>
				<th><?php esc_html_e( 'Errors', 'lpnw-alerts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $recent_feeds ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No feed activity yet. Feeds will run automatically via cron.', 'lpnw-alerts' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $recent_feeds as $feed ) : ?>
					<?php
					$errors      = $feed->errors ? json_decode( $feed->errors, true ) : array();
					$error_count = count( $errors );
					?>
					<tr>
						<td><strong><?php echo esc_html( $feed->feed_name ); ?></strong></td>
						<td><?php echo esc_html( $feed->started_at ); ?></td>
						<td>
							<?php if ( 'completed' === $feed->status ) : ?>
								<span style="color:#059669;">&#10003; Completed</span>
							<?php elseif ( 'running' === $feed->status ) : ?>
								<span style="color:#D97706;">&#8635; Running</span>
							<?php else : ?>
								<span style="color:#DC2626;">&#10007; Failed</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $feed->properties_found ); ?></td>
						<td><?php echo esc_html( $feed->properties_new ); ?></td>
						<td><?php echo $error_count ? esc_html( $error_count . ' errors' ) : '-'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
