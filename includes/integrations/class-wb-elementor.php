<?php
/**
 * Elementor widget for withdrawal form.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Elementor {

	/**
	 * Initialize Elementor integration.
	 */
	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
	}

	/**
	 * Register custom Elementor category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 */
	public static function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'wb',
			array(
				'title' => __( 'Withdrawal', WB_TEXT_DOMAIN ),
				'icon'  => 'fa fa-undo',
			)
		);
	}

	/**
	 * Register widget class.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 */
	public static function register_widget( $widgets_manager ) {
		require_once WB_PATH . 'includes/integrations/class-wb-elementor-widget.php';
		$widgets_manager->register( new WB_Elementor_Widget() );
	}
}
