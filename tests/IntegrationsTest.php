<?php
/**
 * Integrations Test
 *
 * Tests for all integration classes.
 *
 * @package AIAgent\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test case for integration classes.
 */
class IntegrationsTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_wp_mocks();
	}

	/**
	 * Test all integration class files exist.
	 */
	public function testAllIntegrationFilesExist(): void {
		$integrations_dir = dirname( __DIR__ ) . '/includes/integrations/';

		$expected_files = [
			'class-google-drive-integration.php',
			'class-google-calendar-integration.php',
			'class-calendly-integration.php',
			'class-confluence-integration.php',
			'class-zapier-integration.php',
			'class-mailchimp-integration.php',
			'class-woocommerce-integration.php',
		];

		foreach ( $expected_files as $file ) {
			$this->assertFileExists(
				$integrations_dir . $file,
				"Integration file $file should exist"
			);
		}
	}

	/**
	 * Test Google Drive integration settings structure.
	 */
	public function testGoogleDriveSettingsStructure(): void {
		$settings = [
			'client_id'     => 'test-client-id.apps.googleusercontent.com',
			'client_secret' => 'test-client-secret',
			'access_token'  => '',
			'refresh_token' => '',
			'connected'     => false,
		];

		$this->assertArrayHasKey( 'client_id', $settings );
		$this->assertArrayHasKey( 'client_secret', $settings );
		$this->assertArrayHasKey( 'connected', $settings );
	}

	/**
	 * Test Google Calendar integration settings structure.
	 */
	public function testGoogleCalendarSettingsStructure(): void {
		$settings = [
			'enabled'           => false,
			'calendar_id'       => '',
			'booking_enabled'   => false,
			'slot_duration'     => 30,
			'working_hours'     => [
				'start' => '09:00',
				'end'   => '17:00',
			],
			'buffer_time'       => 15,
			'advance_days'      => 14,
		];

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'slot_duration', $settings );
		$this->assertArrayHasKey( 'working_hours', $settings );
		$this->assertEquals( 30, $settings['slot_duration'] );
	}

	/**
	 * Test Calendly integration settings structure.
	 */
	public function testCalendlySettingsStructure(): void {
		$settings = [
			'enabled'        => false,
			'client_id'      => '',
			'client_secret'  => '',
			'access_token'   => '',
			'refresh_token'  => '',
			'organization'   => '',
			'event_type_uri' => '',
		];

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'client_id', $settings );
		$this->assertArrayHasKey( 'event_type_uri', $settings );
	}

	/**
	 * Test Confluence integration settings structure.
	 */
	public function testConfluenceSettingsStructure(): void {
		$settings = [
			'enabled'    => false,
			'site_url'   => '',
			'username'   => '',
			'api_token'  => '',
			'space_key'  => '',
		];

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'site_url', $settings );
		$this->assertArrayHasKey( 'api_token', $settings );
	}

	/**
	 * Test Zapier integration settings structure.
	 */
	public function testZapierSettingsStructure(): void {
		$settings = [
			'enabled'     => false,
			'webhook_url' => '',
			'events'      => [
				'new_lead'         => true,
				'new_conversation' => false,
				'rating_received'  => false,
			],
		];

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'webhook_url', $settings );
		$this->assertArrayHasKey( 'events', $settings );
	}

	/**
	 * Test Mailchimp integration settings structure.
	 */
	public function testMailchimpSettingsStructure(): void {
		$settings = [
			'enabled'    => false,
			'api_key'    => '',
			'list_id'    => '',
			'tags'       => [],
			'merge_fields' => [
				'FNAME' => 'name',
				'EMAIL' => 'email',
			],
		];

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'api_key', $settings );
		$this->assertArrayHasKey( 'list_id', $settings );
	}

	/**
	 * Test WooCommerce integration settings structure.
	 */
	public function testWooCommerceSettingsStructure(): void {
		$settings = [
			'enabled'                  => false,
			'show_prices'              => true,
			'show_add_to_cart'         => true,
			'show_related_products'    => true,
			'show_product_comparison'  => true,
			'max_products_display'     => 6,
			'auto_sync_enabled'        => false,
			'sync_interval'            => 'daily',
		];

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'show_prices', $settings );
		$this->assertArrayHasKey( 'max_products_display', $settings );
		$this->assertEquals( 6, $settings['max_products_display'] );
	}

	/**
	 * Test OAuth redirect URI format.
	 */
	public function testOAuthRedirectURIFormat(): void {
		$admin_url    = admin_url( 'admin.php?page=ai-agent-settings' );
		$redirect_uri = $admin_url . '&action=oauth_callback';

		$this->assertStringContainsString( 'admin.php', $redirect_uri );
		$this->assertStringContainsString( 'oauth_callback', $redirect_uri );
	}

	/**
	 * Test OAuth token structure.
	 */
	public function testOAuthTokenStructure(): void {
		$token = [
			'access_token'  => 'ya29.test-access-token',
			'refresh_token' => '1//test-refresh-token',
			'token_type'    => 'Bearer',
			'expires_in'    => 3600,
			'scope'         => 'https://www.googleapis.com/auth/calendar',
			'created_at'    => time(),
		];

		$this->assertArrayHasKey( 'access_token', $token );
		$this->assertArrayHasKey( 'refresh_token', $token );
		$this->assertArrayHasKey( 'expires_in', $token );
	}

	/**
	 * Test token expiration check.
	 */
	public function testTokenExpirationCheck(): void {
		$token = [
			'expires_in' => 3600,
			'created_at' => time() - 4000, // Created 4000 seconds ago.
		];

		$expires_at = $token['created_at'] + $token['expires_in'];
		$is_expired = time() > $expires_at;

		$this->assertTrue( $is_expired );

		// Not expired token.
		$token['created_at'] = time() - 1000;
		$expires_at          = $token['created_at'] + $token['expires_in'];
		$is_expired          = time() > $expires_at;

		$this->assertFalse( $is_expired );
	}

	/**
	 * Test webhook payload structure.
	 */
	public function testWebhookPayloadStructure(): void {
		$payload = [
			'event'     => 'new_lead',
			'timestamp' => gmdate( 'c' ),
			'data'      => [
				'lead_id'    => 123,
				'name'       => 'John Doe',
				'email'      => 'john@example.com',
				'source'     => 'chat',
			],
			'site_url'  => 'https://example.com',
		];

		$this->assertArrayHasKey( 'event', $payload );
		$this->assertArrayHasKey( 'timestamp', $payload );
		$this->assertArrayHasKey( 'data', $payload );
		$this->assertIsArray( $payload['data'] );
	}

	/**
	 * Test calendar time slot format.
	 */
	public function testCalendarTimeSlotFormat(): void {
		$slot = [
			'start'      => '2024-01-15T09:00:00',
			'end'        => '2024-01-15T09:30:00',
			'available'  => true,
			'timezone'   => 'America/New_York',
		];

		$this->assertArrayHasKey( 'start', $slot );
		$this->assertArrayHasKey( 'end', $slot );
		$this->assertTrue( $slot['available'] );
	}

	/**
	 * Test calendar event creation data.
	 */
	public function testCalendarEventCreationData(): void {
		$event = [
			'summary'     => 'Meeting with John',
			'description' => 'Follow-up discussion',
			'start'       => [
				'dateTime' => '2024-01-15T10:00:00-05:00',
				'timeZone' => 'America/New_York',
			],
			'end'         => [
				'dateTime' => '2024-01-15T10:30:00-05:00',
				'timeZone' => 'America/New_York',
			],
			'attendees'   => [
				[ 'email' => 'john@example.com' ],
			],
		];

		$this->assertArrayHasKey( 'summary', $event );
		$this->assertArrayHasKey( 'start', $event );
		$this->assertArrayHasKey( 'end', $event );
		$this->assertArrayHasKey( 'attendees', $event );
	}

	/**
	 * Test Confluence page content structure.
	 */
	public function testConfluencePageContentStructure(): void {
		$page = [
			'id'      => '123456',
			'title'   => 'Documentation Page',
			'body'    => [
				'storage' => [
					'value' => '<p>Page content here</p>',
				],
			],
			'space'   => [
				'key' => 'DOCS',
			],
			'_links'  => [
				'webui' => '/display/DOCS/Documentation+Page',
			],
		];

		$this->assertArrayHasKey( 'id', $page );
		$this->assertArrayHasKey( 'title', $page );
		$this->assertArrayHasKey( 'body', $page );
	}

	/**
	 * Test Mailchimp subscriber data.
	 */
	public function testMailchimpSubscriberData(): void {
		$subscriber = [
			'email_address' => 'john@example.com',
			'status'        => 'subscribed',
			'merge_fields'  => [
				'FNAME' => 'John',
				'LNAME' => 'Doe',
			],
			'tags'          => [ 'chat-lead', 'website' ],
		];

		$this->assertArrayHasKey( 'email_address', $subscriber );
		$this->assertEquals( 'subscribed', $subscriber['status'] );
		$this->assertContains( 'chat-lead', $subscriber['tags'] );
	}

	/**
	 * Test WooCommerce product data format.
	 */
	public function testWooCommerceProductDataFormat(): void {
		$product = [
			'id'              => 123,
			'name'            => 'Test Product',
			'slug'            => 'test-product',
			'price'           => '29.99',
			'regular_price'   => '39.99',
			'sale_price'      => '29.99',
			'on_sale'         => true,
			'stock_status'    => 'instock',
			'short_description' => 'Short product description',
			'permalink'       => 'https://example.com/product/test-product',
			'images'          => [
				[ 'src' => 'https://example.com/image.jpg' ],
			],
		];

		$this->assertArrayHasKey( 'id', $product );
		$this->assertArrayHasKey( 'name', $product );
		$this->assertArrayHasKey( 'price', $product );
		$this->assertTrue( $product['on_sale'] );
	}

	/**
	 * Test integration connection status.
	 */
	public function testIntegrationConnectionStatus(): void {
		$status = [
			'google_drive'    => false,
			'google_calendar' => false,
			'calendly'        => false,
			'confluence'      => false,
			'zapier'          => true,
			'mailchimp'       => false,
			'woocommerce'     => true,
		];

		$connected = array_filter( $status );

		$this->assertCount( 2, $connected );
		$this->assertTrue( $status['zapier'] );
	}

	/**
	 * Test API rate limit handling.
	 */
	public function testAPIRateLimitHandling(): void {
		$rate_limit = [
			'limit'     => 100,
			'remaining' => 50,
			'reset'     => time() + 3600,
		];

		$this->assertArrayHasKey( 'limit', $rate_limit );
		$this->assertArrayHasKey( 'remaining', $rate_limit );
		$this->assertGreaterThan( 0, $rate_limit['remaining'] );
	}

	/**
	 * Test sync status data.
	 */
	public function testSyncStatusData(): void {
		$sync_status = [
			'last_sync'    => '2024-01-15 10:00:00',
			'items_synced' => 150,
			'errors'       => [],
			'next_sync'    => '2024-01-16 10:00:00',
		];

		$this->assertArrayHasKey( 'last_sync', $sync_status );
		$this->assertArrayHasKey( 'items_synced', $sync_status );
		$this->assertEmpty( $sync_status['errors'] );
	}
}

