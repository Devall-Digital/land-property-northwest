<?php
/**
 * How it works: three steps (monitor, match, alert).
 *
 * Optional variables:
 * - string $section_id    Default lpnw-how-it-works.
 * - string $section_title Default heading.
 * - array  $steps_override Optional list of arrays with keys: title, text.
 *
 * @package LPNW_Theme
 */

defined( 'ABSPATH' ) || exit;

$section_id    = isset( $section_id ) ? sanitize_html_class( $section_id ) : 'lpnw-how-it-works';
$section_title = isset( $section_title ) ? $section_title : __( 'How it works', 'lpnw-theme' );

$steps = array(
	array(
		'title' => __( 'We monitor', 'lpnw-theme' ),
		'text'  => __( 'We pull public data every day: planning portals across the Northwest, EPC register, Land Registry sales, and major auction houses. One pipeline, full coverage.', 'lpnw-theme' ),
	),
	array(
		'title' => __( 'We match', 'lpnw-theme' ),
		'text'  => __( 'You set your patch, budget, property type, and which sources matter. We only surface listings and applications that fit. No noise.', 'lpnw-theme' ),
	),
	array(
		'title' => __( 'You get alerted', 'lpnw-theme' ),
		'text'  => __( 'Pro and VIP subscribers get instant email the moment something new appears. Free tier gets a weekly digest so you can see the value, then upgrade when you are ready.', 'lpnw-theme' ),
	),
);

if ( isset( $steps_override ) && is_array( $steps_override ) ) {
	$steps = $steps_override;
}
?>
<section class="lpnw-how-it-works" id="<?php echo esc_attr( $section_id ); ?>" aria-labelledby="<?php echo esc_attr( $section_id ); ?>-title">
	<h2 id="<?php echo esc_attr( $section_id ); ?>-title" class="lpnw-how-it-works__title"><?php echo esc_html( $section_title ); ?></h2>
	<div class="lpnw-steps">
		<?php foreach ( $steps as $index => $step ) : ?>
			<?php
			$step_num   = (int) $index + 1;
			$step_title = isset( $step['title'] ) ? $step['title'] : '';
			$step_text  = isset( $step['text'] ) ? $step['text'] : '';
			$step_id    = $section_id . '-step-' . $step_num;
			?>
			<div class="lpnw-step">
				<div class="lpnw-step__number" aria-hidden="true"><?php echo esc_html( (string) $step_num ); ?></div>
				<h3 id="<?php echo esc_attr( $step_id ); ?>" class="lpnw-step__title"><?php echo esc_html( $step_title ); ?></h3>
				<p class="lpnw-step__text"><?php echo esc_html( $step_text ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
