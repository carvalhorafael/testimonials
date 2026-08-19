<?php
/**
 * Testimonials content domain.
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Testimonials_Content_Domain {
	public const POST_TYPE                           = 'depoimento';
	public const TAXONOMY                            = 'depoimento_categoria';
	public const TESTIMONIALS_PATH                   = 'aprovados';
	public const REWRITE_RULES_VERSION               = 'aprovados-v1';
	public const REWRITE_RULES_VERSION_OPTION        = 'testimonials_rewrite_rules_version';
	public const VIDEO_URL_META_KEY                  = '_testimonials_video_url';
	public const STUDENT_NAME_META_KEY               = '_testimonials_student_name';
	public const APPROVED_AT_META_KEY                = '_testimonials_approved_at';
	public const PLACEMENT_META_KEY                  = '_testimonials_placement';
	public const COURSE_META_KEY                     = '_testimonials_course';
	public const INSTITUTION_META_KEY                = '_testimonials_institution';
	public const APPROVAL_YEAR_META_KEY              = '_testimonials_approval_year';
	public const PREPARATION_TIME_META_KEY           = '_testimonials_preparation_time';
	public const MAIN_TIP_META_KEY                   = '_testimonials_main_tip';
	public const MAIN_TIP_MAX_LENGTH                 = 160;
	public const EVIDENCE_REFERENCE_META_KEY         = '_testimonials_evidence_reference';
	public const VERIFICATION_STATUS_META_KEY        = '_testimonials_verification_status';
	public const PUBLICATION_CONSENT_STATUS_META_KEY = '_testimonials_publication_consent_status';
	public const HOME_PROOF_ENABLED_META_KEY         = '_testimonials_home_proof_enabled';
	public const VERIFICATION_PENDING                = 'pending';
	public const VERIFICATION_VERIFIED               = 'verified';
	public const VERIFICATION_REJECTED               = 'rejected';
	public const CONSENT_UNKNOWN                     = 'unknown';
	public const CONSENT_CONFIRMED                   = 'confirmed';
	public const CONSENT_REVOKED                     = 'revoked';
	public const META_BOX_ID                         = 'testimonials-video';
	public const META_BOX_NONCE_ACTION               = 'testimonials_save_video_settings';
	public const META_BOX_NONCE_NAME                 = 'testimonials_video_nonce';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_content_types' ) );
		add_action( 'init', array( $this, 'register_meta' ), 11 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'use_title_for_new_post_slug' ), 10, 4 );
	}

	/**
	 * Regenerate rewrite rules once after the public testimonial path changes.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( self::REWRITE_RULES_VERSION === get_option( self::REWRITE_RULES_VERSION_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_RULES_VERSION_OPTION, self::REWRITE_RULES_VERSION, false );
	}

	/**
	 * Derive the slug for a new testimonial from its title.
	 *
	 * Existing records are intentionally left unchanged, including records with
	 * numeric slugs created before this contract was introduced.
	 *
	 * @param array<string,mixed> $data                Sanitized post data.
	 * @param array<string,mixed> $postarr             Processed post data.
	 * @param array<string,mixed> $unsanitized_postarr Raw post data.
	 * @param bool                $update              Whether this is an update.
	 * @return array<string,mixed>
	 */
	public function use_title_for_new_post_slug( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		unset( $postarr, $unsanitized_postarr );

		if ( $update || self::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		$title = isset( $data['post_title'] ) && is_string( $data['post_title'] ) ? $data['post_title'] : '';
		$slug  = sanitize_title( $title );

		if ( '' === $slug ) {
			return $data;
		}

		$data['post_name'] = wp_unique_post_slug(
			$slug,
			0,
			isset( $data['post_status'] ) && is_string( $data['post_status'] ) ? $data['post_status'] : 'draft',
			self::POST_TYPE,
			isset( $data['post_parent'] ) ? (int) $data['post_parent'] : 0
		);

		return $data;
	}

	public function register_content_types(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'has_archive'        => false,
				'hierarchical'       => false,
				'labels'             => $this->post_type_labels(),
				'menu_icon'          => 'dashicons-format-quote',
				'public'             => true,
				'publicly_queryable' => true,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => self::TESTIMONIALS_PATH,
					'with_front' => false,
				),
				'show_in_rest'       => true,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			array( self::POST_TYPE ),
			array(
				'hierarchical'      => true,
				'labels'            => $this->taxonomy_labels(),
				'public'            => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => self::TESTIMONIALS_PATH . '/categoria',
					'with_front' => false,
				),
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'show_ui'           => true,
			)
		);

		add_rewrite_rule(
			'^' . self::TESTIMONIALS_PATH . '/categoria/([^/]+)/?$',
			'index.php?' . self::TAXONOMY . '=$matches[1]',
			'top'
		);
	}

	public function register_meta(): void {
		$this->register_string_meta( self::VIDEO_URL_META_KEY, 'esc_url_raw' );
		$this->register_string_meta( self::STUDENT_NAME_META_KEY, 'sanitize_text_field' );
		$this->register_string_meta( self::APPROVED_AT_META_KEY, 'sanitize_text_field' );
		$this->register_string_meta( self::PLACEMENT_META_KEY, 'sanitize_text_field' );
		$this->register_string_meta( self::COURSE_META_KEY, 'sanitize_text_field' );
		$this->register_string_meta( self::INSTITUTION_META_KEY, 'sanitize_text_field' );
		$this->register_string_meta( self::APPROVAL_YEAR_META_KEY, array( $this, 'sanitize_approval_year' ) );
		$this->register_string_meta( self::PREPARATION_TIME_META_KEY, 'sanitize_text_field' );
		$this->register_string_meta( self::MAIN_TIP_META_KEY, array( $this, 'sanitize_main_tip' ) );
		$this->register_string_meta( self::EVIDENCE_REFERENCE_META_KEY, 'sanitize_text_field', false );
		$this->register_string_meta( self::VERIFICATION_STATUS_META_KEY, array( $this, 'sanitize_verification_status' ), false );
		$this->register_string_meta( self::PUBLICATION_CONSENT_STATUS_META_KEY, array( $this, 'sanitize_publication_consent_status' ), false );
		$this->register_boolean_meta( self::HOME_PROOF_ENABLED_META_KEY );
	}

	public function register_meta_box(): void {
		add_meta_box(
			self::META_BOX_ID,
			__( 'Testimonial details', 'testimonials' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	public function render_meta_box( WP_Post $post ): void {
		$video_url                 = get_post_meta( $post->ID, self::VIDEO_URL_META_KEY, true );
		$student_name              = get_post_meta( $post->ID, self::STUDENT_NAME_META_KEY, true );
		$approved_at               = get_post_meta( $post->ID, self::APPROVED_AT_META_KEY, true );
		$placement                 = get_post_meta( $post->ID, self::PLACEMENT_META_KEY, true );
		$course                    = get_post_meta( $post->ID, self::COURSE_META_KEY, true );
		$institution               = get_post_meta( $post->ID, self::INSTITUTION_META_KEY, true );
		$approval_year             = get_post_meta( $post->ID, self::APPROVAL_YEAR_META_KEY, true );
		$preparation_time          = get_post_meta( $post->ID, self::PREPARATION_TIME_META_KEY, true );
		$main_tip                  = get_post_meta( $post->ID, self::MAIN_TIP_META_KEY, true );
		$evidence_reference        = get_post_meta( $post->ID, self::EVIDENCE_REFERENCE_META_KEY, true );
		$verification_status       = get_post_meta( $post->ID, self::VERIFICATION_STATUS_META_KEY, true );
		$publication_consent_status = get_post_meta( $post->ID, self::PUBLICATION_CONSENT_STATUS_META_KEY, true );
		$home_proof_enabled        = (bool) get_post_meta( $post->ID, self::HOME_PROOF_ENABLED_META_KEY, true );

		$verification_status        = $verification_status ? $verification_status : self::VERIFICATION_PENDING;
		$publication_consent_status = $publication_consent_status ? $publication_consent_status : self::CONSENT_UNKNOWN;

		wp_nonce_field( self::META_BOX_NONCE_ACTION, self::META_BOX_NONCE_NAME );
		?>
		<p>
			<label for="testimonials-student-name"><?php esc_html_e( 'Nome do aluno', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-student-name"
				name="testimonials_student_name"
				type="text"
				value="<?php echo esc_attr( $student_name ); ?>"
			>
		</p>
		<p>
			<label for="testimonials-approved-at"><?php esc_html_e( 'Onde passou', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-approved-at"
				name="testimonials_approved_at"
				type="text"
				value="<?php echo esc_attr( $approved_at ); ?>"
			>
		</p>
		<p>
			<label for="testimonials-placement"><?php esc_html_e( 'Colocação', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-placement"
				name="testimonials_placement"
				type="text"
				value="<?php echo esc_attr( $placement ); ?>"
			>
		</p>
		<p>
			<label for="testimonials-course"><?php esc_html_e( 'Curso', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-course"
				name="testimonials_course"
				type="text"
				value="<?php echo esc_attr( $course ); ?>"
			>
		</p>
		<p>
			<label for="testimonials-institution"><?php esc_html_e( 'Instituição', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-institution"
				name="testimonials_institution"
				type="text"
				value="<?php echo esc_attr( $institution ); ?>"
			>
		</p>
		<p>
			<label for="testimonials-approval-year"><?php esc_html_e( 'Ano da aprovação', 'testimonials' ); ?></label>
			<input
				class="small-text"
				id="testimonials-approval-year"
				max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 1 ) ); ?>"
				min="1900"
				name="testimonials_approval_year"
				type="number"
				value="<?php echo esc_attr( $approval_year ); ?>"
			>
		</p>
		<p>
			<label for="testimonials-preparation-time"><?php esc_html_e( 'Tempo de preparação', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-preparation-time"
				maxlength="80"
				name="testimonials_preparation_time"
				type="text"
				value="<?php echo esc_attr( $preparation_time ); ?>"
			>
			<small><?php esc_html_e( 'Exemplo: 8 meses ou desde o 2º ano.', 'testimonials' ); ?></small>
		</p>
		<p>
			<label for="testimonials-main-tip"><?php esc_html_e( 'Principal dica do aprovado', 'testimonials' ); ?></label>
			<textarea
				class="widefat"
				id="testimonials-main-tip"
				maxlength="<?php echo esc_attr( (string) self::MAIN_TIP_MAX_LENGTH ); ?>"
				name="testimonials_main_tip"
				rows="4"
			><?php echo esc_textarea( $main_tip ); ?></textarea>
			<small><?php esc_html_e( 'Uma dica curta para o bloco “Meu superpoder”.', 'testimonials' ); ?></small>
		</p>
		<p>
			<label for="testimonials-video-url"><?php esc_html_e( 'Video URL', 'testimonials' ); ?></label>
			<input
				class="widefat"
				id="testimonials-video-url"
				name="testimonials_video_url"
				type="url"
				value="<?php echo esc_attr( $video_url ); ?>"
				placeholder="https://www.youtube.com/watch?v=..."
			>
		</p>
		<hr>
		<p>
			<label for="testimonials-evidence-reference"><strong><?php esc_html_e( 'Fonte de verificação', 'testimonials' ); ?></strong></label>
			<input
				class="widefat"
				id="testimonials-evidence-reference"
				name="testimonials_evidence_reference"
				type="text"
				value="<?php echo esc_attr( $evidence_reference ); ?>"
			>
			<small><?php esc_html_e( 'Referência interna ou URL. Este valor não é exposto pela API REST.', 'testimonials' ); ?></small>
		</p>
		<p>
			<label for="testimonials-verification-status"><strong><?php esc_html_e( 'Verificação dos dados', 'testimonials' ); ?></strong></label>
			<select class="widefat" id="testimonials-verification-status" name="testimonials_verification_status">
				<option value="<?php echo esc_attr( self::VERIFICATION_PENDING ); ?>"<?php selected( $verification_status, self::VERIFICATION_PENDING ); ?>><?php esc_html_e( 'Pendente', 'testimonials' ); ?></option>
				<option value="<?php echo esc_attr( self::VERIFICATION_VERIFIED ); ?>"<?php selected( $verification_status, self::VERIFICATION_VERIFIED ); ?>><?php esc_html_e( 'Verificado', 'testimonials' ); ?></option>
				<option value="<?php echo esc_attr( self::VERIFICATION_REJECTED ); ?>"<?php selected( $verification_status, self::VERIFICATION_REJECTED ); ?>><?php esc_html_e( 'Rejeitado', 'testimonials' ); ?></option>
			</select>
		</p>
		<p>
			<label for="testimonials-publication-consent-status"><strong><?php esc_html_e( 'Autorização de publicação', 'testimonials' ); ?></strong></label>
			<select class="widefat" id="testimonials-publication-consent-status" name="testimonials_publication_consent_status">
				<option value="<?php echo esc_attr( self::CONSENT_UNKNOWN ); ?>"<?php selected( $publication_consent_status, self::CONSENT_UNKNOWN ); ?>><?php esc_html_e( 'Não confirmada', 'testimonials' ); ?></option>
				<option value="<?php echo esc_attr( self::CONSENT_CONFIRMED ); ?>"<?php selected( $publication_consent_status, self::CONSENT_CONFIRMED ); ?>><?php esc_html_e( 'Confirmada', 'testimonials' ); ?></option>
				<option value="<?php echo esc_attr( self::CONSENT_REVOKED ); ?>"<?php selected( $publication_consent_status, self::CONSENT_REVOKED ); ?>><?php esc_html_e( 'Revogada', 'testimonials' ); ?></option>
			</select>
		</p>
		<p>
			<label>
				<input name="testimonials_home_proof_enabled" type="checkbox" value="1"<?php checked( $home_proof_enabled ); ?>>
				<?php esc_html_e( 'Disponibilizar para a prova social da home', 'testimonials' ); ?>
			</label>
			<br>
			<small><?php esc_html_e( 'A marcação só produz efeito quando o depoimento está publicado, possui imagem destacada, nome, curso, instituição, fonte, dados verificados e autorização confirmada.', 'testimonials' ); ?></small>
		</p>
		<?php
	}

	public function save_meta_box( int $post_id ): void {
		$nonce = isset( $_POST[ self::META_BOX_NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_BOX_NONCE_NAME ] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::META_BOX_NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save_url_meta( $post_id, 'testimonials_video_url', self::VIDEO_URL_META_KEY );
		$this->save_text_meta( $post_id, 'testimonials_student_name', self::STUDENT_NAME_META_KEY );
		$this->save_text_meta( $post_id, 'testimonials_approved_at', self::APPROVED_AT_META_KEY );
		$this->save_text_meta( $post_id, 'testimonials_placement', self::PLACEMENT_META_KEY );
		$this->save_text_meta( $post_id, 'testimonials_course', self::COURSE_META_KEY );
		$this->save_text_meta( $post_id, 'testimonials_institution', self::INSTITUTION_META_KEY );
		$this->save_approval_year_meta( $post_id );
		$this->save_text_meta( $post_id, 'testimonials_preparation_time', self::PREPARATION_TIME_META_KEY );
		$this->save_main_tip_meta( $post_id );
		$this->save_text_meta( $post_id, 'testimonials_evidence_reference', self::EVIDENCE_REFERENCE_META_KEY );
		$this->save_choice_meta( $post_id, 'testimonials_verification_status', self::VERIFICATION_STATUS_META_KEY, array( $this, 'sanitize_verification_status' ) );
		$this->save_choice_meta( $post_id, 'testimonials_publication_consent_status', self::PUBLICATION_CONSENT_STATUS_META_KEY, array( $this, 'sanitize_publication_consent_status' ) );
		$this->save_boolean_meta( $post_id, 'testimonials_home_proof_enabled', self::HOME_PROOF_ENABLED_META_KEY );
	}

	/**
	 * @param callable|string $sanitize_callback Sanitization callback.
	 */
	private function register_string_meta( string $meta_key, callable|string $sanitize_callback, bool $show_in_rest = true ): void {
		register_post_meta(
			self::POST_TYPE,
			$meta_key,
			array(
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'sanitize_callback' => $sanitize_callback,
				'show_in_rest'      => $show_in_rest,
				'single'            => true,
				'type'              => 'string',
			)
		);
	}

	private function register_boolean_meta( string $meta_key ): void {
		register_post_meta(
			self::POST_TYPE,
			$meta_key,
			array(
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'boolean',
			)
		);
	}

	private function save_url_meta( int $post_id, string $post_key, string $meta_key ): void {
		$value = esc_url_raw( $this->posted_scalar_value( $post_key ) );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	private function save_text_meta( int $post_id, string $post_key, string $meta_key ): void {
		$value = sanitize_text_field( $this->posted_scalar_value( $post_key ) );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	private function save_approval_year_meta( int $post_id ): void {
		$value = $this->sanitize_approval_year( $this->posted_scalar_value( 'testimonials_approval_year' ) );

		if ( '' === $value ) {
			delete_post_meta( $post_id, self::APPROVAL_YEAR_META_KEY );
			return;
		}

		update_post_meta( $post_id, self::APPROVAL_YEAR_META_KEY, $value );
	}

	private function save_main_tip_meta( int $post_id ): void {
		$value = $this->sanitize_main_tip( $this->posted_scalar_value( 'testimonials_main_tip' ) );

		if ( '' === $value ) {
			delete_post_meta( $post_id, self::MAIN_TIP_META_KEY );
			return;
		}

		update_post_meta( $post_id, self::MAIN_TIP_META_KEY, $value );
	}

	private function save_choice_meta( int $post_id, string $post_key, string $meta_key, callable $sanitize_callback ): void {
		$value = (string) call_user_func( $sanitize_callback, $this->posted_scalar_value( $post_key ) );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	private function save_boolean_meta( int $post_id, string $post_key, string $meta_key ): void {
		if ( '1' !== $this->posted_scalar_value( $post_key ) ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, true );
	}

	public function sanitize_approval_year( mixed $value ): string {
		$year         = is_scalar( $value ) ? (string) $value : '';
		$current_year = (int) gmdate( 'Y' );

		if ( ! preg_match( '/^\d{4}$/', $year ) ) {
			return '';
		}

		$year_number = (int) $year;

		return $year_number >= 1900 && $year_number <= $current_year + 1 ? (string) $year_number : '';
	}

	public function sanitize_main_tip( mixed $value ): string {
		$value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, self::MAIN_TIP_MAX_LENGTH );
		}

		return substr( $value, 0, self::MAIN_TIP_MAX_LENGTH );
	}

	public function sanitize_verification_status( mixed $value ): string {
		$value   = is_scalar( $value ) ? (string) $value : '';
		$allowed = array( self::VERIFICATION_PENDING, self::VERIFICATION_VERIFIED, self::VERIFICATION_REJECTED );

		return in_array( $value, $allowed, true ) ? $value : self::VERIFICATION_PENDING;
	}

	public function sanitize_publication_consent_status( mixed $value ): string {
		$value   = is_scalar( $value ) ? (string) $value : '';
		$allowed = array( self::CONSENT_UNKNOWN, self::CONSENT_CONFIRMED, self::CONSENT_REVOKED );

		return in_array( $value, $allowed, true ) ? $value : self::CONSENT_UNKNOWN;
	}

	public function is_home_proof_eligible( int $post_id ): bool {
		if ( self::POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) || ! has_post_thumbnail( $post_id ) ) {
			return false;
		}

		$required_text_meta = array(
			self::STUDENT_NAME_META_KEY,
			self::COURSE_META_KEY,
			self::INSTITUTION_META_KEY,
			self::EVIDENCE_REFERENCE_META_KEY,
		);

		foreach ( $required_text_meta as $meta_key ) {
			if ( '' === trim( (string) get_post_meta( $post_id, $meta_key, true ) ) ) {
				return false;
			}
		}

		return self::VERIFICATION_VERIFIED === get_post_meta( $post_id, self::VERIFICATION_STATUS_META_KEY, true )
			&& self::CONSENT_CONFIRMED === get_post_meta( $post_id, self::PUBLICATION_CONSENT_STATUS_META_KEY, true )
			&& (bool) get_post_meta( $post_id, self::HOME_PROOF_ENABLED_META_KEY, true );
	}

	private function posted_scalar_value( string $post_key ): string {
		if ( ! isset( $_POST[ $post_key ] ) || ! is_scalar( $_POST[ $post_key ] ) ) {
			return '';
		}

		return (string) wp_unslash( $_POST[ $post_key ] );
	}

	/**
	 * @return array<string, string>
	 */
	private function post_type_labels(): array {
		return array(
			'name'                  => _x( 'Testimonials', 'Post type general name', 'testimonials' ),
			'singular_name'         => _x( 'Testimonial', 'Post type singular name', 'testimonials' ),
			'menu_name'             => _x( 'Testimonials', 'Admin menu text', 'testimonials' ),
			'name_admin_bar'        => _x( 'Testimonial', 'Add new on toolbar', 'testimonials' ),
			'add_new'               => __( 'Add new', 'testimonials' ),
			'add_new_item'          => __( 'Add testimonial', 'testimonials' ),
			'all_items'             => __( 'All testimonials', 'testimonials' ),
			'archives'              => __( 'Testimonials', 'testimonials' ),
			'edit_item'             => __( 'Edit testimonial', 'testimonials' ),
			'featured_image'        => __( 'Testimonial image', 'testimonials' ),
			'filter_items_list'     => __( 'Filter testimonials', 'testimonials' ),
			'items_list'            => __( 'Testimonials list', 'testimonials' ),
			'items_list_navigation' => __( 'Testimonials list navigation', 'testimonials' ),
			'new_item'              => __( 'New testimonial', 'testimonials' ),
			'not_found'             => __( 'No testimonials found.', 'testimonials' ),
			'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'testimonials' ),
			'remove_featured_image' => __( 'Remove testimonial image', 'testimonials' ),
			'search_items'          => __( 'Search testimonials', 'testimonials' ),
			'set_featured_image'    => __( 'Set testimonial image', 'testimonials' ),
			'uploaded_to_this_item' => __( 'Uploaded to this testimonial', 'testimonials' ),
			'use_featured_image'    => __( 'Use as testimonial image', 'testimonials' ),
			'view_item'             => __( 'View testimonial', 'testimonials' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function taxonomy_labels(): array {
		return array(
			'name'              => _x( 'Testimonial categories', 'taxonomy general name', 'testimonials' ),
			'singular_name'     => _x( 'Testimonial category', 'taxonomy singular name', 'testimonials' ),
			'add_new_item'      => __( 'Add testimonial category', 'testimonials' ),
			'all_items'         => __( 'All categories', 'testimonials' ),
			'back_to_items'     => __( 'Back to categories', 'testimonials' ),
			'edit_item'         => __( 'Edit category', 'testimonials' ),
			'menu_name'         => __( 'Categories', 'testimonials' ),
			'new_item_name'     => __( 'New category name', 'testimonials' ),
			'not_found'         => __( 'No categories found.', 'testimonials' ),
			'parent_item'       => __( 'Parent category', 'testimonials' ),
			'parent_item_colon' => __( 'Parent category:', 'testimonials' ),
			'search_items'      => __( 'Search categories', 'testimonials' ),
			'update_item'       => __( 'Update category', 'testimonials' ),
			'view_item'         => __( 'View category', 'testimonials' ),
		);
	}
}
