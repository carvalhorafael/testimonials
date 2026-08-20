<?php
/**
 * Main plugin bootstrap.
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Testimonials_Plugin {
	private static ?Testimonials_Plugin $instance = null;

	private bool $booted = false;

	private Testimonials_Content_Domain $content_domain;

	private Testimonials_Blocks $blocks;

	private Testimonials_CSV_Parser $csv_parser;

	private Testimonials_Importer $importer;

	private Testimonials_Import_Admin_Page $import_admin_page;

	private Testimonials_GitHub_Updater $github_updater;

	private function __construct() {
		$this->content_domain    = new Testimonials_Content_Domain();
		$this->blocks            = new Testimonials_Blocks();
		$this->csv_parser        = new Testimonials_CSV_Parser();
		$this->importer          = new Testimonials_Importer( $this->csv_parser, $this->content_domain );
		$this->import_admin_page = new Testimonials_Import_Admin_Page( $this->csv_parser, $this->importer );
		$this->github_updater    = new Testimonials_GitHub_Updater( TESTIMONIALS_FILE, TESTIMONIALS_VERSION );
	}

	public static function instance(): Testimonials_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->content_domain->register_hooks();
		$this->blocks->register_hooks();
		$this->importer->register_hooks();
		$this->import_admin_page->register_hooks();
		$this->github_updater->register_hooks();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'testimonials',
			false,
			dirname( TESTIMONIALS_BASENAME ) . '/languages'
		);
	}

	public function content_domain(): Testimonials_Content_Domain {
		return $this->content_domain;
	}

	public function blocks(): Testimonials_Blocks {
		return $this->blocks;
	}

	public function csv_parser(): Testimonials_CSV_Parser {
		return $this->csv_parser;
	}

	public function importer(): Testimonials_Importer {
		return $this->importer;
	}

	public function import_admin_page(): Testimonials_Import_Admin_Page {
		return $this->import_admin_page;
	}

	public function github_updater(): Testimonials_GitHub_Updater {
		return $this->github_updater;
	}

	public static function activate(): void {
		$domain = new Testimonials_Content_Domain();
		$domain->register_content_types();
		flush_rewrite_rules();
		update_option(
			Testimonials_Content_Domain::REWRITE_RULES_VERSION_OPTION,
			Testimonials_Content_Domain::REWRITE_RULES_VERSION,
			false
		);
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
