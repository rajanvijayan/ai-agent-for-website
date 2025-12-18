<?php
/**
 * Plugin Test
 *
 * @package AIAgent
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for the main plugin functionality.
 */
class PluginTest extends TestCase
{
    /**
     * Test that the plugin file exists.
     */
    public function testPluginFileExists(): void
    {
        $plugin_file = dirname(__DIR__) . '/ai-agent-for-website.php';
        $this->assertFileExists($plugin_file);
    }

    /**
     * Test that the plugin version constant is defined.
     */
    public function testVersionConstant(): void
    {
        // This would normally require WordPress to be loaded
        // For now, we just verify the plugin file contains the version
        $plugin_content = file_get_contents(dirname(__DIR__) . '/ai-agent-for-website.php');
        $this->assertStringContainsString("define('AIAGENT_VERSION'", $plugin_content);
    }

    /**
     * Test that required files exist.
     */
    public function testRequiredFilesExist(): void
    {
        $base_dir = dirname(__DIR__);
        
        $required_files = [
            '/includes/class-admin-settings.php',
            '/includes/class-chat-widget.php',
            '/includes/class-rest-api.php',
            '/includes/class-knowledge-manager.php',
            '/includes/class-conversations-manager.php',
        ];

        foreach ($required_files as $file) {
            $this->assertFileExists(
                $base_dir . $file,
                "Required file {$file} does not exist"
            );
        }
    }

    /**
     * Test that assets directories exist.
     */
    public function testAssetsDirectoriesExist(): void
    {
        $base_dir = dirname(__DIR__);
        
        $this->assertDirectoryExists($base_dir . '/assets/css');
        $this->assertDirectoryExists($base_dir . '/assets/js');
    }

    /**
     * Test that CSS files exist.
     */
    public function testCssFilesExist(): void
    {
        $base_dir = dirname(__DIR__);
        
        $this->assertFileExists($base_dir . '/assets/css/admin.css');
        $this->assertFileExists($base_dir . '/assets/css/chat-widget.css');
    }

    /**
     * Test that JS files exist.
     */
    public function testJsFilesExist(): void
    {
        $base_dir = dirname(__DIR__);
        
        $this->assertFileExists($base_dir . '/assets/js/admin.js');
        $this->assertFileExists($base_dir . '/assets/js/chat-widget.js');
    }
}

