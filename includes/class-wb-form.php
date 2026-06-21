<?php
/**
 * Public withdrawal form (shortcode).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Form {

	/**
	 * Whether current page has the form shortcode.
	 *
	 * @var bool
	 */
	private static $has_form = false;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_shortcode( 'withdrawal_form', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		WB_Spam::init();
	}

	/**
	 * Check if page has form.
	 *
	 * @return bool
	 */
	public static function page_has_form() {
		return self::$has_form;
	}

	/**
	 * Enqueue frontend assets.
	 */
	public static function enqueue_assets() {
		if ( ! self::$has_form ) {
			return;
		}

		wp_enqueue_style( 'wb-form', WB_URL . 'assets/css/form.css', array(), WB_VERSION );
		wp_enqueue_script( 'wb-form', WB_URL . 'assets/js/form.js', array( 'jquery' ), WB_VERSION, true );

		$settings = WB_Settings::get();
		wp_localize_script( 'wb-form', 'wbForm', array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'recaptchaV3Key' => 'recaptcha_v3' === $settings['captcha_provider'] ? $settings['recaptcha_v3_site'] : '',
			'wooAutofill'    => wb_is_woocommerce_enabled() && $settings['woo_autofill'],
			'i18n'           => array(
				'orderFound' => __( 'Order found. Details were filled automatically.', WB_TEXT_DOMAIN ),
				'orderError' => __( 'Could not find an order with this number.', WB_TEXT_DOMAIN ),
			),
		) );

		wp_add_inline_style( 'wb-form', wb_get_branding_css_vars() );
	}

	/**
	 * Public HTML output for blocks/widgets.
	 *
	 * @return string
	 */
	public static function get_form_html() {
		return self::render_shortcode();
	}

	/**
	 * Shortcode callback.
	 *
	 * @return string
	 */
	public static function render_shortcode() {
		self::$has_form = true;
		$settings       = WB_Settings::get();
		$step           = isset( $_POST['wb_step'] ) ? sanitize_key( wp_unslash( $_POST['wb_step'] ) ) : '';

		ob_start();
		echo '<div class="wb-withdrawal-wrap">';

		if ( 'confirm' === $step ) {
			self::handle_step_confirm( $settings );
		} elseif ( 'submit' === $step ) {
			self::handle_step_submit( $settings );
		} else {
			self::render_form( $settings );
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Collect POST fields.
	 *
	 * @return array<string, string>
	 */
	public static function collect_fields() {
		return array(
			'name'         => isset( $_POST['wb_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wb_name'] ) ) : '',
			'email'        => isset( $_POST['wb_email'] ) ? sanitize_email( wp_unslash( $_POST['wb_email'] ) ) : '',
			'order_number' => isset( $_POST['wb_order'] ) ? sanitize_text_field( wp_unslash( $_POST['wb_order'] ) ) : '',
			'store'        => isset( $_POST['wb_store'] ) ? sanitize_text_field( wp_unslash( $_POST['wb_store'] ) ) : '',
			'products'     => isset( $_POST['wb_products'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wb_products'] ) ) : '',
			'message'      => isset( $_POST['wb_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wb_message'] ) ) : '',
			'wc_order_id'  => isset( $_POST['wb_wc_order_id'] ) ? (int) $_POST['wb_wc_order_id'] : 0,
		);
	}

	/**
	 * Validate form fields.
	 *
	 * @param array<string, string> $fields Fields.
	 * @return array<int, string>
	 */
	public static function validate_fields( $fields ) {
		$errors = array();

		if ( '' === $fields['name'] ) {
			$errors[] = __( 'Please enter your full name.', WB_TEXT_DOMAIN );
		}
		if ( '' === $fields['email'] || ! is_email( $fields['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', WB_TEXT_DOMAIN );
		}
		if ( '' === $fields['order_number'] ) {
			$errors[] = __( 'Please enter the order number.', WB_TEXT_DOMAIN );
		}

		$stores = wb_get_stores();
		if ( $stores && ( '' === $fields['store'] || ! in_array( $fields['store'], $stores, true ) ) ) {
			$errors[] = __( 'Please select the store where you purchased.', WB_TEXT_DOMAIN );
		}

		if ( '' === $fields['products'] ) {
			$errors[] = __( 'Please list the products included in the withdrawal.', WB_TEXT_DOMAIN );
		}
		if ( empty( $_POST['wb_declare'] ) ) {
			$errors[] = __( 'You must confirm the withdrawal declaration.', WB_TEXT_DOMAIN );
		}
		if ( empty( $_POST['wb_privacy'] ) ) {
			$errors[] = __( 'You must accept the Privacy Policy.', WB_TEXT_DOMAIN );
		}

		$spam_errors = WB_Spam::validate();
		$errors      = array_merge( $errors, $spam_errors );

		if ( wb_is_woocommerce_enabled() ) {
			$woo_errors = WB_WooCommerce::validate_order( $fields );
			$errors     = array_merge( $errors, $woo_errors );
		}

		return $errors;
	}

	/**
	 * Render step 1 form.
	 *
	 * @param array<string, mixed>       $settings Settings.
	 * @param array<string, string>|null $fields   Fields.
	 * @param array<int, string>         $errors   Errors.
	 */
	private static function render_form( $settings, $fields = null, $errors = array() ) {
		if ( null === $fields ) {
			$fields = array(
				'name'         => '',
				'email'        => '',
				'order_number' => '',
				'store'        => '',
				'products'     => '',
				'message'      => '',
				'wc_order_id'  => 0,
			);
		}

		echo wb_render_template( 'form/form.php', array(
			'settings' => $settings,
			'fields'   => $fields,
			'errors'   => $errors,
			'stores'   => wb_get_stores(),
			'captcha'  => WB_Spam::render_captcha_field(),
		) );
	}

	/**
	 * Handle confirmation step.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private static function handle_step_confirm( $settings ) {
		if ( ! isset( $_POST['wb_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wb_nonce'] ), 'wb_form' ) ) {
			echo '<div class="wb-error">' . esc_html__( 'Session expired. Please try again.', WB_TEXT_DOMAIN ) . '</div>';
			self::render_form( $settings );
			return;
		}

		$fields = self::collect_fields();
		$errors = self::validate_fields( $fields );
		if ( $errors ) {
			self::render_form( $settings, $fields, $errors );
			return;
		}

		echo wb_render_template( 'form/confirm.php', array(
			'fields' => $fields,
		) );
	}

	/**
	 * Handle final submission.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private static function handle_step_submit( $settings ) {
		if ( ! isset( $_POST['wb_nonce2'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wb_nonce2'] ), 'wb_submit' ) ) {
			echo '<div class="wb-error">' . esc_html__( 'Session expired. Please try again.', WB_TEXT_DOMAIN ) . '</div>';
			self::render_form( $settings );
			return;
		}

		$fields = self::collect_fields();
		$errors = self::validate_fields( $fields );
		if ( $errors ) {
			self::render_form( $settings, $fields, $errors );
			return;
		}

		if ( wb_is_woocommerce_enabled() && empty( $fields['wc_order_id'] ) ) {
			$order = WB_WooCommerce::find_order( $fields['order_number'] );
			if ( $order ) {
				$fields['wc_order_id'] = $order->get_id();
			}
		}

		global $wpdb;
		$table = wb_table_name();
		$now   = current_time( 'mysql' );
		$ip    = wb_maybe_anonymize_ip( wb_get_ip() );
		$date  = date_i18n( wb_datetime_format(), current_time( 'timestamp' ) );

		$repl             = wb_build_replacements( $fields );
		$customer_subject = wb_apply_placeholders( $settings['customer_subject'], $repl );
		$customer_body    = wb_apply_placeholders( $settings['customer_body'], $repl );

		$history = wp_json_encode( array(
			array(
				'status' => 'new',
				'at'     => $now,
				'by'     => 'customer',
			),
		) );

		$inserted = $wpdb->insert(
			$table,
			array(
				'submitted_at'     => $now,
				'customer_name'    => $fields['name'],
				'customer_email'   => $fields['email'],
				'order_number'     => $fields['order_number'],
				'store'            => $fields['store'],
				'products'         => $fields['products'],
				'message'          => $fields['message'],
				'status'           => 'new',
				'ip_address'       => $ip,
				'email_copy'       => "SUBJECT: {$customer_subject}\n\n{$customer_body}",
				'updated_at'       => $now,
				'wc_order_id'      => (int) $fields['wc_order_id'],
				'status_history'   => $history,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			echo '<div class="wb-error">' . esc_html__( 'An error occurred while saving your request. Please try again or contact us.', WB_TEXT_DOMAIN ) . '</div>';
			return;
		}

		$request_id = (int) $wpdb->insert_id;
		WB_Spam::increment_rate_limit();
		WB_Emails::send_submission_emails( $fields, $request_id );

		if ( wb_is_woocommerce_enabled() ) {
			WB_WooCommerce::on_request_submitted( $fields, $request_id );
		}

		echo wb_render_template( 'form/success.php', array(
			'settings'   => $settings,
			'request_id' => $request_id,
			'fields'     => $fields,
			'date'       => $date,
		) );
	}
}
