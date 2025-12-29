<?php
/**
 * Live Agent Dashboard Class
 *
 * Provides admin interface for live agents to respond to chat requests.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Live_Agent_Dashboard
 */
class AIAGENT_Live_Agent_Dashboard {

	/**
	 * Initialize the dashboard.
	 */
	public static function init() {
		// Add with priority 20 to ensure main menu is registered first.
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu.
	 */
	public static function add_menu() {
		// Only show if user can be an agent.
		$user_id = get_current_user_id();
		if ( ! AIAGENT_Live_Agent_Manager::can_user_be_agent( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_submenu_page(
			'ai-agent-settings',
			__( 'Live Agent Dashboard', 'ai-agent-for-website' ),
			__( 'Agent Dashboard', 'ai-agent-for-website' ),
			'read',
			'ai-agent-live-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);
	}

	/**
	 * Enqueue scripts for the dashboard.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'ai-agent_page_ai-agent-live-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'aiagent-live-dashboard',
			AIAGENT_PLUGIN_URL . 'assets/css/live-agent-dashboard.css',
			array(),
			AIAGENT_VERSION
		);

		wp_enqueue_script(
			'aiagent-live-dashboard',
			AIAGENT_PLUGIN_URL . 'assets/js/live-agent-dashboard.js',
			array( 'jquery' ),
			AIAGENT_VERSION,
			true
		);

		wp_localize_script(
			'aiagent-live-dashboard',
			'aiagentLiveAgent',
			array(
				'restUrl'   => rest_url( 'ai-agent/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'userId'    => get_current_user_id(),
				'userName'  => wp_get_current_user()->display_name,
				'strings'   => array(
					'noSessions'    => __( 'No active chat sessions', 'ai-agent-for-website' ),
					'sendMessage'   => __( 'Send', 'ai-agent-for-website' ),
					'typeMessage'   => __( 'Type your message...', 'ai-agent-for-website' ),
					'endChat'       => __( 'End Chat', 'ai-agent-for-website' ),
					'acceptChat'    => __( 'Accept Chat', 'ai-agent-for-website' ),
					'visitor'       => __( 'Visitor', 'ai-agent-for-website' ),
					'you'           => __( 'You', 'ai-agent-for-website' ),
					'waiting'       => __( 'Waiting for agent...', 'ai-agent-for-website' ),
					'active'        => __( 'Active', 'ai-agent-for-website' ),
				),
			)
		);
	}

	/**
	 * Render the dashboard.
	 */
	public static function render_dashboard() {
		$user_id   = get_current_user_id();
		$my_status = AIAGENT_Live_Agent_Manager::get_agent_status( $user_id );
		$settings  = AIAGENT_Live_Agent_Manager::get_settings();

		if ( ! $settings['enabled'] ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Live Agent Dashboard', 'ai-agent-for-website' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Live Agent mode is not enabled. Please enable it in Settings → Live Agent tab.', 'ai-agent-for-website' ) . '</p></div></div>';
			return;
		}
		?>
		<div class="wrap aiagent-live-dashboard">
			<h1>
				<span class="dashicons dashicons-format-chat"></span>
				<?php esc_html_e( 'Live Agent Dashboard', 'ai-agent-for-website' ); ?>
			</h1>

			<!-- Agent Status Bar -->
			<div class="aiagent-agent-status-bar">
				<div class="aiagent-status-info">
					<span class="aiagent-status-label"><?php esc_html_e( 'Your Status:', 'ai-agent-for-website' ); ?></span>
					<span id="agent-status-indicator" class="aiagent-status-badge <?php echo 'offline' !== $my_status ? 'online' : 'offline'; ?>">
						<span class="status-dot"></span>
						<span class="status-text"><?php echo 'offline' !== $my_status ? esc_html__( 'Online', 'ai-agent-for-website' ) : esc_html__( 'Offline', 'ai-agent-for-website' ); ?></span>
					</span>
				</div>
				<button type="button" id="toggle-agent-status" class="button <?php echo 'offline' !== $my_status ? 'button-secondary' : 'button-primary'; ?>">
					<?php echo 'offline' !== $my_status ? esc_html__( 'Go Offline', 'ai-agent-for-website' ) : esc_html__( 'Go Online', 'ai-agent-for-website' ); ?>
				</button>
			</div>

			<div class="aiagent-dashboard-container">
				<!-- Sessions List -->
				<div class="aiagent-sessions-panel">
					<div class="aiagent-panel-header">
						<h2><?php esc_html_e( 'Chat Sessions', 'ai-agent-for-website' ); ?></h2>
						<span id="sessions-count" class="aiagent-count-badge">0</span>
					</div>
					<div id="sessions-list" class="aiagent-sessions-list">
						<div class="aiagent-loading">
							<span class="spinner is-active"></span>
							<?php esc_html_e( 'Loading sessions...', 'ai-agent-for-website' ); ?>
						</div>
					</div>
				</div>

				<!-- Chat Panel -->
				<div class="aiagent-chat-panel">
					<div id="chat-header" class="aiagent-chat-header">
						<div class="aiagent-chat-user-info">
							<span class="aiagent-user-avatar">
								<span class="dashicons dashicons-admin-users"></span>
							</span>
							<div class="aiagent-user-details">
								<span id="chat-user-name" class="aiagent-user-name"><?php esc_html_e( 'Select a chat', 'ai-agent-for-website' ); ?></span>
								<span id="chat-user-email" class="aiagent-user-email"></span>
							</div>
						</div>
						<div class="aiagent-chat-actions">
							<button type="button" id="end-chat-btn" class="button button-secondary" style="display: none;">
								<?php esc_html_e( 'End Chat', 'ai-agent-for-website' ); ?>
							</button>
						</div>
					</div>

					<div id="chat-messages" class="aiagent-chat-messages">
						<div class="aiagent-empty-state">
							<span class="dashicons dashicons-format-chat"></span>
							<p><?php esc_html_e( 'Select a chat session from the list to start responding.', 'ai-agent-for-website' ); ?></p>
						</div>
					</div>

					<div id="chat-input-area" class="aiagent-chat-input" style="display: none;">
						<form id="agent-message-form">
							<input type="text" id="agent-message-input" placeholder="<?php esc_attr_e( 'Type your message...', 'ai-agent-for-website' ); ?>" autocomplete="off">
							<button type="submit" class="button button-primary">
								<span class="dashicons dashicons-arrow-right-alt"></span>
								<?php esc_html_e( 'Send', 'ai-agent-for-website' ); ?>
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

// Initialize.
AIAGENT_Live_Agent_Dashboard::init();

