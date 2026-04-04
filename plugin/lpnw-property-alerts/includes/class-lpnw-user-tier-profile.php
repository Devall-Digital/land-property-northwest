<?php
/**
 * User profile: admin tier override (only when no paid Pro/VIP order applies).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers user edit fields for support and comps.
 */
final class LPNW_User_Tier_Profile {

	public static function init(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_fields' ) );
	}

	/**
	 * @param WP_User $user User being edited.
	 */
	public static function render_fields( WP_User $user ): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$from_orders = class_exists( 'LPNW_Subscriber' ) ? LPNW_Subscriber::get_tier_from_orders( (int) $user->ID ) : 'free';
		$current     = get_user_meta( $user->ID, LPNW_Subscriber::USER_META_ADMIN_TIER_OVERRIDE, true );
		$current     = is_string( $current ) ? $current : '';

		$disabled_note = '';
		if ( in_array( $from_orders, array( 'pro', 'vip' ), true ) ) {
			$disabled_note = __( 'This user has a qualifying paid order; tier follows WooCommerce until that changes.', 'lpnw-alerts' );
		}

		wp_nonce_field( 'lpnw_save_tier_override', 'lpnw_tier_override_nonce' );
		?>
		<h2 id="lpnw-tier-override"><?php esc_html_e( 'LPNW alert tier (support)', 'lpnw-alerts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Tier from orders', 'lpnw-alerts' ); ?></th>
				<td>
					<strong><?php echo esc_html( strtoupper( $from_orders ) ); ?></strong>
					<?php if ( '' !== $disabled_note ) : ?>
						<p class="description"><?php echo esc_html( $disabled_note ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lpnw_admin_tier_override"><?php esc_html_e( 'Admin tier override', 'lpnw-alerts' ); ?></label>
				</th>
				<td>
					<select name="lpnw_admin_tier_override" id="lpnw_admin_tier_override" <?php disabled( in_array( $from_orders, array( 'pro', 'vip' ), true ) ); ?>>
						<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'None (use orders only)', 'lpnw-alerts' ); ?></option>
						<option value="free" <?php selected( $current, 'free' ); ?>><?php esc_html_e( 'Free', 'lpnw-alerts' ); ?></option>
						<option value="pro" <?php selected( $current, 'pro' ); ?>><?php esc_html_e( 'Pro (comp / trial)', 'lpnw-alerts' ); ?></option>
						<option value="vip" <?php selected( $current, 'vip' ); ?>><?php esc_html_e( 'VIP (comp / trial)', 'lpnw-alerts' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Use for trials and support. Pro or VIP from a completed or processing paid order always wins and cannot be downgraded here.', 'lpnw-alerts' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param int $user_id User ID.
	 */
	public static function save_fields( int $user_id ): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! isset( $_POST['lpnw_tier_override_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lpnw_tier_override_nonce'] ) ), 'lpnw_save_tier_override' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$raw = isset( $_POST['lpnw_admin_tier_override'] ) ? sanitize_key( wp_unslash( $_POST['lpnw_admin_tier_override'] ) ) : '';

		$from_orders = class_exists( 'LPNW_Subscriber' ) ? LPNW_Subscriber::get_tier_from_orders( $user_id ) : 'free';
		if ( in_array( $from_orders, array( 'pro', 'vip' ), true ) ) {
			return;
		}

		if ( '' === $raw ) {
			delete_user_meta( $user_id, LPNW_Subscriber::USER_META_ADMIN_TIER_OVERRIDE );
			return;
		}

		if ( in_array( $raw, array( 'free', 'pro', 'vip' ), true ) ) {
			update_user_meta( $user_id, LPNW_Subscriber::USER_META_ADMIN_TIER_OVERRIDE, $raw );
		}
	}
}
