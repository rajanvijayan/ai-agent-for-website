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
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="aiagent-name"><?php echo $ai_name; ?></div>
                            <div class="aiagent-status"><?php _e('Online', 'ai-agent-for-website'); ?></div>
                        </div>
                    </div>
                    <button class="aiagent-new-chat" title="<?php _e('New conversation', 'ai-agent-for-website'); ?>">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </button>
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
                    <?php _e('Powered by AI', 'ai-agent-for-website'); ?>
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
        $height = esc_attr($atts['height'] ?? '500px');
        $width = esc_attr($atts['width'] ?? '100%');

        ob_start();
        ?>
        <div class="aiagent-inline-chat" 
             style="--aiagent-primary: <?php echo $color; ?>; height: <?php echo $height; ?>; width: <?php echo $width; ?>;">
            
            <div class="aiagent-header">
                <div class="aiagent-header-info">
                    <div class="aiagent-avatar">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="aiagent-name"><?php echo $ai_name; ?></div>
                        <div class="aiagent-status"><?php _e('Online', 'ai-agent-for-website'); ?></div>
                    </div>
                </div>
                <button class="aiagent-new-chat" title="<?php _e('New conversation', 'ai-agent-for-website'); ?>">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </button>
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

