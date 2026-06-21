<?php
/**
 * Installation and version upgrades.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Install {

	/**
	 * Activate plugin.
	 */
	public static function activate() {
		self::create_tables();
		self::maybe_upgrade();

		if ( false === get_option( 'wb_settings' ) ) {
			add_option( 'wb_settings', WB_Settings::defaults() );
		}

		if ( ! wp_next_scheduled( 'wb_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wb_daily_cleanup' );
		}

		update_option( 'wb_version', WB_VERSION );
	}

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wb_daily_cleanup' );
	}

	/**
	 * Create or update database tables.
	 */
	public static function create_tables() {
		global $wpdb;
		$table   = wb_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submitted_at DATETIME NOT NULL,
			customer_name VARCHAR(190) NOT NULL,
			customer_email VARCHAR(190) NOT NULL,
			order_number VARCHAR(100) NOT NULL,
			store VARCHAR(190) NOT NULL DEFAULT '',
			products TEXT NOT NULL,
			message TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			ip_address VARCHAR(45) NOT NULL,
			email_copy LONGTEXT NULL,
			updated_at DATETIME NULL,
			wc_order_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			status_history LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY order_number (order_number),
			KEY wc_order_id (wc_order_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$log_table = WB_REST_Logger::table_name();
		$log_sql   = "CREATE TABLE {$log_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			logged_at DATETIME NOT NULL,
			route VARCHAR(190) NOT NULL,
			method VARCHAR(10) NOT NULL,
			status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			auth_type VARCHAR(20) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY logged_at (logged_at),
			KEY status_code (status_code)
		) {$charset};";
		dbDelta( $log_sql );
	}

	/**
	 * Run version-based upgrades (new settings keys, schema changes).
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'wb_version', '0' );
		if ( version_compare( $installed, WB_VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();

		$settings = WB_Settings::get();
		$merged   = wp_parse_args( $settings, WB_Settings::defaults() );
		update_option( 'wb_settings', $merged );
		update_option( 'wb_version', WB_VERSION );
	}

	/**
	 * GDPR daily cleanup cron handler.
	 */
	public static function daily_cleanup() {
		global $wpdb;
		$settings = WB_Settings::get();
		$months   = max( 1, (int) $settings['retention_months'] );
		$table    = wb_table_name();
		$cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE submitted_at < %s", $cutoff ) );
		WB_REST_Logger::cleanup_old_logs();
	}
}
