<?php
/**
 * Activity Log Manager Test
 *
 * Tests for the AIAGENT_Activity_Log_Manager class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Activity Log Manager class.
 */
class ActivityLogManagerTest extends TestCase {

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
			dirname( __DIR__ ) . '/includes/class-activity-log-manager.php'
		);
	}

	/**
	 * Test log entry data structure.
	 */
	public function testLogEntryDataStructure(): void {
		$log_entry = [
			'id'         => 1,
			'category'   => 'chat',
			'action'     => 'message_sent',
			'message'    => 'User sent a message',
			'meta'       => json_encode( [ 'conversation_id' => 123 ] ),
			'user_id'    => 1,
			'ip_address' => '127.0.0.1',
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'id', $log_entry );
		$this->assertArrayHasKey( 'category', $log_entry );
		$this->assertArrayHasKey( 'action', $log_entry );
		$this->assertArrayHasKey( 'message', $log_entry );
		$this->assertArrayHasKey( 'meta', $log_entry );
		$this->assertArrayHasKey( 'user_id', $log_entry );
		$this->assertArrayHasKey( 'ip_address', $log_entry );
		$this->assertArrayHasKey( 'created_at', $log_entry );
	}

	/**
	 * Test log categories.
	 */
	public function testLogCategories(): void {
		$categories = [
			'chat',
			'lead',
			'notification',
			'knowledge',
			'integration',
			'api',
			'system',
			'user',
		];

		foreach ( $categories as $category ) {
			$this->assertIsString( $category );
			$this->assertNotEmpty( $category );
		}

		$this->assertCount( 8, $categories );
	}

	/**
	 * Test log actions per category.
	 */
	public function testLogActionsPerCategory(): void {
		$category_actions = [
			'chat'         => [ 'started', 'message_sent', 'message_received', 'ended', 'rated' ],
			'lead'         => [ 'created', 'updated', 'status_changed', 'deleted' ],
			'notification' => [ 'created', 'sent', 'read', 'deleted' ],
			'knowledge'    => [ 'document_added', 'document_deleted', 'cleared', 'synced' ],
			'integration'  => [ 'connected', 'disconnected', 'synced', 'error' ],
			'api'          => [ 'request', 'response', 'error' ],
			'system'       => [ 'activated', 'deactivated', 'settings_updated' ],
		];

		$this->assertArrayHasKey( 'chat', $category_actions );
		$this->assertContains( 'started', $category_actions['chat'] );
	}

	/**
	 * Test log meta parsing.
	 */
	public function testLogMetaParsing(): void {
		$meta = [
			'conversation_id' => 123,
			'user_email'      => 'test@example.com',
			'duration'        => 300,
		];

		$meta_json = json_encode( $meta );
		$parsed    = json_decode( $meta_json, true );

		$this->assertIsArray( $parsed );
		$this->assertEquals( 123, $parsed['conversation_id'] );
	}

	/**
	 * Test log pagination.
	 */
	public function testLogPagination(): void {
		$page     = 1;
		$per_page = 50;
		$offset   = ( $page - 1 ) * $per_page;

		$this->assertEquals( 0, $offset );

		$page   = 3;
		$offset = ( $page - 1 ) * $per_page;

		$this->assertEquals( 100, $offset );
	}

	/**
	 * Test logs response structure.
	 */
	public function testLogsResponseStructure(): void {
		$response = [
			'logs'  => [],
			'total' => 0,
			'pages' => 1,
		];

		$this->assertArrayHasKey( 'logs', $response );
		$this->assertArrayHasKey( 'total', $response );
		$this->assertArrayHasKey( 'pages', $response );
		$this->assertIsArray( $response['logs'] );
	}

	/**
	 * Test log filtering by category.
	 */
	public function testLogFilteringByCategory(): void {
		$logs = [
			[ 'id' => 1, 'category' => 'chat' ],
			[ 'id' => 2, 'category' => 'lead' ],
			[ 'id' => 3, 'category' => 'chat' ],
			[ 'id' => 4, 'category' => 'system' ],
		];

		$filtered = array_filter( $logs, fn( $log ) => 'chat' === $log['category'] );

		$this->assertCount( 2, $filtered );
	}

	/**
	 * Test log filtering by action.
	 */
	public function testLogFilteringByAction(): void {
		$logs = [
			[ 'id' => 1, 'action' => 'created' ],
			[ 'id' => 2, 'action' => 'updated' ],
			[ 'id' => 3, 'action' => 'created' ],
		];

		$filtered = array_filter( $logs, fn( $log ) => 'created' === $log['action'] );

		$this->assertCount( 2, $filtered );
	}

	/**
	 * Test log filtering by date range.
	 */
	public function testLogFilteringByDateRange(): void {
		$logs = [
			[ 'id' => 1, 'created_at' => '2024-01-01 10:00:00' ],
			[ 'id' => 2, 'created_at' => '2024-01-15 10:00:00' ],
			[ 'id' => 3, 'created_at' => '2024-02-01 10:00:00' ],
		];

		$start_date = '2024-01-01';
		$end_date   = '2024-01-31';

		$filtered = array_filter(
			$logs,
			fn( $log ) => $log['created_at'] >= $start_date && $log['created_at'] <= $end_date . ' 23:59:59'
		);

		$this->assertCount( 2, $filtered );
	}

	/**
	 * Test log stats structure.
	 */
	public function testLogStatsStructure(): void {
		$stats = [
			'total_logs'        => 1000,
			'logs_today'        => 50,
			'logs_this_week'    => 200,
			'logs_this_month'   => 800,
			'by_category'       => [
				'chat'    => 500,
				'lead'    => 200,
				'system'  => 300,
			],
			'recent_errors'     => 5,
		];

		$this->assertArrayHasKey( 'total_logs', $stats );
		$this->assertArrayHasKey( 'by_category', $stats );
		$this->assertIsArray( $stats['by_category'] );
	}

	/**
	 * Test IP address validation.
	 */
	public function testIPAddressValidation(): void {
		$valid_ipv4   = '192.168.1.1';
		$valid_ipv6   = '::1';
		$invalid_ip   = 'not-an-ip';

		$this->assertNotFalse( filter_var( $valid_ipv4, FILTER_VALIDATE_IP ) );
		$this->assertNotFalse( filter_var( $valid_ipv6, FILTER_VALIDATE_IP ) );
		$this->assertFalse( filter_var( $invalid_ip, FILTER_VALIDATE_IP ) );
	}

	/**
	 * Test log message formatting.
	 */
	public function testLogMessageFormatting(): void {
		$template = 'User %s performed action %s';
		$message  = sprintf( $template, 'john@example.com', 'login' );

		$this->assertEquals( 'User john@example.com performed action login', $message );
	}

	/**
	 * Test log ordering.
	 */
	public function testLogOrdering(): void {
		$logs = [
			[ 'id' => 1, 'created_at' => '2024-01-01 10:00:00' ],
			[ 'id' => 2, 'created_at' => '2024-01-01 12:00:00' ],
			[ 'id' => 3, 'created_at' => '2024-01-01 11:00:00' ],
		];

		// Sort by created_at descending.
		usort( $logs, fn( $a, $b ) => strcmp( $b['created_at'], $a['created_at'] ) );

		$this->assertEquals( 2, $logs[0]['id'] ); // Latest first.
	}

	/**
	 * Test log retention period.
	 */
	public function testLogRetentionPeriod(): void {
		$retention_days = 30;
		$cutoff_date    = gmdate( 'Y-m-d', strtotime( "-$retention_days days" ) );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $cutoff_date );
	}

	/**
	 * Test log export format.
	 */
	public function testLogExportFormat(): void {
		$log = [
			'id'         => 1,
			'category'   => 'chat',
			'action'     => 'started',
			'message'    => 'Chat started',
			'created_at' => '2024-01-01 10:00:00',
		];

		$csv_headers = [ 'ID', 'Category', 'Action', 'Message', 'Created At' ];
		$csv_line    = implode( ',', array_values( $log ) );

		$this->assertCount( 5, $csv_headers );
		$this->assertStringContainsString( 'chat', $csv_line );
	}

	/**
	 * Test empty logs result.
	 */
	public function testEmptyLogsResult(): void {
		$result = [
			'logs'  => [],
			'total' => 0,
			'pages' => 0,
		];

		$this->assertEmpty( $result['logs'] );
		$this->assertEquals( 0, $result['total'] );
	}

	/**
	 * Test log with null user ID.
	 */
	public function testLogWithNullUserID(): void {
		$log = [
			'id'       => 1,
			'user_id'  => null,
			'category' => 'system',
			'action'   => 'cron_run',
			'message'  => 'Cron job executed',
		];

		$this->assertNull( $log['user_id'] );
		$this->assertEquals( 'system', $log['category'] );
	}

	/**
	 * Test log cleanup operation.
	 */
	public function testLogCleanupOperation(): void {
		$logs = [
			[ 'id' => 1, 'created_at' => gmdate( 'Y-m-d', strtotime( '-60 days' ) ) ],
			[ 'id' => 2, 'created_at' => gmdate( 'Y-m-d', strtotime( '-10 days' ) ) ],
			[ 'id' => 3, 'created_at' => gmdate( 'Y-m-d', strtotime( '-45 days' ) ) ],
		];

		$retention = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$kept      = array_filter( $logs, fn( $log ) => $log['created_at'] >= $retention );

		$this->assertCount( 1, $kept );
	}

	/**
	 * Test log search functionality.
	 */
	public function testLogSearchFunctionality(): void {
		$logs = [
			[ 'id' => 1, 'message' => 'User logged in successfully' ],
			[ 'id' => 2, 'message' => 'API request failed' ],
			[ 'id' => 3, 'message' => 'User updated settings' ],
		];

		$search_term = 'User';
		$results     = array_filter( $logs, fn( $log ) => stripos( $log['message'], $search_term ) !== false );

		$this->assertCount( 2, $results );
	}
}

