<?php
/**
 * Calendly Integration Class
 *
 * Handles Calendly scheduling integration for the chat widget.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Calendly_Integration
 *
 * Manages Calendly scheduling widget and API integration.
 */
class AIAGENT_Calendly_Integration {

	/**
	 * Option name for storing Calendly settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'aiagent_calendly_settings';

	/**
	 * Option name for storing OAuth tokens.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'aiagent_calendly_tokens';

	/**
	 * Calendly API URLs.
	 */
	const API_BASE_URL    = 'https://api.calendly.com';
	const OAUTH_AUTH_URL  = 'https://auth.calendly.com/oauth/authorize';
	const OAUTH_TOKEN_URL = 'https://auth.calendly.com/oauth/token';

	/**
	 * Get Calendly settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		return get_option(
			self::OPTION_NAME,
			array(
				'enabled'            => false,
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- This is documentation, not commented code.
				'integration_type'   => 'embed', // Options: embed, popup, link, or api.
				'scheduling_url'     => '',
				'client_id'          => '',
				'client_secret'      => '',
				'prompt_after_chat'  => true,
				'prompt_message'     => 'Would you like to schedule a call with us?',
				'button_text'        => 'Schedule a Meeting',
				'embed_height'       => '630',
				'hide_event_details' => false,
				'hide_gdpr_banner'   => false,
				'primary_color'      => '',
				'text_color'         => '',
				'background_color'   => '',
			)
		);
	}

	/**
	 * Update Calendly settings.
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
	 * Check if connected to Calendly API.
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
	 * @return bool True if scheduling URL is set.
	 */
	public static function is_configured() {
		$settings = self::get_settings();
		return ! empty( $settings['scheduling_url'] );
	}

	/**
	 * Check if integration is enabled and ready.
	 *
	 * @return bool True if enabled and configured.
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] ) && self::is_configured();
	}

	/**
	 * Parse Calendly URL to extract username/event type.
	 *
	 * @param string $url Calendly URL.
	 * @return array Parsed URL components.
	 */
	public static function parse_calendly_url( $url ) {
		$parsed = wp_parse_url( $url );

		// Check if it's a Calendly URL.
		if ( empty( $parsed['host'] ) || false === strpos( $parsed['host'], 'calendly.com' ) ) {
			return array(
				'valid'    => false,
				'username' => '',
				'event'    => '',
			);
		}

		$path  = trim( $parsed['path'] ?? '', '/' );
		$parts = explode( '/', $path );

		return array(
			'valid'    => true,
			'username' => $parts[0] ?? '',
			'event'    => $parts[1] ?? '',
			'full_url' => $url,
		);
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @return string|WP_Error Authorization URL or error.
	 */
	public function get_auth_url() {
		$settings = self::get_settings();

		if ( empty( $settings['client_id'] ) ) {
			return new WP_Error( 'not_configured', __( 'Calendly Client ID is not configured.', 'ai-agent-for-website' ) );
		}

		$redirect_uri = $this->get_redirect_uri();
		$state        = wp_create_nonce( 'aiagent_calendly_oauth' );

		// Store state for verification.
		set_transient( 'aiagent_calendly_oauth_state', $state, 600 );

		$params = array(
			'client_id'     => $settings['client_id'],
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'state'         => $state,
		);

		return self::OAUTH_AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string Redirect URI.
	 */
	public function get_redirect_uri() {
		return admin_url( 'admin.php?page=ai-agent-settings&tab=integrations&calendly_callback=1' );
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
		$stored_state = get_transient( 'aiagent_calendly_oauth_state' );
		if ( ! $stored_state || $state !== $stored_state ) {
			return new WP_Error( 'invalid_state', __( 'Invalid OAuth state. Please try again.', 'ai-agent-for-website' ) );
		}

		delete_transient( 'aiagent_calendly_oauth_state' );

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

		return array(
			'success'   => true,
			'user_info' => $user_info,
		);
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
			array(
				'body' => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $this->get_redirect_uri(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_desc = $body['error_description'] ?? $body['error'];
			return new WP_Error( 'oauth_error', $error_desc );
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
			array(
				'body' => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'refresh_token' => $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_desc = $body['error_description'] ?? $body['error'];
			return new WP_Error( 'refresh_error', $error_desc );
		}

		// Preserve refresh token if not returned.
		if ( empty( $body['refresh_token'] ) ) {
			$body['refresh_token'] = $tokens['refresh_token'];
		}

		// Preserve user info.
		$body['user_info']  = isset( $tokens['user_info'] ) ? $tokens['user_info'] : null;
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
			return new WP_Error( 'not_connected', __( 'Not connected to Calendly.', 'ai-agent-for-website' ) );
		}

		// Check if token is expired (with 5 min buffer).
		$created_at = isset( $tokens['created_at'] ) ? $tokens['created_at'] : 0;
		$expires_in = isset( $tokens['expires_in'] ) ? $tokens['expires_in'] : 7200;
		$expires_at = $created_at + $expires_in - 300;

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
	 * Fetch user info from Calendly.
	 *
	 * @param string $access_token Access token.
	 * @return array|WP_Error User info or error.
	 */
	private function fetch_user_info( $access_token ) {
		$response = wp_remote_get(
			self::API_BASE_URL . '/users/me',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) || empty( $body['resource'] ) ) {
			$error_msg = isset( $body['message'] ) ? $body['message'] : 'Failed to get user info';
			return new WP_Error( 'userinfo_error', $error_msg );
		}

		$user = $body['resource'];

		return array(
			'uri'                  => $user['uri'] ?? '',
			'name'                 => $user['name'] ?? '',
			'email'                => $user['email'] ?? '',
			'scheduling_url'       => $user['scheduling_url'] ?? '',
			'avatar_url'           => $user['avatar_url'] ?? '',
			'current_organization' => $user['current_organization'] ?? '',
		);
	}

	/**
	 * Get event types from Calendly API.
	 *
	 * @return array|WP_Error Event types or error.
	 */
	public function get_event_types() {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$user_info = self::get_connected_user();
		if ( empty( $user_info['uri'] ) ) {
			return new WP_Error( 'no_user', __( 'User info not available.', 'ai-agent-for-website' ) );
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/event_types?user=' . rawurlencode( $user_info['uri'] ) . '&active=true',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) || ! isset( $body['collection'] ) ) {
			$error_msg = isset( $body['message'] ) ? $body['message'] : 'Failed to get event types';
			return new WP_Error( 'event_types_error', $error_msg );
		}

		$event_types = array();
		foreach ( $body['collection'] as $event ) {
			$event_types[] = array(
				'uri'            => $event['uri'] ?? '',
				'name'           => $event['name'] ?? '',
				'slug'           => $event['slug'] ?? '',
				'scheduling_url' => $event['scheduling_url'] ?? '',
				'duration'       => $event['duration'] ?? 30,
				'color'          => $event['color'] ?? '#006bff',
				'description'    => $event['description_plain'] ?? '',
			);
		}

		return $event_types;
	}

	/**
	 * Generate embed code for Calendly widget.
	 *
	 * @param string $url      Calendly URL.
	 * @param array  $options  Widget options.
	 * @return string Embed HTML.
	 */
	public static function get_embed_code( $url, $options = array() ) {
		$settings = self::get_settings();

		$height           = isset( $options['height'] ) ? $options['height'] : ( $settings['embed_height'] ? $settings['embed_height'] : '630' );
		$hide_details     = isset( $options['hide_event_details'] ) ? $options['hide_event_details'] : $settings['hide_event_details'];
		$hide_gdpr        = isset( $options['hide_gdpr_banner'] ) ? $options['hide_gdpr_banner'] : $settings['hide_gdpr_banner'];
		$primary_color    = isset( $options['primary_color'] ) ? $options['primary_color'] : $settings['primary_color'];
		$text_color       = isset( $options['text_color'] ) ? $options['text_color'] : $settings['text_color'];
		$background_color = isset( $options['background_color'] ) ? $options['background_color'] : $settings['background_color'];

		// Build query params.
		$params = array();
		if ( $hide_details ) {
			$params['hide_event_type_details'] = '1';
		}
		if ( $hide_gdpr ) {
			$params['hide_gdpr_banner'] = '1';
		}
		if ( ! empty( $primary_color ) ) {
			$params['primary_color'] = ltrim( $primary_color, '#' );
		}
		if ( ! empty( $text_color ) ) {
			$params['text_color'] = ltrim( $text_color, '#' );
		}
		if ( ! empty( $background_color ) ) {
			$params['background_color'] = ltrim( $background_color, '#' );
		}

		$embed_url = $url;
		if ( ! empty( $params ) ) {
			$embed_url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $params );
		}

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Calendly embed requires inline script loading.
		return sprintf(
			'<div class="calendly-inline-widget" data-url="%s" style="min-width:320px;height:%spx;"></div>
			<script type="text/javascript" src="https://assets.calendly.com/assets/external/widget.js" async></script>',
			esc_url( $embed_url ),
			esc_attr( $height )
		);
		// phpcs:enable
	}

	/**
	 * Get popup widget code for Calendly.
	 *
	 * @param string $url         Calendly URL.
	 * @param string $button_text Button text.
	 * @param array  $options     Widget options.
	 * @return string Popup HTML.
	 */
	public static function get_popup_code( $url, $button_text = 'Schedule a Meeting', $options = array() ) {
		$settings = self::get_settings();

		$primary_color = isset( $options['primary_color'] ) ? $options['primary_color'] : $settings['primary_color'];

		$button_style = '';
		if ( ! empty( $primary_color ) ) {
			$button_style = sprintf( 'background-color: %s;', esc_attr( $primary_color ) );
		}

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet, WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Calendly widget requires inline resource loading.
		return sprintf(
			'<link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">
			<script src="https://assets.calendly.com/assets/external/widget.js" type="text/javascript" async></script>
			<button class="aiagent-calendly-popup-btn" style="%s" onclick="Calendly.initPopupWidget({url: \'%s\'});return false;">%s</button>',
			$button_style,
			esc_url( $url ),
			esc_html( $button_text )
		);
		// phpcs:enable
	}

	/**
	 * Get settings for frontend (public safe).
	 *
	 * @return array Frontend-safe settings.
	 */
	public static function get_frontend_settings() {
		$settings = self::get_settings();

		return array(
			'enabled'            => self::is_enabled(),
			'integration_type'   => isset( $settings['integration_type'] ) ? $settings['integration_type'] : 'embed',
			'scheduling_url'     => isset( $settings['scheduling_url'] ) ? $settings['scheduling_url'] : '',
			'prompt_after_chat'  => ! empty( $settings['prompt_after_chat'] ),
			'prompt_message'     => isset( $settings['prompt_message'] ) ? $settings['prompt_message'] : 'Would you like to schedule a call with us?',
			'button_text'        => isset( $settings['button_text'] ) ? $settings['button_text'] : 'Schedule a Meeting',
			'embed_height'       => isset( $settings['embed_height'] ) ? $settings['embed_height'] : '630',
			'hide_event_details' => ! empty( $settings['hide_event_details'] ),
			'hide_gdpr_banner'   => ! empty( $settings['hide_gdpr_banner'] ),
			'primary_color'      => isset( $settings['primary_color'] ) ? $settings['primary_color'] : '',
			'text_color'         => isset( $settings['text_color'] ) ? $settings['text_color'] : '',
			'background_color'   => isset( $settings['background_color'] ) ? $settings['background_color'] : '',
		);
	}
}
