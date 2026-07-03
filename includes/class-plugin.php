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

	private Testimonials_GitHub_Updater $github_updater;

	private function __construct() {
		$this->content_domain = new Testimonials_Content_Domain();
		$this->github_updater = new Testimonials_GitHub_Updater( TESTIMONIALS_FILE, TESTIMONIALS_VERSION );
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

	public function github_updater(): Testimonials_GitHub_Updater {
		return $this->github_updater;
	}

	public static function activate(): void {
		$domain = new Testimonials_Content_Domain();
		$domain->register_content_types();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
