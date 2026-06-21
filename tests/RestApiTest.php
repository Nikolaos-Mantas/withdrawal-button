<?php

use PHPUnit\Framework\TestCase;

class WB_RestApiTest extends TestCase {

	public function setUp(): void {
		wb_test_reset(
			array(
				'rest_api_enabled' => 1,
				'rest_api_key'     => 'secret-api-key',
			)
		);
	}

	public function test_is_enabled_reflects_settings() {
		$this->assertTrue( WB_REST_API::is_enabled() );

		update_option(
			'wb_settings',
			wp_parse_args( array( 'rest_api_enabled' => 0 ), WB_Settings::get() )
		);

		$this->assertFalse( WB_REST_API::is_enabled() );
	}

	public function test_validate_api_key_accepts_header() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WB-API-Key', 'secret-api-key' );

		$this->assertTrue( WB_REST_API::validate_api_key( $request ) );
	}

	public function test_validate_api_key_accepts_query_param() {
		$request = new WP_REST_Request();
		$request->set_param( 'api_key', 'secret-api-key' );

		$this->assertTrue( WB_REST_API::validate_api_key( $request ) );
	}

	public function test_validate_api_key_rejects_invalid_key() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WB-API-Key', 'wrong-key' );

		$this->assertFalse( WB_REST_API::validate_api_key( $request ) );
	}

	public function test_authorize_returns_error_when_disabled() {
		update_option(
			'wb_settings',
			wp_parse_args( array( 'rest_api_enabled' => 0 ), WB_Settings::get() )
		);

		$result = WB_REST_API::authorize( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wb_rest_disabled', $result->code );
		$this->assertSame( 403, $result->data['status'] );
	}

	public function test_authorize_returns_error_without_credentials() {
		$result = WB_REST_API::authorize( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wb_rest_forbidden', $result->code );
		$this->assertSame( 401, $result->data['status'] );
	}

	public function test_authorize_allows_valid_api_key() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WB-API-Key', 'secret-api-key' );

		$this->assertTrue( WB_REST_API::authorize( $request ) );
	}

	public function test_generate_api_key_length() {
		$key = WB_REST_API::generate_api_key();
		$this->assertSame( 48, strlen( $key ) );
	}
}
