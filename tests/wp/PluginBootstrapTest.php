<?php
/**
 * Plugin bootstrap integration tests.
 *
 * @package Testimonials
 */

final class PluginBootstrapTest extends WP_UnitTestCase {
	public function test_singleton_exposes_services(): void {
		$this->assertInstanceOf( Testimonials_Plugin::class, testimonials() );
		$this->assertInstanceOf( Testimonials_Content_Domain::class, testimonials()->content_domain() );
		$this->assertInstanceOf( Testimonials_Blocks::class, testimonials()->blocks() );
		$this->assertInstanceOf( Testimonials_GitHub_Updater::class, testimonials()->github_updater() );
	}
}
