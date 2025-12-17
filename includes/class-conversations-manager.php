<?php
/**
 * Conversations Manager Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIAGENT_Conversations_Manager {

    /**
     * Render admin page
     */
    public function render_admin_page() {
        global $wpdb;
        
        $users_table = $wpdb->prefix . 'aiagent_users';
        $conversations_table = $wpdb->prefix . 'aiagent_conversations';
        $messages_table = $wpdb->prefix . 'aiagent_messages';

        // Check if viewing a specific conversation
        $view_conversation = isset($_GET['conversation']) ? absint($_GET['conversation']) : null;
        
        if ($view_conversation) {
            $this->render_conversation_detail($view_conversation);
            return;
        }

        // Get stats
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");
        $total_conversations = $wpdb->get_var("SELECT COUNT(*) FROM $conversations_table");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $messages_table");
        $today_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $conversations_table WHERE DATE(started_at) = %s",
            current_time('Y-m-d')
        ));

        // Get recent conversations with user info
        $conversations = $wpdb->get_results("
            SELECT c.*, u.name as user_name, u.email as user_email,
                   (SELECT COUNT(*) FROM $messages_table WHERE conversation_id = c.id) as message_count,
                   (SELECT content FROM $messages_table WHERE conversation_id = c.id AND role = 'user' ORDER BY id ASC LIMIT 1) as first_message
            FROM $conversations_table c
            LEFT JOIN $users_table u ON c.user_id = u.id
            ORDER BY c.started_at DESC
            LIMIT 50
        ");

        ?>
        <div class="wrap aiagent-admin">
            <h1><?php _e('Conversations', 'ai-agent-for-website'); ?></h1>

            <div class="aiagent-stats-grid">
                <div class="aiagent-stat-card">
                    <div class="aiagent-stat-number"><?php echo esc_html($total_users); ?></div>
                    <div class="aiagent-stat-label"><?php _e('Total Users', 'ai-agent-for-website'); ?></div>
                </div>
                <div class="aiagent-stat-card">
                    <div class="aiagent-stat-number"><?php echo esc_html($total_conversations); ?></div>
                    <div class="aiagent-stat-label"><?php _e('Total Conversations', 'ai-agent-for-website'); ?></div>
                </div>
                <div class="aiagent-stat-card">
                    <div class="aiagent-stat-number"><?php echo esc_html($total_messages); ?></div>
                    <div class="aiagent-stat-label"><?php _e('Total Messages', 'ai-agent-for-website'); ?></div>
                </div>
                <div class="aiagent-stat-card">
                    <div class="aiagent-stat-number"><?php echo esc_html($today_conversations); ?></div>
                    <div class="aiagent-stat-label"><?php _e('Today\'s Conversations', 'ai-agent-for-website'); ?></div>
                </div>
            </div>

            <div class="aiagent-card">
                <h2><?php _e('Recent Conversations', 'ai-agent-for-website'); ?></h2>
                
                <?php if (empty($conversations)): ?>
                    <div class="aiagent-empty-state">
                        <p><?php _e('No conversations yet. Conversations will appear here once users start chatting.', 'ai-agent-for-website'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="aiagent-conversations-table">
                        <thead>
                            <tr>
                                <th><?php _e('User', 'ai-agent-for-website'); ?></th>
                                <th><?php _e('First Message', 'ai-agent-for-website'); ?></th>
                                <th><?php _e('Messages', 'ai-agent-for-website'); ?></th>
                                <th><?php _e('Rating', 'ai-agent-for-website'); ?></th>
                                <th><?php _e('Started', 'ai-agent-for-website'); ?></th>
                                <th><?php _e('Status', 'ai-agent-for-website'); ?></th>
                                <th><?php _e('Actions', 'ai-agent-for-website'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conversations as $conv): ?>
                                <tr>
                                    <td>
                                        <div class="aiagent-user-badge">
                                            <strong><?php echo esc_html($conv->user_name ?: 'Anonymous'); ?></strong>
                                        </div>
                                        <?php if ($conv->user_email): ?>
                                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                                <?php echo esc_html($conv->user_email); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $preview = $conv->first_message ? wp_trim_words($conv->first_message, 10) : '-';
                                        echo esc_html($preview);
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($conv->message_count); ?></td>
                                    <td>
                                        <?php if ($conv->rating): ?>
                                            <span class="aiagent-rating-display">
                                                <?php echo str_repeat('★', $conv->rating) . str_repeat('☆', 5 - $conv->rating); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $started = strtotime($conv->started_at);
                                        echo esc_html(human_time_diff($started, current_time('timestamp')) . ' ago');
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($conv->status === 'active'): ?>
                                            <span style="color: #46b450;">●</span> <?php _e('Active', 'ai-agent-for-website'); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">●</span> <?php _e('Ended', 'ai-agent-for-website'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-agent-conversations&conversation=' . $conv->id)); ?>" class="button button-small">
                                            <?php _e('View', 'ai-agent-for-website'); ?>
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
     * Render conversation detail
     */
    private function render_conversation_detail($conversation_id) {
        global $wpdb;
        
        $users_table = $wpdb->prefix . 'aiagent_users';
        $conversations_table = $wpdb->prefix . 'aiagent_conversations';
        $messages_table = $wpdb->prefix . 'aiagent_messages';

        // Get conversation with user info
        $conversation = $wpdb->get_row($wpdb->prepare("
            SELECT c.*, u.name as user_name, u.email as user_email
            FROM $conversations_table c
            LEFT JOIN $users_table u ON c.user_id = u.id
            WHERE c.id = %d
        ", $conversation_id));

        if (!$conversation) {
            echo '<div class="wrap"><h1>' . __('Conversation not found', 'ai-agent-for-website') . '</h1></div>';
            return;
        }

        // Get messages
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $messages_table 
            WHERE conversation_id = %d 
            ORDER BY created_at ASC
        ", $conversation_id));

        ?>
        <div class="wrap aiagent-admin">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-agent-conversations')); ?>" style="text-decoration: none;">
                    ← <?php _e('Conversations', 'ai-agent-for-website'); ?>
                </a>
            </h1>

            <div class="aiagent-card">
                <h2>
                    <?php _e('Conversation Details', 'ai-agent-for-website'); ?>
                    <?php if ($conversation->status === 'active'): ?>
                        <span class="aiagent-badge" style="background: #46b450;"><?php _e('Active', 'ai-agent-for-website'); ?></span>
                    <?php else: ?>
                        <span class="aiagent-badge" style="background: #999;"><?php _e('Ended', 'ai-agent-for-website'); ?></span>
                    <?php endif; ?>
                </h2>

                <table class="form-table">
                    <tr>
                        <th><?php _e('User', 'ai-agent-for-website'); ?></th>
                        <td>
                            <strong><?php echo esc_html($conversation->user_name ?: 'Anonymous'); ?></strong>
                            <?php if ($conversation->user_email): ?>
                                <br><a href="mailto:<?php echo esc_attr($conversation->user_email); ?>"><?php echo esc_html($conversation->user_email); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Started', 'ai-agent-for-website'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($conversation->started_at))); ?></td>
                    </tr>
                    <?php if ($conversation->ended_at): ?>
                    <tr>
                        <th><?php _e('Ended', 'ai-agent-for-website'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($conversation->ended_at))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php _e('Messages', 'ai-agent-for-website'); ?></th>
                        <td><?php echo count($messages); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Rating', 'ai-agent-for-website'); ?></th>
                        <td>
                            <?php if ($conversation->rating): ?>
                                <span class="aiagent-rating-display" style="color: #ffc107; font-size: 18px;">
                                    <?php echo str_repeat('★', $conversation->rating) . str_repeat('☆', 5 - $conversation->rating); ?>
                                </span>
                                <span style="color: #666; margin-left: 8px;">(<?php echo $conversation->rating; ?>/5)</span>
                            <?php else: ?>
                                <span style="color: #999;"><?php _e('Not rated', 'ai-agent-for-website'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="aiagent-card">
                <h2><?php _e('Messages', 'ai-agent-for-website'); ?></h2>
                
                <?php if (empty($messages)): ?>
                    <div class="aiagent-empty-state">
                        <p><?php _e('No messages in this conversation.', 'ai-agent-for-website'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="aiagent-message-log">
                        <?php foreach ($messages as $msg): ?>
                            <div class="aiagent-message-item <?php echo esc_attr($msg->role); ?>">
                                <div class="aiagent-message-content">
                                    <?php echo nl2br(esc_html($msg->content)); ?>
                                </div>
                                <div class="aiagent-message-meta">
                                    <?php 
                                    $role_label = $msg->role === 'user' ? ($conversation->user_name ?: __('User', 'ai-agent-for-website')) : __('AI Assistant', 'ai-agent-for-website');
                                    echo esc_html($role_label);
                                    ?> • 
                                    <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($msg->created_at))); ?>
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
     * Get all users
     */
    public function get_users($limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_users';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Get user by ID
     */
    public function get_user($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $user_id
        ));
    }

    /**
     * Get user by email
     */
    public function get_user_by_email($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $email
        ));
    }

    /**
     * Get conversations for a user
     */
    public function get_user_conversations($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_conversations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY started_at DESC",
            $user_id
        ));
    }

    /**
     * Get messages for a conversation
     */
    public function get_conversation_messages($conversation_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_messages';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE conversation_id = %d ORDER BY created_at ASC",
            $conversation_id
        ));
    }
}

