<?php
/**
 * Mailchimp Integration Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Mailchimp_Integration
 *
 * Handles Mailchimp integration for newsletter subscriptions.
 */
class AIAGENT_Mailchimp_Integration {

	/**
	 * Option key for storing settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'aiagent_integrations';

	/**
	 * Get integration settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		$defaults = [
			'mailchimp_enabled' => false,
			'mailchimp_api_key' => '',
			'mailchimp_list_id' => '',
			'mailchimp_server'  => '',
		];

		$settings = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update integration settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool True on success.
	 */
	public static function update_settings( $settings ) {
		$current = get_option( self::OPTION_KEY, [] );
		$merged  = array_merge( $current, $settings );
		return update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Check if Mailchimp is enabled and configured.
	 *
	 * @return bool True if enabled.
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['mailchimp_enabled'] )
			&& ! empty( $settings['mailchimp_api_key'] )
			&& ! empty( $settings['mailchimp_list_id'] );
	}

	/**
	 * Get the server prefix from API key.
	 *
	 * @param string $api_key The API key.
	 * @return string Server prefix.
	 */
	public static function get_server_from_api_key( $api_key ) {
		if ( strpos( $api_key, '-' ) !== false ) {
			$parts = explode( '-', $api_key );
			return end( $parts );
		}
		return 'us1';
	}

	/**
	 * Subscribe an email to the Mailchimp list.
	 *
	 * @param string $email      Email address.
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param array  $tags       Optional tags.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function subscribe( $email, $first_name = '', $last_name = '', $tags = [] ) {
		$settings = self::get_settings();

		if ( ! self::is_enabled() ) {
			return new WP_Error( 'mailchimp_disabled', __( 'Mailchimp integration is not enabled.', 'ai-agent-for-website' ) );
		}

		$server  = self::get_server_from_api_key( $settings['mailchimp_api_key'] );
		$list_id = $settings['mailchimp_list_id'];

		$url = sprintf(
			'https://%s.api.mailchimp.com/3.0/lists/%s/members',
			$server,
			$list_id
		);

		$merge_fields = [];
		if ( ! empty( $first_name ) ) {
			$merge_fields['FNAME'] = $first_name;
		}
		if ( ! empty( $last_name ) ) {
			$merge_fields['LNAME'] = $last_name;
		}

		$body = [
			'email_address' => $email,
			'status'        => 'subscribed',
		];

		if ( ! empty( $merge_fields ) ) {
			$body['merge_fields'] = $merge_fields;
		}

		if ( ! empty( $tags ) ) {
			$body['tags'] = $tags;
		}

		$response = wp_remote_post(
			$url,
			[
				'body'    => wp_json_encode( $body ),
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $settings['mailchimp_api_key'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for API auth.
					'Content-Type'  => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// 200 = success, 400 with "Member Exists" is okay too.
		if ( 200 === $code ) {
			return true;
		}

		if ( 400 === $code && isset( $body['title'] ) && 'Member Exists' === $body['title'] ) {
			return true; // Already subscribed, not an error.
		}

		$error_message = isset( $body['detail'] ) ? $body['detail'] : __( 'Failed to subscribe to newsletter.', 'ai-agent-for-website' );
		return new WP_Error( 'mailchimp_error', $error_message );
	}

	/**
	 * Get available lists from Mailchimp.
	 *
	 * @param string $api_key Optional API key to use.
	 * @return array|WP_Error Array of lists or WP_Error.
	 */
	public static function get_lists( $api_key = '' ) {
		if ( empty( $api_key ) ) {
			$settings = self::get_settings();
			$api_key  = $settings['mailchimp_api_key'];
		}

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'No API key provided.', 'ai-agent-for-website' ) );
		}

		$server = self::get_server_from_api_key( $api_key );
		$url    = sprintf( 'https://%s.api.mailchimp.com/3.0/lists?count=100', $server );

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for API auth.
					'Content-Type'  => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error = isset( $body['detail'] ) ? $body['detail'] : __( 'Failed to fetch lists.', 'ai-agent-for-website' );
			return new WP_Error( 'mailchimp_error', $error );
		}

		if ( ! isset( $body['lists'] ) ) {
			return [];
		}

		$lists = [];
		foreach ( $body['lists'] as $list ) {
			$lists[] = [
				'id'           => $list['id'],
				'name'         => $list['name'],
				'member_count' => $list['stats']['member_count'] ?? 0,
			];
		}

		return $lists;
	}

	/**
	 * Test API connection.
	 *
	 * @param string $api_key The API key to test.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_connection( $api_key ) {
		$lists = self::get_lists( $api_key );

		if ( is_wp_error( $lists ) ) {
			return $lists;
		}

		return true;
	}
}
