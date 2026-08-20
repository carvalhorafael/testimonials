<?php
/**
 * CSV parser integration tests.
 *
 * @package Testimonials
 */

final class CSVParserTest extends WP_UnitTestCase {
	public function test_valid_row_is_summarized_and_previewed(): void {
		$path = $this->create_csv(
			array(
				$this->row(
					array(
						'id_externo'              => 'source-1',
						'nome'                     => 'Ana Silva',
						'depoimento'               => 'Minha história.',
						'curso'                    => 'Medicina',
						'instituicao'              => 'Universidade Exemplo',
						'imagem_url'               => 'https://example.com/ana.jpg',
						'fonte_verificacao'        => 'CRM-123',
						'status_verificacao'       => 'verified',
						'consentimento_publicacao' => 'confirmed',
						'status_publicacao'        => 'draft',
					)
				),
			)
		);

		$analysis = testimonials()->csv_parser()->analyze( $path );

		$this->assertIsArray( $analysis );
		$this->assertSame( 1, $analysis['summary']['total'] );
		$this->assertSame( 1, $analysis['summary']['valid'] );
		$this->assertSame( 0, $analysis['summary']['invalid'] );
		$this->assertSame( 'Ana Silva', $analysis['rows'][0]['data']['nome'] );
		$this->assertSame( array(), $analysis['rows'][0]['errors'] );
	}

	public function test_missing_image_is_reported_as_warning(): void {
		$path = $this->create_csv(
			array(
				$this->row(
					array(
						'id_externo' => 'source-2',
						'nome'        => 'Bruno Souza',
						'depoimento'  => 'Meu depoimento.',
					)
				),
			)
		);

		$analysis = testimonials()->csv_parser()->analyze( $path );

		$this->assertIsArray( $analysis );
		$this->assertSame( 1, $analysis['summary']['valid'] );
		$this->assertSame( 1, $analysis['summary']['with_warning'] );
		$this->assertNotEmpty( $analysis['rows'][0]['warnings'] );
	}

	public function test_utf8_bom_before_quoted_header_is_supported(): void {
		$path   = $this->create_csv(
			array(
				$this->row(
					array(
						'id_externo' => 'bom-1',
						'nome'        => 'Pessoa UTF-8',
						'depoimento'  => 'Depoimento com BOM.',
					)
				),
			)
		);
		$content = file_get_contents( $path );

		$this->assertIsString( $content );
		file_put_contents( $path, "\xEF\xBB\xBF" . $content );

		$analysis = testimonials()->csv_parser()->analyze( $path );

		$this->assertIsArray( $analysis );
		$this->assertSame( 1, $analysis['summary']['valid'] );
		$this->assertSame( 'bom-1', $analysis['rows'][0]['data']['id_externo'] );
	}

	public function test_duplicate_external_id_and_unsafe_publish_are_rejected(): void {
		$first = $this->row(
			array(
				'id_externo' => 'duplicate-1',
				'nome'        => 'Primeira Pessoa',
				'depoimento'  => 'Primeiro depoimento.',
				'imagem_url'  => 'https://example.com/primeira.jpg',
			)
		);
		$second = $this->row(
			array(
				'id_externo'              => 'DUPLICATE-1',
				'nome'                     => 'Segunda Pessoa',
				'depoimento'               => 'Segundo depoimento.',
				'imagem_url'               => 'https://example.com/segunda.jpg',
				'consentimento_publicacao' => 'unknown',
				'status_publicacao'        => 'publish',
			)
		);

		$analysis = testimonials()->csv_parser()->analyze( $this->create_csv( array( $first, $second ) ) );

		$this->assertIsArray( $analysis );
		$this->assertSame( 2, $analysis['summary']['total'] );
		$this->assertSame( 1, $analysis['summary']['invalid'] );
		$this->assertCount( 3, $analysis['rows'][1]['errors'] );
	}

	public function test_publish_requires_verified_status(): void {
		$analysis = testimonials()->csv_parser()->analyze(
			$this->create_csv(
				array(
					$this->row(
						array(
							'id_externo'              => 'publish-pending-1',
							'nome'                     => 'Pessoa Pendente',
							'depoimento'               => 'Depoimento pendente.',
							'imagem_url'               => 'https://example.com/pessoa.jpg',
							'status_verificacao'       => 'pending',
							'consentimento_publicacao' => 'confirmed',
							'status_publicacao'        => 'publish',
						)
					),
				)
			)
		);

		$this->assertIsArray( $analysis );
		$this->assertSame( 1, $analysis['summary']['invalid'] );
		$this->assertStringContainsString( 'status_verificacao', implode( ' ', $analysis['rows'][0]['errors'] ) );
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
		$path   = wp_tempnam( 'testimonials-parser-test.csv' );
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
