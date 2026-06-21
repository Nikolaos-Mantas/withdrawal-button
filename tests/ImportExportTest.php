<?php

use PHPUnit\Framework\TestCase;

class ImportExportTest extends TestCase {

	public function test_import_settings_reads_export_payload() {
		wb_test_reset();

		$tmp = sys_get_temp_dir() . '/wb-settings-import-test.json';
		$payload = array(
			'plugin' => WB_PLUGIN_SLUG,
			'type'   => 'settings',
			'data'   => array(
				'admin_email' => 'imported@example.com',
			),
		);
		file_put_contents( $tmp, json_encode( $payload ) );

		$result = WB_Import_Export::import_settings( $tmp, true );

		$this->assertTrue( $result['success'] );
		$settings = WB_Settings::get();
		$this->assertSame( 'imported@example.com', $settings['admin_email'] );

		unlink( $tmp );
	}

	public function test_import_settings_rejects_invalid_json() {
		$tmp = sys_get_temp_dir() . '/wb-settings-bad.json';
		file_put_contents( $tmp, 'not json' );

		$result = WB_Import_Export::import_settings( $tmp );

		$this->assertFalse( $result['success'] );

		unlink( $tmp );
	}
}
