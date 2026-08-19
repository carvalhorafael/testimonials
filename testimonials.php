<?php
/**
 * Plugin Name: Testimonials
 * Description: Registers the reusable Testimonials content domain for WordPress sites.
 * Version: 0.5.2
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

define( 'TESTIMONIALS_VERSION', '0.5.2' );
define( 'TESTIMONIALS_FILE', __FILE__ );
define( 'TESTIMONIALS_DIR', plugin_dir_path( __FILE__ ) );
define( 'TESTIMONIALS_BASENAME', plugin_basename( __FILE__ ) );

require_once TESTIMONIALS_DIR . 'includes/class-content-domain.php';
require_once TESTIMONIALS_DIR . 'includes/class-blocks.php';
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

/**
 * Returns the canonical testimonial student name meta key.
 */
function testimonials_student_name_meta_key(): string {
	return Testimonials_Content_Domain::STUDENT_NAME_META_KEY;
}

/**
 * Returns the canonical testimonial approved at meta key.
 */
function testimonials_approved_at_meta_key(): string {
	return Testimonials_Content_Domain::APPROVED_AT_META_KEY;
}

/**
 * Returns the canonical testimonial placement meta key.
 */
function testimonials_placement_meta_key(): string {
	return Testimonials_Content_Domain::PLACEMENT_META_KEY;
}

/**
 * Returns the canonical testimonial course meta key.
 */
function testimonials_course_meta_key(): string {
	return Testimonials_Content_Domain::COURSE_META_KEY;
}

/**
 * Returns the canonical testimonial institution meta key.
 */
function testimonials_institution_meta_key(): string {
	return Testimonials_Content_Domain::INSTITUTION_META_KEY;
}

/**
 * Returns the canonical testimonial approval year meta key.
 */
function testimonials_approval_year_meta_key(): string {
	return Testimonials_Content_Domain::APPROVAL_YEAR_META_KEY;
}

/**
 * Returns the canonical testimonial preparation time meta key.
 */
function testimonials_preparation_time_meta_key(): string {
	return Testimonials_Content_Domain::PREPARATION_TIME_META_KEY;
}

/**
 * Returns the canonical testimonial main tip meta key.
 */
function testimonials_main_tip_meta_key(): string {
	return Testimonials_Content_Domain::MAIN_TIP_META_KEY;
}

/**
 * Returns the internal testimonial evidence reference meta key.
 */
function testimonials_evidence_reference_meta_key(): string {
	return Testimonials_Content_Domain::EVIDENCE_REFERENCE_META_KEY;
}

/**
 * Returns the internal testimonial verification status meta key.
 */
function testimonials_verification_status_meta_key(): string {
	return Testimonials_Content_Domain::VERIFICATION_STATUS_META_KEY;
}

/**
 * Returns the internal testimonial publication consent status meta key.
 */
function testimonials_publication_consent_status_meta_key(): string {
	return Testimonials_Content_Domain::PUBLICATION_CONSENT_STATUS_META_KEY;
}

/**
 * Returns the internal testimonial home proof selection meta key.
 */
function testimonials_home_proof_enabled_meta_key(): string {
	return Testimonials_Content_Domain::HOME_PROOF_ENABLED_META_KEY;
}

/**
 * Determines whether a testimonial can be used as verifiable home proof.
 */
function testimonials_is_home_proof_eligible( int $post_id ): bool {
	return testimonials()->content_domain()->is_home_proof_eligible( $post_id );
}

register_activation_hook( __FILE__, array( 'Testimonials_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Testimonials_Plugin', 'deactivate' ) );

testimonials()->boot();
