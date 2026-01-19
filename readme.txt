=== AI Agent for Website ===
Contributors: rajanvijayan
Tags: ai, chatbot, chat, groq, llama, assistant, customer support, live chat
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add an AI-powered chat agent to your WordPress website. Train it with your content using the Knowledge Base feature for instant, intelligent responses.

== Description ==

**AI Agent for Website** transforms your WordPress site with an intelligent, customizable chat widget. Powered by Groq's ultra-fast Llama models, visitors can ask questions and receive instant, accurate answers based on your website content.

= Why Choose AI Agent for Website? =

* **Lightning Fast** – Groq's inference API delivers responses in milliseconds
* **Fully Trainable** – Teach the AI using your own content via Knowledge Base
* **Privacy Focused** – Your data stays on your server; only conversations are processed
* **No Coding Required** – Easy setup with intuitive admin interface

= Key Features =

**🤖 Intelligent Chat Widget**
* Beautiful, modern design with live preview
* Customizable colors, position, and animations
* Mobile-responsive and accessible
* Sound notifications for new messages

**📚 Knowledge Base Management**
* Train AI with your website pages and custom content
* Auto-detect pillar pages using AI
* Upload documents (PDF, DOC, DOCX, TXT, CSV, MD, RTF)
* Import from Google Drive and Confluence

**👥 Lead Management**
* Capture visitor information before chat starts
* Convert conversations into qualified leads
* AI-powered lead validation and scoring
* Export leads to CRM platforms

**🔔 Notifications & Activity Logs**
* Real-time admin notifications
* Email alerts for new conversations and leads
* Comprehensive activity logging
* Export logs to Zapier or tag Mailchimp contacts

**🔗 Powerful Integrations**
* **Groq** – Ultra-fast Llama inference engine
* **Google Drive** – Import documents via OAuth
* **Confluence** – Import wiki pages and documentation
* **Zapier** – Connect to 5000+ apps
* **Mailchimp** – Automatic newsletter subscription

= How It Works =

1. **Get API Key** – Sign up for free at [console.groq.com](https://console.groq.com)
2. **Configure Settings** – Customize the widget appearance and behavior
3. **Build Knowledge Base** – Add your content so the AI can answer questions
4. **Enable Widget** – Turn on the chat and engage your visitors

= Use Cases =

* **Customer Support** – Provide 24/7 instant answers to common questions
* **Lead Generation** – Capture visitor details and qualify leads automatically
* **Documentation** – Help users navigate your knowledge base
* **Sales Assistant** – Answer product questions and guide purchasing decisions
* **Internal Help Desk** – Support employees with company policies and procedures

= Shortcode Support =

Embed the chat widget anywhere using the shortcode:

`[ai_agent_chat]`

Optional attributes: `height="500px" width="100%"`

== Installation ==

= Automatic Installation =
1. Go to Plugins → Add New in your WordPress dashboard
2. Search for "AI Agent for Website"
3. Click "Install Now" and then "Activate"

= Manual Installation =
1. Download the plugin ZIP file
2. Go to Plugins → Add New → Upload Plugin
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin

= Initial Setup =
1. Navigate to AI Agent → Settings
2. Enter your Groq API key (free from [console.groq.com](https://console.groq.com))
3. Go to AI Agent → Knowledge Base and add your content
4. Customize the widget appearance in Settings → Appearance
5. Enable the chat widget and you're ready!

== Frequently Asked Questions ==

= Where do I get an API key? =

Get your free API key from [console.groq.com](https://console.groq.com). Groq offers generous free tier limits for personal and development use.

= How do I train the AI with my content? =

Navigate to AI Agent → Knowledge Base. You can:
* Add website page URLs
* Enter custom text content
* Upload documents (PDF, DOC, etc.)
* Import from Google Drive or Confluence

= Can I customize the chat widget appearance? =

Yes! Go to AI Agent → Settings → Appearance to customize:
* AI name and avatar
* Primary color scheme
* Widget position (bottom-right or bottom-left)
* Button size and animations
* Sound notifications
* "Powered by" branding text

= How does lead capture work? =

Enable "Require Name & Email" in Settings → User Information. Visitors must provide their details before starting a chat. You can also:
* Add phone number field (optional or required)
* Show consent checkboxes for AI, newsletter, and promotional content
* View and manage leads in AI Agent → Leads

= Is the chat widget mobile-friendly? =

Absolutely! The widget is fully responsive and optimized for all screen sizes, including smartphones and tablets.

= Can I use this with page builders? =

Yes! The shortcode `[ai_agent_chat]` works with all major page builders including Elementor, Divi, Beaver Builder, and Gutenberg blocks.

= How secure is my data? =

Your Knowledge Base content stays on your WordPress server. Only conversation messages are sent to the Groq API for processing. All API communications use secure HTTPS encryption.

== Screenshots ==

1. Chat widget on frontend - beautiful, modern design
2. Admin settings page - easy configuration
3. Knowledge base management - train your AI
4. Integrations tab - connect your favorite tools
5. Leads management - track and convert visitors
6. Conversations view - review chat history

== Changelog ==

= 1.6.0 =
* NEW: Integrations UI redesign with modern card-based grid layout
* NEW: Modal settings - integration settings now open in popup modals
* NEW: AJAX save - save individual integration settings independently
* NEW: Categorized integrations (Knowledge Base, Conversation)
* IMPROVED: Admin page header with icon and better alignment
* IMPROVED: readme.txt with WordPress 6.7 compatibility

= 1.5.0 =
* NEW: Notification Center - centralized admin notifications for all events
* NEW: AI-powered conversation validation - automatically identify qualified leads
* NEW: Convert conversations to leads using AI validation
* NEW: Close invalid/not interested conversations using AI analysis
* NEW: Activity Log Center - track all system activities when enabled
* NEW: Configurable notification settings (email, events, AI validation)
* NEW: Configurable log settings (categories, retention, export)
* NEW: Zapier integration for activity log export
* NEW: Mailchimp tagging for activity-based subscriber updates
* NEW: Admin notifications with unread count badge
* NEW: Export activity logs to CSV
* NEW: Automatic log cleanup based on retention period
* DATABASE: Added notifications and activity_logs tables

= 1.4.0 =
* NEW: Leads management - convert conversations into leads
* NEW: Admin review - review, close, or convert conversations to leads
* NEW: Zapier integration - sync leads with external CRM platforms
* NEW: Mailchimp integration - automatic newsletter subscription
* NEW: Consent checkboxes (AI, Newsletter, Promotional) before chat
* NEW: Editable "Powered By" text - customize branding at widget bottom
* NEW: Widget customization - new color options for bubble, header, text, background
* NEW: Live preview for button size, animation, and sound settings
* CHANGED: Moved "AI Name" to Widget Appearance tab
* DATABASE: Added leads table and consent fields

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

= 1.5.0 =
Major update! Notification & Log Centers with AI-powered lead validation, centralized notifications, activity logging, and Zapier/Mailchimp export capabilities.

= 1.4.0 =
Leads & CRM integration! Convert conversations to leads, Zapier/Mailchimp integration, consent system, and enhanced widget customization.

= 1.3.0 =
Major knowledge base update! Upload files directly, connect Google Drive, and import from Confluence.

= 1.2.0 =
UI improvements! Side-by-side settings layout, modal-based AI suggestions, and optional "Powered By" branding.

= 1.1.0 =
Major feature update! AI-powered pillar page detection, phone field support, widget preview, and more.

== Additional Resources ==

* [Documentation](https://github.com/rajanvijayan/ai-agent-for-website/wiki)
* [Report Issues](https://github.com/rajanvijayan/ai-agent-for-website/issues)
* [Groq Console](https://console.groq.com)

== Privacy Policy ==

This plugin connects to the Groq API to process chat conversations. Only the conversation messages and knowledge base context are sent to the API. Your WordPress data and personal information remain on your server.

For more information about Groq's privacy practices, visit: https://groq.com/privacy-policy/
