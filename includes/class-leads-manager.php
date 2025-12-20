<?php
/**
 * Leads Manager Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Leads_Manager
 *
 * Handles lead management, conversion from conversations, and CRM integrations.
 */
class AIAGENT_Leads_Manager {

	/**
	 * Lead statuses.
	 */
	const STATUS_NEW       = 'new';
	const STATUS_CONTACTED = 'contacted';
	const STATUS_QUALIFIED = 'qualified';
	const STATUS_CONVERTED = 'converted';
	const STATUS_CLOSED    = 'closed';

	/**
	 * Get all leads with pagination.
	 *
	 * @param int    $page    Page number.
	 * @param int    $per_page Number of items per page.
	 * @param string $status  Filter by status.
	 * @return array Array containing leads and total count.
	 */
	public function get_leads( $page = 1, $per_page = 20, $status = '' ) {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'aiagent_leads';
		$users_table = $wpdb->prefix . 'aiagent_users';

		$offset = ( $page - 1 ) * $per_page;

		$where = '';
		if ( ! empty( $status ) ) {
			$where = $wpdb->prepare( ' WHERE l.status = %s', $status );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom tables with safe interpolation.
		$leads = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.name, u.email, u.phone
				FROM $leads_table l
				LEFT JOIN $users_table u ON l.user_id = u.id
				$where
				ORDER BY l.created_at DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $leads_table l $where" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'leads' => $leads,
			'total' => (int) $total,
			'pages' => ceil( $total / $per_page ),
		];
	}

	/**
	 * Get a single lead by ID.
	 *
	 * @param int $lead_id The lead ID.
	 * @return object|null Lead object or null.
	 */
	public function get_lead( $lead_id ) {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'aiagent_leads';
		$users_table = $wpdb->prefix . 'aiagent_users';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup with safe table names.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT l.*, u.name, u.email, u.phone
				FROM $leads_table l
				LEFT JOIN $users_table u ON l.user_id = u.id
				WHERE l.id = %d",
				$lead_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Convert a conversation to a lead.
	 *
	 * @param int    $conversation_id The conversation ID.
	 * @param string $notes           Optional notes.
	 * @return int|false Lead ID on success, false on failure.
	 */
	public function convert_conversation_to_lead( $conversation_id, $notes = '' ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$leads_table         = $wpdb->prefix . 'aiagent_leads';
		$messages_table      = $wpdb->prefix . 'aiagent_messages';
		$users_table         = $wpdb->prefix . 'aiagent_users';

		// Get conversation details.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup with safe table names.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, u.email, u.name, u.phone
				FROM $conversations_table c
				LEFT JOIN $users_table u ON c.user_id = u.id
				WHERE c.id = %d",
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $conversation ) {
			return false;
		}

		// Check if lead already exists for this conversation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$existing = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT id FROM $leads_table WHERE conversation_id = %d", $conversation_id )
		);

		if ( $existing ) {
			return (int) $existing;
		}

		// Get message count and first message as summary.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup with safe table names.
		$first_message = $wpdb->get_var(
			$wpdb->prepare( "SELECT content FROM $messages_table WHERE conversation_id = %d AND role = 'user' ORDER BY id ASC LIMIT 1", $conversation_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Create lead.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional direct insert.
		$result = $wpdb->insert(
			$leads_table,
			[
				'user_id'         => $conversation->user_id,
				'conversation_id' => $conversation_id,
				'status'          => self::STATUS_NEW,
				'source'          => 'chat',
				'summary'         => $first_message ? wp_trim_words( $first_message, 20 ) : '',
				'notes'           => $notes,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			]
		);

		if ( ! $result ) {
			return false;
		}

		$lead_id = $wpdb->insert_id;

		// Trigger webhook for new lead.
		$this->trigger_webhook( $lead_id, 'lead_created' );

		return $lead_id;
	}

	/**
	 * Update lead status.
	 *
	 * @param int    $lead_id The lead ID.
	 * @param string $status  New status.
	 * @param string $notes   Optional notes to add.
	 * @return bool True on success, false on failure.
	 */
	public function update_lead_status( $lead_id, $status, $notes = '' ) {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'aiagent_leads';

		$update_data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( ! empty( $notes ) ) {
			// Append notes.
			$current_lead = $this->get_lead( $lead_id );
			if ( $current_lead ) {
				$update_data['notes'] = trim( $current_lead->notes . "\n\n" . $notes );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update.
		$result = $wpdb->update(
			$leads_table,
			$update_data,
			[ 'id' => $lead_id ]
		);

		if ( false !== $result ) {
			$this->trigger_webhook( $lead_id, 'lead_updated' );
			return true;
		}

		return false;
	}

	/**
	 * Delete a lead.
	 *
	 * @param int $lead_id The lead ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_lead( $lead_id ) {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'aiagent_leads';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct delete.
		$result = $wpdb->delete(
			$leads_table,
			[ 'id' => $lead_id ]
		);

		return false !== $result;
	}

	/**
	 * Trigger webhook for lead events.
	 *
	 * @param int    $lead_id The lead ID.
	 * @param string $event   Event type (lead_created, lead_updated).
	 */
	public function trigger_webhook( $lead_id, $event ) {
		$settings = get_option( 'aiagent_integrations', [] );

		// Zapier webhook.
		if ( ! empty( $settings['zapier_webhook_url'] ) ) {
			$lead = $this->get_lead( $lead_id );
			if ( $lead ) {
				$payload = [
					'event'      => $event,
					'lead_id'    => $lead->id,
					'name'       => $lead->name,
					'email'      => $lead->email,
					'phone'      => $lead->phone,
					'status'     => $lead->status,
					'source'     => $lead->source,
					'summary'    => $lead->summary,
					'notes'      => $lead->notes,
					'created_at' => $lead->created_at,
					'updated_at' => $lead->updated_at,
				];

				wp_remote_post(
					$settings['zapier_webhook_url'],
					[
						'body'        => wp_json_encode( $payload ),
						'headers'     => [ 'Content-Type' => 'application/json' ],
						'timeout'     => 15,
						'blocking'    => false,
						'data_format' => 'body',
					]
				);
			}
		}
	}

	/**
	 * Get lead statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics() {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'aiagent_leads';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom tables with safe interpolation.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $leads_table" );
		$new   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $leads_table WHERE status = %s", self::STATUS_NEW ) );

		$today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $leads_table WHERE DATE(created_at) = %s",
				current_time( 'Y-m-d' )
			)
		);

		$this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $leads_table WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d",
				current_time( 'm' ),
				current_time( 'Y' )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'total'      => $total,
			'new'        => $new,
			'today'      => $today,
			'this_month' => $this_month,
		];
	}

	/**
	 * Render admin page for leads.
	 */
	public function render_admin_page() {
		// Handle actions.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in action handlers.
		if ( isset( $_POST['aiagent_lead_action'] ) && check_admin_referer( 'aiagent_lead_action' ) ) {
			$this->handle_lead_action();
		}

		// Check if viewing a specific lead.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$view_lead = isset( $_GET['lead'] ) ? absint( $_GET['lead'] ) : null;

		if ( $view_lead ) {
			$this->render_lead_detail( $view_lead );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation.
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$leads_data = $this->get_leads( $current_page, 20, $status_filter );
		$stats      = $this->get_statistics();
		?>
		<div class="wrap aiagent-admin">
			<h1><?php esc_html_e( 'Leads', 'ai-agent-for-website' ); ?></h1>

			<div class="aiagent-stats-grid">
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $stats['total'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Total Leads', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $stats['new'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'New Leads', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $stats['today'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'Today', 'ai-agent-for-website' ); ?></div>
				</div>
				<div class="aiagent-stat-card">
					<div class="aiagent-stat-number"><?php echo esc_html( $stats['this_month'] ); ?></div>
					<div class="aiagent-stat-label"><?php esc_html_e( 'This Month', 'ai-agent-for-website' ); ?></div>
				</div>
			</div>

			<!-- Status Filter -->
			<div class="aiagent-card" style="margin-bottom: 20px;">
				<div class="aiagent-filter-bar">
					<span class="aiagent-filter-label"><?php esc_html_e( 'Filter by Status:', 'ai-agent-for-website' ); ?></span>
					<?php
					$statuses = [
						''                     => __( 'All', 'ai-agent-for-website' ),
						self::STATUS_NEW       => __( 'New', 'ai-agent-for-website' ),
						self::STATUS_CONTACTED => __( 'Contacted', 'ai-agent-for-website' ),
						self::STATUS_QUALIFIED => __( 'Qualified', 'ai-agent-for-website' ),
						self::STATUS_CONVERTED => __( 'Converted', 'ai-agent-for-website' ),
						self::STATUS_CLOSED    => __( 'Closed', 'ai-agent-for-website' ),
					];
					foreach ( $statuses as $key => $label ) :
						$url   = add_query_arg(
							[
								'status' => $key,
								'paged'  => 1,
							],
							admin_url( 'admin.php?page=ai-agent-leads' )
						);
						$class = $status_filter === $key ? 'button-primary' : 'button';
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="button <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="aiagent-card">
				<h2><?php esc_html_e( 'All Leads', 'ai-agent-for-website' ); ?></h2>

				<?php if ( empty( $leads_data['leads'] ) ) : ?>
					<div class="aiagent-empty-state">
						<p><?php esc_html_e( 'No leads yet. Convert conversations to leads to see them here.', 'ai-agent-for-website' ); ?></p>
					</div>
				<?php else : ?>
					<table class="aiagent-conversations-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Contact', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Summary', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Status', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Source', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Created', 'ai-agent-for-website' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'ai-agent-for-website' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $leads_data['leads'] as $lead ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $lead->name ? $lead->name : 'Unknown' ); ?></strong>
										<?php if ( $lead->email ) : ?>
											<br><small><?php echo esc_html( $lead->email ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $lead->summary ? wp_trim_words( $lead->summary, 10 ) : '-' ); ?></td>
									<td>
										<span class="aiagent-lead-status aiagent-status-<?php echo esc_attr( $lead->status ); ?>">
											<?php echo esc_html( ucfirst( $lead->status ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( ucfirst( $lead->source ) ); ?></td>
									<td><?php echo esc_html( human_time_diff( strtotime( $lead->created_at ), time() ) . ' ago' ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-leads&lead=' . $lead->id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'ai-agent-for-website' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $leads_data['pages'] > 1 ) : ?>
						<div class="aiagent-pagination">
							<?php
							$base_url = admin_url( 'admin.php?page=ai-agent-leads' );
							if ( $status_filter ) {
								$base_url = add_query_arg( 'status', $status_filter, $base_url );
							}
							for ( $i = 1; $i <= $leads_data['pages']; $i++ ) :
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
	 * Render lead detail page.
	 *
	 * @param int $lead_id The lead ID.
	 */
	private function render_lead_detail( $lead_id ) {
		$lead = $this->get_lead( $lead_id );

		if ( ! $lead ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Lead not found', 'ai-agent-for-website' ) . '</h1></div>';
			return;
		}

		$statuses = [
			self::STATUS_NEW       => __( 'New', 'ai-agent-for-website' ),
			self::STATUS_CONTACTED => __( 'Contacted', 'ai-agent-for-website' ),
			self::STATUS_QUALIFIED => __( 'Qualified', 'ai-agent-for-website' ),
			self::STATUS_CONVERTED => __( 'Converted', 'ai-agent-for-website' ),
			self::STATUS_CLOSED    => __( 'Closed', 'ai-agent-for-website' ),
		];
		?>
		<div class="wrap aiagent-admin">
			<h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-leads' ) ); ?>" style="text-decoration: none;">
					← <?php esc_html_e( 'Leads', 'ai-agent-for-website' ); ?>
				</a>
			</h1>

			<div class="aiagent-lead-detail-grid">
				<div class="aiagent-card">
					<h2><?php esc_html_e( 'Lead Details', 'ai-agent-for-website' ); ?></h2>

					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Name', 'ai-agent-for-website' ); ?></th>
							<td><strong><?php echo esc_html( $lead->name ? $lead->name : 'Unknown' ); ?></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Email', 'ai-agent-for-website' ); ?></th>
							<td>
								<?php if ( $lead->email ) : ?>
									<a href="mailto:<?php echo esc_attr( $lead->email ); ?>"><?php echo esc_html( $lead->email ); ?></a>
								<?php else : ?>
									-
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $lead->phone ) : ?>
						<tr>
							<th><?php esc_html_e( 'Phone', 'ai-agent-for-website' ); ?></th>
							<td>
								<a href="tel:<?php echo esc_attr( $lead->phone ); ?>"><?php echo esc_html( $lead->phone ); ?></a>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'Status', 'ai-agent-for-website' ); ?></th>
							<td>
								<span class="aiagent-lead-status aiagent-status-<?php echo esc_attr( $lead->status ); ?>">
									<?php echo esc_html( $statuses[ $lead->status ] ?? ucfirst( $lead->status ) ); ?>
								</span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Source', 'ai-agent-for-website' ); ?></th>
							<td><?php echo esc_html( ucfirst( $lead->source ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Created', 'ai-agent-for-website' ); ?></th>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $lead->created_at ) ) ); ?></td>
						</tr>
						<?php if ( $lead->conversation_id ) : ?>
						<tr>
							<th><?php esc_html_e( 'Conversation', 'ai-agent-for-website' ); ?></th>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-conversations&conversation=' . $lead->conversation_id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'View Conversation', 'ai-agent-for-website' ); ?>
								</a>
							</td>
						</tr>
						<?php endif; ?>
					</table>

					<?php if ( $lead->summary ) : ?>
						<h3><?php esc_html_e( 'Summary', 'ai-agent-for-website' ); ?></h3>
						<p><?php echo esc_html( $lead->summary ); ?></p>
					<?php endif; ?>

					<?php if ( $lead->notes ) : ?>
						<h3><?php esc_html_e( 'Notes', 'ai-agent-for-website' ); ?></h3>
						<div class="aiagent-lead-notes">
							<?php echo nl2br( esc_html( $lead->notes ) ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="aiagent-card">
					<h2><?php esc_html_e( 'Actions', 'ai-agent-for-website' ); ?></h2>

					<form method="post" style="margin-bottom: 20px;">
						<?php wp_nonce_field( 'aiagent_lead_action' ); ?>
						<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead->id ); ?>">

						<div class="aiagent-form-row" style="margin-bottom: 15px;">
							<label for="lead_status"><strong><?php esc_html_e( 'Update Status:', 'ai-agent-for-website' ); ?></strong></label>
							<select name="lead_status" id="lead_status" style="width: 100%; margin-top: 5px;">
								<?php foreach ( $statuses as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $lead->status, $key ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="aiagent-form-row" style="margin-bottom: 15px;">
							<label for="lead_notes"><strong><?php esc_html_e( 'Add Note:', 'ai-agent-for-website' ); ?></strong></label>
							<textarea name="lead_notes" id="lead_notes" rows="3" style="width: 100%; margin-top: 5px;" placeholder="<?php esc_attr_e( 'Add a note...', 'ai-agent-for-website' ); ?>"></textarea>
						</div>

						<button type="submit" name="aiagent_lead_action" value="update" class="button button-primary">
							<?php esc_html_e( 'Update Lead', 'ai-agent-for-website' ); ?>
						</button>
					</form>

					<hr>

					<form method="post" style="margin-top: 20px;">
						<?php wp_nonce_field( 'aiagent_lead_action' ); ?>
						<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead->id ); ?>">
						<button type="submit" name="aiagent_lead_action" value="delete" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this lead?', 'ai-agent-for-website' ); ?>');">
							<?php esc_html_e( 'Delete Lead', 'ai-agent-for-website' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle lead actions from form submission.
	 */
	private function handle_lead_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in caller.
		$action = isset( $_POST['aiagent_lead_action'] ) ? sanitize_key( $_POST['aiagent_lead_action'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in caller.
		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;

		if ( ! $lead_id ) {
			return;
		}

		switch ( $action ) {
			case 'update':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in caller.
				$status = isset( $_POST['lead_status'] ) ? sanitize_key( $_POST['lead_status'] ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in caller.
				$notes = isset( $_POST['lead_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lead_notes'] ) ) : '';
				$this->update_lead_status( $lead_id, $status, $notes );
				break;

			case 'delete':
				$this->delete_lead( $lead_id );
				wp_safe_redirect( admin_url( 'admin.php?page=ai-agent-leads' ) );
				exit;
		}
	}
}

