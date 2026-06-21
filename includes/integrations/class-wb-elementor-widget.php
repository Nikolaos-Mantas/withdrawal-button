<?php
/**
 * Elementor withdrawal form widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wb_withdrawal_form';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Withdrawal Form', WB_TEXT_DOMAIN );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories() {
		return array( 'wb', 'general' );
	}

	/**
	 * Widget keywords.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords() {
		return array( 'withdrawal', 'form', 'return', 'refund', 'cancel' );
	}

	/**
	 * Render widget output.
	 */
	protected function render() {
		echo WB_Form::get_form_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
