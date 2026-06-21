<?php
/**
 * WooCommerce integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_WooCommerce {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		$settings = WB_Settings::get();
		if ( ! $settings['woo_enabled'] ) {
			return;
		}

		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
		add_action( 'wp_ajax_wb_lookup_order', array( __CLASS__, 'ajax_lookup_order' ) );
		add_action( 'wp_ajax_nopriv_wb_lookup_order', array( __CLASS__, 'ajax_lookup_order' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_order_meta_box' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order_panel' ) );
	}

	/**
	 * Declare HPOS compatibility.
	 */
	public static function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WB_FILE, true );
		}
	}

	/**
	 * Validate WooCommerce order on submission.
	 *
	 * @param array<string, string> $fields Fields.
	 * @return array<int, string>
	 */
	public static function validate_order( $fields ) {
		$errors  = array();
		$settings = WB_Settings::get();
		$order    = self::find_order( $fields['order_number'] );

		if ( ! $order ) {
			$errors[] = __( 'The order number was not found.', WB_TEXT_DOMAIN );
			return $errors;
		}

		if ( $settings['woo_match_email'] ) {
			$order_email = strtolower( $order->get_billing_email() );
			$form_email  = strtolower( $fields['email'] );
			if ( $order_email && $form_email && $order_email !== $form_email ) {
				$errors[] = __( 'The email does not match the order.', WB_TEXT_DOMAIN );
			}
		}

		return $errors;
	}

	/**
	 * AJAX order lookup for autofill.
	 */
	public static function ajax_lookup_order() {
		check_ajax_referer( 'wb_form', 'nonce' );

		$order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		$order = self::find_order( $order_number );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', WB_TEXT_DOMAIN ) ) );
		}

		$settings = WB_Settings::get();
		if ( $settings['woo_match_email'] && $email ) {
			if ( strtolower( $order->get_billing_email() ) !== strtolower( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Email does not match order.', WB_TEXT_DOMAIN ) ) );
			}
		}

		wp_send_json_success( self::order_to_fields( $order ) );
	}

	/**
	 * Find order by number or ID.
	 *
	 * @param string $order_number Order number.
	 * @return WC_Order|null
	 */
	public static function find_order( $order_number ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_number );
		if ( $order ) {
			return $order;
		}

		$orders = wc_get_orders( array(
			'limit'      => 1,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_key'   => '_order_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $order_number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		) );

		if ( ! empty( $orders ) ) {
			return $orders[0];
		}

		return null;
	}

	/**
	 * Convert order to form fields.
	 *
	 * @param WC_Order $order Order object.
	 * @return array<string, string|int>
	 */
	public static function order_to_fields( $order ) {
		$products = array();
		foreach ( $order->get_items() as $item ) {
			$qty = $item->get_quantity();
			$products[] = $qty . 'x ' . $item->get_name();
		}

		return array(
			'name'         => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email'        => $order->get_billing_email(),
			'order_number' => $order->get_order_number(),
			'store'        => get_bloginfo( 'name' ),
			'products'     => implode( ', ', $products ),
			'wc_order_id'  => $order->get_id(),
		);
	}

	/**
	 * After request submitted: order note + meta.
	 *
	 * @param array<string, string> $fields     Fields.
	 * @param int                   $request_id Request ID.
	 */
	public static function on_request_submitted( $fields, $request_id ) {
		$settings = WB_Settings::get();
		$order_id = (int) $fields['wc_order_id'];

		if ( ! $order_id ) {
			$order = self::find_order( $fields['order_number'] );
			if ( $order ) {
				$order_id = $order->get_id();
			}
		}

		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_wb_withdrawal_request_id', $request_id );
		$order->save();

		if ( $settings['woo_add_order_note'] ) {
			$note = sprintf(
				/* translators: 1: request ID, 2: admin URL */
				__( 'Withdrawal request #%1$d submitted. Manage: %2$s', WB_TEXT_DOMAIN ),
				$request_id,
				admin_url( 'admin.php?page=wb-requests&view=' . $request_id )
			);
			$order->add_order_note( $note, false, true );
		}
	}

	/**
	 * Add meta box on order edit screen.
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post object.
	 */
	public static function add_order_meta_box( $post_type, $post ) {
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		add_meta_box(
			'wb-withdrawal',
			__( 'Withdrawal Request', WB_TEXT_DOMAIN ),
			array( __CLASS__, 'render_order_meta_box' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Render withdrawal panel on HPOS order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function render_order_panel( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$request_id = (int) $order->get_meta( '_wb_withdrawal_request_id' );
		echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Withdrawal Request', WB_TEXT_DOMAIN ) . ':</strong> ';
		if ( $request_id ) {
			$url = admin_url( 'admin.php?page=wb-requests&view=' . $request_id );
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'View request #%d', WB_TEXT_DOMAIN ), $request_id ) ) . '</a>';
		} else {
			echo esc_html__( 'No withdrawal request linked.', WB_TEXT_DOMAIN );
		}
		echo '</p>';
	}

	/**
	 * Render order meta box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order.
	 */
	public static function render_order_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'No order data.', WB_TEXT_DOMAIN ) . '</p>';
			return;
		}

		$request_id = (int) $order->get_meta( '_wb_withdrawal_request_id' );
		if ( ! $request_id ) {
			echo '<p>' . esc_html__( 'No withdrawal request linked.', WB_TEXT_DOMAIN ) . '</p>';
			return;
		}

		$url = admin_url( 'admin.php?page=wb-requests&view=' . $request_id );
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'View request #%d', WB_TEXT_DOMAIN ), $request_id ) ) . '</a></p>';
	}
}
