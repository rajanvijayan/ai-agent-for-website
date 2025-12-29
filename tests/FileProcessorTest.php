<?php
/**
 * File Processor Test
 *
 * Tests for the AIAGENT_File_Processor class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Include the file processor class.
require_once dirname( __DIR__ ) . '/includes/class-file-processor.php';

/**
 * Test case for the File Processor class.
 */
class FileProcessorTest extends TestCase {

	/**
	 * File processor instance.
	 *
	 * @var AIAGENT_File_Processor
	 */
	private $processor;

	/**
	 * Test upload directory.
	 *
	 * @var string
	 */
	private $test_dir;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_mocks();
		$this->processor = new AIAGENT_File_Processor();
		$this->test_dir  = sys_get_temp_dir() . '/aiagent-test-files';

		if ( ! file_exists( $this->test_dir ) ) {
			mkdir( $this->test_dir, 0755, true );
		}
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		// Clean up test files.
		if ( file_exists( $this->test_dir ) ) {
			$files = glob( $this->test_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}
		parent::tearDown();
	}

	/**
	 * Test that the class exists.
	 */
	public function testClassExists(): void {
		$this->assertTrue( class_exists( 'AIAGENT_File_Processor' ) );
	}

	/**
	 * Test that the processor can be instantiated.
	 */
	public function testCanBeInstantiated(): void {
		$this->assertInstanceOf( AIAGENT_File_Processor::class, $this->processor );
	}

	/**
	 * Test get supported extensions.
	 */
	public function testGetSupportedExtensions(): void {
		$extensions = $this->processor->get_supported_extensions();

		$this->assertIsArray( $extensions );
		$this->assertContains( 'txt', $extensions );
		$this->assertContains( 'pdf', $extensions );
		$this->assertContains( 'csv', $extensions );
		$this->assertContains( 'md', $extensions );
		$this->assertContains( 'doc', $extensions );
		$this->assertContains( 'docx', $extensions );
		$this->assertContains( 'rtf', $extensions );
	}

	/**
	 * Test get max file size.
	 */
	public function testGetMaxFileSize(): void {
		$max_size = $this->processor->get_max_file_size();

		$this->assertIsInt( $max_size );
		$this->assertGreaterThan( 0, $max_size );
		$this->assertEquals( 10485760, $max_size ); // 10MB.
	}

	/**
	 * Test process valid text file.
	 */
	public function testProcessValidTextFile(): void {
		// Create a test text file.
		$test_file = $this->test_dir . '/test.txt';
		file_put_contents( $test_file, 'Hello, this is test content.' );

		$file = [
			'name'     => 'test.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'Hello, this is test content.', $result['content'] );
		$this->assertEquals( 'txt', $result['file_type'] );
	}

	/**
	 * Test process CSV file.
	 */
	public function testProcessCSVFile(): void {
		// Create a test CSV file.
		$test_file = $this->test_dir . '/test.csv';
		$content   = "Name,Email,Phone\nJohn Doe,john@test.com,123456";
		file_put_contents( $test_file, $content );

		$file = [
			'name'     => 'test.csv',
			'type'     => 'text/csv',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'John Doe', $result['content'] );
		$this->assertStringContainsString( 'john@test.com', $result['content'] );
	}

	/**
	 * Test process Markdown file.
	 */
	public function testProcessMarkdownFile(): void {
		// Create a test markdown file.
		$test_file = $this->test_dir . '/test.md';
		$content   = "# Header\n\nThis is **bold** text.";
		file_put_contents( $test_file, $content );

		$file = [
			'name'     => 'test.md',
			'type'     => 'text/markdown',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '# Header', $result['content'] );
	}

	/**
	 * Test process file with upload error.
	 */
	public function testProcessFileWithUploadError(): void {
		$file = [
			'name'     => 'test.txt',
			'type'     => 'text/plain',
			'tmp_name' => '',
			'error'    => UPLOAD_ERR_INI_SIZE,
			'size'     => 0,
		];

		$result = $this->processor->process_file( $file );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test process file with no file error.
	 */
	public function testProcessFileNoFile(): void {
		$file = [
			'name'     => '',
			'type'     => '',
			'tmp_name' => '',
			'error'    => UPLOAD_ERR_NO_FILE,
			'size'     => 0,
		];

		$result = $this->processor->process_file( $file );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'No file', $result['error'] );
	}

	/**
	 * Test process unsupported file type.
	 */
	public function testProcessUnsupportedFileType(): void {
		// Create a test file with unsupported extension.
		$test_file = $this->test_dir . '/test.xyz';
		file_put_contents( $test_file, 'test content' );

		$file = [
			'name'     => 'test.xyz',
			'type'     => 'application/octet-stream',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Unsupported', $result['error'] );
	}

	/**
	 * Test process file too large.
	 */
	public function testProcessFileTooLarge(): void {
		// Create a mock file array with large size.
		$test_file = $this->test_dir . '/large.txt';
		file_put_contents( $test_file, 'test' );

		$file = [
			'name'     => 'large.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => 20000000, // 20MB - exceeds limit.
		];

		$result = $this->processor->process_file( $file );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'exceeds', $result['error'] );
	}

	/**
	 * Test process empty text file.
	 */
	public function testProcessEmptyTextFile(): void {
		// Create an empty text file.
		$test_file = $this->test_dir . '/empty.txt';
		file_put_contents( $test_file, '' );

		$file = [
			'name'     => 'empty.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => 0,
		];

		$result = $this->processor->process_file( $file );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'No text content', $result['error'] );
	}

	/**
	 * Test file result contains expected keys.
	 */
	public function testFileResultStructure(): void {
		$test_file = $this->test_dir . '/test.txt';
		file_put_contents( $test_file, 'Test content here' );

		$file = [
			'name'     => 'test.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertArrayHasKey( 'filename', $result );
		$this->assertArrayHasKey( 'original_name', $result );
		$this->assertArrayHasKey( 'file_type', $result );
		$this->assertArrayHasKey( 'file_size', $result );
		$this->assertArrayHasKey( 'char_count', $result );
	}

	/**
	 * Test get uploaded files returns array.
	 */
	public function testGetUploadedFilesReturnsArray(): void {
		$files = $this->processor->get_uploaded_files();
		$this->assertIsArray( $files );
	}

	/**
	 * Test text cleaning removes extra whitespace.
	 */
	public function testTextCleaningRemovesExtraWhitespace(): void {
		$test_file = $this->test_dir . '/whitespace.txt';
		file_put_contents( $test_file, "Text   with   many    spaces\n\n\n\nAnd lines" );

		$file = [
			'name'     => 'whitespace.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertTrue( $result['success'] );
		$this->assertStringNotContainsString( '   ', $result['content'] );
		$this->assertStringNotContainsString( "\n\n\n", $result['content'] );
	}

	/**
	 * Test file with special characters in content.
	 */
	public function testFileWithSpecialCharacters(): void {
		$test_file = $this->test_dir . '/special.txt';
		$content   = "Special chars: äöü é ñ 日本語 émoji: 🎉";
		file_put_contents( $test_file, $content );

		$file = [
			'name'     => 'special.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'Special chars', $result['content'] );
	}

	/**
	 * Test character count is accurate.
	 */
	public function testCharacterCountAccuracy(): void {
		$test_file = $this->test_dir . '/count.txt';
		$content   = 'Exactly 20 chars!! ';
		file_put_contents( $test_file, $content );

		$file = [
			'name'     => 'count.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( strlen( trim( $content ) ), $result['char_count'] );
	}

	/**
	 * Test CSV with different delimiters.
	 */
	public function testCSVProcessing(): void {
		$test_file = $this->test_dir . '/data.csv';
		$content   = "Header1,Header2,Header3\nVal1,Val2,Val3\nA,B,C";
		file_put_contents( $test_file, $content );

		$file = [
			'name'     => 'data.csv',
			'type'     => 'text/csv',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'Header1', $result['content'] );
		$this->assertStringContainsString( 'Val1', $result['content'] );
	}

	/**
	 * Test multiple extensions are supported.
	 */
	public function testAllSupportedExtensionsExist(): void {
		$expected = [ 'txt', 'csv', 'md', 'pdf', 'doc', 'docx', 'rtf' ];
		$actual   = $this->processor->get_supported_extensions();

		foreach ( $expected as $ext ) {
			$this->assertContains( $ext, $actual, "Extension $ext should be supported" );
		}
	}

	/**
	 * Test file path is returned in result.
	 */
	public function testFilePathInResult(): void {
		$test_file = $this->test_dir . '/path.txt';
		file_put_contents( $test_file, 'Content for path test' );

		$file = [
			'name'     => 'path.txt',
			'type'     => 'text/plain',
			'tmp_name' => $test_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $test_file ),
		];

		$result = $this->processor->process_file( $file );

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'file_path', $result );
	}
}

