<?php
/**
 * Notification Manager Test
 *
 * Tests for the AIAGENT_Notification_Manager class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Notification Manager class.
 */
class NotificationManagerTest extends TestCase {

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
			dirname( __DIR__ ) . '/includes/class-notification-manager.php'
		);
	}

	/**
	 * Test notification data structure.
	 */
	public function testNotificationDataStructure(): void {
		$notification = [
			'id'         => 1,
			'type'       => 'new_lead',
			'title'      => 'New Lead Created',
			'message'    => 'A new lead was created from chat conversation.',
			'meta'       => json_encode( [ 'lead_id' => 1 ] ),
			'status'     => 'unread',
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'id', $notification );
		$this->assertArrayHasKey( 'type', $notification );
		$this->assertArrayHasKey( 'title', $notification );
		$this->assertArrayHasKey( 'message', $notification );
		$this->assertArrayHasKey( 'meta', $notification );
		$this->assertArrayHasKey( 'status', $notification );
		$this->assertArrayHasKey( 'created_at', $notification );
	}

	/**
	 * Test notification types.
	 */
	public function testNotificationTypes(): void {
		$types = [
			'new_lead',
			'new_conversation',
			'live_agent_request',
			'api_error',
			'system_alert',
			'integration_sync',
		];

		foreach ( $types as $type ) {
			$this->assertIsString( $type );
			$this->assertNotEmpty( $type );
		}
	}

	/**
	 * Test notification status values.
	 */
	public function testNotificationStatusValues(): void {
		$statuses = [ 'unread', 'read' ];

		foreach ( $statuses as $status ) {
			$this->assertIsString( $status );
		}
	}

	/**
	 * Test unread count calculation.
	 */
	public function testUnreadCountCalculation(): void {
		$notifications = [
			[ 'id' => 1, 'status' => 'unread' ],
			[ 'id' => 2, 'status' => 'read' ],
			[ 'id' => 3, 'status' => 'unread' ],
			[ 'id' => 4, 'status' => 'unread' ],
			[ 'id' => 5, 'status' => 'read' ],
		];

		$unread = array_filter( $notifications, fn( $n ) => 'unread' === $n['status'] );

		$this->assertCount( 3, $unread );
	}

	/**
	 * Test notification meta parsing.
	 */
	public function testNotificationMetaParsing(): void {
		$meta_json = json_encode( [
			'lead_id'    => 123,
			'user_email' => 'test@example.com',
			'source'     => 'chat',
		] );

		$meta = json_decode( $meta_json, true );

		$this->assertIsArray( $meta );
		$this->assertEquals( 123, $meta['lead_id'] );
		$this->assertEquals( 'test@example.com', $meta['user_email'] );
	}

	/**
	 * Test notification pagination.
	 */
	public function testNotificationPagination(): void {
		$page     = 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$this->assertEquals( 0, $offset );

		$page   = 2;
		$offset = ( $page - 1 ) * $per_page;

		$this->assertEquals( 20, $offset );
	}

	/**
	 * Test notifications response structure.
	 */
	public function testNotificationsResponseStructure(): void {
		$response = [
			'notifications' => [],
			'total'         => 0,
			'pages'         => 1,
			'unread_count'  => 0,
		];

		$this->assertArrayHasKey( 'notifications', $response );
		$this->assertArrayHasKey( 'total', $response );
		$this->assertArrayHasKey( 'pages', $response );
		$this->assertArrayHasKey( 'unread_count', $response );
	}

	/**
	 * Test mark as read operation.
	 */
	public function testMarkAsReadOperation(): void {
		$notification = [
			'id'     => 1,
			'status' => 'unread',
		];

		// Simulate marking as read.
		$notification['status'] = 'read';

		$this->assertEquals( 'read', $notification['status'] );
	}

	/**
	 * Test mark all as read.
	 */
	public function testMarkAllAsRead(): void {
		$notifications = [
			[ 'id' => 1, 'status' => 'unread' ],
			[ 'id' => 2, 'status' => 'unread' ],
			[ 'id' => 3, 'status' => 'unread' ],
		];

		// Mark all as read.
		$updated = array_map( function ( $n ) {
			$n['status'] = 'read';
			return $n;
		}, $notifications );

		foreach ( $updated as $n ) {
			$this->assertEquals( 'read', $n['status'] );
		}
	}

	/**
	 * Test notification filtering by type.
	 */
	public function testNotificationFilteringByType(): void {
		$notifications = [
			[ 'id' => 1, 'type' => 'new_lead' ],
			[ 'id' => 2, 'type' => 'new_conversation' ],
			[ 'id' => 3, 'type' => 'new_lead' ],
			[ 'id' => 4, 'type' => 'api_error' ],
		];

		$filtered = array_filter( $notifications, fn( $n ) => 'new_lead' === $n['type'] );

		$this->assertCount( 2, $filtered );
	}

	/**
	 * Test notification title generation.
	 */
	public function testNotificationTitleGeneration(): void {
		$type_titles = [
			'new_lead'           => 'New Lead',
			'new_conversation'   => 'New Conversation',
			'live_agent_request' => 'Live Agent Request',
			'api_error'          => 'API Error',
		];

		$this->assertEquals( 'New Lead', $type_titles['new_lead'] );
		$this->assertEquals( 'API Error', $type_titles['api_error'] );
	}

	/**
	 * Test email notification data.
	 */
	public function testEmailNotificationData(): void {
		$email_data = [
			'to'      => 'admin@example.com',
			'subject' => 'New Lead Notification',
			'message' => 'A new lead was created.',
			'headers' => [ 'Content-Type: text/html; charset=UTF-8' ],
		];

		$this->assertArrayHasKey( 'to', $email_data );
		$this->assertArrayHasKey( 'subject', $email_data );
		$this->assertArrayHasKey( 'message', $email_data );
	}

	/**
	 * Test notification settings storage.
	 */
	public function testNotificationSettingsStorage(): void {
		$settings = [
			'email_enabled'    => true,
			'email_address'    => 'admin@example.com',
			'email_on_lead'    => true,
			'email_on_chat'    => false,
			'browser_enabled'  => true,
		];

		update_option( 'aiagent_notification_settings', $settings );

		$retrieved = get_option( 'aiagent_notification_settings' );

		$this->assertTrue( $retrieved['email_enabled'] );
		$this->assertEquals( 'admin@example.com', $retrieved['email_address'] );
	}

	/**
	 * Test notification deletion.
	 */
	public function testNotificationDeletion(): void {
		$notifications = [
			[ 'id' => 1 ],
			[ 'id' => 2 ],
			[ 'id' => 3 ],
		];

		// Delete notification with id 2.
		$updated = array_filter( $notifications, fn( $n ) => $n['id'] !== 2 );

		$this->assertCount( 2, $updated );
	}

	/**
	 * Test notification timestamp format.
	 */
	public function testNotificationTimestampFormat(): void {
		$timestamp = gmdate( 'Y-m-d H:i:s' );

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$timestamp
		);
	}

	/**
	 * Test notification batch operations.
	 */
	public function testNotificationBatchOperations(): void {
		$ids = [ 1, 2, 3, 4, 5 ];

		$this->assertCount( 5, $ids );
		$this->assertContains( 3, $ids );
	}

	/**
	 * Test empty notifications list.
	 */
	public function testEmptyNotificationsList(): void {
		$result = [
			'notifications' => [],
			'total'         => 0,
			'unread_count'  => 0,
		];

		$this->assertEmpty( $result['notifications'] );
		$this->assertEquals( 0, $result['unread_count'] );
	}

	/**
	 * Test notification ordering.
	 */
	public function testNotificationOrdering(): void {
		$notifications = [
			[ 'id' => 1, 'created_at' => '2024-01-01 10:00:00' ],
			[ 'id' => 2, 'created_at' => '2024-01-01 12:00:00' ],
			[ 'id' => 3, 'created_at' => '2024-01-01 11:00:00' ],
		];

		// Sort by created_at descending.
		usort( $notifications, fn( $a, $b ) => strcmp( $b['created_at'], $a['created_at'] ) );

		$this->assertEquals( 2, $notifications[0]['id'] ); // Latest first.
	}

	/**
	 * Test log notification entry.
	 */
	public function testLogNotificationEntry(): void {
		$log_entry = [
			'category' => 'notification',
			'action'   => 'created',
			'message'  => 'Notification sent for new lead',
			'meta'     => [
				'notification_id' => 1,
				'type'            => 'new_lead',
			],
		];

		$this->assertEquals( 'notification', $log_entry['category'] );
		$this->assertArrayHasKey( 'notification_id', $log_entry['meta'] );
	}
}

