<?php

use PHPUnit\Framework\TestCase;

class PrivacyTest extends TestCase {

	public function test_defaults_include_gdpr_settings() {
		$defaults = WB_Settings::defaults();
		$this->assertSame( 1, $defaults['anonymize_ip'] );
		$this->assertSame( 'delete', $defaults['retention_action'] );
		$this->assertSame( 1, $defaults['captcha_require_privacy_consent'] );
	}

	public function test_consent_version_uses_plugin_version() {
		$this->assertSame( WB_VERSION, WB_Privacy::consent_version() );
	}

	public function test_capture_consent_meta_returns_timestamps() {
		$meta = WB_Privacy::capture_consent_meta();
		$this->assertNotEmpty( $meta['privacy_consent_at'] );
		$this->assertNotEmpty( $meta['declare_consent_at'] );
		$this->assertArrayHasKey( 'privacy_policy_url', $meta );
		$this->assertStringStartsWith( WB_VERSION, $meta['privacy_consent_version'] );
	}

	public function test_form_data_notice_includes_retention_months() {
		wb_test_reset( array( 'retention_months' => 24 ) );
		$notice = WB_Privacy::form_data_notice();
		$this->assertStringContainsString( '24', $notice );
	}

	public function test_privacy_policy_text_lists_collected_fields() {
		$text = WB_Privacy::privacy_policy_text();
		$this->assertStringContainsString( 'Withdrawal requests', $text );
		$this->assertStringContainsString( 'Email address', $text );
	}

	public function test_register_exporters_adds_withdrawal_requests() {
		$exporters = WB_Privacy::register_exporters( array() );
		$this->assertArrayHasKey( 'withdrawal-button-requests', $exporters );
		$this->assertSame( 'Withdrawal requests', $exporters['withdrawal-button-requests']['exporter_friendly_name'] );
	}

	public function test_register_erasers_adds_withdrawal_requests() {
		$erasers = WB_Privacy::register_erasers( array() );
		$this->assertArrayHasKey( 'withdrawal-button-requests', $erasers );
	}

	public function test_build_replacements_uses_stored_ip_when_provided() {
		$repl = wb_build_replacements( array(
			'name' => 'Test',
			'ip'   => '203.0.113.0',
		) );
		$this->assertSame( '203.0.113.0', $repl['{ip}'] );
	}

	public function test_sanitize_retention_action() {
		wb_test_reset();
		$result = WB_Settings::sanitize(
			array(
				'wb_settings_tab'  => 'general',
				'retention_action' => 'anonymize',
			)
		);
		$this->assertSame( 'anonymize', $result['retention_action'] );
	}
}
