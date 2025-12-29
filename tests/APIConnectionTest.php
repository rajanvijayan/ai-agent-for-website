<?php
/**
 * API Connection Test
 *
 * Tests API key validation and connection functionality.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for API connection functionality.
 */
class APIConnectionTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_mocks();
	}

	/**
	 * Test that API tester class exists.
	 */
	public function testAPITesterClassExists(): void {
		$this->assertTrue( class_exists( 'AIAgent_API_Tester' ) );
	}

	/**
	 * Test Groq API key format validation - valid key.
	 */
	public function testGroqAPIKeyFormatValid(): void {
		$valid_key = 'gsk_1234567890abcdefghijklmnop';
		$result    = AIAgent_API_Tester::validate_api_key_format( $valid_key, 'groq' );
		$this->assertTrue( $result );
	}

	/**
	 * Test Groq API key format validation - empty key.
	 */
	public function testGroqAPIKeyFormatEmpty(): void {
		$result = AIAgent_API_Tester::validate_api_key_format( '', 'groq' );
		$this->assertFalse( $result );
	}

	/**
	 * Test Groq API key format validation - long key without prefix.
	 */
	public function testGroqAPIKeyFormatLongKeyWithoutPrefix(): void {
		$key    = 'abcdefghijklmnopqrstuvwxyz';
		$result = AIAgent_API_Tester::validate_api_key_format( $key, 'groq' );
		$this->assertTrue( $result ); // Accepts long keys.
	}

	/**
	 * Test OpenAI API key format validation.
	 */
	public function testOpenAIAPIKeyFormat(): void {
		$valid_key   = 'sk-1234567890abcdefghijklmnop';
		$invalid_key = 'invalid-key';

		$this->assertTrue( AIAgent_API_Tester::validate_api_key_format( $valid_key, 'openai' ) );
		$this->assertFalse( AIAgent_API_Tester::validate_api_key_format( $invalid_key, 'openai' ) );
	}

	/**
	 * Test Anthropic API key format validation.
	 */
	public function testAnthropicAPIKeyFormat(): void {
		$valid_key   = 'sk-ant-api03-1234567890';
		$invalid_key = 'sk-not-anthropic';

		$this->assertTrue( AIAgent_API_Tester::validate_api_key_format( $valid_key, 'anthropic' ) );
		$this->assertFalse( AIAgent_API_Tester::validate_api_key_format( $invalid_key, 'anthropic' ) );
	}

	/**
	 * Test unknown provider key validation.
	 */
	public function testUnknownProviderKeyFormat(): void {
		$valid_key = 'some-long-api-key-here';
		$short_key = 'short';

		$this->assertTrue( AIAgent_API_Tester::validate_api_key_format( $valid_key, 'unknown' ) );
		$this->assertFalse( AIAgent_API_Tester::validate_api_key_format( $short_key, 'unknown' ) );
	}

	/**
	 * Test Groq connection in test mode.
	 */
	public function testGroqConnectionTestMode(): void {
		$result = AIAgent_API_Tester::test_groq_connection( 'test-api-key' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'test mode', $result['message'] );
	}

	/**
	 * Test Groq connection without API key.
	 */
	public function testGroqConnectionWithoutKey(): void {
		// Temporarily undefine the constant for this test.
		$result = AIAgent_API_Tester::test_groq_connection( '' );

		// Without a key, should fail unless default is defined.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}

	/**
	 * Test that API key constants are defined.
	 */
	public function testAPIKeyConstantsDefined(): void {
		$this->assertTrue( defined( 'AIAGENT_GROQ_API_KEY' ) );
		$this->assertTrue( defined( 'AIAGENT_TEST_MODE' ) );
	}

	/**
	 * Test test mode is enabled for unit tests.
	 */
	public function testTestModeEnabled(): void {
		$this->assertTrue( AIAGENT_TEST_MODE );
	}

	/**
	 * Test models are returned in test mode connection.
	 */
	public function testGroqConnectionReturnsModels(): void {
		$result = AIAgent_API_Tester::test_groq_connection( 'test-key' );

		$this->assertArrayHasKey( 'models', $result );
		$this->assertIsArray( $result['models'] );
		$this->assertNotEmpty( $result['models'] );
	}

	/**
	 * Test API key storage in options.
	 */
	public function testAPIKeyStorageInOptions(): void {
		$settings = [
			'api_key' => 'test-api-key-12345',
		];

		update_option( 'aiagent_settings', $settings );

		$retrieved = get_option( 'aiagent_settings' );
		$this->assertEquals( 'test-api-key-12345', $retrieved['api_key'] );
	}

	/**
	 * Test API settings can be updated.
	 */
	public function testAPISettingsUpdate(): void {
		$initial_settings = [
			'api_key' => 'initial-key',
			'ai_name' => 'Test Bot',
		];

		update_option( 'aiagent_settings', $initial_settings );

		// Update settings.
		$updated_settings = get_option( 'aiagent_settings' );
		$updated_settings['api_key'] = 'new-api-key';
		update_option( 'aiagent_settings', $updated_settings );

		$final = get_option( 'aiagent_settings' );
		$this->assertEquals( 'new-api-key', $final['api_key'] );
		$this->assertEquals( 'Test Bot', $final['ai_name'] ); // Unchanged.
	}

	/**
	 * Test multiple API keys can be stored.
	 */
	public function testMultipleAPIKeysStorage(): void {
		$settings = [
			'api_key'         => 'groq-key',
			'openai_api_key'  => 'openai-key',
			'anthropic_key'   => 'anthropic-key',
		];

		update_option( 'aiagent_settings', $settings );

		$retrieved = get_option( 'aiagent_settings' );
		$this->assertEquals( 'groq-key', $retrieved['api_key'] );
		$this->assertEquals( 'openai-key', $retrieved['openai_api_key'] );
		$this->assertEquals( 'anthropic-key', $retrieved['anthropic_key'] );
	}

	/**
	 * Test empty API key handling.
	 */
	public function testEmptyAPIKeyHandling(): void {
		$settings = [
			'api_key' => '',
		];

		update_option( 'aiagent_settings', $settings );

		$retrieved = get_option( 'aiagent_settings' );
		$this->assertEmpty( $retrieved['api_key'] );
	}

	/**
	 * Test API key with special characters.
	 */
	public function testAPIKeySpecialCharacters(): void {
		$key = 'gsk_abc123XYZ!@#$%^&*()_+-=';

		$settings = [
			'api_key' => $key,
		];

		update_option( 'aiagent_settings', $settings );

		$retrieved = get_option( 'aiagent_settings' );
		$this->assertEquals( $key, $retrieved['api_key'] );
	}

	/**
	 * Test API key validation returns proper types.
	 */
	public function testAPIKeyValidationReturnTypes(): void {
		$result = AIAgent_API_Tester::validate_api_key_format( 'test', 'groq' );
		$this->assertIsBool( $result );
	}

	/**
	 * Test connection test returns proper structure.
	 */
	public function testConnectionTestReturnStructure(): void {
		$result = AIAgent_API_Tester::test_groq_connection( 'test-key' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertIsBool( $result['success'] );
		$this->assertIsString( $result['message'] );
	}
}

