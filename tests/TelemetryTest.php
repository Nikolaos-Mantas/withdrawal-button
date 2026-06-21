<?php

use PHPUnit\Framework\TestCase;

class TelemetryTest extends TestCase {

	public function test_is_enabled_false_by_default() {
		wb_test_reset();
		$this->assertFalse( WB_Telemetry::is_enabled() );
	}

	public function test_build_payload_shape() {
		wb_test_reset();

		$payload = WB_Telemetry::build_payload();

		$this->assertArrayHasKey( 'site_id', $payload );
		$this->assertArrayHasKey( 'plugin_version', $payload );
		$this->assertSame( '3.3.0', $payload['plugin_version'] );
		$this->assertArrayHasKey( 'wp_version', $payload );
		$this->assertArrayHasKey( 'php_version', $payload );
		$this->assertArrayHasKey( 'locale', $payload );
		$this->assertArrayHasKey( 'woo_enabled', $payload );
		$this->assertArrayHasKey( 'rest_api_enabled', $payload );
		$this->assertArrayHasKey( 'captcha_provider', $payload );
		$this->assertArrayHasKey( 'request_count', $payload );
		$this->assertArrayHasKey( 'anonymized_count', $payload );
		$this->assertSame( 64, strlen( $payload['site_id'] ) );
	}

	public function test_maybe_send_skips_when_disabled() {
		wb_test_reset();
		$GLOBALS['wb_test_last_remote_post'] = null;

		WB_Telemetry::maybe_send();

		$this->assertNull( $GLOBALS['wb_test_last_remote_post'] );
	}

	public function test_maybe_send_posts_when_enabled() {
		wb_test_reset( array( 'telemetry_enabled' => 1 ) );

		WB_Telemetry::maybe_send();

		$this->assertNotNull( $GLOBALS['wb_test_last_remote_post'] );
		$this->assertSame( WB_TELEMETRY_URL, $GLOBALS['wb_test_last_remote_post']['url'] );
		$body = json_decode( $GLOBALS['wb_test_last_remote_post']['args']['body'], true );
		$this->assertIsArray( $body );
		$this->assertSame( '3.3.0', $body['plugin_version'] );
	}
}
