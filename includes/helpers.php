<?php
/**
 * Helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translate a string using the plugin text domain.
 *
 * @param string $text Text to translate.
 * @return string
 */
function wb__( $text ) {
	return __( $text, WB_TEXT_DOMAIN );
}

/**
 * Echo a translated string using the plugin text domain.
 *
 * @param string $text Text to translate.
 */
function wb_e( $text ) {
	echo esc_html( wb__( $text ) );
}

/**
 * Check if WooCommerce integration is active and enabled.
 *
 * @return bool
 */
function wb_is_woocommerce_enabled() {
	return class_exists( 'WooCommerce' ) && (bool) WB_Settings::get()['woo_enabled'];
}

/**
 * Get request statuses.
 *
 * @return array<string, string>
 */
function wb_statuses() {
	return array(
		'new'         => __( 'New', WB_TEXT_DOMAIN ),
		'in_progress' => __( 'In progress', WB_TEXT_DOMAIN ),
		'approved'    => __( 'Approved', WB_TEXT_DOMAIN ),
		'rejected'    => __( 'Rejected', WB_TEXT_DOMAIN ),
		'completed'   => __( 'Completed', WB_TEXT_DOMAIN ),
	);
}

/**
 * Get visitor IP address.
 *
 * @return string
 */
function wb_get_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return substr( $ip, 0, 45 );
}

/**
 * Maybe anonymize IP for GDPR.
 *
 * @param string $ip IP address.
 * @return string
 */
function wb_maybe_anonymize_ip( $ip ) {
	$settings = WB_Settings::get();
	if ( empty( $settings['anonymize_ip'] ) ) {
		return $ip;
	}
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$parts = explode( '.', $ip );
		$parts[3] = '0';
		return implode( '.', $parts );
	}
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$parts = explode( ':', $ip );
		$parts = array_slice( $parts, 0, 4 );
		return implode( ':', $parts ) . '::';
	}
	return $ip;
}

/**
 * Get configured store list.
 *
 * @return array<int, string>
 */
function wb_get_stores() {
	$settings = WB_Settings::get();
	$stores   = array_filter( array_map( 'trim', explode( "\n", (string) $settings['stores'] ) ) );
	return array_values( $stores );
}

/**
 * WordPress date + time format string for display.
 *
 * @param bool $with_seconds Include seconds when missing from site time format.
 * @return string
 */
function wb_datetime_format( $with_seconds = false ) {
	$time = get_option( 'time_format' );
	if ( $with_seconds && strpos( $time, 's' ) === false ) {
		$time = rtrim( $time ) . ':s';
	}
	return get_option( 'date_format' ) . ' ' . $time;
}

/**
 * Format a MySQL datetime using the site locale settings.
 *
 * @param string $datetime     MySQL datetime string.
 * @param bool   $with_seconds Include seconds in output.
 * @return string
 */
function wb_format_datetime( $datetime, $with_seconds = false ) {
	if ( empty( $datetime ) ) {
		return '';
	}
	$timestamp = strtotime( $datetime );
	if ( ! $timestamp ) {
		return '';
	}
	return date_i18n( wb_datetime_format( $with_seconds ), $timestamp );
}

/**
 * Build placeholder replacements.
 *
 * @param array<string, string> $fields Form fields.
 * @param int                   $request_id Request ID.
 * @return array<string, string>
 */
function wb_build_replacements( $fields, $request_id = 0 ) {
	$settings = WB_Settings::get();
	$date     = date_i18n( wb_datetime_format(), current_time( 'timestamp' ) );

	return array(
		'{name}'         => $fields['name'] ?? '',
		'{email}'        => $fields['email'] ?? '',
		'{order_number}' => $fields['order_number'] ?? '',
		'{store}'        => ! empty( $fields['store'] ) ? $fields['store'] : '—',
		'{products}'     => $fields['products'] ?? '',
		'{message}'      => ! empty( $fields['message'] ) ? $fields['message'] : '—',
		'{date}'         => $date,
		'{ip}'           => isset( $fields['ip'] ) ? $fields['ip'] : wb_maybe_anonymize_ip( wb_get_ip() ),
		'{site_name}'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'{admin_url}'    => admin_url( 'admin.php?page=wb-requests' ),
		'{days}'         => (string) (int) $settings['withdrawal_days'],
		'{request_id}'   => (string) $request_id,
		'{status}'       => isset( $fields['status'] ) ? ( wb_statuses()[ $fields['status'] ] ?? $fields['status'] ) : '',
	);
}

/**
 * Apply placeholders to text.
 *
 * @param string                $text Text with placeholders.
 * @param array<string, string> $repl Replacements.
 * @return string
 */
function wb_apply_placeholders( $text, $repl ) {
	return strtr( $text, $repl );
}

/**
 * Get branding CSS custom properties.
 *
 * @return string
 */
function wb_get_branding_css_vars() {
	$settings = WB_Settings::get();
	$vars     = array(
		'--wb-primary'       => $settings['color_primary'],
		'--wb-button'        => $settings['color_button'],
		'--wb-button-hover'  => $settings['color_button_hover'],
		'--wb-text'          => $settings['color_text'],
		'--wb-bg'            => $settings['color_background'],
		'--wb-border'        => $settings['color_border'],
		'--wb-error'         => $settings['color_error'],
		'--wb-success'       => $settings['color_success'],
		'--wb-radius'        => $settings['border_radius'] . 'px',
		'--wb-font'          => $settings['font_family'],
	);

	$css = '.wb-withdrawal-wrap{';
	foreach ( $vars as $key => $value ) {
		$css .= $key . ':' . esc_attr( $value ) . ';';
	}
	$css .= '}';

	return $css;
}

/**
 * Render a template file.
 *
 * @param string               $template Template path relative to templates/.
 * @param array<string, mixed> $args     Template arguments.
 * @return string
 */
function wb_render_template( $template, $args = array() ) {
	$path = WB_PATH . 'templates/' . $template;
	if ( ! file_exists( $path ) ) {
		return '';
	}

	ob_start();
	extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	include $path;
	return ob_get_clean();
}

/**
 * Get full table name.
 *
 * @return string
 */
function wb_table_name() {
	global $wpdb;
	return $wpdb->prefix . WB_TABLE;
}

/**
 * Plain copyright notice text.
 *
 * @return string
 */
function wb_copyright_notice() {
	return sprintf(
		'© %s %s · %s',
		gmdate( 'Y' ),
		WB_AUTHOR,
		preg_replace( '/^https?:\/\//', '', WB_AUTHOR_URI )
	);
}

/**
 * Admin screen footer credit HTML.
 *
 * @return string
 */
function wb_admin_footer_credit() {
	$author = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( WB_AUTHOR_URI ),
		esc_html( WB_AUTHOR )
	);

	return sprintf(
		'<p class="wb-admin-credit">%s · %s · <a href="%s">%s</a></p>',
		esc_html( wb_copyright_notice() ),
		$author,
		esc_url( 'https://github.com/' . WB_GITHUB_REPO ),
		esc_html__( 'GitHub', WB_TEXT_DOMAIN )
	);
}
