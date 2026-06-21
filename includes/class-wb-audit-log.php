<?php
/**
 * Admin audit log for exports, imports, and bulk privacy actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Audit_Log {

	const RETENTION_DAYS = 90;

	/**
	 * Audit log table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wb_audit_log';
	}

	/**
	 * Record an admin action.
	 *
	 * @param string               $action  Action key.
	 * @param array<string, mixed> $context Optional context.
	 */
	public static function log( $action, $context = array() ) {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wpdb->insert(
			self::table_name(),
			array(
				'logged_at'  => current_time( 'mysql' ),
				'user_id'    => (int) $user_id,
				'action'     => substr( sanitize_key( $action ), 0, 50 ),
				'context'    => wp_json_encode( $context ),
				'ip_address' => wb_maybe_anonymize_ip( wb_get_ip() ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent log rows.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function get_recent( $limit = 50 ) {
		global $wpdb;
		$table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}
		$limit = max( 1, min( 500, (int) $limit ) );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY logged_at DESC LIMIT {$limit}" );
	}

	/**
	 * Delete logs older than retention period.
	 */
	public static function cleanup_old() {
		global $wpdb;
		$table  = self::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE logged_at < %s", $cutoff ) );
	}

	/**
	 * Human-readable action label.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	public static function action_label( $action ) {
		$labels = array(
			'export_requests_csv'   => __( 'Export requests (CSV)', WB_TEXT_DOMAIN ),
			'export_requests_json'  => __( 'Export requests (JSON)', WB_TEXT_DOMAIN ),
			'export_settings'       => __( 'Export settings', WB_TEXT_DOMAIN ),
			'export_rest_logs'      => __( 'Export REST logs', WB_TEXT_DOMAIN ),
			'import_requests'       => __( 'Import requests', WB_TEXT_DOMAIN ),
			'import_settings'       => __( 'Import settings', WB_TEXT_DOMAIN ),
			'bulk_anonymize'        => __( 'Bulk anonymize', WB_TEXT_DOMAIN ),
		);
		return $labels[ $action ] ?? $action;
	}

	/**
	 * Format context JSON for display.
	 *
	 * @param string $context JSON string.
	 * @return string
	 */
	public static function format_context( $context ) {
		$data = json_decode( (string) $context, true );
		if ( ! is_array( $data ) || ! $data ) {
			return '—';
		}
		$parts = array();
		foreach ( $data as $key => $value ) {
			$parts[] = $key . ': ' . ( is_scalar( $value ) ? $value : wp_json_encode( $value ) );
		}
		return implode( ', ', $parts );
	}
}
