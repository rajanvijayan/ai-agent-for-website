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
		$tabs = [
			'general'       => [
				'label' => __( 'General', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-settings',
			],
			'integrations'  => [
				'label' => __( 'Integrations', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-plugins',
			],
			'appearance'    => [
				'label' => __( 'Appearance', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-appearance',
			],
			'user-info'     => [
				'label' => __( 'User Information', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-admin-users',
			],
			'notifications' => [
				'label' => __( 'Notifications & Logs', 'ai-agent-for-website' ),
				'icon'  => 'dashicons-bell',
			],
		];

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
		?>
		<div class="aiagent-card">
			<h2>
				<span class="dashicons dashicons-cloud" style="color: #0073aa;"></span>
				<?php esc_html_e( 'AI Provider', 'ai-agent-for-website' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure your AI provider API key to power the chat functionality.', 'ai-agent-for-website' ); ?>
			</p>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="api_key"><?php esc_html_e( 'Groq API Key', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<div class="aiagent-api-key-wrapper">
							<input type="password" 
									id="api_key" 
									name="api_key" 
									value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>" 
									class="regular-text"
									autocomplete="off">
							<button type="button" class="button aiagent-toggle-password">
								<span class="dashicons dashicons-visibility"></span>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Get your free API key from', 'ai-agent-for-website' ); ?>
							<a href="https://console.groq.com" target="_blank">console.groq.com</a>
						</p>
						<div class="aiagent-api-test" style="margin-top: 10px;">
							<button type="button" id="aiagent-test-api" class="button">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Test Connection', 'ai-agent-for-website' ); ?>
							</button>
							<span id="aiagent-test-result" class="aiagent-test-result"></span>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<?php
		$gdrive_settings  = AIAGENT_Google_Drive_Integration::get_settings();
		$gdrive_connected = AIAGENT_Google_Drive_Integration::is_connected();
		$gdrive_user      = AIAGENT_Google_Drive_Integration::get_connected_user();
		?>
		<div class="aiagent-card aiagent-integration-card">
			<h2>
				<span class="dashicons dashicons-google" style="color: #4285f4;"></span>
				<?php esc_html_e( 'Google Drive', 'ai-agent-for-website' ); ?>
				<?php if ( $gdrive_connected ) : ?>
					<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Connect your Google Drive to import documents directly into your knowledge base.', 'ai-agent-for-website' ); ?>
			</p>

			<?php if ( $gdrive_connected && $gdrive_user ) : ?>
				<div class="aiagent-integration-status aiagent-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					/* translators: %s: User email */
					printf( esc_html__( 'Connected as %s', 'ai-agent-for-website' ), esc_html( $gdrive_user['email'] ) );
					?>
					<button type="button" class="button button-small button-link-delete aiagent-gdrive-disconnect" style="margin-left: 10px;">
						<?php esc_html_e( 'Disconnect', 'ai-agent-for-website' ); ?>
					</button>
				</div>
				<div style="margin-top: 15px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-cloud-upload" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Import from Google Drive', 'ai-agent-for-website' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="aiagent-integration-setup">
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'To connect Google Drive, you need to create OAuth credentials in the Google Cloud Console.', 'ai-agent-for-website' ); ?>
						<a href="https://console.cloud.google.com/apis/credentials" target="_blank"><?php esc_html_e( 'Learn how →', 'ai-agent-for-website' ); ?></a>
					</p>
					<table class="form-table aiagent-compact-table">
						<tr>
							<th scope="row">
								<label for="gdrive_client_id"><?php esc_html_e( 'Client ID', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<input type="text" 
										id="gdrive_client_id" 
										name="gdrive_client_id" 
										value="<?php echo esc_attr( $gdrive_settings['client_id'] ?? '' ); ?>" 
										class="regular-text"
										placeholder="xxxxxx.apps.googleusercontent.com">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="gdrive_client_secret"><?php esc_html_e( 'Client Secret', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<div class="aiagent-api-key-wrapper">
									<input type="password" 
											id="gdrive_client_secret" 
											name="gdrive_client_secret" 
											value="<?php echo esc_attr( $gdrive_settings['client_secret'] ?? '' ); ?>" 
											class="regular-text"
											autocomplete="off">
									<button type="button" class="button aiagent-toggle-password">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Redirect URI', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<code class="aiagent-copy-text" id="gdrive-redirect-uri"><?php echo esc_html( admin_url( 'admin.php?page=ai-agent-settings&tab=integrations&gdrive_callback=1' ) ); ?></code>
								<button type="button" class="button button-small aiagent-copy-btn" data-target="gdrive-redirect-uri">
									<span class="dashicons dashicons-clipboard"></span>
								</button>
								<p class="description"><?php esc_html_e( 'Add this URL to your OAuth consent screen authorized redirect URIs.', 'ai-agent-for-website' ); ?></p>
							</td>
						</tr>
					</table>
					<div class="aiagent-integration-actions">
						<button type="button" id="aiagent-gdrive-connect" class="button button-primary" <?php disabled( empty( $gdrive_settings['client_id'] ) || empty( $gdrive_settings['client_secret'] ) ); ?>>
							<span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Connect Google Drive', 'ai-agent-for-website' ); ?>
						</button>
						<span class="aiagent-connection-status"></span>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php
		$confluence_settings  = AIAGENT_Confluence_Integration::get_settings();
		$confluence_connected = AIAGENT_Confluence_Integration::is_connected();
		?>
		<div class="aiagent-card aiagent-integration-card">
			<h2>
				<span class="aiagent-integration-icon aiagent-icon-atlassian"></span>
				<?php esc_html_e( 'Confluence', 'ai-agent-for-website' ); ?>
				<?php if ( $confluence_connected ) : ?>
					<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Connect Confluence to import wiki pages and documentation into your knowledge base.', 'ai-agent-for-website' ); ?>
			</p>

			<?php if ( $confluence_connected ) : ?>
				<div class="aiagent-integration-status aiagent-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					/* translators: %s: Instance URL */
					printf( esc_html__( 'Connected to %s', 'ai-agent-for-website' ), esc_html( $confluence_settings['instance_url'] ) );
					?>
					<button type="button" class="button button-small button-link-delete aiagent-confluence-disconnect" style="margin-left: 10px;">
						<?php esc_html_e( 'Disconnect', 'ai-agent-for-website' ); ?>
					</button>
				</div>
				<div style="margin-top: 15px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-cloud-upload" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Import from Confluence', 'ai-agent-for-website' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="aiagent-integration-setup">
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Enter your Confluence Cloud or Server credentials. For Cloud, use an API token from', 'ai-agent-for-website' ); ?>
						<a href="https://id.atlassian.com/manage-profile/security/api-tokens" target="_blank"><?php esc_html_e( 'Atlassian API tokens', 'ai-agent-for-website' ); ?></a>
					</p>
					<table class="form-table aiagent-compact-table">
						<tr>
							<th scope="row">
								<label for="confluence_url"><?php esc_html_e( 'Instance URL', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<input type="url" 
										id="confluence_url" 
										name="confluence_url" 
										value="<?php echo esc_attr( $confluence_settings['instance_url'] ?? '' ); ?>" 
										class="regular-text"
										placeholder="https://your-domain.atlassian.net">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="confluence_email"><?php esc_html_e( 'Email', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<input type="email" 
										id="confluence_email" 
										name="confluence_email" 
										value="<?php echo esc_attr( $confluence_settings['email'] ?? '' ); ?>" 
										class="regular-text"
										placeholder="you@example.com">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="confluence_token"><?php esc_html_e( 'API Token', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<div class="aiagent-api-key-wrapper">
									<input type="password" 
											id="confluence_token" 
											name="confluence_token" 
											value="<?php echo esc_attr( $confluence_settings['api_token'] ?? '' ); ?>" 
											class="regular-text"
											autocomplete="off">
									<button type="button" class="button aiagent-toggle-password">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
							</td>
						</tr>
					</table>
					<div class="aiagent-integration-actions">
						<button type="button" id="aiagent-confluence-connect" class="button button-primary">
							<span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Connect Confluence', 'ai-agent-for-website' ); ?>
						</button>
						<span class="aiagent-confluence-status"></span>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="aiagent-card aiagent-integration-card">
			<h2>
				<span class="dashicons dashicons-upload" style="color: #9b59b6;"></span>
				<?php esc_html_e( 'File Upload', 'ai-agent-for-website' ); ?>
				<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Available', 'ai-agent-for-website' ); ?></span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Upload documents (PDF, DOC, DOCX, TXT, CSV, MD, RTF) directly to your knowledge base.', 'ai-agent-for-website' ); ?>
			</p>
			<div class="aiagent-integration-status aiagent-status-connected">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'This feature is available. Go to Knowledge Base to upload files.', 'ai-agent-for-website' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-agent-knowledge' ) ); ?>" class="button button-small" style="margin-left: 10px;">
					<?php esc_html_e( 'Upload Files', 'ai-agent-for-website' ); ?>
				</a>
			</div>
		</div>

		<?php
		$integration_settings = get_option( 'aiagent_integrations', [] );
		$zapier_enabled       = ! empty( $integration_settings['zapier_enabled'] );
		$zapier_url           = $integration_settings['zapier_webhook_url'] ?? '';
		?>
		<div class="aiagent-card aiagent-integration-card">
			<h2>
				<span class="dashicons dashicons-randomize" style="color: #ff4a00;"></span>
				<?php esc_html_e( 'Zapier', 'ai-agent-for-website' ); ?>
				<?php if ( $zapier_enabled && $zapier_url ) : ?>
					<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Connect with Zapier to sync leads with any CRM platform like Salesforce, HubSpot, Pipedrive, and more.', 'ai-agent-for-website' ); ?>
			</p>

			<table class="form-table aiagent-compact-table">
				<tr>
					<th scope="row">
						<label for="zapier_enabled"><?php esc_html_e( 'Enable Zapier', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="zapier_enabled" 
									name="zapier_enabled" 
									value="1" 
									<?php checked( $zapier_enabled ); ?>>
							<span class="slider"></span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="zapier_webhook_url"><?php esc_html_e( 'Webhook URL', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="url" 
								id="zapier_webhook_url" 
								name="zapier_webhook_url" 
								value="<?php echo esc_attr( $zapier_url ); ?>" 
								class="regular-text"
								placeholder="https://hooks.zapier.com/hooks/catch/...">
						<p class="description">
							<?php esc_html_e( 'Create a Zap with "Webhooks by Zapier" trigger and paste the URL here.', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php if ( $zapier_enabled && $zapier_url ) : ?>
				<div class="aiagent-integration-status aiagent-status-connected" style="margin-top: 15px;">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Zapier webhook is configured. New leads will be synced automatically.', 'ai-agent-for-website' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<?php
		$mailchimp_settings  = AIAGENT_Mailchimp_Integration::get_settings();
		$mailchimp_enabled   = ! empty( $mailchimp_settings['mailchimp_enabled'] );
		$mailchimp_connected = AIAGENT_Mailchimp_Integration::is_enabled();
		?>
		<div class="aiagent-card aiagent-integration-card">
			<h2>
				<span class="dashicons dashicons-email-alt" style="color: #FFE01B;"></span>
				<?php esc_html_e( 'Mailchimp', 'ai-agent-for-website' ); ?>
				<?php if ( $mailchimp_connected ) : ?>
					<span class="aiagent-badge aiagent-badge-connected"><?php esc_html_e( 'Connected', 'ai-agent-for-website' ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Connect Mailchimp to automatically subscribe users who opt-in for newsletters.', 'ai-agent-for-website' ); ?>
			</p>

			<table class="form-table aiagent-compact-table">
				<tr>
					<th scope="row">
						<label for="mailchimp_enabled"><?php esc_html_e( 'Enable Mailchimp', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<label class="aiagent-switch">
							<input type="checkbox" 
									id="mailchimp_enabled" 
									name="mailchimp_enabled" 
									value="1" 
									<?php checked( $mailchimp_enabled ); ?>>
							<span class="slider"></span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mailchimp_api_key"><?php esc_html_e( 'API Key', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<div class="aiagent-api-key-wrapper">
							<input type="password" 
									id="mailchimp_api_key" 
									name="mailchimp_api_key" 
									value="<?php echo esc_attr( $mailchimp_settings['mailchimp_api_key'] ?? '' ); ?>" 
									class="regular-text"
									autocomplete="off"
									placeholder="xxxxxxxx-us1">
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
					<th scope="row">
						<label for="mailchimp_list_id"><?php esc_html_e( 'Audience/List ID', 'ai-agent-for-website' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="mailchimp_list_id" 
								name="mailchimp_list_id" 
								value="<?php echo esc_attr( $mailchimp_settings['mailchimp_list_id'] ?? '' ); ?>" 
								class="regular-text"
								placeholder="e.g., abc123def4">
						<p class="description">
							<?php esc_html_e( 'Find your Audience ID in Mailchimp under Audience → Settings → Audience name and defaults.', 'ai-agent-for-website' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php if ( $mailchimp_connected ) : ?>
				<div class="aiagent-integration-status aiagent-status-connected" style="margin-top: 15px;">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Mailchimp is configured. Users who opt-in will be subscribed automatically.', 'ai-agent-for-website' ); ?>
				</div>
			<?php endif; ?>
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
				$integration_settings                       = get_option( 'aiagent_integrations', [] );
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

			case 'notifications':
				// Save notification settings.
				$notification_settings = [
					'enabled'                    => ! empty( $_POST['notifications_enabled'] ),
					'email_notifications'        => ! empty( $_POST['email_notifications'] ),
					'email_recipients'           => isset( $_POST['email_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['email_recipients'] ) ) : get_option( 'admin_email' ),
					'notify_new_conversation'    => ! empty( $_POST['notify_new_conversation'] ),
					'notify_lead_validated'      => ! empty( $_POST['notify_lead_validated'] ),
					'notify_lead_converted'      => ! empty( $_POST['notify_lead_converted'] ),
					'notify_conversation_closed' => ! empty( $_POST['notify_conversation_closed'] ),
					'auto_validate_leads'        => ! empty( $_POST['auto_validate_leads'] ),
					'validation_prompt'          => isset( $_POST['validation_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['validation_prompt'] ) ) : '',
				];
				AIAGENT_Notification_Manager::update_settings( $notification_settings );

				// Save log settings.
				$log_settings = [
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
				];
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
}
