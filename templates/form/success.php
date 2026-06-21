<?php
/**
 * Success message template.
 *
 * @var array<string, mixed> $settings
 * @var int                    $request_id
 * @var array<string, string>  $fields
 * @var string                 $date
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wb-success">
	<strong><?php echo esc_html( $settings['success_message'] ); ?></strong><br>
	<?php
	echo esc_html(
		sprintf(
			/* translators: 1: request ID, 2: order number, 3: date */
			__( 'Request ID: %1$d · Order #%2$s · %3$s', WB_TEXT_DOMAIN ),
			$request_id,
			$fields['order_number'],
			$date
		)
	);
	?>
</div>
