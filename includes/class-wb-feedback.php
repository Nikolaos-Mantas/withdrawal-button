<?php
/**
 * Plugin feedback to the author.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Feedback {

	const TYPES = array( 'bug', 'idea', 'other' );

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Handled via WB_Admin AJAX.
	}

	/**
	 * Validate feedback input.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array{errors: array<int, string>, data: array<string, string>}
	 */
	public static function validate( $input ) {
		$errors = array();
		$type   = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
		$email  = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		$message = isset( $input['message'] ) ? sanitize_textarea_field( $input['message'] ) : '';

		if ( ! in_array( $type, self::TYPES, true ) ) {
			$errors[] = __( 'Please select a feedback type.', WB_TEXT_DOMAIN );
		}

		if ( '' === $message ) {
			$errors[] = __( 'Please enter a message.', WB_TEXT_DOMAIN );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', WB_TEXT_DOMAIN );
		}

		return array(
			'errors' => $errors,
			'data'   => array(
				'type'    => $type,
				'email'   => $email,
				'message' => $message,
			),
		);
	}

	/**
	 * Build technical context for the feedback email.
	 *
	 * @return array<string, string>
	 */
	public static function build_context() {
		global $wp_version;

		$settings = WB_Settings::get();

		return array(
			'plugin_version' => WB_VERSION,
			'wp_version'     => $wp_version,
			'php_version'    => PHP_VERSION,
			'site_url'       => home_url(),
			'update_channel' => (string) $settings['update_channel'],
		);
	}

	/**
	 * Send feedback email to the plugin author.
	 *
	 * @param array<string, string> $data Validated feedback data.
	 * @return bool
	 */
	public static function send( $data ) {
		$context = self::build_context();
		$type_label = self::type_label( $data['type'] );

		$subject = sprintf(
			'[Withdrawal Button] %s from %s',
			$type_label,
			wp_parse_url( $context['site_url'], PHP_URL_HOST ) ?: $context['site_url']
		);

		$body = "Type: {$type_label}\n";
		$body .= "From: {$data['email']}\n";
		$body .= "Site: {$context['site_url']}\n";
		$body .= "Plugin: {$context['plugin_version']}\n";
		$body .= "WordPress: {$context['wp_version']}\n";
		$body .= "PHP: {$context['php_version']}\n";
		$body .= "Update channel: {$context['update_channel']}\n\n";
		$body .= "Message:\n{$data['message']}\n";

		$headers = array(
			'Reply-To: ' . $data['email'],
		);

		return wp_mail( WB_AUTHOR_EMAIL, $subject, $body, $headers );
	}

	/**
	 * Human-readable type label.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'bug'   => __( 'Bug report', WB_TEXT_DOMAIN ),
			'idea'  => __( 'Feature idea', WB_TEXT_DOMAIN ),
			'other' => __( 'Other', WB_TEXT_DOMAIN ),
		);

		return $labels[ $type ] ?? $type;
	}
}
