<?php
/**
 * Bulk testimonial importer integration tests.
 *
 * @package Testimonials
 */

final class ImporterTest extends WP_UnitTestCase {
	public function test_valid_row_is_imported_as_draft_with_metadata_and_category(): void {
		$report = $this->importer_with_successful_media()->import(
			$this->create_csv(
				array(
					$this->row(
						array(
							'id_externo'              => 'planilha-ana-1',
							'nome'                     => 'Ana Silva',
							'depoimento'               => '<p>Minha história de aprovação.</p>',
							'resumo'                   => 'Uma história inspiradora.',
							'onde_passou'              => 'Medicina na Universidade Exemplo',
							'colocacao'                => '1º lugar',
							'curso'                    => 'Medicina',
							'instituicao'              => 'Universidade Exemplo',
							'ano_aprovacao'            => '2026',
							'tempo_preparacao'         => '2 anos',
							'dica_principal'           => 'Mantenha a constância.',
							'video_url'                => 'https://example.com/video',
							'imagem_url'               => 'https://example.com/ana.jpg',
							'categorias'               => 'Aprovados | Medicina',
							'fonte_verificacao'        => 'Lista oficial',
							'status_verificacao'       => 'verified',
							'consentimento_publicacao' => 'confirmed',
							'status_publicacao'        => 'publish',
							'prova_social_home'        => 'sim',
							'historia_destaque'        => 'sim',
							'hero'                      => 'sim',
						)
					),
				)
			),
			'importacao.csv'
		);

		$this->assertIsArray( $report );
		$this->assertSame( 1, $report['summary']['created'] );
		$this->assertSame( 0, $report['summary']['failed'] );

		$post_id = (int) $report['rows'][0]['post_id'];
		$post    = get_post( $post_id );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( 'Ana Silva', $post->post_title );
		$this->assertSame( 'Uma história inspiradora.', $post->post_excerpt );
		$this->assertSame( 'Medicina', get_post_meta( $post_id, Testimonials_Content_Domain::COURSE_META_KEY, true ) );
		$this->assertSame( 'verified', get_post_meta( $post_id, Testimonials_Content_Domain::VERIFICATION_STATUS_META_KEY, true ) );
		$this->assertSame( 'planilha-ana-1', get_post_meta( $post_id, Testimonials_Importer::EXTERNAL_ID_META_KEY, true ) );
		$this->assertSame( 'importacao.csv', get_post_meta( $post_id, Testimonials_Importer::SOURCE_META_KEY, true ) );
		$this->assertSame( '1', get_post_meta( $post_id, Testimonials_Content_Domain::HOME_PROOF_ENABLED_META_KEY, true ) );
		$this->assertSame( '1', get_post_meta( $post_id, Testimonials_Content_Domain::FEATURED_STORY_META_KEY, true ) );
		$this->assertSame( '1', get_post_meta( $post_id, Testimonials_Content_Domain::HERO_ENABLED_META_KEY, true ) );
		$this->assertSame( 'https://example.com/ana.jpg', get_post_meta( $post_id, Testimonials_Importer::IMAGE_URL_META_KEY, true ) );
		$this->assertGreaterThan( 0, get_post_thumbnail_id( $post_id ) );
		$this->assertSame( 1, $report['summary']['media_imported'] );
		$this->assertSame( 'imported', $report['rows'][0]['media_status'] );

		$term_names = wp_get_post_terms( $post_id, Testimonials_Content_Domain::TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertSame( array( 'Aprovados', 'Medicina' ), $term_names );
	}

	public function test_respect_csv_publishes_only_a_valid_publish_row(): void {
		$report = $this->importer_with_successful_media()->import(
			$this->create_csv(
				array(
					$this->row(
						array(
							'id_externo'              => 'publicar-1',
							'nome'                     => 'Pessoa Publicada',
							'depoimento'               => 'Depoimento publicado.',
							'imagem_url'               => 'https://example.com/publicada.jpg',
							'status_verificacao'       => 'verified',
							'consentimento_publicacao' => 'confirmed',
							'status_publicacao'        => 'publish',
						)
					),
				)
			),
			'importacao.csv',
			array( 'publication_mode' => 'respect_csv' )
		);

		$this->assertIsArray( $report );
		$this->assertSame( 'publish', get_post_status( (int) $report['rows'][0]['post_id'] ) );
	}

	public function test_publish_mode_publishes_an_eligible_row_even_when_csv_status_is_draft(): void {
		$report = $this->importer_with_successful_media()->import(
			$this->create_csv(
				array(
					$this->row(
						array(
							'id_externo'              => 'publicacao-automatica-1',
							'nome'                     => 'Pessoa Apta',
							'depoimento'               => 'Depoimento apto para publicação.',
							'imagem_url'               => 'https://example.com/apta.jpg',
							'status_verificacao'       => 'verified',
							'consentimento_publicacao' => 'confirmed',
							'status_publicacao'        => 'draft',
						)
					),
				)
			),
			'importacao.csv',
			array( 'publication_mode' => 'publish' )
		);

		$this->assertIsArray( $report );
		$this->assertSame( 1, $report['summary']['published'] );
		$this->assertSame( 0, $report['summary']['drafts'] );
		$this->assertSame( 'publish', get_post_status( (int) $report['rows'][0]['post_id'] ) );
		$this->assertSame( 'publish', $report['rows'][0]['post_status'] );
	}

	public function test_publish_mode_keeps_a_row_without_image_as_draft(): void {
		$report = $this->importer_with_successful_media()->import(
			$this->create_csv(
				array(
					$this->row(
						array(
							'id_externo'              => 'sem-imagem-publicacao-1',
							'nome'                     => 'Pessoa sem mídia',
							'depoimento'               => 'Depoimento sem mídia.',
							'status_verificacao'       => 'verified',
							'consentimento_publicacao' => 'confirmed',
						)
					),
				)
			),
			'importacao.csv',
			array( 'publication_mode' => 'publish' )
		);

		$this->assertIsArray( $report );
		$this->assertSame( 0, $report['summary']['published'] );
		$this->assertSame( 1, $report['summary']['drafts'] );
		$this->assertSame( 'draft', get_post_status( (int) $report['rows'][0]['post_id'] ) );
		$this->assertStringContainsString( 'imagem destacada', $report['rows'][0]['message'] );
	}

	public function test_repeated_external_id_is_skipped_on_a_later_import(): void {
		$path = $this->create_csv(
			array(
				$this->row(
					array(
						'id_externo' => 'duplicado-externo-1',
						'nome'        => 'Pessoa Única',
						'depoimento'  => 'Depoimento único.',
						'imagem_url'  => 'https://example.com/unica.jpg',
					)
				),
			)
		);

		$importer      = $this->importer_with_successful_media();
		$first_report  = $importer->import( $path, 'primeira.csv' );
		$second_report = $importer->import( $path, 'segunda.csv' );

		$this->assertIsArray( $first_report );
		$this->assertIsArray( $second_report );
		$this->assertSame( 1, $first_report['summary']['created'] );
		$this->assertSame( 0, $second_report['summary']['created'] );
		$this->assertSame( 1, $second_report['summary']['skipped'] );
		$this->assertSame( $first_report['rows'][0]['post_id'], $second_report['rows'][0]['post_id'] );
	}

	public function test_invalid_rows_are_reported_without_creating_posts(): void {
		$report = $this->importer_with_successful_media()->import(
			$this->create_csv(
				array(
					$this->row(
						array(
							'id_externo' => 'invalido-1',
							'nome'        => '',
							'depoimento'  => 'Sem nome.',
						)
					),
				)
			),
			'importacao.csv'
		);

		$this->assertIsArray( $report );
		$this->assertSame( 1, $report['summary']['invalid'] );
		$this->assertSame( 0, $report['summary']['created'] );
		$this->assertSame( 'invalid', $report['rows'][0]['status'] );
		$this->assertSame( 0, $report['rows'][0]['post_id'] );
	}

	public function test_media_failure_keeps_the_post_and_does_not_apply_highlight_selections(): void {
		$importer = new Testimonials_Importer(
			testimonials()->csv_parser(),
			testimonials()->content_domain(),
			static fn(): WP_Error => new WP_Error( 'download_failed', 'Falha simulada.' )
		);
		$report   = $importer->import(
			$this->create_csv(
				array(
					$this->row(
						array(
							'id_externo'              => 'imagem-falha-1',
							'nome'                     => 'Pessoa sem imagem',
							'depoimento'               => 'Depoimento preservado.',
							'imagem_url'               => 'https://example.com/falha.jpg',
							'status_verificacao'       => 'verified',
							'consentimento_publicacao' => 'confirmed',
							'prova_social_home'        => 'sim',
							'historia_destaque'        => 'sim',
							'hero'                      => 'sim',
						)
					),
				)
			),
			'importacao.csv'
		);

		$this->assertIsArray( $report );
		$this->assertSame( 1, $report['summary']['created'] );
		$this->assertSame( 1, $report['summary']['media_failed'] );
		$this->assertSame( 'failed', $report['rows'][0]['media_status'] );
		$this->assertStringContainsString( 'Falha simulada', $report['rows'][0]['message'] );

		$post_id = (int) $report['rows'][0]['post_id'];
		$this->assertSame( '', get_post_meta( $post_id, Testimonials_Content_Domain::HOME_PROOF_ENABLED_META_KEY, true ) );
		$this->assertSame( '', get_post_meta( $post_id, Testimonials_Content_Domain::FEATURED_STORY_META_KEY, true ) );
		$this->assertSame( '', get_post_meta( $post_id, Testimonials_Content_Domain::HERO_ENABLED_META_KEY, true ) );
	}

	private function importer_with_successful_media(): Testimonials_Importer {
		$factory = self::factory();

		return new Testimonials_Importer(
			testimonials()->csv_parser(),
			testimonials()->content_domain(),
			static function ( string $url, int $post_id, string $description ) use ( $factory ): int {
				unset( $url, $description );

				return $factory->attachment->create_object(
					'image.jpg',
					$post_id,
					array( 'post_mime_type' => 'image/jpeg' )
				);
			}
		);
	}

	/**
	 * @param array<string,string> $overrides Row values by canonical header.
	 * @return string[]
	 */
	private function row( array $overrides ): array {
		$values = array_fill_keys( Testimonials_CSV_Parser::headers(), '' );
		$values = array_merge( $values, $overrides );

		return array_values( $values );
	}

	/**
	 * @param array<int,string[]> $rows CSV rows.
	 */
	private function create_csv( array $rows ): string {
		$path   = wp_tempnam( 'testimonials-importer-test.csv' );
		$handle = fopen( $path, 'wb' );

		$this->assertIsResource( $handle );
		fputcsv( $handle, Testimonials_CSV_Parser::headers(), ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ',', '"', '' );
		}
		fclose( $handle );

		return $path;
	}
}
