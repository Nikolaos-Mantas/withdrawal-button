<?php
/**
 * Plugin settings registry.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Settings {

	/**
	 * Get merged settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		return wp_parse_args( (array) get_option( 'wb_settings', array() ), self::defaults() );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$admin     = get_option( 'admin_email' );

		return array(
			// General.
			'admin_email'              => $admin,
			'withdrawal_days'          => 14,
			'retention_months'         => 60,
			'policy_page_id'           => 0,
			'stores'                   => '',
			'anonymize_ip'             => 0,
			'delete_data_on_uninstall' => 0,

			// Form texts.
			'form_intro'       => __( 'Complete the form below to exercise your right of withdrawal within {days} calendar days from receiving your order.', WB_TEXT_DOMAIN ),
			'success_message'  => __( 'Your withdrawal request was submitted successfully. You will receive a confirmation email shortly.', WB_TEXT_DOMAIN ),

			// Branding.
			'logo_url'           => '',
			'color_primary'        => '#b32d2e',
			'color_button'         => '#b32d2e',
			'color_button_hover'   => '#8f2425',
			'color_text'           => '#333333',
			'color_background'     => '#ffffff',
			'color_border'         => '#cccccc',
			'color_error'          => '#b32d2e',
			'color_success'        => '#2e7d32',
			'border_radius'        => 5,
			'font_family'          => 'inherit',

			// Email general.
			'from_name'            => $site_name,
			'from_email'           => $admin,
			'reply_to'             => $admin,
			'bcc_email'            => '',
			'email_format'         => 'html',
			'email_footer'         => sprintf(
				/* translators: 1: site name, 2: author name, 3: author website */
				__( 'Sent by %1$s. Withdrawal Button by %2$s (%3$s).', WB_TEXT_DOMAIN ),
				$site_name,
				WB_AUTHOR,
				WB_AUTHOR_URI
			),

			// Email toggles.
			'email_customer_enabled' => 1,
			'email_admin_enabled'    => 1,
			'email_status_enabled'   => 1,

			// Customer confirmation email.
			'customer_subject' => __( 'Withdrawal confirmation – Order #{order_number}', WB_TEXT_DOMAIN ),
			'customer_body'    => __( "Dear {name},\n\nWe received your withdrawal request for order #{order_number} on {date}.\n\nProducts: {products}\n\nWe will review your request according to our return policy and contact you shortly.\n\nBest regards,\n{site_name}", WB_TEXT_DOMAIN ),

			// Admin notification email.
			'admin_subject' => __( 'New withdrawal request – Order #{order_number}', WB_TEXT_DOMAIN ),
			'admin_body'    => __( "A new withdrawal request was submitted.\n\nName: {name}\nEmail: {email}\nStore: {store}\nOrder: #{order_number}\nProducts: {products}\nMessage: {message}\nDate: {date}\nIP: {ip}\n\nManage: {admin_url}", WB_TEXT_DOMAIN ),

			// Status emails.
			'status_in_progress_subject' => __( 'Withdrawal request in progress – Order #{order_number}', WB_TEXT_DOMAIN ),
			'status_in_progress_body'    => __( "Dear {name},\n\nYour withdrawal request for order #{order_number} is now being processed.\n\nWe will update you as soon as we have more information.\n\nBest regards,\n{site_name}", WB_TEXT_DOMAIN ),
			'status_approved_subject'    => __( 'Withdrawal request approved – Order #{order_number}', WB_TEXT_DOMAIN ),
			'status_approved_body'       => __( "Dear {name},\n\nYour withdrawal request for order #{order_number} has been approved.\n\nPlease follow the return instructions we will provide or that are listed on our website.\n\nBest regards,\n{site_name}", WB_TEXT_DOMAIN ),
			'status_rejected_subject'    => __( 'Withdrawal request rejected – Order #{order_number}', WB_TEXT_DOMAIN ),
			'status_rejected_body'       => __( "Dear {name},\n\nUnfortunately, your withdrawal request for order #{order_number} could not be approved.\n\nIf you have questions, please contact us.\n\nBest regards,\n{site_name}", WB_TEXT_DOMAIN ),
			'status_completed_subject'   => __( 'Withdrawal completed – Order #{order_number}', WB_TEXT_DOMAIN ),
			'status_completed_body'      => __( "Dear {name},\n\nYour withdrawal for order #{order_number} has been completed.\n\nThank you for your patience.\n\nBest regards,\n{site_name}", WB_TEXT_DOMAIN ),

			// Anti-spam.
			'honeypot_enabled'     => 1,
			'time_trap_enabled'    => 1,
			'time_trap_seconds'    => 3,
			'rate_limit_enabled'   => 1,
			'rate_limit_count'     => 5,
			'rate_limit_window'    => 3600,
			'captcha_provider'     => 'none',
			'recaptcha_v2_site'    => '',
			'recaptcha_v2_secret'  => '',
			'recaptcha_v3_site'    => '',
			'recaptcha_v3_secret'  => '',
			'recaptcha_v3_score'   => 0.5,
			'turnstile_site'       => '',
			'turnstile_secret'     => '',

			// WooCommerce.
			'woo_enabled'              => 0,
			'woo_match_email'          => 1,
			'woo_autofill'             => 1,
			'woo_add_order_note'       => 1,

			// REST API.
			'rest_api_enabled'     => 0,
			'rest_api_key'         => '',
			'rest_api_rate_limit'  => 60,
			'rest_api_rate_window' => 3600,
			'rest_api_logging_enabled' => 0,
			'rest_api_log_retention_days' => 30,

			// Updates (GitHub).
			'update_channel'         => 'stable',
			'update_notifications'   => 1,
		);
	}

	/**
	 * Merge imported settings array with current values.
	 *
	 * @param array<string, mixed> $data Imported data.
	 * @return array<string, mixed>
	 */
	public static function merge_import( $data ) {
		$defaults = self::defaults();
		$current  = self::get();
		$merged   = wp_parse_args( (array) $data, $current );
		return wp_parse_args( $merged, $defaults );
	}

	/**
	 * Sanitize settings from POST.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$out      = self::get();
		$tab      = isset( $input['wb_settings_tab'] ) ? sanitize_key( $input['wb_settings_tab'] ) : 'general';

		if ( 'general' === $tab ) {
			$out['admin_email']              = isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $out['admin_email'];
			$out['withdrawal_days']          = isset( $input['withdrawal_days'] ) ? max( 1, (int) $input['withdrawal_days'] ) : $out['withdrawal_days'];
			$out['retention_months']         = isset( $input['retention_months'] ) ? max( 1, (int) $input['retention_months'] ) : $out['retention_months'];
			$out['policy_page_id']           = isset( $input['policy_page_id'] ) ? (int) $input['policy_page_id'] : $out['policy_page_id'];
			$out['stores']                   = isset( $input['stores'] ) ? sanitize_textarea_field( $input['stores'] ) : $out['stores'];
			$out['form_intro']               = isset( $input['form_intro'] ) ? sanitize_textarea_field( $input['form_intro'] ) : $out['form_intro'];
			$out['success_message']          = isset( $input['success_message'] ) ? sanitize_textarea_field( $input['success_message'] ) : $out['success_message'];
			$out['anonymize_ip']             = empty( $input['anonymize_ip'] ) ? 0 : 1;
			$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;
		}

		if ( 'branding' === $tab ) {
			$out['logo_url']           = isset( $input['logo_url'] ) ? esc_url_raw( $input['logo_url'] ) : $out['logo_url'];
			$out['color_primary']      = self::sanitize_color( $input['color_primary'] ?? $out['color_primary'] );
			$out['color_button']       = self::sanitize_color( $input['color_button'] ?? $out['color_button'] );
			$out['color_button_hover'] = self::sanitize_color( $input['color_button_hover'] ?? $out['color_button_hover'] );
			$out['color_text']         = self::sanitize_color( $input['color_text'] ?? $out['color_text'] );
			$out['color_background']   = self::sanitize_color( $input['color_background'] ?? $out['color_background'] );
			$out['color_border']       = self::sanitize_color( $input['color_border'] ?? $out['color_border'] );
			$out['color_error']        = self::sanitize_color( $input['color_error'] ?? $out['color_error'] );
			$out['color_success']      = self::sanitize_color( $input['color_success'] ?? $out['color_success'] );
			$out['border_radius']      = isset( $input['border_radius'] ) ? max( 0, (int) $input['border_radius'] ) : $out['border_radius'];
			$out['font_family']        = isset( $input['font_family'] ) ? sanitize_text_field( $input['font_family'] ) : $out['font_family'];
		}

		if ( 'email' === $tab ) {
			$out['from_name']              = isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : $out['from_name'];
			$out['from_email']             = isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : $out['from_email'];
			$out['reply_to']               = isset( $input['reply_to'] ) ? sanitize_email( $input['reply_to'] ) : $out['reply_to'];
			$out['bcc_email']              = isset( $input['bcc_email'] ) ? sanitize_email( $input['bcc_email'] ) : $out['bcc_email'];
			$out['email_format']           = isset( $input['email_format'] ) && $input['email_format'] === 'plain' ? 'plain' : 'html';
			$out['email_footer']           = isset( $input['email_footer'] ) ? sanitize_textarea_field( $input['email_footer'] ) : $out['email_footer'];
			$out['email_customer_enabled'] = empty( $input['email_customer_enabled'] ) ? 0 : 1;
			$out['email_admin_enabled']    = empty( $input['email_admin_enabled'] ) ? 0 : 1;
			$out['email_status_enabled']   = empty( $input['email_status_enabled'] ) ? 0 : 1;

			$text_fields = array(
				'customer_subject', 'customer_body', 'admin_subject', 'admin_body',
				'status_in_progress_subject', 'status_in_progress_body',
				'status_approved_subject', 'status_approved_body',
				'status_rejected_subject', 'status_rejected_body',
				'status_completed_subject', 'status_completed_body',
			);
			foreach ( $text_fields as $field ) {
				if ( ! isset( $input[ $field ] ) ) {
					continue;
				}
				if ( false !== strpos( $field, '_body' ) ) {
					$out[ $field ] = sanitize_textarea_field( $input[ $field ] );
				} else {
					$out[ $field ] = sanitize_text_field( $input[ $field ] );
				}
			}
		}

		if ( 'security' === $tab ) {
			$out['honeypot_enabled']   = empty( $input['honeypot_enabled'] ) ? 0 : 1;
			$out['time_trap_enabled']  = empty( $input['time_trap_enabled'] ) ? 0 : 1;
			$out['time_trap_seconds']  = isset( $input['time_trap_seconds'] ) ? max( 1, (int) $input['time_trap_seconds'] ) : $out['time_trap_seconds'];
			$out['rate_limit_enabled'] = empty( $input['rate_limit_enabled'] ) ? 0 : 1;
			$out['rate_limit_count']   = isset( $input['rate_limit_count'] ) ? max( 1, (int) $input['rate_limit_count'] ) : $out['rate_limit_count'];
			$out['rate_limit_window']  = isset( $input['rate_limit_window'] ) ? max( 60, (int) $input['rate_limit_window'] ) : $out['rate_limit_window'];

			$providers = array( 'none', 'recaptcha_v2', 'recaptcha_v3', 'turnstile' );
			$provider  = isset( $input['captcha_provider'] ) ? sanitize_key( $input['captcha_provider'] ) : $out['captcha_provider'];
			$out['captcha_provider'] = in_array( $provider, $providers, true ) ? $provider : $out['captcha_provider'];

			$out['recaptcha_v2_site']   = isset( $input['recaptcha_v2_site'] ) ? sanitize_text_field( $input['recaptcha_v2_site'] ) : $out['recaptcha_v2_site'];
			$out['recaptcha_v2_secret'] = isset( $input['recaptcha_v2_secret'] ) ? sanitize_text_field( $input['recaptcha_v2_secret'] ) : $out['recaptcha_v2_secret'];
			$out['recaptcha_v3_site']   = isset( $input['recaptcha_v3_site'] ) ? sanitize_text_field( $input['recaptcha_v3_site'] ) : $out['recaptcha_v3_site'];
			$out['recaptcha_v3_secret'] = isset( $input['recaptcha_v3_secret'] ) ? sanitize_text_field( $input['recaptcha_v3_secret'] ) : $out['recaptcha_v3_secret'];
			$out['recaptcha_v3_score']  = isset( $input['recaptcha_v3_score'] ) ? min( 1, max( 0, (float) $input['recaptcha_v3_score'] ) ) : $out['recaptcha_v3_score'];
			$out['turnstile_site']      = isset( $input['turnstile_site'] ) ? sanitize_text_field( $input['turnstile_site'] ) : $out['turnstile_site'];
			$out['turnstile_secret']    = isset( $input['turnstile_secret'] ) ? sanitize_text_field( $input['turnstile_secret'] ) : $out['turnstile_secret'];
		}

		if ( 'woocommerce' === $tab ) {
			$out['woo_enabled']        = empty( $input['woo_enabled'] ) ? 0 : 1;
			$out['woo_match_email']    = empty( $input['woo_match_email'] ) ? 0 : 1;
			$out['woo_autofill']       = empty( $input['woo_autofill'] ) ? 0 : 1;
			$out['woo_add_order_note'] = empty( $input['woo_add_order_note'] ) ? 0 : 1;
		}

		if ( 'rest-api' === $tab ) {
			$was_enabled = (bool) $out['rest_api_enabled'];
			$out['rest_api_enabled']     = empty( $input['rest_api_enabled'] ) ? 0 : 1;
			$out['rest_api_rate_limit']  = isset( $input['rest_api_rate_limit'] ) ? max( 10, (int) $input['rest_api_rate_limit'] ) : $out['rest_api_rate_limit'];
			$out['rest_api_rate_window'] = isset( $input['rest_api_rate_window'] ) ? max( 60, (int) $input['rest_api_rate_window'] ) : $out['rest_api_rate_window'];

			if ( ! empty( $input['rest_api_regenerate_key'] ) ) {
				$out['rest_api_key'] = WB_REST_API::generate_api_key();
			} elseif ( $out['rest_api_enabled'] && '' === $out['rest_api_key'] ) {
				$out['rest_api_key'] = WB_REST_API::generate_api_key();
			}
			$out['rest_api_logging_enabled']    = empty( $input['rest_api_logging_enabled'] ) ? 0 : 1;
			$out['rest_api_log_retention_days'] = isset( $input['rest_api_log_retention_days'] ) ? max( 1, (int) $input['rest_api_log_retention_days'] ) : $out['rest_api_log_retention_days'];
		}

		if ( 'updates' === $tab ) {
			$channels = array( 'stable', 'beta', 'alpha' );
			$channel  = isset( $input['update_channel'] ) ? sanitize_key( $input['update_channel'] ) : $out['update_channel'];
			$out['update_channel']       = in_array( $channel, $channels, true ) ? $channel : $out['update_channel'];
			$out['update_notifications'] = empty( $input['update_notifications'] ) ? 0 : 1;
		}

		if ( 'import-export' === $tab ) {
			// No fields to save on this tab (handled via separate forms).
		}

		return wp_parse_args( $out, $defaults );
	}

	/**
	 * Sanitize hex color.
	 *
	 * @param string $color Color value.
	 * @return string
	 */
	private static function sanitize_color( $color ) {
		$color = sanitize_text_field( $color );
		if ( preg_match( '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $color ) ) {
			return $color;
		}
		return '#333333';
	}
}
