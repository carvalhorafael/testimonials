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
}
