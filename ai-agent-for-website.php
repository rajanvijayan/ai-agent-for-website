<?php
/**
 * Plugin Name: AI Agent for Website
 * Plugin URI: https://developer.developer.developer
 * Description: Add an AI-powered chat agent to your website using Groq API. Train it with your website content.
 * Version: 1.0.0
 * Author: Developer Developer
 * Author URI: https://developer.developer.developer
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-agent-for-website
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIAGENT_VERSION', '1.0.0');
define('AIAGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIAGENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIAGENT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class AI_Agent_For_Website {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Load AI Engine library
        require_once AIAGENT_PLUGIN_DIR . 'vendor/autoload.php';
        
        // Load plugin classes
        require_once AIAGENT_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once AIAGENT_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once AIAGENT_PLUGIN_DIR . 'includes/class-chat-widget.php';
        require_once AIAGENT_PLUGIN_DIR . 'includes/class-knowledge-manager.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialize components
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = [
            'api_key' => '',
            'ai_name' => 'AI Assistant',
            'system_instruction' => 'You are a helpful assistant for this website. Answer questions based on the website content provided. Be friendly and concise.',
            'welcome_message' => 'Hello! How can I help you today?',
            'widget_position' => 'bottom-right',
            'primary_color' => '#0073aa',
            'knowledge_urls' => [],
            'enabled' => false,
        ];

        if (!get_option('aiagent_settings')) {
            add_option('aiagent_settings', $defaults);
        }

        // Create knowledge base storage directory
        $upload_dir = wp_upload_dir();
        $kb_dir = $upload_dir['basedir'] . '/ai-agent-knowledge';
        if (!file_exists($kb_dir)) {
            wp_mkdir_p($kb_dir);
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('ai-agent-for-website', false, dirname(AIAGENT_PLUGIN_BASENAME) . '/languages');

        // Register shortcode
        add_shortcode('ai_agent_chat', [$this, 'chat_shortcode']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Agent', 'ai-agent-for-website'),
            __('AI Agent', 'ai-agent-for-website'),
            'manage_options',
            'ai-agent-settings',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            'ai-agent-settings',
            __('Settings', 'ai-agent-for-website'),
            __('Settings', 'ai-agent-for-website'),
            'manage_options',
            'ai-agent-settings',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'ai-agent-settings',
            __('Knowledge Base', 'ai-agent-for-website'),
            __('Knowledge Base', 'ai-agent-for-website'),
            'manage_options',
            'ai-agent-knowledge',
            [$this, 'render_knowledge_page']
        );
    }

    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        $admin = new AIAGENT_Admin_Settings();
        $admin->render();
    }

    /**
     * Render knowledge base page
     */
    public function render_knowledge_page() {
        $knowledge = new AIAGENT_Knowledge_Manager();
        $knowledge->render_admin_page();
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $api = new AIAGENT_REST_API();
        $api->register_routes();
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'ai-agent') === false) {
            return;
        }

        wp_enqueue_style(
            'aiagent-admin',
            AIAGENT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AIAGENT_VERSION
        );

        wp_enqueue_script(
            'aiagent-admin',
            AIAGENT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AIAGENT_VERSION,
            true
        );

        wp_localize_script('aiagent-admin', 'aiagentAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('ai-agent/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Enqueue frontend scripts
     */
    public function frontend_scripts() {
        $settings = get_option('aiagent_settings', []);
        
        if (empty($settings['enabled'])) {
            return;
        }

        wp_enqueue_style(
            'aiagent-chat',
            AIAGENT_PLUGIN_URL . 'assets/css/chat-widget.css',
            [],
            AIAGENT_VERSION
        );

        wp_enqueue_script(
            'aiagent-chat',
            AIAGENT_PLUGIN_URL . 'assets/js/chat-widget.js',
            [],
            AIAGENT_VERSION,
            true
        );

        wp_localize_script('aiagent-chat', 'aiagentConfig', [
            'restUrl' => rest_url('ai-agent/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'aiName' => $settings['ai_name'] ?? 'AI Assistant',
            'welcomeMessage' => $settings['welcome_message'] ?? 'Hello! How can I help you?',
            'position' => $settings['widget_position'] ?? 'bottom-right',
            'primaryColor' => $settings['primary_color'] ?? '#0073aa',
        ]);
    }

    /**
     * Chat shortcode
     */
    public function chat_shortcode($atts) {
        $atts = shortcode_atts([
            'height' => '500px',
            'width' => '100%',
        ], $atts);

        $widget = new AIAGENT_Chat_Widget();
        return $widget->render_inline($atts);
    }

    /**
     * Get plugin settings
     */
    public static function get_settings() {
        return get_option('aiagent_settings', []);
    }

    /**
     * Update plugin settings
     */
    public static function update_settings($settings) {
        return update_option('aiagent_settings', $settings);
    }
}

// Initialize plugin
function aiagent_init() {
    return AI_Agent_For_Website::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'aiagent_init');

// Add settings link on plugins page
add_filter('plugin_action_links_' . AIAGENT_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=ai-agent-settings') . '">' . __('Settings', 'ai-agent-for-website') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

