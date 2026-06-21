<?php

use PHPUnit\Framework\TestCase;

class WB_HelpersTest extends TestCase {

	public function test_apply_placeholders() {
		$repl = array(
			'{name}' => 'John',
			'{order_number}' => '99',
		);
		$text = 'Hello {name}, order #{order_number}';
		$this->assertSame( 'Hello John, order #99', wb_apply_placeholders( $text, $repl ) );
	}

	public function test_statuses_returns_five_statuses() {
		$statuses = wb_statuses();
		$this->assertCount( 5, $statuses );
		$this->assertArrayHasKey( 'new', $statuses );
		$this->assertArrayHasKey( 'completed', $statuses );
	}

	public function test_maybe_anonymize_ip_ipv4() {
		wb_test_reset( array( 'anonymize_ip' => 1 ) );

		$this->assertSame( '203.0.113.0', wb_maybe_anonymize_ip( '203.0.113.42' ) );
	}

	public function test_maybe_anonymize_ip_skips_when_disabled() {
		wb_test_reset( array( 'anonymize_ip' => 0 ) );

		$this->assertSame( '203.0.113.42', wb_maybe_anonymize_ip( '203.0.113.42' ) );
	}

	public function test_get_stores_parses_multiline_list() {
		wb_test_reset( array( 'stores' => "Athens\nThessaloniki\n" ) );

		$this->assertSame( array( 'Athens', 'Thessaloniki' ), wb_get_stores() );
	}

	public function test_build_replacements_includes_request_id() {
		wb_test_reset();

		$repl = wb_build_replacements(
			array(
				'name'         => 'Alice',
				'email'        => 'alice@example.com',
				'order_number' => '42',
				'products'     => 'Item',
			),
			99
		);

		$this->assertSame( 'Alice', $repl['{name}'] );
		$this->assertSame( '99', $repl['{request_id}'] );
		$this->assertSame( 'Test Site', $repl['{site_name}'] );
	}
}
