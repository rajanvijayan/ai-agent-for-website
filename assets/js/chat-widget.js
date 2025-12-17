/**
 * AI Agent Chat Widget JavaScript
 */

(function() {
    'use strict';

    class AIAgentChat {
        constructor() {
            this.widget = document.getElementById('aiagent-chat-widget');
            this.inlineChats = document.querySelectorAll('.aiagent-inline-chat');
            this.sessionId = this.getSessionId();
            this.isTyping = false;

            if (this.widget) {
                this.initFloatingWidget();
            }

            this.inlineChats.forEach(chat => this.initChat(chat));
        }

        initFloatingWidget() {
            const toggle = this.widget.querySelector('.aiagent-toggle');
            const messagesContainer = this.widget.querySelector('.aiagent-messages');
            
            // Toggle open/close
            toggle.addEventListener('click', () => {
                this.widget.classList.toggle('open');
                
                // Add welcome message on first open
                if (this.widget.classList.contains('open') && messagesContainer.children.length === 0) {
                    this.addMessage(messagesContainer, aiagentConfig.welcomeMessage, 'ai');
                }
            });

            this.initChat(this.widget);
        }

        initChat(container) {
            const form = container.querySelector('.aiagent-form');
            const input = container.querySelector('.aiagent-input');
            const messagesContainer = container.querySelector('.aiagent-messages');
            const newChatBtn = container.querySelector('.aiagent-new-chat');

            if (!form || !input || !messagesContainer) return;

            // Handle form submit
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const message = input.value.trim();
                
                if (message && !this.isTyping) {
                    this.sendMessage(messagesContainer, input, message);
                }
            });

            // Handle new chat
            if (newChatBtn) {
                newChatBtn.addEventListener('click', () => {
                    this.startNewConversation(messagesContainer);
                });
            }

            // Add welcome message for inline chats
            if (container.classList.contains('aiagent-inline-chat') && messagesContainer.children.length === 0) {
                this.addMessage(messagesContainer, aiagentConfig.welcomeMessage, 'ai');
            }
        }

        async sendMessage(messagesContainer, input, message) {
            // Add user message
            this.addMessage(messagesContainer, message, 'user');
            input.value = '';
            input.disabled = true;

            // Show typing indicator
            const typingEl = this.showTyping(messagesContainer);
            this.isTyping = true;

            try {
                const response = await fetch(aiagentConfig.restUrl + 'chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: this.sessionId
                    })
                });

                const data = await response.json();

                // Remove typing indicator
                this.hideTyping(typingEl);

                if (data.success) {
                    this.addMessage(messagesContainer, data.message, 'ai');
                    if (data.session_id) {
                        this.sessionId = data.session_id;
                        this.saveSessionId();
                    }
                } else {
                    const errorMsg = data.message || 'Sorry, something went wrong.';
                    this.addMessage(messagesContainer, errorMsg, 'error');
                }
            } catch (error) {
                this.hideTyping(typingEl);
                this.addMessage(messagesContainer, 'Unable to connect. Please try again.', 'error');
                console.error('AI Agent Error:', error);
            }

            input.disabled = false;
            input.focus();
            this.isTyping = false;
        }

        addMessage(container, text, type) {
            const messageEl = document.createElement('div');
            messageEl.className = `aiagent-message aiagent-message-${type}`;
            
            // For AI messages, render markdown/HTML; for user messages, use plain text
            if (type === 'ai' || type === 'error') {
                messageEl.innerHTML = this.formatMessage(text);
            } else {
                messageEl.textContent = text;
            }
            
            container.appendChild(messageEl);
            
            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        formatMessage(text) {
            // Escape HTML first to prevent XSS
            let formatted = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            
            // Convert markdown to HTML
            // Code blocks (```)
            formatted = formatted.replace(/```(\w*)\n?([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
            
            // Inline code (`)
            formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');
            
            // Bold (**text** or __text__)
            formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            formatted = formatted.replace(/__([^_]+)__/g, '<strong>$1</strong>');
            
            // Italic (*text* or _text_)
            formatted = formatted.replace(/\*([^*]+)\*/g, '<em>$1</em>');
            formatted = formatted.replace(/_([^_]+)_/g, '<em>$1</em>');
            
            // Links [text](url)
            formatted = formatted.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
            
            // Unordered lists (- item or * item)
            formatted = formatted.replace(/^[\-\*]\s+(.+)$/gm, '<li>$1</li>');
            formatted = formatted.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
            
            // Ordered lists (1. item)
            formatted = formatted.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');
            
            // Wrap consecutive <li> tags in <ul> or <ol>
            formatted = formatted.replace(/(<li>[\s\S]*?<\/li>)+/g, (match) => {
                return '<ul>' + match + '</ul>';
            });
            
            // Clean up duplicate ul tags
            formatted = formatted.replace(/<ul><ul>/g, '<ul>');
            formatted = formatted.replace(/<\/ul><\/ul>/g, '</ul>');
            
            // Headers (## text)
            formatted = formatted.replace(/^###\s+(.+)$/gm, '<h4>$1</h4>');
            formatted = formatted.replace(/^##\s+(.+)$/gm, '<h3>$1</h3>');
            formatted = formatted.replace(/^#\s+(.+)$/gm, '<h3>$1</h3>');
            
            // Line breaks - convert double newlines to paragraphs
            formatted = formatted.replace(/\n\n+/g, '</p><p>');
            formatted = formatted.replace(/\n/g, '<br>');
            
            // Wrap in paragraph if not already wrapped
            if (!formatted.startsWith('<')) {
                formatted = '<p>' + formatted + '</p>';
            }
            
            // Clean up empty paragraphs
            formatted = formatted.replace(/<p><\/p>/g, '');
            formatted = formatted.replace(/<p>(<[huo])/g, '$1');
            formatted = formatted.replace(/(<\/[huo]l>|<\/h[34]>)<\/p>/g, '$1');
            
            return formatted;
        }

        showTyping(container) {
            const typingEl = document.createElement('div');
            typingEl.className = 'aiagent-message aiagent-message-ai aiagent-message-typing';
            typingEl.innerHTML = '<span></span><span></span><span></span>';
            container.appendChild(typingEl);
            container.scrollTop = container.scrollHeight;
            return typingEl;
        }

        hideTyping(typingEl) {
            if (typingEl && typingEl.parentNode) {
                typingEl.parentNode.removeChild(typingEl);
            }
        }

        async startNewConversation(messagesContainer) {
            // Clear messages
            messagesContainer.innerHTML = '';
            
            // Request new session
            try {
                const response = await fetch(aiagentConfig.restUrl + 'new-conversation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId
                    })
                });

                const data = await response.json();
                if (data.session_id) {
                    this.sessionId = data.session_id;
                    this.saveSessionId();
                }
            } catch (error) {
                console.error('AI Agent Error:', error);
            }

            // Add welcome message
            this.addMessage(messagesContainer, aiagentConfig.welcomeMessage, 'ai');
        }

        getSessionId() {
            try {
                return sessionStorage.getItem('aiagent_session') || this.generateSessionId();
            } catch (e) {
                return this.generateSessionId();
            }
        }

        saveSessionId() {
            try {
                sessionStorage.setItem('aiagent_session', this.sessionId);
            } catch (e) {
                // Session storage not available
            }
        }

        generateSessionId() {
            const id = 'session_' + Math.random().toString(36).substr(2, 16);
            this.saveSessionId();
            return id;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new AIAgentChat());
    } else {
        new AIAgentChat();
    }

})();

