<?php
/**
 * REST API Class
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIEngine\AIEngine;

/**
 * Class AIAGENT_REST_API
 *
 * Handles REST API endpoints for the AI Agent plugin.
 */
class AIAGENT_REST_API {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'ai-agent/v1';

	/**
	 * Store conversation history per session.
	 *
	 * @var array
	 */
	private static $conversations = array();

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Chat endpoint.
		register_rest_route(
			$this->namespace,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => '__return_true', // Public endpoint.
				'args'                => array(
					'message'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'user_id'    => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Register user endpoint.
		register_rest_route(
			$this->namespace,
			'/register-user',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_register_user' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'                => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'               => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'phone'               => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session_id'          => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'consent_ai'          => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'consent_newsletter'  => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'consent_promotional' => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		// New conversation endpoint.
		register_rest_route(
			$this->namespace,
			'/new-conversation',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_new_conversation' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'user_id'    => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Test connection endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_test' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// AI suggestion endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/ai-suggest',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_ai_suggest' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Auto detect pillar pages endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/detect-pillar-pages',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_detect_pillar_pages' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Fetch URL endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/fetch-url',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_fetch_url' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Rate conversation endpoint.
		register_rest_route(
			$this->namespace,
			'/rate-conversation',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_rate_conversation' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'rating'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// File upload endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/upload-file',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_file_upload' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Delete uploaded file endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/delete-file',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_delete_file' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'file_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'kb_index' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Google Drive auth URL endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/gdrive/auth-url',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_gdrive_auth_url' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Google Drive disconnect endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/gdrive/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_gdrive_disconnect' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Google Drive list files endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/gdrive/files',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_gdrive_list_files' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Google Drive import file endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/gdrive/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_gdrive_import' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'file_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Confluence test connection endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/confluence/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_confluence_test' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Confluence disconnect endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/confluence/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_confluence_disconnect' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Confluence list spaces endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/confluence/spaces',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_confluence_spaces' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Confluence list pages endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/confluence/pages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_confluence_pages' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'space_key' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Confluence import page endpoint (admin only).
		register_rest_route(
			$this->namespace,
			'/confluence/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_confluence_import' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'page_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Notification endpoints (admin only).
		register_rest_route(
			$this->namespace,
			'/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_notifications' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/notifications/unread-count',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_unread_count' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/notifications/mark-read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_mark_notification_read' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'notification_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/notifications/mark-all-read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_mark_all_read' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// AI validation endpoint for conversations (admin only).
		register_rest_route(
			$this->namespace,
			'/validate-conversation',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_validate_conversation' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/convert-to-lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_convert_to_lead' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/close-conversation',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_close_conversation' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'reason'          => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Activity log endpoints (admin only).
		register_rest_route(
			$this->namespace,
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_logs' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/logs/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_log_stats' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Save integration settings endpoints.
		register_rest_route(
			$this->namespace,
			'/settings/groq',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_groq_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/gdrive',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_gdrive_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/confluence',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_confluence_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/zapier',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_zapier_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/mailchimp',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_mailchimp_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// WooCommerce integration endpoints.
		register_rest_route(
			$this->namespace,
			'/woocommerce/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'query' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/product/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_product' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/related/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_related' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'limit' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 4,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/compare',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_woocommerce_compare' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_ids' => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $ids ) {
							return array_map( 'absint', $ids );
						},
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/add-to-cart',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_woocommerce_add_to_cart' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'quantity'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'variation_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/cart',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_cart' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/remove-from-cart',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_woocommerce_remove_from_cart' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'cart_item_key' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_categories' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/featured',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_featured' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 6,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/sale',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_sale' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 6,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/bestsellers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_bestsellers' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 6,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/variations/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_woocommerce_variations' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/woocommerce',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_woocommerce_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/sync-to-kb',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_woocommerce_sync_to_kb' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Google Calendar integration endpoints.
		register_rest_route(
			$this->namespace,
			'/gcalendar/auth-url',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_gcalendar_auth_url' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/gcalendar/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_gcalendar_disconnect' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/gcalendar/calendars',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_gcalendar_list_calendars' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/gcalendar/slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_gcalendar_slots' ),
				'permission_callback' => '__return_true', // Public endpoint for chat widget.
				'args'                => array(
					'start_date' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'days_ahead' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/gcalendar/create-event',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_gcalendar_create_event' ),
				'permission_callback' => '__return_true', // Public endpoint for chat widget.
				'args'                => array(
					'title'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'start'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end'            => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description'    => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'attendee_email' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'attendee_name'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'add_meet'       => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/gcalendar/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_gcalendar_status' ),
				'permission_callback' => '__return_true', // Public to check if booking is available.
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/gcalendar',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_gcalendar_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Calendly integration endpoints.
		register_rest_route(
			$this->namespace,
			'/calendly/auth-url',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_calendly_auth_url' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/calendly/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_calendly_disconnect' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/calendly/event-types',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_calendly_event_types' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/calendly/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_calendly_status' ),
				'permission_callback' => '__return_true', // Public to check if booking is available.
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/calendly',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_calendly_settings' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Live Agent endpoints.
		register_rest_route(
			$this->namespace,
			'/live-agent/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_live_agent_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/connect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_live_agent_connect' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'user_id'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'conversation_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/message',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_live_agent_message' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'live_session_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'message'         => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'sender_type'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sender_id'       => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_live_agent_get_messages' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'live_session_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'after_id'        => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/end',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_live_agent_end' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'live_session_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'ended_by'        => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'user',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/session-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_live_agent_session_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/heartbeat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_live_agent_heartbeat' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/set-status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_live_agent_set_status' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'status' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/live-agent/sessions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_live_agent_get_sessions' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
			)
		);

		// Accept a live agent chat session.
		register_rest_route(
			$this->namespace,
			'/live-agent/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_live_agent_accept' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'live_session_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle conversation rating.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_rate_conversation( $request ) {
		global $wpdb;

		$session_id = $request->get_param( 'session_id' );
		$rating     = $request->get_param( 'rating' );

		// Validate rating (1-5).
		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';

		// Update the most recent conversation with this session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update for conversation rating.
		$wpdb->update(
			$conversations_table,
			array(
				'rating'   => $rating,
				'status'   => 'ended',
				'ended_at' => current_time( 'mysql' ),
			),
			array( 'session_id' => $session_id )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Thank you for your feedback!', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Admin permission check.
	 *
	 * @return bool True if user has permission.
	 */
	public function admin_permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle user registration.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_register_user( $request ) {
		global $wpdb;

		$name                = $request->get_param( 'name' );
		$email               = $request->get_param( 'email' );
		$phone               = $request->get_param( 'phone' );
		$param_session       = $request->get_param( 'session_id' );
		$session_id          = ! empty( $param_session ) ? $param_session : $this->generate_session_id();
		$consent_ai          = $request->get_param( 'consent_ai' );
		$consent_newsletter  = $request->get_param( 'consent_newsletter' );
		$consent_promotional = $request->get_param( 'consent_promotional' );

		// Validate email.
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$users_table = $wpdb->prefix . 'aiagent_users';

		// Check if user already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$existing_user = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $users_table WHERE email = %s", $email )
		);

		if ( $existing_user ) {
			// Update session ID and return existing user.
			$update_data = array(
				'session_id' => $session_id,
				'name'       => $name,
			);
			if ( ! empty( $phone ) ) {
				$update_data['phone'] = $phone;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update.
			$wpdb->update(
				$users_table,
				$update_data,
				array( 'id' => $existing_user->id )
			);
			$user_id = $existing_user->id;
		} else {
			// Create new user.
			$insert_data = array(
				'name'       => $name,
				'email'      => $email,
				'session_id' => $session_id,
			);
			if ( ! empty( $phone ) ) {
				$insert_data['phone'] = $phone;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional direct insert.
			$wpdb->insert(
				$users_table,
				$insert_data
			);
			$user_id = $wpdb->insert_id;
		}

		if ( ! $user_id ) {
			return new WP_Error( 'db_error', __( 'Could not register user.', 'ai-agent-for-website' ), array( 'status' => 500 ) );
		}

		// Save user consents.
		$this->save_user_consents( $user_id, $consent_ai, $consent_newsletter, $consent_promotional );

		// If newsletter consent, subscribe to Mailchimp.
		if ( $consent_newsletter && AIAGENT_Mailchimp_Integration::is_enabled() ) {
			$name_parts = explode( ' ', $name, 2 );
			$first_name = $name_parts[0] ?? '';
			$last_name  = $name_parts[1] ?? '';
			AIAGENT_Mailchimp_Integration::subscribe( $email, $first_name, $last_name, array( 'AI Agent Chat' ) );
		}

		// Trigger Zapier webhook for new user.
		if ( AIAGENT_Zapier_Integration::is_enabled() ) {
			AIAGENT_Zapier_Integration::send_webhook(
				array(
					'user_id'             => $user_id,
					'name'                => $name,
					'email'               => $email,
					'phone'               => $phone,
					'consent_ai'          => $consent_ai,
					'consent_newsletter'  => $consent_newsletter,
					'consent_promotional' => $consent_promotional,
				),
				'user_registered'
			);
		}

		// Log user registration.
		if ( class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			$log_manager = new AIAGENT_Activity_Log_Manager();
			$log_manager->log(
				'user',
				'registered',
				sprintf(
					/* translators: %s: User email */
					__( 'User registered: %s', 'ai-agent-for-website' ),
					$email
				),
				array(
					'user_id' => $user_id,
					'name'    => $name,
					'email'   => $email,
					'phone'   => $phone,
				)
			);
		}

		// Create a new conversation for this user.
		$this->create_conversation( $user_id, $session_id, $name, $email );

		return rest_ensure_response(
			array(
				'success'    => true,
				'user_id'    => $user_id,
				'session_id' => $session_id,
			)
		);
	}

	/**
	 * Save user consents to database.
	 *
	 * @param int  $user_id             User ID.
	 * @param bool $consent_ai          AI consent.
	 * @param bool $consent_newsletter  Newsletter consent.
	 * @param bool $consent_promotional Promotional consent.
	 */
	private function save_user_consents( $user_id, $consent_ai, $consent_newsletter, $consent_promotional ) {
		global $wpdb;

		$consents_table = $wpdb->prefix . 'aiagent_user_consents';
		$ip_address     = $this->get_client_ip();

		$consents = array(
			'ai'          => $consent_ai,
			'newsletter'  => $consent_newsletter,
			'promotional' => $consent_promotional,
		);

		foreach ( $consents as $type => $value ) {
			if ( null !== $value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional direct insert.
				$wpdb->insert(
					$consents_table,
					array(
						'user_id'      => $user_id,
						'consent_type' => $type,
						'consented'    => $value ? 1 : 0,
						'ip_address'   => $ip_address,
					)
				);
			}
		}
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Handle chat request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_chat( $request ) {
		global $wpdb;

		$message       = $request->get_param( 'message' );
		$param_session = $request->get_param( 'session_id' );
		$session_id    = ! empty( $param_session ) ? $param_session : $this->generate_session_id();
		$user_id       = $request->get_param( 'user_id' );

		// Validate message.
		if ( empty( trim( $message ) ) ) {
			return new WP_Error( 'empty_message', __( 'Please enter a message.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		// Get settings.
		$settings = AI_Agent_For_Website::get_settings();

		// Check if enabled.
		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'not_enabled', __( 'Chat is not enabled.', 'ai-agent-for-website' ), array( 'status' => 403 ) );
		}

		// Check API key.
		if ( empty( $settings['api_key'] ) ) {
			return new WP_Error( 'no_api_key', __( 'AI is not configured.', 'ai-agent-for-website' ), array( 'status' => 500 ) );
		}

		// Get or create conversation.
		$conversation_id = $this->get_or_create_conversation( $user_id, $session_id );

		try {
			// Create AI Engine with Groq.
			$ai = AIEngine::create( 'groq', $settings['api_key'] );

			// Set system instruction.
			$system_instruction = $settings['system_instruction'] ?? '';

			// Add site context.
			$site_context = sprintf(
				"You are %s, an AI assistant for the website '%s' (%s).",
				$settings['ai_name'] ?? 'AI Assistant',
				get_bloginfo( 'name' ),
				home_url()
			);

			// Load knowledge base if available.
			$knowledge_manager = new AIAGENT_Knowledge_Manager();
			$kb                = $knowledge_manager->get_knowledge_base();
			$kb_restriction    = '';

			if ( ! $kb->isEmpty() ) {
				// Get the Groq provider and set knowledge base.
				$provider = $ai->getProvider();
				if ( method_exists( $provider, 'setKnowledgeBase' ) ) {
					$provider->setKnowledgeBase( $kb );
				}

				// Add knowledge base restriction to system instruction.
				$kb_restriction = "\n\nIMPORTANT INSTRUCTIONS:
- You MUST ONLY answer questions based on the knowledge base provided to you.
- If a question is outside the scope of the knowledge base or you don't have relevant information, politely say: \"I don't have information about that topic. Is there something else about our website or services I can help you with?\"
- Do NOT make up information or provide general knowledge answers.
- Stay focused on topics relevant to this website and its services.
- If the user asks irrelevant questions (like general trivia, coding help unrelated to the website, etc.), redirect them back to topics you can help with.";
			}

			$ai->setSystemInstruction( $site_context . "\n\n" . $system_instruction . $kb_restriction );

			// Restore conversation history.
			$this->restore_conversation( $ai, $session_id );

			// Save user message to database.
			if ( $conversation_id ) {
				$this->save_message( $conversation_id, 'user', $message );
			}

			// Send message.
			$response = $ai->chat( $message );

			// Handle error response.
			if ( is_array( $response ) && isset( $response['error'] ) ) {
				return new WP_Error( 'ai_error', $response['error'], array( 'status' => 500 ) );
			}

			// Save AI response to database.
			if ( $conversation_id ) {
				$this->save_message( $conversation_id, 'assistant', $response );
			}

			return rest_ensure_response(
				array(
					'success'    => true,
					'message'    => $response,
					'session_id' => $session_id,
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Handle new conversation.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_new_conversation( $request ) {
		$session_id = $request->get_param( 'session_id' );
		$user_id    = $request->get_param( 'user_id' );

		if ( $session_id ) {
			// Mark old conversation as ended.
			$this->end_conversation( $session_id );
			$this->clear_conversation( $session_id );
		}

		$new_session_id = $this->generate_session_id();

		// Create new conversation if we have a user.
		if ( $user_id ) {
			$this->create_conversation( $user_id, $new_session_id );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'session_id' => $new_session_id,
			)
		);
	}

	/**
	 * Handle API test.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_test( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$settings = AI_Agent_For_Website::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return new WP_Error( 'no_api_key', __( 'API key not configured.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		try {
			$ai       = AIEngine::create( 'groq', $settings['api_key'] );
			$response = $ai->generateContent( 'Say "Hello! API connection successful." in exactly those words.' );

			if ( is_array( $response ) && isset( $response['error'] ) ) {
				return new WP_Error( 'api_error', $response['error'], array( 'status' => 500 ) );
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => $response,
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Handle URL fetch (for admin).
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_fetch_url( $request ) {
		$url = $request->get_param( 'url' );

		$knowledge_manager = new AIAGENT_Knowledge_Manager();
		$kb                = $knowledge_manager->get_knowledge_base();

		$result = $kb->addUrl( $url );

		if ( $result['success'] ) {
			// Save the updated knowledge base.
			$upload_dir = wp_upload_dir();
			$kb_file    = $upload_dir['basedir'] . '/ai-agent-knowledge/knowledge-base.json';
			$kb->save( $kb_file );

			return rest_ensure_response(
				array(
					'success' => true,
					'title'   => $result['title'] ?? 'Untitled',
				)
			);
		}

		return new WP_Error( 'fetch_error', $result['error'] ?? 'Failed to fetch URL', array( 'status' => 400 ) );
	}

	/**
	 * Handle AI suggestion request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_ai_suggest( $request ) {
		$type     = $request->get_param( 'type' );
		$settings = AI_Agent_For_Website::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return new WP_Error( 'no_api_key', __( 'API key not configured.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$site_url  = home_url();

		try {
			$ai = AIEngine::create( 'groq', $settings['api_key'] );

			if ( 'welcome' === $type ) {
				$prompt = sprintf(
					'Generate a friendly, professional welcome message for a chat widget on a website called "%s" (%s). The site description is: "%s". Keep it under 100 characters, warm and inviting. Just return the message text, no quotes or explanation.',
					$site_name,
					$site_url,
					$site_desc
				);
			} else {
				$prompt = sprintf(
					'Generate a system instruction for an AI chat assistant on a website called "%s" (%s). The site description is: "%s". The instruction should define the AI personality, tone, and behavior. Keep it concise (2-3 sentences). Just return the instruction text, no quotes or explanation.',
					$site_name,
					$site_url,
					$site_desc
				);
			}

			$response = $ai->generateContent( $prompt );

			if ( is_array( $response ) && isset( $response['error'] ) ) {
				return new WP_Error( 'ai_error', $response['error'], array( 'status' => 500 ) );
			}

			return rest_ensure_response(
				array(
					'success'    => true,
					'suggestion' => trim( $response ),
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Handle auto detect pillar pages request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_detect_pillar_pages( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$settings = AI_Agent_For_Website::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return new WP_Error( 'no_api_key', __( 'API key not configured.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		// Get all published pages and posts.
		$pages = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $pages ) ) {
			return new WP_Error( 'no_content', __( 'No published content found.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		// Prepare content list for AI.
		$content_list = array();
		foreach ( $pages as $page ) {
			$content_list[] = array(
				'id'      => $page->ID,
				'title'   => $page->post_title,
				'type'    => $page->post_type,
				'url'     => get_permalink( $page->ID ),
				'excerpt' => wp_trim_words( $page->post_content, 50 ),
			);
		}

		try {
			$ai = AIEngine::create( 'groq', $settings['api_key'] );

			$site_name = get_bloginfo( 'name' );
			$site_desc = get_bloginfo( 'description' );

			$content_json = wp_json_encode( $content_list );

			$prompt = sprintf(
				'You are analyzing content for a website called "%s" with description: "%s".

Here is a list of pages/posts on the website:
%s

Identify the TOP 10 most important "pillar pages" - these are foundational, comprehensive pages that cover key topics and are most valuable for training an AI chatbot about this website.

Consider factors like:
- Comprehensive topic coverage
- Core business/service pages
- About/company information
- Key landing pages
- Important product/service pages

Return ONLY a JSON array of the recommended page IDs in order of importance, like: [1, 2, 3, 4, 5]
Do not include any explanation, just the JSON array.',
				$site_name,
				$site_desc,
				$content_json
			);

			$response = $ai->generateContent( $prompt );

			if ( is_array( $response ) && isset( $response['error'] ) ) {
				return new WP_Error( 'ai_error', $response['error'], array( 'status' => 500 ) );
			}

			// Parse the AI response to get page IDs.
			$response = trim( $response );
			// Remove any markdown formatting.
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*/', '', $response );

			$page_ids = json_decode( $response, true );

			if ( ! is_array( $page_ids ) ) {
				return new WP_Error( 'parse_error', __( 'Could not parse AI response.', 'ai-agent-for-website' ), array( 'status' => 500 ) );
			}

			// Get the recommended pages with full details.
			$recommended = array();
			foreach ( $page_ids as $id ) {
				$page = get_post( $id );
				if ( $page ) {
					$recommended[] = array(
						'id'    => $page->ID,
						'title' => $page->post_title,
						'type'  => $page->post_type,
						'url'   => get_permalink( $page->ID ),
					);
				}
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'pages'   => $recommended,
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Generate session ID.
	 *
	 * @return string Generated session ID.
	 */
	private function generate_session_id() {
		return 'session_' . wp_generate_password( 16, false );
	}

	/**
	 * Restore conversation history from database.
	 *
	 * @param AIEngine $ai         The AI Engine instance.
	 * @param string   $session_id The session ID.
	 */
	private function restore_conversation( $ai, $session_id ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';
		$messages_table      = $wpdb->prefix . 'aiagent_messages';

		// Get active conversation for this session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$conversation = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT id FROM $conversations_table WHERE session_id = %s AND status = 'active' ORDER BY id DESC LIMIT 1", $session_id )
		);

		if ( ! $conversation ) {
			return;
		}

		// Get last 20 messages from this conversation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$messages = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT role, content FROM $messages_table WHERE conversation_id = %d ORDER BY id DESC LIMIT 20", $conversation->id )
		);

		if ( $messages ) {
			// Reverse to get chronological order.
			$messages = array_reverse( $messages );
			$provider = $ai->getProvider();
			foreach ( $messages as $msg ) {
				$provider->addToHistory( $msg->role, $msg->content );
			}
		}
	}

	/**
	 * Clear conversation history (mark as ended).
	 *
	 * @param string $session_id The session ID.
	 */
	private function clear_conversation( $session_id ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update.
		$wpdb->update(
			$conversations_table,
			array(
				'status'   => 'ended',
				'ended_at' => current_time( 'mysql' ),
			),
			array(
				'session_id' => $session_id,
				'status'     => 'active',
			)
		);
	}

	/**
	 * Create a new conversation in database.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $session_id The session ID.
	 * @param string $user_name  Optional user name for notification.
	 * @param string $user_email Optional user email for notification.
	 * @return int|null The conversation ID or null.
	 */
	private function create_conversation( $user_id, $session_id, $user_name = '', $user_email = '' ) {
		global $wpdb;

		if ( ! $user_id ) {
			return null;
		}

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional direct insert.
		$wpdb->insert(
			$conversations_table,
			array(
				'user_id'    => $user_id,
				'session_id' => $session_id,
				'status'     => 'active',
			)
		);

		$conversation_id = $wpdb->insert_id;

		// Trigger notification for new conversation.
		if ( $conversation_id && class_exists( 'AIAGENT_Notification_Manager' ) ) {
			// Get user info if not provided.
			if ( empty( $user_name ) || empty( $user_email ) ) {
				$users_table = $wpdb->prefix . 'aiagent_users';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup.
				$user = $wpdb->get_row( $wpdb->prepare( "SELECT name, email FROM $users_table WHERE id = %d", $user_id ) );
				if ( $user ) {
					$user_name  = $user->name;
					$user_email = $user->email;
				}
			}

			$notification_manager = new AIAGENT_Notification_Manager();
			$notification_manager->notify_new_conversation( $conversation_id, $user_name, $user_email );
		}

		// Log the conversation creation.
		if ( $conversation_id && class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			$log_manager = new AIAGENT_Activity_Log_Manager();
			$log_manager->log(
				'conversation',
				'started',
				sprintf(
					/* translators: 1: Conversation ID, 2: User name */
					__( 'Conversation #%1$d started by %2$s', 'ai-agent-for-website' ),
					$conversation_id,
					$user_name ? $user_name : 'Unknown'
				),
				array(
					'conversation_id' => $conversation_id,
					'user_id'         => $user_id,
					'session_id'      => $session_id,
				)
			);
		}

		return $conversation_id;
	}

	/**
	 * Get or create conversation.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $session_id The session ID.
	 * @return int|null The conversation ID or null.
	 */
	private function get_or_create_conversation( $user_id, $session_id ) {
		global $wpdb;

		if ( ! $user_id ) {
			return null;
		}

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';

		// Check for existing active conversation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$conversation = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe table name from wpdb prefix.
			$wpdb->prepare( "SELECT * FROM $conversations_table WHERE session_id = %s AND status = 'active' ORDER BY id DESC LIMIT 1", $session_id )
		);

		if ( $conversation ) {
			return $conversation->id;
		}

		// Create new conversation.
		return $this->create_conversation( $user_id, $session_id );
	}

	/**
	 * End conversation.
	 *
	 * @param string $session_id The session ID.
	 */
	private function end_conversation( $session_id ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'aiagent_conversations';

		// Get conversation ID before ending.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table lookup.
		$conversation = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $conversations_table WHERE session_id = %s AND status = 'active'", $session_id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update.
		$wpdb->update(
			$conversations_table,
			array(
				'status'   => 'ended',
				'ended_at' => current_time( 'mysql' ),
			),
			array( 'session_id' => $session_id )
		);

		// Log conversation end.
		if ( $conversation && class_exists( 'AIAGENT_Activity_Log_Manager' ) ) {
			$log_manager = new AIAGENT_Activity_Log_Manager();
			$log_manager->log(
				'conversation',
				'ended',
				sprintf(
					/* translators: %d: Conversation ID */
					__( 'Conversation #%d ended', 'ai-agent-for-website' ),
					$conversation->id
				),
				array(
					'conversation_id' => $conversation->id,
					'session_id'      => $session_id,
				)
			);
		}
	}

	/**
	 * Save message to database.
	 *
	 * @param int    $conversation_id The conversation ID.
	 * @param string $role            The message role (user/assistant).
	 * @param string $content         The message content.
	 */
	private function save_message( $conversation_id, $role, $content ) {
		global $wpdb;

		if ( ! $conversation_id ) {
			return;
		}

		$messages_table = $wpdb->prefix . 'aiagent_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional direct insert.
		$wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
			)
		);
	}

	/**
	 * Handle file upload request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_file_upload( $request ) {
		// Check if files were uploaded.
		$files = $request->get_file_params();

		if ( empty( $files ) || empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];

		// Process the file.
		$file_processor = new AIAGENT_File_Processor();
		$result         = $file_processor->process_file( $file );

		if ( ! $result['success'] ) {
			return new WP_Error( 'processing_failed', $result['error'], array( 'status' => 400 ) );
		}

		// Add to knowledge base.
		$knowledge_manager = new AIAGENT_Knowledge_Manager();
		$kb                = $knowledge_manager->get_knowledge_base();

		$source = 'file-upload-' . $result['filename'];
		$title  = pathinfo( $result['original_name'], PATHINFO_FILENAME );

		$kb_result = $kb->addText( $result['content'], $source, $title );

		if ( ! $kb_result ) {
			return new WP_Error( 'kb_add_failed', __( 'Failed to add content to knowledge base.', 'ai-agent-for-website' ), array( 'status' => 500 ) );
		}

		// Save knowledge base.
		$knowledge_manager->save_knowledge_base( $kb );

		// Get the document index (last added).
		$summary  = $kb->getSummary();
		$kb_index = $summary['count'] - 1;

		// Save file record to database.
		$file_id = $file_processor->save_file_record( $result, $kb_index );

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => __( 'File uploaded and added to knowledge base.', 'ai-agent-for-website' ),
				'file_id'    => $file_id,
				'filename'   => $result['original_name'],
				'file_type'  => $result['file_type'],
				'char_count' => $result['char_count'],
				'kb_index'   => $kb_index,
			)
		);
	}

	/**
	 * Handle file deletion request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_delete_file( $request ) {
		$file_id  = $request->get_param( 'file_id' );
		$kb_index = $request->get_param( 'kb_index' );

		// Delete from knowledge base if index provided.
		if ( null !== $kb_index && $kb_index >= 0 ) {
			$knowledge_manager = new AIAGENT_Knowledge_Manager();
			$kb                = $knowledge_manager->get_knowledge_base();

			if ( $kb->remove( $kb_index ) ) {
				$knowledge_manager->save_knowledge_base( $kb );
			}
		}

		// Delete file record and physical file.
		$file_processor = new AIAGENT_File_Processor();
		$deleted        = $file_processor->delete_file( $file_id );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete file.', 'ai-agent-for-website' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'File deleted successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle Google Drive auth URL request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_gdrive_auth_url( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$gdrive   = new AIAGENT_Google_Drive_Integration();
		$auth_url = $gdrive->get_auth_url();

		if ( is_wp_error( $auth_url ) ) {
			return $auth_url;
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'auth_url' => $auth_url,
			)
		);
	}

	/**
	 * Handle Google Drive disconnect request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_gdrive_disconnect( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		AIAGENT_Google_Drive_Integration::delete_tokens();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Disconnected from Google Drive.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle Google Drive list files request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_gdrive_list_files( $request ) {
		$query = $request->get_param( 'query' ) ?? '';

		$gdrive = new AIAGENT_Google_Drive_Integration();
		$files  = $gdrive->list_files( $query );

		if ( is_wp_error( $files ) ) {
			return $files;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'files'   => $files,
			)
		);
	}

	/**
	 * Handle Google Drive import file request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_gdrive_import( $request ) {
		$file_id = $request->get_param( 'file_id' );

		$gdrive = new AIAGENT_Google_Drive_Integration();
		$result = $gdrive->import_to_knowledge_base( $file_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle Confluence test connection request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_confluence_test( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$confluence = new AIAGENT_Confluence_Integration();
		$result     = $confluence->test_connection();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle Confluence disconnect request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_confluence_disconnect( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		AIAGENT_Confluence_Integration::disconnect();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Disconnected from Confluence.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle Confluence list spaces request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_confluence_spaces( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$confluence = new AIAGENT_Confluence_Integration();
		$spaces     = $confluence->get_spaces();

		if ( is_wp_error( $spaces ) ) {
			return $spaces;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'spaces'  => $spaces,
			)
		);
	}

	/**
	 * Handle Confluence list pages request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_confluence_pages( $request ) {
		$space_key = $request->get_param( 'space_key' );

		$confluence = new AIAGENT_Confluence_Integration();
		$pages      = $confluence->get_pages( $space_key );

		if ( is_wp_error( $pages ) ) {
			return $pages;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'pages'   => $pages,
			)
		);
	}

	/**
	 * Handle Confluence import page request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_confluence_import( $request ) {
		$page_id = $request->get_param( 'page_id' );

		$confluence = new AIAGENT_Confluence_Integration();
		$result     = $confluence->import_to_knowledge_base( $page_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle get notifications request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_get_notifications( $request ) {
		$page   = $request->get_param( 'page' ) ?? 1;
		$status = $request->get_param( 'status' ) ?? '';
		$type   = $request->get_param( 'type' ) ?? '';

		$notification_manager = new AIAGENT_Notification_Manager();
		$data                 = $notification_manager->get_notifications( $page, 20, $status, $type );

		return rest_ensure_response(
			array(
				'success'       => true,
				'notifications' => $data['notifications'],
				'total'         => $data['total'],
				'pages'         => $data['pages'],
			)
		);
	}

	/**
	 * Handle unread notification count request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_unread_count( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$notification_manager = new AIAGENT_Notification_Manager();
		$count                = $notification_manager->get_unread_count();

		return rest_ensure_response(
			array(
				'success' => true,
				'count'   => $count,
			)
		);
	}

	/**
	 * Handle mark notification as read request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_mark_notification_read( $request ) {
		$notification_id = $request->get_param( 'notification_id' );

		$notification_manager = new AIAGENT_Notification_Manager();
		$result               = $notification_manager->mark_as_read( $notification_id );

		return rest_ensure_response(
			array(
				'success' => $result,
			)
		);
	}

	/**
	 * Handle mark all notifications as read request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_mark_all_read( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$notification_manager = new AIAGENT_Notification_Manager();
		$result               = $notification_manager->mark_all_as_read();

		return rest_ensure_response(
			array(
				'success' => $result,
			)
		);
	}

	/**
	 * Handle validate conversation with AI request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_validate_conversation( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );

		$notification_manager = new AIAGENT_Notification_Manager();
		$result               = $notification_manager->validate_conversation_with_ai( $conversation_id );

		if ( ! $result['success'] ) {
			return new WP_Error( 'validation_failed', $result['error'], array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle convert conversation to lead request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_convert_to_lead( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );

		$notification_manager = new AIAGENT_Notification_Manager();
		$result               = $notification_manager->convert_conversation_to_lead( $conversation_id );

		if ( ! $result['success'] ) {
			return new WP_Error( 'conversion_failed', $result['error'], array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle close conversation request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_close_conversation( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );
		$reason          = $request->get_param( 'reason' ) ?? '';

		$notification_manager = new AIAGENT_Notification_Manager();
		$result               = $notification_manager->close_conversation( $conversation_id, $reason );

		if ( ! $result['success'] ) {
			return new WP_Error( 'close_failed', $result['error'], array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle get activity logs request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_get_logs( $request ) {
		$page      = $request->get_param( 'page' ) ?? 1;
		$category  = $request->get_param( 'category' ) ?? '';
		$date_from = $request->get_param( 'date_from' ) ?? '';
		$date_to   = $request->get_param( 'date_to' ) ?? '';

		$log_manager = new AIAGENT_Activity_Log_Manager();
		$data        = $log_manager->get_logs( $page, 50, $category, $date_from, $date_to );

		return rest_ensure_response(
			array(
				'success' => true,
				'logs'    => $data['logs'],
				'total'   => $data['total'],
				'pages'   => $data['pages'],
			)
		);
	}

	/**
	 * Handle activity log statistics request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_log_stats( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$log_manager = new AIAGENT_Activity_Log_Manager();
		$stats       = $log_manager->get_statistics();

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Handle save Groq settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_groq_settings( $request ) {
		$api_key = sanitize_text_field( $request->get_param( 'api_key' ) );

		$settings            = AI_Agent_For_Website::get_settings();
		$settings['api_key'] = $api_key;
		AI_Agent_For_Website::update_settings( $settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Groq settings saved successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle save Google Drive settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_gdrive_settings( $request ) {
		$client_id     = sanitize_text_field( $request->get_param( 'client_id' ) );
		$client_secret = sanitize_text_field( $request->get_param( 'client_secret' ) );

		$gdrive_settings                  = AIAGENT_Google_Drive_Integration::get_settings();
		$gdrive_settings['client_id']     = $client_id;
		$gdrive_settings['client_secret'] = $client_secret;
		AIAGENT_Google_Drive_Integration::update_settings( $gdrive_settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Google Drive settings saved successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle save Confluence settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_confluence_settings( $request ) {
		$instance_url = esc_url_raw( $request->get_param( 'instance_url' ) );
		$email        = sanitize_email( $request->get_param( 'email' ) );
		$api_token    = sanitize_text_field( $request->get_param( 'api_token' ) );

		$confluence_settings                 = AIAGENT_Confluence_Integration::get_settings();
		$confluence_settings['instance_url'] = $instance_url;
		$confluence_settings['email']        = $email;
		$confluence_settings['api_token']    = $api_token;
		AIAGENT_Confluence_Integration::update_settings( $confluence_settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Confluence settings saved successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle save Zapier settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_zapier_settings( $request ) {
		$enabled     = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		$webhook_url = esc_url_raw( $request->get_param( 'webhook_url' ) );

		$integration_settings                       = get_option( 'aiagent_integrations', array() );
		$integration_settings['zapier_enabled']     = $enabled;
		$integration_settings['zapier_webhook_url'] = $webhook_url;
		update_option( 'aiagent_integrations', $integration_settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Zapier settings saved successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle save Mailchimp settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_mailchimp_settings( $request ) {
		$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		$api_key = sanitize_text_field( $request->get_param( 'api_key' ) );
		$list_id = sanitize_text_field( $request->get_param( 'list_id' ) );

		$integration_settings                      = get_option( 'aiagent_integrations', array() );
		$integration_settings['mailchimp_enabled'] = $enabled;
		$integration_settings['mailchimp_api_key'] = $api_key;
		$integration_settings['mailchimp_list_id'] = $list_id;
		update_option( 'aiagent_integrations', $integration_settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Mailchimp settings saved successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle WooCommerce status request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_woocommerce_status( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$is_active  = AIAGENT_WooCommerce_Integration::is_woocommerce_active();
		$is_enabled = AIAGENT_WooCommerce_Integration::is_enabled();
		$settings   = AIAGENT_WooCommerce_Integration::get_settings();

		return rest_ensure_response(
			array(
				'success'             => true,
				'woocommerce_active'  => $is_active,
				'integration_enabled' => $is_enabled,
				'settings'            => $settings,
			)
		);
	}

	/**
	 * Handle WooCommerce search request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_search( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$query = $request->get_param( 'query' );
		$limit = $request->get_param( 'limit' ) ?? 10;

		$woo      = new AIAGENT_WooCommerce_Integration();
		$settings = AIAGENT_WooCommerce_Integration::get_settings();

		// If query is empty, get all products.
		if ( empty( $query ) ) {
			$products = $woo->get_all_products( $limit );
		} else {
			$products = $woo->search_products( $query, $limit );
		}

		// Get related products from first result if enabled.
		$related = array();
		if ( ! empty( $products ) && $settings['show_related_products'] ) {
			$related = $woo->get_related_products( $products[0]['id'], 3 );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'products' => $products,
				'related'  => $related,
				'query'    => $query,
				'count'    => count( $products ),
			)
		);
	}

	/**
	 * Handle WooCommerce product request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_product( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$product_id = $request->get_param( 'id' );

		$woo     = new AIAGENT_WooCommerce_Integration();
		$product = $woo->get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'product_not_found', __( 'Product not found.', 'ai-agent-for-website' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'product' => $product,
			)
		);
	}

	/**
	 * Handle WooCommerce related products request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_related( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$product_id = $request->get_param( 'id' );
		$limit      = $request->get_param( 'limit' ) ?? 4;

		$woo        = new AIAGENT_WooCommerce_Integration();
		$related    = $woo->get_related_products( $product_id, $limit );
		$upsells    = $woo->get_upsell_products( $product_id, $limit );
		$crosssells = $woo->get_crosssell_products( $product_id, $limit );

		return rest_ensure_response(
			array(
				'success'     => true,
				'related'     => $related,
				'upsells'     => $upsells,
				'cross_sells' => $crosssells,
			)
		);
	}

	/**
	 * Handle WooCommerce compare request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_compare( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$settings = AIAGENT_WooCommerce_Integration::get_settings();
		if ( empty( $settings['show_product_comparison'] ) ) {
			return new WP_Error( 'comparison_disabled', __( 'Product comparison is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$product_ids = $request->get_param( 'product_ids' );

		if ( count( $product_ids ) < 2 ) {
			return new WP_Error( 'insufficient_products', __( 'Please select at least 2 products to compare.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$woo        = new AIAGENT_WooCommerce_Integration();
		$comparison = $woo->compare_products( $product_ids );

		return rest_ensure_response(
			array(
				'success'    => true,
				'comparison' => $comparison,
			)
		);
	}

	/**
	 * Handle WooCommerce add to cart request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_add_to_cart( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$settings = AIAGENT_WooCommerce_Integration::get_settings();
		if ( empty( $settings['show_add_to_cart'] ) ) {
			return new WP_Error( 'add_to_cart_disabled', __( 'Add to cart is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$product_id   = $request->get_param( 'product_id' );
		$quantity     = $request->get_param( 'quantity' ) ?? 1;
		$variation_id = $request->get_param( 'variation_id' ) ?? 0;
		$variation    = $request->get_param( 'variation' ) ?? array();

		$woo    = new AIAGENT_WooCommerce_Integration();
		$result = $woo->add_to_cart( $product_id, $quantity, $variation_id, $variation );

		if ( ! $result['success'] ) {
			return new WP_Error( 'add_to_cart_failed', $result['message'], array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle WooCommerce cart request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_cart( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$woo  = new AIAGENT_WooCommerce_Integration();
		$cart = $woo->get_cart();

		return rest_ensure_response(
			array(
				'success' => true,
				'cart'    => $cart,
			)
		);
	}

	/**
	 * Handle WooCommerce remove from cart request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_remove_from_cart( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$cart_item_key = $request->get_param( 'cart_item_key' );

		$woo    = new AIAGENT_WooCommerce_Integration();
		$result = $woo->remove_from_cart( $cart_item_key );

		if ( ! $result['success'] ) {
			return new WP_Error( 'remove_failed', $result['message'], array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle WooCommerce categories request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_categories( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$woo        = new AIAGENT_WooCommerce_Integration();
		$categories = $woo->get_categories();

		return rest_ensure_response(
			array(
				'success'    => true,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Handle WooCommerce featured products request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_featured( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$limit = $request->get_param( 'limit' ) ?? 6;

		$woo      = new AIAGENT_WooCommerce_Integration();
		$products = $woo->get_featured_products( $limit );

		return rest_ensure_response(
			array(
				'success'  => true,
				'products' => $products,
			)
		);
	}

	/**
	 * Handle WooCommerce sale products request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_sale( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$limit = $request->get_param( 'limit' ) ?? 6;

		$woo      = new AIAGENT_WooCommerce_Integration();
		$products = $woo->get_sale_products( $limit );

		return rest_ensure_response(
			array(
				'success'  => true,
				'products' => $products,
			)
		);
	}

	/**
	 * Handle WooCommerce bestsellers request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_bestsellers( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$limit = $request->get_param( 'limit' ) ?? 6;

		$woo      = new AIAGENT_WooCommerce_Integration();
		$products = $woo->get_bestsellers( $limit );

		return rest_ensure_response(
			array(
				'success'  => true,
				'products' => $products,
			)
		);
	}

	/**
	 * Handle WooCommerce variations request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_variations( $request ) {
		if ( ! AIAGENT_WooCommerce_Integration::is_enabled() ) {
			return new WP_Error( 'woocommerce_disabled', __( 'WooCommerce integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$product_id = $request->get_param( 'id' );

		$woo        = new AIAGENT_WooCommerce_Integration();
		$variations = $woo->get_product_variations( $product_id );

		return rest_ensure_response(
			array(
				'success'    => true,
				'variations' => $variations,
			)
		);
	}

	/**
	 * Handle save WooCommerce settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_woocommerce_settings( $request ) {
		$settings = array(
			'enabled'                 => rest_sanitize_boolean( $request->get_param( 'enabled' ) ),
			'show_prices'             => rest_sanitize_boolean( $request->get_param( 'show_prices' ) ),
			'show_add_to_cart'        => rest_sanitize_boolean( $request->get_param( 'show_add_to_cart' ) ),
			'show_related_products'   => rest_sanitize_boolean( $request->get_param( 'show_related_products' ) ),
			'show_product_comparison' => rest_sanitize_boolean( $request->get_param( 'show_product_comparison' ) ),
			'max_products_display'    => absint( $request->get_param( 'max_products_display' ) ) > 0 ? absint( $request->get_param( 'max_products_display' ) ) : 6,
			'search_in_description'   => rest_sanitize_boolean( $request->get_param( 'search_in_description' ) ),
			'include_out_of_stock'    => rest_sanitize_boolean( $request->get_param( 'include_out_of_stock' ) ),
			// Knowledge base sync settings.
			'sync_to_kb'              => rest_sanitize_boolean( $request->get_param( 'sync_to_kb' ) ),
			'auto_sync'               => rest_sanitize_boolean( $request->get_param( 'auto_sync' ) ),
			'kb_include_descriptions' => rest_sanitize_boolean( $request->get_param( 'kb_include_descriptions' ) ),
			'kb_include_prices'       => rest_sanitize_boolean( $request->get_param( 'kb_include_prices' ) ),
			'kb_include_categories'   => rest_sanitize_boolean( $request->get_param( 'kb_include_categories' ) ),
			'kb_include_attributes'   => rest_sanitize_boolean( $request->get_param( 'kb_include_attributes' ) ),
			'kb_include_stock_status' => rest_sanitize_boolean( $request->get_param( 'kb_include_stock_status' ) ),
		);

		AIAGENT_WooCommerce_Integration::update_settings( $settings );

		// If sync_to_kb is enabled and this is a new enable, trigger sync.
		if ( $settings['sync_to_kb'] && $settings['enabled'] ) {
			$sync_result = AIAGENT_WooCommerce_Integration::sync_products_to_knowledge_base();
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'message'     => __( 'WooCommerce settings saved successfully.', 'ai-agent-for-website' ),
				'sync_result' => $sync_result ?? null,
			)
		);
	}

	/**
	 * Handle WooCommerce sync to knowledge base request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_woocommerce_sync_to_kb( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		if ( ! AIAGENT_WooCommerce_Integration::is_woocommerce_active() ) {
			return new WP_Error( 'woocommerce_not_active', __( 'WooCommerce is not active.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$result = AIAGENT_WooCommerce_Integration::sync_products_to_knowledge_base();

		if ( $result['success'] ) {
			return rest_ensure_response( $result );
		} else {
			return new WP_Error( 'sync_failed', $result['message'], array( 'status' => 400 ) );
		}
	}

	/**
	 * Handle Google Calendar auth URL request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_gcalendar_auth_url( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$gcalendar = new AIAGENT_Google_Calendar_Integration();
		$auth_url  = $gcalendar->get_auth_url();

		if ( is_wp_error( $auth_url ) ) {
			return $auth_url;
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'auth_url' => $auth_url,
			)
		);
	}

	/**
	 * Handle Google Calendar disconnect request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_gcalendar_disconnect( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		AIAGENT_Google_Calendar_Integration::delete_tokens();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Disconnected from Google Calendar.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle Google Calendar list calendars request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_gcalendar_list_calendars( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$gcalendar = new AIAGENT_Google_Calendar_Integration();
		$calendars = $gcalendar->list_calendars();

		if ( is_wp_error( $calendars ) ) {
			return $calendars;
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'calendars' => $calendars,
			)
		);
	}

	/**
	 * Handle Google Calendar available slots request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_gcalendar_slots( $request ) {
		// Check if calendar is enabled.
		if ( ! AIAGENT_Google_Calendar_Integration::is_enabled() ) {
			return new WP_Error( 'calendar_disabled', __( 'Google Calendar integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$start_date = $request->get_param( 'start_date' );
		$days_ahead = $request->get_param( 'days_ahead' );

		$gcalendar = new AIAGENT_Google_Calendar_Integration();
		$slots     = $gcalendar->get_available_slots( $start_date, $days_ahead );

		if ( is_wp_error( $slots ) ) {
			return $slots;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'slots'   => $slots,
			)
		);
	}

	/**
	 * Handle Google Calendar create event request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_gcalendar_create_event( $request ) {
		// Check if calendar is enabled.
		if ( ! AIAGENT_Google_Calendar_Integration::is_enabled() ) {
			return new WP_Error( 'calendar_disabled', __( 'Google Calendar integration is not enabled.', 'ai-agent-for-website' ), array( 'status' => 400 ) );
		}

		$event_data = array(
			'title'          => $request->get_param( 'title' ),
			'description'    => $request->get_param( 'description' ) ?? '',
			'start'          => $request->get_param( 'start' ),
			'end'            => $request->get_param( 'end' ),
			'attendee_email' => $request->get_param( 'attendee_email' ),
			'attendee_name'  => $request->get_param( 'attendee_name' ),
			'add_meet'       => $request->get_param( 'add_meet' ) ?? false,
		);

		$gcalendar = new AIAGENT_Google_Calendar_Integration();
		$result    = $gcalendar->create_event( $event_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle Google Calendar status request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_gcalendar_status( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$is_connected = AIAGENT_Google_Calendar_Integration::is_connected();
		$is_enabled   = AIAGENT_Google_Calendar_Integration::is_enabled();
		$settings     = AIAGENT_Google_Calendar_Integration::get_frontend_settings();

		return rest_ensure_response(
			array(
				'success'   => true,
				'connected' => $is_connected,
				'enabled'   => $is_enabled,
				'settings'  => $settings,
			)
		);
	}

	/**
	 * Handle save Google Calendar settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_gcalendar_settings( $request ) {
		$settings = array(
			'enabled'                    => rest_sanitize_boolean( $request->get_param( 'enabled' ) ),
			'client_id'                  => sanitize_text_field( $request->get_param( 'client_id' ) ?? '' ),
			'client_secret'              => sanitize_text_field( $request->get_param( 'client_secret' ) ?? '' ),
			'default_calendar_id'        => sanitize_text_field( $request->get_param( 'default_calendar_id' ) ?? 'primary' ),
			'default_duration'           => absint( $request->get_param( 'default_duration' ) ) > 0 ? absint( $request->get_param( 'default_duration' ) ) : 30,
			'buffer_time'                => absint( $request->get_param( 'buffer_time' ) ),
			'days_ahead'                 => absint( $request->get_param( 'days_ahead' ) ) > 0 ? absint( $request->get_param( 'days_ahead' ) ) : 14,
			'business_hours_start'       => sanitize_text_field( $request->get_param( 'business_hours_start' ) ?? '09:00' ),
			'business_hours_end'         => sanitize_text_field( $request->get_param( 'business_hours_end' ) ?? '17:00' ),
			'working_days'               => $request->get_param( 'working_days' ) ?? array( 1, 2, 3, 4, 5 ),
			'prompt_after_chat'          => rest_sanitize_boolean( $request->get_param( 'prompt_after_chat' ) ),
			'prompt_message'             => sanitize_text_field( $request->get_param( 'prompt_message' ) ?? '' ),
			'event_title_template'       => sanitize_text_field( $request->get_param( 'event_title_template' ) ?? '' ),
			'event_description_template' => sanitize_textarea_field( $request->get_param( 'event_description_template' ) ?? '' ),
		);

		AIAGENT_Google_Calendar_Integration::update_settings( $settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Google Calendar settings saved successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle Calendly auth URL request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_calendly_auth_url( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$calendly = new AIAGENT_Calendly_Integration();
		$auth_url = $calendly->get_auth_url();

		if ( is_wp_error( $auth_url ) ) {
			return $auth_url;
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'auth_url' => $auth_url,
			)
		);
	}

	/**
	 * Handle Calendly disconnect request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_calendly_disconnect( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		AIAGENT_Calendly_Integration::delete_tokens();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Disconnected from Calendly.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle Calendly event types request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_calendly_event_types( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$calendly    = new AIAGENT_Calendly_Integration();
		$event_types = $calendly->get_event_types();

		if ( is_wp_error( $event_types ) ) {
			return $event_types;
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'event_types' => $event_types,
			)
		);
	}

	/**
	 * Handle Calendly status request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_calendly_status( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$is_connected = AIAGENT_Calendly_Integration::is_connected();
		$is_enabled   = AIAGENT_Calendly_Integration::is_enabled();
		$settings     = AIAGENT_Calendly_Integration::get_frontend_settings();

		return rest_ensure_response(
			array(
				'success'   => true,
				'connected' => $is_connected,
				'enabled'   => $is_enabled,
				'settings'  => $settings,
			)
		);
	}

	/**
	 * Handle save Calendly settings request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_save_calendly_settings( $request ) {
		$settings = array(
			'enabled'            => rest_sanitize_boolean( $request->get_param( 'enabled' ) ),
			'integration_type'   => sanitize_text_field( $request->get_param( 'integration_type' ) ?? 'embed' ),
			'scheduling_url'     => esc_url_raw( $request->get_param( 'scheduling_url' ) ?? '' ),
			'client_id'          => sanitize_text_field( $request->get_param( 'client_id' ) ?? '' ),
			'client_secret'      => sanitize_text_field( $request->get_param( 'client_secret' ) ?? '' ),
			'prompt_after_chat'  => rest_sanitize_boolean( $request->get_param( 'prompt_after_chat' ) ),
			'prompt_message'     => sanitize_text_field( $request->get_param( 'prompt_message' ) ?? '' ),
			'button_text'        => sanitize_text_field( $request->get_param( 'button_text' ) ?? 'Schedule a Meeting' ),
			'embed_height'       => absint( $request->get_param( 'embed_height' ) ) > 0 ? absint( $request->get_param( 'embed_height' ) ) : 630,
			'hide_event_details' => rest_sanitize_boolean( $request->get_param( 'hide_event_details' ) ),
			'hide_gdpr_banner'   => rest_sanitize_boolean( $request->get_param( 'hide_gdpr_banner' ) ),
			'primary_color'      => sanitize_hex_color( $request->get_param( 'primary_color' ) ?? '' ),
			'text_color'         => sanitize_hex_color( $request->get_param( 'text_color' ) ?? '' ),
			'background_color'   => sanitize_hex_color( $request->get_param( 'background_color' ) ?? '' ),
		);

		AIAGENT_Calendly_Integration::update_settings( $settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Calendly settings saved successfully.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle live agent status request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_live_agent_status( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$is_enabled   = AIAGENT_Live_Agent_Manager::is_enabled();
		$is_available = AIAGENT_Live_Agent_Manager::is_agent_available();
		$settings     = AIAGENT_Live_Agent_Manager::get_frontend_settings();

		return rest_ensure_response(
			array(
				'success'   => true,
				'enabled'   => $is_enabled,
				'available' => $is_available,
				'settings'  => $settings,
			)
		);
	}

	/**
	 * Handle live agent connect request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_live_agent_connect( $request ) {
		$session_id      = $request->get_param( 'session_id' );
		$user_id         = $request->get_param( 'user_id' );
		$conversation_id = $request->get_param( 'conversation_id' );

		$result = AIAGENT_Live_Agent_Manager::create_session( $conversation_id, $user_id, $session_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle live agent message request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_live_agent_message( $request ) {
		$live_session_id = $request->get_param( 'live_session_id' );
		$message         = $request->get_param( 'message' );
		$sender_type     = $request->get_param( 'sender_type' );
		$sender_id       = $request->get_param( 'sender_id' );

		$result = AIAGENT_Live_Agent_Manager::send_message( $live_session_id, $message, $sender_type, $sender_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle live agent get messages request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_live_agent_get_messages( $request ) {
		$live_session_id = $request->get_param( 'live_session_id' );
		$after_id        = $request->get_param( 'after_id' ) ?? 0;

		$messages = AIAGENT_Live_Agent_Manager::get_messages( $live_session_id, $after_id );

		return rest_ensure_response(
			array(
				'success'  => true,
				'messages' => $messages,
			)
		);
	}

	/**
	 * Handle live agent end session request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_live_agent_end( $request ) {
		$live_session_id = $request->get_param( 'live_session_id' );
		$ended_by        = $request->get_param( 'ended_by' ) ?? 'user';

		$result = AIAGENT_Live_Agent_Manager::end_session( $live_session_id, $ended_by );

		return rest_ensure_response(
			array(
				'success' => $result,
				'message' => $result ? __( 'Session ended.', 'ai-agent-for-website' ) : __( 'Failed to end session.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle live agent session status request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_live_agent_session_status( $request ) {
		$session_id = $request->get_param( 'session_id' );

		$status = AIAGENT_Live_Agent_Manager::get_session_status( $session_id );

		return rest_ensure_response(
			array_merge( array( 'success' => true ), $status )
		);
	}

	/**
	 * Handle live agent heartbeat request (for agents to stay online).
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_live_agent_heartbeat( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in.', 'ai-agent-for-website' ), array( 'status' => 401 ) );
		}

		$result = AIAGENT_Live_Agent_Manager::agent_heartbeat( $user_id );

		// Get active sessions for this agent.
		$sessions = AIAGENT_Live_Agent_Manager::get_agent_sessions( $user_id );

		return rest_ensure_response(
			array(
				'success'  => $result,
				'sessions' => $sessions,
			)
		);
	}

	/**
	 * Handle live agent set status request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_live_agent_set_status( $request ) {
		$status  = $request->get_param( 'status' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in.', 'ai-agent-for-website' ), array( 'status' => 401 ) );
		}

		// Allow administrators to always set their status (for testing purposes).
		$can_be_agent = AIAGENT_Live_Agent_Manager::can_user_be_agent( $user_id );
		$is_admin     = current_user_can( 'manage_options' );

		if ( ! $can_be_agent && ! $is_admin ) {
			return new WP_Error( 'not_authorized', __( 'You are not authorized to be a live agent.', 'ai-agent-for-website' ), array( 'status' => 403 ) );
		}

		$result = AIAGENT_Live_Agent_Manager::set_agent_status( $user_id, $status );

		return rest_ensure_response(
			array(
				'success' => $result,
				'status'  => $status,
				'message' => $result
					? sprintf(
						/* translators: %s: Status */
						__( 'Status set to %s.', 'ai-agent-for-website' ),
						$status
					)
					: __( 'Failed to set status.', 'ai-agent-for-website' ),
			)
		);
	}

	/**
	 * Handle get live agent sessions request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_live_agent_get_sessions( $request ) {
		// Unused parameter kept for REST API callback signature.
		unset( $request );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in.', 'ai-agent-for-website' ), array( 'status' => 401 ) );
		}

		$sessions = AIAGENT_Live_Agent_Manager::get_agent_sessions( $user_id );

		return rest_ensure_response(
			array(
				'success'  => true,
				'sessions' => $sessions,
			)
		);
	}

	/**
	 * Handle live agent accept session request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_live_agent_accept( $request ) {
		$live_session_id = $request->get_param( 'live_session_id' );
		$user_id         = get_current_user_id();

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in.', 'ai-agent-for-website' ), array( 'status' => 401 ) );
		}

		$result = AIAGENT_Live_Agent_Manager::accept_session( $live_session_id, $user_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Chat accepted successfully.', 'ai-agent-for-website' ),
			)
		);
	}
}
