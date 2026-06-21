<?php
/**
 * Tabbed settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Settings_Page {

	/**
	 * Render settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['export'] ) ) {
			WB_Admin::handle_export( sanitize_key( $_GET['export'] ) );
		}

		WB_Admin::handle_import_export();

		if ( isset( $_POST['wb_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['wb_settings_nonce'] ), 'wb_save_settings' ) ) {
			$sanitized = WB_Settings::sanitize( wp_unslash( $_POST ) );
			update_option( 'wb_settings', $sanitized );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', WB_TEXT_DOMAIN ) . '</p></div>';
		}

		$s       = WB_Settings::get();
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$tabs    = self::tabs();
		$base_url = admin_url( 'admin.php?page=wb-settings' );

		echo '<div class="wrap wb-settings-wrap">';
		echo '<h1>' . esc_html__( 'Withdrawal Settings', WB_TEXT_DOMAIN ) . '</h1>';
		echo '<p>' . esc_html__( 'Shortcode', WB_TEXT_DOMAIN ) . ' <code>[withdrawal_form]</code></p>';

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$class = $tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( 'tab', $key, $base_url ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		$no_settings_form = in_array( $tab, array( 'docs', 'diagnostics', 'import-export', 'support' ), true );

		if ( ! $no_settings_form ) {
			echo '<form method="post" class="wb-settings-form">';
			wp_nonce_field( 'wb_save_settings', 'wb_settings_nonce' );
			echo '<input type="hidden" name="wb_settings_tab" value="' . esc_attr( $tab ) . '">';
		}

		switch ( $tab ) {
			case 'branding':
				self::tab_branding( $s );
				break;
			case 'email':
				self::tab_email( $s );
				break;
			case 'security':
				self::tab_security( $s );
				break;
			case 'woocommerce':
				self::tab_woocommerce( $s );
				break;
			case 'rest-api':
				self::tab_rest_api( $s );
				break;
			case 'updates':
				self::tab_updates( $s );
				break;
			case 'support':
				self::tab_support( $s );
				break;
			case 'import-export':
				self::tab_import_export();
				break;
			case 'docs':
				self::tab_docs();
				break;
			case 'diagnostics':
				self::tab_diagnostics( $s );
				break;
			default:
				self::tab_general( $s );
		}

		if ( ! $no_settings_form ) {
			submit_button( __( 'Save Settings', WB_TEXT_DOMAIN ) );
			echo '</form>';
		}
		echo wb_admin_footer_credit();
		echo '</div>';
	}

	/**
	 * Tab labels.
	 *
	 * @return array<string, string>
	 */
	private static function tabs() {
		$tabs = array(
			'general'     => __( 'General', WB_TEXT_DOMAIN ),
			'branding'    => __( 'Branding', WB_TEXT_DOMAIN ),
			'email'       => __( 'Email', WB_TEXT_DOMAIN ),
			'security'    => __( 'Security', WB_TEXT_DOMAIN ),
			'rest-api'    => __( 'REST API', WB_TEXT_DOMAIN ),
			'updates'     => __( 'Updates', WB_TEXT_DOMAIN ),
			'import-export' => __( 'Import / Export', WB_TEXT_DOMAIN ),
			'diagnostics' => __( 'Tests', WB_TEXT_DOMAIN ),
			'support'     => __( 'Support', WB_TEXT_DOMAIN ),
			'docs'        => __( 'Documentation', WB_TEXT_DOMAIN ),
		);
		if ( class_exists( 'WooCommerce' ) ) {
			$tabs['woocommerce'] = __( 'WooCommerce', WB_TEXT_DOMAIN );
		}
		return $tabs;
	}

	/**
	 * General tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_general( $s ) {
		self::field_email( 'admin_email', __( 'Business email (notifications)', WB_TEXT_DOMAIN ), $s );
		self::field_number( 'withdrawal_days', __( 'Withdrawal period (days)', WB_TEXT_DOMAIN ), $s, 1 );
		self::field_number( 'retention_months', __( 'Data retention (months)', WB_TEXT_DOMAIN ), $s, 1 );
		?>
		<table class="form-table">
			<tr>
				<th><label for="policy_page_id"><?php esc_html_e( 'Returns policy page', WB_TEXT_DOMAIN ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages( array(
						'name'              => 'policy_page_id',
						'id'                => 'policy_page_id',
						'selected'          => (int) $s['policy_page_id'],
						'show_option_none'  => '— ' . __( 'None', WB_TEXT_DOMAIN ) . ' —',
						'option_none_value' => 0,
					) );
					?>
				</td>
			</tr>
			<tr>
				<th><label for="stores"><?php esc_html_e( 'Online stores', WB_TEXT_DOMAIN ); ?></label></th>
				<td>
					<textarea class="large-text" rows="4" id="stores" name="stores" placeholder="example.com"><?php echo esc_textarea( $s['stores'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One store per line. If set, customers must select a store.', WB_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="form_intro"><?php esc_html_e( 'Form introduction', WB_TEXT_DOMAIN ); ?></label></th>
				<td><textarea class="large-text" rows="3" id="form_intro" name="form_intro"><?php echo esc_textarea( $s['form_intro'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="success_message"><?php esc_html_e( 'Success message', WB_TEXT_DOMAIN ); ?></label></th>
				<td><textarea class="large-text" rows="2" id="success_message" name="success_message"><?php echo esc_textarea( $s['success_message'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'GDPR', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="checkbox" name="anonymize_ip" value="1" <?php checked( $s['anonymize_ip'], 1 ); ?>> <?php esc_html_e( 'Anonymize IP addresses', WB_TEXT_DOMAIN ); ?></label><br>
					<label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $s['delete_data_on_uninstall'], 1 ); ?>> <?php esc_html_e( 'Delete all data on uninstall', WB_TEXT_DOMAIN ); ?></label>
				</td>
			</tr>
		</table>
		<p><strong><?php esc_html_e( 'Placeholders:', WB_TEXT_DOMAIN ); ?></strong>
			<code>{name}</code> <code>{email}</code> <code>{order_number}</code> <code>{store}</code> <code>{products}</code> <code>{message}</code> <code>{date}</code> <code>{ip}</code> <code>{site_name}</code> <code>{days}</code> <code>{admin_url}</code> <code>{request_id}</code> <code>{status}</code>
		</p>
		<?php
	}

	/**
	 * Branding tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_branding( $s ) {
		echo '<table class="form-table">';
		echo '<tr><th><label for="logo_url">' . esc_html__( 'Logo', WB_TEXT_DOMAIN ) . '</label></th><td>';
		echo '<input type="url" class="regular-text wb-logo-url" id="logo_url" name="logo_url" value="' . esc_attr( $s['logo_url'] ) . '">';
		echo '<button type="button" class="button wb-upload-logo">' . esc_html__( 'Select logo', WB_TEXT_DOMAIN ) . '</button>';
		if ( $s['logo_url'] ) {
			echo '<p><img src="' . esc_url( $s['logo_url'] ) . '" alt="" style="max-height:60px;margin-top:8px"></p>';
		}
		echo '</td></tr>';
		self::field_color( 'color_primary', __( 'Primary color', WB_TEXT_DOMAIN ), $s );
		self::field_color( 'color_button', __( 'Button color', WB_TEXT_DOMAIN ), $s );
		self::field_color( 'color_button_hover', __( 'Button hover color', WB_TEXT_DOMAIN ), $s );
		self::field_color( 'color_text', __( 'Text color', WB_TEXT_DOMAIN ), $s );
		self::field_color( 'color_background', __( 'Background color', WB_TEXT_DOMAIN ), $s );
		self::field_color( 'color_border', __( 'Border color', WB_TEXT_DOMAIN ), $s );
		self::field_color( 'color_error', __( 'Error color', WB_TEXT_DOMAIN ), $s );
		self::field_color( 'color_success', __( 'Success color', WB_TEXT_DOMAIN ), $s );
		echo '<tr><th><label for="border_radius">' . esc_html__( 'Border radius (px)', WB_TEXT_DOMAIN ) . '</label></th>';
		echo '<td><input type="number" min="0" id="border_radius" name="border_radius" value="' . (int) $s['border_radius'] . '"></td></tr>';
		echo '<tr><th><label for="font_family">' . esc_html__( 'Font family', WB_TEXT_DOMAIN ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="font_family" name="font_family" value="' . esc_attr( $s['font_family'] ) . '" placeholder="inherit"></td></tr>';
		echo '</table>';
	}

	/**
	 * Email tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_email( $s ) {
		self::field_text( 'from_name', __( 'From name', WB_TEXT_DOMAIN ), $s );
		self::field_email( 'from_email', __( 'From email', WB_TEXT_DOMAIN ), $s );
		self::field_email( 'reply_to', __( 'Reply-To email', WB_TEXT_DOMAIN ), $s );
		self::field_email( 'bcc_email', __( 'BCC email (optional)', WB_TEXT_DOMAIN ), $s );
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Email format', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="radio" name="email_format" value="html" <?php checked( $s['email_format'], 'html' ); ?>> <?php esc_html_e( 'HTML', WB_TEXT_DOMAIN ); ?></label>
					<label><input type="radio" name="email_format" value="plain" <?php checked( $s['email_format'], 'plain' ); ?>> <?php esc_html_e( 'Plain text', WB_TEXT_DOMAIN ); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Enable emails', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="checkbox" name="email_customer_enabled" value="1" <?php checked( $s['email_customer_enabled'], 1 ); ?>> <?php esc_html_e( 'Customer confirmation', WB_TEXT_DOMAIN ); ?></label><br>
					<label><input type="checkbox" name="email_admin_enabled" value="1" <?php checked( $s['email_admin_enabled'], 1 ); ?>> <?php esc_html_e( 'Admin notification', WB_TEXT_DOMAIN ); ?></label><br>
					<label><input type="checkbox" name="email_status_enabled" value="1" <?php checked( $s['email_status_enabled'], 1 ); ?>> <?php esc_html_e( 'Status change emails', WB_TEXT_DOMAIN ); ?></label>
				</td>
			</tr>
			<tr>
				<th><label for="email_footer"><?php esc_html_e( 'Email footer', WB_TEXT_DOMAIN ); ?></label></th>
				<td><textarea class="large-text" rows="2" id="email_footer" name="email_footer"><?php echo esc_textarea( $s['email_footer'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Test email', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<input type="email" class="regular-text wb-test-email-to" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<button type="button" class="button wb-send-test-email"><?php esc_html_e( 'Send test email', WB_TEXT_DOMAIN ); ?></button>
					<span class="wb-test-email-result"></span>
					<p class="description"><?php esc_html_e( 'Use an SMTP plugin for reliable delivery. From email should match your domain.', WB_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Customer confirmation', WB_TEXT_DOMAIN ); ?></h2>
		<?php self::field_text( 'customer_subject', __( 'Subject', WB_TEXT_DOMAIN ), $s ); ?>
		<?php self::field_textarea( 'customer_body', __( 'Body', WB_TEXT_DOMAIN ), $s, 8 ); ?>

		<h2><?php esc_html_e( 'Admin notification', WB_TEXT_DOMAIN ); ?></h2>
		<?php self::field_text( 'admin_subject', __( 'Subject', WB_TEXT_DOMAIN ), $s ); ?>
		<?php self::field_textarea( 'admin_body', __( 'Body', WB_TEXT_DOMAIN ), $s, 8 ); ?>

		<h2><?php esc_html_e( 'Status: In progress', WB_TEXT_DOMAIN ); ?></h2>
		<?php self::field_text( 'status_in_progress_subject', __( 'Subject', WB_TEXT_DOMAIN ), $s ); ?>
		<?php self::field_textarea( 'status_in_progress_body', __( 'Body', WB_TEXT_DOMAIN ), $s, 6 ); ?>

		<h2><?php esc_html_e( 'Status: Approved', WB_TEXT_DOMAIN ); ?></h2>
		<?php self::field_text( 'status_approved_subject', __( 'Subject', WB_TEXT_DOMAIN ), $s ); ?>
		<?php self::field_textarea( 'status_approved_body', __( 'Body', WB_TEXT_DOMAIN ), $s, 6 ); ?>

		<h2><?php esc_html_e( 'Status: Rejected', WB_TEXT_DOMAIN ); ?></h2>
		<?php self::field_text( 'status_rejected_subject', __( 'Subject', WB_TEXT_DOMAIN ), $s ); ?>
		<?php self::field_textarea( 'status_rejected_body', __( 'Body', WB_TEXT_DOMAIN ), $s, 6 ); ?>

		<h2><?php esc_html_e( 'Status: Completed', WB_TEXT_DOMAIN ); ?></h2>
		<?php self::field_text( 'status_completed_subject', __( 'Subject', WB_TEXT_DOMAIN ), $s ); ?>
		<?php self::field_textarea( 'status_completed_body', __( 'Body', WB_TEXT_DOMAIN ), $s, 6 ); ?>
		<?php
	}

	/**
	 * Security tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_security( $s ) {
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Honeypot', WB_TEXT_DOMAIN ); ?></th>
				<td><label><input type="checkbox" name="honeypot_enabled" value="1" <?php checked( $s['honeypot_enabled'], 1 ); ?>> <?php esc_html_e( 'Enable honeypot field', WB_TEXT_DOMAIN ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Time trap', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="checkbox" name="time_trap_enabled" value="1" <?php checked( $s['time_trap_enabled'], 1 ); ?>> <?php esc_html_e( 'Reject submissions that are too fast', WB_TEXT_DOMAIN ); ?></label><br>
					<label><?php esc_html_e( 'Minimum seconds:', WB_TEXT_DOMAIN ); ?> <input type="number" min="1" name="time_trap_seconds" value="<?php echo (int) $s['time_trap_seconds']; ?>"></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Rate limit', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="checkbox" name="rate_limit_enabled" value="1" <?php checked( $s['rate_limit_enabled'], 1 ); ?>> <?php esc_html_e( 'Limit submissions per IP', WB_TEXT_DOMAIN ); ?></label><br>
					<label><?php esc_html_e( 'Max submissions:', WB_TEXT_DOMAIN ); ?> <input type="number" min="1" name="rate_limit_count" value="<?php echo (int) $s['rate_limit_count']; ?>"></label>
					<label><?php esc_html_e( 'Window (seconds):', WB_TEXT_DOMAIN ); ?> <input type="number" min="60" name="rate_limit_window" value="<?php echo (int) $s['rate_limit_window']; ?>"></label>
				</td>
			</tr>
			<tr>
				<th><label for="captcha_provider"><?php esc_html_e( 'Captcha provider', WB_TEXT_DOMAIN ); ?></label></th>
				<td>
					<select id="captcha_provider" name="captcha_provider">
						<option value="none" <?php selected( $s['captcha_provider'], 'none' ); ?>><?php esc_html_e( 'None', WB_TEXT_DOMAIN ); ?></option>
						<option value="recaptcha_v2" <?php selected( $s['captcha_provider'], 'recaptcha_v2' ); ?>><?php esc_html_e( 'Google reCAPTCHA v2', WB_TEXT_DOMAIN ); ?></option>
						<option value="recaptcha_v3" <?php selected( $s['captcha_provider'], 'recaptcha_v3' ); ?>><?php esc_html_e( 'Google reCAPTCHA v3', WB_TEXT_DOMAIN ); ?></option>
						<option value="turnstile" <?php selected( $s['captcha_provider'], 'turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile', WB_TEXT_DOMAIN ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'reCAPTCHA v2 keys', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<input type="text" class="regular-text" name="recaptcha_v2_site" value="<?php echo esc_attr( $s['recaptcha_v2_site'] ); ?>" placeholder="Site key"><br>
					<input type="text" class="regular-text" name="recaptcha_v2_secret" value="<?php echo esc_attr( $s['recaptcha_v2_secret'] ); ?>" placeholder="Secret key">
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'reCAPTCHA v3 keys', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<input type="text" class="regular-text" name="recaptcha_v3_site" value="<?php echo esc_attr( $s['recaptcha_v3_site'] ); ?>" placeholder="Site key"><br>
					<input type="text" class="regular-text" name="recaptcha_v3_secret" value="<?php echo esc_attr( $s['recaptcha_v3_secret'] ); ?>" placeholder="Secret key"><br>
					<label><?php esc_html_e( 'Minimum score:', WB_TEXT_DOMAIN ); ?> <input type="number" step="0.1" min="0" max="1" name="recaptcha_v3_score" value="<?php echo esc_attr( $s['recaptcha_v3_score'] ); ?>"></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Turnstile keys', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<input type="text" class="regular-text" name="turnstile_site" value="<?php echo esc_attr( $s['turnstile_site'] ); ?>" placeholder="Site key"><br>
					<input type="text" class="regular-text" name="turnstile_secret" value="<?php echo esc_attr( $s['turnstile_secret'] ); ?>" placeholder="Secret key">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * WooCommerce tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_woocommerce( $s ) {
		?>
		<p class="description"><?php esc_html_e( 'WooCommerce integration is optional. The form works fully without it. Enable only if you want order validation, auto-fill, and order notes.', WB_TEXT_DOMAIN ); ?></p>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'WooCommerce integration', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="checkbox" name="woo_enabled" value="1" <?php checked( $s['woo_enabled'], 1 ); ?>> <?php esc_html_e( 'Enable WooCommerce integration', WB_TEXT_DOMAIN ); ?></label>
					<p class="description"><?php esc_html_e( 'When disabled, customers can submit withdrawals with any order number without WooCommerce checks.', WB_TEXT_DOMAIN ); ?></p>
					<hr>
					<label><input type="checkbox" name="woo_match_email" value="1" <?php checked( $s['woo_match_email'], 1 ); ?>> <?php esc_html_e( 'Require email to match order', WB_TEXT_DOMAIN ); ?></label><br>
					<label><input type="checkbox" name="woo_autofill" value="1" <?php checked( $s['woo_autofill'], 1 ); ?>> <?php esc_html_e( 'Auto-fill form from order lookup', WB_TEXT_DOMAIN ); ?></label><br>
					<label><input type="checkbox" name="woo_add_order_note" value="1" <?php checked( $s['woo_add_order_note'], 1 ); ?>> <?php esc_html_e( 'Add order note on submission', WB_TEXT_DOMAIN ); ?></label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * REST API settings tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_rest_api( $s ) {
		$health_url = rest_url( WB_REST_API::NAMESPACE . '/health' );
		?>
		<p class="description"><?php esc_html_e( 'REST API is optional and disabled by default. When enabled, use the API key in the X-WB-API-Key header. WordPress admins logged in with manage_options can also access endpoints.', WB_TEXT_DOMAIN ); ?></p>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Enable REST API', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="checkbox" name="rest_api_enabled" value="1" <?php checked( $s['rest_api_enabled'], 1 ); ?>> <?php esc_html_e( 'Allow external API access to withdrawal requests', WB_TEXT_DOMAIN ); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'API key', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<input type="text" class="large-text" readonly value="<?php echo esc_attr( $s['rest_api_key'] ); ?>" id="wb-api-key-display">
					<p class="description"><?php esc_html_e( 'Save settings to generate a key when enabling REST API for the first time.', WB_TEXT_DOMAIN ); ?></p>
					<label><input type="checkbox" name="rest_api_regenerate_key" value="1"> <?php esc_html_e( 'Regenerate API key on save', WB_TEXT_DOMAIN ); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Rate limit', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><?php esc_html_e( 'Max requests:', WB_TEXT_DOMAIN ); ?> <input type="number" min="10" name="rest_api_rate_limit" value="<?php echo (int) $s['rest_api_rate_limit']; ?>"></label>
					<label><?php esc_html_e( 'Per window (seconds):', WB_TEXT_DOMAIN ); ?> <input type="number" min="60" name="rest_api_rate_window" value="<?php echo (int) $s['rest_api_rate_window']; ?>"></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Endpoints', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<code><?php echo esc_html( $health_url ); ?></code> — GET health<br>
					<code><?php echo esc_html( rest_url( WB_REST_API::NAMESPACE . '/requests' ) ); ?></code> — GET list<br>
					<code><?php echo esc_html( rest_url( WB_REST_API::NAMESPACE . '/requests/123' ) ); ?></code> — GET/PATCH/DELETE
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Request logging', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label><input type="checkbox" name="rest_api_logging_enabled" value="1" <?php checked( $s['rest_api_logging_enabled'], 1 ); ?>> <?php esc_html_e( 'Log REST API requests', WB_TEXT_DOMAIN ); ?></label><br>
					<label><?php esc_html_e( 'Log retention (days):', WB_TEXT_DOMAIN ); ?> <input type="number" min="1" name="rest_api_log_retention_days" value="<?php echo (int) $s['rest_api_log_retention_days']; ?>"></label>
					<p>
						<button type="button" class="button wb-clear-rest-logs"><?php esc_html_e( 'Clear logs', WB_TEXT_DOMAIN ); ?></button>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wb-settings&tab=rest-api&export=rest-logs' ), 'wb_export' ) ); ?>"><?php esc_html_e( 'Export logs (JSON)', WB_TEXT_DOMAIN ); ?></a>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Recent logs', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<?php self::render_rest_logs_table(); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Test API', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<button type="button" class="button wb-run-test" data-test="rest_api"><?php esc_html_e( 'Run API test', WB_TEXT_DOMAIN ); ?></button>
					<span class="wb-test-result" data-test="rest_api"></span>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Import / Export tab.
	 */
	private static function tab_import_export() {
		$export_settings = wp_nonce_url( admin_url( 'admin.php?page=wb-settings&tab=import-export&export=settings' ), 'wb_export' );
		$export_csv      = wp_nonce_url( admin_url( 'admin.php?page=wb-requests&export=csv' ), 'wb_export' );
		$export_json     = wp_nonce_url( admin_url( 'admin.php?page=wb-requests&export=json' ), 'wb_export' );
		?>
		<h2><?php esc_html_e( 'Export', WB_TEXT_DOMAIN ); ?></h2>
		<p>
			<a class="button" href="<?php echo esc_url( $export_settings ); ?>"><?php esc_html_e( 'Export settings (JSON)', WB_TEXT_DOMAIN ); ?></a>
			<a class="button" href="<?php echo esc_url( $export_csv ); ?>"><?php esc_html_e( 'Export requests (CSV)', WB_TEXT_DOMAIN ); ?></a>
			<a class="button" href="<?php echo esc_url( $export_json ); ?>"><?php esc_html_e( 'Export requests (JSON)', WB_TEXT_DOMAIN ); ?></a>
		</p>

		<h2><?php esc_html_e( 'Import settings', WB_TEXT_DOMAIN ); ?></h2>
		<?php WB_Admin::render_import_form( 'settings' ); ?>

		<h2><?php esc_html_e( 'Import requests', WB_TEXT_DOMAIN ); ?></h2>
		<p class="description"><?php esc_html_e( 'Upload a CSV or JSON file exported from this plugin.', WB_TEXT_DOMAIN ); ?></p>
		<?php WB_Admin::render_import_form( 'requests' ); ?>
		<?php
	}

	/**
	 * Render REST API logs table.
	 */
	private static function render_rest_logs_table() {
		$logs = WB_REST_Logger::get_logs( 50 );
		if ( ! $logs ) {
			echo '<p>' . esc_html__( 'No logs yet.', WB_TEXT_DOMAIN ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Time', WB_TEXT_DOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Method', WB_TEXT_DOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Route', WB_TEXT_DOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Status', WB_TEXT_DOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Auth', WB_TEXT_DOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'ms', WB_TEXT_DOMAIN ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log->logged_at ) . '</td>';
			echo '<td>' . esc_html( $log->method ) . '</td>';
			echo '<td>' . esc_html( $log->route ) . '</td>';
			echo '<td>' . esc_html( (string) $log->status_code ) . '</td>';
			echo '<td>' . esc_html( $log->auth_type ) . '</td>';
			echo '<td>' . esc_html( (string) $log->duration_ms ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Documentation tab.
	 */
	private static function tab_docs() {
		?>
		<div class="wb-docs">
			<h2><?php esc_html_e( 'Display', WB_TEXT_DOMAIN ); ?></h2>
			<ul>
				<li><code>[withdrawal_form]</code></li>
				<li><?php esc_html_e( 'Gutenberg block: Withdrawal Form', WB_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Elementor widget: Withdrawal Form', WB_TEXT_DOMAIN ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'Email placeholders', WB_TEXT_DOMAIN ); ?></h2>
			<p><code>{name}</code> <code>{email}</code> <code>{order_number}</code> <code>{store}</code> <code>{products}</code> <code>{message}</code> <code>{date}</code> <code>{ip}</code> <code>{site_name}</code> <code>{days}</code> <code>{admin_url}</code> <code>{request_id}</code> <code>{status}</code></p>

			<h2><?php esc_html_e( 'REST API', WB_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Header:', WB_TEXT_DOMAIN ); ?> <code>X-WB-API-Key: your-key</code></p>
			<pre>curl -H "X-WB-API-Key: KEY" <?php echo esc_html( rest_url( WB_REST_API::NAMESPACE . '/requests' ) ); ?></pre>

			<h2><?php esc_html_e( 'WP-CLI', WB_TEXT_DOMAIN ); ?></h2>
			<pre>wp wb test all
wp wb export settings --file=settings.json
wp wb export requests --format=csv --file=requests.csv</pre>

			<h2><?php esc_html_e( 'Install path', WB_TEXT_DOMAIN ); ?></h2>
			<p><code>wp-content/plugins/withdrawal-button/withdrawal-button.php</code></p>
		</div>
		<?php
	}

	/**
	 * Updates tab (GitHub releases).
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_updates( $s ) {
		$remote = WB_Updater::get_remote_update();
		?>
		<p><?php esc_html_e( 'Updates are downloaded from GitHub Releases.', WB_TEXT_DOMAIN ); ?>
			<a href="<?php echo esc_url( 'https://github.com/' . WB_GITHUB_REPO ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View repository', WB_TEXT_DOMAIN ); ?></a>
		</p>
		<table class="form-table">
			<tr>
				<th><label for="update_channel"><?php esc_html_e( 'Update channel', WB_TEXT_DOMAIN ); ?></label></th>
				<td>
					<select id="update_channel" name="update_channel">
						<option value="stable" <?php selected( $s['update_channel'], 'stable' ); ?>><?php esc_html_e( 'Stable (recommended)', WB_TEXT_DOMAIN ); ?></option>
						<option value="beta" <?php selected( $s['update_channel'], 'beta' ); ?>><?php esc_html_e( 'Beta (pre-release)', WB_TEXT_DOMAIN ); ?></option>
						<option value="alpha" <?php selected( $s['update_channel'], 'alpha' ); ?>><?php esc_html_e( 'Alpha (pre-release)', WB_TEXT_DOMAIN ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Beta and alpha channels may install unstable builds. Back up before upgrading.', WB_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Current version', WB_TEXT_DOMAIN ); ?></th>
				<td><code><?php echo esc_html( WB_VERSION ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Available update', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<?php if ( $remote ) : ?>
						<code><?php echo esc_html( $remote['version'] ); ?></code>
						<?php if ( 'beta' === $remote['tier'] || 'alpha' === $remote['tier'] ) : ?>
							<strong><?php esc_html_e( '(pre-release)', WB_TEXT_DOMAIN ); ?></strong>
						<?php endif; ?>
						— <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Install from Plugins screen', WB_TEXT_DOMAIN ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'No update available for this channel.', WB_TEXT_DOMAIN ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Update notifications', WB_TEXT_DOMAIN ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="update_notifications" value="1" <?php checked( $s['update_notifications'], 1 ); ?>>
						<?php esc_html_e( 'Show update notices on the Plugins screen', WB_TEXT_DOMAIN ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
		if ( 'beta' === $s['update_channel'] || 'alpha' === $s['update_channel'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'You are following a pre-release update channel.', WB_TEXT_DOMAIN ) . '</p></div>';
		}
	}

	/**
	 * Support / feedback tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_support( $s ) {
		?>
		<div class="wb-support-tab">
			<p><?php esc_html_e( 'Send a message to the plugin author. Your message and basic site details (plugin version, WordPress version, PHP version, site URL) are included.', WB_TEXT_DOMAIN ); ?></p>
			<table class="form-table">
				<tr>
					<th><label for="wb_feedback_type"><?php esc_html_e( 'Type', WB_TEXT_DOMAIN ); ?></label></th>
					<td>
						<select id="wb_feedback_type" class="wb-feedback-type">
							<option value="bug"><?php esc_html_e( 'Bug report', WB_TEXT_DOMAIN ); ?></option>
							<option value="idea"><?php esc_html_e( 'Feature idea', WB_TEXT_DOMAIN ); ?></option>
							<option value="other"><?php esc_html_e( 'Other', WB_TEXT_DOMAIN ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="wb_feedback_email"><?php esc_html_e( 'Your email', WB_TEXT_DOMAIN ); ?></label></th>
					<td>
						<input type="email" id="wb_feedback_email" class="regular-text wb-feedback-email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					</td>
				</tr>
				<tr>
					<th><label for="wb_feedback_message"><?php esc_html_e( 'Message', WB_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wb_feedback_message" class="large-text wb-feedback-message" rows="6"></textarea>
					</td>
				</tr>
				<tr>
					<th></th>
					<td>
						<button type="button" class="button button-primary wb-send-feedback"><?php esc_html_e( 'Send feedback', WB_TEXT_DOMAIN ); ?></button>
						<span class="wb-feedback-result"></span>
					</td>
				</tr>
			</table>
			<p class="description">
				<?php
				printf(
					/* translators: %s: author email */
					esc_html__( 'Or email %s directly.', WB_TEXT_DOMAIN ),
					esc_html( WB_AUTHOR_EMAIL )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Diagnostics / tests tab.
	 *
	 * @param array<string, mixed> $s Settings.
	 */
	private static function tab_diagnostics( $s ) {
		?>
		<p><?php esc_html_e( 'Run connection and security tests. Results appear below each button.', WB_TEXT_DOMAIN ); ?></p>
		<table class="widefat striped wb-tests-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Test', WB_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Action', WB_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Result', WB_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'SMTP / Email', WB_TEXT_DOMAIN ); ?></strong><br><span class="description"><?php esc_html_e( 'Sends a test email via wp_mail().', WB_TEXT_DOMAIN ); ?></span></td>
					<td>
						<input type="email" class="regular-text wb-test-email-to" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<button type="button" class="button wb-run-test" data-test="smtp"><?php esc_html_e( 'Test email', WB_TEXT_DOMAIN ); ?></button>
					</td>
					<td class="wb-test-result" data-test="smtp"></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'REST API', WB_TEXT_DOMAIN ); ?></strong></td>
					<td><button type="button" class="button wb-run-test" data-test="rest_api"><?php esc_html_e( 'Test API', WB_TEXT_DOMAIN ); ?></button></td>
					<td class="wb-test-result" data-test="rest_api"></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WooCommerce', WB_TEXT_DOMAIN ); ?></strong></td>
					<td><button type="button" class="button wb-run-test" data-test="woocommerce"><?php esc_html_e( 'Test connection', WB_TEXT_DOMAIN ); ?></button></td>
					<td class="wb-test-result" data-test="woocommerce"></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Security', WB_TEXT_DOMAIN ); ?></strong><br><span class="description"><?php esc_html_e( 'Checks honeypot, rate limit, captcha, REST, HTTPS.', WB_TEXT_DOMAIN ); ?></span></td>
					<td><button type="button" class="button wb-run-test" data-test="security"><?php esc_html_e( 'Run security check', WB_TEXT_DOMAIN ); ?></button></td>
					<td class="wb-test-result" data-test="security"></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Database', WB_TEXT_DOMAIN ); ?></strong></td>
					<td><button type="button" class="button wb-run-test" data-test="database"><?php esc_html_e( 'Test database', WB_TEXT_DOMAIN ); ?></button></td>
					<td class="wb-test-result" data-test="database"></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Text field row.
	 */
	private static function field_text( $key, $label, $s ) {
		echo '<table class="form-table"><tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="text" class="large-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '"></td></tr></table>';
	}

	/**
	 * Email field row.
	 */
	private static function field_email( $key, $label, $s ) {
		echo '<table class="form-table"><tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="email" class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '"></td></tr></table>';
	}

	/**
	 * Number field row.
	 */
	private static function field_number( $key, $label, $s, $min = 0 ) {
		echo '<table class="form-table"><tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="number" min="' . (int) $min . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . (int) $s[ $key ] . '"></td></tr></table>';
	}

	/**
	 * Textarea field row.
	 */
	private static function field_textarea( $key, $label, $s, $rows = 4 ) {
		echo '<table class="form-table"><tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><textarea class="large-text" rows="' . (int) $rows . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $s[ $key ] ) . '</textarea></td></tr></table>';
	}

	/**
	 * Color picker field row.
	 */
	private static function field_color( $key, $label, $s ) {
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="text" class="wb-color-picker" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '"></td></tr>';
	}
}
