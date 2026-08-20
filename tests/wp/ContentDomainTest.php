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
		$this->assertSame( 'aprovados', $post_type->rewrite['slug'] );
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
		$this->assertSame( 'aprovados/categoria', $taxonomy->rewrite['slug'] );
	}

	public function test_new_testimonial_slug_is_derived_from_title(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_name'   => '1422',
				'post_status' => 'publish',
				'post_title'  => 'Karine Mariane Lopes Martins',
				'post_type'   => testimonials_post_type(),
			)
		);

		$this->assertSame( 'karine-mariane-lopes-martins', get_post_field( 'post_name', $post_id ) );
	}

	public function test_new_testimonial_title_slugs_remain_unique(): void {
		$first_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Maria Silva',
				'post_type'   => testimonials_post_type(),
			)
		);
		$second_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Maria Silva',
				'post_type'   => testimonials_post_type(),
			)
		);

		$this->assertSame( 'maria-silva', get_post_field( 'post_name', $first_post_id ) );
		$this->assertSame( 'maria-silva-2', get_post_field( 'post_name', $second_post_id ) );
	}

	public function test_existing_numeric_testimonial_slug_is_not_migrated_on_update(): void {
		global $wpdb;

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Existing testimonial',
				'post_type'   => testimonials_post_type(),
			)
		);

		$wpdb->update(
			$wpdb->posts,
			array( 'post_name' => '1422' ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Karine Mariane Lopes Martins',
			)
		);

		$this->assertSame( '1422', get_post_field( 'post_name', $post_id ) );
	}

	public function test_rewrite_rules_are_marked_current_after_path_upgrade(): void {
		delete_option( Testimonials_Content_Domain::REWRITE_RULES_VERSION_OPTION );

		testimonials()->content_domain()->maybe_flush_rewrite_rules();

		$this->assertSame(
			Testimonials_Content_Domain::REWRITE_RULES_VERSION,
			get_option( Testimonials_Content_Domain::REWRITE_RULES_VERSION_OPTION )
		);
	}

	public function test_testimonial_metadata_is_registered(): void {
		testimonials()->content_domain()->register_meta();

		$registered_meta = get_registered_meta_keys( 'post', testimonials_post_type() );

		$expected_meta_keys = array(
			testimonials_video_url_meta_key()     => '_testimonials_video_url',
			testimonials_student_name_meta_key()  => '_testimonials_student_name',
			testimonials_approved_at_meta_key()   => '_testimonials_approved_at',
			testimonials_placement_meta_key()     => '_testimonials_placement',
			testimonials_course_meta_key()            => '_testimonials_course',
			testimonials_institution_meta_key()       => '_testimonials_institution',
			testimonials_approval_year_meta_key()     => '_testimonials_approval_year',
			testimonials_preparation_time_meta_key() => '_testimonials_preparation_time',
			testimonials_main_tip_meta_key()          => '_testimonials_main_tip',
		);

		foreach ( $expected_meta_keys as $meta_key => $expected_meta_key ) {
			$this->assertSame( $expected_meta_key, $meta_key );
			$this->assertArrayHasKey( $meta_key, $registered_meta );
			$this->assertSame( 'string', $registered_meta[ $meta_key ]['type'] );
			$this->assertTrue( $registered_meta[ $meta_key ]['single'] );
			$this->assertTrue( $registered_meta[ $meta_key ]['show_in_rest'] );
		}

		$private_meta_keys = array(
			testimonials_evidence_reference_meta_key()          => '_testimonials_evidence_reference',
			testimonials_verification_status_meta_key()          => '_testimonials_verification_status',
			testimonials_publication_consent_status_meta_key()   => '_testimonials_publication_consent_status',
		);

		foreach ( $private_meta_keys as $meta_key => $expected_meta_key ) {
			$this->assertSame( $expected_meta_key, $meta_key );
			$this->assertArrayHasKey( $meta_key, $registered_meta );
			$this->assertSame( 'string', $registered_meta[ $meta_key ]['type'] );
			$this->assertTrue( $registered_meta[ $meta_key ]['single'] );
			$this->assertFalse( $registered_meta[ $meta_key ]['show_in_rest'] );
		}

		$home_proof_meta_key = testimonials_home_proof_enabled_meta_key();
		$this->assertSame( '_testimonials_home_proof_enabled', $home_proof_meta_key );
		$this->assertArrayHasKey( $home_proof_meta_key, $registered_meta );
		$this->assertSame( 'boolean', $registered_meta[ $home_proof_meta_key ]['type'] );
		$this->assertFalse( $registered_meta[ $home_proof_meta_key ]['show_in_rest'] );

		$featured_story_meta_key = testimonials_featured_story_meta_key();
		$this->assertSame( '_testimonials_featured_story', $featured_story_meta_key );
		$this->assertArrayHasKey( $featured_story_meta_key, $registered_meta );
		$this->assertSame( 'boolean', $registered_meta[ $featured_story_meta_key ]['type'] );
		$this->assertFalse( $registered_meta[ $featured_story_meta_key ]['show_in_rest'] );

		$hero_meta_key = testimonials_hero_enabled_meta_key();
		$this->assertSame( '_testimonials_hero_enabled', $hero_meta_key );
		$this->assertArrayHasKey( $hero_meta_key, $registered_meta );
		$this->assertSame( 'boolean', $registered_meta[ $hero_meta_key ]['type'] );
		$this->assertFalse( $registered_meta[ $hero_meta_key ]['show_in_rest'] );
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
		update_post_meta( $post_id, testimonials_course_meta_key(), 'Medicina' );
		update_post_meta( $post_id, testimonials_institution_meta_key(), 'USP' );
		update_post_meta( $post_id, testimonials_approval_year_meta_key(), '2026' );
		update_post_meta( $post_id, testimonials_preparation_time_meta_key(), '8 meses' );
		update_post_meta( $post_id, testimonials_main_tip_meta_key(), 'Revise cada erro do simulado.' );
		update_post_meta( $post_id, testimonials_evidence_reference_meta_key(), 'CRM-123' );
		update_post_meta( $post_id, testimonials_verification_status_meta_key(), Testimonials_Content_Domain::VERIFICATION_VERIFIED );
		update_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), Testimonials_Content_Domain::CONSENT_CONFIRMED );
		update_post_meta( $post_id, testimonials_home_proof_enabled_meta_key(), true );
		update_post_meta( $post_id, testimonials_featured_story_meta_key(), true );
		update_post_meta( $post_id, testimonials_hero_enabled_meta_key(), true );

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
		$this->assertStringContainsString( 'name="testimonials_course"', $output );
		$this->assertStringContainsString( 'value="Medicina"', $output );
		$this->assertStringContainsString( 'name="testimonials_institution"', $output );
		$this->assertStringContainsString( 'value="USP"', $output );
		$this->assertStringContainsString( 'name="testimonials_approval_year"', $output );
		$this->assertStringContainsString( 'value="2026"', $output );
		$this->assertStringContainsString( 'name="testimonials_preparation_time"', $output );
		$this->assertStringContainsString( 'value="8 meses"', $output );
		$this->assertStringContainsString( 'name="testimonials_main_tip"', $output );
		$this->assertStringContainsString( 'Revise cada erro do simulado.', $output );
		$this->assertStringContainsString( 'name="testimonials_evidence_reference"', $output );
		$this->assertStringContainsString( 'value="CRM-123"', $output );
		$this->assertStringContainsString( 'name="testimonials_verification_status"', $output );
		$this->assertStringContainsString( 'name="testimonials_publication_consent_status"', $output );
		$this->assertStringContainsString( 'name="testimonials_home_proof_enabled"', $output );
		$this->assertStringContainsString( 'name="testimonials_featured_story"', $output );
		$this->assertStringContainsString( 'name="testimonials_hero_enabled"', $output );
		$this->assertStringContainsString( 'checked=', $output );
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
		$_POST['testimonials_course']                             = 'Medicina';
		$_POST['testimonials_institution']                        = 'USP';
		$_POST['testimonials_approval_year']                      = '2026';
		$_POST['testimonials_preparation_time']                   = '8 meses';
		$_POST['testimonials_main_tip']                           = 'Revise cada erro do simulado.';
		$_POST['testimonials_evidence_reference']                 = 'Registro interno 123';
		$_POST['testimonials_verification_status']                = Testimonials_Content_Domain::VERIFICATION_VERIFIED;
		$_POST['testimonials_publication_consent_status']         = Testimonials_Content_Domain::CONSENT_CONFIRMED;
		$_POST['testimonials_home_proof_enabled']                 = '1';
		$_POST['testimonials_featured_story']                     = '1';
		$_POST['testimonials_hero_enabled']                       = '1';

		testimonials()->content_domain()->save_meta_box( $post_id );

		$this->assertSame( 'https://www.youtube.com/watch?v=test123', get_post_meta( $post_id, testimonials_video_url_meta_key(), true ) );
		$this->assertSame( 'Maria Silva', get_post_meta( $post_id, testimonials_student_name_meta_key(), true ) );
		$this->assertSame( 'Medicina USP', get_post_meta( $post_id, testimonials_approved_at_meta_key(), true ) );
		$this->assertSame( '1o lugar', get_post_meta( $post_id, testimonials_placement_meta_key(), true ) );
		$this->assertSame( 'Medicina', get_post_meta( $post_id, testimonials_course_meta_key(), true ) );
		$this->assertSame( 'USP', get_post_meta( $post_id, testimonials_institution_meta_key(), true ) );
		$this->assertSame( '2026', get_post_meta( $post_id, testimonials_approval_year_meta_key(), true ) );
		$this->assertSame( '8 meses', get_post_meta( $post_id, testimonials_preparation_time_meta_key(), true ) );
		$this->assertSame( 'Revise cada erro do simulado.', get_post_meta( $post_id, testimonials_main_tip_meta_key(), true ) );
		$this->assertSame( 'Registro interno 123', get_post_meta( $post_id, testimonials_evidence_reference_meta_key(), true ) );
		$this->assertSame( Testimonials_Content_Domain::VERIFICATION_VERIFIED, get_post_meta( $post_id, testimonials_verification_status_meta_key(), true ) );
		$this->assertSame( Testimonials_Content_Domain::CONSENT_CONFIRMED, get_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), true ) );
		$this->assertSame( '1', get_post_meta( $post_id, testimonials_home_proof_enabled_meta_key(), true ) );
		$this->assertSame( '1', get_post_meta( $post_id, testimonials_featured_story_meta_key(), true ) );
		$this->assertSame( '1', get_post_meta( $post_id, testimonials_hero_enabled_meta_key(), true ) );

		$_POST['testimonials_video_url']    = '';
		$_POST['testimonials_student_name'] = '';
		$_POST['testimonials_approved_at']  = '';
		$_POST['testimonials_placement']    = '';
		$_POST['testimonials_course']           = '';
		$_POST['testimonials_institution']      = '';
		$_POST['testimonials_approval_year']    = '';
		$_POST['testimonials_preparation_time'] = '';
		$_POST['testimonials_main_tip']         = '';
		$_POST['testimonials_evidence_reference'] = '';
		$_POST['testimonials_verification_status'] = 'not-valid';
		$_POST['testimonials_publication_consent_status'] = 'not-valid';
		unset( $_POST['testimonials_home_proof_enabled'] );
		unset( $_POST['testimonials_featured_story'] );
		unset( $_POST['testimonials_hero_enabled'] );

		testimonials()->content_domain()->save_meta_box( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, testimonials_video_url_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_student_name_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_approved_at_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_placement_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_course_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_institution_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_approval_year_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_preparation_time_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_main_tip_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_evidence_reference_meta_key(), true ) );
		$this->assertSame( Testimonials_Content_Domain::VERIFICATION_PENDING, get_post_meta( $post_id, testimonials_verification_status_meta_key(), true ) );
		$this->assertSame( Testimonials_Content_Domain::CONSENT_UNKNOWN, get_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_home_proof_enabled_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_featured_story_meta_key(), true ) );
		$this->assertSame( '', get_post_meta( $post_id, testimonials_hero_enabled_meta_key(), true ) );
	}

	public function test_saving_featured_story_replaces_previous_selection(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$first_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'First featured student',
				'post_type'   => testimonials_post_type(),
			)
		);
		$second_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Second featured student',
				'post_type'   => testimonials_post_type(),
			)
		);

		update_post_meta( $first_post_id, testimonials_featured_story_meta_key(), true );
		$_POST[ Testimonials_Content_Domain::META_BOX_NONCE_NAME ] = wp_create_nonce( Testimonials_Content_Domain::META_BOX_NONCE_ACTION );
		$_POST['testimonials_featured_story'] = '1';

		testimonials()->content_domain()->save_meta_box( $second_post_id );

		$this->assertSame( '', get_post_meta( $first_post_id, testimonials_featured_story_meta_key(), true ) );
		$this->assertSame( '1', get_post_meta( $second_post_id, testimonials_featured_story_meta_key(), true ) );
	}

	public function test_saving_fourth_hero_testimonial_keeps_selection_limited_to_three(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$hero_post_ids = self::factory()->post->create_many(
			Testimonials_Content_Domain::HERO_MAX_TESTIMONIALS,
			array(
				'post_status' => 'publish',
				'post_type'   => testimonials_post_type(),
			)
		);
		$fourth_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => testimonials_post_type(),
			)
		);

		foreach ( $hero_post_ids as $hero_post_id ) {
			update_post_meta( $hero_post_id, testimonials_hero_enabled_meta_key(), true );
		}

		$_POST[ Testimonials_Content_Domain::META_BOX_NONCE_NAME ] = wp_create_nonce( Testimonials_Content_Domain::META_BOX_NONCE_ACTION );
		$_POST['testimonials_hero_enabled'] = '1';

		testimonials()->content_domain()->save_meta_box( $fourth_post_id );

		$selected_hero_ids = get_posts(
			array(
				'fields'         => 'ids',
				'meta_key'       => testimonials_hero_enabled_meta_key(),
				'meta_value'     => '1',
				'post_status'    => 'any',
				'post_type'      => testimonials_post_type(),
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( Testimonials_Content_Domain::HERO_MAX_TESTIMONIALS, $selected_hero_ids );
		$this->assertContains( $fourth_post_id, $selected_hero_ids );
	}

	public function test_home_proof_requires_verified_authorized_complete_published_record(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Verified student',
				'post_status' => 'publish',
				'post_type'   => testimonials_post_type(),
			)
		);
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array( 'post_mime_type' => 'image/jpeg' )
		);
		set_post_thumbnail( $post_id, $attachment_id );

		update_post_meta( $post_id, testimonials_student_name_meta_key(), 'Maria Silva' );
		update_post_meta( $post_id, testimonials_course_meta_key(), 'Medicina' );
		update_post_meta( $post_id, testimonials_institution_meta_key(), 'USP' );
		update_post_meta( $post_id, testimonials_evidence_reference_meta_key(), 'Registro interno 123' );
		update_post_meta( $post_id, testimonials_verification_status_meta_key(), Testimonials_Content_Domain::VERIFICATION_VERIFIED );
		update_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), Testimonials_Content_Domain::CONSENT_CONFIRMED );
		update_post_meta( $post_id, testimonials_home_proof_enabled_meta_key(), true );

		$this->assertTrue( testimonials_is_home_proof_eligible( $post_id ) );

		update_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), Testimonials_Content_Domain::CONSENT_REVOKED );
		$this->assertFalse( testimonials_is_home_proof_eligible( $post_id ) );

		update_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), Testimonials_Content_Domain::CONSENT_CONFIRMED );
		delete_post_meta( $post_id, testimonials_evidence_reference_meta_key() );
		$this->assertFalse( testimonials_is_home_proof_eligible( $post_id ) );
	}

	public function test_featured_story_helper_requires_verified_authorized_complete_record_with_quote(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'A Proenem me ajudou a conquistar minha vaga.',
				'post_status'  => 'publish',
				'post_title'   => 'Featured student',
				'post_type'    => testimonials_post_type(),
			)
		);
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array( 'post_mime_type' => 'image/jpeg' )
		);
		set_post_thumbnail( $post_id, $attachment_id );

		update_post_meta( $post_id, testimonials_student_name_meta_key(), 'Maria Silva' );
		update_post_meta( $post_id, testimonials_course_meta_key(), 'Medicina' );
		update_post_meta( $post_id, testimonials_institution_meta_key(), 'USP' );
		update_post_meta( $post_id, testimonials_evidence_reference_meta_key(), 'Registro interno 123' );
		update_post_meta( $post_id, testimonials_verification_status_meta_key(), Testimonials_Content_Domain::VERIFICATION_VERIFIED );
		update_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), Testimonials_Content_Domain::CONSENT_CONFIRMED );
		update_post_meta( $post_id, testimonials_featured_story_meta_key(), true );
		update_post_meta( $post_id, testimonials_hero_enabled_meta_key(), true );

		$this->assertTrue( testimonials_is_featured_story_eligible( $post_id ) );
		$this->assertSame( $post_id, testimonials_get_featured_story()->ID );
		$this->assertTrue( testimonials_is_hero_eligible( $post_id ) );
		$this->assertSame( array( $post_id ), wp_list_pluck( testimonials_get_hero_testimonials(), 'ID' ) );

		update_post_meta( $post_id, testimonials_publication_consent_status_meta_key(), Testimonials_Content_Domain::CONSENT_REVOKED );

		$this->assertFalse( testimonials_is_featured_story_eligible( $post_id ) );
		$this->assertNull( testimonials_get_featured_story() );
		$this->assertFalse( testimonials_is_hero_eligible( $post_id ) );
		$this->assertSame( array(), testimonials_get_hero_testimonials() );
	}

	public function test_editorial_status_and_year_sanitizers_reject_invalid_values(): void {
		$content_domain = testimonials()->content_domain();

		$this->assertSame( '', $content_domain->sanitize_approval_year( '1899' ) );
		$this->assertSame( '', $content_domain->sanitize_approval_year( 'invalid' ) );
		$this->assertSame( Testimonials_Content_Domain::VERIFICATION_PENDING, $content_domain->sanitize_verification_status( 'invalid' ) );
		$this->assertSame( Testimonials_Content_Domain::CONSENT_UNKNOWN, $content_domain->sanitize_publication_consent_status( 'invalid' ) );
	}

	public function test_main_tip_sanitizer_removes_markup_and_limits_length(): void {
		$content_domain = testimonials()->content_domain();
		$long_tip       = '<strong>' . str_repeat( 'a', Testimonials_Content_Domain::MAIN_TIP_MAX_LENGTH + 20 ) . '</strong>';
		$sanitized_tip  = $content_domain->sanitize_main_tip( $long_tip );

		$this->assertStringNotContainsString( '<strong>', $sanitized_tip );
		$this->assertSame( Testimonials_Content_Domain::MAIN_TIP_MAX_LENGTH, strlen( $sanitized_tip ) );
	}
}
