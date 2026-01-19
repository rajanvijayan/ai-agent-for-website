<?php
/**
 * Spam Manager Tests
 *
 * @package AI_Agent_For_Website
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for AIAGENT_Spam_Manager.
 */
class SpamManagerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Reset spam settings.
		update_option(
			'aiagent_spam_settings',
			array(
				'enabled'            => true,
				'spam_threshold'     => 3,
				'block_duration'     => 24,
				'auto_block_enabled' => true,
				'log_spam_attempts'  => true,
			)
		);
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		parent::tearDown();
		delete_option( 'aiagent_spam_settings' );
	}

	/**
	 * Test get_settings returns defaults.
	 */
	public function test_get_settings_returns_defaults() {
		delete_option( 'aiagent_spam_settings' );

		$settings = AIAGENT_Spam_Manager::get_settings();

		$this->assertTrue( $settings['enabled'] );
		$this->assertEquals( 3, $settings['spam_threshold'] );
		$this->assertEquals( 24, $settings['block_duration'] );
		$this->assertTrue( $settings['auto_block_enabled'] );
		$this->assertTrue( $settings['log_spam_attempts'] );
	}

	/**
	 * Test get_settings merges with saved settings.
	 */
	public function test_get_settings_merges_saved() {
		update_option(
			'aiagent_spam_settings',
			array(
				'enabled'        => false,
				'spam_threshold' => 5,
			)
		);

		$settings = AIAGENT_Spam_Manager::get_settings();

		$this->assertFalse( $settings['enabled'] );
		$this->assertEquals( 5, $settings['spam_threshold'] );
		// Defaults still present.
		$this->assertEquals( 24, $settings['block_duration'] );
	}

	/**
	 * Test update_settings saves correctly.
	 */
	public function test_update_settings() {
		$new_settings = array(
			'enabled'        => false,
			'spam_threshold' => 10,
			'block_duration' => 48,
		);

		$result = AIAGENT_Spam_Manager::update_settings( $new_settings );

		$this->assertTrue( $result );

		$saved = get_option( 'aiagent_spam_settings' );
		$this->assertFalse( $saved['enabled'] );
		$this->assertEquals( 10, $saved['spam_threshold'] );
		$this->assertEquals( 48, $saved['block_duration'] );
	}

	/**
	 * Test basic_spam_check detects URL spam.
	 */
	public function test_basic_spam_check_detects_urls() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Check out https://spam-website.com for great deals!' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Contains suspicious URL', $result['reason'] );
		$this->assertEquals( 'basic', $result['method'] );
	}

	/**
	 * Test basic_spam_check detects multiple URLs.
	 */
	public function test_basic_spam_check_detects_multiple_urls() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Visit https://site1.com and https://site2.com' );

		$this->assertTrue( $result['is_spam'] );
		// First pattern match wins - could be "Contains suspicious URL" or "Contains multiple URLs".
		$this->assertContains( $result['reason'], array( 'Contains suspicious URL', 'Contains multiple URLs' ) );
	}

	/**
	 * Test basic_spam_check detects crypto spam.
	 */
	public function test_basic_spam_check_detects_crypto() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Buy Bitcoin now and get rich quick!' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Cryptocurrency spam', $result['reason'] );
	}

	/**
	 * Test basic_spam_check detects adult content.
	 */
	public function test_basic_spam_check_detects_adult() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Visit our adult dating site for hookups' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Adult content spam', $result['reason'] );
	}

	/**
	 * Test basic_spam_check detects money scam.
	 */
	public function test_basic_spam_check_detects_money_scam() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Work from home and earn $1000 daily!' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Money scam spam', $result['reason'] );
	}

	/**
	 * Test basic_spam_check detects repetitive characters.
	 */
	public function test_basic_spam_check_detects_repetitive() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Helloooooooooooooooooooo there!' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Repetitive characters', $result['reason'] );
	}

	/**
	 * Test basic_spam_check detects excessive caps.
	 */
	public function test_basic_spam_check_detects_caps() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'THIS IS A VERY LOUD AND ANNOYING MESSAGE!!' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Excessive caps (potential abuse)', $result['reason'] );
	}

	/**
	 * Test basic_spam_check detects phone numbers.
	 */
	public function test_basic_spam_check_detects_phone() {
		// Use a format that matches the regex pattern: +X-XXX-XXX-XXXX or XXX.XXX.XXXX.
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Call me at +1-555-123-4567 for deals' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Contains phone number', $result['reason'] );
	}

	/**
	 * Test basic_spam_check detects email addresses.
	 */
	public function test_basic_spam_check_detects_email() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Contact me at spam@example.com' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Contains email address', $result['reason'] );
	}

	/**
	 * Test basic_spam_check detects too long messages.
	 */
	public function test_basic_spam_check_detects_long_message() {
		$long_message = str_repeat( 'a', 2500 );
		$result       = AIAGENT_Spam_Manager::basic_spam_check( $long_message );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Message too long', $result['reason'] );
	}

	/**
	 * Test basic_spam_check allows legitimate messages.
	 */
	public function test_basic_spam_check_allows_legit() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'What are your business hours?' );

		$this->assertFalse( $result['is_spam'] );
		$this->assertEquals( '', $result['reason'] );
	}

	/**
	 * Test basic_spam_check allows product questions.
	 */
	public function test_basic_spam_check_allows_product_questions() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Do you have this product in blue color?' );

		$this->assertFalse( $result['is_spam'] );
		$this->assertEquals( '', $result['reason'] );
	}

	/**
	 * Test basic_spam_check allows support requests.
	 */
	public function test_basic_spam_check_allows_support() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'I need help with my order. It has not arrived yet.' );

		$this->assertFalse( $result['is_spam'] );
		$this->assertEquals( '', $result['reason'] );
	}

	/**
	 * Test is_user_blocked returns false for non-blocked user.
	 */
	public function test_is_user_blocked_returns_false() {
		$result = AIAGENT_Spam_Manager::is_user_blocked( 'test-session-123', null, null );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_client_ip handles server variables.
	 */
	public function test_get_client_ip_remote_addr() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

		$ip = AIAGENT_Spam_Manager::get_client_ip();

		$this->assertEquals( '192.168.1.1', $ip );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Test get_client_ip handles forwarded IP.
	 */
	public function test_get_client_ip_forwarded() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 192.168.1.1';

		$ip = AIAGENT_Spam_Manager::get_client_ip();

		$this->assertEquals( '10.0.0.1', $ip );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * Test get_client_ip handles client IP.
	 */
	public function test_get_client_ip_client() {
		$_SERVER['HTTP_CLIENT_IP'] = '172.16.0.1';

		$ip = AIAGENT_Spam_Manager::get_client_ip();

		$this->assertEquals( '172.16.0.1', $ip );

		unset( $_SERVER['HTTP_CLIENT_IP'] );
	}

	/**
	 * Test get_client_ip returns empty for invalid IP.
	 */
	public function test_get_client_ip_invalid() {
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';

		$ip = AIAGENT_Spam_Manager::get_client_ip();

		$this->assertEquals( '', $ip );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Test validate_message_with_ai falls back to basic check.
	 */
	public function test_validate_message_falls_back_to_basic() {
		// No API key configured, should fall back.
		delete_option( 'aiagent_settings' );

		$result = AIAGENT_Spam_Manager::validate_message_with_ai( 'Check out https://spam.com' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'basic', $result['method'] );
	}

	/**
	 * Test validate_message_with_ai with valid message.
	 */
	public function test_validate_message_valid() {
		delete_option( 'aiagent_settings' );

		$result = AIAGENT_Spam_Manager::validate_message_with_ai( 'Hello, how can I contact support?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test get_statistics returns array.
	 */
	public function test_get_statistics_returns_array() {
		$stats = AIAGENT_Spam_Manager::get_statistics();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total_blocked', $stats );
		$this->assertArrayHasKey( 'total_spam_attempts', $stats );
		$this->assertArrayHasKey( 'today_spam', $stats );
		$this->assertArrayHasKey( 'flagged_users', $stats );
	}

	/**
	 * Test get_blocked_users returns array.
	 */
	public function test_get_blocked_users_returns_array() {
		$users = AIAGENT_Spam_Manager::get_blocked_users( 10, 0, false );

		$this->assertIsArray( $users );
	}

	/**
	 * Test get_spam_logs returns array.
	 */
	public function test_get_spam_logs_returns_array() {
		$logs = AIAGENT_Spam_Manager::get_spam_logs( 10, 0 );

		$this->assertIsArray( $logs );
	}

	/**
	 * Test spam threshold constant.
	 */
	public function test_default_spam_threshold() {
		$this->assertEquals( 3, AIAGENT_Spam_Manager::DEFAULT_SPAM_THRESHOLD );
	}

	/**
	 * Test block duration constant.
	 */
	public function test_default_block_duration() {
		$this->assertEquals( 24, AIAGENT_Spam_Manager::DEFAULT_BLOCK_DURATION );
	}

	/**
	 * Test spam patterns detect ETH addresses.
	 */
	public function test_basic_spam_check_detects_ethereum() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Send ETH to this address for airdrops' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Cryptocurrency spam', $result['reason'] );
	}

	/**
	 * Test spam patterns detect NFT spam.
	 */
	public function test_basic_spam_check_detects_nft() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Buy this exclusive NFT collection now!' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Cryptocurrency spam', $result['reason'] );
	}

	/**
	 * Test spam patterns detect lottery scams.
	 */
	public function test_basic_spam_check_detects_lottery() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Congratulations! You are a winner of our lottery!' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Money scam spam', $result['reason'] );
	}

	/**
	 * Test international phone numbers detection.
	 */
	public function test_basic_spam_check_detects_international_phone() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Call +1-555-123-4567 now' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Contains phone number', $result['reason'] );
	}

	/**
	 * Test message with parentheses in phone number.
	 */
	public function test_basic_spam_check_phone_with_parens() {
		// Pattern: +?\d{1,3}[-.\s]?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}.
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Contact us at 1-(555)-123-4567' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Contains phone number', $result['reason'] );
	}

	/**
	 * Test message with dots between phone digits.
	 */
	public function test_basic_spam_check_phone_with_dots() {
		// Pattern requires specific formats.
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Call +1.555.123.4567 for info' );

		$this->assertTrue( $result['is_spam'] );
		$this->assertEquals( 'Contains phone number', $result['reason'] );
	}

	/**
	 * Test empty message is not spam.
	 */
	public function test_basic_spam_check_empty_message() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( '' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test whitespace only message is not spam.
	 */
	public function test_basic_spam_check_whitespace_only() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( '   ' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test normal greeting is not spam.
	 */
	public function test_basic_spam_check_greeting() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Hi there! How are you doing today?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test question about pricing is not spam.
	 */
	public function test_basic_spam_check_pricing_question() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'What is the price of your premium plan?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test complaint message is not spam.
	 */
	public function test_basic_spam_check_complaint() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'I am not happy with my recent purchase. The product arrived damaged.' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test technical question is not spam.
	 */
	public function test_basic_spam_check_technical() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'How do I reset my password on your platform?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test shipping question is not spam.
	 */
	public function test_basic_spam_check_shipping() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Do you ship to Canada? What are the shipping costs?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test refund request is not spam.
	 */
	public function test_basic_spam_check_refund() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'I would like to request a refund for my order.' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test mixed case URL detection.
	 */
	public function test_basic_spam_check_mixed_case_url() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Visit HTTPS://SPAM-SITE.COM now!' );

		$this->assertTrue( $result['is_spam'] );
	}

	/**
	 * Test message at boundary length.
	 */
	public function test_basic_spam_check_boundary_length() {
		// Test that the length check works - 2000 chars is allowed (>2000 is spam).
		// Use varied text to avoid triggering repetitive pattern.
		$message = str_repeat( 'Hello world. ', 154 ); // ~2002 chars, but let's test exactly.
		$message = substr( $message, 0, 2000 );
		$result  = AIAGENT_Spam_Manager::basic_spam_check( $message );

		// Under or at 2000 chars should NOT trigger "Message too long".
		// But might trigger other patterns, so just check it's not length-related.
		if ( $result['is_spam'] ) {
			$this->assertNotEquals( 'Message too long', $result['reason'] );
		} else {
			$this->assertFalse( $result['is_spam'] );
		}
	}

	/**
	 * Test message just over boundary.
	 */
	public function test_basic_spam_check_over_boundary() {
		// 2001 characters should be flagged.
		$message = str_repeat( 'a', 2001 );
		$result  = AIAGENT_Spam_Manager::basic_spam_check( $message );

		$this->assertTrue( $result['is_spam'] );
	}

	/**
	 * Test confidence score range.
	 */
	public function test_basic_spam_check_confidence_range() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Check out https://spam.com' );

		$this->assertGreaterThanOrEqual( 0.0, $result['confidence'] );
		$this->assertLessThanOrEqual( 1.0, $result['confidence'] );
	}

	/**
	 * Test method field is present.
	 */
	public function test_basic_spam_check_has_method() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Hello world' );

		$this->assertArrayHasKey( 'method', $result );
		$this->assertEquals( 'basic', $result['method'] );
	}

	/**
	 * Test message with legitimate business hours question.
	 */
	public function test_legit_business_hours() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'What are your business hours on weekends?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test message with product availability question.
	 */
	public function test_legit_product_availability() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Is this item available in stock?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test message with warranty question.
	 */
	public function test_legit_warranty() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Does this product come with a warranty?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test message with return policy question.
	 */
	public function test_legit_return_policy() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'What is your return policy?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test message with delivery time question.
	 */
	public function test_legit_delivery_time() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'How long does delivery take to my area?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test message asking to speak to human.
	 */
	public function test_legit_human_request() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Can I speak to a human agent please?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test message with feature question.
	 */
	public function test_legit_feature_question() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Does your software support PDF export?' );

		$this->assertFalse( $result['is_spam'] );
	}

	/**
	 * Test message with integration question.
	 */
	public function test_legit_integration_question() {
		$result = AIAGENT_Spam_Manager::basic_spam_check( 'Does this integrate with Slack?' );

		$this->assertFalse( $result['is_spam'] );
	}
}

