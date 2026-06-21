<?php
/**
 * Import and export for requests and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Import_Export {

	/**
	 * Export withdrawal requests.
	 *
	 * @param string      $format csv|json.
	 * @param string|null $file   Optional file path to write.
	 * @return string|bool File content, true on file write, or false on failure.
	 */
	public static function export_requests( $format = 'csv', $file = null ) {
		global $wpdb;
		$table = wb_table_name();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY submitted_at DESC", ARRAY_A );

		if ( 'json' === $format ) {
			$payload = array(
				'plugin'      => WB_PLUGIN_SLUG,
				'version'     => WB_VERSION,
				'exported_at' => gmdate( 'c' ),
				'type'        => 'requests',
				'count'       => count( $rows ),
				'data'        => $rows ? $rows : array(),
			);
			$content = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			if ( $file ) {
				return (bool) file_put_contents( $file, $content );
			}
			return $content;
		}

		$stream = fopen( 'php://temp', 'r+' );
		if ( ! $stream ) {
			return false;
		}
		if ( $rows ) {
			fputcsv( $stream, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $stream, $row );
			}
		}
		rewind( $stream );
		$content = stream_get_contents( $stream );
		fclose( $stream );

		if ( $file ) {
			return (bool) file_put_contents( $file, $content );
		}
		return $content;
	}

	/**
	 * Import withdrawal requests from file.
	 *
	 * @param string $file_path File path.
	 * @param array  $args      Options: format (auto|csv|json), keep_ids (bool).
	 * @return array{success:bool,imported:int,skipped:int,errors:array<int,string>}
	 */
	public static function import_requests( $file_path, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'format'   => 'auto',
				'keep_ids' => false,
			)
		);

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success'  => false,
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'File not readable.', WB_TEXT_DOMAIN ) ),
			);
		}

		$format = $args['format'];
		if ( 'auto' === $format ) {
			$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
			$format = 'json' === $ext ? 'json' : 'csv';
		}

		$rows = array();
		if ( 'json' === $format ) {
			$decoded = json_decode( file_get_contents( $file_path ), true );
			if ( ! is_array( $decoded ) ) {
				return array(
					'success'  => false,
					'imported' => 0,
					'skipped'  => 0,
					'errors'   => array( __( 'Invalid JSON file.', WB_TEXT_DOMAIN ) ),
				);
			}
			$rows = isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : $decoded;
		} else {
			$handle = fopen( $file_path, 'r' );
			if ( ! $handle ) {
				return array(
					'success'  => false,
					'imported' => 0,
					'skipped'  => 0,
					'errors'   => array( __( 'Could not open CSV file.', WB_TEXT_DOMAIN ) ),
				);
			}
			$headers = fgetcsv( $handle );
			if ( ! $headers ) {
				fclose( $handle );
				return array(
					'success'  => false,
					'imported' => 0,
					'skipped'  => 0,
					'errors'   => array( __( 'CSV file is empty.', WB_TEXT_DOMAIN ) ),
				);
			}
			while ( ( $data = fgetcsv( $handle ) ) !== false ) {
				$row = array();
				foreach ( $headers as $i => $header ) {
					$row[ $header ] = isset( $data[ $i ] ) ? $data[ $i ] : '';
				}
				$rows[] = $row;
			}
			fclose( $handle );
		}

		global $wpdb;
		$table    = wb_table_name();
		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$skipped++;
				continue;
			}

			$data = self::sanitize_request_row( $row );
			if ( empty( $data['customer_email'] ) || empty( $data['order_number'] ) ) {
				$skipped++;
				continue;
			}

			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $args['keep_ids'] && $id ) {
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) );
				if ( $exists ) {
					$skipped++;
					continue;
				}
				$data['id'] = $id;
				$ok = $wpdb->insert( $table, $data );
			} else {
				unset( $data['id'] );
				$ok = $wpdb->insert( $table, $data );
			}

			if ( $ok ) {
				$imported++;
			} else {
				$errors[] = sprintf( __( 'Failed to import row for order %s.', WB_TEXT_DOMAIN ), $data['order_number'] );
			}
		}

		return array(
			'success'  => $imported > 0 || empty( $errors ),
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Sanitize imported request row.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private static function sanitize_request_row( $row ) {
		$now = current_time( 'mysql' );
		$status = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'new';
		if ( ! array_key_exists( $status, wb_statuses() ) ) {
			$status = 'new';
		}

		return array(
			'submitted_at'   => isset( $row['submitted_at'] ) ? sanitize_text_field( $row['submitted_at'] ) : $now,
			'customer_name'  => isset( $row['customer_name'] ) ? sanitize_text_field( $row['customer_name'] ) : '',
			'customer_email' => isset( $row['customer_email'] ) ? sanitize_email( $row['customer_email'] ) : '',
			'order_number'   => isset( $row['order_number'] ) ? sanitize_text_field( $row['order_number'] ) : '',
			'store'          => isset( $row['store'] ) ? sanitize_text_field( $row['store'] ) : '',
			'products'       => isset( $row['products'] ) ? sanitize_textarea_field( $row['products'] ) : '',
			'message'        => isset( $row['message'] ) ? sanitize_textarea_field( $row['message'] ) : '',
			'status'         => $status,
			'ip_address'     => isset( $row['ip_address'] ) ? substr( sanitize_text_field( $row['ip_address'] ), 0, 45 ) : '',
			'email_copy'     => isset( $row['email_copy'] ) ? sanitize_textarea_field( $row['email_copy'] ) : '',
			'updated_at'     => isset( $row['updated_at'] ) ? sanitize_text_field( $row['updated_at'] ) : $now,
			'wc_order_id'    => isset( $row['wc_order_id'] ) ? (int) $row['wc_order_id'] : 0,
			'status_history' => isset( $row['status_history'] ) ? sanitize_textarea_field( $row['status_history'] ) : '',
		);
	}

	/**
	 * Export plugin settings as JSON.
	 *
	 * @param string|null $file Optional file path.
	 * @return string|bool
	 */
	public static function export_settings( $file = null ) {
		$payload = array(
			'plugin'      => WB_PLUGIN_SLUG,
			'version'     => WB_VERSION,
			'exported_at' => gmdate( 'c' ),
			'type'        => 'settings',
			'data'        => WB_Settings::get(),
		);
		$content = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( $file ) {
			return (bool) file_put_contents( $file, $content );
		}
		return $content;
	}

	/**
	 * Import settings from JSON file.
	 *
	 * @param string $file_path File path.
	 * @param bool   $merge     Merge with existing settings.
	 * @return array{success:bool,message:string}
	 */
	public static function import_settings( $file_path, $merge = true ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not readable.', WB_TEXT_DOMAIN ),
			);
		}

		$decoded = json_decode( file_get_contents( $file_path ), true );
		if ( ! is_array( $decoded ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid JSON file.', WB_TEXT_DOMAIN ),
			);
		}

		$clean = WB_Settings::merge_import( $data );

		if ( ! $merge ) {
			$clean = wp_parse_args( $data, WB_Settings::defaults() );
		}

		update_option( 'wb_settings', $clean );

		return array(
			'success' => true,
			'message' => __( 'Settings imported successfully.', WB_TEXT_DOMAIN ),
		);
	}

	/**
	 * Send export download headers.
	 *
	 * @param string $filename Filename.
	 * @param string $mime     MIME type.
	 */
	public static function send_download_headers( $filename, $mime ) {
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename=' . $filename );
	}

	/**
	 * Export REST API logs as JSON.
	 *
	 * @param string|null $file Optional file path.
	 * @return string|bool
	 */
	public static function export_rest_logs( $file = null ) {
		$logs = WB_REST_Logger::get_logs( 500 );
		$payload = array(
			'plugin'      => WB_PLUGIN_SLUG,
			'version'     => WB_VERSION,
			'exported_at' => gmdate( 'c' ),
			'type'        => 'rest_logs',
			'count'       => count( $logs ),
			'data'        => $logs,
		);
		$content = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( $file ) {
			return (bool) file_put_contents( $file, $content );
		}
		return $content;
	}
}
