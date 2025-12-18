<?php
/**
 * Conversations Manager Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Conversations_Manager
 *
 * Handles conversation management and admin display.
 */
class AIAGENT_Conversations_Manager {

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		global $wpdb;

		$users_table         = $wpdb->prefix . 'aiagent_users';
		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$messages_table      = $wpdb->prefix . 'aiagent_messages';

		// Handle clear actions.
		if ( isset( $_POST['aiagent_clear_action'] ) && check_admin_referer( 'aiagent_clear_chats' ) ) {
			$action = isset( $_POST['aiagent_clear_action'] ) ? sanitize_text_field( wp_unslash( $_POST['aiagent_clear_action'] ) ) : '';
			$result = $this->handle_clear_action( $action );
			if ( $result['success'] ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $result['message'] ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $result['message'] ) . '</p></div>';
			}
		}

		// Check if viewing a specific conversation.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$view_conversation = isset( $_GET['conversation'] ) ? absint( $_GET['conversation'] ) : null;

		if ( $view_conversation ) {
			$this->render_conversation_detail( $view_conversation );
			return;
		}

		// Get stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, count query.
		$total_users = $wpdb->get_var( "SELECT COUNT(*) FROM $users_table" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, count query.
		$total_conversations = $wpdb->get_var( "SELECT COUNT(*) FROM $conversations_table" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, count query.
		$total_messages = $wpdb->get_var( "SELECT COUNT(*) FROM $messages_table" );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom tables with safe interpolation.
		$today_conversations = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $conversations_table WHERE DATE(started_at) = %s",
				current_time( 'Y-m-d' )
			)
		);

		// Get recent conversations with user info.
		$conversations = $wpdb->get_results(
			"SELECT c.*, u.name as user_name, u.email as user_email,
				(SELECT COUNT(*) FROM $messages_table WHERE conversation_id = c.id) as message_count,
				(SELECT content FROM $messages_table WHERE conversation_id = c.id AND role = 'user' ORDER BY id ASC LIMIT 1) as first_message
			FROM $conversations_table c
			LEFT JOIN $users_table u ON c.user_id = u.id
			ORDER BY c.started_at DESC
			LIMIT 50"
		);

		// Get count of ended conversations.
		$ended_count = $wpdb->get_var( "SELECT COUNT(*) FROM $conversations_table WHERE status = 'ended'" );
		$old_count   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $conversations_table WHERE started_at < %s",
				gmdate( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		?>
		<div class="wrap aiagent-admin">
			<h1><?php esc_html_e( 'Conversations', 'ai-agent-for-website' ); ?></h1>

			<!-- Clear Chats Actions -->
			<div class="aiagent-card" style="margin-bottom: 20px;">
				<h2><?php esc_html_e( 'Manage Conversations', 'ai-agent-for-website' ); ?></h2>
				<form method="post" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
					<?php wp_nonce_field( 'aiagent_clear_chats' ); ?>
					
					<div>
						<button type="submit" name="aiagent_clear_action" value="clear_ended" class="button" 
								onclick="return confirm('<?php esc_attr_e( 'Delete all ended conversations? This cannot be undone.', 'ai-agent-for-website' ); ?>');">
							<?php
							/* translators: %d: Number of ended conversations */
							printf( esc_html__( 'Clear Ended Chats (%d)', 'ai-agent-for-website' ), (int) $ended_count );
							?>
						</button>
					</div>
					
					<div>
						<button type="submit" name="aiagent_clear_action" value="clear_old" class="button"
								onclick="return confirm('<?php esc_attr_e( 'Delete conversations older than 30 days? This cannot be undone.', 'ai-agent-for-website' ); ?>');">
							<?php
							/* translators: %d: Number of old conversations */
							printf( esc_html__( 'Clear Old Chats (30+ days: %d)', 'ai-agent-for-website' ), (int) $old_count );
							?>
						</button>
					</div>
					
					<div>
						<button type="submit" name="aiagent_clear_action" value="clear_all" class="button button-link-delete"
								onclick="return confirm('<?php esc_attr_e( 'DELETE ALL conversations and messages? This cannot be undone!', 'ai-agent-for-website' ); ?>');">
							<?php esc_html_e( 'Clear All Chats', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</form>
			</div>

			<div class="aiagent-stats-grid">
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $total_users ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Total Users', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $total_conversations ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Total Conversations', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $total_messages ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Total Messages', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $today_conversations ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Today\'s Conversations', 'ai-agent-for-website' ); ?></div>
				</div>
			</div>

			<div class="aiagent-card">
				<h2><?php esc_html_e( 'Recent Conversations', 'ai-agent-for-website' ); ?></h2>
				
				<?php if ( empty( $conversations ) ) : ?>
					<div class="aiagent-empty-state">
						<p><?php esc_html_e( 'No conversations yet. Conversations will appear here once users start chatting.', 'ai-agent-for-website' ); ?></p>
					</div>
				<?php else : ?>
					<table class="aiagent-conversations-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'User', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'First Message', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Messages', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Rating', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Started', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Status', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'ai-agent-for-website' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $conversations as $conv ) : ?>
								<tr>
									<td>
										<div class="aiagent-user-badge">
											<strong><?php echo esc_html( $conv->user_name ? $conv->user_name : 'Anonymous' ); ?></strong>
										</div>
										<?php if ( $conv->user_email ) : ?>
											<div style="font-size: 12px; color: #666; margin-top: 4px;">
												<?php echo esc_html( $conv->user_email ); ?>
											</div>
										<?php endif; ?>
									</td>
									<td>
										<?php
										$preview = $conv->first_message ? wp_trim_words( $conv->first_message, 10 ) : '-';
										echo esc_html( $preview );
										?>
									</td>
									<td><?php echo esc_html( $conv->message_count ); ?></td>
									<td>
										<?php if ( $conv->rating ) : ?>
											<span class="aiagent-rating-display">
												<?php echo esc_html( str_repeat( '★', $conv->rating ) . str_repeat( '☆', 5 - $conv->rating ) ); ?>
											</span>
										<?php else : ?>
											<span style="color: #999;">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php
										$started = strtotime( $conv->started_at );
										echo esc_html( human_time_diff( $started, time() ) . ' ago' );
										?>
									</td>
									<td>
										<?php if ( 'active' === $conv->status ) : ?>
											<span style="color: #46b450;">●</span> <?php esc_html_e( 'Active', 'ai-agent-for-website' ); ?>
										<?php else : ?>
											<span style="color: #999;">●</span> <?php esc_html_e( 'Ended', 'ai-agent-for-website' ); ?>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-conversations&conversation=' . $conv->id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'ai-agent-for-website' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render conversation detail.
	 *
	 * @param int $conversation_id The conversation ID.
	 */
	private function render_conversation_detail( $conversation_id ) {
		global $wpdb;

		$users_table         = $wpdb->prefix . 'aiagent_users';
		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$messages_table      = $wpdb->prefix . 'aiagent_messages';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom tables with safe interpolation.

		// Get conversation with user info.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, u.name as user_name, u.email as user_email
				FROM $conversations_table c
				LEFT JOIN $users_table u ON c.user_id = u.id
				WHERE c.id = %d",
				$conversation_id
			)
		);

		if ( ! $conversation ) {
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			echo '<div class="wrap"><h1>' . esc_html__( 'Conversation not found', 'ai-agent-for-website' ) . '</h1></div>';
			return;
		}

		// Get messages.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$messages = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $messages_table WHERE conversation_id = %d ORDER BY created_at ASC", $conversation_id )
		);

		?>
		<div class="wrap aiagent-admin">
			<h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-conversations' ) ); ?>" style="text-decoration: none;">
					← <?php esc_html_e( 'Conversations', 'ai-agent-for-website' ); ?>
				</a>
			</h1>

			<div class="aiagent-card">
				<h2>
					<?php esc_html_e( 'Conversation Details', 'ai-agent-for-website' ); ?>
					<?php if ( 'active' === $conversation->status ) : ?>
						<span class="aiagent-badge" style="background: #46b450;"><?php esc_html_e( 'Active', 'ai-agent-for-website' ); ?></span>
					<?php else : ?>
						<span class="aiagent-badge" style="background: #999;"><?php esc_html_e( 'Ended', 'ai-agent-for-website' ); ?></span>
					<?php endif; ?>
				</h2>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'User', 'ai-agent-for-website' ); ?></th>
						<td>
							<strong><?php echo esc_html( $conversation->user_name ? $conversation->user_name : 'Anonymous' ); ?></strong>
							<?php if ( $conversation->user_email ) : ?>
								<br><a href="mailto:<?php echo esc_attr( $conversation->user_email ); ?>"><?php echo esc_html( $conversation->user_email ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Started', 'ai-agent-for-website' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conversation->started_at ) ) ); ?></td>
					</tr>
					<?php if ( $conversation->ended_at ) : ?>
					<tr>
						<th><?php esc_html_e( 'Ended', 'ai-agent-for-website' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conversation->ended_at ) ) ); ?></td>
					</tr>
					<?php endif; ?>
					<tr>
						<th><?php esc_html_e( 'Messages', 'ai-agent-for-website' ); ?></th>
						<td><?php echo esc_html( count( $messages ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rating', 'ai-agent-for-website' ); ?></th>
						<td>
							<?php if ( $conversation->rating ) : ?>
								<span class="aiagent-rating-display" style="color: #ffc107; font-size: 18px;">
									<?php echo esc_html( str_repeat( '★', $conversation->rating ) . str_repeat( '☆', 5 - $conversation->rating ) ); ?>
								</span>
								<span style="color: #666; margin-left: 8px;">(<?php echo esc_html( $conversation->rating ); ?>/5)</span>
							<?php else : ?>
								<span style="color: #999;"><?php esc_html_e( 'Not rated', 'ai-agent-for-website' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="aiagent-card">
				<h2><?php esc_html_e( 'Messages', 'ai-agent-for-website' ); ?></h2>
				
				<?php if ( empty( $messages ) ) : ?>
					<div class="aiagent-empty-state">
						<p><?php esc_html_e( 'No messages in this conversation.', 'ai-agent-for-website' ); ?></p>
					</div>
				<?php else : ?>
					<div class="aiagent-message-log">
						<?php foreach ( $messages as $msg ) : ?>
							<div class="aiagent-message-item <?php echo esc_attr( $msg->role ); ?>">
								<div class="aiagent-message-content">
									<?php echo nl2br( esc_html( $msg->content ) ); ?>
								</div>
								<div class="aiagent-message-meta">
									<?php
									$role_label = 'user' === $msg->role ? ( $conversation->user_name ? $conversation->user_name : __( 'User', 'ai-agent-for-website' ) ) : __( 'AI Assistant', 'ai-agent-for-website' );
									echo esc_html( $role_label );
									?>
									• 
									<?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $msg->created_at ) ) ); ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get all users.
	 *
	 * @param int $limit  Number of users to retrieve.
	 * @param int $offset Offset for pagination.
	 * @return array Array of user objects.
	 */
	public function get_users( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiagent_users';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		return $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset )
		);
	}

	/**
	 * Get user by ID.
	 *
	 * @param int $user_id The user ID.
	 * @return object|null User object or null.
	 */
	public function get_user( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiagent_users';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $user_id )
		);
	}

	/**
	 * Get user by email.
	 *
	 * @param string $email The user email.
	 * @return object|null User object or null.
	 */
	public function get_user_by_email( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiagent_users';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $table WHERE email = %s", $email )
		);
	}

	/**
	 * Get conversations for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of conversation objects.
	 */
	public function get_user_conversations( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiagent_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		return $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY started_at DESC", $user_id )
		);
	}

	/**
	 * Get messages for a conversation.
	 *
	 * @param int $conversation_id The conversation ID.
	 * @return array Array of message objects.
	 */
	public function get_conversation_messages( $conversation_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiagent_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		return $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $table WHERE conversation_id = %d ORDER BY created_at ASC", $conversation_id )
		);
	}

	/**
	 * Handle clear action.
	 *
	 * @param string $action The action to perform.
	 * @return array Result with success status and message.
	 */
	private function handle_clear_action( $action ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$messages_table      = $wpdb->prefix . 'aiagent_messages';

		switch ( $action ) {
			case 'clear_ended':
				// Get ended conversation IDs.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query.
				$ended_ids = $wpdb->get_col( "SELECT id FROM $conversations_table WHERE status = 'ended'" );

				if ( ! empty( $ended_ids ) ) {
					$ids_placeholder = implode( ',', array_map( 'intval', $ended_ids ) );

					// Delete messages first.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause with sanitized IDs.
					$wpdb->query( "DELETE FROM $messages_table WHERE conversation_id IN ($ids_placeholder)" );

					// Delete conversations.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query.
					$deleted = $wpdb->query( "DELETE FROM $conversations_table WHERE status = 'ended'" );

					return [
						'success' => true,
						/* translators: %d: Number of deleted conversations */
						'message' => sprintf( __( '%d ended conversations deleted.', 'ai-agent-for-website' ), $deleted ),
					];
				}

				return [
					'success' => true,
					'message' => __( 'No ended conversations to delete.', 'ai-agent-for-website' ),
				];

			case 'clear_old':
				// Get old conversation IDs (older than 30 days).
				$old_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
				$old_ids = $wpdb->get_col(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
					$wpdb->prepare( "SELECT id FROM $conversations_table WHERE started_at < %s", $old_date )
				);

				if ( ! empty( $old_ids ) ) {
					$ids_placeholder = implode( ',', array_map( 'intval', $old_ids ) );

					// Delete messages first.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause with sanitized IDs.
					$wpdb->query( "DELETE FROM $messages_table WHERE conversation_id IN ($ids_placeholder)" );

					// Delete conversations.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operation.
					$deleted = $wpdb->query(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
						$wpdb->prepare( "DELETE FROM $conversations_table WHERE started_at < %s", $old_date )
					);

					return [
						'success' => true,
						/* translators: %d: Number of deleted conversations */
						'message' => sprintf( __( '%d old conversations deleted.', 'ai-agent-for-website' ), $deleted ),
					];
				}

				return [
					'success' => true,
					'message' => __( 'No old conversations to delete.', 'ai-agent-for-website' ),
				];

			case 'clear_all':
				// Delete all messages.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table truncate.
				$wpdb->query( "TRUNCATE TABLE $messages_table" );

				// Delete all conversations.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table truncate.
				$wpdb->query( "TRUNCATE TABLE $conversations_table" );

				return [
					'success' => true,
					'message' => __( 'All conversations and messages deleted.', 'ai-agent-for-website' ),
				];

			default:
				return [
					'success' => false,
					'message' => __( 'Invalid action.', 'ai-agent-for-website' ),
				];
		}
	}
}
