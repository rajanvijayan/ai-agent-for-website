<?php
/**
 * PHPUnit Bootstrap File
 *
 * Provides WordPress function mocks and test environment setup.
 *
 * @package AI_Agent_For_Website
 */

declare(strict_types=1);

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'AIAGENT_PLUGIN_DIR' ) ) {
	define( 'AIAGENT_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'AIAGENT_PLUGIN_URL' ) ) {
	define( 'AIAGENT_PLUGIN_URL', 'http://localhost/wp-content/plugins/ai-agent-for-website/' );
}

if ( ! defined( 'AIAGENT_PLUGIN_BASENAME' ) ) {
	define( 'AIAGENT_PLUGIN_BASENAME', 'ai-agent-for-website/ai-agent-for-website.php' );
}

if ( ! defined( 'AIAGENT_VERSION' ) ) {
	define( 'AIAGENT_VERSION', '1.9.0' );
}

// Define test mode BEFORE loading any config files to ensure it takes precedence.
if ( ! defined( 'AIAGENT_TEST_MODE' ) ) {
	define( 'AIAGENT_TEST_MODE', true );
}

// Default test API keys.
if ( ! defined( 'AIAGENT_GROQ_API_KEY' ) ) {
	define( 'AIAGENT_GROQ_API_KEY', 'test-api-key-for-unit-tests' );
}

/**
 * Mock WordPress Functions
 *
 * These functions simulate WordPress behavior for unit testing.
 */

// Storage for mock options.
$GLOBALS['wp_mock_options'] = [];

// Storage for mock transients.
$GLOBALS['wp_mock_transients'] = [];

// Storage for mock hooks.
$GLOBALS['wp_mock_hooks'] = [];

/**
 * Mock wpdb class.
 */
if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {
		public $prefix = 'wp_';
		public $insert_id = 0;

		public function get_var( $query ) {
			return null;
		}

		public function get_row( $query, $output = OBJECT, $y = 0 ) {
			return null;
		}

		public function get_results( $query, $output = OBJECT ) {
			return [];
		}

		public function prepare( $query, ...$args ) {
			return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
		}

		public function insert( $table, $data, $format = null ) {
			$this->insert_id = 1;
			return 1;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			return 1;
		}

		public function delete( $table, $where, $where_format = null ) {
			return 1;
		}

		public function query( $query ) {
			return true;
		}

		public function get_charset_collate() {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}
	}
}

// Initialize global wpdb.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new wpdb();
}

/**
 * Mock get_option function.
 *
 * @param string $option  Option name.
 * @param mixed  $default Default value.
 * @return mixed Option value.
 */
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['wp_mock_options'][ $option ] ?? $default;
	}
}

/**
 * Mock update_option function.
 *
 * @param string $option Option name.
 * @param mixed  $value  Option value.
 * @return bool Success.
 */
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		$GLOBALS['wp_mock_options'][ $option ] = $value;
		return true;
	}
}

/**
 * Mock add_option function.
 *
 * @param string $option Option name.
 * @param mixed  $value  Option value.
 * @return bool Success.
 */
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value = '' ) {
		if ( ! isset( $GLOBALS['wp_mock_options'][ $option ] ) ) {
			$GLOBALS['wp_mock_options'][ $option ] = $value;
			return true;
		}
		return false;
	}
}

/**
 * Mock delete_option function.
 *
 * @param string $option Option name.
 * @return bool Success.
 */
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		unset( $GLOBALS['wp_mock_options'][ $option ] );
		return true;
	}
}

/**
 * Mock get_transient function.
 *
 * @param string $transient Transient name.
 * @return mixed Transient value.
 */
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		$data = $GLOBALS['wp_mock_transients'][ $transient ] ?? null;
		if ( $data && isset( $data['expiration'] ) && $data['expiration'] < time() ) {
			unset( $GLOBALS['wp_mock_transients'][ $transient ] );
			return false;
		}
		return $data['value'] ?? false;
	}
}

/**
 * Mock set_transient function.
 *
 * @param string $transient  Transient name.
 * @param mixed  $value      Transient value.
 * @param int    $expiration Expiration in seconds.
 * @return bool Success.
 */
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['wp_mock_transients'][ $transient ] = [
			'value'      => $value,
			'expiration' => $expiration > 0 ? time() + $expiration : 0,
		];
		return true;
	}
}

/**
 * Mock delete_transient function.
 *
 * @param string $transient Transient name.
 * @return bool Success.
 */
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['wp_mock_transients'][ $transient ] );
		return true;
	}
}

/**
 * Mock wp_upload_dir function.
 *
 * @return array Upload directory information.
 */
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$base = sys_get_temp_dir() . '/wp-uploads';
		if ( ! file_exists( $base ) ) {
			mkdir( $base, 0755, true );
		}
		return [
			'path'    => $base,
			'url'     => 'http://localhost/wp-content/uploads',
			'subdir'  => '',
			'basedir' => $base,
			'baseurl' => 'http://localhost/wp-content/uploads',
			'error'   => false,
		];
	}
}

/**
 * Mock wp_mkdir_p function.
 *
 * @param string $target Directory path.
 * @return bool Success.
 */
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		if ( file_exists( $target ) ) {
			return is_dir( $target );
		}
		return mkdir( $target, 0755, true );
	}
}

/**
 * Mock wp_unique_filename function.
 *
 * @param string $dir      Directory.
 * @param string $filename Filename.
 * @return string Unique filename.
 */
if ( ! function_exists( 'wp_unique_filename' ) ) {
	function wp_unique_filename( $dir, $filename ) {
		$info     = pathinfo( $filename );
		$ext      = ! empty( $info['extension'] ) ? '.' . $info['extension'] : '';
		$name     = $info['filename'];
		$number   = 0;
		$new_name = $filename;

		while ( file_exists( $dir . '/' . $new_name ) ) {
			$number++;
			$new_name = $name . '-' . $number . $ext;
		}

		return $new_name;
	}
}

/**
 * Mock sanitize_file_name function.
 *
 * @param string $filename Filename.
 * @return string Sanitized filename.
 */
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );
	}
}

/**
 * Mock sanitize_text_field function.
 *
 * @param string $str String to sanitize.
 * @return string Sanitized string.
 */
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

/**
 * Mock sanitize_email function.
 *
 * @param string $email Email to sanitize.
 * @return string Sanitized email.
 */
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}

/**
 * Mock sanitize_key function.
 *
 * @param string $key Key to sanitize.
 * @return string Sanitized key.
 */
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) );
	}
}

/**
 * Mock absint function.
 *
 * @param mixed $value Value.
 * @return int Absolute integer.
 */
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

/**
 * Mock esc_html function.
 *
 * @param string $text Text.
 * @return string Escaped text.
 */
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

/**
 * Mock esc_attr function.
 *
 * @param string $text Text.
 * @return string Escaped text.
 */
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

/**
 * Mock esc_url function.
 *
 * @param string $url URL.
 * @return string Escaped URL.
 */
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

/**
 * Mock esc_html__ function.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string Translated and escaped text.
 */
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

/**
 * Mock esc_attr__ function.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string Translated and escaped text.
 */
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( $text );
	}
}

/**
 * Mock __ function (translation).
 *
 * @param string $text   Text to translate.
 * @param string $domain Text domain.
 * @return string Translated text.
 */
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

/**
 * Mock _e function (echo translation).
 *
 * @param string $text   Text to translate.
 * @param string $domain Text domain.
 */
if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

/**
 * Mock esc_html_e function.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 */
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}

/**
 * Mock esc_attr_e function.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 */
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( $text );
	}
}

/**
 * Mock wp_strip_all_tags function.
 *
 * @param string $string String.
 * @return string String without tags.
 */
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return strip_tags( $string );
	}
}

/**
 * Mock wp_trim_words function.
 *
 * @param string $text      Text.
 * @param int    $num_words Number of words.
 * @param string $more      More text.
 * @return string Trimmed text.
 */
if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = '...' ) {
		$words = preg_split( '/\s+/', $text );
		if ( count( $words ) > $num_words ) {
			$words = array_slice( $words, 0, $num_words );
			return implode( ' ', $words ) . $more;
		}
		return $text;
	}
}

/**
 * Mock current_time function.
 *
 * @param string $type   Type (mysql, timestamp).
 * @param bool   $gmt    Use GMT.
 * @return string|int Current time.
 */
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) {
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
		return time();
	}
}

/**
 * Mock add_action function.
 *
 * @param string   $tag      Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool Always true.
 */
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['wp_mock_hooks']['actions'][ $tag ][] = [
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		];
		return true;
	}
}

/**
 * Mock add_filter function.
 *
 * @param string   $tag      Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool Always true.
 */
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['wp_mock_hooks']['filters'][ $tag ][] = [
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		];
		return true;
	}
}

/**
 * Mock do_action function.
 *
 * @param string $tag   Hook name.
 * @param mixed  ...$args Arguments.
 */
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		if ( ! empty( $GLOBALS['wp_mock_hooks']['actions'][ $tag ] ) ) {
			foreach ( $GLOBALS['wp_mock_hooks']['actions'][ $tag ] as $hook ) {
				call_user_func_array( $hook['callback'], $args );
			}
		}
	}
}

/**
 * Mock apply_filters function.
 *
 * @param string $tag   Hook name.
 * @param mixed  $value Value to filter.
 * @param mixed  ...$args Additional arguments.
 * @return mixed Filtered value.
 */
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		if ( ! empty( $GLOBALS['wp_mock_hooks']['filters'][ $tag ] ) ) {
			foreach ( $GLOBALS['wp_mock_hooks']['filters'][ $tag ] as $hook ) {
				$value = call_user_func_array( $hook['callback'], array_merge( [ $value ], $args ) );
			}
		}
		return $value;
	}
}

/**
 * Mock wp_nonce_field function.
 *
 * @param string $action  Action.
 * @param string $name    Name.
 * @param bool   $referer Include referer.
 * @param bool   $echo    Echo output.
 * @return string Nonce field HTML.
 */
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$nonce = wp_create_nonce( $action );
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $nonce ) . '" />';
		if ( $echo ) {
			echo $field;
		}
		return $field;
	}
}

/**
 * Mock wp_create_nonce function.
 *
 * @param string $action Action.
 * @return string Nonce.
 */
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return md5( 'test_nonce_' . $action );
	}
}

/**
 * Mock wp_verify_nonce function.
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool|int Result.
 */
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return $nonce === wp_create_nonce( $action ) ? 1 : false;
	}
}

/**
 * Mock check_admin_referer function.
 *
 * @param string $action Action.
 * @param string $name   Query arg name.
 * @return bool True.
 */
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
		return true;
	}
}

/**
 * Mock is_admin function.
 *
 * @return bool True if admin.
 */
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return defined( 'WP_ADMIN' ) && WP_ADMIN;
	}
}

/**
 * Mock admin_url function.
 *
 * @param string $path Path.
 * @return string Admin URL.
 */
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://localhost/wp-admin/' . ltrim( $path, '/' );
	}
}

/**
 * Mock rest_url function.
 *
 * @param string $path Path.
 * @return string REST URL.
 */
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'http://localhost/wp-json/' . ltrim( $path, '/' );
	}
}

/**
 * Mock plugin_dir_path function.
 *
 * @param string $file File path.
 * @return string Directory path.
 */
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

/**
 * Mock plugin_dir_url function.
 *
 * @param string $file File path.
 * @return string Directory URL.
 */
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://localhost/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

/**
 * Mock plugin_basename function.
 *
 * @param string $file File path.
 * @return string Plugin basename.
 */
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

/**
 * Mock trailingslashit function.
 *
 * @param string $string String.
 * @return string String with trailing slash.
 */
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

/**
 * Mock untrailingslashit function.
 *
 * @param string $string String.
 * @return string String without trailing slash.
 */
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}
}

/**
 * Mock get_bloginfo function.
 *
 * @param string $show What to show.
 * @return string Blog info.
 */
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		$info = [
			'name'        => 'Test Site',
			'description' => 'Test Site Description',
			'url'         => 'http://localhost',
			'admin_email' => 'admin@test.local',
			'version'     => '6.4.0',
		];
		return $info[ $show ] ?? '';
	}
}

/**
 * Mock wp_remote_get function.
 *
 * @param string $url  URL.
 * @param array  $args Arguments.
 * @return array|WP_Error Response.
 */
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = [] ) {
		return [
			'response' => [ 'code' => 200 ],
			'body'     => '{}',
		];
	}
}

/**
 * Mock wp_remote_post function.
 *
 * @param string $url  URL.
 * @param array  $args Arguments.
 * @return array|WP_Error Response.
 */
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = [] ) {
		return [
			'response' => [ 'code' => 200 ],
			'body'     => '{}',
		];
	}
}

/**
 * Mock wp_remote_retrieve_response_code function.
 *
 * @param array $response Response.
 * @return int Response code.
 */
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? 0;
	}
}

/**
 * Mock wp_remote_retrieve_body function.
 *
 * @param array $response Response.
 * @return string Body.
 */
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}

/**
 * Mock is_wp_error function.
 *
 * @param mixed $thing Thing to check.
 * @return bool True if WP_Error.
 */
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

/**
 * Mock WP_Error class.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message( $code = '' ) {
			return $this->message;
		}

		public function get_error_data( $code = '' ) {
			return $this->data;
		}

		public function add( $code, $message, $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
	}
}

/**
 * Mock WP_REST_Request class.
 */
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params     = [];
		private $body       = '';
		private $method     = 'GET';
		private $route      = '';
		private $headers    = [];
		private $file_params = [];

		public function __construct( $method = 'GET', $route = '' ) {
			$this->method = $method;
			$this->route  = $route;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params() {
			return $this->params;
		}

		public function set_body( $body ) {
			$this->body = $body;
		}

		public function get_body() {
			return $this->body;
		}

		public function get_json_params() {
			return json_decode( $this->body, true ) ?? [];
		}

		public function set_header( $key, $value ) {
			$this->headers[ $key ] = $value;
		}

		public function get_header( $key ) {
			return $this->headers[ $key ] ?? null;
		}

		public function get_method() {
			return $this->method;
		}

		public function get_route() {
			return $this->route;
		}

		public function set_file_params( $files ) {
			$this->file_params = $files;
		}

		public function get_file_params() {
			return $this->file_params;
		}
	}
}

/**
 * Mock WP_REST_Response class.
 */
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private $status;
		private $headers = [];

		public function __construct( $data = null, $status = 200, $headers = [] ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}

		public function get_data() {
			return $this->data;
		}

		public function set_data( $data ) {
			$this->data = $data;
		}

		public function get_status() {
			return $this->status;
		}

		public function set_status( $status ) {
			$this->status = $status;
		}

		public function set_headers( $headers ) {
			$this->headers = $headers;
		}

		public function get_headers() {
			return $this->headers;
		}
	}
}

/**
 * Mock load_plugin_textdomain function.
 *
 * @param string $domain  Text domain.
 * @param string $deprecated Deprecated.
 * @param string $path    Path.
 * @return bool True.
 */
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $path = '' ) {
		return true;
	}
}

/**
 * Mock add_shortcode function.
 *
 * @param string   $tag      Shortcode tag.
 * @param callable $callback Callback.
 */
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['wp_mock_hooks']['shortcodes'][ $tag ] = $callback;
	}
}

/**
 * Mock shortcode_atts function.
 *
 * @param array  $pairs    Default pairs.
 * @param array  $atts     User attributes.
 * @param string $shortcode Shortcode name.
 * @return array Combined attributes.
 */
if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = [];
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
	}
}

/**
 * Mock register_activation_hook function.
 *
 * @param string   $file     Plugin file.
 * @param callable $callback Callback.
 */
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		$GLOBALS['wp_mock_hooks']['activation'] = $callback;
	}
}

/**
 * Mock register_deactivation_hook function.
 *
 * @param string   $file     Plugin file.
 * @param callable $callback Callback.
 */
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		$GLOBALS['wp_mock_hooks']['deactivation'] = $callback;
	}
}

/**
 * Mock flush_rewrite_rules function.
 */
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules() {
		return true;
	}
}

/**
 * Mock wp_enqueue_style function.
 *
 * @param string $handle Handle.
 * @param string $src    Source.
 * @param array  $deps   Dependencies.
 * @param string $ver    Version.
 * @param string $media  Media.
 */
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['wp_mock_hooks']['styles'][ $handle ] = [
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		];
	}
}

/**
 * Mock wp_enqueue_script function.
 *
 * @param string $handle    Handle.
 * @param string $src       Source.
 * @param array  $deps      Dependencies.
 * @param string $ver       Version.
 * @param bool   $in_footer In footer.
 */
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {
		$GLOBALS['wp_mock_hooks']['scripts'][ $handle ] = [
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		];
	}
}

/**
 * Mock wp_localize_script function.
 *
 * @param string $handle      Handle.
 * @param string $object_name Object name.
 * @param array  $l10n        Data.
 * @return bool True.
 */
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $l10n ) {
		$GLOBALS['wp_mock_hooks']['localized'][ $handle ] = [
			'name' => $object_name,
			'data' => $l10n,
		];
		return true;
	}
}

/**
 * Mock wp_enqueue_media function.
 */
if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media() {
		return true;
	}
}

/**
 * Mock add_menu_page function.
 *
 * @param string   $page_title Page title.
 * @param string   $menu_title Menu title.
 * @param string   $capability Capability.
 * @param string   $menu_slug  Menu slug.
 * @param callable $function   Callback.
 * @param string   $icon_url   Icon URL.
 * @param int      $position   Position.
 * @return string Hook suffix.
 */
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null ) {
		return 'admin_page_' . $menu_slug;
	}
}

/**
 * Mock add_submenu_page function.
 *
 * @param string   $parent_slug Parent slug.
 * @param string   $page_title  Page title.
 * @param string   $menu_title  Menu title.
 * @param string   $capability  Capability.
 * @param string   $menu_slug   Menu slug.
 * @param callable $function    Callback.
 * @param int      $position    Position.
 * @return string Hook suffix.
 */
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '', $position = null ) {
		return 'admin_page_' . $menu_slug;
	}
}

/**
 * Mock wp_mail function.
 *
 * @param string|array $to          Recipient.
 * @param string       $subject     Subject.
 * @param string       $message     Message.
 * @param string|array $headers     Headers.
 * @param string|array $attachments Attachments.
 * @return bool True.
 */
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
		$GLOBALS['wp_mock_hooks']['mails'][] = [
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
		];
		return true;
	}
}

/**
 * Helper function to reset mock data.
 */
function reset_wp_mocks() {
	$GLOBALS['wp_mock_options']    = [];
	$GLOBALS['wp_mock_transients'] = [];
	$GLOBALS['wp_mock_hooks']      = [];
}

/**
 * Helper class for testing API connections.
 */
class AIAgent_API_Tester {

	/**
	 * Test Groq API connection.
	 *
	 * @param string $api_key API key to test.
	 * @return array Test result with success status and message.
	 */
	public static function test_groq_connection( $api_key = '' ) {
		if ( empty( $api_key ) ) {
			$api_key = defined( 'AIAGENT_GROQ_API_KEY' ) ? AIAGENT_GROQ_API_KEY : '';
		}

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'message' => 'No API key provided',
			];
		}

		// In test mode, simulate successful connection.
		if ( defined( 'AIAGENT_TEST_MODE' ) && AIAGENT_TEST_MODE ) {
			return [
				'success' => true,
				'message' => 'Connection successful (test mode)',
				'models'  => [ 'llama3-8b-8192', 'mixtral-8x7b-32768' ],
			];
		}

		// Real API test would go here.
		return [
			'success' => true,
			'message' => 'Connection test skipped - not in test mode',
		];
	}

	/**
	 * Validate API key format.
	 *
	 * @param string $api_key API key.
	 * @param string $provider Provider name.
	 * @return bool True if valid format.
	 */
	public static function validate_api_key_format( $api_key, $provider = 'groq' ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		switch ( $provider ) {
			case 'groq':
				// Groq API keys start with 'gsk_'.
				return strpos( $api_key, 'gsk_' ) === 0 || strlen( $api_key ) > 20;
			case 'openai':
				// OpenAI API keys start with 'sk-'.
				return strpos( $api_key, 'sk-' ) === 0;
			case 'anthropic':
				// Anthropic API keys start with 'sk-ant-'.
				return strpos( $api_key, 'sk-ant-' ) === 0;
			default:
				return strlen( $api_key ) > 10;
		}
	}
}

/**
 * Mock AI_Agent_For_Website class for testing.
 */
if ( ! class_exists( 'AI_Agent_For_Website' ) ) {
	class AI_Agent_For_Website {
		private static $instance = null;

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {}

		public static function get_settings() {
			return get_option( 'aiagent_settings', [
				'enabled'            => true,
				'ai_name'            => 'AI Assistant',
				'widget_position'    => 'bottom-right',
				'primary_color'      => '#0073aa',
				'avatar_url'         => '',
				'require_user_info'  => true,
				'require_phone'      => false,
				'show_powered_by'    => true,
				'consent_ai_enabled' => true,
				'consent_ai_text'    => 'I agree to interact with AI assistance',
			] );
		}

		public static function update_settings( $settings ) {
			return update_option( 'aiagent_settings', $settings );
		}
	}
}

/**
 * Mock AIAGENT_Live_Agent_Manager class for testing.
 */
if ( ! class_exists( 'AIAGENT_Live_Agent_Manager' ) ) {
	class AIAGENT_Live_Agent_Manager {
		public static function get_settings() {
			return [
				'enabled'             => false,
				'connect_button_text' => 'Connect to Live Agent',
				'waiting_message'     => 'Please wait...',
				'connected_message'   => 'You are now connected.',
				'offline_message'     => 'Agents are currently offline.',
			];
		}

		public static function get_frontend_settings() {
			return [
				'enabled'     => false,
				'isAvailable' => false,
			];
		}
	}
}

/**
 * Mock AIAGENT_WooCommerce_Integration class for testing.
 */
if ( ! class_exists( 'AIAGENT_WooCommerce_Integration' ) ) {
	class AIAGENT_WooCommerce_Integration {
		public static function is_enabled() {
			return false;
		}

		public static function get_settings() {
			return [
				'show_prices'          => true,
				'show_add_to_cart'     => true,
				'show_related_products' => true,
				'show_product_comparison' => true,
				'max_products_display' => 6,
			];
		}
	}
}

/**
 * Mock AIAGENT_Google_Calendar_Integration class for testing.
 */
if ( ! class_exists( 'AIAGENT_Google_Calendar_Integration' ) ) {
	class AIAGENT_Google_Calendar_Integration {
		public static function get_frontend_settings() {
			return [
				'enabled' => false,
			];
		}
	}
}

/**
 * Mock AIAGENT_Calendly_Integration class for testing.
 */
if ( ! class_exists( 'AIAGENT_Calendly_Integration' ) ) {
	class AIAGENT_Calendly_Integration {
		public static function get_frontend_settings() {
			return [
				'enabled' => false,
			];
		}
	}
}

/**
 * Mock wc_get_cart_url function.
 */
if ( ! function_exists( 'wc_get_cart_url' ) ) {
	function wc_get_cart_url() {
		return 'http://localhost/cart/';
	}
}

/**
 * Mock wc_get_checkout_url function.
 */
if ( ! function_exists( 'wc_get_checkout_url' ) ) {
	function wc_get_checkout_url() {
		return 'http://localhost/checkout/';
	}
}

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

