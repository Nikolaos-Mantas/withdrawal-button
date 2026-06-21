<?php
/**
 * Main plugin loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( 'WB_Install', 'maybe_upgrade' ) );
		add_action( 'wb_daily_cleanup', array( 'WB_Install', 'daily_cleanup' ) );

		WB_Form::init();
		WB_Blocks::init();
		WB_REST_API::init();
		WB_Updater::init();
		WB_Privacy::init();

		if ( is_admin() ) {
			WB_Admin::init();
		}

		if ( class_exists( 'WooCommerce' ) && class_exists( 'WB_WooCommerce' ) ) {
			WB_WooCommerce::init();
		}

		add_action( 'elementor/loaded', array( $this, 'init_elementor' ) );
		if ( did_action( 'elementor/loaded' ) ) {
			$this->init_elementor();
		}
	}

	/**
	 * Load Elementor widget when Elementor is available.
	 */
	public function init_elementor() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}
		require_once WB_PATH . 'includes/integrations/class-wb-elementor.php';
		WB_Elementor::init();
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			WB_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( WB_FILE ) ) . '/languages'
		);
	}
}
