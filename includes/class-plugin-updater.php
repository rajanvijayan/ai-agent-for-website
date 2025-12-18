<?php
/**
 * Plugin Updater Class
 *
 * Handles checking for updates from GitHub releases and integrating
 * with WordPress update system for seamless plugin updates.
 *
 * @package AI_Agent_For_Website
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_Plugin_Updater
 *
 * Checks GitHub releases for plugin updates and integrates with WordPress.
 */
class AIAGENT_Plugin_Updater {

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	private $github_username = 'rajanvijayan';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private $github_repo = 'ai-agent-for-website';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * GitHub API URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Cached GitHub response.
	 *
	 * @var object|null
	 */
	private $github_response = null;

	/**
	 * Cache key for transient.
	 *
	 * @var string
	 */
	private $cache_key = 'aiagent_github_update_check';

	/**
	 * Cache expiration in seconds (12 hours).
	 *
	 * @var int
	 */
	private $cache_expiration = 43200;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_slug     = 'ai-agent-for-website';
		$this->plugin_basename = AIAGENT_PLUGIN_BASENAME;
		$this->current_version = AIAGENT_VERSION;
		$this->api_url         = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			$this->github_username,
			$this->github_repo
		);

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Check for updates.
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );

		// Plugin information popup.
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );

		// After installation, activate the plugin.
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );

		// Add update message to plugin row.
		add_action( 'in_plugin_update_message-' . $this->plugin_basename, [ $this, 'update_message' ], 10, 2 );

		// Clear cache on plugin activation/deactivation.
		add_action( 'activated_plugin', [ $this, 'clear_cache' ] );
		add_action( 'deactivated_plugin', [ $this, 'clear_cache' ] );
	}

	/**
	 * Get GitHub release information.
	 *
	 * @return object|false Release data or false on failure.
	 */
	private function get_github_release() {
		// Return cached response if available.
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		// Check transient cache.
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached ) {
			$this->github_response = $cached;
			return $this->github_response;
		}

		// Fetch from GitHub API.
		$response = wp_remote_get(
			$this->api_url,
			[
				'headers' => [
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				],
				'timeout' => 10,
			]
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) || ! isset( $data->tag_name ) ) {
			return false;
		}

		// Parse release data.
		$release = new stdClass();

		// Remove 'v' prefix from tag name.
		$release->version = ltrim( $data->tag_name, 'v' );

		// Get the ZIP download URL from assets or use zipball.
		$release->download_url = $this->get_download_url( $data );
		$release->changelog    = isset( $data->body ) ? $data->body : '';
		$release->published_at = isset( $data->published_at ) ? $data->published_at : '';
		$release->html_url     = isset( $data->html_url ) ? $data->html_url : '';
		$release->name         = isset( $data->name ) ? $data->name : $data->tag_name;

		// Cache the response.
		set_transient( $this->cache_key, $release, $this->cache_expiration );
		$this->github_response = $release;

		return $release;
	}

	/**
	 * Get download URL from release assets or fallback to zipball.
	 *
	 * @param object $data GitHub release data.
	 * @return string Download URL.
	 */
	private function get_download_url( $data ) {
		// Look for the plugin ZIP in release assets.
		if ( ! empty( $data->assets ) && is_array( $data->assets ) ) {
			foreach ( $data->assets as $asset ) {
				// Look for our plugin ZIP file.
				if ( isset( $asset->name ) && strpos( $asset->name, 'ai-agent-for-website' ) !== false && strpos( $asset->name, '.zip' ) !== false ) {
					return $asset->browser_download_url;
				}
			}
		}

		// Fallback to zipball URL.
		if ( isset( $data->zipball_url ) ) {
			return $data->zipball_url;
		}

		return '';
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_github_release();

		if ( false === $release || empty( $release->version ) ) {
			return $transient;
		}

		// Compare versions.
		if ( version_compare( $release->version, $this->current_version, '>' ) ) {
			$plugin_data = [
				'id'            => $this->plugin_basename,
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_basename,
				'new_version'   => $release->version,
				'url'           => sprintf( 'https://github.com/%s/%s', $this->github_username, $this->github_repo ),
				'package'       => $release->download_url,
				'icons'         => [
					'default' => AIAGENT_PLUGIN_URL . 'assets/images/icon-128x128.png',
				],
				'banners'       => [
					'low'  => AIAGENT_PLUGIN_URL . 'assets/images/banner-772x250.png',
					'high' => AIAGENT_PLUGIN_URL . 'assets/images/banner-1544x500.png',
				],
				'tested'        => '6.5',
				'requires_php'  => '8.0',
				'compatibility' => new stdClass(),
			];

			$transient->response[ $this->plugin_basename ] = (object) $plugin_data;
		} else {
			// No update available - add to no_update list.
			$transient->no_update[ $this->plugin_basename ] = (object) [
				'id'            => $this->plugin_basename,
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_basename,
				'new_version'   => $this->current_version,
				'url'           => sprintf( 'https://github.com/%s/%s', $this->github_username, $this->github_repo ),
				'package'       => '',
				'icons'         => [],
				'banners'       => [],
				'tested'        => '6.5',
				'requires_php'  => '8.0',
				'compatibility' => new stdClass(),
			];
		}

		return $transient;
	}

	/**
	 * Plugin information for the popup.
	 *
	 * @param false|object|array $result Result object or array.
	 * @param string             $action API action.
	 * @param object             $args   API arguments.
	 * @return false|object Plugin info or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		// Only handle plugin_information action.
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		// Check if this is our plugin.
		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_github_release();

		if ( false === $release ) {
			return $result;
		}

		$plugin_info = new stdClass();

		$plugin_info->name           = 'AI Agent for Website';
		$plugin_info->slug           = $this->plugin_slug;
		$plugin_info->version        = $release->version;
		$plugin_info->author         = '<a href="https://rajanvijayan.com">Rajan Vijayan</a>';
		$plugin_info->author_profile = 'https://rajanvijayan.com';
		$plugin_info->homepage       = sprintf( 'https://github.com/%s/%s', $this->github_username, $this->github_repo );
		$plugin_info->requires       = '5.8';
		$plugin_info->tested         = '6.5';
		$plugin_info->requires_php   = '8.0';
		$plugin_info->downloaded     = 0;
		$plugin_info->last_updated   = $release->published_at;
		$plugin_info->download_link  = $release->download_url;

		// Parse markdown changelog to HTML.
		$plugin_info->sections = [
			'description' => $this->get_plugin_description(),
			'changelog'   => $this->parse_changelog( $release->changelog ),
		];

		$plugin_info->banners = [
			'low'  => AIAGENT_PLUGIN_URL . 'assets/images/banner-772x250.png',
			'high' => AIAGENT_PLUGIN_URL . 'assets/images/banner-1544x500.png',
		];

		return $plugin_info;
	}

	/**
	 * Get plugin description.
	 *
	 * @return string Plugin description HTML.
	 */
	private function get_plugin_description() {
		return '<p>Add an AI-powered chat agent to your website using Groq API. Train it with your website content for intelligent conversations with your visitors.</p>
		<h4>Features</h4>
		<ul>
			<li>AI-powered chat widget with customizable appearance</li>
			<li>Integration with Groq, Gemini, and Meta Llama AI providers</li>
			<li>Knowledge base management with URL content fetching</li>
			<li>Conversation history and user management</li>
			<li>REST API endpoints for chat functionality</li>
			<li>Shortcode support for inline embedding</li>
			<li>Customizable widget position and colors</li>
		</ul>';
	}

	/**
	 * Parse markdown changelog to HTML.
	 *
	 * @param string $changelog Markdown changelog.
	 * @return string HTML changelog.
	 */
	private function parse_changelog( $changelog ) {
		if ( empty( $changelog ) ) {
			return '<p>No changelog available for this release.</p>';
		}

		// Simple markdown to HTML conversion.
		$html = $changelog;

		// Convert headers.
		$html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
		$html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );

		// Convert lists.
		$html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
		$html = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html );

		// Convert bold.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );

		// Convert code.
		$html = preg_replace( '/`(.+?)`/', '<code>$1</code>', $html );

		// Convert newlines to paragraphs.
		$html = wpautop( $html );

		return $html;
	}

	/**
	 * Handle post-installation tasks.
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments.
	 * @param array $result     Installation result.
	 * @return array Modified result.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		// Check if this is our plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $result;
		}

		// Move plugin to correct directory if needed.
		$plugin_dir = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
		$source     = $result['destination'];

		// Check if source directory name is different (GitHub zipball has different name).
		if ( $source !== $plugin_dir ) {
			$wp_filesystem->move( $source, $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		// Clear update cache.
		$this->clear_cache();

		return $result;
	}

	/**
	 * Display update message in plugin row.
	 *
	 * @param array  $plugin_data Plugin data.
	 * @param object $response    Response object.
	 * @return void
	 */
	public function update_message( $plugin_data, $response ) {
		$release = $this->get_github_release();

		if ( false === $release || empty( $release->html_url ) ) {
			return;
		}

		printf(
			' <a href="%s" target="_blank">%s</a>',
			esc_url( $release->html_url ),
			esc_html__( 'View release notes on GitHub', 'ai-agent-for-website' )
		);
	}

	/**
	 * Clear update cache.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( $this->cache_key );
		$this->github_response = null;

		// Also clear WordPress plugin update cache.
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Force check for updates.
	 *
	 * @return object|false Release data or false.
	 */
	public function force_check() {
		$this->clear_cache();
		return $this->get_github_release();
	}

	/**
	 * Get current update status.
	 *
	 * @return array Update status information.
	 */
	public function get_update_status() {
		$release = $this->get_github_release();

		$status = [
			'current_version' => $this->current_version,
			'latest_version'  => false === $release ? $this->current_version : $release->version,
			'update_available' => false,
			'download_url'    => false === $release ? '' : $release->download_url,
			'release_url'     => false === $release ? '' : $release->html_url,
			'changelog'       => false === $release ? '' : $release->changelog,
		];

		if ( false !== $release && version_compare( $release->version, $this->current_version, '>' ) ) {
			$status['update_available'] = true;
		}

		return $status;
	}
}

