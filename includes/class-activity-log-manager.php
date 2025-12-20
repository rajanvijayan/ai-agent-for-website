<?php
/**
 * Activity Log Manager Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Activity_Log_Manager
 *
 * Handles activity logging for conversations, leads, notifications,
 * and integrations with Zapier and Mailchimp.
 */
class AIAGENT_Activity_Log_Manager {

	/**
	 * Option key for log settings.
	 *
	 * @var string
	 */
	const SETTINGS_KEY = 'aiagent_log_settings';

	/**
	 * Log categories.
	 */
	const CATEGORY_CONVERSATION  = 'conversation';
	const CATEGORY_LEAD          = 'lead';
	const CATEGORY_NOTIFICATION  = 'notification';
	const CATEGORY_AI_VALIDATION = 'ai_validation';
	const CATEGORY_INTEGRATION   = 'integration';
	const CATEGORY_USER          = 'user';
	const CATEGORY_SYSTEM        = 'system';

	/**
	 * Get log settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		$defaults = [
			'enabled'               => true,
			'log_conversations'     => true,
			'log_leads'             => true,
			'log_notifications'     => true,
			'log_ai_validations'    => true,
			'log_integrations'      => true,
			'log_user_actions'      => true,
			'log_system_events'     => true,
			'retention_days'        => 90,
			'export_to_zapier'      => false,
			'zapier_log_webhook'    => '',
			'export_to_mailchimp'   => false,
			'mailchimp_log_list_id' => '',
		];

		$settings = get_option( self::SETTINGS_KEY, [] );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update log settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool True on success.
	 */
	public static function update_settings( $settings ) {
		return update_option( self::SETTINGS_KEY, $settings );
	}

	/**
	 * Log an activity.
	 *
	 * @param string $category  Log category.
	 * @param string $action    Action type.
	 * @param string $message   Log message.
	 * @param array  $meta      Additional metadata.
	 * @param int    $user_id   WordPress user ID (optional).
	 * @return int|false Log ID on success, false on failure.
	 */
	public function log( $category, $action, $message, $meta = [], $user_id = null ) {
		global $wpdb;

		$settings = self::get_settings();

		if ( ! $settings['enabled'] ) {
			return false;
		}

		// Check if this category is enabled.
		$category_map = [
			self::CATEGORY_CONVERSATION  => 'log_conversations',
			self::CATEGORY_LEAD          => 'log_leads',
			self::CATEGORY_NOTIFICATION  => 'log_notifications',
			self::CATEGORY_AI_VALIDATION => 'log_ai_validations',
			self::CATEGORY_INTEGRATION   => 'log_integrations',
			self::CATEGORY_USER          => 'log_user_actions',
			self::CATEGORY_SYSTEM        => 'log_system_events',
		];

		$setting_key = $category_map[ $category ] ?? 'log_system_events';
		if ( empty( $settings[ $setting_key ] ) ) {
			return false;
		}

		// Get user ID if not provided.
		if ( null === $user_id ) {
			$current_user = wp_get_current_user();
			$user_id      = $current_user->ID > 0 ? $current_user->ID : null;
		}

		$logs_table = $wpdb->prefix . 'aiagent_activity_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional direct insert.
		$result = $wpdb->insert(
			$logs_table,
			[
				'category'   => $category,
				'action'     => $action,
				'message'    => $message,
				'meta'       => wp_json_encode( $meta ),
				'user_id'    => $user_id,
				'ip_address' => $this->get_client_ip(),
				'created_at' => current_time( 'mysql' ),
			]
		);

		if ( ! $result ) {
			return false;
		}

		$log_id = $wpdb->insert_id;

		// Export to external services if enabled.
		$this->export_log( $log_id, $category, $action, $message, $meta );

		return $log_id;
	}

	/**
	 * Export log to external services.
	 *
	 * @param int    $log_id   Log ID.
	 * @param string $category Log category.
	 * @param string $action   Action type.
	 * @param string $message  Log message.
	 * @param array  $meta     Additional metadata.
	 */
	private function export_log( $log_id, $category, $action, $message, $meta ) {
		$settings = self::get_settings();

		// Export to Zapier.
		if ( $settings['export_to_zapier'] && ! empty( $settings['zapier_log_webhook'] ) ) {
			$this->send_to_zapier( $log_id, $category, $action, $message, $meta );
		}

		// Export to Mailchimp (for specific events).
		if ( $settings['export_to_mailchimp'] && ! empty( $settings['mailchimp_log_list_id'] ) ) {
			$this->send_to_mailchimp( $category, $action, $meta );
		}
	}

	/**
	 * Send log to Zapier webhook.
	 *
	 * @param int    $log_id   Log ID.
	 * @param string $category Log category.
	 * @param string $action   Action type.
	 * @param string $message  Log message.
	 * @param array  $meta     Additional metadata.
	 */
	private function send_to_zapier( $log_id, $category, $action, $message, $meta ) {
		$settings = self::get_settings();

		$payload = [
			'log_id'    => $log_id,
			'category'  => $category,
			'action'    => $action,
			'message'   => $message,
			'meta'      => $meta,
			'timestamp' => current_time( 'mysql' ),
			'site_name' => get_bloginfo( 'name' ),
			'site_url'  => home_url(),
		];

		wp_remote_post(
			$settings['zapier_log_webhook'],
			[
				'body'        => wp_json_encode( $payload ),
				'headers'     => [ 'Content-Type' => 'application/json' ],
				'timeout'     => 15,
				'blocking'    => false,
				'data_format' => 'body',
			]
		);
	}

	/**
	 * Send log to Mailchimp (for user events).
	 *
	 * @param string $category Log category.
	 * @param string $action   Action type.
	 * @param array  $meta     Additional metadata.
	 */
	private function send_to_mailchimp( $category, $action, $meta ) {
		// Only send user-related events to Mailchimp.
		if ( ! in_array( $category, [ self::CATEGORY_USER, self::CATEGORY_LEAD ], true ) ) {
			return;
		}

		if ( ! class_exists( 'AIAGENT_Mailchimp_Integration' ) || ! AIAGENT_Mailchimp_Integration::is_enabled() ) {
			return;
		}

		// Add tags based on action.
		$email = $meta['user_email'] ?? $meta['email'] ?? '';
		if ( empty( $email ) ) {
			return;
		}

		$tag = sprintf( 'AI Agent - %s - %s', ucfirst( $category ), ucfirst( $action ) );
		AIAGENT_Mailchimp_Integration::update_subscriber_tags( $email, [ $tag ] );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Get logs with pagination.
	 *
	 * @param int    $page      Page number.
	 * @param int    $per_page  Number of items per page.
	 * @param string $category  Filter by category.
	 * @param string $date_from Filter from date (Y-m-d format).
	 * @param string $date_to   Filter to date (Y-m-d format).
	 * @return array Array containing logs and total count.
	 */
	public function get_logs( $page = 1, $per_page = 50, $category = '', $date_from = '', $date_to = '' ) {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'aiagent_activity_logs';
		$offset     = ( $page - 1 ) * $per_page;

		$where_clauses = [];
		$where_values  = [];

		if ( ! empty( $category ) ) {
			$where_clauses[] = 'category = %s';
			$where_values[]  = $category;
		}

		if ( ! empty( $date_from ) ) {
			$where_clauses[] = 'DATE(created_at) >= %s';
			$where_values[]  = $date_from;
		}

		if ( ! empty( $date_to ) ) {
			$where_clauses[] = 'DATE(created_at) <= %s';
			$where_values[]  = $date_to;
		}

		$where = '';
		if ( ! empty( $where_clauses ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables with dynamic where clause.
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM $logs_table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
				array_merge( $where_values, [ $per_page, $offset ] )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		$logs = $wpdb->get_results( $query );

		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare(
				"SELECT COUNT(*) FROM $logs_table $where",
				$where_values
			);
		} else {
			$count_query = "SELECT COUNT(*) FROM $logs_table";
		}

		$total = $wpdb->get_var( $count_query );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// Decode meta for each log.
		foreach ( $logs as &$log ) {
			$log->meta = json_decode( $log->meta, true );
		}

		return [
			'logs'  => $logs,
			'total' => (int) $total,
			'pages' => ceil( $total / $per_page ),
		];
	}

	/**
	 * Get log statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics() {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'aiagent_activity_logs';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $logs_table" );

		$today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $logs_table WHERE DATE(created_at) = %s",
				current_time( 'Y-m-d' )
			)
		);

		$this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $logs_table WHERE created_at >= %s",
				gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) )
			)
		);

		$by_category = $wpdb->get_results(
			"SELECT category, COUNT(*) as count FROM $logs_table GROUP BY category ORDER BY count DESC"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$category_counts = [];
		foreach ( $by_category as $row ) {
			$category_counts[ $row->category ] = (int) $row->count;
		}

		return [
			'total'       => $total,
			'today'       => $today,
			'this_week'   => $this_week,
			'by_category' => $category_counts,
		];
	}

	/**
	 * Delete old logs based on retention period.
	 *
	 * @return int Number of deleted logs.
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$settings       = self::get_settings();
		$retention_days = (int) $settings['retention_days'];

		if ( $retention_days <= 0 ) {
			return 0;
		}

		$logs_table  = $wpdb->prefix . 'aiagent_activity_logs';
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( sprintf( '-%d days', $retention_days ) ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Intentional direct delete.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $logs_table WHERE created_at < %s",
				$cutoff_date
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $deleted > 0 ) {
			$this->log(
				self::CATEGORY_SYSTEM,
				'logs_cleanup',
				sprintf(
					/* translators: %d: Number of deleted logs */
					__( 'Cleaned up %d old log entries', 'ai-agent-for-website' ),
					$deleted
				),
				[ 'retention_days' => $retention_days ]
			);
		}

		return $deleted ? (int) $deleted : 0;
	}

	/**
	 * Export logs to CSV.
	 *
	 * @param string $category  Filter by category.
	 * @param string $date_from Filter from date.
	 * @param string $date_to   Filter to date.
	 * @return string CSV content.
	 */
	public function export_to_csv( $category = '', $date_from = '', $date_to = '' ) {
		$logs_data = $this->get_logs( 1, 10000, $category, $date_from, $date_to );

		$csv = "ID,Category,Action,Message,User ID,IP Address,Created At,Meta\n";

		foreach ( $logs_data['logs'] as $log ) {
			$csv .= sprintf(
				'%d,"%s","%s","%s",%s,"%s","%s","%s"' . "\n",
				$log->id,
				$log->category,
				$log->action,
				str_replace( '"', '""', $log->message ),
				$log->user_id ?? '',
				$log->ip_address,
				$log->created_at,
				str_replace( '"', '""', wp_json_encode( $log->meta ) )
			);
		}

		return $csv;
	}

	/**
	 * Render admin page for logs.
	 */
	public function render_admin_page() {
		// Handle actions.
		$this->handle_log_actions();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$category_filter = isset( $_GET['category'] ) ? sanitize_key( $_GET['category'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		$logs_data = $this->get_logs( $current_page, 50, $category_filter, $date_from, $date_to );
		$stats     = $this->get_statistics();
		?>
		<div class="wrap aiagent-admin">
			<h1><?php esc_html_e( 'Activity Log Center', 'ai-agent-for-website' ); ?></h1>

			<!-- Statistics -->
			<div class="aiagent-stats-grid">
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $stats['total'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Total Logs', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $stats['today'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Today', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $stats['this_week'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'This Week', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( count( $stats['by_category'] ) ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Categories', 'ai-agent-for-website' ); ?></div>
				</div>
			</div>

			<!-- Filter Bar -->
			<div class="aiagent-card" style="margin-bottom: 20px;">
				<form method="get" class="aiagent-filter-form">
					<input type="hidden" name="page" value="ai-agent-logs">

					<div class="aiagent-filter-row">
						<div class="aiagent-filter-field">
							<label for="category"><?php esc_html_e( 'Category:', 'ai-agent-for-website' ); ?></label>
							<select name="category" id="category">
								<option value=""><?php esc_html_e( 'All Categories', 'ai-agent-for-website' ); ?></option>
								<?php
								$categories = [
									self::CATEGORY_CONVERSATION => __( 'Conversation', 'ai-agent-for-website' ),
									self::CATEGORY_LEAD   => __( 'Lead', 'ai-agent-for-website' ),
									self::CATEGORY_NOTIFICATION => __( 'Notification', 'ai-agent-for-website' ),
									self::CATEGORY_AI_VALIDATION => __( 'AI Validation', 'ai-agent-for-website' ),
									self::CATEGORY_INTEGRATION => __( 'Integration', 'ai-agent-for-website' ),
									self::CATEGORY_USER   => __( 'User', 'ai-agent-for-website' ),
									self::CATEGORY_SYSTEM => __( 'System', 'ai-agent-for-website' ),
								];
								foreach ( $categories as $key => $label ) :
									?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $category_filter, $key ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="aiagent-filter-field">
							<label for="date_from"><?php esc_html_e( 'From:', 'ai-agent-for-website' ); ?></label>
							<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>">
						</div>

						<div class="aiagent-filter-field">
							<label for="date_to"><?php esc_html_e( 'To:', 'ai-agent-for-website' ); ?></label>
							<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>">
						</div>

						<div class="aiagent-filter-field">
							<label>&nbsp;</label>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'ai-agent-for-website' ); ?></button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-logs' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'ai-agent-for-website' ); ?></a>
						</div>
					</div>
				</form>

				<div class="aiagent-export-bar" style="margin-top: 15px;">
					<form method="post" style="display: inline;">
						<?php wp_nonce_field( 'aiagent_log_action' ); ?>
						<input type="hidden" name="category" value="<?php echo esc_attr( $category_filter ); ?>">
						<input type="hidden" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
						<input type="hidden" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
						<button type="submit" name="aiagent_export_csv" value="1" class="button">
							<?php esc_html_e( 'Export to CSV', 'ai-agent-for-website' ); ?>
						</button>
						<button type="submit" name="aiagent_cleanup_logs" value="1" class="button" onclick="return confirm('<?php esc_attr_e( 'This will delete logs older than the retention period. Continue?', 'ai-agent-for-website' ); ?>');">
							<?php esc_html_e( 'Cleanup Old Logs', 'ai-agent-for-website' ); ?>
						</button>
					</form>
				</div>
			</div>

			<div class="aiagent-card">
				<h2><?php esc_html_e( 'Activity Logs', 'ai-agent-for-website' ); ?></h2>

				<?php if ( empty( $logs_data['logs'] ) ) : ?>
					<div class="aiagent-empty-state">
						<p><?php esc_html_e( 'No activity logs yet. Actions will be logged when logging is enabled.', 'ai-agent-for-website' ); ?></p>
					</div>
				<?php else : ?>
					<table class="aiagent-conversations-table aiagent-logs-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Category', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Action', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Message', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'User', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'IP', 'ai-agent-for-website' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs_data['logs'] as $log ) : ?>
								<tr>
									<td>
										<span class="aiagent-log-time">
											<?php echo esc_html( date_i18n( 'M j, g:i a', strtotime( $log->created_at ) ) ); ?>
										</span>
									</td>
									<td>
										<span class="aiagent-log-category aiagent-category-<?php echo esc_attr( $log->category ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $log->category ) ) ); ?>
										</span>
									</td>
									<td>
										<span class="aiagent-log-action">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $log->action ) ) ); ?>
										</span>
									</td>
									<td>
										<span class="aiagent-log-message" title="<?php echo esc_attr( $log->message ); ?>">
											<?php echo esc_html( wp_trim_words( $log->message, 10 ) ); ?>
										</span>
										<?php if ( ! empty( $log->meta ) ) : ?>
											<button type="button" class="button button-small aiagent-view-meta" data-meta="<?php echo esc_attr( wp_json_encode( $log->meta ) ); ?>">
												<?php esc_html_e( 'Details', 'ai-agent-for-website' ); ?>
											</button>
										<?php endif; ?>
									</td>
									<td>
										<?php
										if ( $log->user_id ) {
											$user = get_user_by( 'id', $log->user_id );
											echo esc_html( $user ? $user->display_name : '#' . $log->user_id );
										} else {
											esc_html_e( 'System', 'ai-agent-for-website' );
										}
										?>
									</td>
									<td><?php echo esc_html( ! empty( $log->ip_address ) ? $log->ip_address : '-' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $logs_data['pages'] > 1 ) : ?>
						<div class="aiagent-pagination">
							<?php
							$base_url  = add_query_arg(
								[
									'category'  => $category_filter,
									'date_from' => $date_from,
									'date_to'   => $date_to,
								],
								admin_url( 'admin.php?page=ai-agent-logs' )
							);
							$max_pages = min( $logs_data['pages'], 10 );
							for ( $i = 1; $i <= $max_pages; $i++ ) :
								$class = $i === $current_page ? 'button-primary' : 'button';
								?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>" class="button <?php echo esc_attr( $class ); ?>">
									<?php echo esc_html( $i ); ?>
								</a>
							<?php endfor; ?>
							<?php if ( $logs_data['pages'] > 10 ) : ?>
								<span>...</span>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $logs_data['pages'], $base_url ) ); ?>" class="button">
									<?php echo esc_html( $logs_data['pages'] ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Meta Details Modal -->
		<div id="aiagent-meta-modal" class="aiagent-modal" style="display: none;">
			<div class="aiagent-modal-content">
				<span class="aiagent-modal-close">&times;</span>
				<h3><?php esc_html_e( 'Log Details', 'ai-agent-for-website' ); ?></h3>
				<pre id="aiagent-meta-content"></pre>
			</div>
		</div>

		<script>
		(function() {
			document.querySelectorAll('.aiagent-view-meta').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var meta = JSON.parse(this.dataset.meta);
					document.getElementById('aiagent-meta-content').textContent = JSON.stringify(meta, null, 2);
					document.getElementById('aiagent-meta-modal').style.display = 'flex';
				});
			});

			document.querySelector('.aiagent-modal-close').addEventListener('click', function() {
				document.getElementById('aiagent-meta-modal').style.display = 'none';
			});

			document.getElementById('aiagent-meta-modal').addEventListener('click', function(e) {
				if (e.target === this) {
					this.style.display = 'none';
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Handle log actions from form submission.
	 */
	private function handle_log_actions() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'aiagent_log_action' ) ) {
			return;
		}

		if ( isset( $_POST['aiagent_export_csv'] ) ) {
			$category  = isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : '';
			$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
			$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

			$csv = $this->export_to_csv( $category, $date_from, $date_to );

			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="ai-agent-logs-' . gmdate( 'Y-m-d' ) . '.csv"' );
			echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV content is escaped during generation.
			exit;
		}

		if ( isset( $_POST['aiagent_cleanup_logs'] ) ) {
			$deleted = $this->cleanup_old_logs();
			add_settings_error(
				'aiagent_logs',
				'logs_cleaned',
				sprintf(
					/* translators: %d: Number of deleted logs */
					__( 'Cleaned up %d old log entries.', 'ai-agent-for-website' ),
					$deleted
				),
				'success'
			);
		}
	}
}

