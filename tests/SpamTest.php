<?php

use PHPUnit\Framework\TestCase;

class WB_SpamTest extends TestCase {

	public function setUp(): void {
		wb_test_reset(
			array(
				'honeypot_enabled'   => 1,
				'time_trap_enabled'  => 1,
				'time_trap_seconds'  => 5,
				'rate_limit_enabled' => 1,
				'rate_limit_count'   => 3,
				'rate_limit_window'  => 3600,
				'captcha_provider'   => 'none',
			)
		);
	}

	public function test_honeypot_rejects_bot_submission() {
		$_POST['wb_website'] = 'http://spam.example';

		$errors = WB_Spam::validate();

		$this->assertCount( 1, $errors );
		$this->assertSame( 'Submission rejected.', $errors[0] );
	}

	public function test_time_trap_rejects_fast_submission() {
		$_POST['wb_loaded_at'] = time();

		$errors = WB_Spam::validate();

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'wait', strtolower( $errors[0] ) );
	}

	public function test_time_trap_allows_after_minimum_delay() {
		$_POST['wb_loaded_at'] = time() - 10;

		$errors = WB_Spam::validate();

		$this->assertSame( array(), $errors );
	}

	public function test_rate_limit_blocks_excess_submissions() {
		$ip  = wb_get_ip();
		$key = 'wb_rate_' . md5( $ip );
		set_transient( $key, 3, 3600 );

		$errors = WB_Spam::validate();

		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'Too many', $errors[0] );
	}

	public function test_increment_rate_limit_increases_counter() {
		WB_Spam::increment_rate_limit();

		$key = 'wb_rate_' . md5( wb_get_ip() );
		$this->assertSame( 1, (int) get_transient( $key ) );
	}

	public function test_captcha_without_keys_is_not_active() {
		wb_test_reset(
			array(
				'honeypot_enabled'   => 0,
				'time_trap_enabled'  => 0,
				'rate_limit_enabled' => 0,
				'captcha_provider'   => 'recaptcha_v2',
				'recaptcha_v2_site'  => '',
				'recaptcha_v2_secret'=> '',
			)
		);

		$this->assertFalse( wb_is_captcha_configured() );
		$this->assertSame( '', WB_Spam::render_captcha_field() );
		$this->assertSame( array(), WB_Spam::validate() );
	}

	public function test_captcha_requires_both_keys() {
		wb_test_reset(
			array(
				'captcha_provider'    => 'turnstile',
				'turnstile_site'      => 'site-key',
				'turnstile_secret'    => 'secret-key',
			)
		);

		$this->assertTrue( wb_is_captcha_configured() );
	}

	public function test_sanitize_resets_captcha_without_keys() {
		wb_test_reset(
			array(
				'captcha_provider' => 'recaptcha_v3',
			)
		);

		$result = WB_Settings::sanitize(
			array(
				'wb_settings_tab' => 'security',
				'captcha_provider' => 'recaptcha_v3',
			)
		);

		$this->assertSame( 'none', $result['captcha_provider'] );
	}
}
