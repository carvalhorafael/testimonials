<?php
/**
 * Administrative screen for bulk testimonial imports.
 *
 * @package Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Testimonials_Import_Admin_Page {
	public const PAGE_SLUG       = 'testimonials-import';
	public const CAPABILITY      = 'manage_options';
	public const DOWNLOAD_ACTION = 'testimonials_download_import_template';
	public const UPLOAD_ACTION   = 'testimonials_validate_import_file';
	public const IMPORT_ACTION   = 'testimonials_execute_import';
	public const CLEAR_ACTION    = 'testimonials_clear_import_file';
	public const NONCE_ACTION    = 'testimonials_import_admin';
	public const MAX_FILE_SIZE   = 5242880;
	public const SESSION_TTL     = HOUR_IN_SECONDS;

	private string $page_hook = '';

	private Testimonials_CSV_Parser $csv_parser;

	private Testimonials_Importer $importer;

	public function __construct( Testimonials_CSV_Parser $csv_parser, Testimonials_Importer $importer ) {
		$this->csv_parser = $csv_parser;
		$this->importer   = $importer;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'download_template' ) );
		add_action( 'admin_post_' . self::UPLOAD_ACTION, array( $this, 'validate_upload' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'execute_import' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( $this, 'clear_upload' ) );
		add_action( 'testimonials_cleanup_import_file', array( $this, 'cleanup_temp_file' ) );
	}

	public function register_admin_menu(): void {
		$this->page_hook = (string) add_submenu_page(
			'edit.php?post_type=' . Testimonials_Content_Domain::POST_TYPE,
			__( 'Importar depoimentos', 'testimonials' ),
			__( 'Importar', 'testimonials' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $this->page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'testimonials-import-admin',
			plugins_url( 'assets/admin-import.css', TESTIMONIALS_FILE ),
			array(),
			TESTIMONIALS_VERSION
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para importar depoimentos.', 'testimonials' ) );
		}

		$download_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION ),
			self::NONCE_ACTION
		);
		$session      = $this->get_import_session();
		$has_report   = $session && isset( $session['report'] ) && is_array( $session['report'] );
		?>
		<div class="wrap testimonials-import-admin">
			<h1><?php esc_html_e( 'Importar depoimentos', 'testimonials' ); ?></h1>
			<p class="testimonials-import-admin__intro">
				<?php esc_html_e( 'Envie vários depoimentos em um arquivo CSV padronizado. Antes de qualquer importação, o plugin validará a planilha e mostrará o que será criado.', 'testimonials' ); ?>
			</p>

			<?php $this->render_notice(); ?>

			<ol class="testimonials-import-steps" aria-label="<?php esc_attr_e( 'Etapas da importação', 'testimonials' ); ?>">
				<li class="testimonials-import-steps__item<?php echo esc_attr( $session ? '' : ' is-current' ); ?>"<?php if ( ! $session ) : ?> aria-current="step"<?php endif; ?>>
					<span class="testimonials-import-steps__number">1</span>
					<span><?php esc_html_e( 'Enviar arquivo', 'testimonials' ); ?></span>
				</li>
				<li class="testimonials-import-steps__item<?php echo esc_attr( $session && ! $has_report ? ' is-current' : '' ); ?>"<?php if ( $session && ! $has_report ) : ?> aria-current="step"<?php endif; ?>>
					<span class="testimonials-import-steps__number">2</span>
					<span><?php esc_html_e( 'Revisar dados', 'testimonials' ); ?></span>
				</li>
				<li class="testimonials-import-steps__item<?php echo esc_attr( $has_report ? ' is-current' : '' ); ?>"<?php if ( $has_report ) : ?> aria-current="step"<?php endif; ?>>
					<span class="testimonials-import-steps__number">3</span>
					<span><?php esc_html_e( 'Importar', 'testimonials' ); ?></span>
				</li>
			</ol>

			<?php if ( $has_report ) : ?>
				<?php $this->render_import_report( $session ); ?>
			<?php elseif ( $session ) : ?>
				<?php $this->render_review( $session ); ?>
			<?php else : ?>
			<div class="testimonials-import-admin__layout">
				<main class="testimonials-import-admin__main">
					<section class="testimonials-import-section" aria-labelledby="testimonials-import-prepare-title">
						<h2 id="testimonials-import-prepare-title"><?php esc_html_e( 'Prepare sua planilha', 'testimonials' ); ?></h2>
						<p><?php esc_html_e( 'Use o modelo para garantir que os nomes e a ordem das colunas sejam reconhecidos pelo plugin.', 'testimonials' ); ?></p>
						<a class="button button-secondary" href="<?php echo esc_url( $download_url ); ?>">
							<span class="dashicons dashicons-download" aria-hidden="true"></span>
							<?php esc_html_e( 'Baixar modelo CSV', 'testimonials' ); ?>
						</a>
					</section>

					<section class="testimonials-import-section" aria-labelledby="testimonials-import-upload-title">
						<h2 id="testimonials-import-upload-title"><?php esc_html_e( 'Envie o arquivo', 'testimonials' ); ?></h2>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" method="post">
							<input name="action" type="hidden" value="<?php echo esc_attr( self::UPLOAD_ACTION ); ?>">
							<?php wp_nonce_field( self::NONCE_ACTION ); ?>

							<div class="testimonials-import-upload">
								<label for="testimonials-import-file">
									<strong><?php esc_html_e( 'Selecione uma planilha CSV', 'testimonials' ); ?></strong>
									<span><?php esc_html_e( 'Arquivo de até 5 MB, separado por vírgulas ou ponto e vírgula.', 'testimonials' ); ?></span>
								</label>
								<input accept=".csv,text/csv" id="testimonials-import-file" name="testimonials_import_file" required type="file">
							</div>

							<p class="submit">
								<button class="button button-primary button-hero" type="submit">
									<?php esc_html_e( 'Validar planilha', 'testimonials' ); ?>
								</button>
							</p>
							<p class="description">
								<?php esc_html_e( 'Esta etapa verifica o formato do arquivo. Nenhum depoimento será criado.', 'testimonials' ); ?>
							</p>
						</form>
					</section>
				</main>

				<aside class="testimonials-import-admin__aside" aria-labelledby="testimonials-import-guidance-title">
					<h2 id="testimonials-import-guidance-title"><?php esc_html_e( 'Antes de enviar', 'testimonials' ); ?></h2>
					<ul>
						<li><?php esc_html_e( 'Não altere os cabeçalhos do modelo.', 'testimonials' ); ?></li>
						<li><?php esc_html_e( 'Use um identificador externo único para evitar duplicações.', 'testimonials' ); ?></li>
						<li><?php esc_html_e( 'Informe apenas URLs públicas para imagens e vídeos.', 'testimonials' ); ?></li>
						<li><?php esc_html_e( 'Não inclua telefone, e-mail ou outros dados pessoais desnecessários.', 'testimonials' ); ?></li>
					</ul>
					<p>
						<strong><?php esc_html_e( 'Campos obrigatórios:', 'testimonials' ); ?></strong><br>
						<code>id_externo</code>, <code>nome</code>, <code>depoimento</code>
					</p>
				</aside>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function download_template(): void {
		$this->authorize_request();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="modelo-importacao-depoimentos.csv"' );

		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Não foi possível gerar o modelo CSV.', 'testimonials' ) );
		}

		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, Testimonials_CSV_Parser::headers(), ',', '"', '' );
		fclose( $output );
		exit;
	}

	public function validate_upload(): void {
		$this->authorize_request();

		$file = isset( $_FILES['testimonials_import_file'] ) && is_array( $_FILES['testimonials_import_file'] )
			? $_FILES['testimonials_import_file']
			: array();

		$notice = $this->validate_csv_file( $file );
		if ( 'file_ready' !== $notice ) {
			$this->redirect_with_notice( $notice );
		}

		$tmp_name    = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$stored_path = wp_tempnam( 'testimonials-import-' . get_current_user_id() . '.csv' );
		if ( ! $stored_path || ! move_uploaded_file( $tmp_name, $stored_path ) ) {
			$this->redirect_with_notice( 'store_error' );
		}

		$analysis = $this->csv_parser->analyze( $stored_path );
		if ( is_wp_error( $analysis ) ) {
			$this->cleanup_temp_file( $stored_path );
			$this->redirect_with_notice( 'analysis_error' );
		}

		$this->delete_import_session();
		set_transient(
			$this->session_key(),
			array(
				'path'          => $stored_path,
				'file_name'     => isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'depoimentos.csv',
				'file_size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
				'analysis'      => $analysis,
				'uploaded_at'   => time(),
			),
			self::SESSION_TTL
		);

		wp_schedule_single_event( time() + self::SESSION_TTL, 'testimonials_cleanup_import_file', array( $stored_path ) );
		$this->redirect_with_notice( 'analysis_ready' );
	}

	public function clear_upload(): void {
		$this->authorize_request();
		$this->delete_import_session();
		$this->redirect_with_notice( 'file_cleared' );
	}

	public function execute_import(): void {
		$this->authorize_request();

		$session = $this->get_import_session();
		if ( ! $session || ! isset( $session['path'] ) || ! is_string( $session['path'] ) ) {
			$this->redirect_with_notice( 'session_expired' );
		}

		$publication_mode = isset( $_POST['testimonials_publication_mode'] )
			? sanitize_key( wp_unslash( $_POST['testimonials_publication_mode'] ) )
			: 'draft';
		if ( ! in_array( $publication_mode, array( 'draft', 'publish', 'respect_csv' ), true ) ) {
			$publication_mode = 'draft';
		}

		$report = $this->importer->import(
			$session['path'],
			isset( $session['file_name'] ) && is_string( $session['file_name'] ) ? $session['file_name'] : 'depoimentos.csv',
			array( 'publication_mode' => $publication_mode )
		);

		if ( is_wp_error( $report ) ) {
			$this->redirect_with_notice( 'import_error' );
		}

		$session['report']           = $report;
		$session['publication_mode'] = $publication_mode;
		set_transient( $this->session_key(), $session, self::SESSION_TTL );

		$this->redirect_with_notice( 'import_complete' );
	}

	/**
	 * Validate the file envelope and canonical CSV header.
	 *
	 * @param array<string,mixed> $file Uploaded file data.
	 */
	public function validate_csv_file( array $file ): string {
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return 'upload_error';
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 ) {
			return 'empty_file';
		}

		if ( $size > self::MAX_FILE_SIZE ) {
			return 'file_too_large';
		}

		$name      = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$file_type = wp_check_filetype( $name, array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== ( $file_type['ext'] ?? '' ) ) {
			return 'invalid_file_type';
		}

		$tmp_name = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( '' === $tmp_name || ! is_readable( $tmp_name ) ) {
			return 'unreadable_file';
		}

		$inspection = $this->csv_parser->inspect_header( $tmp_name );
		if ( is_wp_error( $inspection ) || ! $this->has_canonical_headers( $inspection['headers'] ) ) {
			return 'invalid_headers';
		}

		return 'file_ready';
	}

	/**
	 * @param array<string,mixed> $session Completed import session.
	 */
	private function render_import_report( array $session ): void {
		$report    = isset( $session['report'] ) && is_array( $session['report'] ) ? $session['report'] : array();
		$summary   = isset( $report['summary'] ) && is_array( $report['summary'] ) ? $report['summary'] : array();
		$rows      = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();
		$clear_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::CLEAR_ACTION ),
			self::NONCE_ACTION
		);
		?>
		<section class="testimonials-import-review" aria-labelledby="testimonials-import-report-title">
			<div class="testimonials-import-review__header">
				<div>
					<h2 id="testimonials-import-report-title"><?php esc_html_e( 'Importação concluída', 'testimonials' ); ?></h2>
					<p>
						<?php esc_html_e( 'Lote:', 'testimonials' ); ?>
						<code><?php echo esc_html( (string) ( $report['batch_id'] ?? '' ) ); ?></code>
					</p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Testimonials_Content_Domain::POST_TYPE ) ); ?>">
					<?php esc_html_e( 'Ver depoimentos', 'testimonials' ); ?>
				</a>
			</div>

			<div class="testimonials-import-summary testimonials-import-summary--report" aria-label="<?php esc_attr_e( 'Resumo da importação', 'testimonials' ); ?>">
				<div>
					<strong><?php echo esc_html( (string) (int) ( $summary['total'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Registros', 'testimonials' ); ?></span>
				</div>
				<div class="is-success">
					<strong><?php echo esc_html( (string) (int) ( $summary['created'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Criados', 'testimonials' ); ?></span>
				</div>
				<div class="is-success">
					<strong><?php echo esc_html( (string) (int) ( $summary['published'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Publicados', 'testimonials' ); ?></span>
				</div>
				<div class="is-success">
					<strong><?php echo esc_html( (string) (int) ( $summary['media_imported'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Imagens importadas', 'testimonials' ); ?></span>
				</div>
				<div class="is-error">
					<strong><?php echo esc_html( (string) (int) ( $summary['media_failed'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Imagens com erro', 'testimonials' ); ?></span>
				</div>
				<div class="is-error">
					<strong><?php echo esc_html( (string) ( (int) ( $summary['failed'] ?? 0 ) + (int) ( $summary['invalid'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Não importados', 'testimonials' ); ?></span>
				</div>
			</div>

			<div class="notice notice-info inline testimonials-import-report-note">
				<p><?php esc_html_e( 'As imagens válidas foram adicionadas à biblioteca e definidas como imagem destacada. Falhas de mídia não removem o depoimento criado.', 'testimonials' ); ?></p>
			</div>

			<div class="testimonials-import-table-wrap">
				<table class="widefat striped testimonials-import-table testimonials-import-report-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Linha', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Identificador', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Aprovado', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Resultado', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Imagem', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Registro', 'testimonials' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php $this->render_report_row( is_array( $row ) ? $row : array() ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="testimonials-import-review__footer testimonials-import-review__footer--compact">
				<p><?php esc_html_e( 'Você pode revisar os registros criados ou iniciar uma nova importação.', 'testimonials' ); ?></p>
				<a class="button" href="<?php echo esc_url( $clear_url ); ?>"><?php esc_html_e( 'Importar outra planilha', 'testimonials' ); ?></a>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $row Import report row.
	 */
	private function render_report_row( array $row ): void {
		$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'failed';
		$labels = array(
			'created' => array( __( 'Criado', 'testimonials' ), 'is-ready' ),
			'skipped' => array( __( 'Ignorado', 'testimonials' ), 'is-warning' ),
			'invalid' => array( __( 'Inválido', 'testimonials' ), 'is-error' ),
			'failed'  => array( __( 'Erro', 'testimonials' ), 'is-error' ),
		);
		$media_status = isset( $row['media_status'] ) ? sanitize_key( (string) $row['media_status'] ) : 'not_processed';
		$media_labels = array(
			'imported'      => array( __( 'Importada', 'testimonials' ), 'is-ready' ),
			'missing'       => array( __( 'Não informada', 'testimonials' ), 'is-warning' ),
			'failed'        => array( __( 'Erro', 'testimonials' ), 'is-error' ),
			'not_processed' => array( __( 'Não processada', 'testimonials' ), 'is-neutral' ),
		);
		$label          = $labels[ $status ] ?? $labels['failed'];
		$media_label    = $media_labels[ $media_status ] ?? $media_labels['not_processed'];
		$post_id        = (int) ( $row['post_id'] ?? 0 );
		$attachment_id  = (int) ( $row['attachment_id'] ?? 0 );
		$post_status    = isset( $row['post_status'] ) ? sanitize_key( (string) $row['post_status'] ) : '';
		$post_statuses  = array(
			'draft'   => __( 'Rascunho', 'testimonials' ),
			'pending' => __( 'Pendente', 'testimonials' ),
			'publish' => __( 'Publicado', 'testimonials' ),
		);
		$edit_url       = $post_id ? get_edit_post_link( $post_id, 'raw' ) : '';
		$attachment_url = $attachment_id ? get_edit_post_link( $attachment_id, 'raw' ) : '';
		?>
		<tr>
			<td><?php echo esc_html( (string) (int) ( $row['line'] ?? 0 ) ); ?></td>
			<td><code><?php echo esc_html( $this->display_value( $row['external_id'] ?? '' ) ); ?></code></td>
			<td><strong><?php echo esc_html( $this->display_value( $row['name'] ?? '' ) ); ?></strong></td>
			<td>
				<span class="testimonials-import-status <?php echo esc_attr( $label[1] ); ?>"><?php echo esc_html( $label[0] ); ?></span>
				<?php if ( isset( $post_statuses[ $post_status ] ) ) : ?>
					<p class="description"><?php echo esc_html( $post_statuses[ $post_status ] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $row['message'] ) ) : ?>
					<p class="description"><?php echo esc_html( (string) $row['message'] ); ?></p>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $attachment_url ) : ?>
					<a href="<?php echo esc_url( $attachment_url ); ?>"><span class="testimonials-import-status <?php echo esc_attr( $media_label[1] ); ?>"><?php echo esc_html( $media_label[0] ); ?></span></a>
				<?php else : ?>
					<span class="testimonials-import-status <?php echo esc_attr( $media_label[1] ); ?>"><?php echo esc_html( $media_label[0] ); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Editar depoimento', 'testimonials' ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'Não criado', 'testimonials' ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param array<string,mixed> $session Import review session.
	 */
	private function render_review( array $session ): void {
		$analysis    = isset( $session['analysis'] ) && is_array( $session['analysis'] ) ? $session['analysis'] : array();
		$summary     = isset( $analysis['summary'] ) && is_array( $analysis['summary'] ) ? $analysis['summary'] : array();
		$rows        = isset( $analysis['rows'] ) && is_array( $analysis['rows'] ) ? $analysis['rows'] : array();
		$errors      = isset( $analysis['file_errors'] ) && is_array( $analysis['file_errors'] ) ? $analysis['file_errors'] : array();
		$valid_count = (int) ( $summary['valid'] ?? 0 );
		$can_import  = $valid_count > 0 && ! $errors;
		$clear_url   = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::CLEAR_ACTION ),
			self::NONCE_ACTION
		);
		?>
		<section class="testimonials-import-review" aria-labelledby="testimonials-import-review-title">
			<div class="testimonials-import-review__header">
				<div>
					<h2 id="testimonials-import-review-title"><?php esc_html_e( 'Revisão da planilha', 'testimonials' ); ?></h2>
					<p>
						<strong><?php echo esc_html( (string) ( $session['file_name'] ?? 'depoimentos.csv' ) ); ?></strong>
						<span aria-hidden="true"> · </span>
						<?php echo esc_html( size_format( (int) ( $session['file_size'] ?? 0 ) ) ); ?>
					</p>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( $clear_url ); ?>">
					<?php esc_html_e( 'Trocar arquivo', 'testimonials' ); ?>
				</a>
			</div>

			<div class="testimonials-import-summary" aria-label="<?php esc_attr_e( 'Resumo da validação', 'testimonials' ); ?>">
				<div>
					<strong><?php echo esc_html( (string) (int) ( $summary['total'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Registros', 'testimonials' ); ?></span>
				</div>
				<div class="is-success">
					<strong><?php echo esc_html( (string) (int) ( $summary['valid'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Válidos', 'testimonials' ); ?></span>
				</div>
				<div class="is-warning">
					<strong><?php echo esc_html( (string) (int) ( $summary['with_warning'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Com avisos', 'testimonials' ); ?></span>
				</div>
				<div class="is-error">
					<strong><?php echo esc_html( (string) (int) ( $summary['invalid'] ?? 0 ) ); ?></strong>
					<span><?php esc_html_e( 'Com erros', 'testimonials' ); ?></span>
				</div>
			</div>

			<?php if ( $errors ) : ?>
				<div class="notice notice-error inline">
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( (string) $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="testimonials-import-table-wrap">
				<table class="widefat striped testimonials-import-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Linha', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Identificador', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Aprovado', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Aprovação', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Mídia', 'testimonials' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Resultado', 'testimonials' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $rows ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'Nenhum registro encontrado.', 'testimonials' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php $this->render_review_row( is_array( $row ) ? $row : array() ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( (int) ( $summary['total'] ?? 0 ) > count( $rows ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of preview rows. */
						esc_html__( 'A prévia mostra os primeiros %d registros. Todos foram considerados no resumo.', 'testimonials' ),
						count( $rows )
					);
					?>
				</p>
			<?php endif; ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="testimonials-import-review__footer" method="post">
				<input name="action" type="hidden" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<fieldset class="testimonials-import-publication-mode">
					<legend><strong><?php esc_html_e( 'Como os depoimentos devem entrar?', 'testimonials' ); ?></strong></legend>
					<label>
						<input checked name="testimonials_publication_mode" type="radio" value="draft">
						<span>
							<strong><?php esc_html_e( 'Importar todos como rascunho', 'testimonials' ); ?></strong>
							<small><?php esc_html_e( 'Opção recomendada para revisar conteúdo e imagens antes da publicação.', 'testimonials' ); ?></small>
						</span>
					</label>
					<label>
						<input name="testimonials_publication_mode" type="radio" value="publish">
						<span>
							<strong><?php esc_html_e( 'Publicar automaticamente os registros aptos', 'testimonials' ); ?></strong>
							<small><?php esc_html_e( 'Serão publicados apenas os registros verificados, autorizados e com imagem importada. Os demais permanecerão como rascunho.', 'testimonials' ); ?></small>
						</span>
					</label>
					<label>
						<input name="testimonials_publication_mode" type="radio" value="respect_csv">
						<span>
							<strong><?php esc_html_e( 'Respeitar status_publicacao da planilha', 'testimonials' ); ?></strong>
							<small><?php esc_html_e( 'Use somente quando os status, a verificação e o consentimento já tiverem sido revisados.', 'testimonials' ); ?></small>
						</span>
					</label>
				</fieldset>

				<div class="testimonials-import-review__actions">
					<p class="description"><?php esc_html_e( 'Linhas com erro serão ignoradas. As imagens válidas serão baixadas para a biblioteca e definidas como imagem destacada.', 'testimonials' ); ?></p>
					<div>
						<a class="button" href="<?php echo esc_url( $clear_url ); ?>"><?php esc_html_e( 'Voltar e trocar arquivo', 'testimonials' ); ?></a>
						<button class="button button-primary"<?php disabled( ! $can_import ); ?> type="submit">
							<?php
							printf(
								/* translators: %d: number of valid testimonials. */
								esc_html( _n( 'Importar %d depoimento', 'Importar %d depoimentos', $valid_count, 'testimonials' ) ),
								$valid_count
							);
							?>
						</button>
					</div>
				</div>
			</form>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $row Analyzed CSV row.
	 */
	private function render_review_row( array $row ): void {
		$data     = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array();
		$errors   = isset( $row['errors'] ) && is_array( $row['errors'] ) ? $row['errors'] : array();
		$warnings = isset( $row['warnings'] ) && is_array( $row['warnings'] ) ? $row['warnings'] : array();
		$media    = $this->media_label( $data );

		$status_class = 'is-ready';
		$status_label = __( 'Pronto', 'testimonials' );
		if ( $errors ) {
			$status_class = 'is-error';
			$status_label = __( 'Erro', 'testimonials' );
		} elseif ( $warnings ) {
			$status_class = 'is-warning';
			$status_label = __( 'Atenção', 'testimonials' );
		}
		?>
		<tr>
			<td><?php echo esc_html( (string) (int) ( $row['line'] ?? 0 ) ); ?></td>
			<td><code><?php echo esc_html( $this->display_value( $data['id_externo'] ?? '' ) ); ?></code></td>
			<td><strong><?php echo esc_html( $this->display_value( $data['nome'] ?? '' ) ); ?></strong></td>
			<td>
				<?php echo esc_html( $this->display_value( $data['curso'] ?? '' ) ); ?><br>
				<span class="description"><?php echo esc_html( $this->display_value( $data['instituicao'] ?? '' ) ); ?></span>
			</td>
			<td><?php echo esc_html( $media ); ?></td>
			<td>
				<span class="testimonials-import-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
				<?php if ( $errors || $warnings ) : ?>
					<ul class="testimonials-import-row-messages">
						<?php foreach ( $errors as $error ) : ?>
							<li class="is-error"><?php echo esc_html( (string) $error ); ?></li>
						<?php endforeach; ?>
						<?php foreach ( $warnings as $warning ) : ?>
							<li class="is-warning"><?php echo esc_html( (string) $warning ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param array<string,string> $data CSV row data.
	 */
	private function media_label( array $data ): string {
		$has_image = '' !== ( $data['imagem_url'] ?? '' );
		$has_video = '' !== ( $data['video_url'] ?? '' );

		if ( $has_image && $has_video ) {
			return __( 'Imagem e vídeo', 'testimonials' );
		}

		if ( $has_image ) {
			return __( 'Imagem', 'testimonials' );
		}

		if ( $has_video ) {
			return __( 'Vídeo', 'testimonials' );
		}

		return __( 'Sem mídia', 'testimonials' );
	}

	private function display_value( mixed $value ): string {
		$value = trim( (string) $value );

		return '' === $value ? __( 'Não informado', 'testimonials' ) : $value;
	}

	/**
	 * @param string[] $headers Uploaded CSV headers.
	 */
	private function has_canonical_headers( array $headers ): bool {
		$canonical_headers = Testimonials_CSV_Parser::headers();
		if ( count( $headers ) !== count( array_unique( $headers ) ) || count( $headers ) !== count( $canonical_headers ) ) {
			return false;
		}

		sort( $headers );
		sort( $canonical_headers );

		return $canonical_headers === $headers;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function get_import_session(): ?array {
		$session = get_transient( $this->session_key() );
		if ( ! is_array( $session ) ) {
			return null;
		}

		$path = isset( $session['path'] ) && is_string( $session['path'] ) ? $session['path'] : '';
		if ( '' === $path || ! is_readable( $path ) ) {
			delete_transient( $this->session_key() );
			return null;
		}

		return $session;
	}

	private function delete_import_session(): void {
		$session = get_transient( $this->session_key() );
		if ( is_array( $session ) && isset( $session['path'] ) && is_string( $session['path'] ) ) {
			$this->cleanup_temp_file( $session['path'] );
		}

		delete_transient( $this->session_key() );
	}

	public function cleanup_temp_file( string $path ): void {
		$normalized_path = wp_normalize_path( $path );
		$temp_directory  = trailingslashit( wp_normalize_path( get_temp_dir() ) );
		$file_name       = wp_basename( $normalized_path );

		if ( ! str_starts_with( $normalized_path, $temp_directory ) || ! str_starts_with( $file_name, 'testimonials-import-' ) ) {
			return;
		}

		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	private function session_key(): string {
		return 'testimonials_import_session_' . get_current_user_id();
	}

	private function authorize_request(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para importar depoimentos.', 'testimonials' ) );
		}

		check_admin_referer( self::NONCE_ACTION );
	}

	private function redirect_with_notice( string $notice ): void {
		$url = add_query_arg(
			array(
				'post_type'                  => Testimonials_Content_Domain::POST_TYPE,
				'page'                       => self::PAGE_SLUG,
				'testimonials_import_notice' => $notice,
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	private function render_notice(): void {
		$notice = isset( $_GET['testimonials_import_notice'] )
			? sanitize_key( wp_unslash( $_GET['testimonials_import_notice'] ) )
			: '';

		$notices = array(
			'analysis_ready'    => array( 'success', __( 'Planilha analisada. Revise os dados e as mensagens antes de continuar.', 'testimonials' ) ),
			'import_complete'    => array( 'success', __( 'Importação concluída. Confira abaixo o resultado de cada registro.', 'testimonials' ) ),
			'file_cleared'      => array( 'success', __( 'A planilha temporária foi descartada. Você pode enviar outro arquivo.', 'testimonials' ) ),
			'session_expired'    => array( 'error', __( 'A planilha temporária expirou. Envie o arquivo novamente.', 'testimonials' ) ),
			'import_error'       => array( 'error', __( 'Não foi possível concluir a importação. Revise a planilha e tente novamente.', 'testimonials' ) ),
			'upload_error'      => array( 'error', __( 'Não foi possível receber o arquivo. Selecione a planilha e tente novamente.', 'testimonials' ) ),
			'empty_file'        => array( 'error', __( 'O arquivo enviado está vazio.', 'testimonials' ) ),
			'file_too_large'    => array( 'error', __( 'O arquivo excede o limite de 5 MB.', 'testimonials' ) ),
			'invalid_file_type' => array( 'error', __( 'Envie um arquivo com extensão CSV.', 'testimonials' ) ),
			'unreadable_file'   => array( 'error', __( 'O arquivo não pôde ser lido pelo WordPress.', 'testimonials' ) ),
			'invalid_headers'   => array( 'error', __( 'As colunas não correspondem ao modelo do plugin. Baixe o modelo e preserve os cabeçalhos.', 'testimonials' ) ),
			'store_error'       => array( 'error', __( 'Não foi possível armazenar temporariamente a planilha.', 'testimonials' ) ),
			'analysis_error'    => array( 'error', __( 'Não foi possível analisar o conteúdo da planilha.', 'testimonials' ) ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}

		$type    = $notices[ $notice ][0];
		$message = $notices[ $notice ][1];
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}
}
