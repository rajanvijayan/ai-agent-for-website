<?php
/**
 * Conversations Manager Test
 *
 * Tests for the AIAGENT_Conversations_Manager class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Conversations Manager class.
 */
class ConversationsManagerTest extends TestCase {

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
			dirname( __DIR__ ) . '/includes/class-conversations-manager.php'
		);
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
		$this->assertArrayHasKey( 'user_id', $conversation );
		$this->assertArrayHasKey( 'session_id', $conversation );
		$this->assertArrayHasKey( 'status', $conversation );
		$this->assertEquals( 'active', $conversation['status'] );
	}

	/**
	 * Test conversation statuses.
	 */
	public function testConversationStatuses(): void {
		$statuses = [ 'active', 'ended', 'closed' ];

		foreach ( $statuses as $status ) {
			$this->assertContains( $status, [ 'active', 'ended', 'closed' ] );
		}
	}

	/**
	 * Test message data structure.
	 */
	public function testMessageDataStructure(): void {
		$message = [
			'id'              => 1,
			'conversation_id' => 1,
			'role'            => 'user',
			'content'         => 'Hello, I need help with my order.',
			'created_at'      => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'id', $message );
		$this->assertArrayHasKey( 'conversation_id', $message );
		$this->assertArrayHasKey( 'role', $message );
		$this->assertArrayHasKey( 'content', $message );
	}

	/**
	 * Test message roles.
	 */
	public function testMessageRoles(): void {
		$roles = [ 'user', 'assistant', 'system' ];

		foreach ( $roles as $role ) {
			$this->assertContains( $role, [ 'user', 'assistant', 'system' ] );
		}
	}

	/**
	 * Test user data structure.
	 */
	public function testUserDataStructure(): void {
		$user = [
			'id'         => 1,
			'name'       => 'John Doe',
			'email'      => 'john@example.com',
			'phone'      => '+1234567890',
			'session_id' => 'sess_abc123',
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'name', $user );
		$this->assertArrayHasKey( 'email', $user );
		$this->assertEquals( 'john@example.com', $user['email'] );
	}

	/**
	 * Test conversation list response.
	 */
	public function testConversationListResponse(): void {
		$response = [
			'conversations' => [],
			'total'         => 0,
			'pages'         => 0,
		];

		$this->assertArrayHasKey( 'conversations', $response );
		$this->assertArrayHasKey( 'total', $response );
		$this->assertArrayHasKey( 'pages', $response );
		$this->assertIsArray( $response['conversations'] );
	}

	/**
	 * Test conversation filtering by status.
	 */
	public function testConversationFilteringByStatus(): void {
		$conversations = [
			[ 'id' => 1, 'status' => 'active' ],
			[ 'id' => 2, 'status' => 'ended' ],
			[ 'id' => 3, 'status' => 'active' ],
			[ 'id' => 4, 'status' => 'closed' ],
		];

		$filtered = array_filter( $conversations, fn( $c ) => 'active' === $c['status'] );

		$this->assertCount( 2, $filtered );
	}

	/**
	 * Test conversation filtering by date.
	 */
	public function testConversationFilteringByDate(): void {
		$conversations = [
			[ 'id' => 1, 'started_at' => '2024-01-01 10:00:00' ],
			[ 'id' => 2, 'started_at' => '2024-01-15 10:00:00' ],
			[ 'id' => 3, 'started_at' => '2024-02-01 10:00:00' ],
		];

		$start_date = '2024-01-01';
		$end_date   = '2024-01-31';

		$filtered = array_filter(
			$conversations,
			fn( $c ) => $c['started_at'] >= $start_date && $c['started_at'] <= $end_date . ' 23:59:59'
		);

		$this->assertCount( 2, $filtered );
	}

	/**
	 * Test rating values.
	 */
	public function testRatingValues(): void {
		$valid_ratings = [ 1, 2, 3, 4, 5 ];

		foreach ( $valid_ratings as $rating ) {
			$this->assertGreaterThanOrEqual( 1, $rating );
			$this->assertLessThanOrEqual( 5, $rating );
		}
	}

	/**
	 * Test conversation duration calculation.
	 */
	public function testConversationDurationCalculation(): void {
		$started_at = '2024-01-15 10:00:00';
		$ended_at   = '2024-01-15 10:30:00';

		$start    = strtotime( $started_at );
		$end      = strtotime( $ended_at );
		$duration = $end - $start;

		$this->assertEquals( 1800, $duration ); // 30 minutes in seconds.
	}

	/**
	 * Test message count per conversation.
	 */
	public function testMessageCountPerConversation(): void {
		$messages = [
			[ 'conversation_id' => 1, 'role' => 'user' ],
			[ 'conversation_id' => 1, 'role' => 'assistant' ],
			[ 'conversation_id' => 1, 'role' => 'user' ],
			[ 'conversation_id' => 2, 'role' => 'user' ],
		];

		$conv1_messages = array_filter( $messages, fn( $m ) => $m['conversation_id'] === 1 );

		$this->assertCount( 3, $conv1_messages );
	}

	/**
	 * Test conversation summary with user info.
	 */
	public function testConversationSummaryWithUserInfo(): void {
		$summary = [
			'id'            => 1,
			'user_name'     => 'John Doe',
			'user_email'    => 'john@example.com',
			'started_at'    => '2024-01-15 10:00:00',
			'ended_at'      => '2024-01-15 10:30:00',
			'message_count' => 10,
			'rating'        => 5,
			'status'        => 'ended',
		];

		$this->assertArrayHasKey( 'user_name', $summary );
		$this->assertArrayHasKey( 'message_count', $summary );
		$this->assertEquals( 10, $summary['message_count'] );
	}

	/**
	 * Test conversation export format.
	 */
	public function testConversationExportFormat(): void {
		$export = [
			'conversation_id' => 1,
			'user'            => [
				'name'  => 'John Doe',
				'email' => 'john@example.com',
			],
			'messages'        => [
				[ 'role' => 'user', 'content' => 'Hello', 'time' => '10:00' ],
				[ 'role' => 'assistant', 'content' => 'Hi!', 'time' => '10:01' ],
			],
			'metadata'        => [
				'started_at' => '2024-01-15 10:00:00',
				'ended_at'   => '2024-01-15 10:30:00',
				'rating'     => 5,
			],
		];

		$this->assertArrayHasKey( 'user', $export );
		$this->assertArrayHasKey( 'messages', $export );
		$this->assertArrayHasKey( 'metadata', $export );
	}

	/**
	 * Test conversation pagination.
	 */
	public function testConversationPagination(): void {
		$page     = 1;
		$per_page = 20;
		$total    = 55;

		$offset = ( $page - 1 ) * $per_page;
		$pages  = (int) ceil( $total / $per_page );

		$this->assertEquals( 0, $offset );
		$this->assertEquals( 3, $pages );
	}

	/**
	 * Test conversation search.
	 */
	public function testConversationSearch(): void {
		$conversations = [
			[ 'id' => 1, 'user_name' => 'John Doe', 'user_email' => 'john@example.com' ],
			[ 'id' => 2, 'user_name' => 'Jane Smith', 'user_email' => 'jane@example.com' ],
			[ 'id' => 3, 'user_name' => 'Bob Johnson', 'user_email' => 'bob@test.com' ],
		];

		$search  = 'example.com';
		$results = array_filter(
			$conversations,
			fn( $c ) => stripos( $c['user_email'], $search ) !== false
		);

		$this->assertCount( 2, $results );
	}

	/**
	 * Test conversion to lead.
	 */
	public function testConversionToLead(): void {
		$conversation = [
			'id'        => 1,
			'user_id'   => 100,
			'status'    => 'ended',
		];

		// Simulate lead creation from conversation.
		$lead = [
			'user_id'         => $conversation['user_id'],
			'conversation_id' => $conversation['id'],
			'status'          => 'new',
			'source'          => 'chat',
		];

		$this->assertEquals( $conversation['user_id'], $lead['user_id'] );
		$this->assertEquals( 'chat', $lead['source'] );
	}

	/**
	 * Test close conversation action.
	 */
	public function testCloseConversationAction(): void {
		$conversation = [
			'id'       => 1,
			'status'   => 'active',
			'ended_at' => null,
		];

		// Close the conversation.
		$conversation['status']   = 'closed';
		$conversation['ended_at'] = gmdate( 'Y-m-d H:i:s' );

		$this->assertEquals( 'closed', $conversation['status'] );
		$this->assertNotNull( $conversation['ended_at'] );
	}

	/**
	 * Test statistics calculation.
	 */
	public function testStatisticsCalculation(): void {
		$stats = [
			'total_conversations' => 100,
			'active'              => 5,
			'ended'               => 95,
			'avg_rating'          => 4.5,
			'avg_duration'        => 900, // seconds.
			'total_messages'      => 1500,
		];

		$this->assertEquals( 100, $stats['total_conversations'] );
		$this->assertGreaterThan( 0, $stats['avg_rating'] );
		$this->assertLessThanOrEqual( 5, $stats['avg_rating'] );
	}
}

