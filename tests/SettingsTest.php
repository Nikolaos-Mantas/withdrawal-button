<?php

use PHPUnit\Framework\TestCase;

class WB_SettingsTest extends TestCase {

	public function test_defaults_include_rest_api_settings() {
		$defaults = WB_Settings::defaults();
		$this->assertArrayHasKey( 'rest_api_enabled', $defaults );
		$this->assertSame( 0, $defaults['rest_api_enabled'] );
		$this->assertArrayHasKey( 'rest_api_logging_enabled', $defaults );
		$this->assertSame( 0, $defaults['rest_api_logging_enabled'] );
		$this->assertArrayHasKey( 'woo_enabled', $defaults );
		$this->assertSame( 0, $defaults['woo_enabled'] );
		$this->assertSame( 'stable', $defaults['update_channel'] );
	}

	public function test_rest_api_tab_generates_key_when_enabled() {
		$input = array(
			'wb_settings_tab' => 'rest-api',
			'rest_api_enabled' => '1',
		);
		$result = WB_Settings::sanitize( $input );
		$this->assertSame( 1, $result['rest_api_enabled'] );
		$this->assertNotEmpty( $result['rest_api_key'] );
	}

	public function test_general_tab_sanitizes_email_and_retention() {
		wb_test_reset();

		$result = WB_Settings::sanitize(
			array(
				'wb_settings_tab'  => 'general',
				'admin_email'      => 'shop@example.com',
				'withdrawal_days'  => '0',
				'retention_months' => '0',
				'anonymize_ip'     => '1',
			)
		);

		$this->assertSame( 'shop@example.com', $result['admin_email'] );
		$this->assertSame( 1, $result['withdrawal_days'] );
		$this->assertSame( 1, $result['retention_months'] );
		$this->assertSame( 1, $result['anonymize_ip'] );
	}

	public function test_branding_tab_rejects_invalid_color() {
		wb_test_reset();

		$result = WB_Settings::sanitize(
			array(
				'wb_settings_tab' => 'branding',
				'color_primary'   => 'not-a-color',
			)
		);

		$this->assertSame( '#333333', $result['color_primary'] );
	}

	public function test_security_tab_validates_captcha_provider() {
		wb_test_reset();

		$result = WB_Settings::sanitize(
			array(
				'wb_settings_tab'  => 'security',
				'captcha_provider' => 'invalid_provider',
			)
		);

		$this->assertSame( 'none', $result['captcha_provider'] );
	}
}
