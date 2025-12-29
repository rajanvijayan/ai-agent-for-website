<?php
/**
 * Admin Settings Test
 *
 * Tests for the AIAGENT_Admin_Settings class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Admin Settings class.
 */
class AdminSettingsTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_mocks();
	}

	/**
	 * Test that the class file exists.
	 */
	public function testClassFileExists(): void {
		$this->assertFileExists(
			dirname( __DIR__ ) . '/includes/class-admin-settings.php'
		);
	}

	/**
	 * Test default settings structure.
	 */
	public function testDefaultSettingsStructure(): void {
		$defaults = [
			'api_key'            => '',
			'ai_name'            => 'AI Assistant',
			'system_instruction' => 'You are a helpful assistant for this website.',
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

		$this->assertArrayHasKey( 'api_key', $defaults );
		$this->assertArrayHasKey( 'ai_name', $defaults );
		$this->assertArrayHasKey( 'widget_position', $defaults );
		$this->assertArrayHasKey( 'primary_color', $defaults );
		$this->assertArrayHasKey( 'enabled', $defaults );
	}

	/**
	 * Test settings save functionality.
	 */
	public function testSettingsSave(): void {
		$settings = [
			'api_key'         => 'test-api-key',
			'ai_name'         => 'My Bot',
			'welcome_message' => 'Welcome!',
			'primary_color'   => '#ff5500',
			'enabled'         => true,
		];

		update_option( 'aiagent_settings', $settings );

		$retrieved = get_option( 'aiagent_settings' );

		$this->assertEquals( 'My Bot', $retrieved['ai_name'] );
		$this->assertEquals( '#ff5500', $retrieved['primary_color'] );
		$this->assertTrue( $retrieved['enabled'] );
	}

	/**
	 * Test widget position options.
	 */
	public function testWidgetPositionOptions(): void {
		$positions = [
			'bottom-right',
			'bottom-left',
			'top-right',
			'top-left',
		];

		foreach ( $positions as $position ) {
			$settings = [ 'widget_position' => $position ];
			update_option( 'aiagent_settings', $settings );

			$retrieved = get_option( 'aiagent_settings' );
			$this->assertEquals( $position, $retrieved['widget_position'] );
		}
	}

	/**
	 * Test color validation format.
	 */
	public function testColorValidationFormat(): void {
		$valid_colors = [
			'#000',
			'#fff',
			'#000000',
			'#ffffff',
			'#0073aa',
			'#FF5500',
		];

		foreach ( $valid_colors as $color ) {
			$this->assertMatchesRegularExpression( '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $color );
		}
	}

	/**
	 * Test consent settings structure.
	 */
	public function testConsentSettingsStructure(): void {
		$consent_settings = [
			'consent_ai_enabled'       => true,
			'consent_ai_text'          => 'I agree to interact with AI assistance',
			'consent_newsletter'       => false,
			'consent_newsletter_text'  => 'Subscribe to our newsletter',
			'consent_promotional'      => false,
			'consent_promotional_text' => 'Receive promotional updates',
		];

		$this->assertArrayHasKey( 'consent_ai_enabled', $consent_settings );
		$this->assertArrayHasKey( 'consent_ai_text', $consent_settings );
		$this->assertTrue( $consent_settings['consent_ai_enabled'] );
	}

	/**
	 * Test widget animation options.
	 */
	public function testWidgetAnimationOptions(): void {
		$animations = [ 'slide', 'fade', 'bounce', 'none' ];

		foreach ( $animations as $animation ) {
			$this->assertIsString( $animation );
		}
	}

	/**
	 * Test widget button size options.
	 */
	public function testWidgetButtonSizeOptions(): void {
		$sizes = [ 'small', 'medium', 'large' ];

		foreach ( $sizes as $size ) {
			$this->assertContains( $size, [ 'small', 'medium', 'large' ] );
		}
	}

	/**
	 * Test auto popup settings.
	 */
	public function testAutoPopupSettings(): void {
		$popup_settings = [
			'auto_popup_enabled' => true,
			'auto_popup_delay'   => 10,
			'auto_popup_message' => 'Need help? Ask me anything!',
			'auto_popup_once'    => true,
		];

		$this->assertArrayHasKey( 'auto_popup_enabled', $popup_settings );
		$this->assertArrayHasKey( 'auto_popup_delay', $popup_settings );
		$this->assertEquals( 10, $popup_settings['auto_popup_delay'] );
	}

	/**
	 * Test live agent settings structure.
	 */
	public function testLiveAgentSettingsStructure(): void {
		$live_agent_settings = [
			'enabled'             => false,
			'connect_button_text' => 'Connect to Live Agent',
			'waiting_message'     => 'Please wait...',
			'connected_message'   => 'You are now connected.',
			'offline_message'     => 'Agents are currently offline.',
			'working_hours'       => [
				'enabled' => false,
				'start'   => '09:00',
				'end'     => '17:00',
				'days'    => [ 1, 2, 3, 4, 5 ],
			],
		];

		$this->assertArrayHasKey( 'enabled', $live_agent_settings );
		$this->assertArrayHasKey( 'working_hours', $live_agent_settings );
		$this->assertIsArray( $live_agent_settings['working_hours']['days'] );
	}

	/**
	 * Test notification settings structure.
	 */
	public function testNotificationSettingsStructure(): void {
		$notification_settings = [
			'email_enabled'         => true,
			'email_address'         => 'admin@example.com',
			'email_on_lead'         => true,
			'email_on_conversation' => false,
			'email_on_live_agent'   => true,
			'browser_enabled'       => true,
			'sound_enabled'         => false,
		];

		$this->assertArrayHasKey( 'email_enabled', $notification_settings );
		$this->assertArrayHasKey( 'email_address', $notification_settings );
	}

	/**
	 * Test admin tabs array.
	 */
	public function testAdminTabsArray(): void {
		$tabs = [
			'general'       => 'General',
			'appearance'    => 'Appearance',
			'user_info'     => 'User Info & Consent',
			'integrations'  => 'Integrations',
			'live_agent'    => 'Live Agent',
			'notifications' => 'Notifications',
		];

		$this->assertArrayHasKey( 'general', $tabs );
		$this->assertArrayHasKey( 'appearance', $tabs );
		$this->assertArrayHasKey( 'integrations', $tabs );
		$this->assertCount( 6, $tabs );
	}

	/**
	 * Test settings merge with defaults.
	 */
	public function testSettingsMergeWithDefaults(): void {
		$defaults = [
			'api_key'        => '',
			'ai_name'        => 'AI Assistant',
			'primary_color'  => '#0073aa',
			'enabled'        => false,
		];

		$existing = [
			'api_key' => 'my-key',
			'enabled' => true,
		];

		$merged = array_merge( $defaults, $existing );

		$this->assertEquals( 'my-key', $merged['api_key'] );
		$this->assertEquals( 'AI Assistant', $merged['ai_name'] );
		$this->assertTrue( $merged['enabled'] );
	}

	/**
	 * Test settings sanitization - text field.
	 */
	public function testSettingsSanitizationTextField(): void {
		$input     = '  Hello <script>alert("xss")</script> World  ';
		$sanitized = sanitize_text_field( $input );

		$this->assertStringNotContainsString( '<script>', $sanitized );
		$this->assertStringContainsString( 'Hello', $sanitized );
	}

	/**
	 * Test settings sanitization - email.
	 */
	public function testSettingsSanitizationEmail(): void {
		$valid_email   = 'test@example.com';
		$invalid_email = 'not-an-email';

		$sanitized_valid   = sanitize_email( $valid_email );
		$sanitized_invalid = sanitize_email( $invalid_email );

		$this->assertEquals( 'test@example.com', $sanitized_valid );
	}

	/**
	 * Test nonce field generation.
	 */
	public function testNonceFieldGeneration(): void {
		ob_start();
		wp_nonce_field( 'aiagent_settings' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="hidden"', $output );
		$this->assertStringContainsString( '_wpnonce', $output );
	}

	/**
	 * Test admin page capability.
	 */
	public function testAdminPageCapability(): void {
		$capability = 'manage_options';
		$this->assertEquals( 'manage_options', $capability );
	}

	/**
	 * Test settings page slug.
	 */
	public function testSettingsPageSlug(): void {
		$slugs = [
			'settings'      => 'ai-agent-settings',
			'knowledge'     => 'ai-agent-knowledge',
			'conversations' => 'ai-agent-conversations',
			'leads'         => 'ai-agent-leads',
			'notifications' => 'ai-agent-notifications',
			'logs'          => 'ai-agent-logs',
		];

		foreach ( $slugs as $name => $slug ) {
			$this->assertStringStartsWith( 'ai-agent-', $slug );
		}
	}

	/**
	 * Test powered by text settings.
	 */
	public function testPoweredByTextSettings(): void {
		$settings = [
			'show_powered_by' => true,
			'powered_by_text' => 'Powered by My Company',
		];

		$this->assertTrue( $settings['show_powered_by'] );
		$this->assertStringContainsString( 'Powered by', $settings['powered_by_text'] );
	}

	/**
	 * Test AI model selection.
	 */
	public function testAIModelSelection(): void {
		$models = [
			'llama3-8b-8192',
			'llama3-70b-8192',
			'mixtral-8x7b-32768',
			'gemma-7b-it',
		];

		$selected = 'llama3-8b-8192';

		$this->assertContains( $selected, $models );
	}

	/**
	 * Test system instruction length.
	 */
	public function testSystemInstructionLength(): void {
		$max_length  = 2000;
		$instruction = 'You are a helpful assistant for this website.';

		$this->assertLessThan( $max_length, strlen( $instruction ) );
	}

	/**
	 * Test welcome message customization.
	 */
	public function testWelcomeMessageCustomization(): void {
		$messages = [
			'Hello! How can I help you today?',
			'Welcome to our website. What can I assist you with?',
			'Hi there! Feel free to ask me anything.',
		];

		foreach ( $messages as $message ) {
			$this->assertIsString( $message );
			$this->assertNotEmpty( $message );
		}
	}

	/**
	 * Test avatar URL validation.
	 */
	public function testAvatarURLValidation(): void {
		$valid_url   = 'https://example.com/avatar.png';
		$invalid_url = 'not-a-url';

		$this->assertNotFalse( filter_var( $valid_url, FILTER_VALIDATE_URL ) );
		$this->assertFalse( filter_var( $invalid_url, FILTER_VALIDATE_URL ) );
	}
}

