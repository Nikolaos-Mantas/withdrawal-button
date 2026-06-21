<?php
/**
 * REST API request logger.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_REST_Logger {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'track_start' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'log_dispatch' ), 10, 3 );
	}

	/**
	 * Is logging enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) WB_Settings::get()['rest_api_logging_enabled'];
	}

	/**
	 * REST log table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wb_rest_logs';
	}

	/**
	 * Track request start time.
	 *
	 * @param mixed            $result  Response.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 * @return mixed
	 */
	public static function track_start( $result, $server, $request ) {
		if ( self::is_wb_route( $request ) ) {
			$request->set_param( '_wb_start', microtime( true ) );
		}
		return $result;
	}

	/**
	 * Log REST response.
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error $response Response.
	 * @param WP_REST_Server                             $server   Server.
	 * @param WP_REST_Request                            $request  Request.
	 * @return WP_REST_Response|WP_HTTP_Response|WP_Error
	 */
	public static function log_dispatch( $response, $server, $request ) {
		if ( ! self::is_enabled() || ! self::is_wb_route( $request ) ) {
			return $response;
		}

		$start = $request->get_param( '_wb_start' );
		$ms    = $start ? (int) round( ( microtime( true ) - (float) $start ) * 1000 ) : 0;
		$code  = 500;
		if ( is_wp_error( $response ) ) {
			$code = (int) $response->get_error_data( 'status' );
			if ( ! $code ) {
				$code = 401;
			}
		} elseif ( $response instanceof WP_REST_Response ) {
			$code = (int) $response->get_status();
		}

		$auth_type = 'none';
		$user_id   = 0;
		if ( current_user_can( 'manage_options' ) ) {
			$auth_type = 'user';
			$user_id   = get_current_user_id();
		} elseif ( WB_REST_API::validate_api_key( $request ) ) {
			$auth_type = 'api_key';
		}

		self::insert_log(
			$request->get_route(),
			$request->get_method(),
			$code,
			wb_maybe_anonymize_ip( wb_get_ip() ),
			$auth_type,
			$user_id,
			$ms
		);

		return $response;
	}

	/**
	 * Insert log row.
	 *
	 * @param string $route     Route.
	 * @param string $method    HTTP method.
	 * @param int    $status    Status code.
	 * @param string $ip        IP address.
	 * @param string $auth_type Auth type.
	 * @param int    $user_id   User ID.
	 * @param int    $duration  Duration ms.
	 */
	public static function insert_log( $route, $method, $status, $ip, $auth_type, $user_id, $duration ) {
		global $wpdb;
		$table = self::table_name();

		$wpdb->insert(
			$table,
			array(
				'logged_at'  => current_time( 'mysql' ),
				'route'      => substr( (string) $route, 0, 190 ),
				'method'     => substr( (string) $method, 0, 10 ),
				'status_code'=> (int) $status,
				'ip_address' => substr( (string) $ip, 0, 45 ),
				'auth_type'  => substr( (string) $auth_type, 0, 20 ),
				'user_id'    => (int) $user_id,
				'duration_ms'=> (int) $duration,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d' )
		);
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function get_logs( $limit = 100 ) {
		global $wpdb;
		$table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}
		$limit = max( 1, min( 500, (int) $limit ) );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY logged_at DESC LIMIT {$limit}" );
	}

	/**
	 * Clear all logs.
	 */
	public static function clear_logs() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete logs older than retention days.
	 */
	public static function cleanup_old_logs() {
		$settings = WB_Settings::get();
		if ( ! $settings['rest_api_logging_enabled'] ) {
			return;
		}
		$days  = max( 1, (int) $settings['rest_api_log_retention_days'] );
		global $wpdb;
		$table  = self::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE logged_at < %s", $cutoff ) );
	}

	/**
	 * Check if route belongs to this plugin.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function is_wb_route( $request ) {
		$route = (string) $request->get_route();
		return 0 === strpos( $route, '/wb/v1' );
	}
}
