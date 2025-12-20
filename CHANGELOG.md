# Changelog

All notable changes to the AI Agent for Website plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.4.0...HEAD
[1.4.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/rajanvijayan/ai-agent-for-website/releases/tag/v1.0.0

