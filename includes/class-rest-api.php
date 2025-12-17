<?php
/**
 * REST API Class
 */

if (!defined('ABSPATH')) {
    exit;
}

use AIEngine\AIEngine;

class AIAGENT_REST_API {

    private $namespace = 'ai-agent/v1';
    
    // Store conversation history per session
    private static $conversations = [];

    /**
     * Register REST routes
     */
    public function register_routes() {
        // Chat endpoint
        register_rest_route($this->namespace, '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'session_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // New conversation endpoint
        register_rest_route($this->namespace, '/new-conversation', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_new_conversation'],
            'permission_callback' => '__return_true',
            'args' => [
                'session_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Test connection endpoint (admin only)
        register_rest_route($this->namespace, '/test', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_test'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Fetch URL endpoint (admin only)
        register_rest_route($this->namespace, '/fetch-url', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_fetch_url'],
            'permission_callback' => [$this, 'admin_permission_check'],
            'args' => [
                'url' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);
    }

    /**
     * Admin permission check
     */
    public function admin_permission_check() {
        return current_user_can('manage_options');
    }

    /**
     * Handle chat request
     */
    public function handle_chat($request) {
        $message = $request->get_param('message');
        $session_id = $request->get_param('session_id') ?: $this->generate_session_id();

        // Validate message
        if (empty(trim($message))) {
            return new WP_Error('empty_message', __('Please enter a message.', 'ai-agent-for-website'), ['status' => 400]);
        }

        // Get settings
        $settings = AI_Agent_For_Website::get_settings();

        // Check if enabled
        if (empty($settings['enabled'])) {
            return new WP_Error('not_enabled', __('Chat is not enabled.', 'ai-agent-for-website'), ['status' => 403]);
        }

        // Check API key
        if (empty($settings['api_key'])) {
            return new WP_Error('no_api_key', __('AI is not configured.', 'ai-agent-for-website'), ['status' => 500]);
        }

        try {
            // Create AI Engine with Groq
            $ai = AIEngine::create('groq', $settings['api_key']);

            // Set system instruction
            $systemInstruction = $settings['system_instruction'] ?? '';
            
            // Add site context
            $siteContext = sprintf(
                "You are %s, an AI assistant for the website '%s' (%s).",
                $settings['ai_name'] ?? 'AI Assistant',
                get_bloginfo('name'),
                home_url()
            );
            
            $ai->setSystemInstruction($siteContext . "\n\n" . $systemInstruction);

            // Load knowledge base if available
            $knowledge_manager = new AIAGENT_Knowledge_Manager();
            $kb = $knowledge_manager->get_knowledge_base();
            
            if (!$kb->isEmpty()) {
                // Get the Groq provider and set knowledge base
                $provider = $ai->getProvider();
                if (method_exists($provider, 'setKnowledgeBase')) {
                    $provider->setKnowledgeBase($kb);
                }
            }

            // Restore conversation history
            $this->restore_conversation($ai, $session_id);

            // Send message
            $response = $ai->chat($message);

            // Save conversation history
            $this->save_conversation($ai, $session_id);

            // Handle error response
            if (is_array($response) && isset($response['error'])) {
                return new WP_Error('ai_error', $response['error'], ['status' => 500]);
            }

            return rest_ensure_response([
                'success' => true,
                'message' => $response,
                'session_id' => $session_id,
            ]);

        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Handle new conversation
     */
    public function handle_new_conversation($request) {
        $session_id = $request->get_param('session_id');
        
        if ($session_id) {
            $this->clear_conversation($session_id);
        }

        $new_session_id = $this->generate_session_id();

        return rest_ensure_response([
            'success' => true,
            'session_id' => $new_session_id,
        ]);
    }

    /**
     * Handle API test
     */
    public function handle_test($request) {
        $settings = AI_Agent_For_Website::get_settings();

        if (empty($settings['api_key'])) {
            return new WP_Error('no_api_key', __('API key not configured.', 'ai-agent-for-website'), ['status' => 400]);
        }

        try {
            $ai = AIEngine::create('groq', $settings['api_key']);
            $response = $ai->generateContent('Say "Hello! API connection successful." in exactly those words.');

            if (is_array($response) && isset($response['error'])) {
                return new WP_Error('api_error', $response['error'], ['status' => 500]);
            }

            return rest_ensure_response([
                'success' => true,
                'message' => $response,
            ]);

        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Handle URL fetch (for admin)
     */
    public function handle_fetch_url($request) {
        $url = $request->get_param('url');

        $knowledge_manager = new AIAGENT_Knowledge_Manager();
        $kb = $knowledge_manager->get_knowledge_base();
        
        $result = $kb->addUrl($url);

        if ($result['success']) {
            // Save the updated knowledge base
            $upload_dir = wp_upload_dir();
            $kb_file = $upload_dir['basedir'] . '/ai-agent-knowledge/knowledge-base.json';
            $kb->save($kb_file);

            return rest_ensure_response([
                'success' => true,
                'title' => $result['title'] ?? 'Untitled',
            ]);
        }

        return new WP_Error('fetch_error', $result['error'] ?? 'Failed to fetch URL', ['status' => 400]);
    }

    /**
     * Generate session ID
     */
    private function generate_session_id() {
        return 'session_' . wp_generate_password(16, false);
    }

    /**
     * Get conversation key for transient
     */
    private function get_conversation_key($session_id) {
        return 'aiagent_conv_' . md5($session_id);
    }

    /**
     * Restore conversation history
     */
    private function restore_conversation($ai, $session_id) {
        $key = $this->get_conversation_key($session_id);
        $history = get_transient($key);
        
        if ($history && is_array($history)) {
            $provider = $ai->getProvider();
            foreach ($history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $provider->addToHistory($msg['role'], $msg['content']);
                }
            }
        }
    }

    /**
     * Save conversation history
     */
    private function save_conversation($ai, $session_id) {
        $key = $this->get_conversation_key($session_id);
        $history = $ai->getHistory();
        
        // Keep last 20 messages to avoid memory issues
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        
        // Store for 1 hour
        set_transient($key, $history, HOUR_IN_SECONDS);
    }

    /**
     * Clear conversation history
     */
    private function clear_conversation($session_id) {
        $key = $this->get_conversation_key($session_id);
        delete_transient($key);
    }
}

