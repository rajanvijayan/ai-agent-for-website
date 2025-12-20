<?php
/**
 * Notification Manager Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Notification_Manager
 *
 * Handles admin notifications for new conversations, AI-powered lead validation,
 * and notification settings management.
 */
class AIAGENT_Notification_Manager {

	/**
	 * Option key for notification settings.
	 *
	 * @var string
	 */
	const SETTINGS_KEY = 'aiagent_notification_settings';

	/**
	 * Notification types.
	 */
	const TYPE_NEW_CONVERSATION    = 'new_conversation';
	const TYPE_LEAD_VALIDATED      = 'lead_validated';
	const TYPE_LEAD_CONVERTED      = 'lead_converted';
	const TYPE_CONVERSATION_CLOSED = 'conversation_closed';

	/**
	 * Notification statuses.
	 */
	const STATUS_UNREAD = 'unread';
	const STATUS_READ   = 'read';

	/**
	 * Get notification settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		$defaults = [
			'enabled'                    => true,
			'email_notifications'        => true,
			'email_recipients'           => get_option( 'admin_email' ),
			'notify_new_conversation'    => true,
			'notify_lead_validated'      => true,
			'notify_lead_converted'      => true,
			'notify_conversation_closed' => false,
			'auto_validate_leads'        => true,
			'auto_close_inactive'        => false,
			'inactive_hours'             => 24,
			'validation_prompt'          => '',
		];

		$settings = get_option( self::SETTINGS_KEY, [] );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update notification settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool True on success.
	 */
	public static function update_settings( $settings ) {
		return update_option( self::SETTINGS_KEY, $settings );
	}

	/**
	 * Create a notification.
	 *
	 * @param string $type        Notification type.
	 * @param string $title       Notification title.
	 * @param string $message     Notification message.
	 * @param array  $meta        Additional metadata.
	 * @return int|false Notification ID on success, false on failure.
	 */
	public function create_notification( $type, $title, $message, $meta = [] ) {
		global $wpdb;

		$settings = self::get_settings();

		if ( ! $settings['enabled'] ) {
			return false;
		}

		$notifications_table = $wpdb->prefix . 'aiagent_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional direct insert.
		$result = $wpdb->insert(
			$notifications_table,
			[
				'type'       => $type,
				'title'      => $title,
				'message'    => $message,
				'meta'       => wp_json_encode( $meta ),
				'status'     => self::STATUS_UNREAD,
				'created_at' => current_time( 'mysql' ),
			]
		);

		if ( ! $result ) {
			return false;
		}

		$notification_id = $wpdb->insert_id;

		// Send email notification if enabled.
		if ( $settings['email_notifications'] ) {
			$this->send_email_notification( $type, $title, $message, $meta );
		}

		// Log the notification.
		$this->log_notification( $type, $title, $meta );

		return $notification_id;
	}

	/**
	 * Send email notification.
	 *
	 * @param string $type    Notification type.
	 * @param string $title   Notification title.
	 * @param string $message Notification message.
	 * @param array  $meta    Additional metadata (reserved for future use).
	 */
	private function send_email_notification( $type, $title, $message, $meta ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for future use.
		$settings   = self::get_settings();
		$recipients = $settings['email_recipients'];

		if ( empty( $recipients ) ) {
			return;
		}

		$type_labels = [
			self::TYPE_NEW_CONVERSATION    => __( 'New Conversation', 'ai-agent-for-website' ),
			self::TYPE_LEAD_VALIDATED      => __( 'Lead Validated', 'ai-agent-for-website' ),
			self::TYPE_LEAD_CONVERTED      => __( 'Lead Converted', 'ai-agent-for-website' ),
			self::TYPE_CONVERSATION_CLOSED => __( 'Conversation Closed', 'ai-agent-for-website' ),
		];

		$type_label = $type_labels[ $type ] ?? $type;

		/* translators: 1: Site name, 2: Notification type */
		$subject = sprintf( __( '[%1$s] AI Agent: %2$s', 'ai-agent-for-website' ), get_bloginfo( 'name' ), $type_label );

		$body = sprintf(
			/* translators: 1: Title, 2: Message, 3: Admin URL */
			__( "%1\$s\n\n%2\$s\n\nView in admin: %3\$s", 'ai-agent-for-website' ),
			$title,
			$message,
			admin_url( 'admin.php?page=ai-agent-notifications' )
		);

		wp_mail( $recipients, $subject, $body );
	}

	/**
	 * Log notification for activity tracking.
	 *
	 * @param string $type  Notification type.
	 * @param string $title Notification title.
	 * @param array  $meta  Additional metadata.
	 */
	private function log_notification( $type, $title, $meta ) {
		if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			$log_manager = new AIAGENT_Activity_Log_Manager();
			$log_manager->log( 'notification', $type, $title, $meta );
		}
	}

	/**
	 * Get notifications with pagination.
	 *
	 * @param int    $page     Page number.
	 * @param int    $per_page Number of items per page.
	 * @param string $status   Filter by status.
	 * @param string $type     Filter by type.
	 * @return array Array containing notifications and total count.
	 */
	public function get_notifications( $page = 1, $per_page = 20, $status = '', $type = '' ) {
		global $wpdb;

		$notifications_table = $wpdb->prefix . 'aiagent_notifications';
		$offset              = ( $page - 1 ) * $per_page;

		$where_clauses = [];
		$where_values  = [];

		if ( ! empty( $status ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $status;
		}

		if ( ! empty( $type ) ) {
			$where_clauses[] = 'type = %s';
			$where_values[]  = $type;
		}

		$where = '';
		if ( ! empty( $where_clauses ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables with dynamic where clause.
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM $notifications_table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
				array_merge( $where_values, [ $per_page, $offset ] )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM $notifications_table ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		$notifications = $wpdb->get_results( $query );

		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare(
				"SELECT COUNT(*) FROM $notifications_table $where",
				$where_values
			);
		} else {
			$count_query = "SELECT COUNT(*) FROM $notifications_table";
		}

		$total = $wpdb->get_var( $count_query );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// Decode meta for each notification.
		foreach ( $notifications as &$notification ) {
			$notification->meta = json_decode( $notification->meta, true );
		}

		return [
			'notifications' => $notifications,
			'total'         => (int) $total,
			'pages'         => ceil( $total / $per_page ),
		];
	}

	/**
	 * Get unread notification count.
	 *
	 * @return int Unread count.
	 */
	public function get_unread_count() {
		global $wpdb;

		$notifications_table = $wpdb->prefix . 'aiagent_notifications';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $notifications_table WHERE status = %s",
				self::STATUS_UNREAD
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Mark notification as read.
	 *
	 * @param int $notification_id Notification ID.
	 * @return bool True on success.
	 */
	public function mark_as_read( $notification_id ) {
		global $wpdb;

		$notifications_table = $wpdb->prefix . 'aiagent_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update.
		$result = $wpdb->update(
			$notifications_table,
			[ 'status' => self::STATUS_READ ],
			[ 'id' => $notification_id ]
		);

		return false !== $result;
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @return bool True on success.
	 */
	public function mark_all_as_read() {
		global $wpdb;

		$notifications_table = $wpdb->prefix . 'aiagent_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table update.
		$result = $wpdb->update(
			$notifications_table,
			[ 'status' => self::STATUS_READ ],
			[ 'status' => self::STATUS_UNREAD ]
		);

		return false !== $result;
	}

	/**
	 * Delete a notification.
	 *
	 * @param int $notification_id Notification ID.
	 * @return bool True on success.
	 */
	public function delete_notification( $notification_id ) {
		global $wpdb;

		$notifications_table = $wpdb->prefix . 'aiagent_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct delete.
		$result = $wpdb->delete(
			$notifications_table,
			[ 'id' => $notification_id ]
		);

		return false !== $result;
	}

	/**
	 * Notify about a new conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $user_name       User name.
	 * @param string $user_email      User email.
	 */
	public function notify_new_conversation( $conversation_id, $user_name, $user_email ) {
		$settings = self::get_settings();

		if ( ! $settings['notify_new_conversation'] ) {
			return;
		}

		/* translators: %s: User name */
		$title = sprintf( __( 'New conversation from %s', 'ai-agent-for-website' ), $user_name );

		$message = sprintf(
			/* translators: 1: User name, 2: User email */
			__( 'A new conversation has been started by %1$s (%2$s).', 'ai-agent-for-website' ),
			$user_name,
			$user_email
		);

		$this->create_notification(
			self::TYPE_NEW_CONVERSATION,
			$title,
			$message,
			[
				'conversation_id' => $conversation_id,
				'user_name'       => $user_name,
				'user_email'      => $user_email,
			]
		);

		// Trigger Zapier webhook.
		if ( class_exists( 'AIAGENT_Zapier_Integration' ) && AIAGENT_Zapier_Integration::is_enabled() ) {
			AIAGENT_Zapier_Integration::send_webhook(
				[
					'conversation_id' => $conversation_id,
					'user_name'       => $user_name,
					'user_email'      => $user_email,
				],
				'conversation_started'
			);
		}
	}

	/**
	 * Validate a conversation using AI and convert to lead if valid.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array Validation result with status and recommendation.
	 */
	public function validate_conversation_with_ai( $conversation_id ) {
		global $wpdb;

		$settings     = AI_Agent_For_Website::get_settings();
		$not_settings = self::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return [
				'success' => false,
				'error'   => __( 'API key not configured.', 'ai-agent-for-website' ),
			];
		}

		// Get conversation messages.
		$messages_table      = $wpdb->prefix . 'aiagent_messages';
		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$users_table         = $wpdb->prefix . 'aiagent_users';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, u.name, u.email, u.phone
				FROM $conversations_table c
				LEFT JOIN $users_table u ON c.user_id = u.id
				WHERE c.id = %d",
				$conversation_id
			)
		);

		if ( ! $conversation ) {
			return [
				'success' => false,
				'error'   => __( 'Conversation not found.', 'ai-agent-for-website' ),
			];
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM $messages_table WHERE conversation_id = %d ORDER BY id ASC",
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $messages ) ) {
			return [
				'success' => false,
				'error'   => __( 'No messages in conversation.', 'ai-agent-for-website' ),
			];
		}

		// Format conversation for AI.
		$conversation_text = '';
		foreach ( $messages as $msg ) {
			$role               = 'user' === $msg->role ? 'User' : 'Assistant';
			$conversation_text .= sprintf( "%s: %s\n", $role, $msg->content );
		}

		try {
			$ai = \AIEngine\AIEngine::create( 'groq', $settings['api_key'] );

			$custom_prompt = ! empty( $not_settings['validation_prompt'] ) ? $not_settings['validation_prompt'] : '';

			$prompt = sprintf(
				'Analyze this customer conversation and determine if the visitor is a qualified lead or prospect.

%s

Conversation:
%s

User Info:
- Name: %s
- Email: %s
- Phone: %s

Evaluate based on:
1. Does the user show genuine interest in products/services?
2. Did they ask specific questions about pricing, availability, or features?
3. Did they provide contact information willingly?
4. Is there buying intent or just casual browsing?
5. Are they a potential customer or just seeking free support?

Respond ONLY with a JSON object in this exact format:
{
    "is_qualified": true or false,
    "confidence": "high", "medium", or "low",
    "recommendation": "convert_lead", "follow_up", or "close",
    "reason": "Brief explanation of your decision"
}',
				$custom_prompt,
				$conversation_text,
				$conversation->name ?? 'Unknown',
				$conversation->email ?? 'Unknown',
				$conversation->phone ?? 'Not provided'
			);

			$response = $ai->generateContent( $prompt );

			// Parse AI response.
			$response = trim( $response );
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*/', '', $response );

			$result = json_decode( $response, true );

			if ( ! is_array( $result ) ) {
				return [
					'success' => false,
					'error'   => __( 'Could not parse AI response.', 'ai-agent-for-website' ),
				];
			}

			// Log the validation.
			if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
				$log_manager = new AIAGENT_Activity_Log_Manager();
				$log_manager->log(
					'ai_validation',
					'conversation_validated',
					sprintf(
						/* translators: %d: Conversation ID */
						__( 'Conversation #%d validated by AI', 'ai-agent-for-website' ),
						$conversation_id
					),
					array_merge( $result, [ 'conversation_id' => $conversation_id ] )
				);
			}

			return [
				'success'        => true,
				'is_qualified'   => $result['is_qualified'] ?? false,
				'confidence'     => $result['confidence'] ?? 'low',
				'recommendation' => $result['recommendation'] ?? 'close',
				'reason'         => $result['reason'] ?? '',
			];

		} catch ( Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Convert a conversation to lead using AI validation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array Result array.
	 */
	public function convert_conversation_to_lead( $conversation_id ) {
		// First validate with AI.
		$validation = $this->validate_conversation_with_ai( $conversation_id );

		if ( ! $validation['success'] ) {
			return $validation;
		}

		$leads_manager = new AIAGENT_Leads_Manager();

		$notes = sprintf(
			/* translators: 1: Confidence level, 2: AI reason */
			__( "AI Validation: %1\$s confidence\nReason: %2\$s", 'ai-agent-for-website' ),
			ucfirst( $validation['confidence'] ),
			$validation['reason']
		);

		$lead_id = $leads_manager->convert_conversation_to_lead( $conversation_id, $notes );

		if ( ! $lead_id ) {
			return [
				'success' => false,
				'error'   => __( 'Failed to create lead.', 'ai-agent-for-website' ),
			];
		}

		// Create notification.
		$settings = self::get_settings();
		if ( $settings['notify_lead_converted'] ) {
			$lead = $leads_manager->get_lead( $lead_id );

			/* translators: %s: User name */
			$title = sprintf( __( 'Lead converted: %s', 'ai-agent-for-website' ), $lead->name ?? 'Unknown' );

			$this->create_notification(
				self::TYPE_LEAD_CONVERTED,
				$title,
				$validation['reason'],
				[
					'lead_id'         => $lead_id,
					'conversation_id' => $conversation_id,
					'confidence'      => $validation['confidence'],
				]
			);
		}

		return [
			'success'    => true,
			'lead_id'    => $lead_id,
			'validation' => $validation,
		];
	}

	/**
	 * Close a conversation using AI validation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $reason          Reason for closing.
	 * @return array Result array.
	 */
	public function close_conversation( $conversation_id, $reason = '' ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$users_table         = $wpdb->prefix . 'aiagent_users';

		// Get conversation details.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, u.name, u.email
				FROM $conversations_table c
				LEFT JOIN $users_table u ON c.user_id = u.id
				WHERE c.id = %d",
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $conversation ) {
			return [
				'success' => false,
				'error'   => __( 'Conversation not found.', 'ai-agent-for-website' ),
			];
		}

		// Update conversation status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update.
		$result = $wpdb->update(
			$conversations_table,
			[
				'status'   => 'closed',
				'ended_at' => current_time( 'mysql' ),
			],
			[ 'id' => $conversation_id ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'error'   => __( 'Failed to close conversation.', 'ai-agent-for-website' ),
			];
		}

		// Create notification.
		$settings = self::get_settings();
		if ( $settings['notify_conversation_closed'] ) {
			/* translators: %s: User name */
			$title = sprintf( __( 'Conversation closed: %s', 'ai-agent-for-website' ), $conversation->name ?? 'Unknown' );

			$this->create_notification(
				self::TYPE_CONVERSATION_CLOSED,
				$title,
				$reason,
				[
					'conversation_id' => $conversation_id,
					'user_email'      => $conversation->email ?? '',
				]
			);
		}

		// Log the closure.
		if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			$log_manager = new AIAGENT_Activity_Log_Manager();
			$log_manager->log(
				'conversation',
				'closed',
				sprintf(
					/* translators: %d: Conversation ID */
					__( 'Conversation #%d closed', 'ai-agent-for-website' ),
					$conversation_id
				),
				[
					'conversation_id' => $conversation_id,
					'reason'          => $reason,
				]
			);
		}

		// Trigger Zapier webhook.
		if ( class_exists( 'AIAGENT_Zapier_Integration' ) && AIAGENT_Zapier_Integration::is_enabled() ) {
			AIAGENT_Zapier_Integration::send_webhook(
				[
					'conversation_id' => $conversation_id,
					'user_name'       => $conversation->name ?? 'Unknown',
					'user_email'      => $conversation->email ?? '',
					'reason'          => $reason,
				],
				'conversation_ended'
			);
		}

		return [
			'success' => true,
			'message' => __( 'Conversation closed successfully.', 'ai-agent-for-website' ),
		];
	}

	/**
	 * Render admin page for notifications.
	 */
	public function render_admin_page() {
		// Handle actions.
		$this->handle_notification_actions();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$type_filter = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';

		$notifications_data = $this->get_notifications( $current_page, 20, $status_filter, $type_filter );
		$unread_count       = $this->get_unread_count();
		?>
		<div class="wrap aiagent-admin">
			<h1>
				<?php esc_html_e( 'Notification Center', 'ai-agent-for-website' ); ?>
				<?php if ( $unread_count > 0 ) : ?>
					<span class="aiagent-badge"><?php echo esc_html( $unread_count ); ?></span>
				<?php endif; ?>
			</h1>

			<!-- Filter Bar -->
			<div class="aiagent-card" style="margin-bottom: 20px;">
				<div class="aiagent-filter-bar">
					<span class="aiagent-filter-label"><?php esc_html_e( 'Status:', 'ai-agent-for-website' ); ?></span>
					<?php
					$statuses = [
						''                  => __( 'All', 'ai-agent-for-website' ),
						self::STATUS_UNREAD => __( 'Unread', 'ai-agent-for-website' ),
						self::STATUS_READ   => __( 'Read', 'ai-agent-for-website' ),
					];
					foreach ( $statuses as $key => $label ) :
						$url   = add_query_arg(
							[
								'status' => $key,
								'type'   => $type_filter,
								'paged'  => 1,
							],
							admin_url( 'admin.php?page=ai-agent-notifications' )
						);
						$class = $status_filter === $key ? 'button-primary' : 'button';
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="button <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>

					<span class="aiagent-filter-label" style="margin-left: 20px;"><?php esc_html_e( 'Type:', 'ai-agent-for-website' ); ?></span>
					<?php
					$types = [
						''                             => __( 'All', 'ai-agent-for-website' ),
						self::TYPE_NEW_CONVERSATION    => __( 'New Conversation', 'ai-agent-for-website' ),
						self::TYPE_LEAD_CONVERTED      => __( 'Lead Converted', 'ai-agent-for-website' ),
						self::TYPE_CONVERSATION_CLOSED => __( 'Closed', 'ai-agent-for-website' ),
					];
					foreach ( $types as $key => $label ) :
						$url   = add_query_arg(
							[
								'status' => $status_filter,
								'type'   => $key,
								'paged'  => 1,
							],
							admin_url( 'admin.php?page=ai-agent-notifications' )
						);
						$class = $type_filter === $key ? 'button-primary' : 'button';
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="button <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>

					<?php if ( $unread_count > 0 ) : ?>
						<form method="post" style="display: inline; margin-left: 20px;">
							<?php wp_nonce_field( 'aiagent_notification_action' ); ?>
							<button type="submit" name="aiagent_mark_all_read" value="1" class="button">
								<?php esc_html_e( 'Mark All as Read', 'ai-agent-for-website' ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<div class="aiagent-card">
				<h2><?php esc_html_e( 'Notifications', 'ai-agent-for-website' ); ?></h2>

				<?php if ( empty( $notifications_data['notifications'] ) ) : ?>
					<div class="aiagent-empty-state">
						<p><?php esc_html_e( 'No notifications yet.', 'ai-agent-for-website' ); ?></p>
					</div>
				<?php else : ?>
					<table class="aiagent-conversations-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Type', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Title', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Message', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Time', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'ai-agent-for-website' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $notifications_data['notifications'] as $notification ) : ?>
								<tr class="<?php echo 'unread' === $notification->status ? 'aiagent-notification-unread' : ''; ?>">
									<td>
										<span class="aiagent-status-dot aiagent-status-<?php echo esc_attr( $notification->status ); ?>"></span>
									</td>
									<td>
										<span class="aiagent-notification-type aiagent-type-<?php echo esc_attr( $notification->type ); ?>">
											<?php echo esc_html( $this->get_type_label( $notification->type ) ); ?>
										</span>
									</td>
									<td><strong><?php echo esc_html( $notification->title ); ?></strong></td>
									<td><?php echo esc_html( wp_trim_words( $notification->message, 15 ) ); ?></td>
									<td><?php echo esc_html( human_time_diff( strtotime( $notification->created_at ), time() ) . ' ago' ); ?></td>
									<td>
										<form method="post" style="display: inline;">
											<?php wp_nonce_field( 'aiagent_notification_action' ); ?>
											<input type="hidden" name="notification_id" value="<?php echo esc_attr( $notification->id ); ?>">
											<?php if ( 'unread' === $notification->status ) : ?>
												<button type="submit" name="aiagent_mark_read" value="1" class="button button-small">
													<?php esc_html_e( 'Mark Read', 'ai-agent-for-website' ); ?>
												</button>
											<?php endif; ?>
											<button type="submit" name="aiagent_delete_notification" value="1" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Delete this notification?', 'ai-agent-for-website' ); ?>');">
												<?php esc_html_e( 'Delete', 'ai-agent-for-website' ); ?>
											</button>
										</form>
										<?php if ( ! empty( $notification->meta['conversation_id'] ) ) : ?>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-conversations&conversation=' . $notification->meta['conversation_id'] ) ); ?>" class="button button-small">
												<?php esc_html_e( 'View', 'ai-agent-for-website' ); ?>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $notifications_data['pages'] > 1 ) : ?>
						<div class="aiagent-pagination">
							<?php
							$base_url = add_query_arg(
								[
									'status' => $status_filter,
									'type'   => $type_filter,
								],
								admin_url( 'admin.php?page=ai-agent-notifications' )
							);
							for ( $i = 1; $i <= $notifications_data['pages']; $i++ ) :
								$class = $i === $current_page ? 'button-primary' : 'button';
								?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>" class="button <?php echo esc_attr( $class ); ?>">
									<?php echo esc_html( $i ); ?>
								</a>
							<?php endfor; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get type label.
	 *
	 * @param string $type Notification type.
	 * @return string Type label.
	 */
	private function get_type_label( $type ) {
		$labels = [
			self::TYPE_NEW_CONVERSATION    => __( 'New Conversation', 'ai-agent-for-website' ),
			self::TYPE_LEAD_VALIDATED      => __( 'Lead Validated', 'ai-agent-for-website' ),
			self::TYPE_LEAD_CONVERTED      => __( 'Lead Converted', 'ai-agent-for-website' ),
			self::TYPE_CONVERSATION_CLOSED => __( 'Closed', 'ai-agent-for-website' ),
		];

		return $labels[ $type ] ?? $type;
	}

	/**
	 * Handle notification actions from form submission.
	 */
	private function handle_notification_actions() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'aiagent_notification_action' ) ) {
			return;
		}

		if ( isset( $_POST['aiagent_mark_all_read'] ) ) {
			$this->mark_all_as_read();
			return;
		}

		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;

		if ( ! $notification_id ) {
			return;
		}

		if ( isset( $_POST['aiagent_mark_read'] ) ) {
			$this->mark_as_read( $notification_id );
		}

		if ( isset( $_POST['aiagent_delete_notification'] ) ) {
			$this->delete_notification( $notification_id );
		}
	}
}

