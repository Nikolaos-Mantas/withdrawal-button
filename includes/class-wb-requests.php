<?php
/**
 * Withdrawal request data operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Requests {

	/**
	 * Update request status and send notification email.
	 *
	 * @param int    $id     Request ID.
	 * @param string $status New status key.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$table = wb_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( ! $row || ! array_key_exists( $status, wb_statuses() ) ) {
			return false;
		}

		if ( $row->status === $status ) {
			return true;
		}

		$history = json_decode( (string) $row->status_history, true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$by = is_user_logged_in() ? get_current_user_id() : 'api';
		$history[] = array(
			'status' => $status,
			'at'     => current_time( 'mysql' ),
			'by'     => $by,
		);

		$wpdb->update(
			$table,
			array(
				'status'         => $status,
				'updated_at'     => current_time( 'mysql' ),
				'status_history' => wp_json_encode( $history ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$fields = array(
			'name'         => $row->customer_name,
			'email'        => $row->customer_email,
			'order_number' => $row->order_number,
			'store'        => $row->store,
			'products'     => $row->products,
			'message'      => $row->message,
			'status'       => $status,
		);

		WB_Emails::send_status_email( $status, $fields, $id );

		return true;
	}

	/**
	 * Delete a request.
	 *
	 * @param int $id Request ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( wb_table_name(), array( 'id' => (int) $id ), array( '%d' ) );
	}
}
