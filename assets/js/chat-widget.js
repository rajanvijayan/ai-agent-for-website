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
            messageEl.textContent = text;
            container.appendChild(messageEl);
            
            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
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

