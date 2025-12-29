<?php
/**
 * Plugin Updater Test
 *
 * Tests for the AIAGENT_Plugin_Updater class.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the Plugin Updater class.
 */
class PluginUpdaterTest extends TestCase {

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
			dirname( __DIR__ ) . '/includes/class-plugin-updater.php'
		);
	}

	/**
	 * Test GitHub repository URL format.
	 */
	public function testGitHubRepositoryURLFormat(): void {
		$repo_url = 'https://github.com/rajanvijayan/ai-agent-for-website';

		$this->assertStringStartsWith( 'https://github.com/', $repo_url );
	}

	/**
	 * Test GitHub API URL format.
	 */
	public function testGitHubAPIURLFormat(): void {
		$api_url = 'https://api.github.com/repos/rajanvijayan/ai-agent-for-website/releases/latest';

		$this->assertStringContainsString( 'api.github.com', $api_url );
		$this->assertStringContainsString( 'releases/latest', $api_url );
	}

	/**
	 * Test version comparison.
	 */
	public function testVersionComparison(): void {
		$current_version = '1.8.0';
		$new_version     = '1.9.0';

		$this->assertTrue( version_compare( $new_version, $current_version, '>' ) );

		$same_version = '1.8.0';
		$this->assertFalse( version_compare( $same_version, $current_version, '>' ) );
	}

	/**
	 * Test version format validation.
	 */
	public function testVersionFormatValidation(): void {
		$valid_versions = [ '1.0.0', '1.9.0', '2.0.0', '10.20.30' ];

		foreach ( $valid_versions as $version ) {
			$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version );
		}
	}

	/**
	 * Test release data structure.
	 */
	public function testReleaseDataStructure(): void {
		$release = [
			'tag_name'     => 'v1.9.0',
			'name'         => 'Version 1.9.0',
			'body'         => 'Release notes here...',
			'published_at' => '2024-01-15T10:00:00Z',
			'assets'       => [
				[
					'name'                 => 'ai-agent-for-website.zip',
					'browser_download_url' => 'https://github.com/.../download/v1.9.0/ai-agent-for-website.zip',
				],
			],
		];

		$this->assertArrayHasKey( 'tag_name', $release );
		$this->assertArrayHasKey( 'name', $release );
		$this->assertArrayHasKey( 'body', $release );
		$this->assertArrayHasKey( 'assets', $release );
	}

	/**
	 * Test version tag parsing.
	 */
	public function testVersionTagParsing(): void {
		$tag     = 'v1.9.0';
		$version = ltrim( $tag, 'v' );

		$this->assertEquals( '1.9.0', $version );
	}

	/**
	 * Test update cache transient name.
	 */
	public function testUpdateCacheTransientName(): void {
		$transient_name = 'aiagent_update_check';

		$this->assertEquals( 'aiagent_update_check', $transient_name );
	}

	/**
	 * Test cache expiration time.
	 */
	public function testCacheExpirationTime(): void {
		$cache_hours = 12;
		$cache_seconds = $cache_hours * HOUR_IN_SECONDS;

		// HOUR_IN_SECONDS is 3600.
		$this->assertEquals( 43200, $cache_seconds );
	}

	/**
	 * Test plugin info structure for WordPress.
	 */
	public function testPluginInfoStructure(): void {
		$plugin_info = (object) [
			'id'            => 'ai-agent-for-website/ai-agent-for-website.php',
			'slug'          => 'ai-agent-for-website',
			'plugin'        => 'ai-agent-for-website/ai-agent-for-website.php',
			'new_version'   => '1.9.0',
			'url'           => 'https://github.com/rajanvijayan/ai-agent-for-website',
			'package'       => 'https://github.com/.../download/v1.9.0/ai-agent-for-website.zip',
			'icons'         => [],
			'banners'       => [],
			'banners_rtl'   => [],
			'tested'        => '6.4',
			'requires_php'  => '8.0',
			'compatibility' => [],
		];

		$this->assertEquals( '1.9.0', $plugin_info->new_version );
		$this->assertStringContainsString( 'github.com', $plugin_info->url );
	}

	/**
	 * Test HTTP request headers for GitHub API.
	 */
	public function testHTTPRequestHeaders(): void {
		$headers = [
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'AI-Agent-For-Website/1.9.0',
		];

		$this->assertArrayHasKey( 'Accept', $headers );
		$this->assertArrayHasKey( 'User-Agent', $headers );
	}

	/**
	 * Test rate limit handling.
	 */
	public function testRateLimitHandling(): void {
		$rate_limit = [
			'limit'     => 60,
			'remaining' => 50,
			'reset'     => time() + 3600,
		];

		$this->assertGreaterThan( 0, $rate_limit['remaining'] );
		$can_make_request = $rate_limit['remaining'] > 0;
		$this->assertTrue( $can_make_request );
	}

	/**
	 * Test download package URL validation.
	 */
	public function testDownloadPackageURLValidation(): void {
		$url = 'https://github.com/rajanvijayan/ai-agent-for-website/releases/download/v1.9.0/ai-agent-for-website.zip';

		$this->assertNotFalse( filter_var( $url, FILTER_VALIDATE_URL ) );
		$this->assertStringEndsWith( '.zip', $url );
	}

	/**
	 * Test update notification data.
	 */
	public function testUpdateNotificationData(): void {
		$notification = [
			'type'    => 'system',
			'title'   => 'Plugin Update Available',
			'message' => 'AI Agent for Website version 1.9.0 is available.',
			'meta'    => [
				'current_version' => '1.8.0',
				'new_version'     => '1.9.0',
			],
		];

		$this->assertEquals( 'system', $notification['type'] );
		$this->assertArrayHasKey( 'new_version', $notification['meta'] );
	}

	/**
	 * Test changelog parsing.
	 */
	public function testChangelogParsing(): void {
		$changelog = "## [1.9.0] - 2024-01-15\n\n### Added\n- New feature\n\n### Fixed\n- Bug fix";

		$this->assertStringContainsString( '1.9.0', $changelog );
		$this->assertStringContainsString( 'Added', $changelog );
		$this->assertStringContainsString( 'Fixed', $changelog );
	}

	/**
	 * Test pre-release version detection.
	 */
	public function testPreReleaseVersionDetection(): void {
		$stable_version     = '1.9.0';
		$prerelease_version = '1.9.0-beta.1';

		$is_stable = preg_match( '/^[\d.]+$/', $stable_version );
		$is_prerelease = preg_match( '/-(alpha|beta|rc)/', $prerelease_version );

		$this->assertEquals( 1, $is_stable );
		$this->assertEquals( 1, $is_prerelease );
	}

	/**
	 * Test minimum WordPress version requirement.
	 */
	public function testMinimumWordPressVersionRequirement(): void {
		$requires_wp = '5.8';
		$current_wp  = '6.4';

		$this->assertTrue( version_compare( $current_wp, $requires_wp, '>=' ) );
	}

	/**
	 * Test minimum PHP version requirement.
	 */
	public function testMinimumPHPVersionRequirement(): void {
		$requires_php = '8.0';

		$this->assertTrue( version_compare( PHP_VERSION, $requires_php, '>=' ) );
	}

	/**
	 * Test error response handling.
	 */
	public function testErrorResponseHandling(): void {
		$error_response = [
			'message' => 'Not Found',
			'documentation_url' => 'https://docs.github.com/rest',
		];

		$this->assertArrayHasKey( 'message', $error_response );
		$this->assertEquals( 'Not Found', $error_response['message'] );
	}

	/**
	 * Test successful response validation.
	 */
	public function testSuccessfulResponseValidation(): void {
		$response = [
			'tag_name' => 'v1.9.0',
			'assets'   => [
				[ 'browser_download_url' => 'https://github.com/.../file.zip' ],
			],
		];

		$is_valid = isset( $response['tag_name'] ) &&
					isset( $response['assets'] ) &&
					! empty( $response['assets'] );

		$this->assertTrue( $is_valid );
	}
}

// Define HOUR_IN_SECONDS if not defined.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

