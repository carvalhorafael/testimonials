<?php
/**
 * Testimonials display block integration tests.
 *
 * @package Testimonials
 */

final class TestimonialsDisplayBlockTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( Testimonials_Blocks::TESTIMONIALS_DISPLAY_BLOCK ) ) {
			testimonials()->blocks()->register_blocks();
		}
	}

	public function test_block_is_registered(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( Testimonials_Blocks::TESTIMONIALS_DISPLAY_BLOCK ) );
	}

	public function test_block_attributes_define_expected_defaults(): void {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( Testimonials_Blocks::TESTIMONIALS_DISPLAY_BLOCK );

		$this->assertSame( 'cards', $block_type->attributes['layout']['default'] );
		$this->assertSame( 'latest', $block_type->attributes['selectionMode']['default'] );
		$this->assertSame( 3, $block_type->attributes['count']['default'] );
		$this->assertSame( array(), $block_type->attributes['testimonialIds']['default'] );
		$this->assertSame( array(), $block_type->attributes['categoryIds']['default'] );
		$this->assertSame( 'desc', $block_type->attributes['order']['default'] );
		$this->assertSame( 'date', $block_type->attributes['orderby']['default'] );
		$this->assertFalse( $block_type->attributes['showVideo']['default'] );
		$this->assertTrue( $block_type->attributes['showExcerpt']['default'] );
		$this->assertTrue( $block_type->attributes['showCategory']['default'] );
	}

	public function test_render_latest_testimonials(): void {
		$this->create_testimonial( 'First testimonial', 'First quote', '2026-01-01 00:00:00' );
		$this->create_testimonial( 'Second testimonial', 'Second quote', '2026-01-02 00:00:00' );

		$output = $this->render_block( array( 'count' => 2 ) );

		$this->assertStringContainsString( 'testimonials-block testimonials-block--cards', $output );
		$this->assertStringContainsString( 'testimonials-card', $output );
		$this->assertStringContainsString( 'Second testimonial', $output );
		$this->assertStringContainsString( 'First testimonial', $output );
		$this->assertLessThan( strpos( $output, 'First testimonial' ), strpos( $output, 'Second testimonial' ) );
	}

	public function test_render_filtered_by_category(): void {
		$category_id = self::factory()->term->create(
			array(
				'name'     => 'Approved',
				'taxonomy' => testimonials_taxonomy(),
			)
		);

		$included_id = $this->create_testimonial( 'Included testimonial' );
		$this->create_testimonial( 'Excluded testimonial' );
		wp_set_object_terms( $included_id, array( $category_id ), testimonials_taxonomy() );

		$output = $this->render_block(
			array(
				'selectionMode' => 'category',
				'categoryIds'   => array( $category_id ),
			)
		);

		$this->assertStringContainsString( 'Included testimonial', $output );
		$this->assertStringContainsString( 'Approved', $output );
		$this->assertStringNotContainsString( 'Excluded testimonial', $output );
	}

	public function test_render_manual_selection_preserves_selected_order(): void {
		$first_id  = $this->create_testimonial( 'First selected' );
		$second_id = $this->create_testimonial( 'Second selected' );
		$this->create_testimonial( 'Not selected' );

		$output = $this->render_block(
			array(
				'selectionMode'  => 'manual',
				'testimonialIds' => array( $second_id, $first_id ),
				'count'          => 2,
			)
		);

		$this->assertStringContainsString( 'Second selected', $output );
		$this->assertStringContainsString( 'First selected', $output );
		$this->assertStringNotContainsString( 'Not selected', $output );
		$this->assertLessThan( strpos( $output, 'First selected' ), strpos( $output, 'Second selected' ) );
	}

	public function test_video_slider_prioritizes_testimonials_with_video(): void {
		$this->create_testimonial( 'Plain testimonial', '', '2026-01-02 00:00:00' );
		$video_id = $this->create_testimonial( 'Video testimonial', '', '2026-01-01 00:00:00' );

		update_post_meta( $video_id, testimonials_video_url_meta_key(), 'https://example.com/video' );

		$output = $this->render_block(
			array(
				'layout'    => 'video-slider',
				'count'     => 1,
				'showVideo' => true,
			)
		);

		$this->assertStringContainsString( 'testimonials-block--video-slider', $output );
		$this->assertStringContainsString( 'Video testimonial', $output );
		$this->assertStringContainsString( 'testimonials-card__video', $output );
		$this->assertStringContainsString( 'https://example.com/video', $output );
		$this->assertStringNotContainsString( 'Plain testimonial', $output );
	}

	public function test_render_fallback_when_no_testimonials_exist(): void {
		$output = $this->render_block();

		$this->assertStringContainsString( 'testimonials-block__empty', $output );
		$this->assertStringContainsString( 'No testimonials found.', $output );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render_block( array $attributes = array() ): string {
		return render_block(
			array(
				'blockName'    => Testimonials_Blocks::TESTIMONIALS_DISPLAY_BLOCK,
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	private function create_testimonial( string $title, string $excerpt = '', string $date = '2026-01-01 00:00:00' ): int {
		return self::factory()->post->create(
			array(
				'post_content' => $excerpt,
				'post_date'    => $date,
				'post_excerpt' => $excerpt,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_type'    => testimonials_post_type(),
			)
		);
	}
}
