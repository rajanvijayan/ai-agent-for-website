<?php
/**
 * Google Drive Integration Class
 *
 * Handles Google Drive OAuth and file import for the knowledge base.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Google_Drive_Integration
 *
 * Manages Google Drive OAuth authentication and document import.
 */
class AIAGENT_Google_Drive_Integration {

	/**
	 * Option name for storing Google Drive settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'aiagent_google_drive_settings';

	/**
	 * Option name for storing OAuth tokens.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'aiagent_google_drive_tokens';

	/**
	 * Google OAuth URLs.
	 */
	const OAUTH_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const DRIVE_API_URL   = 'https://www.googleapis.com/drive/v3';
	const USERINFO_URL    = 'https://www.googleapis.com/oauth2/v2/userinfo';

	/**
	 * Required OAuth scopes.
	 *
	 * @var array
	 */
	private $scopes = [
		'https://www.googleapis.com/auth/drive.readonly',
		'https://www.googleapis.com/auth/userinfo.email',
		'https://www.googleapis.com/auth/userinfo.profile',
	];

	/**
	 * Get Google Drive settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		return get_option(
			self::OPTION_NAME,
			[
				'client_id'     => '',
				'client_secret' => '',
			]
		);
	}

	/**
	 * Update Google Drive settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool True on success.
	 */
	public static function update_settings( $settings ) {
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Get stored OAuth tokens.
	 *
	 * @return array|null Tokens array or null if not connected.
	 */
	public static function get_tokens() {
		$tokens = get_option( self::TOKEN_OPTION );
		return $tokens ? $tokens : null;
	}

	/**
	 * Save OAuth tokens.
	 *
	 * @param array $tokens Tokens to save.
	 * @return bool True on success.
	 */
	public static function save_tokens( $tokens ) {
		return update_option( self::TOKEN_OPTION, $tokens );
	}

	/**
	 * Delete OAuth tokens (disconnect).
	 *
	 * @return bool True on success.
	 */
	public static function delete_tokens() {
		return delete_option( self::TOKEN_OPTION );
	}

	/**
	 * Check if connected to Google Drive.
	 *
	 * @return bool True if connected.
	 */
	public static function is_connected() {
		$tokens = self::get_tokens();
		return ! empty( $tokens['access_token'] );
	}

	/**
	 * Get connected user info.
	 *
	 * @return array|null User info or null.
	 */
	public static function get_connected_user() {
		$tokens = self::get_tokens();
		return isset( $tokens['user_info'] ) ? $tokens['user_info'] : null;
	}

	/**
	 * Check if integration is configured.
	 *
	 * @return bool True if client ID and secret are set.
	 */
	public static function is_configured() {
		$settings = self::get_settings();
		return ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] );
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @return string|WP_Error Authorization URL or error.
	 */
	public function get_auth_url() {
		$settings = self::get_settings();

		if ( empty( $settings['client_id'] ) ) {
			return new WP_Error( 'not_configured', __( 'Google Drive Client ID is not configured.', 'ai-agent-for-website' ) );
		}

		$redirect_uri = $this->get_redirect_uri();
		$state        = wp_create_nonce( 'aiagent_gdrive_oauth' );

		// Store state for verification.
		set_transient( 'aiagent_gdrive_oauth_state', $state, 600 );

		$params = [
			'client_id'     => $settings['client_id'],
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ' ', $this->scopes ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		];

		return self::OAUTH_AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string Redirect URI.
	 */
	public function get_redirect_uri() {
		return admin_url( 'admin.php?page=ai-agent-settings&tab=integrations&gdrive_callback=1' );
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State for verification.
	 * @return array|WP_Error Result array or error.
	 */
	public function handle_callback( $code, $state ) {
		// Verify state.
		$stored_state = get_transient( 'aiagent_gdrive_oauth_state' );
		if ( ! $stored_state || $state !== $stored_state ) {
			return new WP_Error( 'invalid_state', __( 'Invalid OAuth state. Please try again.', 'ai-agent-for-website' ) );
		}

		delete_transient( 'aiagent_gdrive_oauth_state' );

		// Exchange code for tokens.
		$tokens = $this->exchange_code( $code );

		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		// Get user info.
		$user_info = $this->fetch_user_info( $tokens['access_token'] );

		if ( is_wp_error( $user_info ) ) {
			return $user_info;
		}

		// Save tokens with user info.
		$tokens['user_info']  = $user_info;
		$tokens['created_at'] = time();
		self::save_tokens( $tokens );

		return [
			'success'   => true,
			'user_info' => $user_info,
		];
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error Tokens or error.
	 */
	private function exchange_code( $code ) {
		$settings = self::get_settings();

		$response = wp_remote_post(
			self::OAUTH_TOKEN_URL,
			[
				'body' => [
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $this->get_redirect_uri(),
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
		}

		return $body;
	}

	/**
	 * Refresh access token.
	 *
	 * @return array|WP_Error New tokens or error.
	 */
	public function refresh_access_token() {
		$tokens   = self::get_tokens();
		$settings = self::get_settings();

		if ( empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'no_refresh_token', __( 'No refresh token available. Please reconnect.', 'ai-agent-for-website' ) );
		}

		$response = wp_remote_post(
			self::OAUTH_TOKEN_URL,
			[
				'body' => [
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'refresh_token' => $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'refresh_error', $body['error_description'] ?? $body['error'] );
		}

		// Preserve refresh token if not returned.
		if ( empty( $body['refresh_token'] ) ) {
			$body['refresh_token'] = $tokens['refresh_token'];
		}

		// Preserve user info.
		$body['user_info']  = $tokens['user_info'] ?? null;
		$body['created_at'] = time();

		self::save_tokens( $body );

		return $body;
	}

	/**
	 * Get valid access token, refreshing if necessary.
	 *
	 * @return string|WP_Error Access token or error.
	 */
	public function get_access_token() {
		$tokens = self::get_tokens();

		if ( empty( $tokens['access_token'] ) ) {
			return new WP_Error( 'not_connected', __( 'Not connected to Google Drive.', 'ai-agent-for-website' ) );
		}

		// Check if token is expired (with 5 min buffer).
		$expires_at = ( $tokens['created_at'] ?? 0 ) + ( $tokens['expires_in'] ?? 3600 ) - 300;

		if ( time() > $expires_at ) {
			$new_tokens = $this->refresh_access_token();
			if ( is_wp_error( $new_tokens ) ) {
				return $new_tokens;
			}
			return $new_tokens['access_token'];
		}

		return $tokens['access_token'];
	}

	/**
	 * Fetch user info from Google.
	 *
	 * @param string $access_token Access token.
	 * @return array|WP_Error User info or error.
	 */
	private function fetch_user_info( $access_token ) {
		$response = wp_remote_get(
			self::USERINFO_URL,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'userinfo_error', $body['error']['message'] ?? 'Failed to get user info' );
		}

		return [
			'email'   => $body['email'] ?? '',
			'name'    => $body['name'] ?? '',
			'picture' => $body['picture'] ?? '',
		];
	}

	/**
	 * List files from Google Drive.
	 *
	 * @param string $query     Optional search query.
	 * @param string $folder_id Optional folder ID.
	 * @return array|WP_Error Files list or error.
	 */
	public function list_files( $query = '', $folder_id = '' ) {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Build query for text-based files.
		$q_parts = [
			"mimeType != 'application/vnd.google-apps.folder'",
			'trashed = false',
		];

		// Filter to supported file types.
		$supported_mimes = [
			'application/vnd.google-apps.document',
			'text/plain',
			'application/pdf',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'text/csv',
			'text/markdown',
		];

		$mime_query = [];
		foreach ( $supported_mimes as $mime ) {
			$mime_query[] = "mimeType = '$mime'";
		}
		$q_parts[] = '(' . implode( ' or ', $mime_query ) . ')';

		if ( ! empty( $folder_id ) ) {
			$q_parts[] = "'$folder_id' in parents";
		}

		if ( ! empty( $query ) ) {
			$q_parts[] = "name contains '" . addslashes( $query ) . "'";
		}

		$params = [
			'q'        => implode( ' and ', $q_parts ),
			'fields'   => 'files(id,name,mimeType,modifiedTime,size,iconLink)',
			'orderBy'  => 'modifiedTime desc',
			'pageSize' => 50,
		];

		$url = self::DRIVE_API_URL . '/files?' . http_build_query( $params );

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'drive_error', $body['error']['message'] ?? 'Failed to list files' );
		}

		return $body['files'] ?? [];
	}

	/**
	 * Get file content from Google Drive.
	 *
	 * @param string $file_id File ID.
	 * @return array|WP_Error File content and metadata or error.
	 */
	public function get_file_content( $file_id ) {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// First get file metadata.
		$metadata_url = self::DRIVE_API_URL . '/files/' . $file_id . '?fields=id,name,mimeType,size';

		$response = wp_remote_get(
			$metadata_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$metadata = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $metadata['error'] ) ) {
			return new WP_Error( 'metadata_error', $metadata['error']['message'] ?? 'Failed to get file metadata' );
		}

		// Determine how to export/download based on MIME type.
		$mime_type = $metadata['mimeType'];
		$content   = '';

		if ( strpos( $mime_type, 'application/vnd.google-apps' ) === 0 ) {
			// Google Docs format - need to export.
			$export_mime = 'text/plain';
			if ( 'application/vnd.google-apps.spreadsheet' === $mime_type ) {
				$export_mime = 'text/csv';
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Required for API URL encoding.
			$export_url = self::DRIVE_API_URL . '/files/' . $file_id . '/export?mimeType=' . urlencode( $export_mime );

			$response = wp_remote_get(
				$export_url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $access_token,
					],
					'timeout' => 30,
				]
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$content = wp_remote_retrieve_body( $response );
		} else {
			// Regular file - download.
			$download_url = self::DRIVE_API_URL . '/files/' . $file_id . '?alt=media';

			$response = wp_remote_get(
				$download_url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $access_token,
					],
					'timeout' => 30,
				]
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$content = wp_remote_retrieve_body( $response );

			// For PDF and DOCX, we need to process the content.
			if ( 'application/pdf' === $mime_type ) {
				$content = $this->extract_pdf_text( $content );
			} elseif ( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' === $mime_type ) {
				$content = $this->extract_docx_text( $content );
			}
		}

		if ( empty( trim( $content ) ) ) {
			return new WP_Error( 'empty_content', __( 'Could not extract text content from the file.', 'ai-agent-for-website' ) );
		}

		return [
			'id'       => $metadata['id'],
			'name'     => $metadata['name'],
			'mimeType' => $metadata['mimeType'],
			'content'  => $content,
		];
	}

	/**
	 * Extract text from PDF content.
	 *
	 * @param string $content PDF binary content.
	 * @return string Extracted text.
	 */
	private function extract_pdf_text( $content ) {
		// Save to temp file and use file processor.
		$temp_file = wp_tempnam( 'gdrive_pdf_' );
		file_put_contents( $temp_file, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$file_processor = new AIAGENT_File_Processor();
		$result         = $file_processor->process_file(
			[
				'name'     => 'document.pdf',
				'tmp_name' => $temp_file,
				'size'     => strlen( $content ),
				'error'    => UPLOAD_ERR_OK,
			]
		);

		unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		return $result['success'] ? $result['content'] : '';
	}

	/**
	 * Extract text from DOCX content.
	 *
	 * @param string $content DOCX binary content.
	 * @return string Extracted text.
	 */
	private function extract_docx_text( $content ) {
		// Save to temp file and use file processor.
		$temp_file = wp_tempnam( 'gdrive_docx_' );
		file_put_contents( $temp_file, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$file_processor = new AIAGENT_File_Processor();
		$result         = $file_processor->process_file(
			[
				'name'     => 'document.docx',
				'tmp_name' => $temp_file,
				'size'     => strlen( $content ),
				'error'    => UPLOAD_ERR_OK,
			]
		);

		unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		return $result['success'] ? $result['content'] : '';
	}

	/**
	 * Import a file to the knowledge base.
	 *
	 * @param string $file_id Google Drive file ID.
	 * @return array|WP_Error Result or error.
	 */
	public function import_to_knowledge_base( $file_id ) {
		$file_data = $this->get_file_content( $file_id );

		if ( is_wp_error( $file_data ) ) {
			return $file_data;
		}

		// Add to knowledge base.
		$knowledge_manager = new AIAGENT_Knowledge_Manager();
		$kb                = $knowledge_manager->get_knowledge_base();

		$source = 'google-drive-' . $file_id;
		$title  = $file_data['name'];

		$result = $kb->addText( $file_data['content'], $source, $title );

		if ( ! $result ) {
			return new WP_Error( 'kb_add_failed', __( 'Failed to add content to knowledge base.', 'ai-agent-for-website' ) );
		}

		$knowledge_manager->save_knowledge_base( $kb );

		return [
			'success'    => true,
			'title'      => $title,
			'char_count' => strlen( $file_data['content'] ),
		];
	}
}
