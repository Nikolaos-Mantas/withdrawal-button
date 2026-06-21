<?php
/**
 * WP_List_Table for withdrawal requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WB_Requests_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'plural'   => 'wb-requests',
			'singular' => 'wb-request',
			'ajax'     => false,
		) );
	}

	/**
	 * Get columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'id'           => __( 'ID', WB_TEXT_DOMAIN ),
			'submitted_at' => __( 'Date', WB_TEXT_DOMAIN ),
			'customer_name' => __( 'Name', WB_TEXT_DOMAIN ),
			'customer_email' => __( 'Email', WB_TEXT_DOMAIN ),
			'order_number' => __( 'Order', WB_TEXT_DOMAIN ),
			'store'        => __( 'Store', WB_TEXT_DOMAIN ),
			'products'     => __( 'Products', WB_TEXT_DOMAIN ),
			'status'       => __( 'Status', WB_TEXT_DOMAIN ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'           => array( 'id', false ),
			'submitted_at' => array( 'submitted_at', true ),
			'order_number' => array( 'order_number', false ),
			'status'       => array( 'status', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'delete'    => __( 'Delete', WB_TEXT_DOMAIN ),
			'anonymize' => __( 'Anonymize', WB_TEXT_DOMAIN ),
		);
		foreach ( wb_statuses() as $key => $label ) {
			$actions[ 'status_' . $key ] = sprintf( __( 'Mark as %s', WB_TEXT_DOMAIN ), $label );
		}
		return $actions;
	}

	/**
	 * Status filter views.
	 */
	public function views() {
		global $wpdb;
		$table   = wb_table_name();
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$statuses = wb_statuses();

		$links = array();
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$class = $current ? '' : 'current';
		$links['all'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wb-requests' ) ) . '" class="' . $class . '">' . esc_html__( 'All', WB_TEXT_DOMAIN ) . ' (' . $total . ')</a>';

		foreach ( $statuses as $key => $label ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $key ) );
			$class = $current === $key ? 'current' : '';
			$url   = admin_url( 'admin.php?page=wb-requests&status=' . $key );
			$links[ $key ] = '<a href="' . esc_url( $url ) . '" class="' . $class . '">' . esc_html( $label ) . ' (' . $count . ')</a>';
		}

		echo '<ul class="subsubsub">';
		$i = 0;
		foreach ( $links as $link ) {
			if ( $i ) {
				echo ' | ';
			}
			echo '<li>' . $link . '</li>';
			$i++;
		}
		echo '</ul>';
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		global $wpdb;
		$table = wb_table_name();

		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'wb_per_page', 20 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( isset( $_GET['status'] ) ) {
			$status = sanitize_key( $_GET['status'] );
			if ( array_key_exists( $status, wb_statuses() ) ) {
				$where   .= ' AND status = %s';
				$params[] = $status;
			}
		}

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( $search ) {
			$where   .= ' AND (customer_name LIKE %s OR customer_email LIKE %s OR order_number LIKE %s OR products LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'submitted_at';
		$allowed = array( 'id', 'submitted_at', 'order_number', 'status' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'submitted_at';
		}
		$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ) ? 'ASC' : 'DESC';

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$data_sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$this->items = $wpdb->get_results( $wpdb->prepare( $data_sql, $query_params ) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		$action = $this->current_action();
		$ids    = isset( $_POST['id'] ) ? array_map( 'intval', (array) $_POST['id'] ) : array();
		if ( ! $ids ) {
			return;
		}

		global $wpdb;
		$table = wb_table_name();

		if ( 'delete' === $action ) {
			foreach ( $ids as $id ) {
				$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
			}
		}

		if ( 'anonymize' === $action ) {
			$done = 0;
			foreach ( $ids as $id ) {
				if ( WB_Privacy::anonymize_request( $id ) ) {
					$done++;
				}
			}
			if ( $done ) {
				WB_Audit_Log::log( 'bulk_anonymize', array( 'count' => $done ) );
			}
		}

		if ( 0 === strpos( $action, 'status_' ) ) {
			$status = substr( $action, 7 );
			if ( array_key_exists( $status, wb_statuses() ) ) {
				foreach ( $ids as $id ) {
					WB_Requests::update_status( $id, $status );
				}
			}
		}
	}

	/**
	 * Default column output.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				$url = admin_url( 'admin.php?page=wb-requests&view=' . (int) $item->id );
				return '<a href="' . esc_url( $url ) . '"><strong>' . (int) $item->id . '</strong></a>';
			case 'submitted_at':
				return esc_html( wb_format_datetime( $item->submitted_at ) );
			case 'customer_name':
				$html = esc_html( $item->customer_name );
				if ( ! empty( $item->anonymized_at ) ) {
					$html .= ' <span class="wb-anonymized-badge" title="' . esc_attr__( 'Anonymized', WB_TEXT_DOMAIN ) . '">*</span>';
				}
				return $html;
			case 'customer_email':
				return '<a href="mailto:' . esc_attr( $item->customer_email ) . '">' . esc_html( $item->customer_email ) . '</a>';
			case 'order_number':
				$html = '#' . esc_html( $item->order_number );
				if ( $item->wc_order_id && function_exists( 'wc_get_order' ) ) {
					$url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $item->wc_order_id );
					$html .= ' <a href="' . esc_url( $url ) . '" class="wb-woo-link">WC</a>';
				}
				return $html;
			case 'store':
				return $item->store ? esc_html( $item->store ) : '—';
			case 'products':
				return esc_html( wp_trim_words( $item->products, 8, '…' ) );
			case 'status':
				$statuses = wb_statuses();
				return '<span class="wb-status wb-status-' . esc_attr( $item->status ) . '">' . esc_html( $statuses[ $item->status ] ?? $item->status ) . '</span>';
		}
		return '';
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="id[]" value="%d">', (int) $item->id );
	}
}
