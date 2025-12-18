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
	 * Render the settings page.
	 */
	public function render() {
		// Handle form submission.
		if ( isset( $_POST['aiagent_save_settings'] ) && check_admin_referer( 'aiagent_settings_nonce' ) ) {
			$this->save_settings();
		}

		$settings = AI_Agent_For_Website::get_settings();
		?>
		<div class="wrap aiagent-admin">
			<h1><?php esc_html_e( 'AI Agent Settings', 'ai-agent-for-website' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'aiagent_settings_nonce' ); ?>

				<div class="aiagent-card">
					<h2><?php esc_html_e( 'API Configuration', 'ai-agent-for-website' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="api_key"><?php esc_html_e( 'Groq API Key', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<input type="password" 
										id="api_key" 
										name="api_key" 
										value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>" 
										class="regular-text"
										autocomplete="off">
								<p class="description">
									<?php esc_html_e( 'Get your free API key from', 'ai-agent-for-website' ); ?>
									<a href="https://console.groq.com" target="_blank">console.groq.com</a>
								</p>
							</td>
						</tr>
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
								<label for="ai_name"><?php esc_html_e( 'AI Name', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<input type="text" 
										id="ai_name" 
										name="ai_name" 
										value="<?php echo esc_attr( $settings['ai_name'] ?? 'AI Assistant' ); ?>" 
										class="regular-text">
								<p class="description">
									<?php esc_html_e( 'The name shown in the chat widget', 'ai-agent-for-website' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="welcome_message"><?php esc_html_e( 'Welcome Message', 'ai-agent-for-website' ); ?></label>
							</th>
							<td>
								<textarea id="welcome_message" 
											name="welcome_message" 
											rows="2" 
											class="large-text"><?php echo esc_textarea( $settings['welcome_message'] ?? 'Hello! How can I help you today?' ); ?></textarea>
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
								<p class="description">
									<?php esc_html_e( 'Instructions that define how the AI should behave and respond', 'ai-agent-for-website' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="aiagent-card">
					<h2><?php esc_html_e( 'Widget Appearance', 'ai-agent-for-website' ); ?></h2>
					
					<table class="form-table">
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
								<p class="description">
									<?php esc_html_e( 'Upload an avatar image for the chat assistant (recommended: 80x80px)', 'ai-agent-for-website' ); ?>
								</p>
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
					</table>
				</div>

				<div class="aiagent-card">
					<h2><?php esc_html_e( 'User Information', 'ai-agent-for-website' ); ?></h2>
					
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
					</table>
				</div>

				<div class="aiagent-card">
					<h2><?php esc_html_e( 'Shortcode Usage', 'ai-agent-for-website' ); ?></h2>
					<p><?php esc_html_e( 'You can also embed the chat widget directly in any page or post using this shortcode:', 'ai-agent-for-website' ); ?></p>
					<code>[ai_agent_chat]</code>
					<p class="description" style="margin-top: 10px;">
						<?php esc_html_e( 'Optional attributes: height="500px" width="100%"', 'ai-agent-for-website' ); ?>
					</p>
				</div>

				<p class="submit">
					<input type="submit" 
							name="aiagent_save_settings" 
							class="button button-primary" 
							value="<?php esc_attr_e( 'Save Settings', 'ai-agent-for-website' ); ?>">
				</p>
			</form>
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
		$settings['api_key']            = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$settings['enabled']            = ! empty( $_POST['enabled'] );
		$settings['ai_name']            = isset( $_POST['ai_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_name'] ) ) : 'AI Assistant';
		$settings['welcome_message']    = isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['welcome_message'] ) ) : '';
		$settings['system_instruction'] = isset( $_POST['system_instruction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['system_instruction'] ) ) : '';
		$settings['widget_position']    = isset( $_POST['widget_position'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_position'] ) ) : 'bottom-right';
		$settings['primary_color']      = isset( $_POST['primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) : '#0073aa';
		$settings['avatar_url']         = isset( $_POST['avatar_url'] ) ? esc_url_raw( wp_unslash( $_POST['avatar_url'] ) ) : '';
		$settings['require_user_info']  = ! empty( $_POST['require_user_info'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		AI_Agent_For_Website::update_settings( $settings );

		add_settings_error( 'aiagent_messages', 'aiagent_message', __( 'Settings saved.', 'ai-agent-for-website' ), 'updated' );
		settings_errors( 'aiagent_messages' );
	}
}
