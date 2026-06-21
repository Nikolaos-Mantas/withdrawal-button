<?php
/**
 * Email rendering and sending.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Emails {

	/**
	 * Send customer confirmation and admin notification.
	 *
	 * @param array<string, string> $fields     Form fields.
	 * @param int                   $request_id Request ID.
	 */
	public static function send_submission_emails( $fields, $request_id ) {
		$settings = WB_Settings::get();
		$repl     = wb_build_replacements( $fields, $request_id );

		if ( $settings['email_customer_enabled'] ) {
			$subject = wb_apply_placeholders( $settings['customer_subject'], $repl );
			$body    = wb_apply_placeholders( $settings['customer_body'], $repl );
			self::send( $fields['email'], $subject, $body, 'customer-confirmation', $repl );
		}

		if ( $settings['email_admin_enabled'] ) {
			$admin_email = is_email( $settings['admin_email'] ) ? $settings['admin_email'] : get_option( 'admin_email' );
			$subject     = wb_apply_placeholders( $settings['admin_subject'], $repl );
			$body        = wb_apply_placeholders( $settings['admin_body'], $repl );
			self::send( $admin_email, $subject, $body, 'admin-notification', $repl );
		}
	}

	/**
	 * Send status change email to customer.
	 *
	 * @param string                $status     New status key.
	 * @param array<string, string> $fields     Request data.
	 * @param int                   $request_id Request ID.
	 */
	public static function send_status_email( $status, $fields, $request_id ) {
		$settings = WB_Settings::get();
		if ( ! $settings['email_status_enabled'] ) {
			return;
		}

		$map = array(
			'in_progress' => array( 'status_in_progress_subject', 'status_in_progress_body', 'status-in-progress' ),
			'approved'    => array( 'status_approved_subject', 'status_approved_body', 'status-approved' ),
			'rejected'    => array( 'status_rejected_subject', 'status_rejected_body', 'status-rejected' ),
			'completed'   => array( 'status_completed_subject', 'status_completed_body', 'status-completed' ),
		);

		if ( ! isset( $map[ $status ] ) ) {
			return;
		}

		$fields['status'] = $status;
		$repl             = wb_build_replacements( $fields, $request_id );
		$subject_key      = $map[ $status ][0];
		$body_key         = $map[ $status ][1];
		$template         = $map[ $status ][2];

		$subject = wb_apply_placeholders( $settings[ $subject_key ], $repl );
		$body    = wb_apply_placeholders( $settings[ $body_key ], $repl );

		if ( ! empty( $fields['email'] ) && is_email( $fields['email'] ) ) {
			self::send( $fields['email'], $subject, $body, $template, $repl );
		}
	}

	/**
	 * Send test email.
	 *
	 * @param string $to Recipient.
	 * @return bool
	 */
	public static function send_test_email( $to ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$fields = array(
			'name'         => __( 'Test Customer', WB_TEXT_DOMAIN ),
			'email'        => $to,
			'order_number' => '12345',
			'store'        => get_bloginfo( 'name' ),
			'products'     => __( 'Sample product x1', WB_TEXT_DOMAIN ),
			'message'      => __( 'This is a test message.', WB_TEXT_DOMAIN ),
			'status'       => 'new',
		);

		$repl    = wb_build_replacements( $fields, 999 );
		$subject = __( 'Test email from Withdrawal Plugin', WB_TEXT_DOMAIN );
		$body    = __( 'This is a test email. If you received this, your mail configuration is working.', WB_TEXT_DOMAIN );

		return self::send( $to, $subject, $body, 'customer-confirmation', $repl );
	}

	/**
	 * Send an email.
	 *
	 * @param string                $to       Recipient.
	 * @param string                $subject  Subject.
	 * @param string                $body     Plain body content.
	 * @param string                $template Template slug.
	 * @param array<string, string> $repl     Placeholders.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $template, $repl = array() ) {
		$settings = WB_Settings::get();
		$headers  = self::build_headers( $settings );

		$message = $body;
		if ( 'html' === $settings['email_format'] ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$message   = self::wrap_html( $subject, $body, $template, $repl );
		} else {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		}

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Build email headers.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<int, string>
	 */
	private static function build_headers( $settings ) {
		$from_name  = trim( $settings['from_name'] ) ? trim( $settings['from_name'] ) : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$from_email = is_email( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
		$reply_to   = is_email( $settings['reply_to'] ) ? $settings['reply_to'] : $from_email;

		$headers = array(
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $from_name . ' <' . $reply_to . '>',
		);

		if ( is_email( $settings['bcc_email'] ) ) {
			$headers[] = 'Bcc: ' . $settings['bcc_email'];
		}

		return $headers;
	}

	/**
	 * Wrap content in HTML email template.
	 *
	 * @param string                $subject  Email subject.
	 * @param string                $body     Plain body.
	 * @param string                $template Template slug.
	 * @param array<string, string> $repl     Placeholders.
	 * @return string
	 */
	private static function wrap_html( $subject, $body, $template, $repl ) {
		$settings = WB_Settings::get();
		$content  = wb_render_template( 'emails/' . $template . '.php', array(
			'subject'  => $subject,
			'body'     => $body,
			'repl'     => $repl,
			'settings' => $settings,
		) );

		if ( ! $content ) {
			$content = '<p>' . nl2br( esc_html( $body ) ) . '</p>';
		}

		return wb_render_template( 'emails/base.php', array(
			'subject'  => $subject,
			'content'  => $content,
			'settings' => $settings,
		) );
	}
}
