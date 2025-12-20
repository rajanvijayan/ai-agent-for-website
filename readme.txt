=== AI Agent for Website ===
Contributors: rajanvijayan
Tags: ai, chatbot, chat, groq, llama, assistant
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add an AI-powered chat agent to your WordPress website. Train it with your website content using the Knowledge Base feature.

== Description ==

AI Agent for Website adds a beautiful, intelligent chat widget to your WordPress site. Powered by Groq's fast Llama models, your visitors can ask questions and get instant answers based on your website content.

**Features:**

* 🤖 AI-powered chat widget with live preview
* 📚 Knowledge Base - Train AI with your website content
* 📁 File Upload - Import PDF, DOC, DOCX, TXT, CSV, MD, RTF files
* ☁️ Google Drive Integration - Import documents directly
* 📝 Confluence Integration - Import wiki pages
* 🔍 Auto-detect pillar pages using AI
* ⚡ Ultra-fast responses using Groq API
* 🎨 Customizable appearance with Lucide icons
* 💬 Conversation memory with user info collection
* 📱 Mobile responsive
* 🔧 Easy configuration with AI suggestions
* 📞 Optional phone number collection

**How It Works:**

1. Get a free API key from Groq (console.groq.com)
2. Add your website pages to the Knowledge Base
3. Enable the chat widget
4. Your visitors can now ask questions about your website!

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to AI Agent > Settings to configure
4. Enter your Groq API key
5. Add content to Knowledge Base
6. Enable the widget

== Frequently Asked Questions ==

= Where do I get an API key? =

Get your free API key from [console.groq.com](https://console.groq.com)

= How do I train the AI with my content? =

Go to AI Agent > Knowledge Base and add your website URLs. The AI will use this content to answer questions.

= Can I customize the chat widget? =

Yes! You can change the AI name, welcome message, position, and colors in the Settings page.

= Is there a shortcode? =

Yes, use `[ai_agent_chat]` to embed the chat in any page or post.

== Screenshots ==

1. Chat widget on frontend
2. Admin settings page
3. Knowledge base management

== Changelog ==

= 1.3.0 =
* NEW: Custom file upload - upload PDF, DOC, DOCX, TXT, CSV, MD, RTF to knowledge base
* NEW: Google Drive integration - connect and import documents via OAuth 2.0
* NEW: Confluence integration - connect and import wiki pages
* NEW: Drag & drop file upload with progress indicators
* NEW: Integration file/page browsers for easy import
* IMPROVED: Reorganized Knowledge Base page with 3-column grid layout
* IMPROVED: Integrations tab now has actual connection settings
* SECURITY: MIME type validation, file size limits, secure file storage

= 1.2.0 =
* NEW: Configurable "Powered By" text - option to show/hide footer branding
* NEW: AI suggestions now display in a modal with regenerate option
* IMPROVED: Settings page layout - Widget Appearance and Preview side-by-side
* IMPROVED: Responsive design for smaller screens

= 1.1.0 =
* NEW: Phone number field option before starting chat
* NEW: AI-powered "Auto Detect Pillar Pages" feature in Knowledge Base
* NEW: AI suggestion buttons for Welcome Message and System Instruction
* NEW: Live chat widget preview in Settings page
* IMPROVED: Enhanced "Add Custom Text" form with categories and character count
* IMPROVED: Updated icons to Lucide-style SVG icons (open-source)
* IMPROVED: Database schema updated with phone number support

= 1.0.2 =
* Added GitHub-based automatic update system
* Plugin now checks for updates from GitHub releases
* One-click updates directly from WordPress dashboard
* Auto-release workflow for automated builds

= 1.0.1 =
* Fixed all PHPCS (WordPress Coding Standards) errors
* Improved output escaping for better security
* Added proper documentation comments to all functions
* Code quality improvements and standards compliance

= 1.0.0 =
* Initial release
* Chat widget with Groq API
* Knowledge Base for custom content
* Admin settings panel
* Shortcode support

== Upgrade Notice ==

= 1.3.0 =
Major knowledge base update! Upload files directly, connect Google Drive, and import from Confluence.

= 1.2.0 =
UI improvements! Side-by-side settings layout, modal-based AI suggestions, and optional "Powered By" branding.

= 1.1.0 =
Major feature update! AI-powered pillar page detection, phone field support, widget preview, and more.

= 1.0.2 =
Added automatic update system - plugin now updates directly from GitHub releases!

= 1.0.1 =
Code quality improvements and WordPress Coding Standards compliance.

= 1.0.0 =
Initial release of AI Agent for Website.

