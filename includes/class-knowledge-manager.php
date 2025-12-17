<?php
/**
 * Knowledge Base Manager Class
 */

if (!defined('ABSPATH')) {
    exit;
}

use AIEngine\AIEngine;
use AIEngine\Knowledge\KnowledgeBase;

class AIAGENT_Knowledge_Manager {

    private $kb_file;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->kb_file = $upload_dir['basedir'] . '/ai-agent-knowledge/knowledge-base.json';
    }

    /**
     * Render the knowledge base admin page
     */
    public function render_admin_page() {
        // Handle actions
        if (isset($_POST['aiagent_add_url']) && check_admin_referer('aiagent_kb_nonce')) {
            $this->add_url();
        }

        if (isset($_POST['aiagent_add_text']) && check_admin_referer('aiagent_kb_nonce')) {
            $this->add_text();
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['index'])) {
            if (check_admin_referer('aiagent_delete_' . $_GET['index'])) {
                $this->delete_document((int)$_GET['index']);
            }
        }

        if (isset($_POST['aiagent_clear_kb']) && check_admin_referer('aiagent_kb_nonce')) {
            $this->clear_knowledge();
        }

        $kb = $this->get_knowledge_base();
        $documents = $kb->getDocuments();
        $summary = $kb->getSummary();
        ?>
        <div class="wrap aiagent-admin">
            <h1><?php _e('Knowledge Base', 'ai-agent-for-website'); ?></h1>
            
            <p class="description">
                <?php _e('Add content to train your AI agent. The AI will use this information to answer questions about your website.', 'ai-agent-for-website'); ?>
            </p>

            <div class="aiagent-kb-grid">
                <div class="aiagent-card">
                    <h2><?php _e('Add from URL', 'ai-agent-for-website'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('aiagent_kb_nonce'); ?>
                        <p>
                            <input type="url" 
                                   name="kb_url" 
                                   placeholder="https://example.com/page" 
                                   class="large-text" 
                                   required>
                        </p>
                        <p class="description">
                            <?php _e('Enter a URL to fetch and add its content to the knowledge base.', 'ai-agent-for-website'); ?>
                        </p>
                        <p>
                            <input type="submit" 
                                   name="aiagent_add_url" 
                                   class="button button-primary" 
                                   value="<?php _e('Add URL', 'ai-agent-for-website'); ?>">
                        </p>
                    </form>
                </div>

                <div class="aiagent-card">
                    <h2><?php _e('Add Custom Text', 'ai-agent-for-website'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('aiagent_kb_nonce'); ?>
                        <p>
                            <input type="text" 
                                   name="kb_title" 
                                   placeholder="<?php _e('Title (optional)', 'ai-agent-for-website'); ?>" 
                                   class="large-text">
                        </p>
                        <p>
                            <textarea name="kb_text" 
                                      rows="4" 
                                      class="large-text" 
                                      placeholder="<?php _e('Enter your content here...', 'ai-agent-for-website'); ?>" 
                                      required></textarea>
                        </p>
                        <p>
                            <input type="submit" 
                                   name="aiagent_add_text" 
                                   class="button button-primary" 
                                   value="<?php _e('Add Text', 'ai-agent-for-website'); ?>">
                        </p>
                    </form>
                </div>
            </div>

            <div class="aiagent-card">
                <h2>
                    <?php _e('Knowledge Base Contents', 'ai-agent-for-website'); ?>
                    <span class="aiagent-badge"><?php echo $summary['count']; ?> <?php _e('documents', 'ai-agent-for-website'); ?></span>
                </h2>

                <?php if ($summary['count'] > 0): ?>
                    <p class="description">
                        <?php printf(__('Total content: %s characters', 'ai-agent-for-website'), number_format($summary['totalChars'])); ?>
                    </p>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 25%;"><?php _e('Title', 'ai-agent-for-website'); ?></th>
                                <th style="width: 35%;"><?php _e('Source', 'ai-agent-for-website'); ?></th>
                                <th style="width: 15%;"><?php _e('Size', 'ai-agent-for-website'); ?></th>
                                <th style="width: 10%;"><?php _e('Added', 'ai-agent-for-website'); ?></th>
                                <th style="width: 10%;"><?php _e('Actions', 'ai-agent-for-website'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $index => $doc): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo esc_html($doc['title'] ?? __('Untitled', 'ai-agent-for-website')); ?></td>
                                    <td>
                                        <?php if (filter_var($doc['source'], FILTER_VALIDATE_URL)): ?>
                                            <a href="<?php echo esc_url($doc['source']); ?>" target="_blank">
                                                <?php echo esc_html(substr($doc['source'], 0, 50)); ?>...
                                            </a>
                                        <?php else: ?>
                                            <?php echo esc_html($doc['source']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format(strlen($doc['content'])); ?> <?php _e('chars', 'ai-agent-for-website'); ?></td>
                                    <td><?php echo date('M j', strtotime($doc['addedAt'])); ?></td>
                                    <td>
                                        <a href="<?php echo wp_nonce_url(
                                            add_query_arg(['action' => 'delete', 'index' => $index]),
                                            'aiagent_delete_' . $index
                                        ); ?>" 
                                           class="button button-small button-link-delete"
                                           onclick="return confirm('<?php _e('Are you sure?', 'ai-agent-for-website'); ?>');">
                                            <?php _e('Delete', 'ai-agent-for-website'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <form method="post" action="" style="margin-top: 20px;">
                        <?php wp_nonce_field('aiagent_kb_nonce'); ?>
                        <input type="submit" 
                               name="aiagent_clear_kb" 
                               class="button button-link-delete" 
                               value="<?php _e('Clear All Knowledge', 'ai-agent-for-website'); ?>"
                               onclick="return confirm('<?php _e('Are you sure you want to delete all knowledge? This cannot be undone.', 'ai-agent-for-website'); ?>');">
                    </form>
                <?php else: ?>
                    <p class="aiagent-empty-state">
                        <?php _e('No content added yet. Add URLs or text above to train your AI agent.', 'ai-agent-for-website'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="aiagent-card">
                <h2><?php _e('Quick Add Website Pages', 'ai-agent-for-website'); ?></h2>
                <p class="description">
                    <?php _e('Quickly add your website pages to the knowledge base:', 'ai-agent-for-website'); ?>
                </p>
                <div class="aiagent-quick-pages">
                    <?php
                    $pages = get_pages(['number' => 10, 'sort_column' => 'post_modified', 'sort_order' => 'DESC']);
                    foreach ($pages as $page):
                    ?>
                        <form method="post" action="" style="display: inline-block; margin: 5px;">
                            <?php wp_nonce_field('aiagent_kb_nonce'); ?>
                            <input type="hidden" name="kb_url" value="<?php echo get_permalink($page->ID); ?>">
                            <button type="submit" name="aiagent_add_url" class="button button-small">
                                + <?php echo esc_html($page->post_title); ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get knowledge base instance
     */
    public function get_knowledge_base() {
        $kb = new KnowledgeBase();
        
        if (file_exists($this->kb_file)) {
            $kb->load($this->kb_file);
        }
        
        return $kb;
    }

    /**
     * Save knowledge base
     */
    private function save_knowledge_base(KnowledgeBase $kb) {
        $dir = dirname($this->kb_file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        return $kb->save($this->kb_file);
    }

    /**
     * Add URL to knowledge base
     */
    private function add_url() {
        $url = esc_url_raw($_POST['kb_url'] ?? '');
        
        if (empty($url)) {
            $this->show_notice(__('Please enter a valid URL.', 'ai-agent-for-website'), 'error');
            return;
        }

        $kb = $this->get_knowledge_base();
        $result = $kb->addUrl($url);

        if ($result['success']) {
            $this->save_knowledge_base($kb);
            $title = $result['title'] ?? __('Untitled', 'ai-agent-for-website');
            $this->show_notice(sprintf(__('Added: %s', 'ai-agent-for-website'), $title), 'success');
        } else {
            $this->show_notice($result['error'] ?? __('Failed to fetch URL.', 'ai-agent-for-website'), 'error');
        }
    }

    /**
     * Add text to knowledge base
     */
    private function add_text() {
        $text = sanitize_textarea_field($_POST['kb_text'] ?? '');
        $title = sanitize_text_field($_POST['kb_title'] ?? '');

        if (empty($text)) {
            $this->show_notice(__('Please enter some text.', 'ai-agent-for-website'), 'error');
            return;
        }

        $kb = $this->get_knowledge_base();
        $source = 'manual-entry-' . time();
        $result = $kb->addText($text, $source, $title ?: null);

        if ($result) {
            $this->save_knowledge_base($kb);
            $this->show_notice(__('Text added to knowledge base.', 'ai-agent-for-website'), 'success');
        } else {
            $this->show_notice(__('Failed to add text.', 'ai-agent-for-website'), 'error');
        }
    }

    /**
     * Delete a document
     */
    private function delete_document($index) {
        $kb = $this->get_knowledge_base();
        
        if ($kb->remove($index)) {
            $this->save_knowledge_base($kb);
            $this->show_notice(__('Document deleted.', 'ai-agent-for-website'), 'success');
        }
    }

    /**
     * Clear all knowledge
     */
    private function clear_knowledge() {
        $kb = $this->get_knowledge_base();
        $kb->clear();
        $this->save_knowledge_base($kb);
        $this->show_notice(__('Knowledge base cleared.', 'ai-agent-for-website'), 'success');
    }

    /**
     * Show admin notice
     */
    private function show_notice($message, $type = 'success') {
        add_settings_error('aiagent_kb_messages', 'aiagent_kb_message', $message, $type);
        settings_errors('aiagent_kb_messages');
    }
}

