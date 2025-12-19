<?php
/**
 * Knowledge Base Manager Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIEngine\AIEngine;
use AIEngine\Knowledge\KnowledgeBase;

/**
 * Class AIAGENT_Knowledge_Manager
 *
 * Handles knowledge base management for the AI agent.
 */
class AIAGENT_Knowledge_Manager {

	/**
	 * Path to the knowledge base file.
	 *
	 * @var string
	 */
	private $kb_file;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$upload_dir    = wp_upload_dir();
		$this->kb_file = $upload_dir['basedir'] . '/ai-agent-knowledge/knowledge-base.json';
	}

	/**
	 * Render the knowledge base admin page.
	 */
	public function render_admin_page() {
		// Handle actions.
		if ( isset( $_POST['aiagent_add_url'] ) && check_admin_referer( 'aiagent_kb_nonce' ) ) {
			$this->add_url();
		}

		if ( isset( $_POST['aiagent_add_text'] ) && check_admin_referer( 'aiagent_kb_nonce' ) ) {
			$this->add_text();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['index'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Used for nonce action.
			if ( check_admin_referer( 'aiagent_delete_' . absint( $_GET['index'] ) ) ) {
				$this->delete_document( absint( $_GET['index'] ) );
			}
		}

		if ( isset( $_POST['aiagent_clear_kb'] ) && check_admin_referer( 'aiagent_kb_nonce' ) ) {
			$this->clear_knowledge();
		}

		$kb        = $this->get_knowledge_base();
		$documents = $kb->getDocuments();
		$summary   = $kb->getSummary();

		// Get file processor for supported types.
		$file_processor       = new AIAGENT_File_Processor();
		$supported_extensions = $file_processor->get_supported_extensions();
		$max_file_size        = $file_processor->get_max_file_size();
		$uploaded_files       = $file_processor->get_uploaded_files();
		?>
		<div class="wrap aiagent-admin">
			<h1><?php esc_html_e( 'Knowledge Base', 'ai-agent-for-website' ); ?></h1>
			
			<p class="description">
				<?php esc_html_e( 'Add content to train your AI agent. The AI will use this information to answer questions about your website.', 'ai-agent-for-website' ); ?>
			</p>

			<div class="aiagent-kb-grid aiagent-kb-grid-3">
				<div class="aiagent-card">
					<h2>
						<span class="dashicons dashicons-admin-links" style="color: #0073aa;"></span>
						<?php esc_html_e( 'Add from URL', 'ai-agent-for-website' ); ?>
					</h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'aiagent_kb_nonce' ); ?>
						<p>
							<input type="url" 
									name="kb_url" 
									placeholder="https://example.com/page" 
									class="large-text" 
									required>
						</p>
						<p class="description">
							<?php esc_html_e( 'Enter a URL to fetch and add its content to the knowledge base.', 'ai-agent-for-website' ); ?>
						</p>
						<p>
							<input type="submit" 
									name="aiagent_add_url" 
									class="button button-primary" 
									value="<?php esc_attr_e( 'Add URL', 'ai-agent-for-website' ); ?>">
						</p>
					</form>
				</div>

				<div class="aiagent-card">
					<h2>
						<span class="dashicons dashicons-edit" style="color: #23a455;"></span>
						<?php esc_html_e( 'Add Custom Text', 'ai-agent-for-website' ); ?>
					</h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'aiagent_kb_nonce' ); ?>
						<div class="aiagent-custom-text-form">
							<div class="aiagent-form-row">
								<label for="kb_title"><?php esc_html_e( 'Title', 'ai-agent-for-website' ); ?></label>
								<input type="text" 
										id="kb_title"
										name="kb_title" 
										placeholder="<?php esc_attr_e( 'e.g., FAQ, Product Info', 'ai-agent-for-website' ); ?>" 
										class="large-text">
							</div>
							<div class="aiagent-form-row">
								<label for="kb_category"><?php esc_html_e( 'Category', 'ai-agent-for-website' ); ?></label>
								<select name="kb_category" id="kb_category" class="regular-text">
									<option value=""><?php esc_html_e( 'Select a category...', 'ai-agent-for-website' ); ?></option>
									<option value="general"><?php esc_html_e( 'General Information', 'ai-agent-for-website' ); ?></option>
									<option value="faq"><?php esc_html_e( 'FAQ', 'ai-agent-for-website' ); ?></option>
									<option value="product"><?php esc_html_e( 'Product/Service', 'ai-agent-for-website' ); ?></option>
									<option value="support"><?php esc_html_e( 'Support', 'ai-agent-for-website' ); ?></option>
									<option value="policy"><?php esc_html_e( 'Policies', 'ai-agent-for-website' ); ?></option>
									<option value="contact"><?php esc_html_e( 'Contact Info', 'ai-agent-for-website' ); ?></option>
									<option value="other"><?php esc_html_e( 'Other', 'ai-agent-for-website' ); ?></option>
								</select>
							</div>
							<div class="aiagent-form-row">
								<label for="kb_text"><?php esc_html_e( 'Content', 'ai-agent-for-website' ); ?></label>
								<textarea name="kb_text" 
											id="kb_text"
											rows="4" 
											class="large-text" 
											placeholder="<?php esc_attr_e( 'Enter your content here...', 'ai-agent-for-website' ); ?>" 
											required></textarea>
								<div class="aiagent-char-count">
									<span id="kb-char-count">0</span> <?php esc_html_e( 'characters', 'ai-agent-for-website' ); ?>
								</div>
							</div>
							<div class="aiagent-form-actions">
								<input type="submit" 
										name="aiagent_add_text" 
										class="button button-primary" 
										value="<?php esc_attr_e( 'Add Text', 'ai-agent-for-website' ); ?>">
							</div>
						</div>
					</form>
				</div>

				<div class="aiagent-card">
					<h2>
						<span class="dashicons dashicons-upload" style="color: #9b59b6;"></span>
						<?php esc_html_e( 'Upload Files', 'ai-agent-for-website' ); ?>
					</h2>
					<div class="aiagent-file-upload-area" id="aiagent-file-drop-zone">
						<div class="aiagent-file-upload-icon">
							<span class="dashicons dashicons-cloud-upload"></span>
						</div>
						<p class="aiagent-file-upload-text">
							<?php esc_html_e( 'Drag & drop files here or', 'ai-agent-for-website' ); ?>
						</p>
						<label class="button button-primary aiagent-file-upload-btn">
							<?php esc_html_e( 'Choose Files', 'ai-agent-for-website' ); ?>
							<input type="file" 
									id="aiagent-file-input" 
									accept="<?php echo esc_attr( '.' . implode( ',.', $supported_extensions ) ); ?>" 
									multiple 
									hidden>
						</label>
						<p class="description aiagent-file-upload-info">
							<?php
							/* translators: 1: List of supported file extensions, 2: Maximum file size in MB */
							printf(
								esc_html__( 'Supported: %1$s (Max %2$s MB)', 'ai-agent-for-website' ),
								esc_html( strtoupper( implode( ', ', $supported_extensions ) ) ),
								esc_html( round( $max_file_size / 1048576, 1 ) )
							);
							?>
						</p>
					</div>
					<div id="aiagent-file-upload-progress" class="aiagent-file-upload-progress" style="display: none;">
						<div class="aiagent-file-progress-item">
							<span class="aiagent-file-name"></span>
							<div class="aiagent-progress-bar">
								<div class="aiagent-progress-fill"></div>
							</div>
							<span class="aiagent-file-status"></span>
						</div>
					</div>
					<div id="aiagent-file-upload-results" class="aiagent-file-upload-results"></div>
				</div>

				<?php if ( AIAGENT_Google_Drive_Integration::is_connected() ) : ?>
				<div class="aiagent-card aiagent-integration-import-card">
					<h2>
						<span class="dashicons dashicons-google" style="color: #4285f4;"></span>
						<?php esc_html_e( 'Import from Google Drive', 'ai-agent-for-website' ); ?>
					</h2>
					<div class="aiagent-gdrive-browser">
						<div class="aiagent-gdrive-search">
							<input type="text" id="aiagent-gdrive-search" placeholder="<?php esc_attr_e( 'Search files...', 'ai-agent-for-website' ); ?>" class="regular-text">
							<button type="button" id="aiagent-gdrive-refresh" class="button">
								<span class="dashicons dashicons-update"></span>
							</button>
						</div>
						<div id="aiagent-gdrive-files" class="aiagent-file-browser">
							<p class="aiagent-loading"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading files...', 'ai-agent-for-website' ); ?></p>
						</div>
						<div class="aiagent-gdrive-actions" style="display: none;">
							<button type="button" id="aiagent-gdrive-import-selected" class="button button-primary" disabled>
								<?php esc_html_e( 'Import Selected', 'ai-agent-for-website' ); ?>
							</button>
							<span class="aiagent-import-status"></span>
						</div>
					</div>
				</div>
				<?php else : ?>
				<div class="aiagent-card aiagent-integration-import-card aiagent-card-disabled">
					<h2>
						<span class="dashicons dashicons-google" style="color: #4285f4;"></span>
						<?php esc_html_e( 'Google Drive', 'ai-agent-for-website' ); ?>
					</h2>
					<p class="description">
						<?php esc_html_e( 'Connect your Google Drive account to import documents.', 'ai-agent-for-website' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-settings&tab=integrations' ) ); ?>" class="button">
						<span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Connect Google Drive', 'ai-agent-for-website' ); ?>
					</a>
				</div>
				<?php endif; ?>

				<?php if ( AIAGENT_Confluence_Integration::is_connected() ) : ?>
				<div class="aiagent-card aiagent-integration-import-card">
					<h2>
						<span class="aiagent-integration-icon aiagent-icon-atlassian"></span>
						<?php esc_html_e( 'Import from Confluence', 'ai-agent-for-website' ); ?>
					</h2>
					<div class="aiagent-confluence-browser">
						<div class="aiagent-confluence-space-select">
							<label for="aiagent-confluence-space"><?php esc_html_e( 'Select Space:', 'ai-agent-for-website' ); ?></label>
							<select id="aiagent-confluence-space" class="regular-text">
								<option value=""><?php esc_html_e( 'Loading spaces...', 'ai-agent-for-website' ); ?></option>
							</select>
						</div>
						<div id="aiagent-confluence-pages" class="aiagent-file-browser" style="display: none;">
							<p class="aiagent-empty"><?php esc_html_e( 'Select a space to view pages', 'ai-agent-for-website' ); ?></p>
						</div>
						<div class="aiagent-confluence-actions" style="display: none;">
							<button type="button" id="aiagent-confluence-import-selected" class="button button-primary" disabled>
								<?php esc_html_e( 'Import Selected', 'ai-agent-for-website' ); ?>
							</button>
							<span class="aiagent-import-status"></span>
						</div>
					</div>
				</div>
				<?php else : ?>
				<div class="aiagent-card aiagent-integration-import-card aiagent-card-disabled">
					<h2>
						<span class="aiagent-integration-icon aiagent-icon-atlassian"></span>
						<?php esc_html_e( 'Confluence', 'ai-agent-for-website' ); ?>
					</h2>
					<p class="description">
						<?php esc_html_e( 'Connect your Confluence account to import wiki pages.', 'ai-agent-for-website' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-settings&tab=integrations' ) ); ?>" class="button">
						<span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Connect Confluence', 'ai-agent-for-website' ); ?>
					</a>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $uploaded_files ) ) : ?>
			<div class="aiagent-card">
				<h2>
					<span class="dashicons dashicons-media-document" style="color: #9b59b6;"></span>
					<?php esc_html_e( 'Uploaded Files', 'ai-agent-for-website' ); ?>
					<span class="aiagent-badge"><?php echo esc_html( count( $uploaded_files ) ); ?></span>
				</h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 30%;"><?php esc_html_e( 'File Name', 'ai-agent-for-website' ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( 'Type', 'ai-agent-for-website' ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( 'Size', 'ai-agent-for-website' ); ?></th>
							<th style="width: 20%;"><?php esc_html_e( 'Uploaded', 'ai-agent-for-website' ); ?></th>
							<th style="width: 20%;"><?php esc_html_e( 'Actions', 'ai-agent-for-website' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $uploaded_files as $file ) : ?>
							<tr>
								<td>
									<span class="dashicons dashicons-media-document"></span>
									<?php echo esc_html( $file['original_name'] ); ?>
								</td>
								<td>
									<span class="aiagent-file-type-badge"><?php echo esc_html( strtoupper( $file['file_type'] ) ); ?></span>
								</td>
								<td><?php echo esc_html( size_format( $file['file_size'] ) ); ?></td>
								<td><?php echo esc_html( gmdate( 'M j, Y g:i a', strtotime( $file['uploaded_at'] ) ) ); ?></td>
								<td>
									<button type="button" 
											class="button button-small button-link-delete aiagent-delete-file" 
											data-file-id="<?php echo esc_attr( $file['id'] ); ?>"
											data-kb-index="<?php echo esc_attr( $file['kb_document_index'] ); ?>">
										<?php esc_html_e( 'Delete', 'ai-agent-for-website' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<div class="aiagent-card">
				<h2>
					<?php esc_html_e( 'Knowledge Base Contents', 'ai-agent-for-website' ); ?>
					<span class="aiagent-badge"><?php echo esc_html( $summary['count'] ); ?> <?php esc_html_e( 'documents', 'ai-agent-for-website' ); ?></span>
				</h2>

				<?php if ( $summary['count'] > 0 ) : ?>
					<p class="description">
						<?php
						/* translators: %s: Total number of characters */
						printf( esc_html__( 'Total content: %s characters', 'ai-agent-for-website' ), esc_html( number_format( $summary['totalChars'] ) ) );
						?>
					</p>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 5%;">#</th>
								<th style="width: 25%;"><?php esc_html_e( 'Title', 'ai-agent-for-website' ); ?></th>
								<th style="width: 35%;"><?php esc_html_e( 'Source', 'ai-agent-for-website' ); ?></th>
								<th style="width: 15%;"><?php esc_html_e( 'Size', 'ai-agent-for-website' ); ?></th>
								<th style="width: 10%;"><?php esc_html_e( 'Added', 'ai-agent-for-website' ); ?></th>
								<th style="width: 10%;"><?php esc_html_e( 'Actions', 'ai-agent-for-website' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $documents as $index => $doc ) : ?>
								<tr>
									<td><?php echo esc_html( $index + 1 ); ?></td>
									<td><?php echo esc_html( $doc['title'] ?? __( 'Untitled', 'ai-agent-for-website' ) ); ?></td>
									<td>
										<?php
										$source      = $doc['source'] ?? '';
										$source_icon = 'dashicons-admin-page';

										if ( filter_var( $source, FILTER_VALIDATE_URL ) ) {
											$source_icon = 'dashicons-admin-links';
										} elseif ( strpos( $source, 'file-upload-' ) === 0 ) {
											$source_icon = 'dashicons-media-document';
										} elseif ( strpos( $source, 'manual-entry-' ) === 0 ) {
											$source_icon = 'dashicons-edit';
										}
										?>
										<span class="dashicons <?php echo esc_attr( $source_icon ); ?>" style="opacity: 0.5;"></span>
										<?php if ( filter_var( $source, FILTER_VALIDATE_URL ) ) : ?>
											<a href="<?php echo esc_url( $source ); ?>" target="_blank">
												<?php echo esc_html( substr( $source, 0, 40 ) ); ?>...
											</a>
										<?php else : ?>
											<?php echo esc_html( substr( $source, 0, 30 ) ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( number_format( strlen( $doc['content'] ) ) ); ?> <?php esc_html_e( 'chars', 'ai-agent-for-website' ); ?></td>
									<td><?php echo esc_html( gmdate( 'M j', strtotime( $doc['addedAt'] ) ) ); ?></td>
									<td>
										<a href="
										<?php
										echo esc_url(
											wp_nonce_url(
												add_query_arg(
													[
														'action' => 'delete',
														'index'  => $index,
													]
												),
												'aiagent_delete_' . $index
											)
										);
										?>
													" 
											class="button button-small button-link-delete"
											onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'ai-agent-for-website' ); ?>');">
											<?php esc_html_e( 'Delete', 'ai-agent-for-website' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<form method="post" action="" style="margin-top: 20px;">
						<?php wp_nonce_field( 'aiagent_kb_nonce' ); ?>
						<input type="submit" 
								name="aiagent_clear_kb" 
								class="button button-link-delete" 
								value="<?php esc_attr_e( 'Clear All Knowledge', 'ai-agent-for-website' ); ?>"
								onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete all knowledge? This cannot be undone.', 'ai-agent-for-website' ); ?>');">
					</form>
				<?php else : ?>
					<p class="aiagent-empty-state">
						<?php esc_html_e( 'No content added yet. Add URLs, text, or upload files above to train your AI agent.', 'ai-agent-for-website' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="aiagent-card">
				<h2>
					<span class="dashicons dashicons-search" style="color: #e67e22;"></span>
					<?php esc_html_e( 'Auto Detect Pillar Pages', 'ai-agent-for-website' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Use AI to analyze your website and suggest the most important pages to add to the knowledge base.', 'ai-agent-for-website' ); ?>
				</p>
				<div class="aiagent-pillar-detect">
					<button type="button" id="aiagent-detect-pillar" class="button button-primary">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'Detect Pillar Pages', 'ai-agent-for-website' ); ?>
					</button>
					<span class="aiagent-detect-status"></span>
				</div>
				<div id="aiagent-pillar-results" class="aiagent-pillar-results" style="display: none;">
					<h3><?php esc_html_e( 'Recommended Pages', 'ai-agent-for-website' ); ?></h3>
					<p class="description"><?php esc_html_e( 'AI has identified these important pages for your knowledge base:', 'ai-agent-for-website' ); ?></p>
					<div id="aiagent-pillar-list" class="aiagent-pillar-list"></div>
					<div class="aiagent-pillar-actions">
						<button type="button" id="aiagent-add-selected-pillar" class="button button-primary">
							<?php esc_html_e( 'Add Selected Pages', 'ai-agent-for-website' ); ?>
						</button>
						<button type="button" id="aiagent-add-all-pillar" class="button">
							<?php esc_html_e( 'Add All Pages', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="aiagent-card">
				<h2>
					<span class="dashicons dashicons-admin-page" style="color: #3498db;"></span>
					<?php esc_html_e( 'Quick Add Website Pages', 'ai-agent-for-website' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Quickly add your website pages to the knowledge base:', 'ai-agent-for-website' ); ?>
				</p>
				<div class="aiagent-quick-pages">
					<?php
					$pages = get_pages(
						[
							'number'      => 10,
							'sort_column' => 'post_modified',
							'sort_order'  => 'DESC',
						]
					);
					foreach ( $pages as $page ) :
						?>
						<form method="post" action="" style="display: inline-block; margin: 5px;">
							<?php wp_nonce_field( 'aiagent_kb_nonce' ); ?>
							<input type="hidden" name="kb_url" value="<?php echo esc_url( get_permalink( $page->ID ) ); ?>">
							<button type="submit" name="aiagent_add_url" class="button button-small">
								+ <?php echo esc_html( $page->post_title ); ?>
							</button>
						</form>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get knowledge base instance.
	 *
	 * @return KnowledgeBase The knowledge base instance.
	 */
	public function get_knowledge_base() {
		$kb = new KnowledgeBase();

		if ( file_exists( $this->kb_file ) ) {
			$kb->load( $this->kb_file );
		}

		return $kb;
	}

	/**
	 * Save knowledge base.
	 *
	 * @param KnowledgeBase $kb The knowledge base instance to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_knowledge_base( KnowledgeBase $kb ) {
		$dir = dirname( $this->kb_file );
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $kb->save( $this->kb_file );
	}

	/**
	 * Add URL to knowledge base.
	 */
	private function add_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render_admin_page.
		$url = isset( $_POST['kb_url'] ) ? esc_url_raw( wp_unslash( $_POST['kb_url'] ) ) : '';

		if ( empty( $url ) ) {
			$this->show_notice( __( 'Please enter a valid URL.', 'ai-agent-for-website' ), 'error' );
			return;
		}

		$kb     = $this->get_knowledge_base();
		$result = $kb->addUrl( $url );

		if ( $result['success'] ) {
			$this->save_knowledge_base( $kb );
			$title = $result['title'] ?? __( 'Untitled', 'ai-agent-for-website' );
			/* translators: %s: Title of the added content */
			$this->show_notice( sprintf( __( 'Added: %s', 'ai-agent-for-website' ), $title ), 'success' );
		} else {
			$this->show_notice( $result['error'] ?? __( 'Failed to fetch URL.', 'ai-agent-for-website' ), 'error' );
		}
	}

	/**
	 * Add text to knowledge base.
	 */
	private function add_text() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render_admin_page.
		$text = isset( $_POST['kb_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['kb_text'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render_admin_page.
		$title = isset( $_POST['kb_title'] ) ? sanitize_text_field( wp_unslash( $_POST['kb_title'] ) ) : '';

		if ( empty( $text ) ) {
			$this->show_notice( __( 'Please enter some text.', 'ai-agent-for-website' ), 'error' );
			return;
		}

		$kb          = $this->get_knowledge_base();
		$source      = 'manual-entry-' . time();
		$title_param = ! empty( $title ) ? $title : null;
		$result      = $kb->addText( $text, $source, $title_param );

		if ( $result ) {
			$this->save_knowledge_base( $kb );
			$this->show_notice( __( 'Text added to knowledge base.', 'ai-agent-for-website' ), 'success' );
		} else {
			$this->show_notice( __( 'Failed to add text.', 'ai-agent-for-website' ), 'error' );
		}
	}

	/**
	 * Delete a document.
	 *
	 * @param int $index The index of the document to delete.
	 */
	private function delete_document( $index ) {
		$kb = $this->get_knowledge_base();

		if ( $kb->remove( $index ) ) {
			$this->save_knowledge_base( $kb );
			$this->show_notice( __( 'Document deleted.', 'ai-agent-for-website' ), 'success' );
		}
	}

	/**
	 * Clear all knowledge.
	 */
	private function clear_knowledge() {
		$kb = $this->get_knowledge_base();
		$kb->clear();
		$this->save_knowledge_base( $kb );
		$this->show_notice( __( 'Knowledge base cleared.', 'ai-agent-for-website' ), 'success' );
	}

	/**
	 * Show admin notice.
	 *
	 * @param string $message The message to display.
	 * @param string $type    The type of notice (success, error, warning, info).
	 */
	private function show_notice( $message, $type = 'success' ) {
		add_settings_error( 'aiagent_kb_messages', 'aiagent_kb_message', $message, $type );
		settings_errors( 'aiagent_kb_messages' );
	}
}
