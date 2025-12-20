<?php
/**
 * Confluence Integration Class
 *
 * Handles Confluence API authentication and content import for the knowledge base.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Confluence_Integration
 *
 * Manages Confluence API authentication and page import.
 */
class AIAGENT_Confluence_Integration {

	/**
	 * Option name for storing Confluence settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'aiagent_confluence_settings';

	/**
	 * Get Confluence settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		return get_option(
			self::OPTION_NAME,
			[
				'instance_url' => '',
				'email'        => '',
				'api_token'    => '',
			]
		);
	}

	/**
	 * Update Confluence settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool True on success.
	 */
	public static function update_settings( $settings ) {
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Check if connected to Confluence.
	 *
	 * @return bool True if credentials are configured.
	 */
	public static function is_connected() {
		$settings = self::get_settings();
		return ! empty( $settings['instance_url'] )
			&& ! empty( $settings['email'] )
			&& ! empty( $settings['api_token'] );
	}

	/**
	 * Get the base API URL.
	 *
	 * @return string API base URL.
	 */
	private function get_api_url() {
		$settings = self::get_settings();
		$base_url = rtrim( $settings['instance_url'], '/' );

		// Handle both cloud and server instances.
		if ( strpos( $base_url, 'atlassian.net' ) !== false ) {
			return $base_url . '/wiki/api/v2';
		}

		return $base_url . '/rest/api';
	}

	/**
	 * Get authorization header for API requests.
	 *
	 * @return string Authorization header value.
	 */
	private function get_auth_header() {
		$settings = self::get_settings();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for HTTP Basic Auth.
		return 'Basic ' . base64_encode( $settings['email'] . ':' . $settings['api_token'] );
	}

	/**
	 * Make an API request to Confluence.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body.
	 * @return array|WP_Error Response data or error.
	 */
	private function api_request( $endpoint, $method = 'GET', $body = null ) {
		$url = $this->get_api_url() . $endpoint;

		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => $this->get_auth_header(),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		if ( $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$error_message = isset( $body['message'] ) ? $body['message'] : 'API request failed';
			return new WP_Error( 'api_error', $error_message, [ 'status' => $status_code ] );
		}

		return $body;
	}

	/**
	 * Test the Confluence connection.
	 *
	 * @return array|WP_Error User info on success or error.
	 */
	public function test_connection() {
		if ( ! self::is_connected() ) {
			return new WP_Error( 'not_configured', __( 'Confluence is not configured.', 'ai-agent-for-website' ) );
		}

		// Try to get current user info.
		$response = $this->api_request( '/user/current' );

		if ( is_wp_error( $response ) ) {
			// Try V1 API for older instances.
			$response = $this->api_request_v1( '/user/current' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return [
			'success' => true,
			'user'    => [
				'name'  => $response['displayName'] ?? $response['name'] ?? 'Unknown',
				'email' => $response['email'] ?? '',
			],
		];
	}

	/**
	 * Make a V1 API request for older Confluence instances.
	 *
	 * @param string $endpoint API endpoint.
	 * @return array|WP_Error Response data or error.
	 */
	private function api_request_v1( $endpoint ) {
		$settings = self::get_settings();
		$base_url = rtrim( $settings['instance_url'], '/' );
		$url      = $base_url . '/rest/api' . $endpoint;

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => $this->get_auth_header(),
					'Accept'        => 'application/json',
				],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$error_message = isset( $body['message'] ) ? $body['message'] : 'API request failed';
			return new WP_Error( 'api_error', $error_message );
		}

		return $body;
	}

	/**
	 * Get all spaces.
	 *
	 * @return array|WP_Error Spaces list or error.
	 */
	public function get_spaces() {
		// Try V2 API first (Cloud).
		$response = $this->api_request( '/spaces?limit=50' );

		if ( is_wp_error( $response ) ) {
			// Fallback to V1 API.
			$response = $this->api_request_v1( '/space?limit=50' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Normalize response format.
		$spaces = $response['results'] ?? [];

		return array_map(
			function ( $space ) {
				return [
					'id'   => $space['id'] ?? '',
					'key'  => $space['key'] ?? '',
					'name' => $space['name'] ?? '',
					'type' => $space['type'] ?? 'global',
				];
			},
			$spaces
		);
	}

	/**
	 * Get pages in a space.
	 *
	 * @param string $space_key Space key.
	 * @param int    $limit     Maximum number of pages.
	 * @return array|WP_Error Pages list or error.
	 */
	public function get_pages( $space_key, $limit = 50 ) {
		// Try V2 API first.
		$response = $this->api_request( "/spaces/{$space_key}/pages?limit={$limit}" );

		if ( is_wp_error( $response ) ) {
			// Fallback to V1 API.
			$response = $this->api_request_v1( "/content?spaceKey={$space_key}&type=page&limit={$limit}&expand=ancestors" );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$pages = $response['results'] ?? [];

		return array_map(
			function ( $page ) {
				return [
					'id'       => $page['id'] ?? '',
					'title'    => $page['title'] ?? '',
					'status'   => $page['status'] ?? 'current',
					'parentId' => $this->get_parent_id( $page ),
				];
			},
			$pages
		);
	}

	/**
	 * Get parent ID from page data.
	 *
	 * @param array $page Page data.
	 * @return string|null Parent ID or null.
	 */
	private function get_parent_id( $page ) {
		if ( isset( $page['parentId'] ) ) {
			return $page['parentId'];
		}
		if ( isset( $page['ancestors'] ) && ! empty( $page['ancestors'] ) ) {
			$parent = end( $page['ancestors'] );
			return $parent['id'] ?? null;
		}
		return null;
	}

	/**
	 * Get page content.
	 *
	 * @param string $page_id Page ID.
	 * @return array|WP_Error Page content or error.
	 */
	public function get_page_content( $page_id ) {
		// Try V2 API first.
		$response = $this->api_request( "/pages/{$page_id}?body-format=storage" );

		if ( is_wp_error( $response ) ) {
			// Fallback to V1 API.
			$response = $this->api_request_v1( "/content/{$page_id}?expand=body.storage,space" );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$title = $response['title'] ?? 'Untitled';

		// Get body content - handle different API versions.
		$body_html = '';
		if ( isset( $response['body']['storage']['value'] ) ) {
			$body_html = $response['body']['storage']['value'];
		} elseif ( isset( $response['body']['value'] ) ) {
			$body_html = $response['body']['value'];
		}

		// Convert HTML to plain text.
		$content = $this->html_to_text( $body_html );

		if ( empty( trim( $content ) ) ) {
			return new WP_Error( 'empty_content', __( 'No content found in this page.', 'ai-agent-for-website' ) );
		}

		$space_key = $response['space']['key'] ?? $response['spaceId'] ?? '';

		return [
			'id'        => $page_id,
			'title'     => $title,
			'space_key' => $space_key,
			'content'   => $content,
		];
	}

	/**
	 * Convert Confluence HTML to plain text.
	 *
	 * @param string $html HTML content.
	 * @return string Plain text.
	 */
	private function html_to_text( $html ) {
		// Remove Confluence macros.
		$html = preg_replace( '/<ac:[^>]+>[^<]*<\/ac:[^>]+>/s', '', $html );
		$html = preg_replace( '/<ac:[^>]+\/>/s', '', $html );

		// Remove ri: tags (resource identifiers).
		$html = preg_replace( '/<ri:[^>]+>[^<]*<\/ri:[^>]+>/s', '', $html );
		$html = preg_replace( '/<ri:[^>]+\/>/s', '', $html );

		// Convert common elements.
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$html = preg_replace( '/<\/p>/i', "\n\n", $html );
		$html = preg_replace( '/<\/li>/i', "\n", $html );
		$html = preg_replace( '/<\/tr>/i', "\n", $html );
		$html = preg_replace( '/<td[^>]*>/i', ' | ', $html );
		$html = preg_replace( '/<h[1-6][^>]*>/i', "\n\n", $html );
		$html = preg_replace( '/<\/h[1-6]>/i', "\n", $html );

		// Strip remaining HTML tags.
		$text = wp_strip_all_tags( $html );

		// Decode HTML entities.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Clean up whitespace.
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Search pages in Confluence.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum results.
	 * @return array|WP_Error Search results or error.
	 */
	public function search_pages( $query, $limit = 20 ) {
		$cql = 'type=page AND text~"' . addslashes( $query ) . '"';

		// Try V2 API.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Required for API URL encoding.
		$response = $this->api_request( '/search?cql=' . urlencode( $cql ) . '&limit=' . $limit );

		if ( is_wp_error( $response ) ) {
			// Fallback to V1 API.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Required for API URL encoding.
			$response = $this->api_request_v1( '/content/search?cql=' . urlencode( $cql ) . '&limit=' . $limit );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$results = $response['results'] ?? [];

		return array_map(
			function ( $result ) {
				$content = $result['content'] ?? $result;
				return [
					'id'       => $content['id'] ?? '',
					'title'    => $content['title'] ?? '',
					'spaceKey' => $content['space']['key'] ?? '',
					'excerpt'  => $result['excerpt'] ?? '',
				];
			},
			$results
		);
	}

	/**
	 * Import a page to the knowledge base.
	 *
	 * @param string $page_id Confluence page ID.
	 * @return array|WP_Error Result or error.
	 */
	public function import_to_knowledge_base( $page_id ) {
		$page_data = $this->get_page_content( $page_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Add to knowledge base.
		$knowledge_manager = new AIAGENT_Knowledge_Manager();
		$kb                = $knowledge_manager->get_knowledge_base();

		$source = 'confluence-' . $page_id;
		$title  = $page_data['title'];

		$result = $kb->addText( $page_data['content'], $source, $title );

		if ( ! $result ) {
			return new WP_Error( 'kb_add_failed', __( 'Failed to add content to knowledge base.', 'ai-agent-for-website' ) );
		}

		$knowledge_manager->save_knowledge_base( $kb );

		return [
			'success'    => true,
			'title'      => $title,
			'char_count' => strlen( $page_data['content'] ),
		];
	}

	/**
	 * Import multiple pages to the knowledge base.
	 *
	 * @param array $page_ids Array of page IDs.
	 * @return array Results for each page.
	 */
	public function import_multiple_pages( $page_ids ) {
		$results = [];

		foreach ( $page_ids as $page_id ) {
			$result = $this->import_to_knowledge_base( $page_id );

			$results[] = [
				'page_id' => $page_id,
				'success' => ! is_wp_error( $result ),
				'title'   => is_wp_error( $result ) ? '' : $result['title'],
				'error'   => is_wp_error( $result ) ? $result->get_error_message() : null,
			];
		}

		return $results;
	}

	/**
	 * Disconnect Confluence (clear settings).
	 *
	 * @return bool True on success.
	 */
	public static function disconnect() {
		return delete_option( self::OPTION_NAME );
	}
}
