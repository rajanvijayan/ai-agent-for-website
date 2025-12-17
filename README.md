# AI Agent for Website

A WordPress plugin that adds an AI-powered chat agent to your website. Train it with your website content using the Knowledge Base feature.

## Features

- 🤖 **AI-powered chat widget** - Beautiful floating chat bubble
- 📚 **Knowledge Base (RAG)** - Train AI with your website content
- ⚡ **Ultra-fast responses** - Powered by Groq API
- 🎨 **Customizable** - Colors, position, AI name, persona
- 💬 **Conversation memory** - Remembers context during chat
- 📱 **Mobile responsive** - Works on all devices
- 🔧 **Easy configuration** - WordPress admin panel

## Installation

### Step 1: Install Dependencies

Navigate to the plugin directory and run:

```bash
cd wp-content/plugins/ai-agent-for-website
composer install
```

This will install the [AI Engine library](https://github.com/rajanvijayan/ai-engine) from GitHub.

### Step 2: Activate Plugin

Go to **WordPress Admin → Plugins** and activate "AI Agent for Website".

### Step 3: Configure

1. Go to **AI Agent → Settings**
2. Enter your **Groq API key** (get free at [console.groq.com](https://console.groq.com))
3. Customize AI name, welcome message, and appearance
4. Enable the chat widget

### Step 4: Add Knowledge (Optional)

1. Go to **AI Agent → Knowledge Base**
2. Add your website pages or custom text
3. The AI will use this content to answer questions

## Usage

### Floating Widget

Once enabled in settings, a chat bubble appears on all pages.

### Shortcode

Embed the chat anywhere:

```
[ai_agent_chat]
```

With custom dimensions:

```
[ai_agent_chat height="600px" width="100%"]
```

## Configuration Options

| Setting | Description |
|---------|-------------|
| API Key | Your Groq API key |
| AI Name | Name shown in chat header |
| Welcome Message | First message when chat opens |
| System Instruction | AI personality/behavior instructions |
| Widget Position | Bottom-right or bottom-left |
| Primary Color | Theme color for the widget |

## Tech Stack

- **AI Engine**: [rajanvijayan/ai-engine](https://github.com/rajanvijayan/ai-engine)
- **AI Provider**: Groq (Llama models)
- **Frontend**: Vanilla JavaScript (no jQuery in widget)
- **API**: WordPress REST API

## File Structure

```
ai-agent-for-website/
├── ai-agent-for-website.php    # Main plugin file
├── composer.json               # Dependencies
├── readme.txt                  # WordPress readme
├── assets/
│   ├── css/
│   │   ├── admin.css           # Admin styles
│   │   └── chat-widget.css     # Frontend widget styles
│   └── js/
│       ├── admin.js            # Admin JavaScript
│       └── chat-widget.js      # Frontend widget JavaScript
├── includes/
│   ├── class-admin-settings.php    # Settings page
│   ├── class-chat-widget.php       # Widget renderer
│   ├── class-knowledge-manager.php # Knowledge base admin
│   └── class-rest-api.php          # REST API endpoints
└── vendor/                     # Composer dependencies
```

## REST API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/ai-agent/v1/chat` | POST | Send chat message |
| `/ai-agent/v1/new-conversation` | POST | Start new conversation |
| `/ai-agent/v1/test` | POST | Test API connection (admin only) |
| `/ai-agent/v1/fetch-url` | POST | Fetch URL for knowledge base (admin only) |

## Requirements

- WordPress 5.8+
- PHP 8.0+
- Composer

## License

GPL v2 or later

## Credits

- [AI Engine](https://github.com/rajanvijayan/ai-engine) - Multi-provider AI library
- [Groq](https://console.groq.com) - Fast AI inference

