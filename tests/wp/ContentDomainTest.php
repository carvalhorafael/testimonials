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

	public function test_testimonial_metadata_is_registered(): void {
		testimonials()->content_domain()->register_meta();

		$registered_meta = get_registered_meta_keys( 'post', testimonials_post_type() );

		$expected_meta_keys = array(
			testimonials_video_url_meta_key()    => '_testimonials_video_url',
			testimonials_student_name_meta_key() => '_testimonials_student_name',
			testimonials_approved_at_meta_key()  => '_testimonials_approved_at',
			testimonials_placement_meta_key()    => '_testimonials_placement',
		);

		foreach ( $expected_meta_keys as $meta_key => $expected_meta_key ) {
			$this->assertSame( $expected_meta_key, $meta_key );
			$this->assertArrayHasKey( $meta_key, $registered_meta );
			$this->assertSame( 'string', $registered_meta[ $meta_key ]['type'] );
			$this->assertTrue( $registered_meta[ $meta_key ]['single'] );
			$this->assertTrue( $registered_meta[ $meta_key ]['show_in_rest'] );
		}
	}

	public function test_meta_box_renders_testimonial_fields(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Student testimonial',
				'post_status' => 'publish',
				'post_type'   => testimonials_post_type(),
			)
		);

		update_post_meta( $post_id, testimonials_video_url_meta_key(), 'https://www.youtube.com/watch?v=test123' );
		update_post_meta( $post_id, testimonials_student_name_meta_key(), 'Maria Silva' );
		update_post_meta( $post_id, testimonials_approved_at_meta_key(), 'Medicina USP' );
		update_post_meta( $post_id, testimonials_placement_meta_key(), '1o lugar' );

		ob_start();
		testimonials()->content_domain()->render_meta_box( get_post( $post_id ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="testimonials_student_name"', $output );
		$this->assertStringContainsString( 'value="Maria Silva"', $output );
		$this->assertStringContainsString( 'name="testimonials_approved_at"', $output );
		$this->assertStringContainsString( 'value="Medicina USP"', $output );
		$this->assertStringContainsString( 'name="testimonials_placement"', $output );
		$this->assertStringContainsString( 'value="1o lugar"', $output );
		$this->assertStringContainsString( 'name="testimonials_video_url"', $output );
		$this->assertStringContainsString( 'type="url"', $output );
		$this->assertStringContainsString( 'value="https://www.youtube.com/watch?v=test123"', $output );
	}

	public function test_save_meta_box_updates_and_deletes_testimonial_fields(): void {
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
		$_POST['testimonials_student_name']                       = 'Maria Silva';
		$_POST['testimonials_approved_at']                        = 'Medicina USP';
		$_POST['testimonials_placement']                          = '1o lugar';

		testimonials()->content_domain()->save_meta_box( $post_id );

		$this->assertSame( 'https://www.youtube.com/watch?v=test123', get_post_meta( $post_id, testimonials_video_url_meta_key(), true ) );
		$this->assertSame( 'Maria Silva', get_post_meta( $post_id, testimonials_student_name_meta_key(), true ) );
		$this->assertSame( 'Medicina USP', get_post_meta( $post_id, testimonials_approved_at_meta_key(), true ) );
		$this->assertSame( '1o lugar', get_post_meta( $post_id, testimonials_placement_meta_key(), true ) );

		$_POST['testimonials_video_url']    = '';
		$_POST['testimonials_student_name'] = '';
		$_POST['testimonials_approved_at']  = '';
		$_POST['testimonials_placement']    = '';

		testimonials()->content_domain()->save_meta_box( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, testimonials_video_url_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_student_name_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_approved_at_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_placement_meta_key(), true ) );
	}
}
