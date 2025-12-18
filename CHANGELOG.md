# Changelog

All notable changes to the AI Agent for Website plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/rajanvijayan/ai-agent-for-website/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/rajanvijayan/ai-agent-for-website/releases/tag/v1.0.0

