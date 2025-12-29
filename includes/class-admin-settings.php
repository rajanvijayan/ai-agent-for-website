<?php
/**
 * Admin Settings Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Admin_Settings
 *
 * Handles the admin settings page for the AI Agent plugin.
 */
class AIAGENT_Admin_Settings {

	/**
	 * Current active tab.
	 *
	 * @var string
	 */
	private $active_tab = 'general';

	/**
	 * Render the settings page.
	 */
	public function render() {
		// Handle form submission.
		if ( isset( $_POST['aiagent_save_settings'] ) && check_admin_referer( 'aiagent_settings_nonce' ) ) {
			$this->save_settings();
		}

		// Handle Google Drive OAuth callback.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state verified in callback handler.
		if ( isset( $_GET['gdrive_callback'] ) && isset( $_GET['code'] ) ) {
			$this->handle_gdrive_callback();
		}

		// Handle Google Calendar OAuth callback.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state verified in callback handler.
		if ( isset( $_GET['gcalendar_callback'] ) && isset( $_GET['code'] ) ) {
			$this->handle_gcalendar_callback();
		}

		// Handle Calendly OAuth callback.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state verified in callback handler.
		if ( isset( $_GET['calendly_callback'] ) && isset( $_GET['code'] ) ) {
			$this->handle_calendly_callback();
		}

		// Get current tab from URL parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading tab parameter for display.
		$this->active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		$settings = AI_Agent_For_Website::get_settings();
		?>
		<div class="wrap aiagent-admin">
			<h1><?php esc_html_e( 'AI Agent Settings', 'ai-agent-for-website' ); ?></h1>

			<?php $this->render_tabs(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'aiagent_settings_nonce' ); ?>
				<input type="hidden" name="aiagent_active_tab" value="<?php echo esc_attr( $this->active_tab ); ?>">

				<div class="aiagent-tab-content">
					<?php
					switch ( $this->active_tab ) {
						case 'integrations':
							$this->render_integrations_tab( $settings );
							break;
						case 'live-agent':
							$this->render_live_agent_tab();
							break;
						case 'appearance':
							$this->render_appearance_tab( $settings );
							break;
						case 'user-info':
							$this->render_user_info_tab( $settings );
							break;
						case 'notifications':
							$this->render_notifications_tab();
							break;
						default:
							$this->render_general_tab( $settings );
							break;
					}
					?>
				</div>

				<p class="submit">
					<input type="submit" 
							name="aiagent_save_settings" 
							class="button button-primary" 
							value="<?php esc_attr_e( 'Save Settings', 'ai-agent-for-website' ); ?>">
				</p>
			</form>

			<?php $this->render_ai_modal(); ?>
		</div>
		<?php
	}

	/**
	 * Render the tab navigation.
	 */
	private function render_tabs() {
		$tabs = array(
			'general'       => array(
				'label' => __( 'General', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'integrations'  => array(
				'label' => __( 'Integrations', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-plugins',
			),
			'live-agent'    => array(
				'label' => __( 'Live Agent', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-groups',
			),
			'appearance'    => array(
				'label' => __( 'Appearance', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-appearance',
			),
			'user-info'     => array(
				'label' => __( 'User Information', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-users',
			),
			'notifications' => array(
				'label' => __( 'Notifications & Logs', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-bell',
			),
		);

		$base_url = admin_url( 'admin.php?page=ai-agent-settings' );
		?>
		<nav class="aiagent-tabs-nav">
			<?php foreach ( $tabs as $tab_id => $tab ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, $base_url ) ); ?>" 
					class="aiagent-tab-link <?php echo $this->active_tab === $tab_id ? 'active' : ''; ?>">
					<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
					<?php echo esc_html( $tab['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render the General tab content.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_general_tab( $settings ) {
		?>
		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Chat Widget', 'ai-agent-for-website' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="enabled"><?php esc_html_e( 'Enable Chat Widget', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="enabled" 
									name="enabled" 
									value="1" 
									<?php checked( ! empty( $settings['enabled'] ) ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show the chat widget on your website', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'AI Personality', 'ai-agent-for-website' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="welcome_message"><?php esc_html_e( 'Welcome Message', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<textarea id="welcome_message" 
									name="welcome_message" 
									rows="2" 
									class="large-text"><?php echo esc_textarea( $settings['welcome_message'] ?? 'Hello! How can I help you today?' ); ?></textarea>
						<div class="aiagent-ai-suggest-wrapper">
							<button type="button" class="button aiagent-ai-suggest-btn" data-target="welcome_message" data-type="welcome">
								<span class="dashicons dashicons-lightbulb"></span>
								<?php esc_html_e( 'AI Suggestion', 'ai-agent-for-website' ); ?>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'First message shown when user opens the chat', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="system_instruction"><?php esc_html_e( 'System Instruction', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<textarea id="system_instruction" 
									name="system_instruction" 
									rows="4" 
									class="large-text"><?php echo esc_textarea( $settings['system_instruction'] ?? '' ); ?></textarea>
						<div class="aiagent-ai-suggest-wrapper">
							<button type="button" class="button aiagent-ai-suggest-btn" data-target="system_instruction" data-type="instruction">
								<span class="dashicons dashicons-lightbulb"></span>
								<?php esc_html_e( 'AI Suggestion', 'ai-agent-for-website' ); ?>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Instructions that define how the AI should behave and respond', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Shortcode Usage', 'ai-agent-for-website' ); ?></h2>
			<p><?php esc_html_e( 'You can also embed the chat widget directly in any page or post using this shortcode:', 'ai-agent-for-website' ); ?></p>
			<code class="aiagent-shortcode-display">[ai_agent_chat]</code>
			<p class="description" style="margin-top: 10px;">
				<?php esc_html_e( 'Optional attributes: height="500px" width="100%"', 'ai-agent-for-website' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the Integrations tab content.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_integrations_tab( $settings ) {
		$gdrive_settings      = AIAGENT_Google_Drive_Integration::get_settings();
		$gdrive_connected     = AIAGENT_Google_Drive_Integration::is_connected();
		$gdrive_user          = AIAGENT_Google_Drive_Integration::get_connected_user();
		$gcalendar_settings   = AIAGENT_Google_Calendar_Integration::get_settings();
		$gcalendar_connected  = AIAGENT_Google_Calendar_Integration::is_connected();
		$gcalendar_user       = AIAGENT_Google_Calendar_Integration::get_connected_user();
		$calendly_settings    = AIAGENT_Calendly_Integration::get_settings();
		$calendly_connected   = AIAGENT_Calendly_Integration::is_connected();
		$calendly_user        = AIAGENT_Calendly_Integration::get_connected_user();
		$confluence_settings  = AIAGENT_Confluence_Integration::get_settings();
		$confluence_connected = AIAGENT_Confluence_Integration::is_connected();
		$integration_settings = get_option( 'aiagent_integrations', array() );
		$zapier_enabled       = ! empty( $integration_settings['zapier_enabled'] );
		$zapier_url           = $integration_settings['zapier_webhook_url'] ?? '';
		$mailchimp_settings   = AIAGENT_Mailchimp_Integration::get_settings();
		$mailchimp_enabled    = ! empty( $mailchimp_settings['mailchimp_enabled'] );
		$mailchimp_connected  = AIAGENT_Mailchimp_Integration::is_enabled();
		?>
		<!-- AI Provider Section -->
		<div class="aiagent-integration-section">
			<div class="aiagent-section-header">
				<span class="dashicons dashicons-cloud"></span>
				<h2><?php esc_html_e( 'AI Provider', 'ai-agent-for-website' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure your AI provider to power the chat functionality.', 'ai-agent-for-website' ); ?></p>
			</div>

			<div class="aiagent-integration-grid">
				<!-- Groq -->
				<div class="aiagent-integration-box" data-integration="groq">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #f55036 0%, #e42e12 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'Groq', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Ultra-fast Llama inference', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( ! empty( $settings['api_key'] ) ) : ?>
							<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<button type="button" class="button aiagent-integration-configure-btn" data-modal="groq-modal">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Knowledge Base Section -->
		<div class="aiagent-integration-section">
			<div class="aiagent-section-header">
				<span class="dashicons dashicons-book-alt"></span>
				<h2><?php esc_html_e( 'Knowledge Base', 'ai-agent-for-website' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Import documents and content to train your AI assistant.', 'ai-agent-for-website' ); ?></p>
			</div>

			<div class="aiagent-integration-grid">
				<!-- Google Drive -->
				<div class="aiagent-integration-box" data-integration="gdrive">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #4285f4 0%, #1a73e8 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M7.71 3.5L1.15 15l3.43 5.98h13.39L21.41 15H7.71L4.28 9.02 7.71 3.5zm1.43 0L12 9.02l2.86-5.52H9.14zm8.57 11.5L12 9.02 5.43 20.98H12l5.71-5.98z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'Google Drive', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Import from Google Docs & Drive', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( $gdrive_connected ) : ?>
							<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<?php if ( $gdrive_connected ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-primary">
								<span class="dashicons dashicons-cloud-upload"></span>
								<?php esc_html_e( 'Import', 'ai-agent-for-website' ); ?>
							</a>
						<?php endif; ?>
						<button type="button" class="button aiagent-integration-configure-btn" data-modal="gdrive-modal">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>

				<!-- Confluence -->
				<div class="aiagent-integration-box" data-integration="confluence">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #0052cc 0%, #2684ff 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M3.58 16.41c-.17.28-.33.57-.33.87 0 .79.91 1.42 2.03 1.42h13.44c.79 0 1.48-.39 1.83-.99l3.02-5.16c.22-.38.43-.78.43-1.19 0-.79-.91-1.42-2.03-1.42H8.53c-.79 0-1.48.39-1.83.99L3.58 16.41z"/>
								<path d="M20.42 7.59c.17-.28.33-.57.33-.87 0-.79-.91-1.42-2.03-1.42H5.28c-.79 0-1.48.39-1.83.99L.43 11.45c-.22.38-.43.78-.43 1.19 0 .79.91 1.42 2.03 1.42h13.44c.79 0 1.48-.39 1.83-.99l3.12-5.48z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'Confluence', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Import wiki pages & docs', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( $confluence_connected ) : ?>
							<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<?php if ( $confluence_connected ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-primary">
								<span class="dashicons dashicons-cloud-upload"></span>
								<?php esc_html_e( 'Import', 'ai-agent-for-website' ); ?>
							</a>
						<?php endif; ?>
						<button type="button" class="button aiagent-integration-configure-btn" data-modal="confluence-modal">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>

				<!-- File Upload -->
				<div class="aiagent-integration-box" data-integration="file-upload">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'File Upload', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'PDF, DOC, DOCX, TXT, CSV, MD', 'ai-agent-for-website' ); ?></span>
						</div>
						<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Available', 'ai-agent-for-website' ); ?></span>
					</div>
					<div class="aiagent-integration-box-footer">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-upload"></span>
							<?php esc_html_e( 'Upload Files', 'ai-agent-for-website' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- E-Commerce Section -->
		<?php
		$woo_active   = AIAGENT_WooCommerce_Integration::is_woocommerce_active();
		$woo_enabled  = AIAGENT_WooCommerce_Integration::is_enabled();
		$woo_settings = AIAGENT_WooCommerce_Integration::get_settings();
		?>
		<div class="aiagent-integration-section">
			<div class="aiagent-section-header">
				<span class="dashicons dashicons-cart"></span>
				<h2><?php esc_html_e( 'E-Commerce', 'ai-agent-for-website' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Enable product search, comparison, and add-to-cart functionality in chat.', 'ai-agent-for-website' ); ?></p>
			</div>

			<div class="aiagent-integration-grid">
				<!-- WooCommerce -->
				<div class="aiagent-integration-box" data-integration="woocommerce">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #96588a 0%, #7b4b7e 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M2 3h20c.6 0 1 .4 1 1v1c0 .6-.4 1-1 1H2c-.6 0-1-.4-1-1V4c0-.6.4-1 1-1zm0 4h20l-1.5 11c-.1.6-.5 1-1 1H4.5c-.5 0-.9-.4-1-1L2 7zm4 3c0 .6.4 1 1 1s1-.4 1-1-.4-1-1-1-1 .4-1 1zm4 0c0 .6.4 1 1 1s1-.4 1-1-.4-1-1-1-1 .4-1 1zm4 0c0 .6.4 1 1 1s1-.4 1-1-.4-1-1-1-1 .4-1 1z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'WooCommerce', 'ai-agent-for-website' ); ?> <span class="aiagent-badge aiagent-badge-beta"><?php esc_html_e( 'Beta', 'ai-agent-for-website' ); ?></span></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Product search & add to cart', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( ! $woo_active ) : ?>
							<span class="aiagent-badge aiagent-badge-inactive"><?php esc_html_e( 'Not Installed', 'ai-agent-for-website' ); ?></span>
						<?php elseif ( $woo_enabled ) : ?>
							<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Enabled', 'ai-agent-for-website' ); ?></span>
						<?php else : ?>
							<span class="aiagent-badge aiagent-badge-available"><?php esc_html_e( 'Available', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<?php if ( $woo_active ) : ?>
							<button type="button" class="button aiagent-integration-configure-btn" data-modal="woocommerce-modal">
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
							</button>
						<?php else : ?>
							<span class="aiagent-not-installed-text"><?php esc_html_e( 'Install WooCommerce to enable', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- CRM & Marketing Section -->
		<div class="aiagent-integration-section">
			<div class="aiagent-section-header">
				<span class="dashicons dashicons-megaphone"></span>
				<h2><?php esc_html_e( 'CRM & Marketing', 'ai-agent-for-website' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Sync leads and automate marketing workflows.', 'ai-agent-for-website' ); ?></p>
			</div>

			<div class="aiagent-integration-grid">
				<!-- Zapier -->
				<div class="aiagent-integration-box" data-integration="zapier">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #ff4a00 0%, #e64100 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'Zapier', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Connect to 5000+ apps', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( $zapier_enabled && $zapier_url ) : ?>
							<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<button type="button" class="button aiagent-integration-configure-btn" data-modal="zapier-modal">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>

				<!-- Mailchimp -->
				<div class="aiagent-integration-box" data-integration="mailchimp">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #FFE01B 0%, #f0c800 100%);">
							<svg viewBox="0 0 24 24" fill="#1e1e1e" width="24" height="24">
								<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'Mailchimp', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Email marketing automation', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( $mailchimp_connected ) : ?>
							<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<button type="button" class="button aiagent-integration-configure-btn" data-modal="mailchimp-modal">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>

				<!-- Coming Soon Placeholder -->
				<div class="aiagent-integration-box aiagent-integration-box-coming-soon">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #bdc3c7 0%, #95a5a6 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'More Coming Soon', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'HubSpot, Salesforce & more', 'ai-agent-for-website' ); ?></span>
						</div>
						<span class="aiagent-badge aiagent-badge-soon"><?php esc_html_e( 'Soon', 'ai-agent-for-website' ); ?></span>
					</div>
					<div class="aiagent-integration-box-footer">
						<span class="aiagent-coming-soon-text"><?php esc_html_e( 'Stay tuned!', 'ai-agent-for-website' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Scheduling Section -->
		<div class="aiagent-integration-section">
			<div class="aiagent-section-header">
				<span class="dashicons dashicons-calendar-alt"></span>
				<h2><?php esc_html_e( 'Scheduling', 'ai-agent-for-website' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Let users book meetings and appointments at the end of conversations.', 'ai-agent-for-website' ); ?></p>
			</div>

			<div class="aiagent-integration-grid">
				<!-- Google Calendar -->
				<div class="aiagent-integration-box" data-integration="gcalendar">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #4285f4 0%, #0d47a1 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'Google Calendar', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Book meetings after chat', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( $gcalendar_connected ) : ?>
							<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<button type="button" class="button aiagent-integration-configure-btn" data-modal="gcalendar-modal">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>

				<!-- Calendly -->
				<div class="aiagent-integration-box" data-integration="calendly">
					<div class="aiagent-integration-box-header">
						<div class="aiagent-integration-box-icon" style="background: linear-gradient(135deg, #006bff 0%, #0052cc 100%);">
							<svg viewBox="0 0 24 24" fill="white" width="24" height="24">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
							</svg>
						</div>
						<div class="aiagent-integration-box-title">
							<h3><?php esc_html_e( 'Calendly', 'ai-agent-for-website' ); ?></h3>
							<span class="aiagent-integration-box-desc"><?php esc_html_e( 'Easy scheduling links', 'ai-agent-for-website' ); ?></span>
						</div>
						<?php if ( ! empty( $calendly_settings['enabled'] ) && ! empty( $calendly_settings['scheduling_url'] ) ) : ?>
							<span class="aiagent-badge aiagent-badge-enabled"><?php esc_html_e( 'Enabled', 'ai-agent-for-website' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aiagent-integration-box-footer">
						<button type="button" class="button aiagent-integration-configure-btn" data-modal="calendly-modal">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configure', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Integration Configuration Modals -->
		<?php $this->render_integration_modals( $settings, $gdrive_settings, $gdrive_connected, $gdrive_user, $confluence_settings, $confluence_connected, $zapier_enabled, $zapier_url, $mailchimp_settings, $mailchimp_enabled, $mailchimp_connected, $woo_active, $woo_enabled, $woo_settings, $gcalendar_settings, $gcalendar_connected, $gcalendar_user, $calendly_settings, $calendly_connected, $calendly_user ); ?>
		<?php
	}

	/**
	 * Render integration configuration modals.
	 *
	 * @param array  $settings             Main plugin settings.
	 * @param array  $gdrive_settings      Google Drive settings.
	 * @param bool   $gdrive_connected     Whether Google Drive is connected.
	 * @param array  $gdrive_user          Connected Google Drive user.
	 * @param array  $confluence_settings  Confluence settings.
	 * @param bool   $confluence_connected Whether Confluence is connected.
	 * @param bool   $zapier_enabled       Whether Zapier is enabled.
	 * @param string $zapier_url           Zapier webhook URL.
	 * @param array  $mailchimp_settings   Mailchimp settings.
	 * @param bool   $mailchimp_enabled    Whether Mailchimp is enabled.
	 * @param bool   $mailchimp_connected  Whether Mailchimp is connected.
	 * @param bool   $woo_active           Whether WooCommerce is active.
	 * @param bool   $woo_enabled          Whether WooCommerce integration is enabled.
	 * @param array  $woo_settings         WooCommerce integration settings.
	 * @param array  $gcalendar_settings   Google Calendar settings.
	 * @param bool   $gcalendar_connected  Whether Google Calendar is connected.
	 * @param array  $gcalendar_user       Connected Google Calendar user.
	 * @param array  $calendly_settings    Calendly settings.
	 * @param bool   $calendly_connected   Whether Calendly is connected (reserved for future OAuth use).
	 * @param array  $calendly_user        Connected Calendly user (reserved for future OAuth use).
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function render_integration_modals( $settings, $gdrive_settings, $gdrive_connected, $gdrive_user, $confluence_settings, $confluence_connected, $zapier_enabled, $zapier_url, $mailchimp_settings, $mailchimp_enabled, $mailchimp_connected, $woo_active = false, $woo_enabled = false, $woo_settings = array(), $gcalendar_settings = array(), $gcalendar_connected = false, $gcalendar_user = null, $calendly_settings = array(), $calendly_connected = false, $calendly_user = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		?>
		<!-- Groq Modal -->
		<div id="groq-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #f55036 0%, #e42e12 100%);">
						<svg viewBox="0 0 24 24" fill="white" width="20" height="20">
							<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure Groq', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<p class="description"><?php esc_html_e( 'Enter your Groq API key to power the AI chat functionality.', 'ai-agent-for-website' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="api_key"><?php esc_html_e( 'API Key', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<div class="aiagent-api-key-wrapper">
									<input type="password" id="api_key" name="api_key" 
										value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>" 
										class="large-text" autocomplete="off">
									<button type="button" class="button aiagent-toggle-password">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Get your free API key from', 'ai-agent-for-website' ); ?>
									<a href="https://console.groq.com" target="_blank">console.groq.com</a>
								</p>
							</td>
						</tr>
					</table>
					<div class="aiagent-modal-actions">
						<button type="button" id="aiagent-test-api" class="button">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Test Connection', 'ai-agent-for-website' ); ?>
						</button>
						<span id="aiagent-test-result" class="aiagent-test-result"></span>
					</div>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<button type="button" class="button button-primary aiagent-modal-save" data-integration="groq">
						<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Google Drive Modal -->
		<div id="gdrive-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #4285f4 0%, #1a73e8 100%);">
						<svg viewBox="0 0 24 24" fill="white" width="20" height="20">
							<path d="M7.71 3.5L1.15 15l3.43 5.98h13.39L21.41 15H7.71L4.28 9.02 7.71 3.5zm1.43 0L12 9.02l2.86-5.52H9.14zm8.57 11.5L12 9.02 5.43 20.98H12l5.71-5.98z"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure Google Drive', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<?php if ( $gdrive_connected && $gdrive_user ) : ?>
						<div class="aiagent-integration-status aiagent-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php
							/* translators: %s: User email */
							printf( esc_html__( 'Connected as %s', 'ai-agent-for-website' ), esc_html( $gdrive_user['email'] ) );
							?>
						</div>
						<div class="aiagent-modal-actions" style="margin-top: 20px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-primary">
								<span class="dashicons dashicons-cloud-upload"></span>
								<?php esc_html_e( 'Import Files', 'ai-agent-for-website' ); ?>
							</a>
							<button type="button" class="button button-link-delete aiagent-gdrive-disconnect">
								<?php esc_html_e( 'Disconnect', 'ai-agent-for-website' ); ?>
							</button>
						</div>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Create OAuth credentials in the Google Cloud Console.', 'ai-agent-for-website' ); ?>
							<a href="https://console.cloud.google.com/apis/credentials" target="_blank"><?php esc_html_e( 'Learn how →', 'ai-agent-for-website' ); ?></a>
						</p>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="gdrive_client_id"><?php esc_html_e( 'Client ID', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="text" id="gdrive_client_id" name="gdrive_client_id" 
										value="<?php echo esc_attr( $gdrive_settings['client_id'] ?? '' ); ?>" 
										class="large-text" placeholder="xxxxxx.apps.googleusercontent.com">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="gdrive_client_secret"><?php esc_html_e( 'Client Secret', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<div class="aiagent-api-key-wrapper">
										<input type="password" id="gdrive_client_secret" name="gdrive_client_secret" 
											value="<?php echo esc_attr( $gdrive_settings['client_secret'] ?? '' ); ?>" 
											class="large-text" autocomplete="off">
										<button type="button" class="button aiagent-toggle-password">
											<span class="dashicons dashicons-visibility"></span>
										</button>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Redirect URI', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<code class="aiagent-copy-text" id="gdrive-redirect-uri"><?php echo esc_html( admin_url( 'admin.php?page=ai-agent-settings&tab=integrations&gdrive_callback=1' ) ); ?></code>
									<button type="button" class="button button-small aiagent-copy-btn" data-target="gdrive-redirect-uri">
										<span class="dashicons dashicons-clipboard"></span>
									</button>
									<p class="description"><?php esc_html_e( 'Add this URL to your OAuth consent screen.', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
						</table>
						<div class="aiagent-modal-actions">
							<button type="button" id="aiagent-gdrive-connect" class="button button-primary" <?php disabled( empty( $gdrive_settings['client_id'] ) || empty( $gdrive_settings['client_secret'] ) ); ?>>
								<span class="dashicons dashicons-admin-links"></span>
								<?php esc_html_e( 'Connect Google Drive', 'ai-agent-for-website' ); ?>
							</button>
							<span class="aiagent-connection-status"></span>
						</div>
					<?php endif; ?>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<?php if ( ! $gdrive_connected ) : ?>
						<button type="button" class="button button-primary aiagent-modal-save" data-integration="gdrive">
							<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Confluence Modal -->
		<div id="confluence-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #0052cc 0%, #2684ff 100%);">
						<svg viewBox="0 0 24 24" fill="white" width="20" height="20">
							<path d="M3.58 16.41c-.17.28-.33.57-.33.87 0 .79.91 1.42 2.03 1.42h13.44c.79 0 1.48-.39 1.83-.99l3.02-5.16c.22-.38.43-.78.43-1.19 0-.79-.91-1.42-2.03-1.42H8.53c-.79 0-1.48.39-1.83.99L3.58 16.41z"/>
							<path d="M20.42 7.59c.17-.28.33-.57.33-.87 0-.79-.91-1.42-2.03-1.42H5.28c-.79 0-1.48.39-1.83.99L.43 11.45c-.22.38-.43.78-.43 1.19 0 .79.91 1.42 2.03 1.42h13.44c.79 0 1.48-.39 1.83-.99l3.12-5.48z"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure Confluence', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<?php if ( $confluence_connected ) : ?>
						<div class="aiagent-integration-status aiagent-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php
							/* translators: %s: Instance URL */
							printf( esc_html__( 'Connected to %s', 'ai-agent-for-website' ), esc_html( $confluence_settings['instance_url'] ) );
							?>
						</div>
						<div class="aiagent-modal-actions" style="margin-top: 20px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-primary">
								<span class="dashicons dashicons-cloud-upload"></span>
								<?php esc_html_e( 'Import Pages', 'ai-agent-for-website' ); ?>
							</a>
							<button type="button" class="button button-link-delete aiagent-confluence-disconnect">
								<?php esc_html_e( 'Disconnect', 'ai-agent-for-website' ); ?>
							</button>
						</div>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'For Cloud, use an API token from', 'ai-agent-for-website' ); ?>
							<a href="https://id.atlassian.com/manage-profile/security/api-tokens" target="_blank"><?php esc_html_e( 'Atlassian API tokens', 'ai-agent-for-website' ); ?></a>
						</p>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="confluence_url"><?php esc_html_e( 'Instance URL', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="url" id="confluence_url" name="confluence_url" 
										value="<?php echo esc_attr( $confluence_settings['instance_url'] ?? '' ); ?>" 
										class="large-text" placeholder="https://your-domain.atlassian.net">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="confluence_email"><?php esc_html_e( 'Email', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="email" id="confluence_email" name="confluence_email" 
										value="<?php echo esc_attr( $confluence_settings['email'] ?? '' ); ?>" 
										class="large-text" placeholder="you@example.com">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="confluence_token"><?php esc_html_e( 'API Token', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<div class="aiagent-api-key-wrapper">
										<input type="password" id="confluence_token" name="confluence_token" 
											value="<?php echo esc_attr( $confluence_settings['api_token'] ?? '' ); ?>" 
											class="large-text" autocomplete="off">
										<button type="button" class="button aiagent-toggle-password">
											<span class="dashicons dashicons-visibility"></span>
										</button>
									</div>
								</td>
							</tr>
						</table>
						<div class="aiagent-modal-actions">
							<button type="button" id="aiagent-confluence-connect" class="button button-primary">
								<span class="dashicons dashicons-admin-links"></span>
								<?php esc_html_e( 'Connect Confluence', 'ai-agent-for-website' ); ?>
							</button>
							<span class="aiagent-confluence-status"></span>
						</div>
					<?php endif; ?>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<?php if ( ! $confluence_connected ) : ?>
						<button type="button" class="button button-primary aiagent-modal-save" data-integration="confluence">
							<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Zapier Modal -->
		<div id="zapier-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #ff4a00 0%, #e64100 100%);">
						<svg viewBox="0 0 24 24" fill="white" width="20" height="20">
							<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure Zapier', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<p class="description"><?php esc_html_e( 'Connect with Zapier to sync leads with any CRM platform like Salesforce, HubSpot, Pipedrive, and more.', 'ai-agent-for-website' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="zapier_enabled"><?php esc_html_e( 'Enable Zapier', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="zapier_enabled" name="zapier_enabled" value="1" <?php checked( $zapier_enabled ); ?>>
									<span class="slider"></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="zapier_webhook_url"><?php esc_html_e( 'Webhook URL', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="url" id="zapier_webhook_url" name="zapier_webhook_url" 
									value="<?php echo esc_attr( $zapier_url ); ?>" 
									class="large-text" placeholder="https://hooks.zapier.com/hooks/catch/...">
								<p class="description"><?php esc_html_e( 'Create a Zap with "Webhooks by Zapier" trigger and paste the URL here.', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
					</table>
					<?php if ( $zapier_enabled && $zapier_url ) : ?>
						<div class="aiagent-integration-status aiagent-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Zapier webhook is configured. New leads will be synced automatically.', 'ai-agent-for-website' ); ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<button type="button" class="button button-primary aiagent-modal-save" data-integration="zapier">
						<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Mailchimp Modal -->
		<div id="mailchimp-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #FFE01B 0%, #f0c800 100%);">
						<svg viewBox="0 0 24 24" fill="#1e1e1e" width="20" height="20">
							<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure Mailchimp', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<p class="description"><?php esc_html_e( 'Connect Mailchimp to automatically subscribe users who opt-in for newsletters.', 'ai-agent-for-website' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="mailchimp_enabled"><?php esc_html_e( 'Enable Mailchimp', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="mailchimp_enabled" name="mailchimp_enabled" value="1" <?php checked( $mailchimp_enabled ); ?>>
									<span class="slider"></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="mailchimp_api_key"><?php esc_html_e( 'API Key', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<div class="aiagent-api-key-wrapper">
									<input type="password" id="mailchimp_api_key" name="mailchimp_api_key" 
										value="<?php echo esc_attr( $mailchimp_settings['mailchimp_api_key'] ?? '' ); ?>" 
										class="large-text" autocomplete="off" placeholder="xxxxxxxx-us1">
									<button type="button" class="button aiagent-toggle-password">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Get your API key from', 'ai-agent-for-website' ); ?>
									<a href="https://admin.mailchimp.com/account/api/" target="_blank"><?php esc_html_e( 'Mailchimp Account Settings', 'ai-agent-for-website' ); ?></a>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="mailchimp_list_id"><?php esc_html_e( 'Audience/List ID', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="text" id="mailchimp_list_id" name="mailchimp_list_id" 
									value="<?php echo esc_attr( $mailchimp_settings['mailchimp_list_id'] ?? '' ); ?>" 
									class="large-text" placeholder="e.g., abc123def4">
								<p class="description"><?php esc_html_e( 'Find your Audience ID in Mailchimp under Audience → Settings → Audience name and defaults.', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
					</table>
					<?php if ( $mailchimp_connected ) : ?>
						<div class="aiagent-integration-status aiagent-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Mailchimp is configured. Users who opt-in will be subscribed automatically.', 'ai-agent-for-website' ); ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<button type="button" class="button button-primary aiagent-modal-save" data-integration="mailchimp">
						<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- WooCommerce Modal -->
		<?php if ( $woo_active ) : ?>
		<div id="woocommerce-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #96588a 0%, #7b4b7e 100%);">
						<svg viewBox="0 0 24 24" fill="white" width="20" height="20">
							<path d="M2 3h20c.6 0 1 .4 1 1v1c0 .6-.4 1-1 1H2c-.6 0-1-.4-1-1V4c0-.6.4-1 1-1zm0 4h20l-1.5 11c-.1.6-.5 1-1 1H4.5c-.5 0-.9-.4-1-1L2 7zm4 3c0 .6.4 1 1 1s1-.4 1-1-.4-1-1-1-1 .4-1 1zm4 0c0 .6.4 1 1 1s1-.4 1-1-.4-1-1-1-1 .4-1 1zm4 0c0 .6.4 1 1 1s1-.4 1-1-.4-1-1-1-1 .4-1 1z"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure WooCommerce Integration', 'ai-agent-for-website' ); ?> <span class="aiagent-badge aiagent-badge-beta"><?php esc_html_e( 'Beta', 'ai-agent-for-website' ); ?></span></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<p class="description"><?php esc_html_e( 'Enable customers to search products, compare items, and add to cart directly from the chat widget.', 'ai-agent-for-website' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="woo_enabled"><?php esc_html_e( 'Enable WooCommerce Integration', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_enabled" name="woo_enabled" value="1" <?php checked( $woo_enabled ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Allow customers to search and browse products in chat', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="woo_show_prices"><?php esc_html_e( 'Show Prices', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_show_prices" name="woo_show_prices" value="1" <?php checked( $woo_settings['show_prices'] ?? true ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Display product prices in search results', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="woo_show_add_to_cart"><?php esc_html_e( 'Show Add to Cart', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_show_add_to_cart" name="woo_show_add_to_cart" value="1" <?php checked( $woo_settings['show_add_to_cart'] ?? true ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Allow customers to add products to cart from chat', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="woo_show_related_products"><?php esc_html_e( 'Show Related Products', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_show_related_products" name="woo_show_related_products" value="1" <?php checked( $woo_settings['show_related_products'] ?? true ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Suggest related products based on search', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="woo_show_product_comparison"><?php esc_html_e( 'Enable Product Comparison', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_show_product_comparison" name="woo_show_product_comparison" value="1" <?php checked( $woo_settings['show_product_comparison'] ?? true ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Allow customers to compare multiple products', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="woo_max_products_display"><?php esc_html_e( 'Max Products Display', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="number" id="woo_max_products_display" name="woo_max_products_display" 
									value="<?php echo esc_attr( $woo_settings['max_products_display'] ?? 6 ); ?>" 
									min="1" max="20" style="width: 80px;">
								<p class="description"><?php esc_html_e( 'Maximum number of products to show in search results', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="woo_include_out_of_stock"><?php esc_html_e( 'Include Out of Stock', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_include_out_of_stock" name="woo_include_out_of_stock" value="1" <?php checked( $woo_settings['include_out_of_stock'] ?? false ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Show out of stock products in search results', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
					</table>

					<!-- Knowledge Base Sync Section -->
					<h4 style="margin: 20px 0 10px; border-top: 1px solid #ddd; padding-top: 20px;">
						<span class="dashicons dashicons-database" style="margin-right: 5px;"></span>
						<?php esc_html_e( 'Knowledge Base Sync', 'ai-agent-for-website' ); ?>
					</h4>
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Sync your product catalog to the AI knowledge base so the assistant can answer questions about your products.', 'ai-agent-for-website' ); ?>
					</p>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="woo_sync_to_kb"><?php esc_html_e( 'Sync Products to Knowledge Base', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_sync_to_kb" name="woo_sync_to_kb" value="1" <?php checked( $woo_settings['sync_to_kb'] ?? true ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Add product information to AI knowledge base', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="woo_auto_sync"><?php esc_html_e( 'Auto-Sync on Product Changes', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="woo_auto_sync" name="woo_auto_sync" value="1" <?php checked( $woo_settings['auto_sync'] ?? false ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically update knowledge base when products are added, updated, or deleted', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Include in Knowledge Base', 'ai-agent-for-website' ); ?></th>
							<td>
								<fieldset>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" id="woo_kb_include_descriptions" name="woo_kb_include_descriptions" value="1" <?php checked( $woo_settings['kb_include_descriptions'] ?? true ); ?>>
										<?php esc_html_e( 'Product descriptions', 'ai-agent-for-website' ); ?>
									</label>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" id="woo_kb_include_prices" name="woo_kb_include_prices" value="1" <?php checked( $woo_settings['kb_include_prices'] ?? true ); ?>>
										<?php esc_html_e( 'Product prices', 'ai-agent-for-website' ); ?>
									</label>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" id="woo_kb_include_categories" name="woo_kb_include_categories" value="1" <?php checked( $woo_settings['kb_include_categories'] ?? true ); ?>>
										<?php esc_html_e( 'Product categories', 'ai-agent-for-website' ); ?>
									</label>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" id="woo_kb_include_attributes" name="woo_kb_include_attributes" value="1" <?php checked( $woo_settings['kb_include_attributes'] ?? true ); ?>>
										<?php esc_html_e( 'Product attributes (size, color, etc.)', 'ai-agent-for-website' ); ?>
									</label>
									<label style="display: block;">
										<input type="checkbox" id="woo_kb_include_stock_status" name="woo_kb_include_stock_status" value="1" <?php checked( $woo_settings['kb_include_stock_status'] ?? true ); ?>>
										<?php esc_html_e( 'Stock availability', 'ai-agent-for-website' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Manual Sync', 'ai-agent-for-website' ); ?></th>
							<td>
								<button type="button" class="button" id="woo_sync_now_btn">
									<span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
									<?php esc_html_e( 'Sync Products Now', 'ai-agent-for-website' ); ?>
								</button>
								<span id="woo_sync_status" style="margin-left: 10px;"></span>
								<?php
								$last_sync    = AIAGENT_WooCommerce_Integration::get_last_sync_time();
								$synced_count = AIAGENT_WooCommerce_Integration::get_synced_product_count();
								if ( $last_sync ) :
									?>
									<p class="description" style="margin-top: 8px;">
										<?php
										printf(
											/* translators: 1: product count, 2: last sync time */
											esc_html__( 'Last synced: %1$d products on %2$s', 'ai-agent-for-website' ),
											intval( $synced_count ),
											esc_html( $last_sync )
										);
										?>
									</p>
								<?php else : ?>
									<p class="description" style="margin-top: 8px;">
										<?php esc_html_e( 'Products have not been synced yet.', 'ai-agent-for-website' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<?php if ( $woo_enabled ) : ?>
						<div class="aiagent-integration-status aiagent-status-connected" style="margin-top: 15px;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'WooCommerce integration is enabled. Customers can search and shop from chat.', 'ai-agent-for-website' ); ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<button type="button" class="button button-primary aiagent-modal-save" data-integration="woocommerce">
						<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Google Calendar Modal -->
		<div id="gcalendar-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content aiagent-modal-content-large">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #4285f4 0%, #0d47a1 100%);">
						<svg viewBox="0 0 24 24" fill="white" width="20" height="20">
							<path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure Google Calendar', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<?php if ( $gcalendar_connected && $gcalendar_user ) : ?>
						<div class="aiagent-integration-status aiagent-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php
							/* translators: %s: User email */
							printf( esc_html__( 'Connected as %s', 'ai-agent-for-website' ), esc_html( $gcalendar_user['email'] ) );
							?>
						</div>

						<!-- Enable/Disable Toggle -->
						<table class="form-table">
							<tr>
								<th scope="row"><label for="gcalendar_enabled"><?php esc_html_e( 'Enable Calendar Booking', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<label class="aiagent-switch">
										<input type="checkbox" id="gcalendar_enabled" name="gcalendar_enabled" value="1" <?php checked( $gcalendar_settings['enabled'] ?? false ); ?>>
										<span class="slider"></span>
									</label>
									<p class="description"><?php esc_html_e( 'Allow users to book meetings after conversations', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="gcalendar_prompt_after_chat"><?php esc_html_e( 'Prompt After Chat', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<label class="aiagent-switch">
										<input type="checkbox" id="gcalendar_prompt_after_chat" name="gcalendar_prompt_after_chat" value="1" <?php checked( $gcalendar_settings['prompt_after_chat'] ?? true ); ?>>
										<span class="slider"></span>
									</label>
									<p class="description"><?php esc_html_e( 'Ask users if they want to schedule a meeting when conversation ends', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="gcalendar_prompt_message"><?php esc_html_e( 'Prompt Message', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="text" id="gcalendar_prompt_message" name="gcalendar_prompt_message" 
										value="<?php echo esc_attr( $gcalendar_settings['prompt_message'] ?? 'Would you like to schedule a meeting or follow-up?' ); ?>" 
										class="large-text">
								</td>
							</tr>
						</table>

						<h4 style="margin: 20px 0 10px; border-top: 1px solid #ddd; padding-top: 20px;">
							<span class="dashicons dashicons-clock" style="margin-right: 5px;"></span>
							<?php esc_html_e( 'Scheduling Settings', 'ai-agent-for-website' ); ?>
						</h4>

						<table class="form-table">
							<tr>
								<th scope="row"><label for="gcalendar_default_duration"><?php esc_html_e( 'Default Duration', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<select id="gcalendar_default_duration" name="gcalendar_default_duration">
										<option value="15" <?php selected( $gcalendar_settings['default_duration'] ?? 30, 15 ); ?>><?php esc_html_e( '15 minutes', 'ai-agent-for-website' ); ?></option>
										<option value="30" <?php selected( $gcalendar_settings['default_duration'] ?? 30, 30 ); ?>><?php esc_html_e( '30 minutes', 'ai-agent-for-website' ); ?></option>
										<option value="45" <?php selected( $gcalendar_settings['default_duration'] ?? 30, 45 ); ?>><?php esc_html_e( '45 minutes', 'ai-agent-for-website' ); ?></option>
										<option value="60" <?php selected( $gcalendar_settings['default_duration'] ?? 30, 60 ); ?>><?php esc_html_e( '1 hour', 'ai-agent-for-website' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="gcalendar_buffer_time"><?php esc_html_e( 'Buffer Time', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<select id="gcalendar_buffer_time" name="gcalendar_buffer_time">
										<option value="0" <?php selected( $gcalendar_settings['buffer_time'] ?? 15, 0 ); ?>><?php esc_html_e( 'No buffer', 'ai-agent-for-website' ); ?></option>
										<option value="5" <?php selected( $gcalendar_settings['buffer_time'] ?? 15, 5 ); ?>><?php esc_html_e( '5 minutes', 'ai-agent-for-website' ); ?></option>
										<option value="10" <?php selected( $gcalendar_settings['buffer_time'] ?? 15, 10 ); ?>><?php esc_html_e( '10 minutes', 'ai-agent-for-website' ); ?></option>
										<option value="15" <?php selected( $gcalendar_settings['buffer_time'] ?? 15, 15 ); ?>><?php esc_html_e( '15 minutes', 'ai-agent-for-website' ); ?></option>
										<option value="30" <?php selected( $gcalendar_settings['buffer_time'] ?? 15, 30 ); ?>><?php esc_html_e( '30 minutes', 'ai-agent-for-website' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Minimum time between appointments', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="gcalendar_days_ahead"><?php esc_html_e( 'Days Ahead', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="number" id="gcalendar_days_ahead" name="gcalendar_days_ahead" 
										value="<?php echo esc_attr( $gcalendar_settings['days_ahead'] ?? 14 ); ?>" 
										min="1" max="60" style="width: 80px;">
									<p class="description"><?php esc_html_e( 'How many days in advance users can book', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
						</table>

						<h4 style="margin: 20px 0 10px; border-top: 1px solid #ddd; padding-top: 20px;">
							<span class="dashicons dashicons-businessman" style="margin-right: 5px;"></span>
							<?php esc_html_e( 'Business Hours', 'ai-agent-for-website' ); ?>
						</h4>

						<table class="form-table">
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Available Hours', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="time" id="gcalendar_business_hours_start" name="gcalendar_business_hours_start" 
										value="<?php echo esc_attr( $gcalendar_settings['business_hours_start'] ?? '09:00' ); ?>">
									<span style="margin: 0 10px;"><?php esc_html_e( 'to', 'ai-agent-for-website' ); ?></span>
									<input type="time" id="gcalendar_business_hours_end" name="gcalendar_business_hours_end" 
										value="<?php echo esc_attr( $gcalendar_settings['business_hours_end'] ?? '17:00' ); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Working Days', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<?php
									$working_days = $gcalendar_settings['working_days'] ?? array( 1, 2, 3, 4, 5 );
									$days         = array(
										1 => __( 'Monday', 'ai-agent-for-website' ),
										2 => __( 'Tuesday', 'ai-agent-for-website' ),
										3 => __( 'Wednesday', 'ai-agent-for-website' ),
										4 => __( 'Thursday', 'ai-agent-for-website' ),
										5 => __( 'Friday', 'ai-agent-for-website' ),
										6 => __( 'Saturday', 'ai-agent-for-website' ),
										7 => __( 'Sunday', 'ai-agent-for-website' ),
									);
									foreach ( $days as $num => $label ) :
										?>
										<label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
											<input type="checkbox" name="gcalendar_working_days[]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( $num, $working_days, true ) ); ?>>
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						</table>

						<h4 style="margin: 20px 0 10px; border-top: 1px solid #ddd; padding-top: 20px;">
							<span class="dashicons dashicons-edit" style="margin-right: 5px;"></span>
							<?php esc_html_e( 'Event Defaults', 'ai-agent-for-website' ); ?>
						</h4>

						<table class="form-table">
							<tr>
								<th scope="row"><label for="gcalendar_event_title_template"><?php esc_html_e( 'Event Title Template', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="text" id="gcalendar_event_title_template" name="gcalendar_event_title_template" 
										value="<?php echo esc_attr( $gcalendar_settings['event_title_template'] ?? 'Meeting with {user_name}' ); ?>" 
										class="large-text">
									<p class="description"><?php esc_html_e( 'Use {user_name} and {user_email} as placeholders', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="gcalendar_event_description_template"><?php esc_html_e( 'Event Description', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<textarea id="gcalendar_event_description_template" name="gcalendar_event_description_template" 
										rows="2" class="large-text"><?php echo esc_textarea( $gcalendar_settings['event_description_template'] ?? 'Scheduled via AI Agent chat widget.' ); ?></textarea>
								</td>
							</tr>
						</table>

						<div class="aiagent-modal-actions" style="margin-top: 20px;">
							<button type="button" class="button button-link-delete aiagent-gcalendar-disconnect">
								<?php esc_html_e( 'Disconnect', 'ai-agent-for-website' ); ?>
							</button>
						</div>

					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Connect your Google Calendar to let users book meetings after conversations.', 'ai-agent-for-website' ); ?>
							<a href="https://console.cloud.google.com/apis/credentials" target="_blank"><?php esc_html_e( 'Create OAuth credentials →', 'ai-agent-for-website' ); ?></a>
						</p>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="gcalendar_client_id"><?php esc_html_e( 'Client ID', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<input type="text" id="gcalendar_client_id" name="gcalendar_client_id" 
										value="<?php echo esc_attr( $gcalendar_settings['client_id'] ?? '' ); ?>" 
										class="large-text" placeholder="xxxxxx.apps.googleusercontent.com">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="gcalendar_client_secret"><?php esc_html_e( 'Client Secret', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<div class="aiagent-api-key-wrapper">
										<input type="password" id="gcalendar_client_secret" name="gcalendar_client_secret" 
											value="<?php echo esc_attr( $gcalendar_settings['client_secret'] ?? '' ); ?>" 
											class="large-text" autocomplete="off">
										<button type="button" class="button aiagent-toggle-password">
											<span class="dashicons dashicons-visibility"></span>
										</button>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Redirect URI', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<code class="aiagent-copy-text" id="gcalendar-redirect-uri"><?php echo esc_html( admin_url( 'admin.php?page=ai-agent-settings&tab=integrations&gcalendar_callback=1' ) ); ?></code>
									<button type="button" class="button button-small aiagent-copy-btn" data-target="gcalendar-redirect-uri">
										<span class="dashicons dashicons-clipboard"></span>
									</button>
									<p class="description"><?php esc_html_e( 'Add this URL to your OAuth consent screen.', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Required Scopes', 'ai-agent-for-website' ); ?></label></th>
								<td>
									<code style="display: block; font-size: 11px;">https://www.googleapis.com/auth/calendar.events</code>
									<code style="display: block; font-size: 11px; margin-top: 5px;">https://www.googleapis.com/auth/calendar.readonly</code>
									<p class="description"><?php esc_html_e( 'Enable Google Calendar API in your project.', 'ai-agent-for-website' ); ?></p>
								</td>
							</tr>
						</table>
						<div class="aiagent-modal-actions">
							<button type="button" id="aiagent-gcalendar-connect" class="button button-primary" <?php disabled( empty( $gcalendar_settings['client_id'] ) || empty( $gcalendar_settings['client_secret'] ) ); ?>>
								<span class="dashicons dashicons-admin-links"></span>
								<?php esc_html_e( 'Connect Google Calendar', 'ai-agent-for-website' ); ?>
							</button>
							<span class="aiagent-gcalendar-status"></span>
						</div>
					<?php endif; ?>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<?php if ( $gcalendar_connected ) : ?>
						<button type="button" class="button button-primary aiagent-modal-save" data-integration="gcalendar">
							<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button button-primary aiagent-modal-save" data-integration="gcalendar">
							<?php esc_html_e( 'Save Credentials', 'ai-agent-for-website' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Calendly Modal -->
		<div id="calendly-modal" class="aiagent-integration-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content aiagent-modal-content-large">
				<div class="aiagent-modal-header">
					<div class="aiagent-modal-header-icon" style="background: linear-gradient(135deg, #006bff 0%, #0052cc 100%);">
						<svg viewBox="0 0 24 24" fill="white" width="20" height="20">
							<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Configure Calendly', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<p class="description" style="margin-bottom: 20px;">
						<?php esc_html_e( 'Add your Calendly scheduling link to let users book meetings after conversations.', 'ai-agent-for-website' ); ?>
						<a href="https://calendly.com" target="_blank"><?php esc_html_e( 'Get Calendly →', 'ai-agent-for-website' ); ?></a>
					</p>

					<!-- Enable/Disable Toggle -->
					<table class="form-table">
						<tr>
							<th scope="row"><label for="calendly_enabled"><?php esc_html_e( 'Enable Calendly', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="calendly_enabled" name="calendly_enabled" value="1" <?php checked( $calendly_settings['enabled'] ?? false ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Allow users to schedule meetings via Calendly', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="calendly_scheduling_url"><?php esc_html_e( 'Scheduling URL', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="url" id="calendly_scheduling_url" name="calendly_scheduling_url" 
									value="<?php echo esc_attr( $calendly_settings['scheduling_url'] ?? '' ); ?>" 
									class="large-text" placeholder="https://calendly.com/your-name/30min">
								<p class="description"><?php esc_html_e( 'Your Calendly scheduling link (e.g., calendly.com/your-name or calendly.com/your-name/event-type)', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="calendly_integration_type"><?php esc_html_e( 'Display Type', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<select id="calendly_integration_type" name="calendly_integration_type">
									<option value="popup" <?php selected( $calendly_settings['integration_type'] ?? 'embed', 'popup' ); ?>><?php esc_html_e( 'Popup Widget', 'ai-agent-for-website' ); ?></option>
									<option value="embed" <?php selected( $calendly_settings['integration_type'] ?? 'embed', 'embed' ); ?>><?php esc_html_e( 'Inline Embed', 'ai-agent-for-website' ); ?></option>
									<option value="link" <?php selected( $calendly_settings['integration_type'] ?? 'embed', 'link' ); ?>><?php esc_html_e( 'External Link', 'ai-agent-for-website' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'How the Calendly scheduler should be displayed', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
					</table>

					<h4 style="margin: 20px 0 10px; border-top: 1px solid #ddd; padding-top: 20px;">
						<span class="dashicons dashicons-format-chat" style="margin-right: 5px;"></span>
						<?php esc_html_e( 'Chat Prompt Settings', 'ai-agent-for-website' ); ?>
					</h4>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="calendly_prompt_after_chat"><?php esc_html_e( 'Prompt After Chat', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label class="aiagent-switch">
									<input type="checkbox" id="calendly_prompt_after_chat" name="calendly_prompt_after_chat" value="1" <?php checked( $calendly_settings['prompt_after_chat'] ?? true ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Ask users if they want to schedule a meeting when conversation ends', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="calendly_prompt_message"><?php esc_html_e( 'Prompt Message', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="text" id="calendly_prompt_message" name="calendly_prompt_message" 
									value="<?php echo esc_attr( $calendly_settings['prompt_message'] ?? 'Would you like to schedule a call with us?' ); ?>" 
									class="large-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="calendly_button_text"><?php esc_html_e( 'Button Text', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="text" id="calendly_button_text" name="calendly_button_text" 
									value="<?php echo esc_attr( $calendly_settings['button_text'] ?? 'Schedule a Meeting' ); ?>" 
									class="regular-text">
							</td>
						</tr>
					</table>

					<h4 style="margin: 20px 0 10px; border-top: 1px solid #ddd; padding-top: 20px;">
						<span class="dashicons dashicons-admin-appearance" style="margin-right: 5px;"></span>
						<?php esc_html_e( 'Widget Appearance', 'ai-agent-for-website' ); ?>
					</h4>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="calendly_embed_height"><?php esc_html_e( 'Embed Height', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="number" id="calendly_embed_height" name="calendly_embed_height" 
									value="<?php echo esc_attr( $calendly_settings['embed_height'] ?? 630 ); ?>" 
									min="400" max="1000" style="width: 100px;"> px
								<p class="description"><?php esc_html_e( 'Height of the embedded Calendly widget', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="calendly_primary_color"><?php esc_html_e( 'Primary Color', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<input type="color" id="calendly_primary_color" name="calendly_primary_color" 
									value="<?php echo esc_attr( $calendly_settings['primary_color'] ?? '#006bff' ); ?>">
								<span class="description"><?php esc_html_e( 'Customize Calendly accent color', 'ai-agent-for-website' ); ?></span>
							</td>
						</tr>
						<tr>
							<th scope="row"><label><?php esc_html_e( 'Widget Options', 'ai-agent-for-website' ); ?></label></th>
							<td>
								<label style="display: block; margin-bottom: 8px;">
									<input type="checkbox" id="calendly_hide_event_details" name="calendly_hide_event_details" value="1" <?php checked( $calendly_settings['hide_event_details'] ?? false ); ?>>
									<?php esc_html_e( 'Hide event type details', 'ai-agent-for-website' ); ?>
								</label>
								<label style="display: block;">
									<input type="checkbox" id="calendly_hide_gdpr_banner" name="calendly_hide_gdpr_banner" value="1" <?php checked( $calendly_settings['hide_gdpr_banner'] ?? false ); ?>>
									<?php esc_html_e( 'Hide GDPR cookie banner', 'ai-agent-for-website' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button aiagent-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?></button>
					<button type="button" class="button button-primary aiagent-modal-save" data-integration="calendly">
						<?php esc_html_e( 'Save', 'ai-agent-for-website' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Appearance tab content.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_appearance_tab( $settings ) {
		?>
		<div class="aiagent-appearance-grid">
			<div class="aiagent-card">
				<h2><?php esc_html_e( 'Widget Appearance', 'ai-agent-for-website' ); ?></h2>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ai_name"><?php esc_html_e( 'AI Name', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="text" 
									id="ai_name" 
									name="ai_name" 
									value="<?php echo esc_attr( $settings['ai_name'] ?? 'AI Assistant' ); ?>" 
									class="regular-text">
							<p class="description">
								<?php esc_html_e( 'The name shown in the chat widget header', 'ai-agent-for-website' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="avatar_url"><?php esc_html_e( 'Avatar Image', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<div class="aiagent-avatar-upload">
								<input type="hidden" 
										id="avatar_url" 
										name="avatar_url" 
										value="<?php echo esc_attr( $settings['avatar_url'] ?? '' ); ?>">
								<div class="aiagent-avatar-preview" id="avatar_preview">
									<?php if ( ! empty( $settings['avatar_url'] ) ) : ?>
										<img src="<?php echo esc_url( $settings['avatar_url'] ); ?>" alt="Avatar">
									<?php else : ?>
										<span class="aiagent-avatar-placeholder">
											<svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40">
												<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
											</svg>
										</span>
									<?php endif; ?>
								</div>
								<button type="button" class="button" id="upload_avatar_btn">
									<?php esc_html_e( 'Upload Avatar', 'ai-agent-for-website' ); ?>
								</button>
								<button type="button" class="button" id="remove_avatar_btn" <?php echo empty( $settings['avatar_url'] ) ? 'style="display:none;"' : ''; ?>>
									<?php esc_html_e( 'Remove', 'ai-agent-for-website' ); ?>
								</button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="widget_position"><?php esc_html_e( 'Widget Position', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<select id="widget_position" name="widget_position">
								<option value="bottom-right" <?php selected( $settings['widget_position'] ?? '', 'bottom-right' ); ?>>
									<?php esc_html_e( 'Bottom Right', 'ai-agent-for-website' ); ?>
								</option>
								<option value="bottom-left" <?php selected( $settings['widget_position'] ?? '', 'bottom-left' ); ?>>
									<?php esc_html_e( 'Bottom Left', 'ai-agent-for-website' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="primary_color"><?php esc_html_e( 'Primary Color', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="color" 
									id="primary_color" 
									name="primary_color" 
									value="<?php echo esc_attr( $settings['primary_color'] ?? '#0073aa' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="show_powered_by"><?php esc_html_e( 'Show Powered By', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<label class="aiagent-switch">
								<input type="checkbox" 
										id="show_powered_by" 
										name="show_powered_by" 
										value="1" 
										<?php checked( $settings['show_powered_by'] ?? true ); ?>>
								<span class="slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Show branding text at the bottom of chat widget', 'ai-agent-for-website' ); ?>
							</p>
						</td>
					</tr>
					<tr class="powered-by-text-row" <?php echo empty( $settings['show_powered_by'] ) ? 'style="display:none;"' : ''; ?>>
						<th scope="row">
							<label for="powered_by_text"><?php esc_html_e( 'Powered By Text', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<input type="text" 
									id="powered_by_text" 
									name="powered_by_text" 
									class="regular-text"
									value="<?php echo esc_attr( $settings['powered_by_text'] ?? get_bloginfo( 'name' ) ); ?>"
									placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<p class="description">
								<?php esc_html_e( 'Custom text to display at the bottom of the chat widget. Leave empty to use site name.', 'ai-agent-for-website' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="widget_button_size"><?php esc_html_e( 'Button Size', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<select id="widget_button_size" name="widget_button_size">
								<option value="small" <?php selected( $settings['widget_button_size'] ?? '', 'small' ); ?>>
									<?php esc_html_e( 'Small (48px)', 'ai-agent-for-website' ); ?>
								</option>
								<option value="medium" <?php selected( $settings['widget_button_size'] ?? 'medium', 'medium' ); ?>>
									<?php esc_html_e( 'Medium (60px)', 'ai-agent-for-website' ); ?>
								</option>
								<option value="large" <?php selected( $settings['widget_button_size'] ?? '', 'large' ); ?>>
									<?php esc_html_e( 'Large (72px)', 'ai-agent-for-website' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="widget_animation"><?php esc_html_e( 'Open Animation', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<select id="widget_animation" name="widget_animation">
								<option value="slide" <?php selected( $settings['widget_animation'] ?? 'slide', 'slide' ); ?>>
									<?php esc_html_e( 'Slide Up', 'ai-agent-for-website' ); ?>
								</option>
								<option value="fade" <?php selected( $settings['widget_animation'] ?? '', 'fade' ); ?>>
									<?php esc_html_e( 'Fade In', 'ai-agent-for-website' ); ?>
								</option>
								<option value="scale" <?php selected( $settings['widget_animation'] ?? '', 'scale' ); ?>>
									<?php esc_html_e( 'Scale', 'ai-agent-for-website' ); ?>
								</option>
								<option value="none" <?php selected( $settings['widget_animation'] ?? '', 'none' ); ?>>
									<?php esc_html_e( 'None', 'ai-agent-for-website' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="widget_sound"><?php esc_html_e( 'Enable Sound', 'ai-agent-for-website' ); ?></label>
						</th>
						<td>
							<label class="aiagent-switch">
								<input type="checkbox" 
										id="widget_sound" 
										name="widget_sound" 
										value="1" 
										<?php checked( ! empty( $settings['widget_sound'] ) ); ?>>
								<span class="slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Play notification sound when receiving messages', 'ai-agent-for-website' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="aiagent-card aiagent-preview-card">
				<h2><?php esc_html_e( 'Live Preview', 'ai-agent-for-website' ); ?></h2>
				<div class="aiagent-preview-container">
					<div class="aiagent-preview-widget" id="aiagent-preview-widget" style="--aiagent-primary: <?php echo esc_attr( $settings['primary_color'] ?? '#0073aa' ); ?>;">
						<div class="aiagent-preview-window animation-<?php echo esc_attr( $settings['widget_animation'] ?? 'slide' ); ?>" id="preview-window">
							<div class="aiagent-preview-header">
								<div class="aiagent-preview-header-info">
									<div class="aiagent-preview-avatar" id="preview-avatar">
										<?php if ( ! empty( $settings['avatar_url'] ) ) : ?>
											<img src="<?php echo esc_url( $settings['avatar_url'] ); ?>" alt="Avatar">
										<?php else : ?>
											<svg viewBox="0 0 24 24" fill="currentColor">
												<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
											</svg>
										<?php endif; ?>
									</div>
									<div>
										<div class="aiagent-preview-name" id="preview-name"><?php echo esc_html( $settings['ai_name'] ?? 'AI Assistant' ); ?></div>
										<div class="aiagent-preview-status"><?php esc_html_e( 'Online now', 'ai-agent-for-website' ); ?></div>
									</div>
								</div>
								<div class="aiagent-preview-actions">
									<button type="button" class="aiagent-preview-btn" title="<?php esc_attr_e( 'New conversation', 'ai-agent-for-website' ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
											<line x1="12" y1="5" x2="12" y2="19"></line>
											<line x1="5" y1="12" x2="19" y2="12"></line>
										</svg>
									</button>
									<button type="button" class="aiagent-preview-btn" title="<?php esc_attr_e( 'Close', 'ai-agent-for-website' ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
											<line x1="18" y1="6" x2="6" y2="18"></line>
											<line x1="6" y1="6" x2="18" y2="18"></line>
										</svg>
									</button>
								</div>
							</div>
							<div class="aiagent-preview-messages">
								<div class="aiagent-preview-message aiagent-preview-message-ai" id="preview-welcome">
									<?php echo esc_html( $settings['welcome_message'] ?? 'Hello! How can I help you today?' ); ?>
								</div>
							</div>
							<div class="aiagent-preview-input">
								<input type="text" placeholder="<?php esc_attr_e( 'Type your message...', 'ai-agent-for-website' ); ?>" disabled>
								<button type="button" disabled>
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
										<line x1="22" y1="2" x2="11" y2="13"></line>
										<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
									</svg>
								</button>
							</div>
							<div class="aiagent-preview-powered" id="preview-powered">
								<?php
								$powered_text = $settings['powered_by_text'] ?? '';
								echo esc_html( ! empty( $powered_text ) ? $powered_text : get_bloginfo( 'name' ) );
								?>
							</div>
						</div>
						<button type="button" class="aiagent-preview-toggle size-<?php echo esc_attr( $settings['widget_button_size'] ?? 'medium' ); ?>" id="preview-toggle">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24">
								<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
							</svg>
							<span class="sound-indicator" id="preview-sound-indicator" <?php echo empty( $settings['widget_sound'] ) ? 'style="display:none;"' : ''; ?>>
								<svg viewBox="0 0 24 24" fill="currentColor" width="10" height="10">
									<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/>
								</svg>
							</span>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the User Information tab content.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_user_info_tab( $settings ) {
		?>
		<div class="aiagent-card">
			<h2><?php esc_html_e( 'User Information Collection', 'ai-agent-for-website' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure what information to collect from users before they start chatting.', 'ai-agent-for-website' ); ?>
			</p>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="require_user_info"><?php esc_html_e( 'Require Name & Email', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="require_user_info" 
									name="require_user_info" 
									value="1" 
									<?php checked( ! empty( $settings['require_user_info'] ) ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Ask users for their name and email before starting a conversation', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="require_phone"><?php esc_html_e( 'Show Phone Number Field', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="require_phone" 
									name="require_phone" 
									value="1" 
									<?php checked( ! empty( $settings['require_phone'] ) ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show phone number field in the user info form', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="phone_required"><?php esc_html_e( 'Make Phone Required', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="phone_required" 
									name="phone_required" 
									value="1" 
									<?php checked( ! empty( $settings['phone_required'] ) ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Make the phone number field required (only if phone field is enabled)', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Consent Options', 'ai-agent-for-website' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure consent checkboxes that appear before the user starts a conversation.', 'ai-agent-for-website' ); ?>
			</p>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="consent_ai_enabled"><?php esc_html_e( 'AI Consent (Required)', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="consent_ai_enabled" 
									name="consent_ai_enabled" 
									value="1" 
									<?php checked( $settings['consent_ai_enabled'] ?? true ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Require users to consent to AI interaction before chatting (mandatory)', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="consent_ai_text"><?php esc_html_e( 'AI Consent Text', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="consent_ai_text" 
								name="consent_ai_text" 
								value="<?php echo esc_attr( $settings['consent_ai_text'] ?? 'I agree to interact with AI assistance' ); ?>" 
								class="large-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="consent_newsletter"><?php esc_html_e( 'Newsletter Consent (Optional)', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="consent_newsletter" 
									name="consent_newsletter" 
									value="1" 
									<?php checked( ! empty( $settings['consent_newsletter'] ) ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show newsletter subscription checkbox (optional for users)', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="consent_newsletter_text"><?php esc_html_e( 'Newsletter Consent Text', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="consent_newsletter_text" 
								name="consent_newsletter_text" 
								value="<?php echo esc_attr( $settings['consent_newsletter_text'] ?? 'Subscribe to our newsletter' ); ?>" 
								class="large-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="consent_promotional"><?php esc_html_e( 'Promotional Consent (Optional)', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="consent_promotional" 
									name="consent_promotional" 
									value="1" 
									<?php checked( ! empty( $settings['consent_promotional'] ) ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show promotional consent checkbox (optional for users)', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="consent_promotional_text"><?php esc_html_e( 'Promotional Consent Text', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="consent_promotional_text" 
								name="consent_promotional_text" 
								value="<?php echo esc_attr( $settings['consent_promotional_text'] ?? 'Receive promotional updates' ); ?>" 
								class="large-text">
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'User Data Preview', 'ai-agent-for-website' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'This is how the user information form will appear to visitors:', 'ai-agent-for-website' ); ?>
			</p>
			<div class="aiagent-user-form-preview">
				<div class="aiagent-form-preview-box">
					<div class="aiagent-form-preview-field">
						<label><?php esc_html_e( 'Name', 'ai-agent-for-website' ); ?> <span class="required">*</span></label>
						<input type="text" placeholder="<?php esc_attr_e( 'Enter your name', 'ai-agent-for-website' ); ?>" disabled>
					</div>
					<div class="aiagent-form-preview-field">
						<label><?php esc_html_e( 'Email', 'ai-agent-for-website' ); ?> <span class="required">*</span></label>
						<input type="email" placeholder="<?php esc_attr_e( 'Enter your email', 'ai-agent-for-website' ); ?>" disabled>
					</div>
					<div class="aiagent-form-preview-field aiagent-phone-field" id="phone-preview-field" style="<?php echo empty( $settings['require_phone'] ) ? 'display:none;' : ''; ?>">
						<label>
							<?php esc_html_e( 'Phone', 'ai-agent-for-website' ); ?> 
							<span class="required" id="phone-required-star" style="<?php echo empty( $settings['phone_required'] ) ? 'display:none;' : ''; ?>">*</span>
						</label>
						<input type="tel" placeholder="<?php esc_attr_e( 'Enter your phone number', 'ai-agent-for-website' ); ?>" disabled>
					</div>
					<button type="button" class="aiagent-preview-submit" disabled>
						<?php esc_html_e( 'Start Chat', 'ai-agent-for-website' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Live Agent tab content.
	 */
	private function render_live_agent_tab() {
		$settings       = AIAGENT_Live_Agent_Manager::get_settings();
		$available_roles = AIAGENT_Live_Agent_Manager::get_available_roles();
		$online_agents  = AIAGENT_Live_Agent_Manager::get_online_agents();
		?>
		<div class="aiagent-card">
			<h2>
				<span class="dashicons dashicons-groups" style="color: #27ae60;"></span>
				<?php esc_html_e( 'Live Agent Mode', 'ai-agent-for-website' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Allow visitors to connect with live human agents when AI cannot help. Agents can take over conversations seamlessly.', 'ai-agent-for-website' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="live_agent_enabled"><?php esc_html_e( 'Enable Live Agent', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="live_agent_enabled" 
									name="live_agent_enabled" 
									value="1" 
									<?php checked( $settings['enabled'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable visitors to connect with live human agents', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_roles"><?php esc_html_e( 'Allowed Roles', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<select id="live_agent_roles" name="live_agent_roles[]" multiple class="aiagent-select-multiple" style="min-width: 300px; min-height: 120px;">
							<?php foreach ( $available_roles as $role_name => $role_label ) : ?>
								<option value="<?php echo esc_attr( $role_name ); ?>" 
									<?php echo in_array( $role_name, $settings['allowed_roles'], true ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $role_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select which user roles can act as live agents. Hold Ctrl/Cmd to select multiple.', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'When to Show Live Agent Option', 'ai-agent-for-website' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="live_agent_show_on_no_results"><?php esc_html_e( 'Show on Negative AI Results', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="live_agent_show_on_no_results" 
									name="live_agent_show_on_no_results" 
									value="1" 
									<?php checked( $settings['show_on_no_results'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show live agent option when AI responds with "I don\'t know" or similar negative responses', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_show_after_messages"><?php esc_html_e( 'Show After Messages', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="number" 
								id="live_agent_show_after_messages" 
								name="live_agent_show_after_messages" 
								value="<?php echo esc_attr( $settings['show_after_messages'] ); ?>" 
								min="0" 
								max="20"
								class="small-text">
						<p class="description">
							<?php esc_html_e( 'Always show the live agent option after this many messages (0 = never auto-show, only on negative results)', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Messages & UI', 'ai-agent-for-website' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="live_agent_connect_button_text"><?php esc_html_e( 'Connect Button Text', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="live_agent_connect_button_text" 
								name="live_agent_connect_button_text" 
								value="<?php echo esc_attr( $settings['connect_button_text'] ); ?>" 
								class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_waiting_message"><?php esc_html_e( 'Waiting Message', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="live_agent_waiting_message" 
								name="live_agent_waiting_message" 
								value="<?php echo esc_attr( $settings['waiting_message'] ); ?>" 
								class="large-text">
						<p class="description">
							<?php esc_html_e( 'Shown while waiting for an agent to connect', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_connected_message"><?php esc_html_e( 'Connected Message', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="live_agent_connected_message" 
								name="live_agent_connected_message" 
								value="<?php echo esc_attr( $settings['connected_message'] ); ?>" 
								class="large-text">
						<p class="description">
							<?php esc_html_e( 'Shown when connected to a live agent', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_offline_message"><?php esc_html_e( 'Offline Message', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="live_agent_offline_message" 
								name="live_agent_offline_message" 
								value="<?php echo esc_attr( $settings['offline_message'] ); ?>" 
								class="large-text">
						<p class="description">
							<?php esc_html_e( 'Shown when no agents are available', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_show_agent_name"><?php esc_html_e( 'Show Agent Name', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="live_agent_show_agent_name" 
									name="live_agent_show_agent_name" 
									value="1" 
									<?php checked( $settings['show_agent_name'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Display the agent\'s name to visitors', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_show_agent_avatar"><?php esc_html_e( 'Show Agent Avatar', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="live_agent_show_agent_avatar" 
									name="live_agent_show_agent_avatar" 
									value="1" 
									<?php checked( $settings['show_agent_avatar'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Display the agent\'s avatar/profile picture', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Agent Queue Settings', 'ai-agent-for-website' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="live_agent_queue_enabled"><?php esc_html_e( 'Enable Queue', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="live_agent_queue_enabled" 
									name="live_agent_queue_enabled" 
									value="1" 
									<?php checked( $settings['queue_enabled'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Queue visitors when all agents are busy', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_queue_message"><?php esc_html_e( 'Queue Message', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="live_agent_queue_message" 
								name="live_agent_queue_message" 
								value="<?php echo esc_attr( $settings['queue_message'] ); ?>" 
								class="large-text">
						<p class="description">
							<?php esc_html_e( 'Use {position} as placeholder for queue position', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_max_concurrent_chats"><?php esc_html_e( 'Max Concurrent Chats', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="number" 
								id="live_agent_max_concurrent_chats" 
								name="live_agent_max_concurrent_chats" 
								value="<?php echo esc_attr( $settings['max_concurrent_chats'] ); ?>" 
								min="1" 
								max="20"
								class="small-text">
						<p class="description">
							<?php esc_html_e( 'Maximum concurrent chats per agent', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_auto_assign"><?php esc_html_e( 'Auto-Assign Agents', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="live_agent_auto_assign" 
									name="live_agent_auto_assign" 
									value="1" 
									<?php checked( $settings['auto_assign'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically assign chats to available agents based on workload', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="live_agent_transfer_conversation"><?php esc_html_e( 'Allow Transfer', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="live_agent_transfer_conversation" 
									name="live_agent_transfer_conversation" 
									value="1" 
									<?php checked( $settings['transfer_conversation'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Allow agents to transfer conversations to other agents', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Agent Status', 'ai-agent-for-website' ); ?></h2>
			
			<?php if ( ! empty( $online_agents ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Agent', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Status', 'ai-agent-for-website' ); ?></th>
							<th><?php esc_html_e( 'Active Chats', 'ai-agent-for-website' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $online_agents as $agent ) : ?>
							<tr>
								<td>
									<img src="<?php echo esc_url( $agent['avatar'] ); ?>" 
										alt="<?php echo esc_attr( $agent['name'] ); ?>" 
										style="width: 24px; height: 24px; border-radius: 50%; vertical-align: middle; margin-right: 8px;">
									<?php echo esc_html( $agent['name'] ); ?>
								</td>
								<td>
									<span class="aiagent-status-badge aiagent-status-<?php echo esc_attr( $agent['status'] ); ?>">
										<?php echo esc_html( ucfirst( $agent['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $agent['active_chats'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description" style="padding: 20px; background: #f9f9f9; border-radius: 8px; text-align: center;">
					<span class="dashicons dashicons-warning" style="color: #dba617;"></span>
					<?php esc_html_e( 'No agents are currently online. Agents must go online from the admin bar to accept live chats.', 'ai-agent-for-website' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Notifications & Logs tab content.
	 */
	private function render_notifications_tab() {
		$notification_settings = AIAGENT_Notification_Manager::get_settings();
		$log_settings          = AIAGENT_Activity_Log_Manager::get_settings();
		?>
		<div class="aiagent-card">
			<h2>
				<span class="dashicons dashicons-bell" style="color: #e74c3c;"></span>
				<?php esc_html_e( 'Notification Settings', 'ai-agent-for-website' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure admin notifications for new conversations, leads, and AI validations.', 'ai-agent-for-website' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="notifications_enabled"><?php esc_html_e( 'Enable Notifications', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="notifications_enabled" 
									name="notifications_enabled" 
									value="1" 
									<?php checked( $notification_settings['enabled'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable the notification system for admin alerts', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_notifications"><?php esc_html_e( 'Email Notifications', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="email_notifications" 
									name="email_notifications" 
									value="1" 
									<?php checked( $notification_settings['email_notifications'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Send email notifications for important events', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_recipients"><?php esc_html_e( 'Email Recipients', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="email_recipients" 
								name="email_recipients" 
								value="<?php echo esc_attr( $notification_settings['email_recipients'] ); ?>" 
								class="regular-text"
								placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Comma-separated list of email addresses to receive notifications', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Notification Events', 'ai-agent-for-website' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="notify_new_conversation"><?php esc_html_e( 'New Conversation', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="notify_new_conversation" 
									name="notify_new_conversation" 
									value="1" 
									<?php checked( $notification_settings['notify_new_conversation'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Notify when a new conversation is started', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="notify_lead_converted"><?php esc_html_e( 'Lead Converted', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="notify_lead_converted" 
									name="notify_lead_converted" 
									value="1" 
									<?php checked( $notification_settings['notify_lead_converted'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Notify when a conversation is converted to a lead', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="notify_conversation_closed"><?php esc_html_e( 'Conversation Closed', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="notify_conversation_closed" 
									name="notify_conversation_closed" 
									value="1" 
									<?php checked( $notification_settings['notify_conversation_closed'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Notify when a conversation is closed', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'AI Validation Settings', 'ai-agent-for-website' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="auto_validate_leads"><?php esc_html_e( 'Enable AI Validation', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="auto_validate_leads" 
									name="auto_validate_leads" 
									value="1" 
									<?php checked( $notification_settings['auto_validate_leads'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Use AI to validate conversations and identify qualified leads', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="validation_prompt"><?php esc_html_e( 'Custom Validation Prompt', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<textarea id="validation_prompt" 
								name="validation_prompt" 
								rows="3" 
								class="large-text"
								placeholder="<?php esc_attr_e( 'Add custom instructions for AI lead validation...', 'ai-agent-for-website' ); ?>"><?php echo esc_textarea( $notification_settings['validation_prompt'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Optional: Add custom criteria for the AI to use when validating leads', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2>
				<span class="dashicons dashicons-list-view" style="color: #3498db;"></span>
				<?php esc_html_e( 'Activity Log Settings', 'ai-agent-for-website' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure activity logging for tracking system events and actions.', 'ai-agent-for-website' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="logs_enabled"><?php esc_html_e( 'Enable Activity Logging', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="logs_enabled" 
									name="logs_enabled" 
									value="1" 
									<?php checked( $log_settings['enabled'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Track all activities in the system', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="retention_days"><?php esc_html_e( 'Log Retention (Days)', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="number" 
								id="retention_days" 
								name="retention_days" 
								value="<?php echo esc_attr( $log_settings['retention_days'] ); ?>" 
								min="1"
								max="365"
								style="width: 100px;">
						<p class="description">
							<?php esc_html_e( 'Number of days to keep log entries before automatic cleanup', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Log Categories', 'ai-agent-for-website' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="log_conversations"><?php esc_html_e( 'Log Conversations', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="log_conversations" 
									name="log_conversations" 
									value="1" 
									<?php checked( $log_settings['log_conversations'] ); ?>>
							<span class="slider"></span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="log_leads"><?php esc_html_e( 'Log Leads', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="log_leads" 
									name="log_leads" 
									value="1" 
									<?php checked( $log_settings['log_leads'] ); ?>>
							<span class="slider"></span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="log_ai_validations"><?php esc_html_e( 'Log AI Validations', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="log_ai_validations" 
									name="log_ai_validations" 
									value="1" 
									<?php checked( $log_settings['log_ai_validations'] ); ?>>
							<span class="slider"></span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="log_integrations"><?php esc_html_e( 'Log Integrations', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="log_integrations" 
									name="log_integrations" 
									value="1" 
									<?php checked( $log_settings['log_integrations'] ); ?>>
							<span class="slider"></span>
						</label>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Log Export to External Services', 'ai-agent-for-website' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="export_to_zapier"><?php esc_html_e( 'Export to Zapier', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="export_to_zapier" 
									name="export_to_zapier" 
									value="1" 
									<?php checked( $log_settings['export_to_zapier'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Send activity logs to a separate Zapier webhook for external tracking', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="zapier_log_webhook"><?php esc_html_e( 'Zapier Log Webhook URL', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="url" 
								id="zapier_log_webhook" 
								name="zapier_log_webhook" 
								value="<?php echo esc_attr( $log_settings['zapier_log_webhook'] ); ?>" 
								class="regular-text"
								placeholder="https://hooks.zapier.com/hooks/catch/...">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="export_to_mailchimp"><?php esc_html_e( 'Tag Mailchimp Contacts', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="export_to_mailchimp" 
									name="export_to_mailchimp" 
									value="1" 
									<?php checked( $log_settings['export_to_mailchimp'] ); ?>>
							<span class="slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Add activity-based tags to Mailchimp contacts for lead/user events', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="aiagent-card">
			<h2><?php esc_html_e( 'Quick Links', 'ai-agent-for-website' ); ?></h2>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-notifications' ) ); ?>" class="button">
					<span class="dashicons dashicons-bell" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'View Notification Center', 'ai-agent-for-website' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-logs' ) ); ?>" class="button" style="margin-left: 10px;">
					<span class="dashicons dashicons-list-view" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'View Activity Logs', 'ai-agent-for-website' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the AI suggestion modal.
	 */
	private function render_ai_modal() {
		?>
		<!-- AI Suggestion Modal -->
		<div id="aiagent-suggest-modal" class="aiagent-modal" style="display: none;">
			<div class="aiagent-modal-overlay"></div>
			<div class="aiagent-modal-content">
				<div class="aiagent-modal-header">
					<h3 id="aiagent-modal-title"><?php esc_html_e( 'AI Suggestion', 'ai-agent-for-website' ); ?></h3>
					<button type="button" class="aiagent-modal-close">&times;</button>
				</div>
				<div class="aiagent-modal-body">
					<div id="aiagent-modal-loading" class="aiagent-modal-loading">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Generating suggestion...', 'ai-agent-for-website' ); ?></p>
					</div>
					<div id="aiagent-modal-result" class="aiagent-modal-result" style="display: none;">
						<label><?php esc_html_e( 'Suggested Text:', 'ai-agent-for-website' ); ?></label>
						<textarea id="aiagent-modal-suggestion" rows="4" class="large-text" readonly></textarea>
					</div>
					<div id="aiagent-modal-error" class="aiagent-modal-error" style="display: none;">
						<p></p>
					</div>
				</div>
				<div class="aiagent-modal-footer">
					<button type="button" class="button" id="aiagent-modal-regenerate">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Regenerate', 'ai-agent-for-website' ); ?>
					</button>
					<button type="button" class="button button-primary" id="aiagent-modal-apply">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Use This', 'ai-agent-for-website' ); ?>
					</button>
					<button type="button" class="button" id="aiagent-modal-cancel">
						<?php esc_html_e( 'Cancel', 'ai-agent-for-website' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		// Nonce already verified in render() before calling this method.
		$settings = AI_Agent_For_Website::get_settings();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in render().
		// Get the active tab to only save settings for that tab.
		$active_tab = isset( $_POST['aiagent_active_tab'] ) ? sanitize_key( $_POST['aiagent_active_tab'] ) : 'general';

		// Save settings based on active tab to prevent overwriting other tab settings.
		switch ( $active_tab ) {
			case 'general':
				if ( isset( $_POST['api_key'] ) ) {
					$settings['api_key'] = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
				}
				$settings['enabled']            = ! empty( $_POST['enabled'] );
				$settings['ai_name']            = isset( $_POST['ai_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_name'] ) ) : ( $settings['ai_name'] ?? 'AI Assistant' );
				$settings['welcome_message']    = isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['welcome_message'] ) ) : ( $settings['welcome_message'] ?? '' );
				$settings['system_instruction'] = isset( $_POST['system_instruction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['system_instruction'] ) ) : ( $settings['system_instruction'] ?? '' );
				break;

			case 'integrations':
				// Save Google Drive settings if provided.
				if ( isset( $_POST['gdrive_client_id'] ) || isset( $_POST['gdrive_client_secret'] ) ) {
					$gdrive_settings = AIAGENT_Google_Drive_Integration::get_settings();
					if ( isset( $_POST['gdrive_client_id'] ) ) {
						$gdrive_settings['client_id'] = sanitize_text_field( wp_unslash( $_POST['gdrive_client_id'] ) );
					}
					if ( isset( $_POST['gdrive_client_secret'] ) ) {
						$gdrive_settings['client_secret'] = sanitize_text_field( wp_unslash( $_POST['gdrive_client_secret'] ) );
					}
					AIAGENT_Google_Drive_Integration::update_settings( $gdrive_settings );
				}

				// Save Confluence settings if provided.
				if ( isset( $_POST['confluence_url'] ) || isset( $_POST['confluence_email'] ) || isset( $_POST['confluence_token'] ) ) {
					$confluence_settings = AIAGENT_Confluence_Integration::get_settings();
					if ( isset( $_POST['confluence_url'] ) ) {
						$confluence_settings['instance_url'] = esc_url_raw( wp_unslash( $_POST['confluence_url'] ) );
					}
					if ( isset( $_POST['confluence_email'] ) ) {
						$confluence_settings['email'] = sanitize_email( wp_unslash( $_POST['confluence_email'] ) );
					}
					if ( isset( $_POST['confluence_token'] ) ) {
						$confluence_settings['api_token'] = sanitize_text_field( wp_unslash( $_POST['confluence_token'] ) );
					}
					AIAGENT_Confluence_Integration::update_settings( $confluence_settings );
				}

				// Also save API key from integrations tab if present.
				if ( isset( $_POST['api_key'] ) ) {
					$settings['api_key'] = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
				}

				// Save Zapier settings.
				$integration_settings                       = get_option( 'aiagent_integrations', array() );
				$integration_settings['zapier_enabled']     = ! empty( $_POST['zapier_enabled'] );
				$integration_settings['zapier_webhook_url'] = isset( $_POST['zapier_webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['zapier_webhook_url'] ) ) : '';

				// Save Mailchimp settings.
				$integration_settings['mailchimp_enabled'] = ! empty( $_POST['mailchimp_enabled'] );
				$integration_settings['mailchimp_api_key'] = isset( $_POST['mailchimp_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['mailchimp_api_key'] ) ) : '';
				$integration_settings['mailchimp_list_id'] = isset( $_POST['mailchimp_list_id'] ) ? sanitize_text_field( wp_unslash( $_POST['mailchimp_list_id'] ) ) : '';

				update_option( 'aiagent_integrations', $integration_settings );
				break;

			case 'appearance':
				$settings['ai_name']            = isset( $_POST['ai_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_name'] ) ) : ( $settings['ai_name'] ?? 'AI Assistant' );
				$settings['widget_position']    = isset( $_POST['widget_position'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_position'] ) ) : ( $settings['widget_position'] ?? 'bottom-right' );
				$settings['primary_color']      = isset( $_POST['primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) : ( $settings['primary_color'] ?? '#0073aa' );
				$settings['avatar_url']         = isset( $_POST['avatar_url'] ) ? esc_url_raw( wp_unslash( $_POST['avatar_url'] ) ) : ( $settings['avatar_url'] ?? '' );
				$settings['show_powered_by']    = ! empty( $_POST['show_powered_by'] );
				$settings['powered_by_text']    = isset( $_POST['powered_by_text'] ) ? sanitize_text_field( wp_unslash( $_POST['powered_by_text'] ) ) : ( $settings['powered_by_text'] ?? '' );
				$settings['widget_button_size'] = isset( $_POST['widget_button_size'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_button_size'] ) ) : ( $settings['widget_button_size'] ?? 'medium' );
				$settings['widget_animation']   = isset( $_POST['widget_animation'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_animation'] ) ) : ( $settings['widget_animation'] ?? 'slide' );
				$settings['widget_sound']       = ! empty( $_POST['widget_sound'] );
				break;

			case 'user-info':
				$settings['require_user_info']        = ! empty( $_POST['require_user_info'] );
				$settings['require_phone']            = ! empty( $_POST['require_phone'] );
				$settings['phone_required']           = ! empty( $_POST['phone_required'] );
				$settings['consent_ai_enabled']       = ! empty( $_POST['consent_ai_enabled'] );
				$settings['consent_ai_text']          = isset( $_POST['consent_ai_text'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_ai_text'] ) ) : ( $settings['consent_ai_text'] ?? 'I agree to interact with AI assistance' );
				$settings['consent_newsletter']       = ! empty( $_POST['consent_newsletter'] );
				$settings['consent_newsletter_text']  = isset( $_POST['consent_newsletter_text'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_newsletter_text'] ) ) : ( $settings['consent_newsletter_text'] ?? 'Subscribe to our newsletter' );
				$settings['consent_promotional']      = ! empty( $_POST['consent_promotional'] );
				$settings['consent_promotional_text'] = isset( $_POST['consent_promotional_text'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_promotional_text'] ) ) : ( $settings['consent_promotional_text'] ?? 'Receive promotional updates' );
				break;

			case 'live-agent':
				// Save live agent settings.
				$live_agent_roles = array();
				if ( isset( $_POST['live_agent_roles'] ) && is_array( $_POST['live_agent_roles'] ) ) {
					$live_agent_roles = array_map( 'sanitize_key', wp_unslash( $_POST['live_agent_roles'] ) );
				}

				$live_agent_settings = array(
					'enabled'                    => ! empty( $_POST['live_agent_enabled'] ),
					'allowed_roles'              => $live_agent_roles,
					'show_on_no_results'         => ! empty( $_POST['live_agent_show_on_no_results'] ),
					'show_after_messages'        => isset( $_POST['live_agent_show_after_messages'] ) ? absint( $_POST['live_agent_show_after_messages'] ) : 3,
					'connect_button_text'        => isset( $_POST['live_agent_connect_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['live_agent_connect_button_text'] ) ) : '',
					'waiting_message'            => isset( $_POST['live_agent_waiting_message'] ) ? sanitize_text_field( wp_unslash( $_POST['live_agent_waiting_message'] ) ) : '',
					'connected_message'          => isset( $_POST['live_agent_connected_message'] ) ? sanitize_text_field( wp_unslash( $_POST['live_agent_connected_message'] ) ) : '',
					'offline_message'            => isset( $_POST['live_agent_offline_message'] ) ? sanitize_text_field( wp_unslash( $_POST['live_agent_offline_message'] ) ) : '',
					'agent_typing_indicator'     => true,
					'enable_sound_notifications' => true,
					'auto_assign'                => ! empty( $_POST['live_agent_auto_assign'] ),
					'max_concurrent_chats'       => isset( $_POST['live_agent_max_concurrent_chats'] ) ? absint( $_POST['live_agent_max_concurrent_chats'] ) : 5,
					'queue_enabled'              => ! empty( $_POST['live_agent_queue_enabled'] ),
					'queue_message'              => isset( $_POST['live_agent_queue_message'] ) ? sanitize_text_field( wp_unslash( $_POST['live_agent_queue_message'] ) ) : '',
					'transfer_conversation'      => ! empty( $_POST['live_agent_transfer_conversation'] ),
					'show_agent_name'            => ! empty( $_POST['live_agent_show_agent_name'] ),
					'show_agent_avatar'          => ! empty( $_POST['live_agent_show_agent_avatar'] ),
				);
				AIAGENT_Live_Agent_Manager::update_settings( $live_agent_settings );
				break;

			case 'notifications':
				// Save notification settings.
				$notification_settings = array(
					'enabled'                    => ! empty( $_POST['notifications_enabled'] ),
					'email_notifications'        => ! empty( $_POST['email_notifications'] ),
					'email_recipients'           => isset( $_POST['email_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['email_recipients'] ) ) : get_option( 'admin_email' ),
					'notify_new_conversation'    => ! empty( $_POST['notify_new_conversation'] ),
					'notify_lead_validated'      => ! empty( $_POST['notify_lead_validated'] ),
					'notify_lead_converted'      => ! empty( $_POST['notify_lead_converted'] ),
					'notify_conversation_closed' => ! empty( $_POST['notify_conversation_closed'] ),
					'auto_validate_leads'        => ! empty( $_POST['auto_validate_leads'] ),
					'validation_prompt'          => isset( $_POST['validation_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['validation_prompt'] ) ) : '',
				);
				AIAGENT_Notification_Manager::update_settings( $notification_settings );

				// Save log settings.
				$log_settings = array(
					'enabled'             => ! empty( $_POST['logs_enabled'] ),
					'log_conversations'   => ! empty( $_POST['log_conversations'] ),
					'log_leads'           => ! empty( $_POST['log_leads'] ),
					'log_notifications'   => ! empty( $_POST['log_notifications'] ),
					'log_ai_validations'  => ! empty( $_POST['log_ai_validations'] ),
					'log_integrations'    => ! empty( $_POST['log_integrations'] ),
					'log_user_actions'    => ! empty( $_POST['log_user_actions'] ),
					'log_system_events'   => ! empty( $_POST['log_system_events'] ),
					'retention_days'      => isset( $_POST['retention_days'] ) ? absint( $_POST['retention_days'] ) : 90,
					'export_to_zapier'    => ! empty( $_POST['export_to_zapier'] ),
					'zapier_log_webhook'  => isset( $_POST['zapier_log_webhook'] ) ? esc_url_raw( wp_unslash( $_POST['zapier_log_webhook'] ) ) : '',
					'export_to_mailchimp' => ! empty( $_POST['export_to_mailchimp'] ),
				);
				AIAGENT_Activity_Log_Manager::update_settings( $log_settings );
				break;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		AI_Agent_For_Website::update_settings( $settings );

		add_settings_error( 'aiagent_messages', 'aiagent_message', __( 'Settings saved.', 'ai-agent-for-website' ), 'updated' );
		settings_errors( 'aiagent_messages' );
	}

	/**
	 * Handle Google Drive OAuth callback.
	 */
	private function handle_gdrive_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state verified in integration class.
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $error ) {
			add_settings_error(
				'aiagent_messages',
				'gdrive_error',
				/* translators: %s: Error message */
				sprintf( __( 'Google Drive authorization failed: %s', 'ai-agent-for-website' ), $error ),
				'error'
			);
			return;
		}

		$gdrive = new AIAGENT_Google_Drive_Integration();
		$result = $gdrive->handle_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'aiagent_messages',
				'gdrive_error',
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'aiagent_messages',
				'gdrive_success',
				__( 'Successfully connected to Google Drive!', 'ai-agent-for-website' ),
				'updated'
			);
		}
	}

	/**
	 * Handle Google Calendar OAuth callback.
	 */
	private function handle_gcalendar_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state verified in integration class.
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $error ) {
			add_settings_error(
				'aiagent_messages',
				'gcalendar_error',
				/* translators: %s: Error message */
				sprintf( __( 'Google Calendar authorization failed: %s', 'ai-agent-for-website' ), $error ),
				'error'
			);
			return;
		}

		$gcalendar = new AIAGENT_Google_Calendar_Integration();
		$result    = $gcalendar->handle_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'aiagent_messages',
				'gcalendar_error',
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'aiagent_messages',
				'gcalendar_success',
				__( 'Successfully connected to Google Calendar!', 'ai-agent-for-website' ),
				'updated'
			);
		}
	}

	/**
	 * Handle Calendly OAuth callback.
	 */
	private function handle_calendly_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state verified in integration class.
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $error ) {
			add_settings_error(
				'aiagent_messages',
				'calendly_error',
				/* translators: %s: Error message */
				sprintf( __( 'Calendly authorization failed: %s', 'ai-agent-for-website' ), $error ),
				'error'
			);
			return;
		}

		$calendly = new AIAGENT_Calendly_Integration();
		$result   = $calendly->handle_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'aiagent_messages',
				'calendly_error',
				$result->get_error_message(),
				'error'
			);
		} else {
			// Auto-populate scheduling URL from user info.
			if ( ! empty( $result['user_info']['scheduling_url'] ) ) {
				$settings                   = AIAGENT_Calendly_Integration::get_settings();
				$settings['scheduling_url'] = $result['user_info']['scheduling_url'];
				AIAGENT_Calendly_Integration::update_settings( $settings );
			}

			add_settings_error(
				'aiagent_messages',
				'calendly_success',
				__( 'Successfully connected to Calendly!', 'ai-agent-for-website' ),
				'updated'
			);
		}
	}
}
