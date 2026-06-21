<?php
/**
 * Gutenberg block registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Blocks {

	/**
	 * Initialize block hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register withdrawal form block.
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			WB_PATH . 'blocks/withdrawal-form',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Render block output.
	 *
	 * @return string
	 */
	public static function render_block() {
		return WB_Form::get_form_html();
	}
}
