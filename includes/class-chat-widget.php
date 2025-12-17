<?php
/**
 * Chat Widget Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIAGENT_Chat_Widget {

    /**
     * Render floating chat widget (added to footer)
     */
    public function render_floating() {
        $settings = AI_Agent_For_Website::get_settings();
        
        if (empty($settings['enabled'])) {
            return '';
        }

        $ai_name = esc_attr($settings['ai_name'] ?? 'AI Assistant');
        $position = esc_attr($settings['widget_position'] ?? 'bottom-right');
        $color = esc_attr($settings['primary_color'] ?? '#0073aa');
        $avatar_url = esc_url($settings['avatar_url'] ?? '');
        $require_user_info = !empty($settings['require_user_info']);

        ob_start();
        ?>
        <div id="aiagent-chat-widget" 
             class="aiagent-widget aiagent-position-<?php echo $position; ?>"
             style="--aiagent-primary: <?php echo $color; ?>;">
            
            <!-- Toggle Button -->
            <button class="aiagent-toggle" aria-label="<?php _e('Open chat', 'ai-agent-for-website'); ?>">
                <svg class="aiagent-icon-chat" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
                <svg class="aiagent-icon-close" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>

            <!-- Chat Window -->
            <div class="aiagent-window">
                <div class="aiagent-header">
                    <div class="aiagent-header-info">
                        <div class="aiagent-avatar">
                            <?php if ($avatar_url): ?>
                                <img src="<?php echo $avatar_url; ?>" alt="<?php echo $ai_name; ?>">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="aiagent-name"><?php echo $ai_name; ?></div>
                            <div class="aiagent-status"><?php _e('Online now', 'ai-agent-for-website'); ?></div>
                        </div>
                    </div>
                    <button class="aiagent-new-chat" title="<?php _e('New conversation', 'ai-agent-for-website'); ?>">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </button>
                </div>

                <!-- User Info Form -->
                <div class="aiagent-user-form" style="display: none;">
                    <div class="aiagent-user-form-inner">
                        <h3><?php _e('Start a conversation', 'ai-agent-for-website'); ?></h3>
                        <p><?php _e('Please introduce yourself so we can assist you better.', 'ai-agent-for-website'); ?></p>
                        <form class="aiagent-user-info-form">
                            <div class="aiagent-form-group">
                                <label for="aiagent-user-name"><?php _e('Your Name', 'ai-agent-for-website'); ?></label>
                                <input type="text" 
                                       id="aiagent-user-name" 
                                       name="user_name" 
                                       required 
                                       placeholder="<?php _e('Enter your name', 'ai-agent-for-website'); ?>">
                            </div>
                            <div class="aiagent-form-group">
                                <label for="aiagent-user-email"><?php _e('Email Address', 'ai-agent-for-website'); ?></label>
                                <input type="email" 
                                       id="aiagent-user-email" 
                                       name="user_email" 
                                       required 
                                       placeholder="<?php _e('Enter your email', 'ai-agent-for-website'); ?>">
                            </div>
                            <button type="submit" class="aiagent-start-chat-btn">
                                <?php _e('Start Chat', 'ai-agent-for-website'); ?>
                                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                    <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="aiagent-messages">
                    <!-- Messages will be inserted here -->
                </div>

                <div class="aiagent-input-area">
                    <form class="aiagent-form">
                        <input type="text" 
                               class="aiagent-input" 
                               placeholder="<?php _e('Type your message...', 'ai-agent-for-website'); ?>"
                               autocomplete="off">
                        <button type="submit" class="aiagent-send">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </form>
                </div>

                <div class="aiagent-powered">
                    <?php echo esc_html(get_bloginfo('name')); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render inline chat (for shortcode)
     */
    public function render_inline($atts) {
        $settings = AI_Agent_For_Website::get_settings();
        
        $ai_name = esc_attr($settings['ai_name'] ?? 'AI Assistant');
        $color = esc_attr($settings['primary_color'] ?? '#0073aa');
        $avatar_url = esc_url($settings['avatar_url'] ?? '');
        $height = esc_attr($atts['height'] ?? '500px');
        $width = esc_attr($atts['width'] ?? '100%');

        ob_start();
        ?>
        <div class="aiagent-inline-chat" 
             style="--aiagent-primary: <?php echo $color; ?>; height: <?php echo $height; ?>; width: <?php echo $width; ?>;">
            
            <div class="aiagent-header">
                <div class="aiagent-header-info">
                    <div class="aiagent-avatar">
                        <?php if ($avatar_url): ?>
                            <img src="<?php echo $avatar_url; ?>" alt="<?php echo $ai_name; ?>">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="aiagent-name"><?php echo $ai_name; ?></div>
                        <div class="aiagent-status"><?php _e('Online now', 'ai-agent-for-website'); ?></div>
                    </div>
                </div>
                <button class="aiagent-new-chat" title="<?php _e('New conversation', 'ai-agent-for-website'); ?>">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </button>
            </div>

            <!-- User Info Form -->
            <div class="aiagent-user-form" style="display: none;">
                <div class="aiagent-user-form-inner">
                    <h3><?php _e('Start a conversation', 'ai-agent-for-website'); ?></h3>
                    <p><?php _e('Please introduce yourself so we can assist you better.', 'ai-agent-for-website'); ?></p>
                    <form class="aiagent-user-info-form">
                        <div class="aiagent-form-group">
                            <label for="aiagent-user-name"><?php _e('Your Name', 'ai-agent-for-website'); ?></label>
                            <input type="text" 
                                   id="aiagent-user-name" 
                                   name="user_name" 
                                   required 
                                   placeholder="<?php _e('Enter your name', 'ai-agent-for-website'); ?>">
                        </div>
                        <div class="aiagent-form-group">
                            <label for="aiagent-user-email"><?php _e('Email Address', 'ai-agent-for-website'); ?></label>
                            <input type="email" 
                                   id="aiagent-user-email" 
                                   name="user_email" 
                                   required 
                                   placeholder="<?php _e('Enter your email', 'ai-agent-for-website'); ?>">
                        </div>
                        <button type="submit" class="aiagent-start-chat-btn">
                            <?php _e('Start Chat', 'ai-agent-for-website'); ?>
                            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <div class="aiagent-messages">
                <!-- Messages will be inserted here -->
            </div>

            <div class="aiagent-input-area">
                <form class="aiagent-form">
                    <input type="text" 
                           class="aiagent-input" 
                           placeholder="<?php _e('Type your message...', 'ai-agent-for-website'); ?>"
                           autocomplete="off">
                    <button type="submit" class="aiagent-send">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Add floating widget to footer
add_action('wp_footer', function() {
    $widget = new AIAGENT_Chat_Widget();
    echo $widget->render_floating();
});

