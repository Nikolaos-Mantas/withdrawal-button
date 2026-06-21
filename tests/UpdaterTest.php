<?php

use PHPUnit\Framework\TestCase;

class WB_UpdaterTest extends TestCase {

	public function test_release_tier_detection() {
		$this->assertSame( 'stable', WB_Updater::release_tier( '3.0.0' ) );
		$this->assertSame( 'beta', WB_Updater::release_tier( '3.1.0-beta.1' ) );
		$this->assertSame( 'alpha', WB_Updater::release_tier( 'v3.2.0-alpha.2' ) );
	}

	public function test_release_matches_stable_channel() {
		$stable = array(
			'tag_name'   => '3.0.0',
			'prerelease' => false,
		);
		$beta = array(
			'tag_name'   => '3.1.0-beta.1',
			'prerelease' => true,
		);

		$this->assertTrue( WB_Updater::release_matches_channel( $stable, 'stable' ) );
		$this->assertFalse( WB_Updater::release_matches_channel( $beta, 'stable' ) );
	}

	public function test_pick_release_for_channel() {
		$releases = array(
			array(
				'tag_name' => '3.0.0',
				'prerelease' => false,
				'assets' => array(
					array( 'name' => 'withdrawal-button.zip', 'browser_download_url' => 'https://example.com/3.0.0.zip' ),
				),
			),
			array(
				'tag_name' => '3.1.0-beta.1',
				'prerelease' => true,
				'assets' => array(
					array( 'name' => 'withdrawal-button.zip', 'browser_download_url' => 'https://example.com/beta.zip' ),
				),
			),
			array(
				'tag_name' => '3.2.0-alpha.1',
				'prerelease' => true,
				'assets' => array(
					array( 'name' => 'withdrawal-button.zip', 'browser_download_url' => 'https://example.com/alpha.zip' ),
				),
			),
		);

		$beta_pick = WB_Updater::pick_release_for_channel( $releases, 'beta' );
		$this->assertSame( '3.1.0-beta.1', $beta_pick['tag_name'] );

		$stable_pick = WB_Updater::pick_release_for_channel( $releases, 'stable' );
		$this->assertSame( '3.0.0', $stable_pick['tag_name'] );
	}

	public function test_get_zip_download_url() {
		$release = array(
			'assets' => array(
				array( 'name' => 'other.zip', 'browser_download_url' => 'https://example.com/other.zip' ),
				array( 'name' => 'withdrawal-button.zip', 'browser_download_url' => 'https://example.com/plugin.zip' ),
			),
		);

		$this->assertSame( 'https://example.com/plugin.zip', WB_Updater::get_zip_download_url( $release ) );
	}
}
