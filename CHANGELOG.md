# Changelog

All notable changes to the AI Agent for Website plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.8.0] - 2024-12-29

### Added
- **Google Calendar Integration**: Allow users to book meetings after chat conversations
  - OAuth 2.0 authentication with Google Calendar API
  - Automatic availability detection using Google Calendar free/busy API
  - Configurable business hours and working days
  - Buffer time between appointments
  - Days ahead booking limit
  - Google Meet link generation for scheduled meetings
  - Calendar invites sent to user's email
- New admin settings section "Scheduling" for calendar configuration
- Calendar booking modal in chat widget with step-by-step flow:
  - Prompt to schedule after conversation ends
  - Date/time slot selection with navigation
  - Meeting details form (title, description)
  - Booking confirmation
- REST API endpoints for calendar integration:
  - `GET /gcalendar/auth-url` - OAuth authorization URL
  - `POST /gcalendar/disconnect` - Disconnect account
  - `GET /gcalendar/calendars` - List available calendars
  - `GET /gcalendar/slots` - Get available time slots
  - `POST /gcalendar/create-event` - Create calendar event
  - `GET /gcalendar/status` - Check integration status
  - `POST /settings/gcalendar` - Save calendar settings
- Customizable prompt message for calendar booking suggestion
- Event title and description templates with placeholder support
- **Calendly Integration**: Easy scheduling via Calendly
  - Support for popup widget, inline embed, and external link modes
  - Customizable scheduling URL (user profile or specific event type)
  - Pre-fill user info from chat session (name, email)
  - Configurable prompt message and button text
  - Widget appearance options (height, colors, hide event details/GDPR banner)
  - Optional OAuth 2.0 connection for advanced features (event types)
- New Calendly admin settings in "Scheduling" section
- REST API endpoints for Calendly integration:
  - `GET /calendly/auth-url` - OAuth authorization URL
  - `POST /calendly/disconnect` - Disconnect account
  - `GET /calendly/event-types` - List event types
  - `GET /calendly/status` - Check integration status
  - `POST /settings/calendly` - Save Calendly settings

### Changed
- Enhanced chat widget flow to include calendar booking after rating
- Updated admin JavaScript with Google Calendar connection handlers

## [1.7.1] - 2024-12-29

### Fixed
- Chat widget container no longer blocks clicks on page elements when widget is closed
- Added `pointer-events` CSS properties to ensure proper click behavior

## [1.7.0] - 2024-12-20

### Added
- **WooCommerce Integration**: Full e-commerce integration with AI chat agent
  - Automatic WooCommerce detection and configuration
  - Product search through chat interface with natural language queries
  - Display relevant products with images, prices, and descriptions
  - Related products and smart suggestions based on user queries
  - Product comparison feature to compare multiple products side-by-side
  - Add to cart functionality directly from chat widget
  - Configurable display options (show prices, show add to cart, etc.)
- **Product Knowledge Base Sync**: Sync WooCommerce products to AI knowledge base
  - Configurable sync options (descriptions, prices, categories, attributes, stock status)
  - Manual "Sync Now" button for on-demand synchronization
  - Auto-sync option to automatically update KB when products change
  - Last sync timestamp and product count display
- New REST API endpoints for WooCommerce product operations and KB sync
- WooCommerce integration modal in admin settings with KB sync section
- Product card and comparison UI components in chat widget

### Changed
- Enhanced chat widget JavaScript with product display capabilities
- Extended admin settings with WooCommerce integration category
- Added WooCommerce-specific CSS styles for product display
- AI assistant can now answer questions about products from knowledge base

## [1.6.0] - 2024-12-20

### Added
- **Integrations UI Redesign**: Modern card-based grid layout for integrations
- **Modal Settings**: Integration settings now open in popup modals for cleaner UX
- **AJAX Save**: Save individual integration settings without affecting others
- **Categorized Integrations**: Integrations organized into Knowledge Base and Conversation categories
- **Page Title Styling**: Improved admin page header with icon and better alignment

### Changed
- Integrations tab now displays all integrations in responsive grid format
- Each integration has its own modal with dedicated Save/Cancel buttons
- REST API endpoints added for saving individual integration settings
- Updated readme.txt with WordPress 6.7 compatibility and enhanced content

## [1.5.0] - 2024-12-20

### Added
- **Notification Center**: Centralized admin notifications for new conversations, leads, and system events
- **AI-Powered Validation**: Automatically validate conversations using AI to identify qualified leads
- **Convert to Lead with AI**: Use AI analysis to convert promising conversations to leads with confidence scoring
- **Close Conversations with AI**: Intelligently close invalid or not interested conversations using AI
- **Activity Log Center**: Comprehensive activity logging for all system events
- **Notification Settings**: Configure email notifications, recipients, and event triggers
- **Log Settings**: Configure log categories, retention period, and external export
- **Zapier Log Export**: Export activity logs to Zapier webhooks for external tracking
- **Mailchimp Tagging**: Add activity-based tags to Mailchimp subscribers for user/lead events
- **Unread Badge**: Admin menu shows unread notification count
- **CSV Export**: Export activity logs to CSV for reporting
- **Automatic Cleanup**: Configurable log retention with automatic cleanup

### Database
- Added `aiagent_notifications` table for storing admin notifications
- Added `aiagent_activity_logs` table for comprehensive activity logging

### Changed
- Added new "Notifications & Logs" settings tab for centralized configuration
- Enhanced Zapier integration with additional webhook events for logs

## [1.4.0] - 2024-12-20

### Added
- **Leads Management**: Convert conversations into leads for CRM workflow
- **Admin Review**: Review conversations and convert to leads or close them
- **Zapier Integration**: Sync leads with external CRM platforms via webhook
- **Mailchimp Integration**: Automatically subscribe users to newsletter lists
- **Consent System**: Add consent checkboxes before starting conversation
  - AI Consent (Mandatory)
  - Newsletter Consent (Optional)
  - Promotional Consent (Optional)
- **Editable "Powered By" Text**: Customize the branding text at widget bottom
- **Widget Customization**: New color options for bubble, header, text, and background
- **Live Preview Enhancements**: Real-time preview for button size, animation, and sound settings

### Changed
- Moved "AI Name" field from General tab to Widget Appearance tab
- Improved widget appearance settings organization
- Enhanced admin preview with interactive animations

### Database
- Added `aiagent_leads` table for lead management
- Added consent fields to user data storage

## [1.3.0] - 2024-12-19

### Added
- **Custom File Upload**: Upload documents directly to knowledge base (PDF, DOC, DOCX, TXT, CSV, MD, RTF)
- **Google Drive Integration**: Connect Google Drive and import documents via OAuth 2.0
- **Confluence Integration**: Connect Atlassian Confluence and import wiki pages
- File processor class with support for multiple document formats
- Drag & drop file upload interface with progress indicators
- REST API endpoints for file upload, Google Drive, and Confluence operations
- Integration configuration in Settings > Integrations tab
- File/page browsers in Knowledge Base page for connected integrations
- Uploaded files management table with delete functionality
- Secure file storage with .htaccess protection

### Changed
- Reorganized Knowledge Base page with 3-column grid layout
- Improved Integrations tab with actual connection settings (previously placeholder)
- Enhanced admin UI with file type badges and source icons

### Security
- MIME type validation for uploaded files
- File size limits (10MB max)
- Nonce verification on all new endpoints
- OAuth state verification for Google Drive

## [1.2.0] - 2024-12-19

### Added
- Configurable "Powered By" text - option to show/hide footer branding
- AI suggestions modal with regenerate and apply functionality

### Changed
- Settings page layout: Widget Appearance and Chat Widget Preview now display side-by-side
- AI suggestion buttons now open a modal dialog instead of inline display
- Improved responsive design for settings page (stacks vertically on smaller screens)

### Improved
- Better user experience with modal-based AI suggestions (regenerate, apply, or cancel)
- Real-time preview updates when toggling "Show Powered By" setting

## [1.1.0] - 2024-12-18

### Added
- Phone number field option in user info form before starting chat
- "Show Phone Number Field" and "Make Phone Required" settings options
- Improved "Add Custom Text" form in Knowledge Base with category selection
- Character count display for custom text entries
- AI-powered "Auto Detect Pillar Pages" feature to identify important pages for knowledge base
- AI suggestion buttons for Welcome Message and System Instruction fields
- Live chat widget preview in Settings page with real-time updates
- Database support for storing user phone numbers

### Changed
- Updated all chat widget icons to Lucide-style SVG icons (open-source)
- Enhanced Knowledge Base UI with better form organization
- Database schema updated to version 1.2.0

### Improved
- Better user experience with live preview of widget customizations
- More intuitive knowledge base content management

## [1.0.2] - 2024-12-18

### Added
- GitHub-based automatic update system for seamless plugin updates from WordPress dashboard
- Auto-release GitHub Action workflow that triggers on main branch updates
- Plugin updater module that checks GitHub releases for new versions
- One-click update capability directly from wp-admin Plugins page
- Release notes display in WordPress update popup

### Changed
- Improved CI/CD pipeline with automated build and release process

### Fixed
- Plugin URI now correctly points to GitHub repository instead of author website

## [1.0.1] - 2024-12-18

### Fixed
- Fixed all PHPCS (WordPress Coding Standards) errors across all PHP files
- Added proper `@package` tags to all file comments
- Added class and function documentation comments with `@param` and `@return` tags
- Replaced `_e()` with `esc_html_e()` and `esc_attr_e()` for proper output escaping
- Added proper output escaping for all variables using `esc_html()`, `esc_attr()`, `esc_url()`
- Fixed inline comments to end with proper punctuation
- Applied Yoda condition checks where required
- Added `wp_unslash()` before sanitization of POST/GET data
- Replaced `date()` with `gmdate()` for timezone-safe date formatting
- Replaced short ternary operators with explicit conditionals
- Added translators comments for sprintf placeholders
- Added proper phpcs:ignore comments for intentional direct database queries
- Fixed variable naming to use snake_case format

### Added
- CI/CD workflows for automated testing
- GitHub Actions for release automation
- CONTRIBUTING.md documentation
- CHANGELOG.md documentation

## [1.0.0] - 2024-12-18

### Added
- Initial release of AI Agent for Website plugin
- AI-powered chat widget with customizable appearance
- Integration with Groq, Gemini, and Meta Llama AI providers
- Knowledge base management with URL content fetching
- Admin settings page for configuration
- Conversation history and user management
- REST API endpoints for chat functionality
- Shortcode support `[ai_agent_chat]` for inline embedding
- Customizable widget position (bottom-left, bottom-right)
- Custom avatar support for the AI assistant
- Option to require/skip user information collection
- Database tables for storing users, conversations, and messages
- Session-based conversation continuity

### Security
- Nonce verification for REST API requests
- Sanitization of user inputs
- Capability checks for admin operations

[Unreleased]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.8.0...HEAD
[1.8.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.7.1...v1.8.0
[1.7.1]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.7.0...v1.7.1
[1.7.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/rajanvijayan/ai-agent-for-website/releases/tag/v1.0.0

