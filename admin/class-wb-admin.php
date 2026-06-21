<?php
/**
 * Admin bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wb_send_test_email', array( __CLASS__, 'ajax_send_test_email' ) );
		add_action( 'wp_ajax_wb_run_diagnostic', array( __CLASS__, 'ajax_run_diagnostic' ) );
		add_action( 'wp_ajax_wb_clear_rest_logs', array( __CLASS__, 'ajax_clear_rest_logs' ) );
		add_action( 'wp_ajax_wb_send_feedback', array( __CLASS__, 'ajax_send_feedback' ) );
	}

	/**
	 * Register admin menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Withdrawals', WB_TEXT_DOMAIN ),
			__( 'Withdrawals', WB_TEXT_DOMAIN ) . self::menu_badge(),
			'manage_options',
			'wb-requests',
			array( __CLASS__, 'requests_page' ),
			'dashicons-undo',
			56
		);

		add_submenu_page(
			'wb-requests',
			__( 'Requests', WB_TEXT_DOMAIN ),
			__( 'Requests', WB_TEXT_DOMAIN ),
			'manage_options',
			'wb-requests',
			array( __CLASS__, 'requests_page' )
		);

		add_submenu_page(
			'wb-requests',
			__( 'Withdrawal Settings', WB_TEXT_DOMAIN ),
			__( 'Settings', WB_TEXT_DOMAIN ),
			'manage_options',
			'wb-settings',
			array( 'WB_Settings_Page', 'render' )
		);
	}

	/**
	 * Menu badge for new requests.
	 *
	 * @return string
	 */
	private static function menu_badge() {
		global $wpdb;
		$table = wb_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return '';
		}
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'new'" );
		return $count ? ' <span class="awaiting-mod">' . $count . '</span>' : '';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'wb-' ) === false ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_style( 'wb-admin', WB_URL . 'assets/css/admin.css', array(), WB_VERSION );
		wp_enqueue_script( 'wb-admin', WB_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), WB_VERSION, true );
		wp_localize_script( 'wb-admin', 'wbAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wb_admin' ),
			'i18n'    => array(
				'testSent'   => __( 'Test email sent.', WB_TEXT_DOMAIN ),
				'testFailed' => __( 'Test failed.', WB_TEXT_DOMAIN ),
				'testOk'     => __( 'OK', WB_TEXT_DOMAIN ),
				'running'    => __( 'Running…', WB_TEXT_DOMAIN ),
				'selectLogo' => __( 'Select logo', WB_TEXT_DOMAIN ),
				'feedbackSent'   => __( 'Feedback sent. Thank you!', WB_TEXT_DOMAIN ),
				'feedbackFailed' => __( 'Could not send feedback. Try again or email the author directly.', WB_TEXT_DOMAIN ),
			),
		) );
	}

	/**
	 * Requests list / view page.
	 */
	public static function requests_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::handle_import_export();

		if ( isset( $_GET['export'] ) ) {
			self::handle_export( sanitize_key( $_GET['export'] ) );
			return;
		}

		if ( isset( $_GET['view'] ) ) {
			self::view_request( (int) $_GET['view'] );
			return;
		}

		self::handle_actions();

		$table = new WB_Requests_Table();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Withdrawal Requests', WB_TEXT_DOMAIN ) . '</h1>';
		$export_csv_url = wp_nonce_url( admin_url( 'admin.php?page=wb-requests&export=csv' ), 'wb_export' );
		$export_json_url = wp_nonce_url( admin_url( 'admin.php?page=wb-requests&export=json' ), 'wb_export' );
		echo ' <a href="' . esc_url( $export_csv_url ) . '" class="page-title-action">' . esc_html__( 'Export CSV', WB_TEXT_DOMAIN ) . '</a>';
		echo ' <a href="' . esc_url( $export_json_url ) . '" class="page-title-action">' . esc_html__( 'Export JSON', WB_TEXT_DOMAIN ) . '</a>';
		echo '<hr class="wp-header-end">';

		self::render_import_form( 'requests' );

		$table->views();
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="wb-requests">';
		if ( isset( $_GET['status'] ) ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( $_GET['status'] ) ) . '">';
		}
		$table->search_box( __( 'Search requests', WB_TEXT_DOMAIN ), 'wb-search' );
		$table->display();
		echo '</form>';
		echo wb_admin_footer_credit();
		echo '</div>';
	}

	/**
	 * Handle admin POST actions.
	 */
	private static function handle_actions() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wb_admin_action' ) ) {
			return;
		}

		global $wpdb;
		$table = wb_table_name();

		if ( isset( $_POST['wb_action'], $_POST['wb_id'] ) ) {
			$id     = (int) $_POST['wb_id'];
			$action = sanitize_key( $_POST['wb_action'] );

			if ( 'status' === $action && isset( $_POST['wb_status'] ) ) {
				$new_status = sanitize_key( $_POST['wb_status'] );
				if ( array_key_exists( $new_status, wb_statuses() ) ) {
					self::update_status( $id, $new_status );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Status updated.', WB_TEXT_DOMAIN ) . '</p></div>';
				}
			}

			if ( 'delete' === $action ) {
				$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Request deleted.', WB_TEXT_DOMAIN ) . '</p></div>';
			}
		}
	}

	/**
	 * Update request status and send email.
	 *
	 * @param int    $id     Request ID.
	 * @param string $status New status.
	 */
	public static function update_status( $id, $status ) {
		return WB_Requests::update_status( $id, $status );
	}

	/**
	 * View single request.
	 *
	 * @param int $id Request ID.
	 */
	private static function view_request( $id ) {
		global $wpdb;
		$table = wb_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		$statuses = wb_statuses();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( sprintf( __( 'Withdrawal Request #%d', WB_TEXT_DOMAIN ), $id ) );
		echo ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=wb-requests' ) ) . '">' . esc_html__( 'Back to list', WB_TEXT_DOMAIN ) . '</a></h1>';

		if ( ! $row ) {
			echo '<p>' . esc_html__( 'Request not found.', WB_TEXT_DOMAIN ) . '</p></div>';
			return;
		}

		$history = json_decode( (string) $row->status_history, true );
		?>
		<table class="widefat fixed striped" style="max-width:900px">
			<tbody>
				<tr><th><?php esc_html_e( 'Submitted at', WB_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( wb_format_datetime( $row->submitted_at, true ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Name', WB_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $row->customer_name ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', WB_TEXT_DOMAIN ); ?></th><td><a href="mailto:<?php echo esc_attr( $row->customer_email ); ?>"><?php echo esc_html( $row->customer_email ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Order number', WB_TEXT_DOMAIN ); ?></th><td>#<?php echo esc_html( $row->order_number ); ?>
					<?php if ( $row->wc_order_id && function_exists( 'wc_get_order' ) ) :
						$order_url = admin_url( 'post.php?post=' . (int) $row->wc_order_id . '&action=edit' );
						if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
							$order_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $row->wc_order_id );
						}
						?>
						&nbsp;<a href="<?php echo esc_url( $order_url ); ?>"><?php esc_html_e( 'View WooCommerce order', WB_TEXT_DOMAIN ); ?></a>
					<?php endif; ?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Store', WB_TEXT_DOMAIN ); ?></th><td><?php echo $row->store ? esc_html( $row->store ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Products', WB_TEXT_DOMAIN ); ?></th><td><?php echo nl2br( esc_html( $row->products ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Message', WB_TEXT_DOMAIN ); ?></th><td><?php echo $row->message ? nl2br( esc_html( $row->message ) ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Status', WB_TEXT_DOMAIN ); ?></th><td><strong><?php echo esc_html( $statuses[ $row->status ] ?? $row->status ); ?></strong></td></tr>
				<tr><th><?php esc_html_e( 'IP address', WB_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $row->ip_address ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Last updated', WB_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $row->updated_at ? wb_format_datetime( $row->updated_at, true ) : '—' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Confirmation email copy', WB_TEXT_DOMAIN ); ?></th><td><pre style="white-space:pre-wrap;font-family:inherit;margin:0"><?php echo esc_html( $row->email_copy ); ?></pre></td></tr>
			</tbody>
		</table>

		<?php if ( is_array( $history ) && $history ) : ?>
			<h2><?php esc_html_e( 'Status history', WB_TEXT_DOMAIN ); ?></h2>
			<table class="widefat fixed striped" style="max-width:900px">
				<thead><tr><th><?php esc_html_e( 'Status', WB_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Date', WB_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'By', WB_TEXT_DOMAIN ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $history as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $statuses[ $entry['status'] ] ?? $entry['status'] ); ?></td>
						<td><?php echo esc_html( wb_format_datetime( $entry['at'], true ) ); ?></td>
						<td><?php echo esc_html( $entry['by'] === 'customer' ? __( 'Customer', WB_TEXT_DOMAIN ) : (string) $entry['by'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" style="margin-top:15px;display:flex;gap:8px;align-items:center">
			<?php wp_nonce_field( 'wb_admin_action' ); ?>
			<input type="hidden" name="wb_action" value="status">
			<input type="hidden" name="wb_id" value="<?php echo (int) $row->id; ?>">
			<label><strong><?php esc_html_e( 'Change status:', WB_TEXT_DOMAIN ); ?></strong></label>
			<select name="wb_status">
				<?php foreach ( $statuses as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button button-primary"><?php esc_html_e( 'Update', WB_TEXT_DOMAIN ); ?></button>
		</form>
		<?php echo wb_admin_footer_credit(); ?>
		</div>
		<?php
	}

	/**
	 * Handle export download.
	 *
	 * @param string $type Export type: csv, json, settings, rest-logs.
	 */
	public static function handle_export( $type ) {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wb_export' ) ) {
			wp_die( esc_html__( 'Invalid export request.', WB_TEXT_DOMAIN ) );
		}

		switch ( $type ) {
			case 'csv':
				$content = WB_Import_Export::export_requests( 'csv' );
				WB_Import_Export::send_download_headers( 'withdrawal-requests.csv', 'text/csv; charset=utf-8' );
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			case 'json':
				$content = WB_Import_Export::export_requests( 'json' );
				WB_Import_Export::send_download_headers( 'withdrawal-requests.json', 'application/json; charset=utf-8' );
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			case 'settings':
				$content = WB_Import_Export::export_settings();
				WB_Import_Export::send_download_headers( 'wb-settings.json', 'application/json; charset=utf-8' );
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			case 'rest-logs':
				$content = WB_Import_Export::export_rest_logs();
				WB_Import_Export::send_download_headers( 'wb-rest-logs.json', 'application/json; charset=utf-8' );
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
		}

		wp_die( esc_html__( 'Unknown export type.', WB_TEXT_DOMAIN ) );
	}

	public static function handle_import_export() {
		if ( ! isset( $_POST['wb_import_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wb_import_nonce'] ), 'wb_import' ) ) {
			return;
		}

		if ( empty( $_FILES['wb_import_file']['tmp_name'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No file uploaded.', WB_TEXT_DOMAIN ) . '</p></div>';
			return;
		}

		$type = isset( $_POST['wb_import_type'] ) ? sanitize_key( $_POST['wb_import_type'] ) : '';
		$tmp  = $_FILES['wb_import_file']['tmp_name'];

		if ( 'requests' === $type ) {
			$keep_ids = ! empty( $_POST['wb_import_keep_ids'] );
			$result   = WB_Import_Export::import_requests(
				$tmp,
				array(
					'format'   => 'auto',
					'keep_ids' => $keep_ids,
				)
			);
			$class = $result['success'] ? 'notice-success' : 'notice-warning';
			$msg   = sprintf(
				__( 'Imported %1$d requests, skipped %2$d.', WB_TEXT_DOMAIN ),
				$result['imported'],
				$result['skipped']
			);
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		} elseif ( 'settings' === $type ) {
			$merge  = empty( $_POST['wb_import_merge'] ) ? false : true;
			$result = WB_Import_Export::import_settings( $tmp, $merge );
			$class  = $result['success'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $result['message'] ) . '</p></div>';
		}
	}

	public static function render_import_form( $type ) {
		?>
		<div class="wb-import-box" style="margin:12px 0;padding:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px">
			<strong><?php esc_html_e( 'Import', WB_TEXT_DOMAIN ); ?></strong>
			<form method="post" enctype="multipart/form-data" style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
				<?php wp_nonce_field( 'wb_import', 'wb_import_nonce' ); ?>
				<input type="hidden" name="wb_import_type" value="<?php echo esc_attr( $type ); ?>">
				<input type="file" name="wb_import_file" accept=".json,.csv" required>
				<?php if ( 'requests' === $type ) : ?>
					<label><input type="checkbox" name="wb_import_keep_ids" value="1"> <?php esc_html_e( 'Keep original IDs', WB_TEXT_DOMAIN ); ?></label>
				<?php else : ?>
					<label><input type="checkbox" name="wb_import_merge" value="1" checked> <?php esc_html_e( 'Merge with current settings', WB_TEXT_DOMAIN ); ?></label>
				<?php endif; ?>
				<button type="submit" class="button"><?php esc_html_e( 'Import file', WB_TEXT_DOMAIN ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX: clear REST logs.
	 */
	public static function ajax_clear_rest_logs() {
		check_ajax_referer( 'wb_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		WB_REST_Logger::clear_logs();
		wp_send_json_success();
	}

	/**
	 * AJAX: send test email.
	 */
	public static function ajax_send_test_email() {
		check_ajax_referer( 'wb_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$to = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email.', WB_TEXT_DOMAIN ) ) );
		}

		$sent = WB_Emails::send_test_email( $to );
		if ( $sent ) {
			wp_send_json_success();
		}
		wp_send_json_error();
	}

	/**
	 * AJAX: run diagnostic test.
	 */
	public static function ajax_run_diagnostic() {
		check_ajax_referer( 'wb_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', WB_TEXT_DOMAIN ) ) );
		}

		$test = isset( $_POST['test'] ) ? sanitize_key( wp_unslash( $_POST['test'] ) ) : '';
		$result = array();

		switch ( $test ) {
			case 'smtp':
				$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : get_option( 'admin_email' );
				$result = WB_Diagnostics::test_smtp( $email );
				break;
			case 'rest_api':
				$result = WB_Diagnostics::test_rest_api();
				break;
			case 'woocommerce':
				$result = WB_Diagnostics::test_woocommerce();
				break;
			case 'security':
				$result = WB_Diagnostics::test_security();
				break;
			case 'database':
				$result = WB_Diagnostics::test_database();
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown test.', WB_TEXT_DOMAIN ) ) );
		}

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( $result );
	}

	/**
	 * AJAX: send plugin feedback to the author.
	 */
	public static function ajax_send_feedback() {
		check_ajax_referer( 'wb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', WB_TEXT_DOMAIN ) ) );
		}

		$validated = WB_Feedback::validate(
			array(
				'type'    => isset( $_POST['type'] ) ? wp_unslash( $_POST['type'] ) : '',
				'email'   => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
				'message' => isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '',
			)
		);

		if ( $validated['errors'] ) {
			wp_send_json_error( array( 'message' => implode( ' ', $validated['errors'] ) ) );
		}

		if ( WB_Feedback::send( $validated['data'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Feedback sent. Thank you!', WB_TEXT_DOMAIN ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Could not send feedback. Try again or email the author directly.', WB_TEXT_DOMAIN ) ) );
	}
}
