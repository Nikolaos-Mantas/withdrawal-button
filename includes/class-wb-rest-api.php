<?php
/**
 * REST API for withdrawal requests (optional).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_REST_API {

	const NAMESPACE = 'wb/v1';

	/**
	 * Initialize REST routes when enabled.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		WB_REST_Logger::init();
	}

	/**
	 * Check if REST API is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) WB_Settings::get()['rest_api_enabled'];
	}

	/**
	 * Register routes.
	 */
	public static function register_routes() {
		if ( ! self::is_enabled() ) {
			return;
		}

		register_rest_route(
			self::NAMESPACE,
			'/requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_requests' ),
					'permission_callback' => array( __CLASS__, 'authorize' ),
					'args'                => array(
						'status'   => array( 'sanitize_callback' => 'sanitize_key' ),
						'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
						'per_page' => array( 'default' => 20, 'sanitize_callback' => 'absint' ),
						'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
				'schema' => array( __CLASS__, 'get_requests_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_request' ),
					'permission_callback' => array( __CLASS__, 'authorize' ),
					'args'                => array(
						'id' => array( 'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						} ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_request' ),
					'permission_callback' => array( __CLASS__, 'authorize' ),
					'args'                => array(
						'id'     => array( 'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						} ),
						'status' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_request' ),
					'permission_callback' => array( __CLASS__, 'authorize' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'health_check' ),
				'permission_callback' => array( __CLASS__, 'authorize' ),
			)
		);
	}

	/**
	 * Permission callback: manage_options OR valid API key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function authorize( $request ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'wb_rest_disabled', __( 'REST API is disabled.', WB_TEXT_DOMAIN ), array( 'status' => 403 ) );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( ! self::validate_api_key( $request ) ) {
			return new WP_Error( 'wb_rest_forbidden', __( 'Invalid or missing API key.', WB_TEXT_DOMAIN ), array( 'status' => 401 ) );
		}

		if ( ! self::check_ip_allowlist() ) {
			return new WP_Error( 'wb_rest_ip_blocked', __( 'IP address not allowed.', WB_TEXT_DOMAIN ), array( 'status' => 403 ) );
		}

		if ( ! self::check_rate_limit( $request ) ) {
			return new WP_Error( 'wb_rest_rate_limit', __( 'Rate limit exceeded.', WB_TEXT_DOMAIN ), array( 'status' => 429 ) );
		}

		return true;
	}

	/**
	 * Validate API key from header or query (header preferred).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function validate_api_key( $request ) {
		$settings = WB_Settings::get();
		$stored   = (string) $settings['rest_api_key'];
		if ( '' === $stored ) {
			return false;
		}

		$key = $request->get_header( 'x_wb_api_key' );
		if ( ! $key ) {
			$key = $request->get_header( 'X-WB-API-Key' );
		}
		if ( ! $key ) {
			$key = $request->get_param( 'api_key' );
		}

		$key = sanitize_text_field( (string) $key );
		return $key && hash_equals( $stored, $key );
	}

	/**
	 * Check API-key client IP against optional allowlist (admins bypass).
	 *
	 * @return bool
	 */
	public static function check_ip_allowlist() {
		$settings = WB_Settings::get();
		if ( empty( $settings['rest_api_ip_allowlist_enabled'] ) ) {
			return true;
		}

		$allowlist = wb_parse_ip_allowlist( $settings['rest_api_ip_allowlist'] );
		if ( empty( $allowlist ) ) {
			return false;
		}

		return wb_ip_in_allowlist( wb_get_ip(), $allowlist );
	}

	/**
	 * Simple rate limit for API key / IP.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function check_rate_limit( $request ) {
		$settings = WB_Settings::get();
		$limit    = max( 10, (int) $settings['rest_api_rate_limit'] );
		$window   = max( 60, (int) $settings['rest_api_rate_window'] );

		$ip  = wb_get_ip();
		$key = 'wb_rest_' . md5( $ip . '|' . (string) $request->get_header( 'x_wb_api_key' ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * GET /health
	 *
	 * @return WP_REST_Response
	 */
	public static function health_check() {
		return new WP_REST_Response(
			array(
				'status'  => 'ok',
				'version' => WB_VERSION,
				'time'    => current_time( 'mysql' ),
			),
			200
		);
	}

	/**
	 * GET /requests
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_requests( $request ) {
		global $wpdb;
		$table    = wb_table_name();
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$status   = $request->get_param( 'status' );
		$search   = $request->get_param( 'search' );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $status && array_key_exists( $status, wb_statuses() ) ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		if ( $search ) {
			$where   .= ' AND (customer_name LIKE %s OR customer_email LIKE %s OR order_number LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$data_sql = "SELECT * FROM {$table} {$where} ORDER BY submitted_at DESC LIMIT %d OFFSET %d";
		$query_params   = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;
		$rows           = $wpdb->get_results( $wpdb->prepare( $data_sql, $query_params ) );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::format_row( $row );
		}

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * GET /requests/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_request( $request ) {
		global $wpdb;
		$id  = (int) $request['id'];
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . wb_table_name() . ' WHERE id = %d', $id ) );

		if ( ! $row ) {
			return new WP_Error( 'wb_not_found', __( 'Request not found.', WB_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::format_row( $row ), 200 );
	}

	/**
	 * PATCH /requests/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_request( $request ) {
		$id     = (int) $request['id'];
		$status = sanitize_key( $request->get_param( 'status' ) );

		if ( ! array_key_exists( $status, wb_statuses() ) ) {
			return new WP_Error( 'wb_invalid_status', __( 'Invalid status.', WB_TEXT_DOMAIN ), array( 'status' => 400 ) );
		}

		WB_Requests::update_status( $id, $status );

		return self::get_request( $request );
	}

	/**
	 * DELETE /requests/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_request( $request ) {
		$id = (int) $request['id'];
		global $wpdb;
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . wb_table_name() . ' WHERE id = %d', $id ) );

		if ( ! $exists ) {
			return new WP_Error( 'wb_not_found', __( 'Request not found.', WB_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		WB_Requests::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
	}

	/**
	 * Format DB row for API response.
	 *
	 * @param object $row Database row.
	 * @return array<string, mixed>
	 */
	private static function format_row( $row ) {
		return array(
			'id'             => (int) $row->id,
			'submitted_at'   => $row->submitted_at,
			'customer_name'  => $row->customer_name,
			'customer_email' => $row->customer_email,
			'order_number'   => $row->order_number,
			'store'          => $row->store,
			'products'       => $row->products,
			'message'        => $row->message,
			'status'         => $row->status,
			'ip_address'     => $row->ip_address,
			'wc_order_id'    => (int) $row->wc_order_id,
			'updated_at'     => $row->updated_at,
		);
	}

	/**
	 * Schema for requests collection.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_requests_schema() {
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => 'withdrawal_request',
			'type'    => 'object',
		);
	}

	/**
	 * Generate a new API key.
	 *
	 * @return string
	 */
	public static function generate_api_key() {
		return wp_generate_password( 48, false, false );
	}
}
