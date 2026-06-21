<?php

use PHPUnit\Framework\TestCase;

class WB_FeedbackTest extends TestCase {

	public function setUp(): void {
		wb_test_reset();
	}

	public function test_validate_rejects_empty_message() {
		$result = WB_Feedback::validate(
			array(
				'type'    => 'bug',
				'email'   => 'user@example.com',
				'message' => '',
			)
		);

		$this->assertNotEmpty( $result['errors'] );
	}

	public function test_validate_accepts_valid_input() {
		$result = WB_Feedback::validate(
			array(
				'type'    => 'idea',
				'email'   => 'user@example.com',
				'message' => 'Add export to PDF',
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 'idea', $result['data']['type'] );
		$this->assertSame( 'user@example.com', $result['data']['email'] );
	}

	public function test_type_label() {
		$this->assertSame( 'Bug report', WB_Feedback::type_label( 'bug' ) );
	}

	public function test_build_context_includes_versions() {
		$context = WB_Feedback::build_context();

		$this->assertSame( WB_VERSION, $context['plugin_version'] );
		$this->assertSame( 'stable', $context['update_channel'] );
		$this->assertNotEmpty( $context['site_url'] );
	}
}
