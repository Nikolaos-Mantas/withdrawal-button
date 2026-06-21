<?php
/**
 * Form confirmation step template.
 *
 * @var array<string, string> $fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h3 class="wb-title"><?php esc_html_e( 'Confirm Withdrawal Request', WB_TEXT_DOMAIN ); ?></h3>
<p><strong><?php echo esc_html( sprintf( __( 'Are you sure you want to submit a withdrawal request for order #%s?', WB_TEXT_DOMAIN ), $fields['order_number'] ) ); ?></strong></p>

<dl class="wb-summary">
	<dt><?php esc_html_e( 'Full name', WB_TEXT_DOMAIN ); ?></dt><dd><?php echo esc_html( $fields['name'] ); ?></dd>
	<dt><?php esc_html_e( 'Email', WB_TEXT_DOMAIN ); ?></dt><dd><?php echo esc_html( $fields['email'] ); ?></dd>
	<dt><?php esc_html_e( 'Order', WB_TEXT_DOMAIN ); ?></dt><dd>#<?php echo esc_html( $fields['order_number'] ); ?></dd>
	<?php if ( $fields['store'] ) : ?>
		<dt><?php esc_html_e( 'Store', WB_TEXT_DOMAIN ); ?></dt><dd><?php echo esc_html( $fields['store'] ); ?></dd>
	<?php endif; ?>
	<dt><?php esc_html_e( 'Products', WB_TEXT_DOMAIN ); ?></dt><dd><?php echo nl2br( esc_html( $fields['products'] ) ); ?></dd>
	<?php if ( $fields['message'] ) : ?>
		<dt><?php esc_html_e( 'Message', WB_TEXT_DOMAIN ); ?></dt><dd><?php echo nl2br( esc_html( $fields['message'] ) ); ?></dd>
	<?php endif; ?>
</dl>

<form method="post" action="" class="wb-inline-form">
	<?php wp_nonce_field( 'wb_submit', 'wb_nonce2' ); ?>
	<input type="hidden" name="wb_step" value="submit">
	<input type="hidden" name="wb_name" value="<?php echo esc_attr( $fields['name'] ); ?>">
	<input type="hidden" name="wb_email" value="<?php echo esc_attr( $fields['email'] ); ?>">
	<input type="hidden" name="wb_order" value="<?php echo esc_attr( $fields['order_number'] ); ?>">
	<input type="hidden" name="wb_store" value="<?php echo esc_attr( $fields['store'] ); ?>">
	<input type="hidden" name="wb_products" value="<?php echo esc_attr( $fields['products'] ); ?>">
	<input type="hidden" name="wb_message" value="<?php echo esc_attr( $fields['message'] ); ?>">
	<input type="hidden" name="wb_wc_order_id" value="<?php echo esc_attr( (string) $fields['wc_order_id'] ); ?>">
	<input type="hidden" name="wb_privacy_consent_at" value="<?php echo esc_attr( $fields['privacy_consent_at'] ?? '' ); ?>">
	<input type="hidden" name="wb_declare_consent_at" value="<?php echo esc_attr( $fields['declare_consent_at'] ?? '' ); ?>">
	<input type="hidden" name="wb_privacy_policy_url" value="<?php echo esc_attr( $fields['privacy_policy_url'] ?? '' ); ?>">
	<input type="hidden" name="wb_privacy_consent_version" value="<?php echo esc_attr( $fields['privacy_consent_version'] ?? '' ); ?>">
	<input type="hidden" name="wb_declare" value="1">
	<input type="hidden" name="wb_privacy" value="1">
	<button type="submit" class="wb-btn"><?php esc_html_e( 'Submit Withdrawal', WB_TEXT_DOMAIN ); ?></button>
</form>
<form method="post" action="" class="wb-inline-form">
	<input type="hidden" name="wb_step" value="">
	<input type="hidden" name="wb_name" value="<?php echo esc_attr( $fields['name'] ); ?>">
	<input type="hidden" name="wb_email" value="<?php echo esc_attr( $fields['email'] ); ?>">
	<input type="hidden" name="wb_order" value="<?php echo esc_attr( $fields['order_number'] ); ?>">
	<input type="hidden" name="wb_store" value="<?php echo esc_attr( $fields['store'] ); ?>">
	<input type="hidden" name="wb_products" value="<?php echo esc_attr( $fields['products'] ); ?>">
	<input type="hidden" name="wb_message" value="<?php echo esc_attr( $fields['message'] ); ?>">
	<input type="hidden" name="wb_wc_order_id" value="<?php echo esc_attr( (string) $fields['wc_order_id'] ); ?>">
	<button type="submit" class="wb-btn wb-btn-secondary"><?php esc_html_e( 'Back / Edit', WB_TEXT_DOMAIN ); ?></button>
</form>
