<?php
/**
 * Plugin Name: Testimonials
 * Description: Registers the reusable Testimonials content domain for WordPress sites.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Rafael Carvalho
 * Plugin URI: https://github.com/carvalhorafael/testimonials
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/carvalhorafael/testimonials
 * Text Domain: testimonials
 * Domain Path: /languages
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TESTIMONIALS_VERSION', '0.1.0' );
define( 'TESTIMONIALS_FILE', __FILE__ );
define( 'TESTIMONIALS_DIR', plugin_dir_path( __FILE__ ) );
define( 'TESTIMONIALS_BASENAME', plugin_basename( __FILE__ ) );

require_once TESTIMONIALS_DIR . 'includes/class-content-domain.php';
require_once TESTIMONIALS_DIR . 'includes/class-github-updater.php';
require_once TESTIMONIALS_DIR . 'includes/class-plugin.php';

/**
 * Returns the plugin singleton.
 */
function testimonials(): Testimonials_Plugin {
	return Testimonials_Plugin::instance();
}

/**
 * Returns the canonical testimonial post type.
 */
function testimonials_post_type(): string {
	return Testimonials_Content_Domain::POST_TYPE;
}

/**
 * Returns the canonical testimonial taxonomy.
 */
function testimonials_taxonomy(): string {
	return Testimonials_Content_Domain::TAXONOMY;
}

/**
 * Returns the canonical testimonial video URL meta key.
 */
function testimonials_video_url_meta_key(): string {
	return Testimonials_Content_Domain::VIDEO_URL_META_KEY;
}

register_activation_hook( __FILE__, array( 'Testimonials_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Testimonials_Plugin', 'deactivate' ) );

testimonials()->boot();
