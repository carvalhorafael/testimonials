<?php
/**
 * Gutenberg blocks.
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Testimonials_Blocks {
	public const TESTIMONIALS_DISPLAY_BLOCK = 'testimonials/testimonials-display';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function register_blocks(): void {
		$block_dir     = TESTIMONIALS_DIR . 'blocks/testimonials-display';
		$editor_script = 'testimonials-testimonials-display-editor';
		$editor_asset  = $block_dir . '/editor.js';

		wp_register_script(
			$editor_script,
			plugins_url( 'blocks/testimonials-display/editor.js', TESTIMONIALS_FILE ),
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			file_exists( $editor_asset ) ? (string) filemtime( $editor_asset ) : TESTIMONIALS_VERSION,
			true
		);

		register_block_type(
			$block_dir,
			array(
				'editor_script'   => $editor_script,
				'render_callback' => array( $this, 'render_testimonials_display' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_testimonials_display( array $attributes = array() ): string {
		$attributes = $this->normalize_attributes( $attributes );
		$posts      = $this->query_testimonials( $attributes );
		$classes    = array(
			'testimonials-block',
			'testimonials-block--' . sanitize_html_class( $attributes['layout'] ),
		);

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => implode( ' ', $classes ),
			)
		);

		if ( array() === $posts ) {
			return sprintf(
				'<div %1$s><p class="testimonials-block__empty">%2$s</p></div>',
				$wrapper_attributes,
				esc_html__( 'No testimonials found.', 'testimonials' )
			);
		}

		$items = array_map(
			function ( WP_Post $post ) use ( $attributes ): string {
				return $this->render_testimonial_card( $post, $attributes );
			},
			$posts
		);

		return sprintf(
			'<div %1$s><div class="testimonials-block__items">%2$s</div></div>',
			$wrapper_attributes,
			implode( '', $items )
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	private function normalize_attributes( array $attributes ): array {
		$layouts         = array( 'cards', 'grid', 'slider', 'video-slider', 'featured' );
		$selection_modes = array( 'latest', 'manual', 'category' );
		$orders          = array( 'asc', 'desc' );
		$orderbys        = array( 'date', 'menu_order', 'rand' );

		$layout         = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'cards';
		$selection_mode = isset( $attributes['selectionMode'] ) ? sanitize_key( (string) $attributes['selectionMode'] ) : 'latest';
		$order          = isset( $attributes['order'] ) ? strtolower( sanitize_key( (string) $attributes['order'] ) ) : 'desc';
		$orderby        = isset( $attributes['orderby'] ) ? sanitize_key( (string) $attributes['orderby'] ) : 'date';

		return array(
			'layout'         => in_array( $layout, $layouts, true ) ? $layout : 'cards',
			'selectionMode'  => in_array( $selection_mode, $selection_modes, true ) ? $selection_mode : 'latest',
			'count'          => isset( $attributes['count'] ) ? max( 1, absint( $attributes['count'] ) ) : 3,
			'testimonialIds' => $this->normalize_ids( $attributes['testimonialIds'] ?? array() ),
			'categoryIds'    => $this->normalize_ids( $attributes['categoryIds'] ?? array() ),
			'order'          => in_array( $order, $orders, true ) ? $order : 'desc',
			'orderby'        => in_array( $orderby, $orderbys, true ) ? $orderby : 'date',
			'showVideo'      => ! empty( $attributes['showVideo'] ),
			'showExcerpt'    => array_key_exists( 'showExcerpt', $attributes ) ? (bool) $attributes['showExcerpt'] : true,
			'showCategory'   => array_key_exists( 'showCategory', $attributes ) ? (bool) $attributes['showCategory'] : true,
		);
	}

	/**
	 * @param mixed $ids Raw IDs.
	 * @return int[]
	 */
	private function normalize_ids( mixed $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$ids = array_map( 'absint', $ids );
		$ids = array_filter(
			$ids,
			static function ( int $id ): bool {
				return $id > 0;
			}
		);

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return WP_Post[]
	 */
	private function query_testimonials( array $attributes ): array {
		$query_args = array(
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'post_status'         => 'publish',
			'post_type'           => testimonials_post_type(),
			'posts_per_page'      => (int) $attributes['count'],
			'order'               => strtoupper( (string) $attributes['order'] ),
			'orderby'             => $attributes['orderby'],
		);

		if ( 'manual' === $attributes['selectionMode'] ) {
			if ( array() === $attributes['testimonialIds'] ) {
				return array();
			}

			$query_args['post__in']       = $attributes['testimonialIds'];
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = min( (int) $attributes['count'], count( $attributes['testimonialIds'] ) );
		}

		if ( 'category' === $attributes['selectionMode'] ) {
			if ( array() === $attributes['categoryIds'] ) {
				return array();
			}

			$query_args['tax_query'] = array(
				array(
					'field'    => 'term_id',
					'taxonomy' => testimonials_taxonomy(),
					'terms'    => $attributes['categoryIds'],
				),
			);
		}

		if ( 'video-slider' === $attributes['layout'] && 'manual' !== $attributes['selectionMode'] ) {
			$query_args['posts_per_page'] = max( (int) $attributes['count'], (int) $attributes['count'] * 4 );
		}

		$query = new WP_Query( $query_args );
		$posts = $query->posts;

		if ( 'video-slider' === $attributes['layout'] && 'manual' !== $attributes['selectionMode'] ) {
			usort(
				$posts,
				function ( WP_Post $first, WP_Post $second ): int {
					return (int) $this->has_video( $second ) <=> (int) $this->has_video( $first );
				}
			);

			$posts = array_slice( $posts, 0, (int) $attributes['count'] );
		}

		return $posts;
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render_testimonial_card( WP_Post $post, array $attributes ): string {
		$video_url = (string) get_post_meta( $post->ID, testimonials_video_url_meta_key(), true );
		$media     = $this->render_media( $post, $video_url, $attributes );
		$category  = $attributes['showCategory'] ? $this->render_category( $post ) : '';
		$quote     = $attributes['showExcerpt'] ? $this->get_quote( $post ) : '';

		return sprintf(
			'<article class="testimonials-card">%1$s%2$s%3$s<h3 class="testimonials-card__person">%4$s</h3></article>',
			$media,
			'' !== $quote ? sprintf( '<div class="testimonials-card__quote">%s</div>', wp_kses_post( wpautop( $quote ) ) ) : '',
			$category,
			esc_html( get_the_title( $post ) )
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render_media( WP_Post $post, string $video_url, array $attributes ): string {
		if ( $attributes['showVideo'] && '' !== $video_url ) {
			return sprintf(
				'<div class="testimonials-card__media"><a class="testimonials-card__video" href="%1$s">%2$s</a></div>',
				esc_url( $video_url ),
				esc_html__( 'Watch video', 'testimonials' )
			);
		}

		if ( has_post_thumbnail( $post ) ) {
			return sprintf(
				'<div class="testimonials-card__media">%s</div>',
				get_the_post_thumbnail(
					$post,
					'medium',
					array(
						'class' => 'testimonials-card__image',
					)
				)
			);
		}

		return '';
	}

	private function render_category( WP_Post $post ): string {
		$terms = get_the_terms( $post, testimonials_taxonomy() );

		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}

		$term = reset( $terms );

		return sprintf(
			'<div class="testimonials-card__category">%s</div>',
			esc_html( $term->name )
		);
	}

	private function get_quote( WP_Post $post ): string {
		if ( has_excerpt( $post ) ) {
			return get_the_excerpt( $post );
		}

		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
	}

	private function has_video( WP_Post $post ): bool {
		return '' !== (string) get_post_meta( $post->ID, testimonials_video_url_meta_key(), true );
	}
}
