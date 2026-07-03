<?php
/**
 * GitHub updater unit tests.
 *
 * @package Testimonials
 */

use PHPUnit\Framework\TestCase;

final class GitHubUpdaterTest extends TestCase {
	public function test_latest_release_normalizes_expected_package_asset(): void {
		$updater = new Testimonials_GitHub_Updater(
			'/tmp/testimonials/testimonials.php',
			'0.1.0',
			static function (): array {
				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => json_encode(
						array(
							'tag_name'     => 'v0.2.0',
							'html_url'     => 'https://github.com/carvalhorafael/testimonials/releases/tag/v0.2.0',
							'published_at' => '2026-06-06T00:00:00Z',
							'body'         => 'Release notes.',
							'assets'       => array(
								array(
									'name'                 => 'testimonials-0.2.0.zip',
									'browser_download_url' => 'https://github.com/carvalhorafael/testimonials/releases/download/v0.2.0/testimonials-0.2.0.zip',
								),
							),
						)
					),
				);
			}
		);

		$release = $updater->latest_release();

		$this->assertIsArray( $release );
		$this->assertSame( '0.2.0', $release['version'] );
		$this->assertSame( 'https://github.com/carvalhorafael/testimonials/releases/download/v0.2.0/testimonials-0.2.0.zip', $release['package_url'] );
	}
}
