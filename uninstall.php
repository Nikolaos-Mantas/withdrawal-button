<?php
/**
 * Uninstall cleanup for Withdrawal Button.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'wb_settings', array() );
$delete_data = isset( $settings['delete_data_on_uninstall'] ) && $settings['delete_data_on_uninstall'];

if ( $delete_data ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wb_withdrawal_requests';
	$log_table = $wpdb->prefix . 'wb_rest_logs';
	$audit_table = $wpdb->prefix . 'wb_audit_log';
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$log_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$audit_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	delete_option( 'wb_settings' );
	delete_option( 'wb_version' );
	wp_clear_scheduled_hook( 'wb_daily_cleanup' );
	wp_clear_scheduled_hook( 'wb_telemetry_ping' );
}
