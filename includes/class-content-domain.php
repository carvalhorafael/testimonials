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
	public const POST_TYPE             = 'depoimento';
	public const TAXONOMY              = 'depoimento_categoria';
	public const TESTIMONIALS_PATH     = 'depoimentos';
	public const VIDEO_URL_META_KEY    = '_testimonials_video_url';
	public const META_BOX_ID           = 'testimonials-video';
	public const META_BOX_NONCE_ACTION = 'testimonials_save_video_settings';
	public const META_BOX_NONCE_NAME   = 'testimonials_video_nonce';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_content_types' ) );
		add_action( 'init', array( $this, 'register_meta' ), 11 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ) );
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

	public function register_meta(): void {
		register_post_meta(
			self::POST_TYPE,
			self::VIDEO_URL_META_KEY,
			array(
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'esc_url_raw',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			)
		);
	}

	public function register_meta_box(): void {
		add_meta_box(
			self::META_BOX_ID,
			__( 'Testimonial video', 'testimonials' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	public function render_meta_box( WP_Post $post ): void {
		$video_url = get_post_meta( $post->ID, self::VIDEO_URL_META_KEY, true );

		wp_nonce_field( self::META_BOX_NONCE_ACTION, self::META_BOX_NONCE_NAME );
		?>
		<p>
			<label for="testimonials-video-url"><?php esc_html_e( 'Video URL', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-video-url"
				name="testimonials_video_url"
				type="url"
				value="<?php echo esc_attr( $video_url ); ?>"
				placeholder="https://www.youtube.com/watch?v=..."
			>
		</p>
		<?php
	}

	public function save_meta_box( int $post_id ): void {
		$nonce = isset( $_POST[ self::META_BOX_NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_BOX_NONCE_NAME ] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::META_BOX_NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$video_url = isset( $_POST['testimonials_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['testimonials_video_url'] ) ) : '';

		if ( '' === $video_url ) {
			delete_post_meta( $post_id, self::VIDEO_URL_META_KEY );
			return;
		}

		update_post_meta( $post_id, self::VIDEO_URL_META_KEY, $video_url );
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
