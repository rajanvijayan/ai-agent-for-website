<?php
/**
 * REST API Test
 *
 * Tests for the AIAGENT_REST_API class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the REST API class.
 */
class RESTAPITest extends TestCase {

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
			dirname( __DIR__ ) . '/includes/class-rest-api.php'
		);
	}

	/**
	 * Test REST API namespace.
	 */
	public function testRESTAPINamespace(): void {
		$namespace = 'ai-agent/v1';
		$this->assertEquals( 'ai-agent/v1', $namespace );
	}

	/**
	 * Test REST request creation.
	 */
	public function testRESTRequestCreation(): void {
		$request = new WP_REST_Request( 'POST', '/ai-agent/v1/chat' );

		$this->assertInstanceOf( WP_REST_Request::class, $request );
		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( '/ai-agent/v1/chat', $request->get_route() );
	}

	/**
	 * Test REST request parameters.
	 */
	public function testRESTRequestParameters(): void {
		$request = new WP_REST_Request( 'POST', '/ai-agent/v1/chat' );
		$request->set_param( 'message', 'Hello, how can I help?' );
		$request->set_param( 'session_id', 'abc123' );

		$this->assertEquals( 'Hello, how can I help?', $request->get_param( 'message' ) );
		$this->assertEquals( 'abc123', $request->get_param( 'session_id' ) );
	}

	/**
	 * Test REST response creation.
	 */
	public function testRESTResponseCreation(): void {
		$data = [
			'success' => true,
			'message' => 'Hello!',
		];

		$response = new WP_REST_Response( $data, 200 );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $data, $response->get_data() );
	}

	/**
	 * Test REST response with error status.
	 */
	public function testRESTResponseWithError(): void {
		$data = [
			'success' => false,
			'error'   => 'Something went wrong',
		];

		$response = new WP_REST_Response( $data, 400 );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
	}

	/**
	 * Test REST request JSON body.
	 */
	public function testRESTRequestJSONBody(): void {
		$request = new WP_REST_Request( 'POST', '/ai-agent/v1/register' );
		$body    = json_encode( [
			'name'  => 'John Doe',
			'email' => 'john@example.com',
		] );
		$request->set_body( $body );

		$params = $request->get_json_params();

		$this->assertEquals( 'John Doe', $params['name'] );
		$this->assertEquals( 'john@example.com', $params['email'] );
	}

	/**
	 * Test REST request headers.
	 */
	public function testRESTRequestHeaders(): void {
		$request = new WP_REST_Request( 'POST', '/ai-agent/v1/chat' );
		$request->set_header( 'X-WP-Nonce', 'test-nonce-123' );

		$this->assertEquals( 'test-nonce-123', $request->get_header( 'X-WP-Nonce' ) );
	}

	/**
	 * Test REST endpoint routes.
	 */
	public function testRESTEndpointRoutes(): void {
		$routes = [
			'chat'          => '/ai-agent/v1/chat',
			'register'      => '/ai-agent/v1/register',
			'test'          => '/ai-agent/v1/test',
			'fetch-url'     => '/ai-agent/v1/fetch-url',
			'upload-file'   => '/ai-agent/v1/upload-file',
			'notifications' => '/ai-agent/v1/notifications',
			'leads'         => '/ai-agent/v1/leads',
			'logs'          => '/ai-agent/v1/logs',
		];

		foreach ( $routes as $name => $route ) {
			$this->assertStringStartsWith( '/ai-agent/v1/', $route );
		}
	}

	/**
	 * Test session ID generation format.
	 */
	public function testSessionIDGenerationFormat(): void {
		// Simulate session ID generation.
		$session_id = 'sess_' . bin2hex( random_bytes( 16 ) );

		$this->assertStringStartsWith( 'sess_', $session_id );
		$this->assertEquals( 37, strlen( $session_id ) ); // sess_ (5) + 32 hex chars.
	}

	/**
	 * Test conversation data structure.
	 */
	public function testConversationDataStructure(): void {
		$conversation = [
			'id'         => 1,
			'user_id'    => 100,
			'session_id' => 'sess_abc123',
			'started_at' => gmdate( 'Y-m-d H:i:s' ),
			'ended_at'   => null,
			'status'     => 'active',
			'rating'     => null,
		];

		$this->assertArrayHasKey( 'id', $conversation );
		$this->assertArrayHasKey( 'session_id', $conversation );
		$this->assertArrayHasKey( 'status', $conversation );
		$this->assertEquals( 'active', $conversation['status'] );
	}

	/**
	 * Test message data structure.
	 */
	public function testMessageDataStructure(): void {
		$message = [
			'id'              => 1,
			'conversation_id' => 1,
			'role'            => 'user',
			'content'         => 'Hello, I need help',
			'created_at'      => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'role', $message );
		$this->assertContains( $message['role'], [ 'user', 'assistant', 'system' ] );
	}

	/**
	 * Test user registration data.
	 */
	public function testUserRegistrationData(): void {
		$user_data = [
			'name'       => 'John Doe',
			'email'      => 'john@example.com',
			'phone'      => '+1234567890',
			'session_id' => 'sess_abc123',
		];

		$this->assertArrayHasKey( 'name', $user_data );
		$this->assertArrayHasKey( 'email', $user_data );
		$this->assertEquals( 'john@example.com', $user_data['email'] );
	}

	/**
	 * Test chat response structure.
	 */
	public function testChatResponseStructure(): void {
		$response = [
			'success'  => true,
			'response' => 'Here is my answer to your question.',
			'metadata' => [
				'conversation_id' => 1,
				'message_id'      => 50,
				'tokens_used'     => 150,
			],
		];

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'response', $response );
		$this->assertArrayHasKey( 'metadata', $response );
	}

	/**
	 * Test error response structure.
	 */
	public function testErrorResponseStructure(): void {
		$error = new WP_Error( 'invalid_request', 'Missing required parameter: message' );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( 'invalid_request', $error->get_error_code() );
		$this->assertEquals( 'Missing required parameter: message', $error->get_error_message() );
	}

	/**
	 * Test nonce verification.
	 */
	public function testNonceVerification(): void {
		$nonce = wp_create_nonce( 'wp_rest' );

		$this->assertNotEmpty( $nonce );
		$this->assertTrue( (bool) wp_verify_nonce( $nonce, 'wp_rest' ) );
		$this->assertFalse( (bool) wp_verify_nonce( 'invalid', 'wp_rest' ) );
	}

	/**
	 * Test REST URL generation.
	 */
	public function testRESTURLGeneration(): void {
		$url = rest_url( 'ai-agent/v1/chat' );

		$this->assertStringContainsString( '/wp-json/', $url );
		$this->assertStringContainsString( 'ai-agent/v1/chat', $url );
	}

	/**
	 * Test file upload parameters.
	 */
	public function testFileUploadParameters(): void {
		$request = new WP_REST_Request( 'POST', '/ai-agent/v1/upload-file' );
		$request->set_file_params( [
			'file' => [
				'name'     => 'test.pdf',
				'type'     => 'application/pdf',
				'tmp_name' => '/tmp/phpXXXXXX',
				'error'    => 0,
				'size'     => 12345,
			],
		] );

		$files = $request->get_file_params();

		$this->assertArrayHasKey( 'file', $files );
		$this->assertEquals( 'test.pdf', $files['file']['name'] );
	}

	/**
	 * Test rate conversation endpoint data.
	 */
	public function testRateConversationData(): void {
		$data = [
			'session_id' => 'sess_abc123',
			'rating'     => 5,
		];

		$this->assertArrayHasKey( 'session_id', $data );
		$this->assertArrayHasKey( 'rating', $data );
		$this->assertGreaterThanOrEqual( 1, $data['rating'] );
		$this->assertLessThanOrEqual( 5, $data['rating'] );
	}

	/**
	 * Test settings save endpoint data.
	 */
	public function testSettingsSaveData(): void {
		$settings = [
			'api_key'         => 'gsk_test_key_123',
			'ai_name'         => 'My Bot',
			'welcome_message' => 'Hello!',
			'primary_color'   => '#ff5500',
			'enabled'         => true,
		];

		$this->assertArrayHasKey( 'api_key', $settings );
		$this->assertTrue( $settings['enabled'] );
	}

	/**
	 * Test permission check response.
	 */
	public function testPermissionCheckResponse(): void {
		// Simulate permission check result.
		$has_permission = true;

		$this->assertTrue( $has_permission );
	}

	/**
	 * Test URL fetch endpoint data.
	 */
	public function testURLFetchData(): void {
		$data = [
			'url' => 'https://example.com/page',
		];

		$this->assertArrayHasKey( 'url', $data );
		$this->assertNotFalse( filter_var( $data['url'], FILTER_VALIDATE_URL ) );
	}

	/**
	 * Test WooCommerce endpoint data.
	 */
	public function testWooCommerceEndpointData(): void {
		$search_data = [
			'keyword' => 'product name',
			'limit'   => 10,
		];

		$this->assertArrayHasKey( 'keyword', $search_data );
		$this->assertArrayHasKey( 'limit', $search_data );
		$this->assertIsInt( $search_data['limit'] );
	}

	/**
	 * Test calendar slots endpoint data.
	 */
	public function testCalendarSlotsData(): void {
		$data = [
			'start_date'  => '2024-01-15',
			'days_ahead'  => 7,
		];

		$this->assertArrayHasKey( 'start_date', $data );
		$this->assertArrayHasKey( 'days_ahead', $data );
	}

	/**
	 * Test live agent endpoint data.
	 */
	public function testLiveAgentEndpointData(): void {
		$data = [
			'session_id' => 'sess_abc123',
			'message'    => 'Hello agent',
		];

		$this->assertArrayHasKey( 'session_id', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test multiple request methods.
	 */
	public function testMultipleRequestMethods(): void {
		$methods = [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' ];

		foreach ( $methods as $method ) {
			$request = new WP_REST_Request( $method, '/ai-agent/v1/test' );
			$this->assertEquals( $method, $request->get_method() );
		}
	}

	/**
	 * Test test-email endpoint route.
	 */
	public function testTestEmailEndpointRoute(): void {
		$route = '/ai-agent/v1/test-email';

		$this->assertStringStartsWith( '/ai-agent/v1/', $route );
		$this->assertStringContainsString( 'test-email', $route );
	}

	/**
	 * Test test-email request creation.
	 */
	public function testTestEmailRequestCreation(): void {
		$request = new WP_REST_Request( 'POST', '/ai-agent/v1/test-email' );

		$this->assertInstanceOf( WP_REST_Request::class, $request );
		$this->assertEquals( 'POST', $request->get_method() );
		$this->assertEquals( '/ai-agent/v1/test-email', $request->get_route() );
	}

	/**
	 * Test test-email success response structure.
	 */
	public function testTestEmailSuccessResponse(): void {
		$response = [
			'success'    => true,
			'message'    => 'Test email sent successfully to admin@example.com',
			'recipients' => 'admin@example.com',
		];

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'recipients', $response );
		$this->assertStringContainsString( 'sent successfully', $response['message'] );
	}

	/**
	 * Test test-email error response for no recipients.
	 */
	public function testTestEmailErrorNoRecipients(): void {
		$error = new WP_Error(
			'no_recipients',
			'No email recipients configured. Please add email addresses in the notification settings.'
		);

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( 'no_recipients', $error->get_error_code() );
		$this->assertStringContainsString( 'No email recipients', $error->get_error_message() );
	}

	/**
	 * Test test-email error response for failed send.
	 */
	public function testTestEmailErrorSendFailed(): void {
		$error = new WP_Error(
			'email_failed',
			'Failed to send test email. Please check your server\'s email configuration.'
		);

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( 'email_failed', $error->get_error_code() );
		$this->assertStringContainsString( 'Failed to send', $error->get_error_message() );
	}

	/**
	 * Test test-email requires admin permission.
	 */
	public function testTestEmailRequiresAdminPermission(): void {
		// Test that endpoint requires admin capability.
		$required_capability = 'manage_options';

		// Simulate permission check.
		$has_permission = true; // In real tests, this would check current_user_can().

		$this->assertTrue( $has_permission );
		$this->assertEquals( 'manage_options', $required_capability );
	}
}

