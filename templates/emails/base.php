<?php
/**
 * HTML email base wrapper.
 *
 * @var string               $subject
 * @var string               $content
 * @var array<string, mixed> $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$primary = esc_attr( $settings['color_primary'] );
$text    = esc_attr( $settings['color_text'] );
$bg      = esc_attr( $settings['color_background'] );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $subject ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:<?php echo $text; ?>;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f4;padding:24px 0;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:<?php echo $bg; ?>;border-radius:8px;overflow:hidden;border:1px solid #e5e5e5;">
					<tr>
						<td style="background:<?php echo $primary; ?>;padding:20px 24px;text-align:center;">
							<?php if ( ! empty( $settings['logo_url'] ) ) : ?>
								<img src="<?php echo esc_url( $settings['logo_url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="max-height:50px;max-width:200px;">
							<?php else : ?>
								<span style="color:#fff;font-size:20px;font-weight:bold;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td style="padding:28px 24px;font-size:15px;line-height:1.6;">
							<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
					</tr>
					<tr>
						<td style="padding:16px 24px;background:#fafafa;border-top:1px solid #eee;font-size:12px;color:#777;text-align:center;">
							<?php echo esc_html( $settings['email_footer'] ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
