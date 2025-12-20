<?php
/**
 * File Processor Class
 *
 * Handles file upload and text extraction for the knowledge base.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_File_Processor
 *
 * Processes uploaded files and extracts text content for the knowledge base.
 */
class AIAGENT_File_Processor {

	/**
	 * Supported file types and their MIME types.
	 *
	 * @var array
	 */
	private $supported_types = [
		'txt'  => [ 'text/plain' ],
		'csv'  => [ 'text/csv', 'application/csv', 'text/comma-separated-values' ],
		'md'   => [ 'text/markdown', 'text/x-markdown', 'text/plain' ],
		'pdf'  => [ 'application/pdf' ],
		'doc'  => [ 'application/msword' ],
		'docx' => [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ],
		'rtf'  => [ 'application/rtf', 'text/rtf' ],
	];

	/**
	 * Maximum file size in bytes (10MB default).
	 *
	 * @var int
	 */
	private $max_file_size = 10485760;

	/**
	 * Upload directory for processed files.
	 *
	 * @var string
	 */
	private $upload_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$wp_upload_dir    = wp_upload_dir();
		$this->upload_dir = $wp_upload_dir['basedir'] . '/ai-agent-files';

		// Create upload directory if it doesn't exist.
		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );

			// Add .htaccess for security.
			$htaccess = $this->upload_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Options -Indexes\n<FilesMatch '.*'>\n    Require all denied\n</FilesMatch>" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			// Add index.php for extra security.
			$index = $this->upload_dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}

	/**
	 * Get supported file extensions.
	 *
	 * @return array List of supported extensions.
	 */
	public function get_supported_extensions() {
		return array_keys( $this->supported_types );
	}

	/**
	 * Get maximum file size.
	 *
	 * @return int Maximum file size in bytes.
	 */
	public function get_max_file_size() {
		return $this->max_file_size;
	}

	/**
	 * Process an uploaded file.
	 *
	 * @param array $file The $_FILES array element.
	 * @return array Result with success status, content, and metadata.
	 */
	public function process_file( $file ) {
		// Validate file.
		$validation = $this->validate_file( $file );
		if ( ! $validation['valid'] ) {
			return [
				'success' => false,
				'error'   => $validation['error'],
			];
		}

		// Get file extension.
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		// Extract text content based on file type.
		$content = $this->extract_content( $file['tmp_name'], $extension );

		if ( is_wp_error( $content ) ) {
			return [
				'success' => false,
				'error'   => $content->get_error_message(),
			];
		}

		if ( empty( trim( $content ) ) ) {
			return [
				'success' => false,
				'error'   => __( 'No text content could be extracted from the file.', 'ai-agent-for-website' ),
			];
		}

		// Generate a unique filename and save a copy.
		$unique_name = wp_unique_filename( $this->upload_dir, sanitize_file_name( $file['name'] ) );
		$saved_path  = $this->upload_dir . '/' . $unique_name;

		// Move uploaded file to our directory.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Suppressing move_uploaded_file errors, handling via return value.
		$moved = @move_uploaded_file( $file['tmp_name'], $saved_path );

		if ( ! $moved ) {
			// Try copy if move fails (for testing scenarios).
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Suppressing copy errors, handling via return value.
			$moved = @copy( $file['tmp_name'], $saved_path );
		}

		return [
			'success'       => true,
			'content'       => $content,
			'filename'      => $unique_name,
			'original_name' => $file['name'],
			'file_type'     => $extension,
			'file_size'     => $file['size'],
			'file_path'     => $moved ? $saved_path : null,
			'char_count'    => strlen( $content ),
		];
	}

	/**
	 * Validate an uploaded file.
	 *
	 * @param array $file The $_FILES array element.
	 * @return array Validation result with 'valid' and 'error' keys.
	 */
	private function validate_file( $file ) {
		// Check for upload errors.
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
			$error_message = $this->get_upload_error_message( $file['error'] ?? UPLOAD_ERR_NO_FILE );
			return [
				'valid' => false,
				'error' => $error_message,
			];
		}

		// Check file size.
		if ( $file['size'] > $this->max_file_size ) {
			return [
				'valid' => false,
				/* translators: %s: Maximum file size in MB */
				'error' => sprintf( __( 'File size exceeds the maximum limit of %s MB.', 'ai-agent-for-website' ), round( $this->max_file_size / 1048576, 1 ) ),
			];
		}

		// Check file extension.
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! array_key_exists( $extension, $this->supported_types ) ) {
			return [
				'valid' => false,
				/* translators: %s: List of supported file extensions */
				'error' => sprintf( __( 'Unsupported file type. Supported types: %s', 'ai-agent-for-website' ), implode( ', ', array_keys( $this->supported_types ) ) ),
			];
		}

		// Verify MIME type.
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		$allowed_mimes = $this->supported_types[ $extension ];
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			// Allow text/plain for many text-based formats.
			if ( 'text/plain' !== $mime_type && ! in_array( $extension, [ 'txt', 'csv', 'md' ], true ) ) {
				return [
					'valid' => false,
					'error' => __( 'File MIME type does not match the expected type for this extension.', 'ai-agent-for-website' ),
				];
			}
		}

		return [ 'valid' => true ];
	}

	/**
	 * Extract text content from a file.
	 *
	 * @param string $file_path Path to the file.
	 * @param string $extension File extension.
	 * @return string|WP_Error Extracted text content or error.
	 */
	private function extract_content( $file_path, $extension ) {
		switch ( $extension ) {
			case 'txt':
			case 'md':
				return $this->extract_plain_text( $file_path );

			case 'csv':
				return $this->extract_csv( $file_path );

			case 'pdf':
				return $this->extract_pdf( $file_path );

			case 'doc':
				return $this->extract_doc( $file_path );

			case 'docx':
				return $this->extract_docx( $file_path );

			case 'rtf':
				return $this->extract_rtf( $file_path );

			default:
				return new WP_Error( 'unsupported_type', __( 'Unsupported file type.', 'ai-agent-for-website' ) );
		}
	}

	/**
	 * Extract text from plain text files.
	 *
	 * @param string $file_path Path to the file.
	 * @return string|WP_Error Extracted text content or error.
	 */
	private function extract_plain_text( $file_path ) {
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Could not read the file.', 'ai-agent-for-website' ) );
		}

		// Convert encoding if needed.
		$encoding = mb_detect_encoding( $content, [ 'UTF-8', 'ISO-8859-1', 'Windows-1252' ], true );
		if ( $encoding && 'UTF-8' !== $encoding ) {
			$content = mb_convert_encoding( $content, 'UTF-8', $encoding );
		}

		return $this->clean_text( $content );
	}

	/**
	 * Extract text from CSV files.
	 *
	 * @param string $file_path Path to the file.
	 * @return string|WP_Error Extracted text content or error.
	 */
	private function extract_csv( $file_path ) {
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return new WP_Error( 'read_error', __( 'Could not read the CSV file.', 'ai-agent-for-website' ) );
		}

		$content = [];
		$headers = null;

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard pattern for reading CSV rows.
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( null === $headers ) {
				$headers = $row;
				continue;
			}

			// Create readable row with headers.
			$row_text = [];
			foreach ( $row as $index => $value ) {
				$header     = isset( $headers[ $index ] ) ? $headers[ $index ] : "Column $index";
				$row_text[] = "$header: $value";
			}
			$content[] = implode( ', ', $row_text );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $this->clean_text( implode( "\n", $content ) );
	}

	/**
	 * Extract text from PDF files.
	 *
	 * @param string $file_path Path to the file.
	 * @return string|WP_Error Extracted text content or error.
	 */
	private function extract_pdf( $file_path ) {
		// Try to use pdftotext command if available (Linux/Mac).
		$pdftotext_path = $this->find_pdftotext();

		if ( $pdftotext_path ) {
			$output_file = tempnam( sys_get_temp_dir(), 'pdf_' );
			$command     = sprintf(
				'%s -layout %s %s 2>&1',
				escapeshellcmd( $pdftotext_path ),
				escapeshellarg( $file_path ),
				escapeshellarg( $output_file )
			);

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $command, $output, $return_var );

			if ( 0 === $return_var && file_exists( $output_file ) ) {
				$content = file_get_contents( $output_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				unlink( $output_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

				if ( ! empty( trim( $content ) ) ) {
					return $this->clean_text( $content );
				}
			}

			if ( file_exists( $output_file ) ) {
				unlink( $output_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		// Fallback: Basic PDF text extraction (limited functionality).
		$content = $this->extract_pdf_basic( $file_path );

		if ( ! empty( trim( $content ) ) ) {
			return $this->clean_text( $content );
		}

		return new WP_Error(
			'pdf_extraction_failed',
			__( 'Could not extract text from PDF. The PDF may be image-based or encrypted. For best results, ensure pdftotext is installed on your server.', 'ai-agent-for-website' )
		);
	}

	/**
	 * Find pdftotext binary path.
	 *
	 * @return string|false Path to pdftotext or false if not found.
	 */
	private function find_pdftotext() {
		$paths = [
			'/usr/bin/pdftotext',
			'/usr/local/bin/pdftotext',
			'/opt/homebrew/bin/pdftotext',
		];

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}

		// Try which command.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( 'which pdftotext 2>/dev/null', $output, $return_var );
		if ( 0 === $return_var && ! empty( $output[0] ) ) {
			return trim( $output[0] );
		}

		return false;
	}

	/**
	 * Basic PDF text extraction without external tools.
	 *
	 * @param string $file_path Path to the PDF file.
	 * @return string Extracted text (may be incomplete).
	 */
	private function extract_pdf_basic( $file_path ) {
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return '';
		}

		$text = '';

		// Try to find text between stream objects.
		if ( preg_match_all( '/stream\s*\n(.+?)\nendstream/s', $content, $matches ) ) {
			foreach ( $matches[1] as $stream ) {
				// Try to decompress if it's compressed.
				$decompressed = @gzuncompress( $stream ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false !== $decompressed ) {
					$stream = $decompressed;
				}

				// Extract text from the stream.
				if ( preg_match_all( '/\(([^)]+)\)/', $stream, $text_matches ) ) {
					$text .= implode( ' ', $text_matches[1] ) . "\n";
				}

				// Try BT/ET text blocks.
				if ( preg_match_all( '/BT\s*(.+?)\s*ET/s', $stream, $bt_matches ) ) {
					foreach ( $bt_matches[1] as $block ) {
						if ( preg_match_all( '/\[?\(([^)]+)\)\]?\s*Tj/s', $block, $tj_matches ) ) {
							$text .= implode( '', $tj_matches[1] ) . ' ';
						}
					}
				}
			}
		}

		return $text;
	}

	/**
	 * Extract text from old .doc files.
	 *
	 * @param string $file_path Path to the file.
	 * @return string|WP_Error Extracted text content or error.
	 */
	private function extract_doc( $file_path ) {
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Could not read the DOC file.', 'ai-agent-for-website' ) );
		}

		// Basic extraction - strip binary content and extract readable text.
		$text = '';

		// Remove null bytes and control characters, keep printable ASCII and common extended chars.
		$content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $content );

		// Extract sequences of readable characters.
		if ( preg_match_all( '/[\x20-\x7E\xA0-\xFF]{4,}/', $content, $matches ) ) {
			$text = implode( ' ', $matches[0] );
		}

		if ( empty( trim( $text ) ) ) {
			return new WP_Error(
				'doc_extraction_failed',
				__( 'Could not extract text from DOC file. Consider converting to DOCX format for better compatibility.', 'ai-agent-for-website' )
			);
		}

		return $this->clean_text( $text );
	}

	/**
	 * Extract text from DOCX files.
	 *
	 * @param string $file_path Path to the file.
	 * @return string|WP_Error Extracted text content or error.
	 */
	private function extract_docx( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_not_available', __( 'ZipArchive extension is required to process DOCX files.', 'ai-agent-for-website' ) );
		}

		$zip    = new ZipArchive();
		$result = $zip->open( $file_path );

		if ( true !== $result ) {
			return new WP_Error( 'zip_open_failed', __( 'Could not open the DOCX file.', 'ai-agent-for-website' ) );
		}

		// Read the main document content.
		$xml_content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml_content ) {
			return new WP_Error( 'docx_read_failed', __( 'Could not read content from DOCX file.', 'ai-agent-for-website' ) );
		}

		// Parse XML and extract text.
		$text = $this->extract_text_from_xml( $xml_content );

		if ( empty( trim( $text ) ) ) {
			return new WP_Error( 'docx_empty', __( 'No text content found in the DOCX file.', 'ai-agent-for-website' ) );
		}

		return $this->clean_text( $text );
	}

	/**
	 * Extract text from XML content (used for DOCX).
	 *
	 * @param string $xml_content XML string.
	 * @return string Extracted text.
	 */
	private function extract_text_from_xml( $xml_content ) {
		// Strip XML tags and decode entities.
		$text = wp_strip_all_tags( $xml_content );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );

		// Alternative: Parse properly with SimpleXML.
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_content );

		if ( false !== $xml ) {
			// Register namespace.
			$xml->registerXPathNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

			// Extract all text elements.
			$text_elements = $xml->xpath( '//w:t' );
			$paragraphs    = [];
			$current_para  = '';

			foreach ( $text_elements as $element ) {
				$current_para .= (string) $element;
			}

			if ( ! empty( $current_para ) ) {
				$text = $current_para;
			}
		}

		libxml_clear_errors();

		return $text;
	}

	/**
	 * Extract text from RTF files.
	 *
	 * @param string $file_path Path to the file.
	 * @return string|WP_Error Extracted text content or error.
	 */
	private function extract_rtf( $file_path ) {
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Could not read the RTF file.', 'ai-agent-for-website' ) );
		}

		// Remove RTF control words and groups.
		$text = $content;

		// Remove header info.
		$text = preg_replace( '/^\{\\\\rtf1.*?\\\\deflang\d+\s*/s', '', $text );

		// Remove font tables, color tables, etc.
		$text = preg_replace( '/\{\\\\fonttbl[^}]*\}/s', '', $text );
		$text = preg_replace( '/\{\\\\colortbl[^}]*\}/s', '', $text );
		$text = preg_replace( '/\{\\\\stylesheet[^}]*\}/s', '', $text );

		// Remove control words.
		$text = preg_replace( '/\\\\[a-z]+\d*\s?/i', '', $text );

		// Remove remaining braces and backslashes.
		$text = str_replace( [ '{', '}', '\\' ], '', $text );

		// Clean up whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		return $this->clean_text( trim( $text ) );
	}

	/**
	 * Clean extracted text.
	 *
	 * @param string $text Raw text.
	 * @return string Cleaned text.
	 */
	private function clean_text( $text ) {
		// Normalize line endings.
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

		// Remove excessive whitespace.
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		// Trim each line.
		$lines = explode( "\n", $text );
		$lines = array_map( 'trim', $lines );
		$text  = implode( "\n", $lines );

		return trim( $text );
	}

	/**
	 * Get upload error message.
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( $error_code ) {
		$messages = [
			UPLOAD_ERR_INI_SIZE   => __( 'The file exceeds the maximum upload size allowed by the server.', 'ai-agent-for-website' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'The file exceeds the maximum size allowed.', 'ai-agent-for-website' ),
			UPLOAD_ERR_PARTIAL    => __( 'The file was only partially uploaded.', 'ai-agent-for-website' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'ai-agent-for-website' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Server configuration error: Missing temporary folder.', 'ai-agent-for-website' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Server error: Failed to write file to disk.', 'ai-agent-for-website' ),
			UPLOAD_ERR_EXTENSION  => __( 'File upload stopped by a server extension.', 'ai-agent-for-website' ),
		];

		return $messages[ $error_code ] ?? __( 'Unknown upload error.', 'ai-agent-for-website' );
	}

	/**
	 * Get list of uploaded files.
	 *
	 * @return array List of uploaded files with metadata.
	 */
	public function get_uploaded_files() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aiagent_uploaded_files';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$files = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM $table_name ORDER BY uploaded_at DESC",
			ARRAY_A
		);

		return $files ? $files : [];
	}

	/**
	 * Save file metadata to database.
	 *
	 * @param array $file_data File data from process_file().
	 * @param int   $kb_index  Knowledge base document index.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function save_file_record( $file_data, $kb_index = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aiagent_uploaded_files';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_name,
			[
				'filename'          => $file_data['filename'],
				'original_name'     => $file_data['original_name'],
				'file_type'         => $file_data['file_type'],
				'file_size'         => $file_data['file_size'],
				'file_path'         => $file_data['file_path'] ?? '',
				'kb_document_index' => $kb_index,
			],
			[ '%s', '%s', '%s', '%d', '%s', '%d' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Delete a file record and its physical file.
	 *
	 * @param int $file_id The file record ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_file( $file_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aiagent_uploaded_files';

		// Get file info first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$file = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM $table_name WHERE id = %d",
				$file_id
			),
			ARRAY_A
		);

		if ( ! $file ) {
			return false;
		}

		// Delete physical file if it exists.
		if ( ! empty( $file['file_path'] ) && file_exists( $file['file_path'] ) ) {
			unlink( $file['file_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		// Delete database record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$table_name,
			[ 'id' => $file_id ],
			[ '%d' ]
		);

		return (bool) $deleted;
	}
}
