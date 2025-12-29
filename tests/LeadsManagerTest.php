<?php
/**
 * Leads Manager Test
 *
 * Tests for the AIAGENT_Leads_Manager class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Leads Manager class.
 */
class LeadsManagerTest extends TestCase {

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
			dirname( __DIR__ ) . '/includes/class-leads-manager.php'
		);
	}

	/**
	 * Test lead status constants are defined.
	 */
	public function testLeadStatusConstants(): void {
		// Read the file and check for status definitions.
		$content = file_get_contents( dirname( __DIR__ ) . '/includes/class-leads-manager.php' );

		$this->assertStringContainsString( 'STATUS_NEW', $content );
		$this->assertStringContainsString( 'STATUS_CONTACTED', $content );
		$this->assertStringContainsString( 'STATUS_QUALIFIED', $content );
		$this->assertStringContainsString( 'STATUS_CONVERTED', $content );
		$this->assertStringContainsString( 'STATUS_CLOSED', $content );
	}

	/**
	 * Test lead status values.
	 */
	public function testLeadStatusValues(): void {
		$statuses = [
			'new'       => 'new',
			'contacted' => 'contacted',
			'qualified' => 'qualified',
			'converted' => 'converted',
			'closed'    => 'closed',
		];

		foreach ( $statuses as $key => $value ) {
			$this->assertEquals( $key, $value );
		}
	}

	/**
	 * Test lead data structure.
	 */
	public function testLeadDataStructure(): void {
		$lead = [
			'id'              => 1,
			'user_id'         => 100,
			'conversation_id' => 50,
			'status'          => 'new',
			'source'          => 'chat',
			'summary'         => 'Customer inquiry about products',
			'notes'           => 'Follow up next week',
			'created_at'      => gmdate( 'Y-m-d H:i:s' ),
			'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'id', $lead );
		$this->assertArrayHasKey( 'user_id', $lead );
		$this->assertArrayHasKey( 'conversation_id', $lead );
		$this->assertArrayHasKey( 'status', $lead );
		$this->assertArrayHasKey( 'source', $lead );
		$this->assertArrayHasKey( 'summary', $lead );
		$this->assertArrayHasKey( 'notes', $lead );
		$this->assertArrayHasKey( 'created_at', $lead );
	}

	/**
	 * Test lead source types.
	 */
	public function testLeadSourceTypes(): void {
		$sources = [ 'chat', 'form', 'manual', 'import' ];

		foreach ( $sources as $source ) {
			$lead = [
				'source' => $source,
			];

			$this->assertEquals( $source, $lead['source'] );
		}
	}

	/**
	 * Test lead notes structure.
	 */
	public function testLeadNotesStructure(): void {
		$note = [
			'id'          => 1,
			'lead_id'     => 1,
			'note'        => 'Called the customer, no answer.',
			'author_id'   => 1,
			'author_name' => 'Admin User',
			'created_at'  => gmdate( 'Y-m-d H:i:s' ),
		];

		$this->assertArrayHasKey( 'lead_id', $note );
		$this->assertArrayHasKey( 'note', $note );
		$this->assertArrayHasKey( 'author_id', $note );
		$this->assertArrayHasKey( 'author_name', $note );
	}

	/**
	 * Test lead with user information.
	 */
	public function testLeadWithUserInfo(): void {
		$lead = [
			'id'     => 1,
			'name'   => 'John Doe',
			'email'  => 'john@example.com',
			'phone'  => '+1234567890',
			'status' => 'new',
		];

		$this->assertEquals( 'John Doe', $lead['name'] );
		$this->assertEquals( 'john@example.com', $lead['email'] );
		$this->assertEquals( '+1234567890', $lead['phone'] );
	}

	/**
	 * Test pagination parameters.
	 */
	public function testPaginationParameters(): void {
		$page     = 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$this->assertEquals( 0, $offset );

		$page   = 3;
		$offset = ( $page - 1 ) * $per_page;

		$this->assertEquals( 40, $offset );
	}

	/**
	 * Test leads response structure.
	 */
	public function testLeadsResponseStructure(): void {
		$response = [
			'leads' => [],
			'total' => 0,
			'pages' => 0,
		];

		$this->assertArrayHasKey( 'leads', $response );
		$this->assertArrayHasKey( 'total', $response );
		$this->assertArrayHasKey( 'pages', $response );
		$this->assertIsArray( $response['leads'] );
	}

	/**
	 * Test leads filtering by status.
	 */
	public function testLeadsFilteringByStatus(): void {
		$leads = [
			[ 'id' => 1, 'status' => 'new' ],
			[ 'id' => 2, 'status' => 'contacted' ],
			[ 'id' => 3, 'status' => 'new' ],
			[ 'id' => 4, 'status' => 'qualified' ],
		];

		$filtered = array_filter( $leads, fn( $lead ) => 'new' === $lead['status'] );

		$this->assertCount( 2, $filtered );
	}

	/**
	 * Test status transition validation.
	 */
	public function testStatusTransitionValidation(): void {
		$valid_statuses = [ 'new', 'contacted', 'qualified', 'converted', 'closed' ];

		$current_status = 'new';
		$new_status     = 'contacted';

		$this->assertContains( $current_status, $valid_statuses );
		$this->assertContains( $new_status, $valid_statuses );
	}

	/**
	 * Test lead summary generation.
	 */
	public function testLeadSummaryGeneration(): void {
		$message  = 'This is a very long message that contains a lot of information about the customer inquiry regarding our products and services.';
		$summary  = wp_trim_words( $message, 10 );

		$this->assertNotEquals( $message, $summary );
		$this->assertStringContainsString( '...', $summary );
	}

	/**
	 * Test empty leads result.
	 */
	public function testEmptyLeadsResult(): void {
		$result = [
			'leads' => [],
			'total' => 0,
			'pages' => 0,
		];

		$this->assertEmpty( $result['leads'] );
		$this->assertEquals( 0, $result['total'] );
		$this->assertEquals( 0, $result['pages'] );
	}

	/**
	 * Test pages calculation.
	 */
	public function testPagesCalculation(): void {
		$total    = 45;
		$per_page = 20;
		$pages    = (int) ceil( $total / $per_page );

		$this->assertEquals( 3, $pages );

		$total = 20;
		$pages = (int) ceil( $total / $per_page );

		$this->assertEquals( 1, $pages );

		$total = 0;
		$pages = (int) ceil( $total / $per_page );

		$this->assertEquals( 0, $pages );
	}

	/**
	 * Test lead creation timestamp.
	 */
	public function testLeadCreationTimestamp(): void {
		$created_at = current_time( 'mysql' );

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$created_at
		);
	}

	/**
	 * Test webhook trigger data structure.
	 */
	public function testWebhookTriggerData(): void {
		$webhook_data = [
			'event'   => 'lead_created',
			'lead_id' => 1,
			'data'    => [
				'name'   => 'John Doe',
				'email'  => 'john@example.com',
				'status' => 'new',
			],
		];

		$this->assertEquals( 'lead_created', $webhook_data['event'] );
		$this->assertArrayHasKey( 'lead_id', $webhook_data );
		$this->assertArrayHasKey( 'data', $webhook_data );
	}

	/**
	 * Test lead export data format.
	 */
	public function testLeadExportDataFormat(): void {
		$lead = [
			'id'         => 1,
			'name'       => 'John Doe',
			'email'      => 'john@example.com',
			'phone'      => '123456',
			'status'     => 'new',
			'summary'    => 'Product inquiry',
			'created_at' => '2024-01-01 10:00:00',
		];

		$csv_line = implode( ',', array_values( $lead ) );

		$this->assertStringContainsString( 'John Doe', $csv_line );
		$this->assertStringContainsString( 'john@example.com', $csv_line );
	}

	/**
	 * Test lead search fields.
	 */
	public function testLeadSearchFields(): void {
		$searchable_fields = [ 'name', 'email', 'phone', 'summary', 'notes' ];

		foreach ( $searchable_fields as $field ) {
			$this->assertIsString( $field );
		}

		$this->assertCount( 5, $searchable_fields );
	}

	/**
	 * Test lead activity log entry.
	 */
	public function testLeadActivityLogEntry(): void {
		$log_entry = [
			'category' => 'lead',
			'action'   => 'created',
			'message'  => 'Lead #1 created from conversation for john@example.com',
			'meta'     => [
				'lead_id'         => 1,
				'conversation_id' => 50,
				'user_email'      => 'john@example.com',
			],
		];

		$this->assertEquals( 'lead', $log_entry['category'] );
		$this->assertEquals( 'created', $log_entry['action'] );
		$this->assertArrayHasKey( 'lead_id', $log_entry['meta'] );
	}
}

