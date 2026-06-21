<?php
/**
 * GDPR: consent records, retention, WordPress Privacy Tools integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Privacy {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_erasers' ) );
		add_action( 'wp_add_privacy_policy_content', array( __CLASS__, 'add_privacy_policy_content' ) );
	}

	/**
	 * Consent version string stored with each request (plugin + policy page revision).
	 *
	 * @return string
	 */
	public static function consent_version() {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $page_id ) {
			$modified = get_post_modified_time( 'U', true, $page_id );
			if ( $modified ) {
				return WB_VERSION . '-' . $modified;
			}
		}
		return WB_VERSION;
	}

	/**
	 * Build consent metadata at form confirmation step.
	 *
	 * @return array<string, string>
	 */
	public static function capture_consent_meta() {
		$now = current_time( 'mysql' );
		$url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

		return array(
			'privacy_consent_at'      => $now,
			'declare_consent_at'      => $now,
			'privacy_policy_url'      => $url ? esc_url_raw( $url ) : '',
			'privacy_consent_version' => self::consent_version(),
		);
	}

	/**
	 * Register personal data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporters( $exporters ) {
		$exporters['withdrawal-button-requests'] = array(
			'exporter_friendly_name' => __( 'Withdrawal requests', WB_TEXT_DOMAIN ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register personal data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_erasers( $erasers ) {
		$erasers['withdrawal-button-requests'] = array(
			'eraser_friendly_name' => __( 'Withdrawal requests', WB_TEXT_DOMAIN ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export withdrawal requests for an email address.
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page number.
	 * @return array<string, mixed>
	 */
	public static function export_personal_data( $email_address, $page = 1 ) {
		$email_address = sanitize_email( $email_address );
		if ( ! is_email( $email_address ) ) {
			return array( 'data' => array(), 'done' => true );
		}

		global $wpdb;
		$table    = wb_table_name();
		$per_page = 10;
		$page     = max( 1, (int) $page );
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_email = %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
				$email_address,
				$per_page,
				$offset
			)
		);

		$export_items = array();
		foreach ( $rows as $row ) {
			$export_items[] = array(
				'group_id'    => 'wb-withdrawal-requests',
				'group_label' => __( 'Withdrawal requests', WB_TEXT_DOMAIN ),
				'item_id'     => 'withdrawal-request-' . $row->id,
				'data'        => self::export_row_fields( $row ),
			);
		}

		$done = count( $rows ) < $per_page;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	/**
	 * Erase or anonymize withdrawal requests for an email address.
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page number.
	 * @return array<string, mixed>
	 */
	public static function erase_personal_data( $email_address, $page = 1 ) {
		$email_address = sanitize_email( $email_address );
		if ( ! is_email( $email_address ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;
		$table    = wb_table_name();
		$per_page = 10;
		$page     = max( 1, (int) $page );
		$offset   = ( $page - 1 ) * $per_page;
		$settings = WB_Settings::get();
		$action   = $settings['retention_action'];

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE customer_email = %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
				$email_address,
				$per_page,
				$offset
			)
		);

		$removed = 0;
		foreach ( $ids as $id ) {
			if ( 'anonymize' === $action ) {
				if ( self::anonymize_request( (int) $id ) ) {
					$removed++;
				}
			} else {
				if ( WB_Requests::delete( (int) $id ) ) {
					$removed++;
				}
			}
		}

		$done = count( $ids ) < $per_page;

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $done,
		);
	}

	/**
	 * Anonymize a single withdrawal request (retain ID and status timeline).
	 *
	 * @param int $id Request ID.
	 * @return bool
	 */
	public static function anonymize_request( $id ) {
		global $wpdb;
		$table = wb_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, anonymized_at FROM {$table} WHERE id = %d", $id ) );

		if ( ! $row || ! empty( $row->anonymized_at ) ) {
			return false;
		}

		$now = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			array(
				'customer_name'    => __( '[Removed]', WB_TEXT_DOMAIN ),
				'customer_email'   => 'removed-' . $id . '@anonymous.invalid',
				'order_number'     => '',
				'store'            => '',
				'products'         => '',
				'message'          => '',
				'ip_address'       => '',
				'email_copy'       => '',
				'wc_order_id'      => 0,
				'privacy_policy_url' => '',
				'updated_at'       => $now,
				'anonymized_at'    => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Map DB row to export field list.
	 *
	 * @param object $row Database row.
	 * @return array<int, array<string, string>>
	 */
	private static function export_row_fields( $row ) {
		$fields = array(
			array(
				'name'  => __( 'Request ID', WB_TEXT_DOMAIN ),
				'value' => (string) $row->id,
			),
			array(
				'name'  => __( 'Submitted at', WB_TEXT_DOMAIN ),
				'value' => wb_format_datetime( $row->submitted_at, true ),
			),
			array(
				'name'  => __( 'Full name', WB_TEXT_DOMAIN ),
				'value' => $row->customer_name,
			),
			array(
				'name'  => __( 'Email', WB_TEXT_DOMAIN ),
				'value' => $row->customer_email,
			),
			array(
				'name'  => __( 'Order number', WB_TEXT_DOMAIN ),
				'value' => $row->order_number,
			),
			array(
				'name'  => __( 'Store', WB_TEXT_DOMAIN ),
				'value' => $row->store,
			),
			array(
				'name'  => __( 'Products', WB_TEXT_DOMAIN ),
				'value' => $row->products,
			),
			array(
				'name'  => __( 'Message', WB_TEXT_DOMAIN ),
				'value' => $row->message,
			),
			array(
				'name'  => __( 'Status', WB_TEXT_DOMAIN ),
				'value' => wb_statuses()[ $row->status ] ?? $row->status,
			),
			array(
				'name'  => __( 'IP address', WB_TEXT_DOMAIN ),
				'value' => $row->ip_address,
			),
		);

		if ( ! empty( $row->wc_order_id ) ) {
			$fields[] = array(
				'name'  => __( 'WooCommerce order ID', WB_TEXT_DOMAIN ),
				'value' => (string) $row->wc_order_id,
			);
		}

		if ( ! empty( $row->privacy_consent_at ) ) {
			$fields[] = array(
				'name'  => __( 'Privacy consent at', WB_TEXT_DOMAIN ),
				'value' => wb_format_datetime( $row->privacy_consent_at, true ),
			);
		}

		if ( ! empty( $row->privacy_policy_url ) ) {
			$fields[] = array(
				'name'  => __( 'Privacy policy URL at consent', WB_TEXT_DOMAIN ),
				'value' => $row->privacy_policy_url,
			);
		}

		if ( ! empty( $row->privacy_consent_version ) ) {
			$fields[] = array(
				'name'  => __( 'Consent version', WB_TEXT_DOMAIN ),
				'value' => $row->privacy_consent_version,
			);
		}

		if ( ! empty( $row->anonymized_at ) ) {
			$fields[] = array(
				'name'  => __( 'Anonymized at', WB_TEXT_DOMAIN ),
				'value' => wb_format_datetime( $row->anonymized_at, true ),
			);
		}

		return $fields;
	}

	/**
	 * Suggested privacy policy section for site owners.
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = self::privacy_policy_text();
		wp_add_privacy_policy_content(
			__( 'Withdrawal Button', WB_TEXT_DOMAIN ),
			wp_kses_post( $content )
		);
	}

	/**
	 * Privacy policy suggested HTML.
	 *
	 * @return string
	 */
	public static function privacy_policy_text() {
		$settings = WB_Settings::get();
		$months   = (int) $settings['retention_months'];
		$captcha  = $settings['captcha_provider'];

		$lines = array(
			'<h2>' . esc_html__( 'Withdrawal requests', WB_TEXT_DOMAIN ) . '</h2>',
			'<p>' . esc_html__( 'When you submit a withdrawal request through our website form, we collect and process the following personal data:', WB_TEXT_DOMAIN ) . '</p>',
			'<ul>',
			'<li>' . esc_html__( 'Full name', WB_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'Email address', WB_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'Order number and purchase details (products, store)', WB_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'Optional message', WB_TEXT_DOMAIN ) . '</li>',
			'<li>' . esc_html__( 'IP address (optionally truncated for privacy)', WB_TEXT_DOMAIN ) . '</li>',
			'</ul>',
			'<p>' . esc_html__( 'Purpose: to process your statutory right of withdrawal, communicate about your request, and meet legal obligations.', WB_TEXT_DOMAIN ) . '</p>',
			'<p>' . esc_html__( 'Legal basis: performance of a contract / legal obligation and your consent to our privacy policy when submitting the form.', WB_TEXT_DOMAIN ) . '</p>',
			'<p>' . sprintf(
				/* translators: %d: number of months */
				esc_html__( 'Retention: we keep withdrawal requests for up to %d months unless a longer period is required by law, after which they are deleted or anonymized.', WB_TEXT_DOMAIN ),
				$months
			) . '</p>',
			'<p>' . esc_html__( 'We send confirmation and status emails to you and may notify our staff. Email delivery uses your site’s mail configuration.', WB_TEXT_DOMAIN ) . '</p>',
		);

		if ( class_exists( 'WooCommerce' ) && $settings['woo_enabled'] ) {
			$lines[] = '<p>' . esc_html__( 'If WooCommerce is enabled, we may match your request to an existing order and store the linked order ID.', WB_TEXT_DOMAIN ) . '</p>';
		}

		if ( 'none' !== $captcha ) {
			$lines[] = '<p>' . esc_html__( 'Anti-spam protection may use third-party services (Google reCAPTCHA or Cloudflare Turnstile) that may process your IP address. Scripts are loaded only after you accept the privacy policy when that option is enabled.', WB_TEXT_DOMAIN ) . '</p>';
		}

		$lines[] = '<p>' . esc_html__( 'You may request export or erasure of your data via the site administrator or WordPress privacy tools where available.', WB_TEXT_DOMAIN ) . '</p>';

		return implode( '', $lines );
	}

	/**
	 * Short data-processing notice for the public form.
	 *
	 * @return string
	 */
	public static function form_data_notice() {
		$settings = WB_Settings::get();
		$months   = (int) $settings['retention_months'];

		return sprintf(
			/* translators: %d: retention months */
			__( 'We process your name, email, order details, and IP address to handle your withdrawal request. Data is retained for up to %d months. See our Privacy Policy for details.', WB_TEXT_DOMAIN ),
			$months
		);
	}
}
