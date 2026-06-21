<?php
/**
 * Optional opt-in telemetry (no customer PII).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Telemetry {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wb_telemetry_ping', array( __CLASS__, 'maybe_send' ) );
	}

	/**
	 * Schedule weekly ping on activation.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'wb_telemetry_ping' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'wb_telemetry_ping' );
		}
	}

	/**
	 * Clear scheduled ping.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( 'wb_telemetry_ping' );
	}

	/**
	 * Is telemetry enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) WB_Settings::get()['telemetry_enabled'];
	}

	/**
	 * Build anonymous payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_payload() {
		global $wpdb;

		$settings = WB_Settings::get();
		$table    = wb_table_name();
		$count    = 0;
		$anon     = 0;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$anon  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE anonymized_at IS NOT NULL" );
		}

		$site_id = hash( 'sha256', home_url( '/' ) . ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) );

		return array(
			'site_id'          => $site_id,
			'plugin_version'   => WB_VERSION,
			'wp_version'       => $GLOBALS['wp_version'] ?? '',
			'php_version'      => PHP_VERSION,
			'locale'           => get_locale(),
			'woo_enabled'      => (int) ( $settings['woo_enabled'] ?? 0 ),
			'rest_api_enabled' => (int) ( $settings['rest_api_enabled'] ?? 0 ),
			'captcha_provider' => $settings['captcha_provider'] ?? 'none',
			'request_count'    => $count,
			'anonymized_count' => $anon,
		);
	}

	/**
	 * Cron handler: send if opted in.
	 */
	public static function maybe_send() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$payload = self::build_payload();
		$url     = defined( 'WB_TELEMETRY_URL' ) ? WB_TELEMETRY_URL : '';

		if ( ! $url ) {
			return;
		}

		wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);
	}
}
