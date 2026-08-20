<?php
/**
 * CSV parsing and validation for testimonial imports.
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Testimonials_CSV_Parser {
	public const MAX_ROWS          = 1000;
	public const PREVIEW_ROW_LIMIT = 50;

	/**
	 * @return string[]
	 */
	public static function headers(): array {
		return array(
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
		);
	}

	/**
	 * @return array{headers:string[],delimiter:string}|WP_Error
	 */
	public function inspect_header( string $path ): array|WP_Error {
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return new WP_Error( 'unreadable_file', __( 'O arquivo não pôde ser lido pelo WordPress.', 'testimonials' ) );
		}

		$first_line = fgets( $handle );
		fclose( $handle );

		if ( false === $first_line || str_contains( $first_line, "\0" ) ) {
			return new WP_Error( 'invalid_file', __( 'O arquivo não contém um cabeçalho CSV válido.', 'testimonials' ) );
		}

		$first_line = preg_replace( '/^\xEF\xBB\xBF/', '', $first_line ) ?? $first_line;

		$delimiter = substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ? ';' : ',';
		$headers   = str_getcsv( $first_line, $delimiter, '"', '' );
		$headers   = array_map( 'trim', $headers );

		return array(
			'headers'   => $headers,
			'delimiter' => $delimiter,
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function analyze( string $path ): array|WP_Error {
		$parsed = $this->parse_rows( $path );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$total_rows   = count( $parsed['rows'] );
		$valid_rows   = 0;
		$warning_rows = 0;
		$invalid_rows = 0;

		foreach ( $parsed['rows'] as $row ) {
			if ( ! empty( $row['errors'] ) ) {
				++$invalid_rows;
			} else {
				++$valid_rows;
			}

			if ( ! empty( $row['warnings'] ) ) {
				++$warning_rows;
			}
		}

		return array(
			'summary'       => array(
				'total'        => $total_rows,
				'valid'        => $valid_rows,
				'with_warning' => $warning_rows,
				'invalid'      => $invalid_rows,
			),
			'rows'          => array_slice( $parsed['rows'], 0, self::PREVIEW_ROW_LIMIT ),
			'file_errors'   => $parsed['file_errors'],
			'preview_limit' => self::PREVIEW_ROW_LIMIT,
			'truncated'     => $parsed['truncated'],
		);
	}

	/**
	 * @return array{rows:array<int,array<string,mixed>>,file_errors:string[],truncated:bool}|WP_Error
	 */
	public function parse_rows( string $path ): array|WP_Error {
		$inspection = $this->inspect_header( $path );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return new WP_Error( 'unreadable_file', __( 'O arquivo não pôde ser lido pelo WordPress.', 'testimonials' ) );
		}

		fgetcsv( $handle, 0, $inspection['delimiter'], '"', '' );

		$line_number  = 1;
		$rows         = array();
		$external_ids = array();
		$file_errors  = array();
		$truncated    = false;

		while ( false !== ( $values = fgetcsv( $handle, 0, $inspection['delimiter'], '"', '' ) ) ) {
			++$line_number;

			if ( $this->is_empty_row( $values ) ) {
				continue;
			}

			if ( count( $rows ) >= self::MAX_ROWS ) {
				$truncated     = true;
				$file_errors[] = sprintf(
					/* translators: %d: maximum number of CSV rows. */
					__( 'O arquivo excede o limite de %d depoimentos por importação.', 'testimonials' ),
					self::MAX_ROWS
				);
				break;
			}

			$rows[] = $this->analyze_row( $inspection['headers'], $values, $line_number, $external_ids );
		}

		fclose( $handle );

		if ( ! $rows ) {
			$file_errors[] = __( 'A planilha não contém nenhum depoimento.', 'testimonials' );
		}

		return array(
			'rows'        => $rows,
			'file_errors' => $file_errors,
			'truncated'   => $truncated,
		);
	}

	/**
	 * @param string[]          $headers      CSV headers.
	 * @param array<int,string> $values       CSV row values.
	 * @param array<string,int> $external_ids Previously seen external IDs.
	 * @return array<string,mixed>
	 */
	private function analyze_row( array $headers, array $values, int $line_number, array &$external_ids ): array {
		$errors   = array();
		$warnings = array();

		if ( count( $headers ) !== count( $values ) ) {
			return array(
				'line'     => $line_number,
				'data'     => array(),
				'errors'   => array( __( 'A quantidade de valores não corresponde à quantidade de colunas.', 'testimonials' ) ),
				'warnings' => array(),
			);
		}

		$data = array_combine( $headers, array_map( array( $this, 'normalize_value' ), $values ) );
		if ( false === $data ) {
			$data = array();
		}

		foreach ( array( 'id_externo', 'nome', 'depoimento' ) as $required_field ) {
			if ( '' === ( $data[ $required_field ] ?? '' ) ) {
				$errors[] = sprintf(
					/* translators: %s: required CSV column name. */
					__( 'O campo %s é obrigatório.', 'testimonials' ),
					$required_field
				);
			}
		}

		$external_id = mb_strtolower( $data['id_externo'] ?? '' );
		if ( '' !== $external_id ) {
			if ( isset( $external_ids[ $external_id ] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number where duplicate ID first appeared. */
					__( 'O id_externo está repetido; ele apareceu primeiro na linha %d.', 'testimonials' ),
					$external_ids[ $external_id ]
				);
			} else {
				$external_ids[ $external_id ] = $line_number;
			}
		}

		$this->validate_choice( $data, 'status_verificacao', array( '', 'pending', 'verified', 'rejected' ), $errors );
		$this->validate_choice( $data, 'consentimento_publicacao', array( '', 'unknown', 'confirmed', 'revoked' ), $errors );
		$this->validate_choice( $data, 'status_publicacao', array( '', 'draft', 'pending', 'publish' ), $errors );

		foreach ( array( 'prova_social_home', 'historia_destaque', 'hero' ) as $boolean_field ) {
			$this->validate_choice( $data, $boolean_field, array( '', '0', '1', 'nao', 'não', 'no', 'false', 'sim', 'yes', 'true' ), $errors );
		}

		if ( '' !== ( $data['ano_aprovacao'] ?? '' ) ) {
			$year = (int) $data['ano_aprovacao'];
			if ( (string) $year !== $data['ano_aprovacao'] || $year < 1900 || $year > ( (int) gmdate( 'Y' ) + 1 ) ) {
				$errors[] = __( 'O ano_aprovacao deve conter um ano válido.', 'testimonials' );
			}
		}

		if ( mb_strlen( $data['dica_principal'] ?? '' ) > Testimonials_Content_Domain::MAIN_TIP_MAX_LENGTH ) {
			$errors[] = sprintf(
				/* translators: %d: maximum main tip length. */
				__( 'A dica_principal deve ter no máximo %d caracteres.', 'testimonials' ),
				Testimonials_Content_Domain::MAIN_TIP_MAX_LENGTH
			);
		}

		foreach ( array( 'video_url', 'imagem_url' ) as $url_field ) {
			$url = $data[ $url_field ] ?? '';
			if ( '' !== $url && ! wp_http_validate_url( $url ) ) {
				$errors[] = sprintf(
					/* translators: %s: CSV URL column name. */
					__( 'O campo %s deve conter uma URL pública válida.', 'testimonials' ),
					$url_field
				);
			}
		}

		if ( '' === ( $data['imagem_url'] ?? '' ) ) {
			$warnings[] = __( 'Nenhuma imagem foi informada.', 'testimonials' );
		}

		if ( 'verified' === ( $data['status_verificacao'] ?? '' ) && '' === ( $data['fonte_verificacao'] ?? '' ) ) {
			$warnings[] = __( 'O registro está marcado como verificado, mas não possui fonte de verificação.', 'testimonials' );
		}

		if ( 'publish' === ( $data['status_publicacao'] ?? '' ) && 'confirmed' !== ( $data['consentimento_publicacao'] ?? '' ) ) {
			$errors[] = __( 'Para publicar, o consentimento_publicacao deve estar confirmado.', 'testimonials' );
		}

		if ( 'publish' === ( $data['status_publicacao'] ?? '' ) && 'verified' !== ( $data['status_verificacao'] ?? '' ) ) {
			$errors[] = __( 'Para publicar, o status_verificacao deve estar verificado.', 'testimonials' );
		}

		if ( $this->has_highlight_selection( $data ) && '' === ( $data['imagem_url'] ?? '' ) ) {
			$warnings[] = __( 'A seleção de destaque não terá efeito sem uma imagem.', 'testimonials' );
		}

		return array(
			'line'     => $line_number,
			'data'     => $data,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * @param array<string,string> $data    Row data.
	 * @param string[]             $allowed Allowed values.
	 * @param string[]             $errors  Row errors.
	 */
	private function validate_choice( array $data, string $field, array $allowed, array &$errors ): void {
		$value = mb_strtolower( $data[ $field ] ?? '' );
		if ( ! in_array( $value, $allowed, true ) ) {
			$errors[] = sprintf(
				/* translators: 1: CSV column name, 2: allowed values. */
				__( 'O campo %1$s possui um valor inválido. Valores aceitos: %2$s.', 'testimonials' ),
				$field,
				implode( ', ', array_filter( $allowed ) )
			);
		}
	}

	/**
	 * @param array<int,string|null> $values CSV row.
	 */
	private function is_empty_row( array $values ): bool {
		foreach ( $values as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	private function normalize_value( mixed $value ): string {
		return trim( (string) $value );
	}

	/**
	 * @param array<string,string> $data Row data.
	 */
	private function has_highlight_selection( array $data ): bool {
		foreach ( array( 'prova_social_home', 'historia_destaque', 'hero' ) as $field ) {
			if ( in_array( mb_strtolower( $data[ $field ] ?? '' ), array( '1', 'sim', 'yes', 'true' ), true ) ) {
				return true;
			}
		}

		return false;
	}
}
