<?php
/**
 * Customer confirmation email content.
 *
 * @var string               $subject
 * @var string               $body
 * @var array<string, string> $repl
 * @var array<string, mixed>  $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 style="margin:0 0 16px;color:<?php echo esc_attr( $settings['color_primary'] ); ?>;"><?php esc_html_e( 'Withdrawal Confirmation', WB_TEXT_DOMAIN ); ?></h2>
<div><?php echo wp_kses_post( nl2br( esc_html( $body ) ) ); ?></div>
