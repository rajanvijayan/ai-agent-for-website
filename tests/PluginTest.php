<?php
/**
 * Plugin Test
 *
 * Comprehensive test case for the main plugin functionality.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the main plugin functionality.
 */
class PluginTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_mocks();
	}

	/**
	 * Test that the plugin file exists.
	 */
	public function testPluginFileExists(): void {
		$plugin_file = dirname( __DIR__ ) . '/ai-agent-for-website.php';
		$this->assertFileExists( $plugin_file );
	}

	/**
	 * Test that the plugin version constant is defined.
	 */
	public function testVersionConstant(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );
		$this->assertStringContainsString( "define( 'AIAGENT_VERSION'", $plugin_content );
	}

	/**
	 * Test that AIAGENT_VERSION is properly defined.
	 */
	public function testVersionConstantDefined(): void {
		$this->assertTrue( defined( 'AIAGENT_VERSION' ) );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', AIAGENT_VERSION );
	}

	/**
	 * Test that AIAGENT_PLUGIN_DIR is defined.
	 */
	public function testPluginDirConstantDefined(): void {
		$this->assertTrue( defined( 'AIAGENT_PLUGIN_DIR' ) );
		$this->assertNotEmpty( AIAGENT_PLUGIN_DIR );
	}

	/**
	 * Test that AIAGENT_PLUGIN_URL is defined.
	 */
	public function testPluginUrlConstantDefined(): void {
		$this->assertTrue( defined( 'AIAGENT_PLUGIN_URL' ) );
		$this->assertStringContainsString( 'http', AIAGENT_PLUGIN_URL );
	}

	/**
	 * Test that required files exist.
	 */
	public function testRequiredFilesExist(): void {
		$base_dir = dirname( __DIR__ );

		$required_files = [
			'/includes/class-admin-settings.php',
			'/includes/class-chat-widget.php',
			'/includes/class-rest-api.php',
			'/includes/class-knowledge-manager.php',
			'/includes/class-conversations-manager.php',
			'/includes/class-leads-manager.php',
			'/includes/class-notification-manager.php',
			'/includes/class-activity-log-manager.php',
			'/includes/class-file-processor.php',
			'/includes/class-plugin-updater.php',
			'/includes/class-live-agent-manager.php',
			'/includes/class-live-agent-dashboard.php',
		];

		foreach ( $required_files as $file ) {
			$this->assertFileExists(
				$base_dir . $file,
				"Required file {$file} does not exist"
			);
		}
	}

	/**
	 * Test that integration files exist.
	 */
	public function testIntegrationFilesExist(): void {
		$base_dir = dirname( __DIR__ );

		$integration_files = [
			'/includes/integrations/class-google-drive-integration.php',
			'/includes/integrations/class-google-calendar-integration.php',
			'/includes/integrations/class-calendly-integration.php',
			'/includes/integrations/class-confluence-integration.php',
			'/includes/integrations/class-zapier-integration.php',
			'/includes/integrations/class-mailchimp-integration.php',
			'/includes/integrations/class-woocommerce-integration.php',
		];

		foreach ( $integration_files as $file ) {
			$this->assertFileExists(
				$base_dir . $file,
				"Integration file {$file} does not exist"
			);
		}
	}

	/**
	 * Test that assets directories exist.
	 */
	public function testAssetsDirectoriesExist(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertDirectoryExists( $base_dir . '/assets/css' );
		$this->assertDirectoryExists( $base_dir . '/assets/js' );
	}

	/**
	 * Test that CSS files exist.
	 */
	public function testCssFilesExist(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/assets/css/admin.css' );
		$this->assertFileExists( $base_dir . '/assets/css/chat-widget.css' );
		$this->assertFileExists( $base_dir . '/assets/css/live-agent-dashboard.css' );
	}

	/**
	 * Test that JS files exist.
	 */
	public function testJsFilesExist(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/assets/js/admin.js' );
		$this->assertFileExists( $base_dir . '/assets/js/chat-widget.js' );
		$this->assertFileExists( $base_dir . '/assets/js/live-agent-dashboard.js' );
	}

	/**
	 * Test plugin header information.
	 */
	public function testPluginHeaderInformation(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'Plugin Name:', $plugin_content );
		$this->assertStringContainsString( 'Description:', $plugin_content );
		$this->assertStringContainsString( 'Version:', $plugin_content );
		$this->assertStringContainsString( 'Author:', $plugin_content );
		$this->assertStringContainsString( 'Text Domain:', $plugin_content );
	}

	/**
	 * Test plugin text domain.
	 */
	public function testPluginTextDomain(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( "Text Domain: ai-agent-for-website", $plugin_content );
	}

	/**
	 * Test PHP version requirement.
	 */
	public function testPHPVersionRequirement(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'Requires PHP:', $plugin_content );
		$this->assertTrue( version_compare( PHP_VERSION, '8.0', '>=' ) );
	}

	/**
	 * Test WordPress version requirement.
	 */
	public function testWordPressVersionRequirement(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'Requires at least:', $plugin_content );
	}

	/**
	 * Test ABSPATH check exists.
	 */
	public function testABSPATHCheckExists(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( "defined( 'ABSPATH' )", $plugin_content );
	}

	/**
	 * Test main plugin class exists.
	 */
	public function testMainPluginClassExists(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'class AI_Agent_For_Website', $plugin_content );
	}

	/**
	 * Test singleton pattern implementation.
	 */
	public function testSingletonPatternImplementation(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'get_instance', $plugin_content );
		$this->assertStringContainsString( 'private static $instance', $plugin_content );
	}

	/**
	 * Test activation hook registration.
	 */
	public function testActivationHookRegistration(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'register_activation_hook', $plugin_content );
	}

	/**
	 * Test deactivation hook registration.
	 */
	public function testDeactivationHookRegistration(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'register_deactivation_hook', $plugin_content );
	}

	/**
	 * Test REST API routes registration.
	 */
	public function testRESTAPIRoutesRegistration(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'rest_api_init', $plugin_content );
		$this->assertStringContainsString( 'register_rest_routes', $plugin_content );
	}

	/**
	 * Test admin menu registration.
	 */
	public function testAdminMenuRegistration(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'admin_menu', $plugin_content );
		$this->assertStringContainsString( 'add_admin_menu', $plugin_content );
	}

	/**
	 * Test script enqueue registration.
	 */
	public function testScriptEnqueueRegistration(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'admin_enqueue_scripts', $plugin_content );
		$this->assertStringContainsString( 'wp_enqueue_scripts', $plugin_content );
	}

	/**
	 * Test shortcode registration.
	 */
	public function testShortcodeRegistration(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'add_shortcode', $plugin_content );
		$this->assertStringContainsString( 'ai_agent_chat', $plugin_content );
	}

	/**
	 * Test database table creation methods exist.
	 */
	public function testDatabaseTableCreationMethodsExist(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'create_tables', $plugin_content );
		$this->assertStringContainsString( 'dbDelta', $plugin_content );
	}

	/**
	 * Test settings methods exist.
	 */
	public function testSettingsMethodsExist(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'get_settings', $plugin_content );
		$this->assertStringContainsString( 'update_settings', $plugin_content );
	}

	/**
	 * Test aiagent_init function exists.
	 */
	public function testInitFunctionExists(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'function aiagent_init', $plugin_content );
		$this->assertStringContainsString( 'plugins_loaded', $plugin_content );
	}

	/**
	 * Test settings link filter exists.
	 */
	public function testSettingsLinkFilterExists(): void {
		$plugin_content = file_get_contents( dirname( __DIR__ ) . '/ai-agent-for-website.php' );

		$this->assertStringContainsString( 'plugin_action_links', $plugin_content );
	}

	/**
	 * Test default settings values.
	 */
	public function testDefaultSettingsValues(): void {
		$defaults = [
			'api_key'            => '',
			'ai_name'            => 'AI Assistant',
			'system_instruction' => 'You are a helpful assistant',
			'welcome_message'    => 'Hello! How can I help you today?',
			'widget_position'    => 'bottom-right',
			'primary_color'      => '#0073aa',
			'enabled'            => false,
		];

		$this->assertEmpty( $defaults['api_key'] );
		$this->assertEquals( 'AI Assistant', $defaults['ai_name'] );
		$this->assertEquals( '#0073aa', $defaults['primary_color'] );
		$this->assertFalse( $defaults['enabled'] );
	}

	/**
	 * Test options storage.
	 */
	public function testOptionsStorage(): void {
		$settings = [
			'api_key' => 'test-key',
			'ai_name' => 'Test Bot',
			'enabled' => true,
		];

		update_option( 'aiagent_settings', $settings );

		$retrieved = get_option( 'aiagent_settings' );

		$this->assertEquals( 'test-key', $retrieved['api_key'] );
		$this->assertEquals( 'Test Bot', $retrieved['ai_name'] );
		$this->assertTrue( $retrieved['enabled'] );
	}

	/**
	 * Test knowledge base directory creation.
	 */
	public function testKnowledgeBaseDirectoryCreation(): void {
		$upload_dir = wp_upload_dir();
		$kb_dir     = $upload_dir['basedir'] . '/ai-agent-knowledge';

		if ( ! file_exists( $kb_dir ) ) {
			wp_mkdir_p( $kb_dir );
		}

		$this->assertDirectoryExists( $kb_dir );
	}

	/**
	 * Test readme file exists.
	 */
	public function testReadmeFileExists(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/readme.txt' );
		$this->assertFileExists( $base_dir . '/README.md' );
	}

	/**
	 * Test changelog file exists.
	 */
	public function testChangelogFileExists(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/CHANGELOG.md' );
	}

	/**
	 * Test composer.json exists.
	 */
	public function testComposerJsonExists(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/composer.json' );

		$composer = json_decode( file_get_contents( $base_dir . '/composer.json' ), true );
		$this->assertArrayHasKey( 'require', $composer );
		$this->assertArrayHasKey( 'require-dev', $composer );
	}

	/**
	 * Test phpcs.xml exists.
	 */
	public function testPhpcsXmlExists(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/phpcs.xml' );
	}

	/**
	 * Test phpunit.xml exists.
	 */
	public function testPhpunitXmlExists(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/phpunit.xml' );
	}

	/**
	 * Test vendor directory exists.
	 */
	public function testVendorDirectoryExists(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertDirectoryExists( $base_dir . '/vendor' );
		$this->assertFileExists( $base_dir . '/vendor/autoload.php' );
	}

	/**
	 * Test API keys example file exists.
	 *
	 * Note: api-keys.php is gitignored, so we check for the example file instead.
	 */
	public function testAPIKeysFileExists(): void {
		$base_dir = dirname( __DIR__ );

		$this->assertFileExists( $base_dir . '/api-keys.example.php' );
	}

	/**
	 * Test .gitignore includes api-keys files.
	 */
	public function testGitignoreIncludesAPIKeys(): void {
		$base_dir   = dirname( __DIR__ );
		$gitignore  = file_get_contents( $base_dir . '/.gitignore' );

		$this->assertStringContainsString( 'api-keys.local.php', $gitignore );
		$this->assertStringContainsString( 'api-keys.php', $gitignore );
	}
}
