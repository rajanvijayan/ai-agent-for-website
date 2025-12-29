<?php
/**
 * Chat Widget Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Chat_Widget
 *
 * Handles rendering of the chat widget on the frontend.
 */
class AIAGENT_Chat_Widget {

	/**
	 * Render floating chat widget (added to footer).
	 *
	 * @return string HTML output.
	 */
	public function render_floating() {
		$settings = AI_Agent_For_Website::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return '';
		}

		$ai_name           = esc_attr( $settings['ai_name'] ?? 'AI Assistant' );
		$position          = esc_attr( $settings['widget_position'] ?? 'bottom-right' );
		$color             = esc_attr( $settings['primary_color'] ?? '#0073aa' );
		$avatar_url        = esc_url( $settings['avatar_url'] ?? '' );
		$require_user_info = ! empty( $settings['require_user_info'] );
		$require_phone     = ! empty( $settings['require_phone'] );

		ob_start();
		?>
		<div id="aiagent-chat-widget" 
			class="aiagent-widget aiagent-position-<?php echo esc_attr( $position ); ?>"
			style="--aiagent-primary: <?php echo esc_attr( $color ); ?>;">
			
			<!-- Toggle Button -->
			<button class="aiagent-toggle" aria-label="<?php esc_attr_e( 'Open chat', 'ai-agent-for-website' ); ?>">
				<!-- Lucide: message-circle -->
				<svg class="aiagent-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
				</svg>
				<!-- Lucide: x -->
				<svg class="aiagent-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>

			<!-- Chat Window -->
			<div class="aiagent-window">
				<div class="aiagent-header">
					<div class="aiagent-header-info">
						<div class="aiagent-avatar">
							<?php if ( $avatar_url ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $ai_name ); ?>">
							<?php else : ?>
								<svg viewBox="0 0 24 24" fill="currentColor">
									<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
								</svg>
							<?php endif; ?>
						</div>
						<div>
							<div class="aiagent-name"><?php echo esc_html( $ai_name ); ?></div>
							<div class="aiagent-status"><?php esc_html_e( 'Online now', 'ai-agent-for-website' ); ?></div>
						</div>
					</div>
					<div class="aiagent-header-actions">
						<button class="aiagent-new-chat" title="<?php esc_attr_e( 'New conversation', 'ai-agent-for-website' ); ?>">
							<!-- Lucide: plus -->
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="12" y1="5" x2="12" y2="19"></line>
								<line x1="5" y1="12" x2="19" y2="12"></line>
							</svg>
						</button>
						<button class="aiagent-close-chat" title="<?php esc_attr_e( 'End conversation', 'ai-agent-for-website' ); ?>">
							<!-- Lucide: x -->
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
					</div>
				</div>

				<!-- User Info Form -->
				<div class="aiagent-user-form">
					<div class="aiagent-user-form-inner">
						<h3><?php esc_html_e( 'Start a conversation', 'ai-agent-for-website' ); ?></h3>
						<p><?php esc_html_e( 'Please introduce yourself so we can assist you better.', 'ai-agent-for-website' ); ?></p>
						<form class="aiagent-user-info-form">
							<div class="aiagent-form-group">
								<label for="aiagent-user-name"><?php esc_html_e( 'Your Name', 'ai-agent-for-website' ); ?></label>
								<input type="text" 
										id="aiagent-user-name" 
										name="user_name" 
										required 
										placeholder="<?php esc_attr_e( 'Enter your name', 'ai-agent-for-website' ); ?>">
							</div>
							<div class="aiagent-form-group">
								<label for="aiagent-user-email"><?php esc_html_e( 'Email Address', 'ai-agent-for-website' ); ?></label>
								<input type="email" 
										id="aiagent-user-email" 
										name="user_email" 
										required 
										placeholder="<?php esc_attr_e( 'Enter your email', 'ai-agent-for-website' ); ?>">
							</div>
						<?php if ( ! empty( $settings['require_phone'] ) ) : ?>
						<div class="aiagent-form-group">
							<label for="aiagent-user-phone"><?php esc_html_e( 'Phone Number', 'ai-agent-for-website' ); ?></label>
							<input type="tel" 
									id="aiagent-user-phone" 
									name="user_phone" 
									<?php echo ! empty( $settings['phone_required'] ) ? 'required' : ''; ?>
									placeholder="<?php esc_attr_e( 'Enter your phone number', 'ai-agent-for-website' ); ?>">
						</div>
						<?php endif; ?>

						<!-- Consent Checkboxes -->
						<div class="aiagent-consent-section">
							<?php if ( ! empty( $settings['consent_ai_enabled'] ) ) : ?>
							<div class="aiagent-consent-item">
								<label class="aiagent-checkbox-label">
									<input type="checkbox" 
											name="consent_ai" 
											required>
									<span><?php echo esc_html( $settings['consent_ai_text'] ?? 'I agree to interact with AI assistance' ); ?> <span class="required">*</span></span>
								</label>
							</div>
							<?php endif; ?>

							<?php if ( ! empty( $settings['consent_newsletter'] ) ) : ?>
							<div class="aiagent-consent-item">
								<label class="aiagent-checkbox-label">
									<input type="checkbox" 
											name="consent_newsletter">
									<span><?php echo esc_html( $settings['consent_newsletter_text'] ?? 'Subscribe to our newsletter' ); ?></span>
								</label>
							</div>
							<?php endif; ?>

							<?php if ( ! empty( $settings['consent_promotional'] ) ) : ?>
							<div class="aiagent-consent-item">
								<label class="aiagent-checkbox-label">
									<input type="checkbox" 
											name="consent_promotional">
									<span><?php echo esc_html( $settings['consent_promotional_text'] ?? 'Receive promotional updates' ); ?></span>
								</label>
							</div>
							<?php endif; ?>
						</div>

						<button type="submit" class="aiagent-start-chat-btn">
								<?php esc_html_e( 'Start Chat', 'ai-agent-for-website' ); ?>
								<!-- Lucide: arrow-right -->
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
									<line x1="5" y1="12" x2="19" y2="12"></line>
									<polyline points="12 5 19 12 12 19"></polyline>
								</svg>
							</button>
						</form>
					</div>
				</div>

				<!-- Rating Modal -->
				<div class="aiagent-rating-modal">
					<div class="aiagent-rating-inner">
						<h3><?php esc_html_e( 'How was your experience?', 'ai-agent-for-website' ); ?></h3>
						<p><?php esc_html_e( 'Please rate your conversation', 'ai-agent-for-website' ); ?></p>
						<div class="aiagent-stars">
							<button type="button" class="aiagent-star" data-rating="1">★</button>
							<button type="button" class="aiagent-star" data-rating="2">★</button>
							<button type="button" class="aiagent-star" data-rating="3">★</button>
							<button type="button" class="aiagent-star" data-rating="4">★</button>
							<button type="button" class="aiagent-star" data-rating="5">★</button>
						</div>
						<div class="aiagent-rating-actions">
							<button type="button" class="aiagent-skip-rating"><?php esc_html_e( 'Skip', 'ai-agent-for-website' ); ?></button>
						</div>
					</div>
				</div>

				<!-- Calendar Booking Modal -->
				<div class="aiagent-calendar-modal">
					<div class="aiagent-calendar-inner">
						<!-- Step 1: Prompt -->
						<div class="aiagent-calendar-step" data-step="prompt">
							<div class="aiagent-calendar-icon">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48">
									<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
									<line x1="16" y1="2" x2="16" y2="6"></line>
									<line x1="8" y1="2" x2="8" y2="6"></line>
									<line x1="3" y1="10" x2="21" y2="10"></line>
								</svg>
							</div>
							<h3><?php esc_html_e( 'Schedule a Meeting?', 'ai-agent-for-website' ); ?></h3>
							<p class="aiagent-calendar-prompt-text"></p>
							<div class="aiagent-calendar-prompt-actions">
								<button type="button" class="aiagent-btn aiagent-btn-primary aiagent-calendar-yes">
									<?php esc_html_e( 'Yes, book a time', 'ai-agent-for-website' ); ?>
								</button>
								<button type="button" class="aiagent-btn aiagent-btn-secondary aiagent-calendar-no">
									<?php esc_html_e( 'No, thanks', 'ai-agent-for-website' ); ?>
								</button>
							</div>
						</div>

						<!-- Step 2: Select Date & Time -->
						<div class="aiagent-calendar-step" data-step="slots" style="display: none;">
							<div class="aiagent-calendar-header">
								<button type="button" class="aiagent-calendar-back">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
										<polyline points="15 18 9 12 15 6"></polyline>
									</svg>
								</button>
								<h3><?php esc_html_e( 'Select a Time', 'ai-agent-for-website' ); ?></h3>
							</div>
							<div class="aiagent-calendar-loading">
								<div class="aiagent-spinner"></div>
								<p><?php esc_html_e( 'Loading available times...', 'ai-agent-for-website' ); ?></p>
							</div>
							<div class="aiagent-calendar-slots-container">
								<div class="aiagent-calendar-date-nav">
									<button type="button" class="aiagent-date-prev" disabled>
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
											<polyline points="15 18 9 12 15 6"></polyline>
										</svg>
									</button>
									<span class="aiagent-date-label"></span>
									<button type="button" class="aiagent-date-next">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
											<polyline points="9 18 15 12 9 6"></polyline>
										</svg>
									</button>
								</div>
								<div class="aiagent-slots-grid">
									<!-- Slots will be inserted here -->
								</div>
								<div class="aiagent-no-slots" style="display: none;">
									<p><?php esc_html_e( 'No available times on this day.', 'ai-agent-for-website' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Step 3: Event Details -->
						<div class="aiagent-calendar-step" data-step="details" style="display: none;">
							<div class="aiagent-calendar-header">
								<button type="button" class="aiagent-calendar-back">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
										<polyline points="15 18 9 12 15 6"></polyline>
									</svg>
								</button>
								<h3><?php esc_html_e( 'Meeting Details', 'ai-agent-for-website' ); ?></h3>
							</div>
							<div class="aiagent-selected-time">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
									<circle cx="12" cy="12" r="10"></circle>
									<polyline points="12 6 12 12 16 14"></polyline>
								</svg>
								<span class="aiagent-selected-time-text"></span>
							</div>
							<form class="aiagent-calendar-form">
								<div class="aiagent-form-group">
									<label for="aiagent-event-title"><?php esc_html_e( 'Meeting Topic', 'ai-agent-for-website' ); ?></label>
									<input type="text" id="aiagent-event-title" name="event_title" 
										placeholder="<?php esc_attr_e( 'e.g., Follow-up discussion', 'ai-agent-for-website' ); ?>" required>
								</div>
								<div class="aiagent-form-group">
									<label for="aiagent-event-description"><?php esc_html_e( 'Notes (optional)', 'ai-agent-for-website' ); ?></label>
									<textarea id="aiagent-event-description" name="event_description" rows="2" 
										placeholder="<?php esc_attr_e( 'Any additional details...', 'ai-agent-for-website' ); ?>"></textarea>
								</div>
								<button type="submit" class="aiagent-btn aiagent-btn-primary aiagent-btn-full">
									<?php esc_html_e( 'Confirm Booking', 'ai-agent-for-website' ); ?>
								</button>
							</form>
						</div>

						<!-- Step 4: Confirmation -->
						<div class="aiagent-calendar-step" data-step="confirmation" style="display: none;">
							<div class="aiagent-calendar-success-icon">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48">
									<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
									<polyline points="22 4 12 14.01 9 11.01"></polyline>
								</svg>
							</div>
							<h3><?php esc_html_e( 'Booking Confirmed!', 'ai-agent-for-website' ); ?></h3>
							<div class="aiagent-booking-details">
								<p class="aiagent-booking-title"></p>
								<p class="aiagent-booking-time"></p>
							</div>
							<p class="aiagent-booking-invite-sent">
								<?php esc_html_e( 'A calendar invite has been sent to your email.', 'ai-agent-for-website' ); ?>
							</p>
							<button type="button" class="aiagent-btn aiagent-btn-secondary aiagent-calendar-done">
								<?php esc_html_e( 'Done', 'ai-agent-for-website' ); ?>
							</button>
						</div>
					</div>
				</div>

				<div class="aiagent-messages">
					<!-- Messages will be inserted here -->
				</div>

				<div class="aiagent-input-area">
					<form class="aiagent-form">
						<input type="text" 
								class="aiagent-input" 
								placeholder="<?php esc_attr_e( 'Type your message...', 'ai-agent-for-website' ); ?>"
								autocomplete="off">
						<button type="submit" class="aiagent-send">
							<!-- Lucide: send -->
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="22" y1="2" x2="11" y2="13"></line>
								<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
							</svg>
						</button>
					</form>
				</div>

				<?php if ( $settings['show_powered_by'] ?? true ) : ?>
				<div class="aiagent-powered">
					<?php
					$powered_by_text = $settings['powered_by_text'] ?? '';
					echo esc_html( ! empty( $powered_by_text ) ? $powered_by_text : get_bloginfo( 'name' ) );
					?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render inline chat (for shortcode).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_inline( $atts ) {
		$settings = AI_Agent_For_Website::get_settings();

		$ai_name       = esc_attr( $settings['ai_name'] ?? 'AI Assistant' );
		$color         = esc_attr( $settings['primary_color'] ?? '#0073aa' );
		$avatar_url    = esc_url( $settings['avatar_url'] ?? '' );
		$height        = esc_attr( $atts['height'] ?? '500px' );
		$width         = esc_attr( $atts['width'] ?? '100%' );
		$require_phone = ! empty( $settings['require_phone'] );

		ob_start();
		?>
		<div class="aiagent-inline-chat" 
			style="--aiagent-primary: <?php echo esc_attr( $color ); ?>; height: <?php echo esc_attr( $height ); ?>; width: <?php echo esc_attr( $width ); ?>;">
			
			<div class="aiagent-header">
				<div class="aiagent-header-info">
					<div class="aiagent-avatar">
						<?php if ( $avatar_url ) : ?>
							<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $ai_name ); ?>">
						<?php else : ?>
							<svg viewBox="0 0 24 24" fill="currentColor">
								<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
							</svg>
						<?php endif; ?>
					</div>
					<div>
						<div class="aiagent-name"><?php echo esc_html( $ai_name ); ?></div>
						<div class="aiagent-status"><?php esc_html_e( 'Online now', 'ai-agent-for-website' ); ?></div>
					</div>
				</div>
				<div class="aiagent-header-actions">
					<button class="aiagent-new-chat" title="<?php esc_attr_e( 'New conversation', 'ai-agent-for-website' ); ?>">
						<!-- Lucide: plus -->
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<line x1="12" y1="5" x2="12" y2="19"></line>
							<line x1="5" y1="12" x2="19" y2="12"></line>
						</svg>
					</button>
					<button class="aiagent-close-chat" title="<?php esc_attr_e( 'End conversation', 'ai-agent-for-website' ); ?>">
						<!-- Lucide: x -->
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<line x1="18" y1="6" x2="6" y2="18"></line>
							<line x1="6" y1="6" x2="18" y2="18"></line>
						</svg>
					</button>
				</div>
			</div>

			<!-- User Info Form -->
			<div class="aiagent-user-form">
				<div class="aiagent-user-form-inner">
					<h3><?php esc_html_e( 'Start a conversation', 'ai-agent-for-website' ); ?></h3>
					<p><?php esc_html_e( 'Please introduce yourself so we can assist you better.', 'ai-agent-for-website' ); ?></p>
					<form class="aiagent-user-info-form">
						<div class="aiagent-form-group">
							<label for="aiagent-user-name-inline"><?php esc_html_e( 'Your Name', 'ai-agent-for-website' ); ?></label>
							<input type="text" 
									id="aiagent-user-name-inline" 
									name="user_name" 
									required 
									placeholder="<?php esc_attr_e( 'Enter your name', 'ai-agent-for-website' ); ?>">
						</div>
						<div class="aiagent-form-group">
							<label for="aiagent-user-email-inline"><?php esc_html_e( 'Email Address', 'ai-agent-for-website' ); ?></label>
							<input type="email" 
									id="aiagent-user-email-inline" 
									name="user_email" 
									required 
									placeholder="<?php esc_attr_e( 'Enter your email', 'ai-agent-for-website' ); ?>">
						</div>
					<?php if ( ! empty( $settings['require_phone'] ) ) : ?>
					<div class="aiagent-form-group">
						<label for="aiagent-user-phone-inline"><?php esc_html_e( 'Phone Number', 'ai-agent-for-website' ); ?></label>
						<input type="tel" 
								id="aiagent-user-phone-inline" 
								name="user_phone" 
								<?php echo ! empty( $settings['phone_required'] ) ? 'required' : ''; ?>
								placeholder="<?php esc_attr_e( 'Enter your phone number', 'ai-agent-for-website' ); ?>">
					</div>
					<?php endif; ?>

					<!-- Consent Checkboxes -->
					<div class="aiagent-consent-section">
						<?php if ( ! empty( $settings['consent_ai_enabled'] ) ) : ?>
						<div class="aiagent-consent-item">
							<label class="aiagent-checkbox-label">
								<input type="checkbox" 
										name="consent_ai" 
										required>
								<span><?php echo esc_html( $settings['consent_ai_text'] ?? 'I agree to interact with AI assistance' ); ?> <span class="required">*</span></span>
							</label>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $settings['consent_newsletter'] ) ) : ?>
						<div class="aiagent-consent-item">
							<label class="aiagent-checkbox-label">
								<input type="checkbox" 
										name="consent_newsletter">
								<span><?php echo esc_html( $settings['consent_newsletter_text'] ?? 'Subscribe to our newsletter' ); ?></span>
							</label>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $settings['consent_promotional'] ) ) : ?>
						<div class="aiagent-consent-item">
							<label class="aiagent-checkbox-label">
								<input type="checkbox" 
										name="consent_promotional">
								<span><?php echo esc_html( $settings['consent_promotional_text'] ?? 'Receive promotional updates' ); ?></span>
							</label>
						</div>
						<?php endif; ?>
					</div>

					<button type="submit" class="aiagent-start-chat-btn">
						<?php esc_html_e( 'Start Chat', 'ai-agent-for-website' ); ?>
							<!-- Lucide: arrow-right -->
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
								<line x1="5" y1="12" x2="19" y2="12"></line>
								<polyline points="12 5 19 12 12 19"></polyline>
							</svg>
						</button>
					</form>
				</div>
			</div>

			<!-- Rating Modal -->
			<div class="aiagent-rating-modal">
				<div class="aiagent-rating-inner">
					<h3><?php esc_html_e( 'How was your experience?', 'ai-agent-for-website' ); ?></h3>
					<p><?php esc_html_e( 'Please rate your conversation', 'ai-agent-for-website' ); ?></p>
					<div class="aiagent-stars">
						<button type="button" class="aiagent-star" data-rating="1">★</button>
						<button type="button" class="aiagent-star" data-rating="2">★</button>
						<button type="button" class="aiagent-star" data-rating="3">★</button>
						<button type="button" class="aiagent-star" data-rating="4">★</button>
						<button type="button" class="aiagent-star" data-rating="5">★</button>
					</div>
					<div class="aiagent-rating-actions">
						<button type="button" class="aiagent-skip-rating"><?php esc_html_e( 'Skip', 'ai-agent-for-website' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Calendar Booking Modal -->
			<div class="aiagent-calendar-modal">
				<div class="aiagent-calendar-inner">
					<!-- Step 1: Prompt -->
					<div class="aiagent-calendar-step" data-step="prompt">
						<div class="aiagent-calendar-icon">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48">
								<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
								<line x1="16" y1="2" x2="16" y2="6"></line>
								<line x1="8" y1="2" x2="8" y2="6"></line>
								<line x1="3" y1="10" x2="21" y2="10"></line>
							</svg>
						</div>
						<h3><?php esc_html_e( 'Schedule a Meeting?', 'ai-agent-for-website' ); ?></h3>
						<p class="aiagent-calendar-prompt-text"></p>
						<div class="aiagent-calendar-prompt-actions">
							<button type="button" class="aiagent-btn aiagent-btn-primary aiagent-calendar-yes">
								<?php esc_html_e( 'Yes, book a time', 'ai-agent-for-website' ); ?>
							</button>
							<button type="button" class="aiagent-btn aiagent-btn-secondary aiagent-calendar-no">
								<?php esc_html_e( 'No, thanks', 'ai-agent-for-website' ); ?>
							</button>
						</div>
					</div>

					<!-- Step 2: Select Date & Time -->
					<div class="aiagent-calendar-step" data-step="slots" style="display: none;">
						<div class="aiagent-calendar-header">
							<button type="button" class="aiagent-calendar-back">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
									<polyline points="15 18 9 12 15 6"></polyline>
								</svg>
							</button>
							<h3><?php esc_html_e( 'Select a Time', 'ai-agent-for-website' ); ?></h3>
						</div>
						<div class="aiagent-calendar-loading">
							<div class="aiagent-spinner"></div>
							<p><?php esc_html_e( 'Loading available times...', 'ai-agent-for-website' ); ?></p>
						</div>
						<div class="aiagent-calendar-slots-container">
							<div class="aiagent-calendar-date-nav">
								<button type="button" class="aiagent-date-prev" disabled>
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
										<polyline points="15 18 9 12 15 6"></polyline>
									</svg>
								</button>
								<span class="aiagent-date-label"></span>
								<button type="button" class="aiagent-date-next">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
										<polyline points="9 18 15 12 9 6"></polyline>
									</svg>
								</button>
							</div>
							<div class="aiagent-slots-grid">
								<!-- Slots will be inserted here -->
							</div>
							<div class="aiagent-no-slots" style="display: none;">
								<p><?php esc_html_e( 'No available times on this day.', 'ai-agent-for-website' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Step 3: Event Details -->
					<div class="aiagent-calendar-step" data-step="details" style="display: none;">
						<div class="aiagent-calendar-header">
							<button type="button" class="aiagent-calendar-back">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
									<polyline points="15 18 9 12 15 6"></polyline>
								</svg>
							</button>
							<h3><?php esc_html_e( 'Meeting Details', 'ai-agent-for-website' ); ?></h3>
						</div>
						<div class="aiagent-selected-time">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
								<circle cx="12" cy="12" r="10"></circle>
								<polyline points="12 6 12 12 16 14"></polyline>
							</svg>
							<span class="aiagent-selected-time-text"></span>
						</div>
						<form class="aiagent-calendar-form">
							<div class="aiagent-form-group">
								<label for="aiagent-event-title-inline"><?php esc_html_e( 'Meeting Topic', 'ai-agent-for-website' ); ?></label>
								<input type="text" id="aiagent-event-title-inline" name="event_title" 
									placeholder="<?php esc_attr_e( 'e.g., Follow-up discussion', 'ai-agent-for-website' ); ?>" required>
							</div>
							<div class="aiagent-form-group">
								<label for="aiagent-event-description-inline"><?php esc_html_e( 'Notes (optional)', 'ai-agent-for-website' ); ?></label>
								<textarea id="aiagent-event-description-inline" name="event_description" rows="2" 
									placeholder="<?php esc_attr_e( 'Any additional details...', 'ai-agent-for-website' ); ?>"></textarea>
							</div>
							<button type="submit" class="aiagent-btn aiagent-btn-primary aiagent-btn-full">
								<?php esc_html_e( 'Confirm Booking', 'ai-agent-for-website' ); ?>
							</button>
						</form>
					</div>

					<!-- Step 4: Confirmation -->
					<div class="aiagent-calendar-step" data-step="confirmation" style="display: none;">
						<div class="aiagent-calendar-success-icon">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48">
								<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
								<polyline points="22 4 12 14.01 9 11.01"></polyline>
							</svg>
						</div>
						<h3><?php esc_html_e( 'Booking Confirmed!', 'ai-agent-for-website' ); ?></h3>
						<div class="aiagent-booking-details">
							<p class="aiagent-booking-title"></p>
							<p class="aiagent-booking-time"></p>
						</div>
						<p class="aiagent-booking-invite-sent">
							<?php esc_html_e( 'A calendar invite has been sent to your email.', 'ai-agent-for-website' ); ?>
						</p>
						<button type="button" class="aiagent-btn aiagent-btn-secondary aiagent-calendar-done">
							<?php esc_html_e( 'Done', 'ai-agent-for-website' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="aiagent-messages">
				<!-- Messages will be inserted here -->
			</div>

			<div class="aiagent-input-area">
				<form class="aiagent-form">
					<input type="text" 
							class="aiagent-input" 
							placeholder="<?php esc_attr_e( 'Type your message...', 'ai-agent-for-website' ); ?>"
							autocomplete="off">
					<button type="submit" class="aiagent-send">
						<!-- Lucide: send -->
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<line x1="22" y1="2" x2="11" y2="13"></line>
							<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
						</svg>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Add floating widget to footer.
add_action(
	'wp_footer',
	function () {
		$widget = new AIAGENT_Chat_Widget();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render_floating method.
		echo $widget->render_floating();
	}
);
