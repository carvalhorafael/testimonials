<?php
/**
 * Import admin page integration tests.
 *
 * @package Testimonials
 */

final class ImportAdminPageTest extends WP_UnitTestCase {
	public function test_csv_template_exposes_the_canonical_columns(): void {
		$this->assertSame(
			array(
				'id_externo',
				'nome',
				'depoimento',
				'resumo',
				'onde_passou',
				'colocacao',
				'curso',
				'instituicao',
				'ano_aprovacao',
				'tempo_preparacao',
				'dica_principal',
				'video_url',
				'imagem_url',
				'categorias',
				'fonte_verificacao',
				'status_verificacao',
				'consentimento_publicacao',
				'status_publicacao',
				'prova_social_home',
				'historia_destaque',
				'hero',
			),
			Testimonials_CSV_Parser::headers()
		);
	}

	public function test_valid_csv_header_is_recognized(): void {
		$path = $this->create_csv_file( Testimonials_CSV_Parser::headers(), ',' );

		$result = testimonials()->import_admin_page()->validate_csv_file(
			array(
				'error'    => UPLOAD_ERR_OK,
				'name'     => 'depoimentos.csv',
				'size'     => filesize( $path ),
				'tmp_name' => $path,
			)
		);

		$this->assertSame( 'file_ready', $result );
	}

	public function test_semicolon_delimited_header_is_recognized(): void {
		$path = $this->create_csv_file( Testimonials_CSV_Parser::headers(), ';' );

		$result = testimonials()->import_admin_page()->validate_csv_file(
			array(
				'error'    => UPLOAD_ERR_OK,
				'name'     => 'depoimentos.csv',
				'size'     => filesize( $path ),
				'tmp_name' => $path,
			)
		);

		$this->assertSame( 'file_ready', $result );
	}

	public function test_unexpected_columns_are_rejected(): void {
		$path = $this->create_csv_file( array( 'nome', 'depoimento' ), ',' );

		$result = testimonials()->import_admin_page()->validate_csv_file(
			array(
				'error'    => UPLOAD_ERR_OK,
				'name'     => 'depoimentos.csv',
				'size'     => filesize( $path ),
				'tmp_name' => $path,
			)
		);

		$this->assertSame( 'invalid_headers', $result );
	}

	public function test_canonical_columns_can_be_reordered(): void {
		$headers = array_reverse( Testimonials_CSV_Parser::headers() );
		$path    = $this->create_csv_file( $headers, ',' );

		$result = testimonials()->import_admin_page()->validate_csv_file(
			array(
				'error'    => UPLOAD_ERR_OK,
				'name'     => 'depoimentos.csv',
				'size'     => filesize( $path ),
				'tmp_name' => $path,
			)
		);

		$this->assertSame( 'file_ready', $result );
	}

	/**
	 * @param string[] $headers CSV headers.
	 */
	private function create_csv_file( array $headers, string $delimiter ): string {
		$path   = wp_tempnam( 'testimonials-import-test.csv' );
		$handle = fopen( $path, 'wb' );

		$this->assertIsResource( $handle );
		fputcsv( $handle, $headers, $delimiter, '"', '' );
		fclose( $handle );

		return $path;
	}
}
