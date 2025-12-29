<?php
/**
 * Google Calendar Integration Class
 *
 * Handles Google Calendar OAuth and event scheduling for the chat widget.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Google_Calendar_Integration
 *
 * Manages Google Calendar OAuth authentication and event scheduling.
 */
class AIAGENT_Google_Calendar_Integration {

	/**
	 * Option name for storing Google Calendar settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'aiagent_google_calendar_settings';

	/**
	 * Option name for storing OAuth tokens.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'aiagent_google_calendar_tokens';

	/**
	 * Google OAuth URLs.
	 */
	const OAUTH_AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
	const OAUTH_TOKEN_URL  = 'https://oauth2.googleapis.com/token';
	const CALENDAR_API_URL = 'https://www.googleapis.com/calendar/v3';
	const USERINFO_URL     = 'https://www.googleapis.com/oauth2/v2/userinfo';

	/**
	 * Required OAuth scopes.
	 *
	 * @var array
	 */
	private $scopes = [
		'https://www.googleapis.com/auth/calendar.events',
		'https://www.googleapis.com/auth/calendar.readonly',
		'https://www.googleapis.com/auth/userinfo.email',
		'https://www.googleapis.com/auth/userinfo.profile',
	];

	/**
	 * Get Google Calendar settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		return get_option(
			self::OPTION_NAME,
			[
				'enabled'                    => false,
				'client_id'                  => '',
				'client_secret'              => '',
				'default_calendar_id'        => 'primary',
				'default_duration'           => 30,
				'buffer_time'                => 15,
				'days_ahead'                 => 14,
				'business_hours_start'       => '09:00',
				'business_hours_end'         => '17:00',
				'working_days'               => [ 1, 2, 3, 4, 5 ], // Monday to Friday.
				'prompt_after_chat'          => true,
				'prompt_message'             => 'Would you like to schedule a meeting or follow-up?',
				'event_title_template'       => 'Meeting with {user_name}',
				'event_description_template' => 'Scheduled via AI Agent chat widget.',
			]
		);
	}

	/**
	 * Update Google Calendar settings.
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
	 * Check if connected to Google Calendar.
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
	 * Check if integration is enabled and ready.
	 *
	 * @return bool True if enabled and connected.
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] ) && self::is_connected();
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @return string|WP_Error Authorization URL or error.
	 */
	public function get_auth_url() {
		$settings = self::get_settings();

		if ( empty( $settings['client_id'] ) ) {
			return new WP_Error( 'not_configured', __( 'Google Calendar Client ID is not configured.', 'ai-agent-for-website' ) );
		}

		$redirect_uri = $this->get_redirect_uri();
		$state        = wp_create_nonce( 'aiagent_gcalendar_oauth' );

		// Store state for verification.
		set_transient( 'aiagent_gcalendar_oauth_state', $state, 600 );

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
		return admin_url( 'admin.php?page=ai-agent-settings&tab=integrations&gcalendar_callback=1' );
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
		$stored_state = get_transient( 'aiagent_gcalendar_oauth_state' );
		if ( ! $stored_state || $state !== $stored_state ) {
			return new WP_Error( 'invalid_state', __( 'Invalid OAuth state. Please try again.', 'ai-agent-for-website' ) );
		}

		delete_transient( 'aiagent_gcalendar_oauth_state' );

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
			return new WP_Error( 'not_connected', __( 'Not connected to Google Calendar.', 'ai-agent-for-website' ) );
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
	 * List available calendars.
	 *
	 * @return array|WP_Error Calendars list or error.
	 */
	public function list_calendars() {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$url = self::CALENDAR_API_URL . '/users/me/calendarList';

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
			return new WP_Error( 'calendar_error', $body['error']['message'] ?? 'Failed to list calendars' );
		}

		$calendars = [];
		if ( ! empty( $body['items'] ) ) {
			foreach ( $body['items'] as $item ) {
				// Only include calendars where user can create events.
				if ( in_array( $item['accessRole'], [ 'owner', 'writer' ], true ) ) {
					$calendars[] = [
						'id'      => $item['id'],
						'summary' => $item['summary'],
						'primary' => ! empty( $item['primary'] ),
					];
				}
			}
		}

		return $calendars;
	}

	/**
	 * Get free/busy information for a time range.
	 *
	 * @param string $start_date Start date (Y-m-d format).
	 * @param string $end_date   End date (Y-m-d format).
	 * @return array|WP_Error Free/busy data or error.
	 */
	public function get_free_busy( $start_date, $end_date ) {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings    = self::get_settings();
		$calendar_id = ! empty( $settings['default_calendar_id'] ) ? $settings['default_calendar_id'] : 'primary';

		// Get timezone.
		$timezone = wp_timezone_string();

		$url = self::CALENDAR_API_URL . '/freeBusy';

		$body = [
			'timeMin'  => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $start_date . ' 00:00:00' ) ),
			'timeMax'  => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $end_date . ' 23:59:59' ) ),
			'timeZone' => $timezone,
			'items'    => [
				[ 'id' => $calendar_id ],
			],
		];

		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'freebusy_error', $result['error']['message'] ?? 'Failed to get free/busy info' );
		}

		return $result;
	}

	/**
	 * Get available time slots for booking.
	 *
	 * @param string|null $start_date Start date (Y-m-d format). Defaults to today.
	 * @param int|null    $days_ahead Number of days to look ahead.
	 * @return array|WP_Error Available slots or error.
	 */
	public function get_available_slots( $start_date = null, $days_ahead = null ) {
		$settings = self::get_settings();

		if ( null === $start_date ) {
			$start_date = gmdate( 'Y-m-d' );
		}

		if ( null === $days_ahead ) {
			$days_ahead_setting = intval( $settings['days_ahead'] );
			$days_ahead         = $days_ahead_setting > 0 ? $days_ahead_setting : 14;
		}

		$end_date = gmdate( 'Y-m-d', strtotime( $start_date . ' + ' . $days_ahead . ' days' ) );

		// Get free/busy info.
		$freebusy = $this->get_free_busy( $start_date, $end_date );

		if ( is_wp_error( $freebusy ) ) {
			return $freebusy;
		}

		$calendar_id = ! empty( $settings['default_calendar_id'] ) ? $settings['default_calendar_id'] : 'primary';
		$busy_times  = [];

		if ( isset( $freebusy['calendars'][ $calendar_id ]['busy'] ) ) {
			$busy_times = $freebusy['calendars'][ $calendar_id ]['busy'];
		}

		// Generate available slots.
		$slots = $this->generate_available_slots( $start_date, $end_date, $busy_times, $settings );

		return $slots;
	}

	/**
	 * Generate available time slots based on busy times and settings.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $busy_times Busy time ranges from Google.
	 * @param array  $settings   Calendar settings.
	 * @return array Available slots grouped by date.
	 */
	private function generate_available_slots( $start_date, $end_date, $busy_times, $settings ) {
		$duration_setting = intval( $settings['default_duration'] );
		$buffer_setting   = intval( $settings['buffer_time'] );
		$duration         = $duration_setting > 0 ? $duration_setting : 30;
		$buffer           = $buffer_setting >= 0 ? $buffer_setting : 15;
		$hours_start      = ! empty( $settings['business_hours_start'] ) ? $settings['business_hours_start'] : '09:00';
		$hours_end        = ! empty( $settings['business_hours_end'] ) ? $settings['business_hours_end'] : '17:00';
		$working_days     = isset( $settings['working_days'] ) ? $settings['working_days'] : [ 1, 2, 3, 4, 5 ];
		$timezone         = wp_timezone();

		$slots        = [];
		$current_date = new DateTime( $start_date, $timezone );
		$end          = new DateTime( $end_date, $timezone );

		// Don't show slots in the past.
		$now = new DateTime( 'now', $timezone );
		if ( $current_date < $now ) {
			$current_date = $now;
		}

		while ( $current_date <= $end ) {
			$day_of_week = intval( $current_date->format( 'N' ) );

			// Check if it's a working day.
			if ( in_array( $day_of_week, $working_days, true ) ) {
				$date_str  = $current_date->format( 'Y-m-d' );
				$day_start = new DateTime( $date_str . ' ' . $hours_start, $timezone );
				$day_end   = new DateTime( $date_str . ' ' . $hours_end, $timezone );

				// If today, start from current time + buffer.
				if ( $date_str === $now->format( 'Y-m-d' ) ) {
					$earliest = clone $now;
					$earliest->modify( '+' . $buffer . ' minutes' );
					// Round to next slot boundary.
					$minutes   = intval( $earliest->format( 'i' ) );
					$round_to  = $duration;
					$remainder = $minutes % $round_to;
					if ( $remainder > 0 ) {
						$earliest->modify( '+' . ( $round_to - $remainder ) . ' minutes' );
					}
					if ( $earliest > $day_start ) {
						$day_start = $earliest;
					}
				}

				$slot_start = clone $day_start;

				while ( $slot_start < $day_end ) {
					$slot_end = clone $slot_start;
					$slot_end->modify( '+' . $duration . ' minutes' );

					// Check if slot ends after business hours.
					if ( $slot_end > $day_end ) {
						break;
					}

					// Check if slot conflicts with busy times.
					$is_busy = false;
					foreach ( $busy_times as $busy ) {
						$busy_start = new DateTime( $busy['start'] );
						$busy_end   = new DateTime( $busy['end'] );

						// Apply buffer before and after busy time.
						$busy_start->modify( '-' . $buffer . ' minutes' );
						$busy_end->modify( '+' . $buffer . ' minutes' );

						// Check for overlap.
						if ( $slot_start < $busy_end && $slot_end > $busy_start ) {
							$is_busy = true;
							break;
						}
					}

					if ( ! $is_busy ) {
						if ( ! isset( $slots[ $date_str ] ) ) {
							$slots[ $date_str ] = [
								'date'       => $date_str,
								'day_name'   => $slot_start->format( 'l' ),
								'date_label' => $slot_start->format( 'F j, Y' ),
								'slots'      => [],
							];
						}

						$slots[ $date_str ]['slots'][] = [
							'start'     => $slot_start->format( 'H:i' ),
							'end'       => $slot_end->format( 'H:i' ),
							'start_iso' => $slot_start->format( 'c' ),
							'end_iso'   => $slot_end->format( 'c' ),
							'label'     => $slot_start->format( 'g:i A' ) . ' - ' . $slot_end->format( 'g:i A' ),
						];
					}

					// Move to next slot.
					$slot_start->modify( '+' . $duration . ' minutes' );
				}
			}

			$current_date->modify( '+1 day' );
		}

		return array_values( $slots );
	}

	/**
	 * Create a calendar event.
	 *
	 * @param array $event_data Event data.
	 * @return array|WP_Error Created event or error.
	 */
	public function create_event( $event_data ) {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings    = self::get_settings();
		$calendar_id = ! empty( $settings['default_calendar_id'] ) ? $settings['default_calendar_id'] : 'primary';
		$timezone    = wp_timezone_string();

		// Prepare event body.
		$event = [
			'summary'     => $event_data['title'] ?? 'Meeting',
			'description' => $event_data['description'] ?? '',
			'start'       => [
				'dateTime' => $event_data['start'],
				'timeZone' => $timezone,
			],
			'end'         => [
				'dateTime' => $event_data['end'],
				'timeZone' => $timezone,
			],
		];

		// Add attendee if email provided.
		if ( ! empty( $event_data['attendee_email'] ) ) {
			$event['attendees']   = [
				[
					'email'       => $event_data['attendee_email'],
					'displayName' => $event_data['attendee_name'] ?? '',
				],
			];
			$event['sendUpdates'] = 'all'; // Send email invites.
		}

		// Add conference link if requested.
		if ( ! empty( $event_data['add_meet'] ) ) {
			$event['conferenceData'] = [
				'createRequest' => [
					'requestId'             => wp_generate_uuid4(),
					'conferenceSolutionKey' => [
						'type' => 'hangoutsMeet',
					],
				],
			];
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Required for API URL encoding.
		$url = self::CALENDAR_API_URL . '/calendars/' . urlencode( $calendar_id ) . '/events';

		if ( ! empty( $event_data['add_meet'] ) ) {
			$url .= '?conferenceDataVersion=1';
		}

		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $event ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'event_error', $result['error']['message'] ?? 'Failed to create event' );
		}

		// Log the event creation.
		if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			$log_manager = new AIAGENT_Activity_Log_Manager();
			$log_manager->log(
				'integration',
				'calendar_event_created',
				sprintf(
					/* translators: %s: Event title */
					__( 'Calendar event created: %s', 'ai-agent-for-website' ),
					$event['summary']
				),
				[
					'event_id'    => $result['id'],
					'event_title' => $event['summary'],
					'attendee'    => $event_data['attendee_email'] ?? '',
					'start'       => $event_data['start'],
				]
			);
		}

		return [
			'success'   => true,
			'event_id'  => $result['id'],
			'html_link' => $result['htmlLink'] ?? '',
			'meet_link' => $result['hangoutLink'] ?? '',
			'summary'   => $result['summary'],
			'start'     => $result['start'],
			'end'       => $result['end'],
		];
	}

	/**
	 * Get event details by ID.
	 *
	 * @param string $event_id Event ID.
	 * @return array|WP_Error Event details or error.
	 */
	public function get_event( $event_id ) {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings    = self::get_settings();
		$calendar_id = ! empty( $settings['default_calendar_id'] ) ? $settings['default_calendar_id'] : 'primary';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Required for API URL encoding.
		$url = self::CALENDAR_API_URL . '/calendars/' . urlencode( $calendar_id ) . '/events/' . urlencode( $event_id );

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

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'event_error', $result['error']['message'] ?? 'Failed to get event' );
		}

		return $result;
	}

	/**
	 * Cancel/delete an event.
	 *
	 * @param string $event_id Event ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public function cancel_event( $event_id ) {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings    = self::get_settings();
		$calendar_id = ! empty( $settings['default_calendar_id'] ) ? $settings['default_calendar_id'] : 'primary';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Required for API URL encoding.
		$url = self::CALENDAR_API_URL . '/calendars/' . urlencode( $calendar_id ) . '/events/' . urlencode( $event_id );

		$response = wp_remote_request(
			$url,
			[
				'method'  => 'DELETE',
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 204 !== $status_code && 200 !== $status_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new WP_Error( 'delete_error', $body['error']['message'] ?? 'Failed to cancel event' );
		}

		return true;
	}

	/**
	 * Get settings for frontend (public safe).
	 *
	 * @return array Frontend-safe settings.
	 */
	public static function get_frontend_settings() {
		$settings = self::get_settings();

		$duration_setting = intval( $settings['default_duration'] );

		return [
			'enabled'           => self::is_enabled(),
			'prompt_after_chat' => ! empty( $settings['prompt_after_chat'] ),
			'prompt_message'    => isset( $settings['prompt_message'] ) ? $settings['prompt_message'] : 'Would you like to schedule a meeting or follow-up?',
			'default_duration'  => $duration_setting > 0 ? $duration_setting : 30,
		];
	}
}
