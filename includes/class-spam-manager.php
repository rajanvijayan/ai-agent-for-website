<?php
/**
 * Spam Manager Class
 *
 * Handles spam detection and user blocking functionality.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIEngine\AIEngine;

/**
 * Class AIAGENT_Spam_Manager
 *
 * Manages spam detection using AI validation and blocks spammy users.
 */
class AIAGENT_Spam_Manager {

	/**
	 * Default spam threshold (number of spam messages before blocking).
	 *
	 * @var int
	 */
	const DEFAULT_SPAM_THRESHOLD = 3;

	/**
	 * Default block duration in hours.
	 *
	 * @var int
	 */
	const DEFAULT_BLOCK_DURATION = 24;

	/**
	 * Get spam detection settings.
	 *
	 * @return array Spam detection settings.
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'            => true,
			'spam_threshold'     => self::DEFAULT_SPAM_THRESHOLD,
			'block_duration'     => self::DEFAULT_BLOCK_DURATION,
			'auto_block_enabled' => true,
			'log_spam_attempts'  => true,
		);

		$settings = get_option( 'aiagent_spam_settings', array() );
		return array_merge( $defaults, $settings );
	}

	/**
	 * Update spam detection settings.
	 *
	 * @param array $settings New settings to save.
	 * @return bool True if updated successfully.
	 */
	public static function update_settings( $settings ) {
		return update_option( 'aiagent_spam_settings', $settings );
	}

	/**
	 * Check if a user is blocked.
	 *
	 * @param string      $session_id The session ID.
	 * @param int|null    $user_id    The user ID (optional).
	 * @param string|null $ip_address The IP address (optional).
	 * @return bool|array False if not blocked, or block info array if blocked.
	 */
	public static function is_user_blocked( $session_id, $user_id = null, $ip_address = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiagent_spam_blocks';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return false;
		}

		// Build query conditions.
		$conditions = array();
		$values     = array();

		if ( ! empty( $session_id ) ) {
			$conditions[] = 'session_id = %s';
			$values[]     = $session_id;
		}

		if ( ! empty( $user_id ) ) {
			$conditions[] = 'user_id = %d';
			$values[]     = $user_id;
		}

		if ( ! empty( $ip_address ) ) {
			$conditions[] = 'ip_address = %s';
			$values[]     = $ip_address;
		}

		if ( empty( $conditions ) ) {
			return false;
		}

		// Only match records where user is actually blocked (blocked_until is set and in the future).
		$where_clause  = '(' . implode( ' OR ', $conditions ) . ')';
		$where_clause .= ' AND blocked_until IS NOT NULL AND blocked_until > %s';
		$values[]      = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT 1",
				...$values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( $block ) {
			return array(
				'blocked'       => true,
				'reason'        => $block->reason,
				'blocked_until' => $block->blocked_until,
				'spam_count'    => $block->spam_count,
			);
		}

		return false;
	}

	/**
	 * Validate message using AI to detect spam.
	 *
	 * @param string $message The message to validate.
	 * @param array  $context Additional context (user info, conversation history).
	 * @return array Validation result with is_spam, confidence, and reason.
	 */
	public static function validate_message_with_ai( $message, $context = array() ) {
		// Check if main plugin class exists.
		if ( ! class_exists( 'AI_Agent_For_Website' ) ) {
			return self::basic_spam_check( $message );
		}

		$settings = AI_Agent_For_Website::get_settings();

		// Check if API key exists.
		if ( empty( $settings['api_key'] ) ) {
			// Fall back to basic validation if no API key.
			return self::basic_spam_check( $message );
		}

		// Check if AIEngine class exists.
		if ( ! class_exists( 'AIEngine\AIEngine' ) ) {
			return self::basic_spam_check( $message );
		}

		try {
			// Create AI Engine with Groq.
			$ai = AIEngine::create( 'groq', $settings['api_key'] );

			// Set system instruction for spam detection.
			$spam_detection_prompt = "You are a spam detection AI. Your job is to analyze chat messages and determine if they are spam.

SPAM INDICATORS:
- Promotional content, ads, or marketing messages
- Links to external websites (especially suspicious or unrelated ones)
- Repetitive or nonsensical text
- Offensive, abusive, or inappropriate content
- Phishing attempts or scam messages
- Excessive use of special characters or emojis
- Messages in languages that seem automated or bot-like
- Requests for personal/financial information
- Cryptocurrency, gambling, or adult content promotion
- Contact information spam (phone numbers, emails for marketing)

NOT SPAM (legitimate messages):
- Genuine questions about products, services, or the website
- Support requests or complaints
- Normal conversational messages
- Questions containing URLs if they're asking about website features
- Contact information when naturally requested in a conversation

Analyze the message and respond with ONLY a JSON object in this exact format:
{\"is_spam\": true/false, \"confidence\": 0.0-1.0, \"reason\": \"brief explanation\"}

Do not include any other text, just the JSON object.";

			$ai->setSystemInstruction( $spam_detection_prompt );

			// Build the message to analyze with context.
			$analysis_message = 'Message to analyze: "' . $message . '"';

			if ( ! empty( $context['user_name'] ) ) {
				$analysis_message .= "\nUser name: " . $context['user_name'];
			}

			if ( ! empty( $context['message_count'] ) ) {
				$analysis_message .= "\nMessages sent in this session: " . $context['message_count'];
			}

			// Send to AI.
			$response = $ai->chat( $analysis_message );

			// Handle error response.
			if ( is_array( $response ) && isset( $response['error'] ) ) {
				// Fall back to basic check on error.
				return self::basic_spam_check( $message );
			}

			// Parse JSON response.
			$result = json_decode( $response, true );

			if ( json_last_error() === JSON_ERROR_NONE && isset( $result['is_spam'] ) ) {
				return array(
					'is_spam'    => (bool) $result['is_spam'],
					'confidence' => isset( $result['confidence'] ) ? (float) $result['confidence'] : 0.5,
					'reason'     => isset( $result['reason'] ) ? sanitize_text_field( $result['reason'] ) : '',
					'method'     => 'ai',
				);
			}

			// Fall back to basic check if AI response is malformed.
			return self::basic_spam_check( $message );

		} catch ( Exception $e ) {
			// Fall back to basic check on error (error logged via WP_DEBUG if enabled).
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging for debugging.
			error_log( 'AI Agent Spam Detection Error: ' . $e->getMessage() );
			return self::basic_spam_check( $message );
		}
	}

	/**
	 * Basic spam check without AI (fallback).
	 *
	 * @param string $message The message to check.
	 * @return array Validation result.
	 */
	public static function basic_spam_check( $message ) {
		$is_spam    = false;
		$confidence = 0.0;
		$reason     = '';

		// Check for common spam patterns.
		$spam_patterns = array(
			// URL patterns.
			'/https?:\/\/[^\s]+/i'                     => 'Contains suspicious URL',
			// Multiple URLs.
			'/(https?:\/\/[^\s]+){2,}/i'               => 'Contains multiple URLs',
			// Crypto spam.
			'/\b(bitcoin|btc|ethereum|eth|crypto|nft|airdrop)\b/i' => 'Cryptocurrency spam',
			// Adult content.
			'/\b(porn|xxx|sex|nude|dating|hookup)\b/i' => 'Adult content spam',
			// Money scam.
			'/\b(earn\s*\$|make\s*money|work\s*from\s*home|lottery|winner)\b/i' => 'Money scam spam',
			// Repetitive characters.
			'/(.)\1{10,}/'                             => 'Repetitive characters',
			// All caps (abuse).
			'/^[A-Z\s!]{20,}$/'                        => 'Excessive caps (potential abuse)',
			// Phone numbers spam.
			'/\+?\d{1,3}[-.\s]?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/' => 'Contains phone number',
			// Email spam.
			'/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i' => 'Contains email address',
		);

		foreach ( $spam_patterns as $pattern => $pattern_reason ) {
			if ( preg_match( $pattern, $message ) ) {
				$is_spam    = true;
				$confidence = 0.7;
				$reason     = $pattern_reason;
				break;
			}
		}

		// Check message length (too short or too long).
		$message_length = strlen( trim( $message ) );
		if ( $message_length > 2000 ) {
			$is_spam    = true;
			$confidence = 0.6;
			$reason     = 'Message too long';
		}

		return array(
			'is_spam'    => $is_spam,
			'confidence' => $confidence,
			'reason'     => $reason,
			'method'     => 'basic',
		);
	}

	/**
	 * Record a spam attempt.
	 *
	 * @param string      $session_id The session ID.
	 * @param int|null    $user_id    The user ID (optional).
	 * @param string      $message    The spam message.
	 * @param string      $reason     The reason for spam detection.
	 * @param string|null $ip_address The IP address (optional).
	 * @return int|false The spam count for this user, or false on error.
	 */
	public static function record_spam_attempt( $session_id, $user_id = null, $message = '', $reason = '', $ip_address = null ) {
		global $wpdb;

		$blocks_table = $wpdb->prefix . 'aiagent_spam_blocks';
		$logs_table   = $wpdb->prefix . 'aiagent_spam_logs';
		$settings     = self::get_settings();

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $blocks_table )
		);

		if ( ! $table_exists ) {
			// Table doesn't exist yet, can't record spam.
			return false;
		}

		// Get IP address if not provided.
		if ( empty( $ip_address ) ) {
			$ip_address = self::get_client_ip();
		}

		// Check if there's an existing block record for this user.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $blocks_table WHERE session_id = %s OR (user_id IS NOT NULL AND user_id = %d) OR ip_address = %s ORDER BY created_at DESC LIMIT 1",
				$session_id,
				$user_id ? $user_id : 0,
				$ip_address
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$spam_count = 1;

		if ( $existing ) {
			$spam_count = (int) $existing->spam_count + 1;

			// Update existing record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$blocks_table,
				array(
					'spam_count'  => $spam_count,
					'last_reason' => $reason,
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);

			// Check if we should block the user.
			if ( $settings['auto_block_enabled'] && $spam_count >= $settings['spam_threshold'] && empty( $existing->blocked_until ) ) {
				self::block_user( $session_id, $user_id, $ip_address, $reason );
			}
		} else {
			// Insert new record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$blocks_table,
				array(
					'session_id'  => $session_id,
					'user_id'     => $user_id,
					'ip_address'  => $ip_address,
					'spam_count'  => 1,
					'reason'      => $reason,
					'last_reason' => $reason,
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		// Log the spam attempt if enabled.
		if ( $settings['log_spam_attempts'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$logs_table,
				array(
					'session_id' => $session_id,
					'user_id'    => $user_id,
					'ip_address' => $ip_address,
					'message'    => wp_trim_words( $message, 50 ),
					'reason'     => $reason,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		// Log to activity log.
		if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			AIAGENT_Activity_Log_Manager::log(
				'spam',
				'spam_detected',
				/* translators: %s: spam reason */
				sprintf( __( 'Spam message detected: %s', 'ai-agent-for-website' ), $reason ),
				array(
					'session_id'      => $session_id,
					'user_id'         => $user_id,
					'spam_count'      => $spam_count,
					'message_preview' => wp_trim_words( $message, 10 ),
				),
				$user_id,
				$ip_address
			);
		}

		return $spam_count;
	}

	/**
	 * Block a user.
	 *
	 * @param string      $session_id The session ID.
	 * @param int|null    $user_id    The user ID (optional).
	 * @param string|null $ip_address The IP address (optional).
	 * @param string      $reason     The reason for blocking.
	 * @return bool True on success.
	 */
	public static function block_user( $session_id, $user_id = null, $ip_address = null, $reason = '' ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'aiagent_spam_blocks';
		$settings = self::get_settings();

		// Calculate block end time.
		$block_duration = $settings['block_duration'] * HOUR_IN_SECONDS;
		$blocked_until  = gmdate( 'Y-m-d H:i:s', time() + $block_duration );

		// Get IP address if not provided.
		if ( empty( $ip_address ) ) {
			$ip_address = self::get_client_ip();
		}

		// Check if there's an existing record.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE session_id = %s OR (user_id IS NOT NULL AND user_id = %d) OR ip_address = %s LIMIT 1",
				$session_id,
				$user_id ? $user_id : 0,
				$ip_address
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'blocked_until' => $blocked_until,
					'reason'        => $reason,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'session_id'    => $session_id,
					'user_id'       => $user_id,
					'ip_address'    => $ip_address,
					'spam_count'    => 1,
					'reason'        => $reason,
					'last_reason'   => $reason,
					'blocked_until' => $blocked_until,
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		// Create notification.
		if ( class_exists( 'AIAGENT_Notification_Manager' ) ) {
			$notification_manager = new AIAGENT_Notification_Manager();
			$notification_manager->create(
				'spam_block',
				__( 'User Blocked for Spam', 'ai-agent-for-website' ),
				/* translators: %s: block reason */
				sprintf( __( 'A user has been automatically blocked for spam: %s', 'ai-agent-for-website' ), $reason ),
				array(
					'session_id' => $session_id,
					'user_id'    => $user_id,
					'ip_address' => $ip_address,
				)
			);
		}

		// Log to activity log.
		if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			AIAGENT_Activity_Log_Manager::log(
				'spam',
				'user_blocked',
				/* translators: %s: block reason */
				sprintf( __( 'User blocked for spam: %s', 'ai-agent-for-website' ), $reason ),
				array(
					'session_id'    => $session_id,
					'user_id'       => $user_id,
					'blocked_until' => $blocked_until,
				),
				$user_id,
				$ip_address
			);
		}

		return true;
	}

	/**
	 * Unblock a user.
	 *
	 * @param int $block_id The block record ID.
	 * @return bool True on success.
	 */
	public static function unblock_user( $block_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiagent_spam_blocks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'blocked_until' => null,
				'spam_count'    => 0,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $block_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a block record.
	 *
	 * @param int $block_id The block record ID.
	 * @return bool True on success.
	 */
	public static function delete_block( $block_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiagent_spam_blocks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			array( 'id' => $block_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get all blocked users.
	 *
	 * @param int  $limit         Number of records to retrieve.
	 * @param int  $offset        Offset for pagination.
	 * @param bool $only_active   Whether to only show currently blocked users.
	 * @return array Array of block records.
	 */
	public static function get_blocked_users( $limit = 50, $offset = 0, $only_active = false ) {
		global $wpdb;

		$blocks_table = $wpdb->prefix . 'aiagent_spam_blocks';
		$users_table  = $wpdb->prefix . 'aiagent_users';

		$where = '';
		if ( $only_active ) {
			$where = $wpdb->prepare(
				' WHERE b.blocked_until IS NOT NULL AND b.blocked_until > %s',
				current_time( 'mysql' )
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, u.name as user_name, u.email as user_email 
				FROM $blocks_table b 
				LEFT JOIN $users_table u ON b.user_id = u.id 
				$where
				ORDER BY b.updated_at DESC 
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results;
	}

	/**
	 * Get spam logs.
	 *
	 * @param int $limit  Number of records to retrieve.
	 * @param int $offset Offset for pagination.
	 * @return array Array of spam log records.
	 */
	public static function get_spam_logs( $limit = 100, $offset = 0 ) {
		global $wpdb;

		$logs_table  = $wpdb->prefix . 'aiagent_spam_logs';
		$users_table = $wpdb->prefix . 'aiagent_users';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.name as user_name, u.email as user_email 
				FROM $logs_table l 
				LEFT JOIN $users_table u ON l.user_id = u.id 
				ORDER BY l.created_at DESC 
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results;
	}

	/**
	 * Get spam statistics.
	 *
	 * @return array Statistics array.
	 */
	public static function get_statistics() {
		global $wpdb;

		$blocks_table = $wpdb->prefix . 'aiagent_spam_blocks';
		$logs_table   = $wpdb->prefix . 'aiagent_spam_logs';

		// Check if tables exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $blocks_table )
		);

		if ( ! $table_exists ) {
			// Tables don't exist yet, return empty stats.
			return array(
				'total_blocked'       => 0,
				'total_spam_attempts' => 0,
				'today_spam'          => 0,
				'flagged_users'       => 0,
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Total blocked users.
		$total_blocked = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $blocks_table WHERE blocked_until IS NOT NULL AND blocked_until > %s",
				current_time( 'mysql' )
			)
		);

		// Total spam attempts (all time).
		$total_spam_attempts = $wpdb->get_var( "SELECT COUNT(*) FROM $logs_table" );

		// Spam attempts today.
		$today_spam = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $logs_table WHERE DATE(created_at) = %s",
				current_time( 'Y-m-d' )
			)
		);

		// Users with spam flags (not yet blocked).
		$flagged_users = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $blocks_table WHERE spam_count > 0 AND (blocked_until IS NULL OR blocked_until < %s)",
				current_time( 'mysql' )
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'total_blocked'       => (int) $total_blocked,
			'total_spam_attempts' => (int) $total_spam_attempts,
			'today_spam'          => (int) $today_spam,
			'flagged_users'       => (int) $flagged_users,
		);
	}

	/**
	 * Clear old spam logs.
	 *
	 * @param int $days Number of days to keep.
	 * @return int Number of deleted records.
	 */
	public static function clear_old_logs( $days = 30 ) {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'aiagent_spam_logs';
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "DELETE FROM $logs_table WHERE created_at < %s", $cutoff )
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	public static function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			// Handle comma-separated IPs.
			if ( strpos( $ip, ',' ) !== false ) {
				$ip = trim( explode( ',', $ip )[0] );
			}
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		global $wpdb;

		// Handle actions.
		if ( isset( $_POST['aiagent_spam_action'] ) && check_admin_referer( 'aiagent_spam_action' ) ) {
			$this->handle_admin_action();
		}

		// Handle settings save.
		if ( isset( $_POST['aiagent_save_spam_settings'] ) && check_admin_referer( 'aiagent_spam_settings' ) ) {
			$this->save_spam_settings();
		}

		$settings   = self::get_settings();
		$statistics = self::get_statistics();
		$blocks     = self::get_blocked_users( 50, 0, false );

		// Check which tab we're on.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'blocked';

		?>
		<div class="wrap aiagent-admin">
			<h1><?php esc_html_e( 'Spam Protection', 'ai-agent-for-website' ); ?></h1>

			<!-- Statistics -->
			<div class="aiagent-stats-grid">
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $statistics['total_blocked'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Currently Blocked', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $statistics['today_spam'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Spam Today', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $statistics['total_spam_attempts'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Total Spam Blocked', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $statistics['flagged_users'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Flagged Users', 'ai-agent-for-website' ); ?></div>
				</div>
			</div>

			<!-- Tabs -->
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-spam&tab=blocked' ) ); ?>" 
					class="nav-tab <?php echo 'blocked' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Blocked Users', 'ai-agent-for-website' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-spam&tab=logs' ) ); ?>" 
					class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Spam Logs', 'ai-agent-for-website' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-spam&tab=settings' ) ); ?>" 
					class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'ai-agent-for-website' ); ?>
				</a>
			</nav>

			<?php
			switch ( $active_tab ) {
				case 'logs':
					$this->render_logs_tab();
					break;
				case 'settings':
					$this->render_settings_tab( $settings );
					break;
				default:
					$this->render_blocked_tab( $blocks );
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render blocked users tab.
	 *
	 * @param array $blocks Array of block records.
	 */
	private function render_blocked_tab( $blocks ) {
		?>
		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Blocked Users', 'ai-agent-for-website' ); ?></h2>
			
			<?php if ( empty( $blocks ) ) : ?>
				<div class="aiagent-empty-state">
					<p><?php esc_html_e( 'No blocked users. Users who send spam messages will appear here.', 'ai-agent-for-website' ); ?></p>
				</div>
			<?php else : ?>
				<table class="aiagent-conversations-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'IP Address', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Spam Count', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Status', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Blocked Until', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ai-agent-for-website' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $blocks as $block ) : ?>
							<?php
							$is_blocked = ! empty( $block->blocked_until ) && strtotime( $block->blocked_until ) > time();
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $block->user_name ? $block->user_name : __( 'Anonymous', 'ai-agent-for-website' ) ); ?></strong>
									<?php if ( $block->user_email ) : ?>
										<br><span style="font-size: 12px; color: #666;"><?php echo esc_html( $block->user_email ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<code><?php echo esc_html( $block->ip_address ? $block->ip_address : '-' ); ?></code>
								</td>
								<td>
									<span class="aiagent-badge" style="background: #dc3232;"><?php echo esc_html( $block->spam_count ); ?></span>
								</td>
								<td><?php echo esc_html( $block->last_reason ? $block->last_reason : '-' ); ?></td>
								<td>
									<?php if ( $is_blocked ) : ?>
										<span style="color: #dc3232;">● <?php esc_html_e( 'Blocked', 'ai-agent-for-website' ); ?></span>
									<?php else : ?>
										<span style="color: #f0b849;">● <?php esc_html_e( 'Flagged', 'ai-agent-for-website' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									if ( $is_blocked ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $block->blocked_until ) ) );
									} else {
										echo '-';
									}
									?>
								</td>
								<td>
									<form method="post" style="display: inline;">
										<?php wp_nonce_field( 'aiagent_spam_action' ); ?>
										<input type="hidden" name="block_id" value="<?php echo esc_attr( $block->id ); ?>">
										
										<?php if ( $is_blocked ) : ?>
											<button type="submit" name="aiagent_spam_action" value="unblock" class="button button-small">
												<?php esc_html_e( 'Unblock', 'ai-agent-for-website' ); ?>
											</button>
										<?php else : ?>
											<button type="submit" name="aiagent_spam_action" value="block" class="button button-small">
												<?php esc_html_e( 'Block Now', 'ai-agent-for-website' ); ?>
											</button>
										<?php endif; ?>
										
										<button type="submit" name="aiagent_spam_action" value="delete" class="button button-small button-link-delete" 
											onclick="return confirm('<?php esc_attr_e( 'Delete this record?', 'ai-agent-for-website' ); ?>');">
											<?php esc_html_e( 'Delete', 'ai-agent-for-website' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render spam logs tab.
	 */
	private function render_logs_tab() {
		$logs = self::get_spam_logs( 100, 0 );
		?>
		<div class="aiagent-card">
			<h2>
				<?php esc_html_e( 'Spam Logs', 'ai-agent-for-website' ); ?>
				<form method="post" style="display: inline; float: right;">
					<?php wp_nonce_field( 'aiagent_spam_action' ); ?>
					<button type="submit" name="aiagent_spam_action" value="clear_old_logs" class="button button-small"
						onclick="return confirm('<?php esc_attr_e( 'Delete logs older than 30 days?', 'ai-agent-for-website' ); ?>');">
						<?php esc_html_e( 'Clear Old Logs', 'ai-agent-for-website' ); ?>
					</button>
				</form>
			</h2>
			
			<?php if ( empty( $logs ) ) : ?>
				<div class="aiagent-empty-state">
					<p><?php esc_html_e( 'No spam logs. Detected spam messages will be logged here.', 'ai-agent-for-website' ); ?></p>
				</div>
			<?php else : ?>
				<table class="aiagent-conversations-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'User', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'IP Address', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Message Preview', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'ai-agent-for-website' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td>
									<?php echo esc_html( human_time_diff( strtotime( $log->created_at ), time() ) . ' ' . __( 'ago', 'ai-agent-for-website' ) ); ?>
								</td>
								<td>
									<?php echo esc_html( $log->user_name ? $log->user_name : __( 'Anonymous', 'ai-agent-for-website' ) ); ?>
								</td>
								<td>
									<code><?php echo esc_html( $log->ip_address ? $log->ip_address : '-' ); ?></code>
								</td>
								<td>
									<span style="color: #666; font-style: italic;">
										"<?php echo esc_html( wp_trim_words( $log->message, 15 ) ); ?>"
									</span>
								</td>
								<td><?php echo esc_html( $log->reason ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @param array $settings Current spam settings.
	 */
	private function render_settings_tab( $settings ) {
		?>
		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Spam Protection Settings', 'ai-agent-for-website' ); ?></h2>
			
			<form method="post">
				<?php wp_nonce_field( 'aiagent_spam_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="spam_enabled"><?php esc_html_e( 'Enable Spam Protection', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="spam_enabled" name="spam_enabled" value="1" 
								<?php checked( $settings['enabled'], true ); ?>>
							<p class="description"><?php esc_html_e( 'Enable AI-powered spam detection for chat messages.', 'ai-agent-for-website' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spam_threshold"><?php esc_html_e( 'Spam Threshold', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="number" id="spam_threshold" name="spam_threshold" 
								value="<?php echo esc_attr( $settings['spam_threshold'] ); ?>" 
								min="1" max="20" class="small-text">
							<p class="description"><?php esc_html_e( 'Number of spam messages before automatically blocking a user.', 'ai-agent-for-website' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="block_duration"><?php esc_html_e( 'Block Duration (hours)', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="number" id="block_duration" name="block_duration" 
								value="<?php echo esc_attr( $settings['block_duration'] ); ?>" 
								min="1" max="720" class="small-text">
							<p class="description"><?php esc_html_e( 'How long to block a user after reaching the spam threshold.', 'ai-agent-for-website' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="auto_block_enabled"><?php esc_html_e( 'Auto-Block Users', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="auto_block_enabled" name="auto_block_enabled" value="1" 
								<?php checked( $settings['auto_block_enabled'], true ); ?>>
							<p class="description"><?php esc_html_e( 'Automatically block users who exceed the spam threshold.', 'ai-agent-for-website' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="log_spam_attempts"><?php esc_html_e( 'Log Spam Attempts', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="log_spam_attempts" name="log_spam_attempts" value="1" 
								<?php checked( $settings['log_spam_attempts'], true ); ?>>
							<p class="description"><?php esc_html_e( 'Keep a log of all detected spam messages.', 'ai-agent-for-website' ); ?></p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" name="aiagent_save_spam_settings" class="button button-primary" 
						value="<?php esc_attr_e( 'Save Settings', 'ai-agent-for-website' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle admin actions.
	 */
	private function handle_admin_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in caller.
		$action = isset( $_POST['aiagent_spam_action'] ) ? sanitize_key( $_POST['aiagent_spam_action'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in caller.
		$block_id = isset( $_POST['block_id'] ) ? absint( $_POST['block_id'] ) : 0;

		switch ( $action ) {
			case 'unblock':
				if ( $block_id && self::unblock_user( $block_id ) ) {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'User unblocked successfully.', 'ai-agent-for-website' ) . '</p></div>';
				}
				break;

			case 'block':
				if ( $block_id ) {
					global $wpdb;
					$table = $wpdb->prefix . 'aiagent_spam_blocks';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$record = $wpdb->get_row(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
						$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $block_id )
					);
					if ( $record ) {
						self::block_user( $record->session_id, $record->user_id, $record->ip_address, __( 'Manually blocked by admin', 'ai-agent-for-website' ) );
						echo '<div class="notice notice-success"><p>' . esc_html__( 'User blocked successfully.', 'ai-agent-for-website' ) . '</p></div>';
					}
				}
				break;

			case 'delete':
				if ( $block_id && self::delete_block( $block_id ) ) {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Record deleted successfully.', 'ai-agent-for-website' ) . '</p></div>';
				}
				break;

			case 'clear_old_logs':
				$deleted = self::clear_old_logs( 30 );
				/* translators: %d: Number of deleted log entries */
				echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d old log entries deleted.', 'ai-agent-for-website' ), $deleted ) ) . '</p></div>';
				break;
		}
	}

	/**
	 * Save spam settings from form.
	 */
	private function save_spam_settings() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in render_admin_page().
		$settings = array(
			'enabled'            => isset( $_POST['spam_enabled'] ),
			'spam_threshold'     => isset( $_POST['spam_threshold'] ) ? absint( $_POST['spam_threshold'] ) : self::DEFAULT_SPAM_THRESHOLD,
			'block_duration'     => isset( $_POST['block_duration'] ) ? absint( $_POST['block_duration'] ) : self::DEFAULT_BLOCK_DURATION,
			'auto_block_enabled' => isset( $_POST['auto_block_enabled'] ),
			'log_spam_attempts'  => isset( $_POST['log_spam_attempts'] ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( self::update_settings( $settings ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'ai-agent-for-website' ) . '</p></div>';
		}
	}
}

