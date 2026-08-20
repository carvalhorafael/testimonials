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
		$this->assertInstanceOf( Testimonials_CSV_Parser::class, testimonials()->csv_parser() );
		$this->assertInstanceOf( Testimonials_Importer::class, testimonials()->importer() );
		$this->assertInstanceOf( Testimonials_Import_Admin_Page::class, testimonials()->import_admin_page() );
		$this->assertInstanceOf( Testimonials_GitHub_Updater::class, testimonials()->github_updater() );
	}
}
