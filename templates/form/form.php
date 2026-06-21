<?php
/**
 * Form step 1 template.
 *
 * @var array<string, mixed>       $settings
 * @var array<string, string>      $fields
 * @var array<int, string>         $errors
 * @var array<int, string>         $stores
 * @var string                     $captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$privacy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
$policy_url  = $settings['policy_page_id'] ? get_permalink( (int) $settings['policy_page_id'] ) : '';
$intro       = str_replace( '{days}', (int) $settings['withdrawal_days'], $settings['form_intro'] );

if ( $errors ) {
	echo '<div class="wb-error"><ul>';
	foreach ( $errors as $e ) {
		echo '<li>' . esc_html( $e ) . '</li>';
	}
	echo '</ul></div>';
}
?>
<h3 class="wb-title"><?php esc_html_e( 'Exercise Right of Withdrawal', WB_TEXT_DOMAIN ); ?></h3>
<p class="wb-intro"><?php echo esc_html( $intro ); ?></p>
<?php if ( $policy_url ) : ?>
	<p><a href="<?php echo esc_url( $policy_url ); ?>"><?php esc_html_e( 'View Returns Policy', WB_TEXT_DOMAIN ); ?></a></p>
<?php endif; ?>

<form method="post" action="" class="wb-form" id="wb-withdrawal-form">
	<?php wp_nonce_field( 'wb_form', 'wb_nonce' ); ?>
	<input type="hidden" name="wb_step" value="confirm">
	<input type="hidden" name="wb_loaded_at" value="<?php echo esc_attr( time() ); ?>">
	<input type="hidden" name="wb_wc_order_id" id="wb_wc_order_id" value="<?php echo esc_attr( (string) $fields['wc_order_id'] ); ?>">
	<p class="wb-hp"><label class="wb-hp-label" for="wb_website"><?php esc_html_e( 'Website', WB_TEXT_DOMAIN ); ?></label><input type="text" name="wb_website" id="wb_website" tabindex="-1" autocomplete="off"></p>

	<label for="wb_name"><?php esc_html_e( 'Full name', WB_TEXT_DOMAIN ); ?> <span class="wb-required">*</span></label>
	<input type="text" name="wb_name" id="wb_name" required value="<?php echo esc_attr( $fields['name'] ); ?>">

	<label for="wb_email"><?php esc_html_e( 'Email', WB_TEXT_DOMAIN ); ?> <span class="wb-required">*</span></label>
	<input type="email" name="wb_email" id="wb_email" required value="<?php echo esc_attr( $fields['email'] ); ?>">

	<label for="wb_order"><?php esc_html_e( 'Order number', WB_TEXT_DOMAIN ); ?> <span class="wb-required">*</span></label>
	<input type="text" name="wb_order" id="wb_order" required value="<?php echo esc_attr( $fields['order_number'] ); ?>">
	<?php if ( wb_is_woocommerce_enabled() && $settings['woo_autofill'] ) : ?>
		<p class="description wb-order-hint"><?php esc_html_e( 'Enter your order number to auto-fill your details.', WB_TEXT_DOMAIN ); ?></p>
	<?php endif; ?>

	<?php if ( $stores ) : ?>
		<label for="wb_store"><?php esc_html_e( 'Which online store did you purchase from?', WB_TEXT_DOMAIN ); ?> <span class="wb-required">*</span></label>
		<select name="wb_store" id="wb_store" required>
			<option value=""><?php esc_html_e( '— Select store —', WB_TEXT_DOMAIN ); ?></option>
			<?php foreach ( $stores as $st ) : ?>
				<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $fields['store'], $st ); ?>><?php echo esc_html( $st ); ?></option>
			<?php endforeach; ?>
		</select>
	<?php endif; ?>

	<label for="wb_products"><?php esc_html_e( 'Products included in withdrawal', WB_TEXT_DOMAIN ); ?> <span class="wb-required">*</span></label>
	<textarea name="wb_products" id="wb_products" rows="3" required placeholder="<?php esc_attr_e( 'e.g. 1x Product Name, 2x Another Product', WB_TEXT_DOMAIN ); ?>"><?php echo esc_textarea( $fields['products'] ); ?></textarea>

	<label for="wb_message"><?php esc_html_e( 'Message (optional)', WB_TEXT_DOMAIN ); ?></label>
	<textarea name="wb_message" id="wb_message" rows="3"><?php echo esc_textarea( $fields['message'] ); ?></textarea>

	<div class="wb-checkbox">
		<input type="checkbox" name="wb_declare" id="wb_declare" value="1" required>
		<label for="wb_declare"><?php esc_html_e( 'I declare that I wish to exercise my right of withdrawal for the above order.', WB_TEXT_DOMAIN ); ?> <span class="wb-required">*</span></label>
	</div>

	<div class="wb-checkbox">
		<input type="checkbox" name="wb_privacy" id="wb_privacy" value="1" required>
		<label for="wb_privacy">
			<?php esc_html_e( 'I have read and accept the', WB_TEXT_DOMAIN ); ?>
			<?php if ( $privacy_url ) : ?>
				<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy Policy', WB_TEXT_DOMAIN ); ?></a>.
			<?php else : ?>
				<?php esc_html_e( 'Privacy Policy.', WB_TEXT_DOMAIN ); ?>
			<?php endif; ?>
			<span class="wb-required">*</span>
		</label>
	</div>

	<?php if ( $captcha ) : ?>
		<div class="wb-captcha"><?php echo $captcha; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php endif; ?>

	<button type="submit" class="wb-btn"><?php esc_html_e( 'Continue to Confirmation', WB_TEXT_DOMAIN ); ?></button>
</form>
