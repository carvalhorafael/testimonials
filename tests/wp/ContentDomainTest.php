<?php
/**
 * Content domain integration tests.
 *
 * @package Testimonials
 */

final class ContentDomainTest extends WP_UnitTestCase {
	public function test_post_type_is_registered_with_portable_contract(): void {
		$post_type = get_post_type_object( testimonials_post_type() );

		$this->assertNotNull( $post_type );
		$this->assertSame( 'depoimento', testimonials_post_type() );
		$this->assertTrue( $post_type->public );
		$this->assertFalse( $post_type->has_archive );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertSame( 'depoimentos', $post_type->rewrite['slug'] );
		$this->assertTrue( post_type_supports( testimonials_post_type(), 'title' ) );
		$this->assertTrue( post_type_supports( testimonials_post_type(), 'editor' ) );
		$this->assertTrue( post_type_supports( testimonials_post_type(), 'thumbnail' ) );
		$this->assertTrue( post_type_supports( testimonials_post_type(), 'excerpt' ) );
	}

	public function test_taxonomy_is_registered_with_portable_contract(): void {
		$taxonomy = get_taxonomy( testimonials_taxonomy() );

		$this->assertNotFalse( $taxonomy );
		$this->assertSame( 'depoimento_categoria', testimonials_taxonomy() );
		$this->assertTrue( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertContains( testimonials_post_type(), $taxonomy->object_type );
		$this->assertSame( 'depoimentos/categoria', $taxonomy->rewrite['slug'] );
	}

	public function test_video_url_metadata_is_registered(): void {
		testimonials()->content_domain()->register_meta();

		$registered_meta = get_registered_meta_keys( 'post', testimonials_post_type() );

		$this->assertArrayHasKey( '_testimonials_video_url', $registered_meta );
		$this->assertSame( '_testimonials_video_url', testimonials_video_url_meta_key() );
		$this->assertSame( 'string', $registered_meta['_testimonials_video_url']['type'] );
		$this->assertTrue( $registered_meta['_testimonials_video_url']['single'] );
		$this->assertTrue( $registered_meta['_testimonials_video_url']['show_in_rest'] );
	}

	public function test_meta_box_renders_video_url_field(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Student testimonial',
				'post_status' => 'publish',
				'post_type'   => testimonials_post_type(),
			)
		);

		update_post_meta( $post_id, testimonials_video_url_meta_key(), 'https://www.youtube.com/watch?v=test123' );

		ob_start();
		testimonials()->content_domain()->render_meta_box( get_post( $post_id ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="testimonials_video_url"', $output );
		$this->assertStringContainsString( 'type="url"', $output );
		$this->assertStringContainsString( 'value="https://www.youtube.com/watch?v=test123"', $output );
	}

	public function test_save_meta_box_updates_and_deletes_video_url(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Video testimonial',
				'post_status' => 'publish',
				'post_type'   => testimonials_post_type(),
			)
		);

		$_POST[ Testimonials_Content_Domain::META_BOX_NONCE_NAME ] = wp_create_nonce( Testimonials_Content_Domain::META_BOX_NONCE_ACTION );
		$_POST['testimonials_video_url']                          = 'https://www.youtube.com/watch?v=test123';

		testimonials()->content_domain()->save_meta_box( $post_id );

		$this->assertSame( 'https://www.youtube.com/watch?v=test123', get_post_meta( $post_id, testimonials_video_url_meta_key(), true ) );

		$_POST['testimonials_video_url'] = '';

		testimonials()->content_domain()->save_meta_box( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, testimonials_video_url_meta_key(), true ) );
	}
}
