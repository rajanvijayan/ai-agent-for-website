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
            this.userId = this.getUserId();
            this.userName = this.getUserName();
            this.isTyping = false;
            this.hasMessages = false;

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
                if (this.widget.classList.contains('open')) {
                    // Closing - check if we should show rating
                    if (this.hasMessages) {
                        this.showRating(this.widget);
                    } else {
                        this.widget.classList.remove('open');
                    }
                } else {
                    // Opening
                    this.widget.classList.add('open');
                    this.checkUserInfo(this.widget, messagesContainer);
                }
            });

            this.initChat(this.widget);
        }

        checkUserInfo(container, messagesContainer) {
            // If user info is required and we don't have it, show the form
            if (aiagentConfig.requireUserInfo && !this.userId) {
                container.classList.add('show-user-form');
            } else if (messagesContainer.children.length === 0) {
                // Add welcome message if we have user info or don't need it
                const welcomeMsg = this.userName 
                    ? `Hi ${this.userName}! ${aiagentConfig.welcomeMessage}`
                    : aiagentConfig.welcomeMessage;
                this.addMessage(messagesContainer, welcomeMsg, 'ai');
            }
        }

        initChat(container) {
            const form = container.querySelector('.aiagent-form');
            const input = container.querySelector('.aiagent-input');
            const messagesContainer = container.querySelector('.aiagent-messages');
            const newChatBtn = container.querySelector('.aiagent-new-chat');
            const closeChatBtn = container.querySelector('.aiagent-close-chat');
            const userInfoForm = container.querySelector('.aiagent-user-info-form');
            const ratingModal = container.querySelector('.aiagent-rating-modal');

            if (!form || !input || !messagesContainer) return;

            // Handle user info form submit
            if (userInfoForm) {
                userInfoForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleUserInfoSubmit(container, userInfoForm, messagesContainer);
                });
            }

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
                    this.startNewConversation(container, messagesContainer);
                });
            }

            // Handle close chat
            if (closeChatBtn) {
                closeChatBtn.addEventListener('click', () => {
                    if (this.hasMessages) {
                        this.showRating(container);
                    } else {
                        this.closeChat(container);
                    }
                });
            }

            // Handle rating
            if (ratingModal) {
                this.initRating(container, ratingModal);
            }

            // Add welcome message for inline chats
            if (container.classList.contains('aiagent-inline-chat')) {
                this.checkUserInfo(container, messagesContainer);
            }
        }

        initRating(container, ratingModal) {
            const stars = ratingModal.querySelectorAll('.aiagent-star');
            const skipBtn = ratingModal.querySelector('.aiagent-skip-rating');
            let selectedRating = 0;

            // Star hover effect
            stars.forEach((star, index) => {
                star.addEventListener('mouseenter', () => {
                    stars.forEach((s, i) => {
                        s.classList.toggle('active', i <= index);
                    });
                });

                star.addEventListener('mouseleave', () => {
                    stars.forEach((s, i) => {
                        s.classList.remove('active');
                        s.classList.toggle('selected', i < selectedRating);
                    });
                });

                star.addEventListener('click', () => {
                    selectedRating = index + 1;
                    stars.forEach((s, i) => {
                        s.classList.toggle('selected', i < selectedRating);
                    });
                    // Submit rating after a brief delay
                    setTimeout(() => {
                        this.submitRating(container, selectedRating);
                    }, 300);
                });
            });

            // Skip rating
            if (skipBtn) {
                skipBtn.addEventListener('click', () => {
                    this.closeChat(container);
                });
            }
        }

        showRating(container) {
            container.classList.add('show-rating');
        }

        async submitRating(container, rating) {
            try {
                await fetch(aiagentConfig.restUrl + 'rate-conversation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        rating: rating
                    })
                });
            } catch (error) {
                console.error('AI Agent Error:', error);
            }

            this.closeChat(container);
        }

        closeChat(container) {
            container.classList.remove('show-rating');
            
            if (container.id === 'aiagent-chat-widget') {
                container.classList.remove('open');
            }
            
            // Reset for next conversation
            this.hasMessages = false;
            
            // Reset rating stars
            const stars = container.querySelectorAll('.aiagent-star');
            stars.forEach(s => s.classList.remove('selected', 'active'));
        }

        async handleUserInfoSubmit(container, form, messagesContainer) {
            const nameInput = form.querySelector('input[name="user_name"]');
            const emailInput = form.querySelector('input[name="user_email"]');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            const name = nameInput.value.trim();
            const email = emailInput.value.trim();

            if (!name || !email) return;

            // Disable form
            submitBtn.disabled = true;
            submitBtn.textContent = 'Starting...';

            try {
                const response = await fetch(aiagentConfig.restUrl + 'register-user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce
                    },
                    body: JSON.stringify({
                        name: name,
                        email: email,
                        session_id: this.sessionId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Save user info
                    this.userId = data.user_id;
                    this.userName = name;
                    this.saveUserInfo(data.user_id, name, email);
                    
                    if (data.session_id) {
                        this.sessionId = data.session_id;
                        this.saveSessionId();
                    }

                    // Hide form, show chat
                    container.classList.remove('show-user-form');
                    
                    // Add personalized welcome message
                    const welcomeMsg = `Hi ${name}! ${aiagentConfig.welcomeMessage}`;
                    this.addMessage(messagesContainer, welcomeMsg, 'ai');
                } else {
                    alert(data.message || 'Something went wrong. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Start Chat <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>';
                }
            } catch (error) {
                console.error('AI Agent Error:', error);
                alert('Unable to connect. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Start Chat <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>';
            }
        }

        async sendMessage(messagesContainer, input, message) {
            // Add user message
            this.addMessage(messagesContainer, message, 'user');
            this.hasMessages = true;
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
                        session_id: this.sessionId,
                        user_id: this.userId
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

        async startNewConversation(container, messagesContainer) {
            // Clear messages
            messagesContainer.innerHTML = '';
            this.hasMessages = false;
            
            // Request new session
            try {
                const response = await fetch(aiagentConfig.restUrl + 'new-conversation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        user_id: this.userId
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

            // Check if we need user info again or just show welcome
            if (aiagentConfig.requireUserInfo && !this.userId) {
                container.classList.add('show-user-form');
            } else {
                const welcomeMsg = this.userName 
                    ? `Hi ${this.userName}! ${aiagentConfig.welcomeMessage}`
                    : aiagentConfig.welcomeMessage;
                this.addMessage(messagesContainer, welcomeMsg, 'ai');
            }
        }

        // Session management
        getSessionId() {
            try {
                return localStorage.getItem('aiagent_session') || this.generateSessionId();
            } catch (e) {
                return this.generateSessionId();
            }
        }

        saveSessionId() {
            try {
                localStorage.setItem('aiagent_session', this.sessionId);
            } catch (e) {
                // Storage not available
            }
        }

        generateSessionId() {
            const id = 'session_' + Math.random().toString(36).substr(2, 16);
            this.saveSessionId();
            return id;
        }

        // User info management
        getUserId() {
            try {
                return localStorage.getItem('aiagent_user_id') || null;
            } catch (e) {
                return null;
            }
        }

        getUserName() {
            try {
                return localStorage.getItem('aiagent_user_name') || null;
            } catch (e) {
                return null;
            }
        }

        saveUserInfo(userId, name, email) {
            try {
                localStorage.setItem('aiagent_user_id', userId);
                localStorage.setItem('aiagent_user_name', name);
                localStorage.setItem('aiagent_user_email', email);
            } catch (e) {
                // Storage not available
            }
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new AIAgentChat());
    } else {
        new AIAgentChat();
    }

})();
