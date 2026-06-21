<?php
/**
 * Admin diagnostics and connection tests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Diagnostics {

	/**
	 * Run SMTP / mail test.
	 *
	 * @param string $to Recipient email.
	 * @return array<string, mixed>
	 */
	public static function test_smtp( $to ) {
		if ( ! is_email( $to ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid email address.', WB_TEXT_DOMAIN ) );
		}

		$sent = WB_Emails::send_test_email( $to );
		if ( $sent ) {
			return array(
				'success' => true,
				'message' => __( 'Test email sent successfully. Check your inbox and spam folder.', WB_TEXT_DOMAIN ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'wp_mail() returned false. Configure an SMTP plugin and check server mail logs.', WB_TEXT_DOMAIN ),
		);
	}

	/**
	 * Test REST API health endpoint.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_rest_api() {
		if ( ! WB_REST_API::is_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'REST API is disabled. Enable it in the REST API settings tab.', WB_TEXT_DOMAIN ),
			);
		}

		$settings = WB_Settings::get();
		$key      = (string) $settings['rest_api_key'];
		if ( '' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'No API key configured. Save REST settings to generate a key.', WB_TEXT_DOMAIN ),
			);
		}

		$url = rest_url( WB_REST_API::NAMESPACE . '/health' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'X-WB-API-Key' => $key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && isset( $body['status'] ) && 'ok' === $body['status'] ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: API version */
					__( 'REST API is working. Version: %s', WB_TEXT_DOMAIN ),
					isset( $body['version'] ) ? $body['version'] : WB_VERSION
				),
				'data'    => $body,
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: 1: HTTP status code */
				__( 'REST API test failed (HTTP %d). Check API key and HTTPS.', WB_TEXT_DOMAIN ),
				$code
			),
		);
	}

	/**
	 * Test WooCommerce connection.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce is not installed.', WB_TEXT_DOMAIN ),
			);
		}

		if ( ! wb_is_woocommerce_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce integration is disabled in settings.', WB_TEXT_DOMAIN ),
			);
		}

		$orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		if ( empty( $orders ) ) {
			return array(
				'success' => true,
				'message' => __( 'WooCommerce is active but no orders found to verify lookup.', WB_TEXT_DOMAIN ),
			);
		}

		$order = $orders[0];
		$found = WB_WooCommerce::find_order( $order->get_order_number() );

		if ( $found && $found->get_id() === $order->get_id() ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: order number */
					__( 'WooCommerce connection OK. Sample order #%s found.', WB_TEXT_DOMAIN ),
					$order->get_order_number()
				),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'WooCommerce is active but order lookup failed.', WB_TEXT_DOMAIN ),
		);
	}

	/**
	 * Test security configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_security() {
		$settings = WB_Settings::get();
		$checks   = array();
		$passed   = 0;
		$total    = 0;

		$checks[] = self::security_check(
			__( 'Honeypot enabled', WB_TEXT_DOMAIN ),
			! empty( $settings['honeypot_enabled'] )
		);
		$checks[] = self::security_check(
			__( 'Time trap enabled', WB_TEXT_DOMAIN ),
			! empty( $settings['time_trap_enabled'] )
		);
		$checks[] = self::security_check(
			__( 'Rate limiting enabled', WB_TEXT_DOMAIN ),
			! empty( $settings['rate_limit_enabled'] )
		);

		$captcha_ok = 'none' !== $settings['captcha_provider'];
		if ( 'recaptcha_v2' === $settings['captcha_provider'] ) {
			$captcha_ok = $settings['recaptcha_v2_site'] && $settings['recaptcha_v2_secret'];
		} elseif ( 'recaptcha_v3' === $settings['captcha_provider'] ) {
			$captcha_ok = $settings['recaptcha_v3_site'] && $settings['recaptcha_v3_secret'];
		} elseif ( 'turnstile' === $settings['captcha_provider'] ) {
			$captcha_ok = $settings['turnstile_site'] && $settings['turnstile_secret'];
		}

		$checks[] = self::security_check(
			__( 'Captcha configured', WB_TEXT_DOMAIN ),
			$captcha_ok,
			'none' === $settings['captcha_provider'] ? __( 'Optional — captcha is disabled.', WB_TEXT_DOMAIN ) : ''
		);

		$checks[] = self::security_check(
			__( 'REST API disabled or secured', WB_TEXT_DOMAIN ),
			! $settings['rest_api_enabled'] || ! empty( $settings['rest_api_key'] )
		);

		$checks[] = self::security_check(
			__( 'HTTPS active', WB_TEXT_DOMAIN ),
			is_ssl(),
			__( 'Recommended for production.', WB_TEXT_DOMAIN )
		);

		foreach ( $checks as $check ) {
			$total++;
			if ( $check['ok'] ) {
				$passed++;
			}
		}

		$success = $passed >= 3;

		return array(
			'success' => $success,
			'message' => sprintf(
				/* translators: 1: passed count, 2: total count */
				__( 'Security checks: %1$d/%2$d passed.', WB_TEXT_DOMAIN ),
				$passed,
				$total
			),
			'checks'  => $checks,
		);
	}

	/**
	 * Build a single security check row.
	 *
	 * @param string $label   Label.
	 * @param bool   $ok      Pass state.
	 * @param string $note    Optional note.
	 * @return array<string, mixed>
	 */
	private static function security_check( $label, $ok, $note = '' ) {
		return array(
			'label' => $label,
			'ok'    => (bool) $ok,
			'note'  => $note,
		);
	}

	/**
	 * Test database table exists.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_database() {
		global $wpdb;
		$table = wb_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

		if ( ! $exists ) {
			return array(
				'success' => false,
				'message' => __( 'Database table not found. Re-activate the plugin.', WB_TEXT_DOMAIN ),
			);
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: number of requests */
				__( 'Database OK. %d requests stored.', WB_TEXT_DOMAIN ),
				$count
			),
		);
	}
}
