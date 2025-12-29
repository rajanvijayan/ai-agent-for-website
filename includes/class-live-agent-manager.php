<?php
/**
 * Live Agent Manager Class
 *
 * Handles live agent functionality including agent availability,
 * session handoff from AI, and real-time messaging.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Live_Agent_Manager
 */
class AIAGENT_Live_Agent_Manager {

	/**
	 * Option key for live agent settings.
	 *
	 * @var string
	 */
	const SETTINGS_KEY = 'aiagent_live_agent_settings';

	/**
	 * Transient key prefix for agent status.
	 *
	 * @var string
	 */
	const AGENT_STATUS_PREFIX = 'aiagent_agent_online_';

	/**
	 * Agent online timeout in seconds (5 minutes).
	 *
	 * @var int
	 */
	const AGENT_TIMEOUT = 300;

	/**
	 * Get live agent settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'                    => false,
			'allowed_roles'              => array( 'administrator' ),
			'show_on_no_results'         => true,
			'show_after_messages'        => 3,
			'connect_button_text'        => __( 'Connect to Live Agent', 'ai-agent-for-website' ),
			'waiting_message'            => __( 'Please wait while we connect you to a live agent...', 'ai-agent-for-website' ),
			'connected_message'          => __( 'You are now connected with a live agent.', 'ai-agent-for-website' ),
			'offline_message'            => __( 'Our agents are currently offline. Please try again later or leave a message.', 'ai-agent-for-website' ),
			'agent_typing_indicator'     => true,
			'enable_sound_notifications' => true,
			'auto_assign'                => true,
			'max_concurrent_chats'       => 5,
			'queue_enabled'              => true,
			'queue_message'              => __( 'You are in queue. Position: {position}', 'ai-agent-for-website' ),
			'transfer_conversation'      => true,
			'show_agent_name'            => true,
			'show_agent_avatar'          => true,
		);

		$settings = get_option( self::SETTINGS_KEY, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update live agent settings.
	 *
	 * @param array $settings New settings to save.
	 * @return bool True if updated successfully.
	 */
	public static function update_settings( $settings ) {
		return update_option( self::SETTINGS_KEY, $settings );
	}

	/**
	 * Check if live agent feature is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Check if any agent is currently available.
	 *
	 * @return bool True if at least one agent is online.
	 */
	public static function is_agent_available() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$online_agents = self::get_online_agents();
		return ! empty( $online_agents );
	}

	/**
	 * Get list of online agents.
	 *
	 * @return array Array of online agent user objects.
	 */
	public static function get_online_agents() {
		$settings      = self::get_settings();
		$allowed_roles = $settings['allowed_roles'] ?? array( 'administrator' );

		if ( empty( $allowed_roles ) ) {
			return array();
		}

		// Get users with allowed roles.
		$users = get_users(
			array(
				'role__in' => $allowed_roles,
			)
		);

		$online_agents = array();

		foreach ( $users as $user ) {
			$status = get_transient( self::AGENT_STATUS_PREFIX . $user->ID );
			if ( 'online' === $status || 'available' === $status ) {
				$online_agents[] = array(
					'id'          => $user->ID,
					'name'        => $user->display_name,
					'email'       => $user->user_email,
					'avatar'      => get_avatar_url( $user->ID, array( 'size' => 64 ) ),
					'status'      => $status,
					'active_chats' => self::get_agent_active_chat_count( $user->ID ),
				);
			}
		}

		return $online_agents;
	}

	/**
	 * Set agent online status.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  Status (online, offline, busy, available).
	 * @return bool True if status was set.
	 */
	public static function set_agent_status( $user_id, $status = 'online' ) {
		// Allow admins to set status for testing, even if not in allowed roles.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$can_be_agent = self::can_user_be_agent( $user_id );
		$is_admin     = in_array( 'administrator', $user->roles, true );

		if ( ! $can_be_agent && ! $is_admin ) {
			return false;
		}

		if ( 'offline' === $status ) {
			delete_transient( self::AGENT_STATUS_PREFIX . $user_id );
			
			// Also update user meta for persistence.
			update_user_meta( $user_id, 'aiagent_agent_status', 'offline' );
			update_user_meta( $user_id, 'aiagent_last_seen', current_time( 'mysql' ) );
		} else {
			set_transient( self::AGENT_STATUS_PREFIX . $user_id, $status, self::AGENT_TIMEOUT );
			
			// Also update user meta.
			update_user_meta( $user_id, 'aiagent_agent_status', $status );
			update_user_meta( $user_id, 'aiagent_last_heartbeat', current_time( 'mysql' ) );
		}

		return true;
	}

	/**
	 * Get agent status.
	 *
	 * @param int $user_id User ID.
	 * @return string Status (online, offline, busy, available).
	 */
	public static function get_agent_status( $user_id ) {
		$status = get_transient( self::AGENT_STATUS_PREFIX . $user_id );
		return $status ? $status : 'offline';
	}

	/**
	 * Heartbeat to keep agent online.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if heartbeat was recorded.
	 */
	public static function agent_heartbeat( $user_id ) {
		$current_status = self::get_agent_status( $user_id );
		
		if ( 'offline' === $current_status ) {
			$current_status = 'available';
		}
		
		return self::set_agent_status( $user_id, $current_status );
	}

	/**
	 * Check if user can be a live agent.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user can be agent.
	 */
	public static function can_user_be_agent( $user_id ) {
		$settings      = self::get_settings();
		$allowed_roles = $settings['allowed_roles'] ?? array( 'administrator' );

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get number of active chats for an agent.
	 *
	 * @param int $user_id Agent user ID.
	 * @return int Number of active chats.
	 */
	public static function get_agent_active_chat_count( $user_id ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';
		
		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions_table ) );
		
		if ( ! $table_exists ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $sessions_table WHERE agent_id = %d AND status = 'active'",
				$user_id
			)
		);

		return (int) $count;
	}

	/**
	 * Create a live agent session (handoff from AI).
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param int    $user_id         Chat user ID.
	 * @param string $session_id      Session ID.
	 * @return array|WP_Error Session data or error.
	 */
	public static function create_session( $conversation_id, $user_id, $session_id ) {
		global $wpdb;

		if ( ! self::is_enabled() ) {
			return new WP_Error( 'not_enabled', __( 'Live agent feature is not enabled.', 'ai-agent-for-website' ) );
		}

		if ( ! self::is_agent_available() ) {
			return new WP_Error( 'no_agents', __( 'No agents are currently available.', 'ai-agent-for-website' ) );
		}

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';

		// Check for existing active session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $sessions_table WHERE session_id = %s AND status IN ('waiting', 'active')",
				$session_id
			)
		);

		if ( $existing ) {
			return array(
				'success'    => true,
				'session'    => $existing,
				'message'    => __( 'Session already exists.', 'ai-agent-for-website' ),
			);
		}

		// Find best available agent.
		$agent = self::find_available_agent();

		$settings = self::get_settings();
		$status   = $agent ? 'active' : ( $settings['queue_enabled'] ? 'waiting' : 'no_agent' );

		if ( 'no_agent' === $status ) {
			return new WP_Error( 'no_agents', __( 'No agents are currently available.', 'ai-agent-for-website' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$sessions_table,
			array(
				'conversation_id' => $conversation_id,
				'user_id'         => $user_id,
				'session_id'      => $session_id,
				'agent_id'        => $agent ? $agent['id'] : null,
				'status'          => $status,
				'started_at'      => current_time( 'mysql' ),
			)
		);

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create live agent session.', 'ai-agent-for-website' ) );
		}

		$live_session_id = $wpdb->insert_id;

		// Get queue position if waiting.
		$queue_position = 0;
		if ( 'waiting' === $status ) {
			$queue_position = self::get_queue_position( $live_session_id );
		}

		// Log the session creation.
		if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			$log_manager = new AIAGENT_Activity_Log_Manager();
			$log_manager->log(
				'live_agent',
				'session_created',
				sprintf(
					/* translators: %d: Session ID */
					__( 'Live agent session #%d created', 'ai-agent-for-website' ),
					$live_session_id
				),
				array(
					'live_session_id' => $live_session_id,
					'conversation_id' => $conversation_id,
					'agent_id'        => $agent ? $agent['id'] : null,
					'status'          => $status,
				)
			);
		}

		// Notify agent if assigned.
		if ( $agent && class_exists( 'AIAGENT_Notification_Manager' ) ) {
			$notification_manager = new AIAGENT_Notification_Manager();
			$notification_manager->create_notification(
				'live_chat_request',
				__( 'New Live Chat Request', 'ai-agent-for-website' ),
				sprintf(
					/* translators: %d: Session ID */
					__( 'A visitor has requested to chat with a live agent. Session #%d', 'ai-agent-for-website' ),
					$live_session_id
				),
				array(
					'live_session_id' => $live_session_id,
					'agent_id'        => $agent['id'],
				)
			);
		}

		return array(
			'success'        => true,
			'session_id'     => $live_session_id,
			'status'         => $status,
			'agent'          => $agent ? array(
				'id'     => $agent['id'],
				'name'   => $agent['name'],
				'avatar' => $agent['avatar'],
			) : null,
			'queue_position' => $queue_position,
			'message'        => 'active' === $status
				? $settings['connected_message']
				: str_replace( '{position}', $queue_position, $settings['queue_message'] ),
		);
	}

	/**
	 * Find the best available agent based on workload.
	 *
	 * @return array|null Agent data or null if none available.
	 */
	public static function find_available_agent() {
		$online_agents = self::get_online_agents();

		if ( empty( $online_agents ) ) {
			return null;
		}

		$settings           = self::get_settings();
		$max_chats          = $settings['max_concurrent_chats'] ?? 5;
		$available_agents   = array();

		foreach ( $online_agents as $agent ) {
			if ( 'available' === $agent['status'] && $agent['active_chats'] < $max_chats ) {
				$available_agents[] = $agent;
			}
		}

		if ( empty( $available_agents ) ) {
			return null;
		}

		// Sort by active chats (least busy first).
		usort(
			$available_agents,
			function ( $a, $b ) {
				return $a['active_chats'] - $b['active_chats'];
			}
		);

		return $available_agents[0];
	}

	/**
	 * Get queue position for a session.
	 *
	 * @param int $live_session_id Live session ID.
	 * @return int Queue position (1-based).
	 */
	public static function get_queue_position( $live_session_id ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$position = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) + 1 FROM $sessions_table WHERE status = 'waiting' AND id < %d",
				$live_session_id
			)
		);

		return (int) $position;
	}

	/**
	 * End a live agent session.
	 *
	 * @param int    $live_session_id Live session ID.
	 * @param string $ended_by        Who ended the session (agent, user, system).
	 * @return bool True if session was ended.
	 */
	public static function end_session( $live_session_id, $ended_by = 'system' ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$sessions_table,
			array(
				'status'   => 'ended',
				'ended_at' => current_time( 'mysql' ),
				'ended_by' => $ended_by,
			),
			array( 'id' => $live_session_id )
		);

		if ( $result ) {
			// Log session end.
			if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
				$log_manager = new AIAGENT_Activity_Log_Manager();
				$log_manager->log(
					'live_agent',
					'session_ended',
					sprintf(
						/* translators: 1: Session ID, 2: Who ended */
						__( 'Live agent session #%1$d ended by %2$s', 'ai-agent-for-website' ),
						$live_session_id,
						$ended_by
					),
					array(
						'live_session_id' => $live_session_id,
						'ended_by'        => $ended_by,
					)
				);
			}

			// Check queue and assign next waiting session to freed agent.
			self::process_queue();
		}

		return (bool) $result;
	}

	/**
	 * Process the waiting queue.
	 */
	public static function process_queue() {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';

		// Get next waiting session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$waiting_session = $wpdb->get_row(
			"SELECT * FROM $sessions_table WHERE status = 'waiting' ORDER BY started_at ASC LIMIT 1"
		);

		if ( ! $waiting_session ) {
			return;
		}

		// Find available agent.
		$agent = self::find_available_agent();

		if ( ! $agent ) {
			return;
		}

		// Assign agent to session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$sessions_table,
			array(
				'agent_id' => $agent['id'],
				'status'   => 'active',
			),
			array( 'id' => $waiting_session->id )
		);

		// Notify about assignment.
		if ( class_exists( 'AIAGENT_Notification_Manager' ) ) {
			$notification_manager = new AIAGENT_Notification_Manager();
			$notification_manager->create_notification(
				'live_chat_assigned',
				__( 'Live Chat Assigned', 'ai-agent-for-website' ),
				sprintf(
					/* translators: %d: Session ID */
					__( 'You have been assigned to live chat session #%d', 'ai-agent-for-website' ),
					$waiting_session->id
				),
				array(
					'live_session_id' => $waiting_session->id,
					'agent_id'        => $agent['id'],
				)
			);
		}
	}

	/**
	 * Send a message in a live agent session.
	 *
	 * @param int    $live_session_id Live session ID.
	 * @param string $message         Message content.
	 * @param string $sender_type     Sender type (agent or user).
	 * @param int    $sender_id       Sender ID (agent user ID or chat user ID).
	 * @return array|WP_Error Result or error.
	 */
	public static function send_message( $live_session_id, $message, $sender_type, $sender_id ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';
		$messages_table = $wpdb->prefix . 'aiagent_live_messages';

		// Verify session exists and is active.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$session = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $sessions_table WHERE id = %d", $live_session_id )
		);

		if ( ! $session ) {
			return new WP_Error( 'not_found', __( 'Session not found.', 'ai-agent-for-website' ) );
		}

		if ( 'active' !== $session->status ) {
			return new WP_Error( 'not_active', __( 'Session is not active.', 'ai-agent-for-website' ) );
		}

		// Insert message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$messages_table,
			array(
				'live_session_id' => $live_session_id,
				'sender_type'     => $sender_type,
				'sender_id'       => $sender_id,
				'message'         => $message,
			)
		);

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Failed to send message.', 'ai-agent-for-website' ) );
		}

		return array(
			'success'    => true,
			'message_id' => $wpdb->insert_id,
		);
	}

	/**
	 * Get messages for a live session.
	 *
	 * @param int $live_session_id Live session ID.
	 * @param int $after_id        Get messages after this ID.
	 * @return array Messages array.
	 */
	public static function get_messages( $live_session_id, $after_id = 0 ) {
		global $wpdb;

		$messages_table = $wpdb->prefix . 'aiagent_live_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $messages_table WHERE live_session_id = %d AND id > %d ORDER BY id ASC",
				$live_session_id,
				$after_id
			)
		);

		return $messages ?: array();
	}

	/**
	 * Get session status for polling.
	 *
	 * @param string $session_id Chat session ID.
	 * @return array Session status data.
	 */
	public static function get_session_status( $session_id ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $sessions_table WHERE session_id = %s ORDER BY id DESC LIMIT 1",
				$session_id
			)
		);

		if ( ! $session ) {
			return array(
				'has_session'    => false,
				'agent_available' => self::is_agent_available(),
			);
		}

		$agent_data = null;
		if ( $session->agent_id ) {
			$agent = get_user_by( 'id', $session->agent_id );
			if ( $agent ) {
				$settings   = self::get_settings();
				$agent_data = array(
					'id'     => $agent->ID,
					'name'   => $settings['show_agent_name'] ? $agent->display_name : __( 'Agent', 'ai-agent-for-website' ),
					'avatar' => $settings['show_agent_avatar'] ? get_avatar_url( $agent->ID, array( 'size' => 64 ) ) : '',
				);
			}
		}

		return array(
			'has_session'     => true,
			'live_session_id' => $session->id,
			'status'          => $session->status,
			'agent'           => $agent_data,
			'queue_position'  => 'waiting' === $session->status ? self::get_queue_position( $session->id ) : 0,
		);
	}

	/**
	 * Check if AI response indicates no helpful results.
	 *
	 * @param string $ai_response The AI response to check.
	 * @return bool True if response indicates no helpful results.
	 */
	public static function is_negative_result( $ai_response ) {
		$negative_patterns = array(
			'i don\'t have',
			'i don\'t know',
			'i\'m not sure',
			'i cannot',
			'i can\'t',
			'unfortunately',
			'i\'m sorry, but',
			'i apologize',
			'no information',
			'not available',
			'outside my knowledge',
			'beyond my scope',
			'i\'m unable to',
			'i don\'t understand',
			'please clarify',
			'could you rephrase',
		);

		$lower_response = strtolower( $ai_response );

		foreach ( $negative_patterns as $pattern ) {
			if ( strpos( $lower_response, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all WordPress roles for settings dropdown.
	 *
	 * @return array Roles array with name => label.
	 */
	public static function get_available_roles() {
		$wp_roles = wp_roles();
		$roles    = array();

		foreach ( $wp_roles->roles as $role_name => $role_info ) {
			$roles[ $role_name ] = $role_info['name'];
		}

		return $roles;
	}

	/**
	 * Get pending live sessions for an agent.
	 *
	 * @param int $agent_id Agent user ID.
	 * @return array Array of pending sessions.
	 */
	public static function get_agent_sessions( $agent_id ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';
		$users_table    = $wpdb->prefix . 'aiagent_users';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, u.name as user_name, u.email as user_email 
				FROM $sessions_table s 
				LEFT JOIN $users_table u ON s.user_id = u.id 
				WHERE s.agent_id = %d AND s.status = 'active' 
				ORDER BY s.started_at DESC",
				$agent_id
			)
		);

		return $sessions ?: array();
	}

	/**
	 * Transfer session to another agent.
	 *
	 * @param int $live_session_id  Live session ID.
	 * @param int $new_agent_id     New agent user ID.
	 * @param int $current_agent_id Current agent user ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public static function transfer_session( $live_session_id, $new_agent_id, $current_agent_id ) {
		global $wpdb;

		$settings = self::get_settings();

		if ( empty( $settings['transfer_conversation'] ) ) {
			return new WP_Error( 'transfer_disabled', __( 'Session transfer is not enabled.', 'ai-agent-for-website' ) );
		}

		if ( ! self::can_user_be_agent( $new_agent_id ) ) {
			return new WP_Error( 'invalid_agent', __( 'Invalid agent selected.', 'ai-agent-for-website' ) );
		}

		$sessions_table = $wpdb->prefix . 'aiagent_live_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$sessions_table,
			array( 'agent_id' => $new_agent_id ),
			array(
				'id'       => $live_session_id,
				'agent_id' => $current_agent_id,
			)
		);

		if ( $result ) {
			// Log the transfer.
			if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
				$log_manager = new AIAGENT_Activity_Log_Manager();
				$old_agent   = get_user_by( 'id', $current_agent_id );
				$new_agent   = get_user_by( 'id', $new_agent_id );

				$log_manager->log(
					'live_agent',
					'session_transferred',
					sprintf(
						/* translators: 1: Session ID, 2: Old agent name, 3: New agent name */
						__( 'Session #%1$d transferred from %2$s to %3$s', 'ai-agent-for-website' ),
						$live_session_id,
						$old_agent ? $old_agent->display_name : 'Unknown',
						$new_agent ? $new_agent->display_name : 'Unknown'
					),
					array(
						'live_session_id'  => $live_session_id,
						'old_agent_id'     => $current_agent_id,
						'new_agent_id'     => $new_agent_id,
					)
				);
			}

			return true;
		}

		return new WP_Error( 'transfer_failed', __( 'Failed to transfer session.', 'ai-agent-for-website' ) );
	}

	/**
	 * Get frontend settings for JavaScript.
	 *
	 * @return array Frontend settings.
	 */
	public static function get_frontend_settings() {
		$settings = self::get_settings();

		return array(
			'enabled'              => $settings['enabled'],
			'show_on_no_results'   => $settings['show_on_no_results'],
			'show_after_messages'  => $settings['show_after_messages'],
			'connect_button_text'  => $settings['connect_button_text'],
			'waiting_message'      => $settings['waiting_message'],
			'connected_message'    => $settings['connected_message'],
			'offline_message'      => $settings['offline_message'],
			'typing_indicator'     => $settings['agent_typing_indicator'],
			'sound_notifications'  => $settings['enable_sound_notifications'],
			'show_agent_name'      => $settings['show_agent_name'],
			'show_agent_avatar'    => $settings['show_agent_avatar'],
			'agent_available'      => self::is_agent_available(),
		);
	}
}

