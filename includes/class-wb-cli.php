<?php
/**
 * WP-CLI commands for the Withdrawal Button plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage withdrawal plugin via WP-CLI.
 */
class WB_CLI_Command {

	/**
	 * Run diagnostic tests.
	 *
	 * ## OPTIONS
	 *
	 * <test>
	 * : Test to run: smtp, api, woocommerce, security, database, all
	 *
	 * [--email=<email>]
	 * : Email for SMTP test
	 *
	 * ## EXAMPLES
	 *
	 *     wp wb test smtp --email=admin@example.com
	 *     wp wb test all
	 */
	public function test( $args, $assoc_args ) {
		$test  = isset( $args[0] ) ? sanitize_key( $args[0] ) : 'all';
		$email = isset( $assoc_args['email'] ) ? sanitize_email( $assoc_args['email'] ) : get_option( 'admin_email' );

		$tests = array();
		if ( 'all' === $test ) {
			$tests = array( 'smtp', 'api', 'woocommerce', 'security', 'database' );
		} else {
			$tests = array( $test );
		}

		foreach ( $tests as $name ) {
			$result = $this->run_test( $name, $email );
			if ( $result['success'] ) {
				WP_CLI::success( $name . ': ' . $result['message'] );
			} else {
				WP_CLI::warning( $name . ': ' . $result['message'] );
			}
		}
	}

	/**
	 * Export data.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Export type: requests, settings, rest-logs
	 *
	 * [--format=<format>]
	 * : For requests: csv or json (default: json)
	 *
	 * [--file=<path>]
	 * : Write to file instead of stdout
	 *
	 * ## EXAMPLES
	 *
	 *     wp wb export requests --format=csv --file=/tmp/requests.csv
	 *     wp wb export settings --file=/tmp/wb-settings.json
	 */
	public function export( $args, $assoc_args ) {
		$type   = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
		$file   = isset( $assoc_args['file'] ) ? $assoc_args['file'] : null;
		$format = isset( $assoc_args['format'] ) ? sanitize_key( $assoc_args['format'] ) : 'json';

		switch ( $type ) {
			case 'requests':
				$result = WB_Import_Export::export_requests( $format, $file );
				break;
			case 'settings':
				$result = WB_Import_Export::export_settings( $file );
				break;
			case 'rest-logs':
				$result = WB_Import_Export::export_rest_logs( $file );
				break;
			default:
				WP_CLI::error( 'Invalid type. Use: requests, settings, rest-logs' );
				return;
		}

		if ( false === $result ) {
			WP_CLI::error( 'Export failed.' );
		}

		if ( $file ) {
			WP_CLI::success( 'Exported to ' . $file );
		} else {
			WP_CLI::line( $result );
		}
	}

	/**
	 * Import data.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Import type: requests, settings
	 *
	 * <file>
	 * : Path to import file
	 *
	 * [--format=<format>]
	 * : auto, csv, or json
	 *
	 * [--keep-ids]
	 * : Keep original request IDs when importing
	 *
	 * [--no-merge]
	 * : Replace settings instead of merging
	 *
	 * ## EXAMPLES
	 *
	 *     wp wb import requests /tmp/requests.json
	 *     wp wb import settings /tmp/wb-settings.json
	 */
	public function import( $args, $assoc_args ) {
		$type = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
		$file = isset( $args[1] ) ? $args[1] : '';

		if ( ! $file || ! file_exists( $file ) ) {
			WP_CLI::error( 'File not found.' );
		}

		if ( 'requests' === $type ) {
			$result = WB_Import_Export::import_requests(
				$file,
				array(
					'format'   => isset( $assoc_args['format'] ) ? sanitize_key( $assoc_args['format'] ) : 'auto',
					'keep_ids' => isset( $assoc_args['keep-ids'] ),
				)
			);
			if ( $result['success'] ) {
				WP_CLI::success( sprintf( 'Imported %d, skipped %d.', $result['imported'], $result['skipped'] ) );
			} else {
				WP_CLI::error( implode( '; ', $result['errors'] ) );
			}
		} elseif ( 'settings' === $type ) {
			$merge  = ! isset( $assoc_args['no-merge'] );
			$result = WB_Import_Export::import_settings( $file, $merge );
			if ( $result['success'] ) {
				WP_CLI::success( $result['message'] );
			} else {
				WP_CLI::error( $result['message'] );
			}
		} else {
			WP_CLI::error( 'Invalid type. Use: requests, settings' );
		}
	}

	/**
	 * View or clear REST API logs.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Number of log entries to show
	 *
	 * [--clear]
	 * : Clear all logs
	 *
	 * ## EXAMPLES
	 *
	 *     wp wb logs --limit=20
	 *     wp wb logs --clear
	 */
	public function logs( $args, $assoc_args ) {
		if ( isset( $assoc_args['clear'] ) ) {
			WB_REST_Logger::clear_logs();
			WP_CLI::success( 'REST logs cleared.' );
			return;
		}

		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50;
		$logs  = WB_REST_Logger::get_logs( $limit );

		if ( ! $logs ) {
			WP_CLI::line( 'No logs found.' );
			return;
		}

		foreach ( $logs as $log ) {
			WP_CLI::line(
				sprintf(
					'%s %s %s %d %s %dms',
					$log->logged_at,
					$log->method,
					$log->route,
					$log->status_code,
					$log->auth_type,
					$log->duration_ms
				)
			);
		}
	}

	/**
	 * Alias: wp wb test-smtp
	 *
	 * [--email=<email>]
	 */
	public function test_smtp( $args, $assoc_args ) {
		$this->test( array( 'smtp' ), $assoc_args );
	}

	/**
	 * Alias: wp wb test-api
	 */
	public function test_api( $args, $assoc_args ) {
		$this->test( array( 'api' ), $assoc_args );
	}

	/**
	 * Alias: wp wb test-woocommerce
	 */
	public function test_woocommerce( $args, $assoc_args ) {
		$this->test( array( 'woocommerce' ), $assoc_args );
	}

	/**
	 * Alias: wp wb test-security
	 */
	public function test_security( $args, $assoc_args ) {
		$this->test( array( 'security' ), $assoc_args );
	}

	/**
	 * Alias: wp wb test-database
	 */
	public function test_database( $args, $assoc_args ) {
		$this->test( array( 'database' ), $assoc_args );
	}

	/**
	 * Run a single diagnostic test.
	 *
	 * @param string $name  Test name.
	 * @param string $email Email for SMTP.
	 * @return array<string, mixed>
	 */
	private function run_test( $name, $email ) {
		switch ( $name ) {
			case 'smtp':
				return WB_Diagnostics::test_smtp( $email );
			case 'api':
				return WB_Diagnostics::test_rest_api();
			case 'woocommerce':
				return WB_Diagnostics::test_woocommerce();
			case 'security':
				return WB_Diagnostics::test_security();
			case 'database':
				return WB_Diagnostics::test_database();
			default:
				return array(
					'success' => false,
					'message' => 'Unknown test: ' . $name,
				);
		}
	}
}

WP_CLI::add_command( 'wb', 'WB_CLI_Command' );
WP_CLI::add_command( 'wb test-smtp', array( 'WB_CLI_Command', 'test_smtp' ) );
WP_CLI::add_command( 'wb test-api', array( 'WB_CLI_Command', 'test_api' ) );
WP_CLI::add_command( 'wb test-woocommerce', array( 'WB_CLI_Command', 'test_woocommerce' ) );
WP_CLI::add_command( 'wb test-security', array( 'WB_CLI_Command', 'test_security' ) );
WP_CLI::add_command( 'wb test-database', array( 'WB_CLI_Command', 'test_database' ) );
