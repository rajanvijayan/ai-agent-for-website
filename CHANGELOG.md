# Changelog

All notable changes to the AI Agent for Website plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.2] - 2024-12-18

### Added
- GitHub-based automatic update system for seamless plugin updates from WordPress dashboard
- Auto-release GitHub Action workflow that triggers on main branch updates
- Plugin updater module that checks GitHub releases for new versions
- One-click update capability directly from wp-admin Plugins page
- Release notes display in WordPress update popup

### Changed
- Improved CI/CD pipeline with automated build and release process

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

[Unreleased]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/rajanvijayan/ai-agent-for-website/releases/tag/v1.0.0

