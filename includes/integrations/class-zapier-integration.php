<?php
/**
 * Zapier Integration Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Zapier_Integration
 *
 * Handles Zapier webhook integration for syncing leads with CRM platforms.
 */
class AIAGENT_Zapier_Integration {

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
			'zapier_enabled'     => false,
			'zapier_webhook_url' => '',
			'zapier_events'      => [ 'lead_created', 'lead_updated' ],
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
	 * Check if Zapier is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['zapier_enabled'] ) && ! empty( $settings['zapier_webhook_url'] );
	}

	/**
	 * Send data to Zapier webhook.
	 *
	 * @param array  $data  Data to send.
	 * @param string $event Event type.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function send_webhook( $data, $event ) {
		$settings = self::get_settings();

		if ( ! self::is_enabled() ) {
			return new WP_Error( 'zapier_disabled', __( 'Zapier integration is not enabled.', 'ai-agent-for-website' ) );
		}

		// Check if this event is enabled.
		if ( ! in_array( $event, $settings['zapier_events'], true ) ) {
			return true; // Skip but don't error.
		}

		$payload = array_merge(
			$data,
			[
				'event'     => $event,
				'timestamp' => current_time( 'mysql' ),
				'site_name' => get_bloginfo( 'name' ),
				'site_url'  => home_url(),
			]
		);

		$response = wp_remote_post(
			$settings['zapier_webhook_url'],
			[
				'body'        => wp_json_encode( $payload ),
				'headers'     => [ 'Content-Type' => 'application/json' ],
				'timeout'     => 15,
				'data_format' => 'body',
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'zapier_error',
				/* translators: %d: HTTP response code */
				sprintf( __( 'Zapier returned error code: %d', 'ai-agent-for-website' ), $code )
			);
		}

		return true;
	}

	/**
	 * Test webhook connection.
	 *
	 * @param string $webhook_url The webhook URL to test.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_webhook( $webhook_url ) {
		if ( empty( $webhook_url ) ) {
			return new WP_Error( 'no_url', __( 'No webhook URL provided.', 'ai-agent-for-website' ) );
		}

		$test_data = [
			'event'     => 'test',
			'message'   => 'This is a test webhook from AI Agent for Website plugin.',
			'timestamp' => current_time( 'mysql' ),
			'site_name' => get_bloginfo( 'name' ),
			'site_url'  => home_url(),
		];

		$response = wp_remote_post(
			$webhook_url,
			[
				'body'        => wp_json_encode( $test_data ),
				'headers'     => [ 'Content-Type' => 'application/json' ],
				'timeout'     => 15,
				'data_format' => 'body',
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'webhook_error',
				/* translators: %d: HTTP response code */
				sprintf( __( 'Webhook returned error code: %d', 'ai-agent-for-website' ), $code )
			);
		}

		return true;
	}

	/**
	 * Get available webhook events.
	 *
	 * @return array Array of event keys and labels.
	 */
	public static function get_available_events() {
		return [
			'lead_created'         => __( 'New Lead Created', 'ai-agent-for-website' ),
			'lead_updated'         => __( 'Lead Updated', 'ai-agent-for-website' ),
			'conversation_started' => __( 'Conversation Started', 'ai-agent-for-website' ),
			'conversation_ended'   => __( 'Conversation Ended', 'ai-agent-for-website' ),
			'user_registered'      => __( 'User Registered', 'ai-agent-for-website' ),
		];
	}
}
