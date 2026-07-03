<?php
/**
 * Testimonials content domain.
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Testimonials_Content_Domain {
	public const POST_TYPE         = 'depoimento';
	public const TAXONOMY          = 'depoimento_categoria';
	public const TESTIMONIALS_PATH = 'depoimentos';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_content_types' ) );
	}

	public function register_content_types(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'has_archive'        => false,
				'hierarchical'       => false,
				'labels'             => $this->post_type_labels(),
				'menu_icon'          => 'dashicons-format-quote',
				'public'             => true,
				'publicly_queryable' => true,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => self::TESTIMONIALS_PATH,
					'with_front' => false,
				),
				'show_in_rest'       => true,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			array( self::POST_TYPE ),
			array(
				'hierarchical'      => true,
				'labels'            => $this->taxonomy_labels(),
				'public'            => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => self::TESTIMONIALS_PATH . '/categoria',
					'with_front' => false,
				),
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'show_ui'           => true,
			)
		);

		add_rewrite_rule(
			'^' . self::TESTIMONIALS_PATH . '/categoria/([^/]+)/?$',
			'index.php?' . self::TAXONOMY . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function post_type_labels(): array {
		return array(
			'name'                  => _x( 'Testimonials', 'Post type general name', 'testimonials' ),
			'singular_name'         => _x( 'Testimonial', 'Post type singular name', 'testimonials' ),
			'menu_name'             => _x( 'Testimonials', 'Admin menu text', 'testimonials' ),
			'name_admin_bar'        => _x( 'Testimonial', 'Add new on toolbar', 'testimonials' ),
			'add_new'               => __( 'Add new', 'testimonials' ),
			'add_new_item'          => __( 'Add testimonial', 'testimonials' ),
			'all_items'             => __( 'All testimonials', 'testimonials' ),
			'archives'              => __( 'Testimonials', 'testimonials' ),
			'edit_item'             => __( 'Edit testimonial', 'testimonials' ),
			'featured_image'        => __( 'Testimonial image', 'testimonials' ),
			'filter_items_list'     => __( 'Filter testimonials', 'testimonials' ),
			'items_list'            => __( 'Testimonials list', 'testimonials' ),
			'items_list_navigation' => __( 'Testimonials list navigation', 'testimonials' ),
			'new_item'              => __( 'New testimonial', 'testimonials' ),
			'not_found'             => __( 'No testimonials found.', 'testimonials' ),
			'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'testimonials' ),
			'remove_featured_image' => __( 'Remove testimonial image', 'testimonials' ),
			'search_items'          => __( 'Search testimonials', 'testimonials' ),
			'set_featured_image'    => __( 'Set testimonial image', 'testimonials' ),
			'uploaded_to_this_item' => __( 'Uploaded to this testimonial', 'testimonials' ),
			'use_featured_image'    => __( 'Use as testimonial image', 'testimonials' ),
			'view_item'             => __( 'View testimonial', 'testimonials' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function taxonomy_labels(): array {
		return array(
			'name'              => _x( 'Testimonial categories', 'taxonomy general name', 'testimonials' ),
			'singular_name'     => _x( 'Testimonial category', 'taxonomy singular name', 'testimonials' ),
			'add_new_item'      => __( 'Add testimonial category', 'testimonials' ),
			'all_items'         => __( 'All categories', 'testimonials' ),
			'back_to_items'     => __( 'Back to categories', 'testimonials' ),
			'edit_item'         => __( 'Edit category', 'testimonials' ),
			'menu_name'         => __( 'Categories', 'testimonials' ),
			'new_item_name'     => __( 'New category name', 'testimonials' ),
			'not_found'         => __( 'No categories found.', 'testimonials' ),
			'parent_item'       => __( 'Parent category', 'testimonials' ),
			'parent_item_colon' => __( 'Parent category:', 'testimonials' ),
			'search_items'      => __( 'Search categories', 'testimonials' ),
			'update_item'       => __( 'Update category', 'testimonials' ),
			'view_item'         => __( 'View category', 'testimonials' ),
		);
	}
}
