<?php
/**
 * Chat Widget Test
 *
 * Tests for the AIAGENT_Chat_Widget class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Include required files.
require_once dirname( __DIR__ ) . '/includes/class-chat-widget.php';

/**
 * Test case for the Chat Widget class.
 */
class ChatWidgetTest extends TestCase {

	/**
	 * Chat widget instance.
	 *
	 * @var AIAGENT_Chat_Widget
	 */
	private $widget;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_mocks();

		// Set up default settings.
		update_option(
			'aiagent_settings',
			[
				'enabled'            => true,
				'ai_name'            => 'Test Bot',
				'widget_position'    => 'bottom-right',
				'primary_color'      => '#0073aa',
				'avatar_url'         => '',
				'require_user_info'  => true,
				'require_phone'      => false,
				'show_powered_by'    => true,
				'consent_ai_enabled' => true,
				'consent_ai_text'    => 'I agree to AI assistance',
			]
		);

		$this->widget = new AIAGENT_Chat_Widget();
	}

	/**
	 * Test that the class exists.
	 */
	public function testClassExists(): void {
		$this->assertTrue( class_exists( 'AIAGENT_Chat_Widget' ) );
	}

	/**
	 * Test that the widget can be instantiated.
	 */
	public function testCanBeInstantiated(): void {
		$this->assertInstanceOf( AIAGENT_Chat_Widget::class, $this->widget );
	}

	/**
	 * Test render_floating returns string when enabled.
	 */
	public function testRenderFloatingReturnsString(): void {
		$html = $this->widget->render_floating();

		$this->assertIsString( $html );
		$this->assertNotEmpty( $html );
	}

	/**
	 * Test render_floating returns empty when disabled.
	 */
	public function testRenderFloatingReturnsEmptyWhenDisabled(): void {
		update_option(
			'aiagent_settings',
			[
				'enabled' => false,
			]
		);

		$widget = new AIAGENT_Chat_Widget();
		$html   = $widget->render_floating();

		$this->assertEmpty( $html );
	}

	/**
	 * Test render_floating contains widget container.
	 */
	public function testRenderFloatingContainsContainer(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'id="aiagent-chat-widget"', $html );
		$this->assertStringContainsString( 'aiagent-widget', $html );
	}

	/**
	 * Test render_floating contains AI name.
	 */
	public function testRenderFloatingContainsAIName(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'Test Bot', $html );
	}

	/**
	 * Test render_floating contains position class.
	 */
	public function testRenderFloatingContainsPositionClass(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-position-bottom-right', $html );
	}

	/**
	 * Test render_floating contains primary color.
	 */
	public function testRenderFloatingContainsPrimaryColor(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( '#0073aa', $html );
	}

	/**
	 * Test render_floating contains toggle button.
	 */
	public function testRenderFloatingContainsToggleButton(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-toggle', $html );
	}

	/**
	 * Test render_floating contains chat window.
	 */
	public function testRenderFloatingContainsChatWindow(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-window', $html );
	}

	/**
	 * Test render_floating contains header.
	 */
	public function testRenderFloatingContainsHeader(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-header', $html );
	}

	/**
	 * Test render_floating contains messages container.
	 */
	public function testRenderFloatingContainsMessagesContainer(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-messages', $html );
	}

	/**
	 * Test render_floating contains input area.
	 */
	public function testRenderFloatingContainsInputArea(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-input-area', $html );
		$this->assertStringContainsString( 'aiagent-input', $html );
		$this->assertStringContainsString( 'aiagent-send', $html );
	}

	/**
	 * Test render_floating contains user form when required.
	 */
	public function testRenderFloatingContainsUserForm(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-user-form', $html );
		$this->assertStringContainsString( 'aiagent-user-info-form', $html );
	}

	/**
	 * Test render_floating contains rating modal.
	 */
	public function testRenderFloatingContainsRatingModal(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-rating-modal', $html );
		$this->assertStringContainsString( 'aiagent-star', $html );
	}

	/**
	 * Test render_floating contains calendar modal.
	 */
	public function testRenderFloatingContainsCalendarModal(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-calendar-modal', $html );
	}

	/**
	 * Test render_floating contains consent checkbox.
	 */
	public function testRenderFloatingContainsConsentCheckbox(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'consent_ai', $html );
		$this->assertStringContainsString( 'I agree to AI assistance', $html );
	}

	/**
	 * Test render_floating contains powered by when enabled.
	 */
	public function testRenderFloatingContainsPoweredBy(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-powered', $html );
	}

	/**
	 * Test render_inline returns string.
	 */
	public function testRenderInlineReturnsString(): void {
		$html = $this->widget->render_inline( [] );

		$this->assertIsString( $html );
		$this->assertNotEmpty( $html );
	}

	/**
	 * Test render_inline with custom attributes.
	 */
	public function testRenderInlineWithCustomAttributes(): void {
		$atts = [
			'height' => '600px',
			'width'  => '400px',
		];

		$html = $this->widget->render_inline( $atts );

		$this->assertStringContainsString( '600px', $html );
		$this->assertStringContainsString( '400px', $html );
	}

	/**
	 * Test render_inline contains inline class.
	 */
	public function testRenderInlineContainsInlineClass(): void {
		$html = $this->widget->render_inline( [] );

		$this->assertStringContainsString( 'aiagent-inline-chat', $html );
	}

	/**
	 * Test render_inline contains all required elements.
	 */
	public function testRenderInlineContainsRequiredElements(): void {
		$html = $this->widget->render_inline( [] );

		$this->assertStringContainsString( 'aiagent-header', $html );
		$this->assertStringContainsString( 'aiagent-messages', $html );
		$this->assertStringContainsString( 'aiagent-input-area', $html );
		$this->assertStringContainsString( 'aiagent-form', $html );
	}

	/**
	 * Test different widget positions.
	 */
	public function testDifferentWidgetPositions(): void {
		$positions = [ 'bottom-left', 'bottom-right', 'top-left', 'top-right' ];

		foreach ( $positions as $position ) {
			update_option(
				'aiagent_settings',
				[
					'enabled'         => true,
					'widget_position' => $position,
				]
			);

			$widget = new AIAGENT_Chat_Widget();
			$html   = $widget->render_floating();

			$this->assertStringContainsString( "aiagent-position-$position", $html );
		}
	}

	/**
	 * Test phone field appears when required.
	 */
	public function testPhoneFieldWhenRequired(): void {
		update_option(
			'aiagent_settings',
			[
				'enabled'       => true,
				'require_phone' => true,
			]
		);

		$widget = new AIAGENT_Chat_Widget();
		$html   = $widget->render_floating();

		$this->assertStringContainsString( 'user_phone', $html );
	}

	/**
	 * Test avatar displays when set.
	 */
	public function testAvatarDisplays(): void {
		update_option(
			'aiagent_settings',
			[
				'enabled'    => true,
				'avatar_url' => 'https://example.com/avatar.png',
			]
		);

		$widget = new AIAGENT_Chat_Widget();
		$html   = $widget->render_floating();

		$this->assertStringContainsString( 'example.com/avatar.png', $html );
		$this->assertStringContainsString( '<img', $html );
	}

	/**
	 * Test live agent elements are present.
	 */
	public function testLiveAgentElementsPresent(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-live-agent-banner', $html );
		$this->assertStringContainsString( 'aiagent-live-agent-status', $html );
		$this->assertStringContainsString( 'aiagent-connect-agent-btn', $html );
	}

	/**
	 * Test new chat button is present.
	 */
	public function testNewChatButtonPresent(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-new-chat', $html );
	}

	/**
	 * Test close chat button is present.
	 */
	public function testCloseChatButtonPresent(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aiagent-close-chat', $html );
	}

	/**
	 * Test SVG icons are present.
	 */
	public function testSVGIconsPresent(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( 'viewBox', $html );
	}

	/**
	 * Test form has proper structure.
	 */
	public function testFormStructure(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( '<button', $html );
	}

	/**
	 * Test accessibility attributes present.
	 */
	public function testAccessibilityAttributes(): void {
		$html = $this->widget->render_floating();

		$this->assertStringContainsString( 'aria-label', $html );
		$this->assertStringContainsString( 'title=', $html );
	}
}

