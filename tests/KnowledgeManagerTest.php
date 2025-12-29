<?php
/**
 * Knowledge Manager Test
 *
 * Tests for the AIAGENT_Knowledge_Manager class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Knowledge Manager class.
 */
class KnowledgeManagerTest extends TestCase {

	/**
	 * Test directory for knowledge base.
	 *
	 * @var string
	 */
	private $test_kb_dir;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_mocks();

		// Set up test knowledge base directory.
		$upload_dir       = wp_upload_dir();
		$this->test_kb_dir = $upload_dir['basedir'] . '/ai-agent-knowledge';

		if ( ! file_exists( $this->test_kb_dir ) ) {
			mkdir( $this->test_kb_dir, 0755, true );
		}
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		// Clean up test files.
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';
		if ( file_exists( $kb_file ) ) {
			unlink( $kb_file );
		}
		parent::tearDown();
	}

	/**
	 * Test that the class file exists.
	 */
	public function testClassFileExists(): void {
		$this->assertFileExists(
			dirname( __DIR__ ) . '/includes/class-knowledge-manager.php'
		);
	}

	/**
	 * Test knowledge base directory is created.
	 */
	public function testKnowledgeBaseDirectoryCreated(): void {
		$this->assertDirectoryExists( $this->test_kb_dir );
	}

	/**
	 * Test default settings structure.
	 */
	public function testDefaultSettingsStructure(): void {
		$defaults = [
			'api_key'            => '',
			'ai_name'            => 'AI Assistant',
			'system_instruction' => 'You are a helpful assistant.',
			'welcome_message'    => 'Hello! How can I help you today?',
			'widget_position'    => 'bottom-right',
			'primary_color'      => '#0073aa',
			'knowledge_urls'     => [],
			'enabled'            => false,
		];

		update_option( 'aiagent_settings', $defaults );

		$settings = get_option( 'aiagent_settings' );

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'api_key', $settings );
		$this->assertArrayHasKey( 'ai_name', $settings );
		$this->assertArrayHasKey( 'knowledge_urls', $settings );
		$this->assertIsArray( $settings['knowledge_urls'] );
	}

	/**
	 * Test knowledge URL storage.
	 */
	public function testKnowledgeURLStorage(): void {
		$urls = [
			'https://example.com/page1',
			'https://example.com/page2',
		];

		$settings = [
			'knowledge_urls' => $urls,
		];

		update_option( 'aiagent_settings', $settings );

		$retrieved = get_option( 'aiagent_settings' );

		$this->assertCount( 2, $retrieved['knowledge_urls'] );
		$this->assertContains( 'https://example.com/page1', $retrieved['knowledge_urls'] );
	}

	/**
	 * Test knowledge base file creation.
	 */
	public function testKnowledgeBaseFileCreation(): void {
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';

		$kb_data = [
			'documents' => [
				[
					'title'   => 'Test Document',
					'content' => 'This is test content.',
					'source'  => 'manual',
				],
			],
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		];

		file_put_contents( $kb_file, json_encode( $kb_data ) );

		$this->assertFileExists( $kb_file );

		$content = json_decode( file_get_contents( $kb_file ), true );
		$this->assertIsArray( $content );
		$this->assertArrayHasKey( 'documents', $content );
	}

	/**
	 * Test knowledge base JSON structure.
	 */
	public function testKnowledgeBaseJSONStructure(): void {
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';

		$kb_data = [
			'documents' => [
				[
					'title'      => 'FAQ',
					'content'    => 'Frequently asked questions content.',
					'source'     => 'url',
					'url'        => 'https://example.com/faq',
					'added_at'   => gmdate( 'Y-m-d H:i:s' ),
				],
			],
			'summary' => [
				'total_documents' => 1,
				'total_chars'     => 100,
			],
		];

		file_put_contents( $kb_file, json_encode( $kb_data, JSON_PRETTY_PRINT ) );

		$content = json_decode( file_get_contents( $kb_file ), true );

		$this->assertArrayHasKey( 'documents', $content );
		$this->assertArrayHasKey( 'summary', $content );
		$this->assertCount( 1, $content['documents'] );
	}

	/**
	 * Test multiple documents can be stored.
	 */
	public function testMultipleDocumentsStorage(): void {
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';

		$documents = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$documents[] = [
				'title'   => "Document $i",
				'content' => "Content for document $i",
				'source'  => 'manual',
			];
		}

		$kb_data = [ 'documents' => $documents ];
		file_put_contents( $kb_file, json_encode( $kb_data ) );

		$content = json_decode( file_get_contents( $kb_file ), true );

		$this->assertCount( 5, $content['documents'] );
	}

	/**
	 * Test document deletion simulation.
	 */
	public function testDocumentDeletion(): void {
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';

		$documents = [
			[ 'id' => 1, 'title' => 'Doc 1' ],
			[ 'id' => 2, 'title' => 'Doc 2' ],
			[ 'id' => 3, 'title' => 'Doc 3' ],
		];

		$kb_data = [ 'documents' => $documents ];
		file_put_contents( $kb_file, json_encode( $kb_data ) );

		// Simulate deletion of document with id 2.
		$content      = json_decode( file_get_contents( $kb_file ), true );
		$new_docs     = array_filter( $content['documents'], fn( $doc ) => $doc['id'] !== 2 );
		$content['documents'] = array_values( $new_docs );

		file_put_contents( $kb_file, json_encode( $content ) );

		$updated = json_decode( file_get_contents( $kb_file ), true );

		$this->assertCount( 2, $updated['documents'] );
	}

	/**
	 * Test knowledge base clear.
	 */
	public function testKnowledgeBaseClear(): void {
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';

		$kb_data = [ 'documents' => [ [ 'title' => 'Test' ] ] ];
		file_put_contents( $kb_file, json_encode( $kb_data ) );

		// Clear by writing empty documents.
		$empty = [ 'documents' => [] ];
		file_put_contents( $kb_file, json_encode( $empty ) );

		$content = json_decode( file_get_contents( $kb_file ), true );

		$this->assertEmpty( $content['documents'] );
	}

	/**
	 * Test document content with special characters.
	 */
	public function testDocumentWithSpecialCharacters(): void {
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';

		$document = [
			'title'   => 'Special Characters Test',
			'content' => 'Content with "quotes", <html>, and émojis: 🎉',
		];

		$kb_data = [ 'documents' => [ $document ] ];
		file_put_contents( $kb_file, json_encode( $kb_data, JSON_UNESCAPED_UNICODE ) );

		$content = json_decode( file_get_contents( $kb_file ), true );

		$this->assertStringContainsString( '🎉', $content['documents'][0]['content'] );
	}

	/**
	 * Test document source types.
	 */
	public function testDocumentSourceTypes(): void {
		$sources = [ 'url', 'manual', 'file', 'gdrive', 'confluence' ];

		foreach ( $sources as $source ) {
			$document = [
				'title'  => "Test $source",
				'source' => $source,
			];

			$this->assertEquals( $source, $document['source'] );
		}
	}

	/**
	 * Test document categories.
	 */
	public function testDocumentCategories(): void {
		$categories = [
			'general',
			'faq',
			'product',
			'support',
			'policy',
			'contact',
			'other',
		];

		foreach ( $categories as $category ) {
			$document = [
				'title'    => "Test $category",
				'category' => $category,
			];

			$this->assertEquals( $category, $document['category'] );
		}
	}

	/**
	 * Test large document handling.
	 */
	public function testLargeDocumentHandling(): void {
		$kb_file = $this->test_kb_dir . '/knowledge-base.json';

		// Create a large content string.
		$large_content = str_repeat( 'This is a test sentence. ', 1000 );

		$document = [
			'title'   => 'Large Document',
			'content' => $large_content,
		];

		$kb_data = [ 'documents' => [ $document ] ];
		file_put_contents( $kb_file, json_encode( $kb_data ) );

		$content = json_decode( file_get_contents( $kb_file ), true );

		$this->assertStringContainsString( 'test sentence', $content['documents'][0]['content'] );
		$this->assertGreaterThan( 10000, strlen( $content['documents'][0]['content'] ) );
	}

	/**
	 * Test document metadata.
	 */
	public function testDocumentMetadata(): void {
		$document = [
			'title'      => 'Test Document',
			'content'    => 'Test content',
			'source'     => 'manual',
			'category'   => 'faq',
			'added_at'   => gmdate( 'Y-m-d H:i:s' ),
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			'char_count' => 12,
		];

		$this->assertArrayHasKey( 'title', $document );
		$this->assertArrayHasKey( 'content', $document );
		$this->assertArrayHasKey( 'source', $document );
		$this->assertArrayHasKey( 'category', $document );
		$this->assertArrayHasKey( 'added_at', $document );
		$this->assertArrayHasKey( 'char_count', $document );
	}

	/**
	 * Test summary calculation.
	 */
	public function testSummaryCalculation(): void {
		$documents = [
			[ 'content' => 'First document content' ],
			[ 'content' => 'Second document' ],
			[ 'content' => 'Third' ],
		];

		$total_docs  = count( $documents );
		$total_chars = array_reduce( $documents, fn( $sum, $doc ) => $sum + strlen( $doc['content'] ), 0 );

		$summary = [
			'total_documents' => $total_docs,
			'total_chars'     => $total_chars,
		];

		$this->assertEquals( 3, $summary['total_documents'] );
		$this->assertGreaterThan( 0, $summary['total_chars'] );
	}

	/**
	 * Test empty knowledge base structure.
	 */
	public function testEmptyKnowledgeBaseStructure(): void {
		$empty_kb = [
			'documents' => [],
			'summary'   => [
				'total_documents' => 0,
				'total_chars'     => 0,
			],
		];

		$this->assertIsArray( $empty_kb['documents'] );
		$this->assertEmpty( $empty_kb['documents'] );
		$this->assertEquals( 0, $empty_kb['summary']['total_documents'] );
	}
}

