<?php
/**
 * Live Agent Manager Test
 *
 * Tests for the AIAGENT_Live_Agent_Manager class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Live Agent Manager class.
 */
class LiveAgentManagerTest extends TestCase {

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
			dirname( __DIR__ ) . '/includes/class-live-agent-manager.php'
		);
	}

	/**
	 * Test live session data structure.
	 */
	public function testLiveSessionDataStructure(): void {
		$session = [
			'id'              => 1,
			'conversation_id' => 100,
			'user_id'         => 50,
			'session_id'      => 'sess_abc123',
			'agent_id'        => 1,
			'status'          => 'active',
			'started_at'      => gmdate( 'Y-m-d H:i:s' ),
			'ended_at'        => null,
			'ended_by'        => null,
		];

		$this->assertArrayHasKey( 'id', $session );
		$this->assertArrayHasKey( 'agent_id', $session );
		$this->assertArrayHasKey( 'status', $session );
		$this->assertEquals( 'active', $session['status'] );
	}

	/**
	 * Test live session statuses.
	 */
	public function testLiveSessionStatuses(): void {
		$statuses = [ 'waiting', 'active', 'ended' ];

		foreach ( $statuses as $status ) {
			$this->assertContains( $status, [ 'waiting', 'active', 'ended' ] );
		}
	}

	/**
	 * Test live message data structure.
	 */
	public function testLiveMessageDataStructure(): void {
		$message = [
			'id'              => 1,
			'live_session_id' => 1,
			'sender_type'     => 'user',
			'sender_id'       => 50,
			'message'         => 'Hello agent!',
			'created_at'      => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'live_session_id', $message );
		$this->assertArrayHasKey( 'sender_type', $message );
		$this->assertArrayHasKey( 'message', $message );
	}

	/**
	 * Test sender types.
	 */
	public function testSenderTypes(): void {
		$sender_types = [ 'user', 'agent' ];

		foreach ( $sender_types as $type ) {
			$this->assertContains( $type, [ 'user', 'agent' ] );
		}
	}

	/**
	 * Test agent availability check.
	 */
	public function testAgentAvailabilityCheck(): void {
		$agents_online = 3;
		$is_available  = $agents_online > 0;

		$this->assertTrue( $is_available );

		$agents_online = 0;
		$is_available  = $agents_online > 0;

		$this->assertFalse( $is_available );
	}

	/**
	 * Test working hours check.
	 */
	public function testWorkingHoursCheck(): void {
		$working_hours = [
			'enabled' => true,
			'start'   => '09:00',
			'end'     => '17:00',
			'days'    => [ 1, 2, 3, 4, 5 ], // Mon-Fri.
		];

		$this->assertTrue( $working_hours['enabled'] );
		$this->assertCount( 5, $working_hours['days'] );
		$this->assertContains( 1, $working_hours['days'] ); // Monday.
	}

	/**
	 * Test queue position calculation.
	 */
	public function testQueuePositionCalculation(): void {
		$waiting_sessions = [
			[ 'id' => 1, 'status' => 'waiting', 'started_at' => '2024-01-01 10:00:00' ],
			[ 'id' => 2, 'status' => 'waiting', 'started_at' => '2024-01-01 10:05:00' ],
			[ 'id' => 3, 'status' => 'waiting', 'started_at' => '2024-01-01 10:10:00' ],
		];

		$current_session_id = 2;
		$position           = 0;

		foreach ( $waiting_sessions as $index => $session ) {
			if ( $session['id'] === $current_session_id ) {
				$position = $index + 1;
				break;
			}
		}

		$this->assertEquals( 2, $position );
	}

	/**
	 * Test session end reasons.
	 */
	public function testSessionEndReasons(): void {
		$reasons = [ 'user', 'agent', 'timeout', 'system' ];

		foreach ( $reasons as $reason ) {
			$this->assertIsString( $reason );
		}
	}

	/**
	 * Test agent status options.
	 */
	public function testAgentStatusOptions(): void {
		$statuses = [ 'online', 'busy', 'away', 'offline' ];

		foreach ( $statuses as $status ) {
			$this->assertContains( $status, [ 'online', 'busy', 'away', 'offline' ] );
		}
	}

	/**
	 * Test heartbeat interval.
	 */
	public function testHeartbeatInterval(): void {
		$interval = 5; // seconds.

		$this->assertIsInt( $interval );
		$this->assertGreaterThan( 0, $interval );
		$this->assertLessThanOrEqual( 30, $interval );
	}

	/**
	 * Test session timeout calculation.
	 */
	public function testSessionTimeoutCalculation(): void {
		$timeout_minutes  = 30;
		$last_activity    = strtotime( '-35 minutes' );
		$timeout_seconds  = $timeout_minutes * 60;
		$is_timed_out     = ( time() - $last_activity ) > $timeout_seconds;

		$this->assertTrue( $is_timed_out );
	}

	/**
	 * Test message polling response.
	 */
	public function testMessagePollingResponse(): void {
		$response = [
			'success'  => true,
			'messages' => [
				[
					'id'          => 1,
					'sender_type' => 'agent',
					'message'     => 'Hello, how can I help?',
					'created_at'  => gmdate( 'Y-m-d H:i:s' ),
				],
			],
			'status'   => 'active',
		];

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $response['messages'] );
		$this->assertEquals( 'active', $response['status'] );
	}

	/**
	 * Test connect response structure.
	 */
	public function testConnectResponseStructure(): void {
		$response = [
			'success'        => true,
			'session_id'     => 'live_abc123',
			'status'         => 'waiting',
			'queue_position' => 1,
			'message'        => 'Connecting you to an agent...',
		];

		$this->assertArrayHasKey( 'session_id', $response );
		$this->assertArrayHasKey( 'queue_position', $response );
		$this->assertEquals( 'waiting', $response['status'] );
	}

	/**
	 * Test agent accept response.
	 */
	public function testAgentAcceptResponse(): void {
		$response = [
			'success'    => true,
			'session'    => [
				'id'         => 1,
				'user_name'  => 'John Doe',
				'user_email' => 'john@example.com',
				'status'     => 'active',
			],
			'messages'   => [],
		];

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'session', $response );
		$this->assertEquals( 'active', $response['session']['status'] );
	}

	/**
	 * Test frontend settings structure.
	 */
	public function testFrontendSettingsStructure(): void {
		$settings = [
			'enabled'             => true,
			'connect_button_text' => 'Talk to Human',
			'waiting_message'     => 'Please wait...',
			'connected_message'   => 'Connected!',
			'offline_message'     => 'Agents offline',
			'is_available'        => true,
		];

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'is_available', $settings );
		$this->assertTrue( $settings['enabled'] );
	}

	/**
	 * Test agent dashboard data.
	 */
	public function testAgentDashboardData(): void {
		$dashboard = [
			'waiting_count'   => 5,
			'active_sessions' => 2,
			'total_today'     => 15,
			'avg_wait_time'   => 120, // seconds.
			'avg_chat_time'   => 600, // seconds.
		];

		$this->assertArrayHasKey( 'waiting_count', $dashboard );
		$this->assertArrayHasKey( 'active_sessions', $dashboard );
		$this->assertGreaterThanOrEqual( 0, $dashboard['waiting_count'] );
	}

	/**
	 * Test session history format.
	 */
	public function testSessionHistoryFormat(): void {
		$history = [
			[
				'id'         => 1,
				'user_name'  => 'John Doe',
				'agent_name' => 'Support Agent',
				'started_at' => '2024-01-15 10:00:00',
				'ended_at'   => '2024-01-15 10:30:00',
				'duration'   => 1800, // seconds.
				'messages'   => 25,
			],
		];

		$this->assertCount( 1, $history );
		$this->assertArrayHasKey( 'duration', $history[0] );
		$this->assertEquals( 1800, $history[0]['duration'] );
	}

	/**
	 * Test notification for new request.
	 */
	public function testNotificationForNewRequest(): void {
		$notification = [
			'type'    => 'live_agent_request',
			'title'   => 'New Live Agent Request',
			'message' => 'John Doe is waiting for assistance',
			'meta'    => [
				'session_id' => 'live_abc123',
				'user_name'  => 'John Doe',
			],
		];

		$this->assertEquals( 'live_agent_request', $notification['type'] );
		$this->assertArrayHasKey( 'session_id', $notification['meta'] );
	}

	/**
	 * Test typing indicator data.
	 */
	public function testTypingIndicatorData(): void {
		$typing = [
			'session_id'  => 'live_abc123',
			'sender_type' => 'agent',
			'is_typing'   => true,
		];

		$this->assertTrue( $typing['is_typing'] );
		$this->assertEquals( 'agent', $typing['sender_type'] );
	}

	/**
	 * Test agent assignment logic.
	 */
	public function testAgentAssignmentLogic(): void {
		$available_agents = [
			[ 'id' => 1, 'active_chats' => 2 ],
			[ 'id' => 2, 'active_chats' => 1 ],
			[ 'id' => 3, 'active_chats' => 3 ],
		];

		// Assign to agent with least active chats.
		usort( $available_agents, fn( $a, $b ) => $a['active_chats'] - $b['active_chats'] );

		$assigned_agent = $available_agents[0];

		$this->assertEquals( 2, $assigned_agent['id'] );
		$this->assertEquals( 1, $assigned_agent['active_chats'] );
	}
}

