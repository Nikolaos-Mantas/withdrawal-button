<?php

use PHPUnit\Framework\TestCase;

class WB_FormTest extends TestCase {

	public function setUp(): void {
		wb_test_reset(
			array(
				'stores'               => '',
				'honeypot_enabled'     => 0,
				'time_trap_enabled'    => 0,
				'rate_limit_enabled'   => 0,
				'captcha_provider'     => 'none',
			)
		);
	}

	public function test_validate_fields_requires_core_fields() {
		$_POST['wb_declare'] = '1';
		$_POST['wb_privacy'] = '1';

		$errors = WB_Form::validate_fields(
			array(
				'name'         => '',
				'email'        => 'not-an-email',
				'order_number' => '',
				'store'        => '',
				'products'     => '',
				'message'      => '',
			)
		);

		$this->assertGreaterThanOrEqual( 4, count( $errors ) );
	}

	public function test_validate_fields_accepts_valid_submission() {
		$_POST['wb_declare'] = '1';
		$_POST['wb_privacy'] = '1';

		$errors = WB_Form::validate_fields(
			array(
				'name'         => 'Jane Doe',
				'email'        => 'jane@example.com',
				'order_number' => '1001',
				'store'        => '',
				'products'     => 'Widget A',
				'message'      => 'Optional note',
			)
		);

		$this->assertSame( array(), $errors );
	}

	public function test_validate_fields_requires_store_when_stores_configured() {
		update_option(
			'wb_settings',
			wp_parse_args(
				array( 'stores' => "Athens\nThessaloniki" ),
				WB_Settings::get()
			)
		);

		$_POST['wb_declare'] = '1';
		$_POST['wb_privacy'] = '1';

		$errors = WB_Form::validate_fields(
			array(
				'name'         => 'Jane Doe',
				'email'        => 'jane@example.com',
				'order_number' => '1001',
				'store'        => 'Invalid',
				'products'     => 'Widget A',
				'message'      => '',
			)
		);

		$this->assertNotEmpty( $errors );
	}

	public function test_validate_fields_requires_checkboxes() {
		$errors = WB_Form::validate_fields(
			array(
				'name'         => 'Jane Doe',
				'email'        => 'jane@example.com',
				'order_number' => '1001',
				'store'        => '',
				'products'     => 'Widget A',
				'message'      => '',
			)
		);

		$this->assertCount( 2, $errors );
	}
}
