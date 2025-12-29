<?php
/**
 * API Keys Configuration Example File
 *
 * This is an example configuration file for API keys.
 * Copy this file to api-keys.php and update with your actual keys.
 *
 * IMPORTANT: The api-keys.php file should NEVER be committed to version control.
 *
 * @package AI_Agent_For_Website
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI/LLM API Keys
 * Supported providers: Groq, OpenAI, Anthropic
 */

// Groq API Key (Primary AI provider).
// Get your API key from: https://console.groq.com/
if ( ! defined( 'AIAGENT_GROQ_API_KEY' ) ) {
	define( 'AIAGENT_GROQ_API_KEY', 'your-groq-api-key-here' );
}

// OpenAI API Key (Alternative AI provider).
// Get your API key from: https://platform.openai.com/
if ( ! defined( 'AIAGENT_OPENAI_API_KEY' ) ) {
	define( 'AIAGENT_OPENAI_API_KEY', 'your-openai-api-key-here' );
}

// Anthropic API Key (Alternative AI provider).
// Get your API key from: https://console.anthropic.com/
if ( ! defined( 'AIAGENT_ANTHROPIC_API_KEY' ) ) {
	define( 'AIAGENT_ANTHROPIC_API_KEY', 'your-anthropic-api-key-here' );
}

/**
 * Integration API Keys
 */

// Google OAuth Credentials (for Google Drive and Calendar).
// Get credentials from: https://console.cloud.google.com/
if ( ! defined( 'AIAGENT_GOOGLE_CLIENT_ID' ) ) {
	define( 'AIAGENT_GOOGLE_CLIENT_ID', 'your-google-client-id-here' );
}

if ( ! defined( 'AIAGENT_GOOGLE_CLIENT_SECRET' ) ) {
	define( 'AIAGENT_GOOGLE_CLIENT_SECRET', 'your-google-client-secret-here' );
}

// Calendly OAuth Credentials.
// Get credentials from: https://developer.calendly.com/
if ( ! defined( 'AIAGENT_CALENDLY_CLIENT_ID' ) ) {
	define( 'AIAGENT_CALENDLY_CLIENT_ID', 'your-calendly-client-id-here' );
}

if ( ! defined( 'AIAGENT_CALENDLY_CLIENT_SECRET' ) ) {
	define( 'AIAGENT_CALENDLY_CLIENT_SECRET', 'your-calendly-client-secret-here' );
}

// Confluence API Credentials.
// Get your API token from: https://id.atlassian.com/manage-profile/security/api-tokens
if ( ! defined( 'AIAGENT_CONFLUENCE_API_TOKEN' ) ) {
	define( 'AIAGENT_CONFLUENCE_API_TOKEN', 'your-confluence-api-token-here' );
}

// Mailchimp API Key.
// Get your API key from: https://admin.mailchimp.com/account/api/
if ( ! defined( 'AIAGENT_MAILCHIMP_API_KEY' ) ) {
	define( 'AIAGENT_MAILCHIMP_API_KEY', 'your-mailchimp-api-key-here' );
}

// Zapier Webhook URL.
// Create a webhook at: https://zapier.com/
if ( ! defined( 'AIAGENT_ZAPIER_WEBHOOK_URL' ) ) {
	define( 'AIAGENT_ZAPIER_WEBHOOK_URL', 'https://hooks.zapier.com/hooks/catch/your-webhook-url' );
}

/**
 * Test Mode Configuration
 *
 * When AIAGENT_TEST_MODE is true, the plugin will use mock responses
 * instead of making actual API calls.
 */
if ( ! defined( 'AIAGENT_TEST_MODE' ) ) {
	define( 'AIAGENT_TEST_MODE', false );
}

/**
 * Debug Mode Configuration
 *
 * When AIAGENT_DEBUG_MODE is true, additional debug information
 * will be logged.
 */
if ( ! defined( 'AIAGENT_DEBUG_MODE' ) ) {
	define( 'AIAGENT_DEBUG_MODE', false );
}

