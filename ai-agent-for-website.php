<?php
/**
 * Plugin Name: AI Agent for Website
 * Plugin URI: https://github.com/rajanvijayan/ai-agent-for-website
 * Description: Add an AI-powered chat agent to your website using Groq API. Train it with your website content.
 * Version: 1.2.0
 * Author: Rajan Vijayan
 * Author URI: https://rajanvijayan.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-agent-for-website
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 *
 * @package AI_Agent_For_Website
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AIAGENT_VERSION', '1.2.0' );
define( 'AIAGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIAGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIAGENT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
class AI_Agent_For_Website {

	/**
	 * Single instance of the class.
	 *
	 * @var AI_Agent_For_Website|null
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return AI_Agent_For_Website
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		// Load AI Engine library.
		require_once AIAGENT_PLUGIN_DIR . 'vendor/autoload.php';

		// Load plugin classes.
		require_once AIAGENT_PLUGIN_DIR . 'includes/class-admin-settings.php';
		require_once AIAGENT_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once AIAGENT_PLUGIN_DIR . 'includes/class-chat-widget.php';
		require_once AIAGENT_PLUGIN_DIR . 'includes/class-knowledge-manager.php';
		require_once AIAGENT_PLUGIN_DIR . 'includes/class-plugin-updater.php';
		require_once AIAGENT_PLUGIN_DIR . 'includes/class-file-processor.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

		// Initialize components.
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Check and create tables if needed.
		add_action( 'admin_init', [ $this, 'maybe_create_tables' ] );

		// Initialize plugin updater for GitHub releases.
		add_action( 'admin_init', [ $this, 'init_updater' ] );
	}

	/**
	 * Initialize the plugin updater.
	 *
	 * @return void
	 */
	public function init_updater() {
		new AIAGENT_Plugin_Updater();
	}

	/**
	 * Check if tables exist and create if needed.
	 */
	public function maybe_create_tables() {
		global $wpdb;
		$db_version = get_option( 'aiagent_db_version', '0' );

		// Check if we need to create/update tables.
		if ( version_compare( $db_version, '1.3.0', '<' ) ) {
			$this->create_tables();
		}

		// Ensure settings have all required keys.
		$this->maybe_upgrade_settings();
	}

	/**
	 * Upgrade settings to include new keys.
	 */
	private function maybe_upgrade_settings() {
		$settings = get_option( 'aiagent_settings', [] );
		$updated  = false;

		// Default values for new settings.
		$defaults = [
			'avatar_url'        => '',
			'require_user_info' => true,
			'require_phone'     => false,
			'phone_required'    => false,
			'show_powered_by'   => true,
		];

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $default;
				$updated          = true;
			}
		}

		if ( $updated ) {
			update_option( 'aiagent_settings', $settings );
		}
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Set default options.
		$defaults = [
			'api_key'            => '',
			'ai_name'            => 'AI Assistant',
			'system_instruction' => 'You are a helpful assistant for this website. Answer questions based on the website content provided. Be friendly and concise.',
			'welcome_message'    => 'Hello! How can I help you today?',
			'widget_position'    => 'bottom-right',
			'primary_color'      => '#0073aa',
			'knowledge_urls'     => [],
			'enabled'            => false,
			'avatar_url'         => '',
			'require_user_info'  => true,
			'require_phone'      => false,
			'phone_required'     => false,
			'show_powered_by'    => true,
		];

		if ( ! get_option( 'aiagent_settings' ) ) {
			add_option( 'aiagent_settings', $defaults );
		} else {
			// Merge new defaults with existing settings.
			$existing = get_option( 'aiagent_settings' );
			$merged   = array_merge( $defaults, $existing );
			update_option( 'aiagent_settings', $merged );
		}

		// Create database tables.
		$this->create_tables();

		// Create knowledge base storage directory.
		$upload_dir = wp_upload_dir();
		$kb_dir     = $upload_dir['basedir'] . '/ai-agent-knowledge';
		if ( ! file_exists( $kb_dir ) ) {
			wp_mkdir_p( $kb_dir );
		}

		flush_rewrite_rules();
	}

	/**
	 * Create database tables for users, conversations, and uploaded files.
	 */
	private function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Chat users table.
		$users_table = $wpdb->prefix . 'aiagent_users';
		$users_sql   = "CREATE TABLE $users_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            session_id varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY session_id (session_id)
        ) $charset_collate;";

		// Conversations table.
		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$conversations_sql   = "CREATE TABLE $conversations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_id varchar(100) NOT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            ended_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            rating tinyint(1) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) $charset_collate;";

		// Messages table.
		$messages_table = $wpdb->prefix . 'aiagent_messages';
		$messages_sql   = "CREATE TABLE $messages_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            role varchar(20) NOT NULL,
            content text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";

		// Uploaded files table.
		$files_table = $wpdb->prefix . 'aiagent_uploaded_files';
		$files_sql   = "CREATE TABLE $files_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            filename varchar(255) NOT NULL,
            original_name varchar(255) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            file_path varchar(500) DEFAULT '',
            kb_document_index int DEFAULT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY file_type (file_type)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $users_sql );
		dbDelta( $conversations_sql );
		dbDelta( $messages_sql );
		dbDelta( $files_sql );

		// Store DB version.
		update_option( 'aiagent_db_version', '1.3.0' );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Load text domain.
		load_plugin_textdomain( 'ai-agent-for-website', false, dirname( AIAGENT_PLUGIN_BASENAME ) . '/languages' );

		// Register shortcode.
		add_shortcode( 'ai_agent_chat', [ $this, 'chat_shortcode' ] );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'AI Agent', 'ai-agent-for-website' ),
			__( 'AI Agent', 'ai-agent-for-website' ),
			'manage_options',
			'ai-agent-settings',
			[ $this, 'render_admin_page' ],
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			'ai-agent-settings',
			__( 'Settings', 'ai-agent-for-website' ),
			__( 'Settings', 'ai-agent-for-website' ),
			'manage_options',
			'ai-agent-settings',
			[ $this, 'render_admin_page' ]
		);

		add_submenu_page(
			'ai-agent-settings',
			__( 'Knowledge Base', 'ai-agent-for-website' ),
			__( 'Knowledge Base', 'ai-agent-for-website' ),
			'manage_options',
			'ai-agent-knowledge',
			[ $this, 'render_knowledge_page' ]
		);

		add_submenu_page(
			'ai-agent-settings',
			__( 'Conversations', 'ai-agent-for-website' ),
			__( 'Conversations', 'ai-agent-for-website' ),
			'manage_options',
			'ai-agent-conversations',
			[ $this, 'render_conversations_page' ]
		);
	}

	/**
	 * Render conversations page.
	 */
	public function render_conversations_page() {
		require_once AIAGENT_PLUGIN_DIR . 'includes/class-conversations-manager.php';
		$manager = new AIAGENT_Conversations_Manager();
		$manager->render_admin_page();
	}

	/**
	 * Render admin settings page.
	 */
	public function render_admin_page() {
		$admin = new AIAGENT_Admin_Settings();
		$admin->render();
	}

	/**
	 * Render knowledge base page.
	 */
	public function render_knowledge_page() {
		$knowledge = new AIAGENT_Knowledge_Manager();
		$knowledge->render_admin_page();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$api = new AIAGENT_REST_API();
		$api->register_routes();
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function admin_scripts( $hook ) {
		if ( strpos( $hook, 'ai-agent' ) === false ) {
			return;
		}

		// Enqueue media uploader for avatar.
		wp_enqueue_media();

		wp_enqueue_style(
			'aiagent-admin',
			AIAGENT_PLUGIN_URL . 'assets/css/admin.css',
			[],
			AIAGENT_VERSION
		);

		wp_enqueue_script(
			'aiagent-admin',
			AIAGENT_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			AIAGENT_VERSION,
			true
		);

		wp_localize_script(
			'aiagent-admin',
			'aiagentAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'ai-agent/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public function frontend_scripts() {
		$settings = get_option( 'aiagent_settings', [] );

		if ( empty( $settings['enabled'] ) ) {
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

		// Default to true if require_user_info is not set.
		$require_user_info = array_key_exists( 'require_user_info', $settings ) ? $settings['require_user_info'] : true;

		wp_localize_script(
			'aiagent-chat',
			'aiagentConfig',
			[
				'restUrl'         => rest_url( 'ai-agent/v1/' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'aiName'          => $settings['ai_name'] ?? 'AI Assistant',
				'welcomeMessage'  => $settings['welcome_message'] ?? 'Hello! How can I help you?',
				'position'        => $settings['widget_position'] ?? 'bottom-right',
				'primaryColor'    => $settings['primary_color'] ?? '#0073aa',
				'avatarUrl'       => $settings['avatar_url'] ?? '',
				'requireUserInfo' => (bool) $require_user_info,
			]
		);
	}

	/**
	 * Chat shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function chat_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'height' => '500px',
				'width'  => '100%',
			],
			$atts
		);

		$widget = new AIAGENT_Chat_Widget();
		return $widget->render_inline( $atts );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array Plugin settings.
	 */
	public static function get_settings() {
		return get_option( 'aiagent_settings', [] );
	}

	/**
	 * Update plugin settings.
	 *
	 * @param array $settings New settings to save.
	 * @return bool True if updated successfully.
	 */
	public static function update_settings( $settings ) {
		return update_option( 'aiagent_settings', $settings );
	}
}

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Main plugin file requires function for plugins_loaded hook.

/**
 * Initialize plugin.
 *
 * @return AI_Agent_For_Website
 */
function aiagent_init() {
	return AI_Agent_For_Website::get_instance();
}

// phpcs:enable Universal.Files.SeparateFunctionsFromOO.Mixed

// Start the plugin.
add_action( 'plugins_loaded', 'aiagent_init' );

// Add settings link on plugins page.
add_filter(
	'plugin_action_links_' . AIAGENT_PLUGIN_BASENAME,
	function ( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=ai-agent-settings' ) . '">' . __( 'Settings', 'ai-agent-for-website' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);
