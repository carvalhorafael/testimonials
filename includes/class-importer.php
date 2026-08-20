<?php
/**
 * Creates testimonial posts from validated CSV rows.
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Testimonials_Importer {
	public const EXTERNAL_ID_META_KEY = '_testimonials_import_external_id';
	public const FINGERPRINT_META_KEY = '_testimonials_import_fingerprint';
	public const BATCH_ID_META_KEY     = '_testimonials_import_batch_id';
	public const SOURCE_META_KEY       = '_testimonials_import_source';
	public const IMAGE_URL_META_KEY    = '_testimonials_import_image_url';
	public const MAX_IMAGE_FILE_SIZE   = 10485760;

	private Testimonials_CSV_Parser $csv_parser;

	private Testimonials_Content_Domain $content_domain;

	/** @var callable|null */
	private $media_import_callback;

	public function __construct( Testimonials_CSV_Parser $csv_parser, Testimonials_Content_Domain $content_domain, ?callable $media_import_callback = null ) {
		$this->csv_parser            = $csv_parser;
		$this->content_domain        = $content_domain;
		$this->media_import_callback = $media_import_callback;
	}

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_meta' ), 12 );
	}

	public function register_meta(): void {
		foreach ( array( self::EXTERNAL_ID_META_KEY, self::FINGERPRINT_META_KEY, self::BATCH_ID_META_KEY, self::SOURCE_META_KEY, self::IMAGE_URL_META_KEY ) as $meta_key ) {
			register_post_meta(
				Testimonials_Content_Domain::POST_TYPE,
				$meta_key,
				array(
					'auth_callback'     => static fn( bool $allowed, string $key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
					'sanitize_callback' => 'sanitize_text_field',
					'show_in_rest'      => false,
					'single'            => true,
					'type'              => 'string',
				)
			);
		}
	}

	/**
	 * @param array<string,string> $options Import options.
	 * @return array<string,mixed>|WP_Error
	 */
	public function import( string $path, string $source_name, array $options = array() ): array|WP_Error {
		$parsed = $this->csv_parser->parse_rows( $path );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		if ( $parsed['file_errors'] ) {
			return new WP_Error( 'invalid_import_file', implode( ' ', $parsed['file_errors'] ) );
		}

		$publication_mode = isset( $options['publication_mode'] ) && 'respect_csv' === $options['publication_mode']
			? 'respect_csv'
			: ( isset( $options['publication_mode'] ) && 'publish' === $options['publication_mode'] ? 'publish' : 'draft' );
		$batch_id        = wp_generate_uuid4();
		$report_rows     = array();
		$created         = 0;
		$skipped         = 0;
		$failed          = 0;
		$invalid         = 0;
		$media_imported  = 0;
		$media_failed    = 0;
		$media_missing   = 0;
		$published       = 0;
		$drafts          = 0;

		foreach ( $parsed['rows'] as $row ) {
			if ( ! empty( $row['errors'] ) ) {
				++$invalid;
				$report_rows[] = $this->report_row( $row, 'invalid', 0, implode( ' ', $row['errors'] ), 'not_processed', 0, '' );
				continue;
			}

			$data        = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array();
			$external_id = mb_strtolower( sanitize_text_field( $data['id_externo'] ?? '' ) );
			$existing_id = $this->find_existing_post_id( $external_id );

			if ( $existing_id ) {
				++$skipped;
				$report_rows[] = $this->report_row(
					$row,
					'skipped',
					$existing_id,
					__( 'Já existe um depoimento com este id_externo.', 'testimonials' ),
					'not_processed',
					0,
					(string) get_post_status( $existing_id )
				);
				continue;
			}

			$post_id = $this->insert_post( $data );
			if ( is_wp_error( $post_id ) ) {
				++$failed;
				$report_rows[] = $this->report_row( $row, 'failed', 0, $post_id->get_error_message(), 'not_processed', 0, '' );
				continue;
			}

			$this->save_metadata( $post_id, $data, $external_id, $batch_id, $source_name );
			$term_error   = $this->assign_categories( $post_id, $data['categorias'] ?? '' );
			$media_result = $this->import_featured_image( $post_id, $data );
			$message      = is_wp_error( $term_error ) ? $term_error->get_error_message() : '';

			if ( 'imported' === $media_result['status'] ) {
				++$media_imported;
				$this->apply_selections( $post_id, $data );
			} elseif ( 'failed' === $media_result['status'] ) {
				++$media_failed;
				$message = $this->append_message( $message, $media_result['message'] );
			} else {
				++$media_missing;
			}

			$status_result = $this->finalize_post_status( $post_id, $data, $publication_mode, $media_result['status'] );
			$message       = $this->append_message( $message, $status_result['message'] );
			if ( 'publish' === $status_result['status'] ) {
				++$published;
			} else {
				++$drafts;
			}

			++$created;
			$report_rows[] = $this->report_row(
				$row,
				'created',
				$post_id,
				$message,
				$media_result['status'],
				$media_result['attachment_id'],
				$status_result['status']
			);
		}

		return array(
			'batch_id' => $batch_id,
			'summary'  => array(
				'total'   => count( $parsed['rows'] ),
				'created' => $created,
				'skipped' => $skipped,
				'failed'  => $failed,
				'invalid' => $invalid,
				'media_imported' => $media_imported,
				'media_failed'   => $media_failed,
				'media_missing'  => $media_missing,
				'published'      => $published,
				'drafts'         => $drafts,
			),
			'rows'     => $report_rows,
		);
	}

	/**
	 * @param array<string,string> $data Validated CSV row.
	 * @return int|WP_Error
	 */
	private function insert_post( array $data ): int|WP_Error {
		return wp_insert_post(
			wp_slash(
				array(
					'post_type'    => Testimonials_Content_Domain::POST_TYPE,
					'post_status'  => 'draft',
					'post_title'   => sanitize_text_field( $data['nome'] ?? '' ),
					'post_content' => wp_kses_post( $data['depoimento'] ?? '' ),
					'post_excerpt' => sanitize_textarea_field( $data['resumo'] ?? '' ),
				)
			),
			true
		);
	}

	/**
	 * @param array<string,string> $data Validated CSV row.
	 * @return array{status:string,message:string}
	 */
	private function finalize_post_status( int $post_id, array $data, string $publication_mode, string $media_status ): array {
		$desired_status = 'draft';
		$message        = '';

		if ( 'respect_csv' === $publication_mode ) {
			$desired_status = in_array( $data['status_publicacao'] ?? '', array( 'draft', 'pending', 'publish' ), true )
				? $data['status_publicacao']
				: 'draft';
		} elseif ( 'publish' === $publication_mode ) {
			if ( 'verified' === ( $data['status_verificacao'] ?? '' ) && 'confirmed' === ( $data['consentimento_publicacao'] ?? '' ) ) {
				$desired_status = 'publish';
			} else {
				$message = __( 'Mantido como rascunho porque a verificação ou o consentimento não está confirmado.', 'testimonials' );
			}
		}

		if ( 'publish' === $desired_status && 'imported' !== $media_status ) {
			$desired_status = 'draft';
			$message        = __( 'Mantido como rascunho porque a imagem destacada não foi importada.', 'testimonials' );
		}

		if ( 'draft' !== $desired_status ) {
			$updated_post_id = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $desired_status,
				),
				true
			);

			if ( is_wp_error( $updated_post_id ) ) {
				return array(
					'status'  => 'draft',
					'message' => $this->append_message(
						$message,
						sprintf(
							/* translators: %s: post publication error. */
							__( 'Não foi possível atualizar o status: %s', 'testimonials' ),
							$updated_post_id->get_error_message()
						)
					),
				);
			}
		}

		return array(
			'status'  => $desired_status,
			'message' => $message,
		);
	}

	/**
	 * @param array<string,string> $data Validated CSV row.
	 */
	private function save_metadata( int $post_id, array $data, string $external_id, string $batch_id, string $source_name ): void {
		$text_meta = array(
			Testimonials_Content_Domain::STUDENT_NAME_META_KEY       => $data['nome'] ?? '',
			Testimonials_Content_Domain::APPROVED_AT_META_KEY        => $data['onde_passou'] ?? '',
			Testimonials_Content_Domain::PLACEMENT_META_KEY          => $data['colocacao'] ?? '',
			Testimonials_Content_Domain::COURSE_META_KEY             => $data['curso'] ?? '',
			Testimonials_Content_Domain::INSTITUTION_META_KEY        => $data['instituicao'] ?? '',
			Testimonials_Content_Domain::APPROVAL_YEAR_META_KEY      => $data['ano_aprovacao'] ?? '',
			Testimonials_Content_Domain::PREPARATION_TIME_META_KEY   => $data['tempo_preparacao'] ?? '',
			Testimonials_Content_Domain::MAIN_TIP_META_KEY           => $data['dica_principal'] ?? '',
			Testimonials_Content_Domain::EVIDENCE_REFERENCE_META_KEY => $data['fonte_verificacao'] ?? '',
		);

		foreach ( $text_meta as $meta_key => $value ) {
			$value = sanitize_text_field( $value );
			if ( '' === $value ) {
				continue;
			}
			update_post_meta( $post_id, $meta_key, $value );
		}

		$video_url = esc_url_raw( $data['video_url'] ?? '' );
		if ( '' !== $video_url ) {
			update_post_meta( $post_id, Testimonials_Content_Domain::VIDEO_URL_META_KEY, $video_url );
		}

		$verification_status = in_array( $data['status_verificacao'] ?? '', array( 'pending', 'verified', 'rejected' ), true )
			? $data['status_verificacao']
			: Testimonials_Content_Domain::VERIFICATION_PENDING;
		$consent_status      = in_array( $data['consentimento_publicacao'] ?? '', array( 'unknown', 'confirmed', 'revoked' ), true )
			? $data['consentimento_publicacao']
			: Testimonials_Content_Domain::CONSENT_UNKNOWN;

		update_post_meta( $post_id, Testimonials_Content_Domain::VERIFICATION_STATUS_META_KEY, $verification_status );
		update_post_meta( $post_id, Testimonials_Content_Domain::PUBLICATION_CONSENT_STATUS_META_KEY, $consent_status );
		update_post_meta( $post_id, self::EXTERNAL_ID_META_KEY, $external_id );
		update_post_meta( $post_id, self::FINGERPRINT_META_KEY, $this->fingerprint( $data ) );
		update_post_meta( $post_id, self::BATCH_ID_META_KEY, $batch_id );
		update_post_meta( $post_id, self::SOURCE_META_KEY, sanitize_file_name( $source_name ) );

	}

	/**
	 * @param array<string,string> $data Validated CSV row.
	 * @return array{status:string,attachment_id:int,message:string}
	 */
	private function import_featured_image( int $post_id, array $data ): array {
		$image_url = esc_url_raw( $data['imagem_url'] ?? '' );
		if ( '' === $image_url ) {
			return array(
				'status'        => 'missing',
				'attachment_id' => 0,
				'message'       => '',
			);
		}

		$result = $this->media_import_callback
			? call_user_func( $this->media_import_callback, $image_url, $post_id, $data['nome'] ?? '' )
			: $this->sideload_image( $image_url, $post_id, $data['nome'] ?? '' );

		if ( is_wp_error( $result ) ) {
			return array(
				'status'        => 'failed',
				'attachment_id' => 0,
				'message'       => sprintf(
					/* translators: %s: media import error. */
					__( 'A imagem não pôde ser importada: %s', 'testimonials' ),
					$result->get_error_message()
				),
			);
		}

		$attachment_id = (int) $result;
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) || ! set_post_thumbnail( $post_id, $attachment_id ) ) {
			if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );
			}

			return array(
				'status'        => 'failed',
				'attachment_id' => 0,
				'message'       => __( 'A imagem baixada não pôde ser definida como imagem destacada.', 'testimonials' ),
			);
		}

		update_post_meta( $post_id, self::IMAGE_URL_META_KEY, $image_url );

		return array(
			'status'        => 'imported',
			'attachment_id' => $attachment_id,
			'message'       => '',
		);
	}

	/**
	 * @return int|WP_Error
	 */
	private function sideload_image( string $image_url, int $post_id, string $description ): int|WP_Error {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$temp_file = wp_tempnam( 'testimonials-image-' . $post_id );
		if ( ! $temp_file ) {
			return new WP_Error( 'image_temp_file', __( 'Não foi possível criar o arquivo temporário.', 'testimonials' ) );
		}

		$response = wp_safe_remote_get(
			$image_url,
			array(
				'filename'            => $temp_file,
				'limit_response_size' => self::MAX_IMAGE_FILE_SIZE,
				'redirection'         => 3,
				'stream'              => true,
				'timeout'             => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $temp_file );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			wp_delete_file( $temp_file );
			return new WP_Error(
				'image_http_status',
				sprintf(
					/* translators: %d: HTTP response status code. */
					__( 'A URL respondeu com o código HTTP %d.', 'testimonials' ),
					$status_code
				)
			);
		}

		if ( filesize( $temp_file ) >= self::MAX_IMAGE_FILE_SIZE ) {
			wp_delete_file( $temp_file );
			return new WP_Error( 'image_too_large', __( 'A imagem excede o limite de 10 MB.', 'testimonials' ) );
		}

		$file_name = $this->image_file_name( $image_url, (string) wp_remote_retrieve_header( $response, 'content-type' ), $post_id );
		$file_type = wp_check_filetype_and_ext( $temp_file, $file_name );
		if ( ! isset( $file_type['type'] ) || ! is_string( $file_type['type'] ) || ! str_starts_with( $file_type['type'], 'image/' ) ) {
			wp_delete_file( $temp_file );
			return new WP_Error( 'invalid_image_type', __( 'O arquivo remoto não é uma imagem compatível.', 'testimonials' ) );
		}

		if ( ! empty( $file_type['proper_filename'] ) && is_string( $file_type['proper_filename'] ) ) {
			$file_name = $file_type['proper_filename'];
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $file_name,
				'tmp_name' => $temp_file,
			),
			$post_id,
			sanitize_text_field( $description )
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temp_file );
			return $attachment_id;
		}

		update_post_meta( (int) $attachment_id, '_source_url', $image_url );

		return (int) $attachment_id;
	}

	private function image_file_name( string $image_url, string $content_type, int $post_id ): string {
		$path      = (string) wp_parse_url( $image_url, PHP_URL_PATH );
		$file_name = sanitize_file_name( wp_basename( $path ) );
		$extension = strtolower( (string) pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, array( 'avif', 'gif', 'heic', 'heif', 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			return $file_name;
		}

		$extensions = array(
			'image/avif' => 'avif',
			'image/gif'  => 'gif',
			'image/heic' => 'heic',
			'image/heif' => 'heif',
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);

		return 'depoimento-' . $post_id . '.' . ( $extensions[ strtolower( $content_type ) ] ?? 'jpg' );
	}

	/**
	 * @param array<string,string> $data Validated CSV row.
	 */
	private function apply_selections( int $post_id, array $data ): void {
		if ( $this->boolean_value( $data['prova_social_home'] ?? '' ) ) {
			update_post_meta( $post_id, Testimonials_Content_Domain::HOME_PROOF_ENABLED_META_KEY, true );
		}

		if ( $this->boolean_value( $data['historia_destaque'] ?? '' ) ) {
			$this->content_domain->set_featured_story_selection( $post_id, true );
		}

		if ( $this->boolean_value( $data['hero'] ?? '' ) ) {
			$this->content_domain->set_hero_selection( $post_id, true );
		}
	}

	private function append_message( string $current, string $addition ): string {
		return '' === $current ? $addition : $current . ' ' . $addition;
	}

	private function assign_categories( int $post_id, string $categories ): WP_Error|array {
		$terms = array_filter( array_map( 'sanitize_text_field', preg_split( '/\s*\|\s*/', $categories ) ?: array() ) );
		if ( ! $terms ) {
			return array();
		}

		return wp_set_object_terms( $post_id, $terms, Testimonials_Content_Domain::TAXONOMY, false );
	}

	private function find_existing_post_id( string $external_id ): int {
		if ( '' === $external_id ) {
			return 0;
		}

		$post_ids = get_posts(
			array(
				'fields'         => 'ids',
				'meta_key'       => self::EXTERNAL_ID_META_KEY,
				'meta_value'     => $external_id,
				'post_status'    => 'any',
				'post_type'      => Testimonials_Content_Domain::POST_TYPE,
				'posts_per_page' => 1,
			)
		);

		return $post_ids ? (int) $post_ids[0] : 0;
	}

	/**
	 * @param array<string,string> $data CSV row.
	 */
	private function fingerprint( array $data ): string {
		return hash( 'sha256', (string) wp_json_encode( $data ) );
	}

	private function boolean_value( string $value ): bool {
		return in_array( mb_strtolower( $value ), array( '1', 'sim', 'yes', 'true' ), true );
	}

	/**
	 * @param array<string,mixed> $row Parsed row.
	 * @return array<string,mixed>
	 */
	private function report_row( array $row, string $status, int $post_id, string $message, string $media_status, int $attachment_id, string $post_status ): array {
		$data = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array();

		return array(
			'line'          => (int) ( $row['line'] ?? 0 ),
			'external_id'   => (string) ( $data['id_externo'] ?? '' ),
			'name'          => (string) ( $data['nome'] ?? '' ),
			'status'        => $status,
			'post_id'       => $post_id,
			'message'       => $message,
			'media_status'  => $media_status,
			'attachment_id' => $attachment_id,
			'post_status'    => $post_status,
		);
	}
}
