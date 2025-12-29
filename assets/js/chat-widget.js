/**
 * AI Agent Chat Widget JavaScript
 */

(function () {
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
            this.soundEnabled = aiagentConfig.widgetSound || false;
            this.audioContext = null;
            this.compareProducts = [];
            this.wooEnabled = aiagentConfig.wooEnabled || false;

            // Calendar booking state (Google Calendar)
            this.gcalendarEnabled = aiagentConfig.gcalendarSettings?.enabled || false;
            this.gcalendarPromptAfterChat =
                aiagentConfig.gcalendarSettings?.prompt_after_chat || false;
            this.gcalendarPromptMessage =
                aiagentConfig.gcalendarSettings?.prompt_message ||
                'Would you like to schedule a meeting or follow-up?';
            this.calendarSlots = [];
            this.currentDateIndex = 0;
            this.selectedSlot = null;

            // Calendly state
            this.calendlyEnabled = aiagentConfig.calendlySettings?.enabled || false;
            this.calendlyPromptAfterChat =
                aiagentConfig.calendlySettings?.prompt_after_chat || false;
            this.calendlyPromptMessage =
                aiagentConfig.calendlySettings?.prompt_message ||
                'Would you like to schedule a call with us?';
            this.calendlySettings = aiagentConfig.calendlySettings || {};

            // Combined calendar enabled (either one)
            this.calendarEnabled = this.gcalendarEnabled || this.calendlyEnabled;
            this.calendarPromptAfterChat =
                (this.gcalendarEnabled && this.gcalendarPromptAfterChat) ||
                (this.calendlyEnabled && this.calendlyPromptAfterChat);

            // Live Agent state
            this.liveAgentEnabled = aiagentConfig.liveAgentSettings?.enabled || false;
            this.isAgentAvailable = aiagentConfig.liveAgentSettings?.agent_available || false;
            this.liveAgentSettings = aiagentConfig.liveAgentSettings || {};
            this.liveAgentConnectButtonText = aiagentConfig.liveAgentSettings?.connect_button_text || 'Connect to Live Agent';
            this.liveAgentNoAgentMessage = aiagentConfig.liveAgentSettings?.offline_message || 'No agents are currently available.';
            this.isLiveAgentMode = false;
            this.liveSessionId = null;
            this.liveAgentPollInterval = null;
            this.lastLiveMessageId = 0;
            this.messageCount = 0;
            this.negativeResponseCount = 0;

            // Debug: Log current state
            console.log('AI Agent Config:', {
                requireUserInfo: aiagentConfig.requireUserInfo,
                userId: this.userId,
                userName: this.userName,
                sessionId: this.sessionId,
                soundEnabled: this.soundEnabled,
                wooEnabled: this.wooEnabled,
                gcalendarEnabled: this.gcalendarEnabled,
                calendlyEnabled: this.calendlyEnabled,
                liveAgentEnabled: this.liveAgentEnabled,
                isAgentAvailable: this.isAgentAvailable,
            });

            if (this.widget) {
                this.initFloatingWidget();
            }

            this.inlineChats.forEach((chat) => this.initChat(chat));
        }

        // Play notification sound using Web Audio API
        playNotificationSound() {
            if (!this.soundEnabled) return;

            try {
                // Create audio context on first use (browsers require user interaction)
                if (!this.audioContext) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }

                const ctx = this.audioContext;

                // Resume if suspended (browser autoplay policy)
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }

                // Create a pleasant notification sound
                const oscillator = ctx.createOscillator();
                const gainNode = ctx.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(ctx.destination);

                // Use a pleasant frequency (C5 note = 523.25 Hz)
                oscillator.frequency.setValueAtTime(523.25, ctx.currentTime);
                oscillator.type = 'sine';

                // Envelope: quick fade in, hold, fade out
                gainNode.gain.setValueAtTime(0, ctx.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.05);
                gainNode.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.1);
                gainNode.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.3);

                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + 0.3);

                // Play second note for a pleasant chime
                setTimeout(() => {
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();

                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);

                    // E5 note = 659.25 Hz
                    osc2.frequency.setValueAtTime(659.25, ctx.currentTime);
                    osc2.type = 'sine';

                    gain2.gain.setValueAtTime(0, ctx.currentTime);
                    gain2.gain.linearRampToValueAtTime(0.25, ctx.currentTime + 0.05);
                    gain2.gain.linearRampToValueAtTime(0.25, ctx.currentTime + 0.1);
                    gain2.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.35);

                    osc2.start(ctx.currentTime);
                    osc2.stop(ctx.currentTime + 0.35);
                }, 150);
            } catch (e) {
                console.log('Sound not available:', e);
            }
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
            console.log('checkUserInfo:', {
                requireUserInfo: aiagentConfig.requireUserInfo,
                userId: this.userId,
                showForm: aiagentConfig.requireUserInfo && !this.userId,
            });

            // If user info is required and we don't have it, show the form
            if (aiagentConfig.requireUserInfo && !this.userId) {
                console.log('Showing user form');
                container.classList.add('show-user-form');
            } else if (messagesContainer.children.length === 0) {
                // Add welcome message if we have user info or don't need it
                const welcomeMsg = this.getPersonalizedWelcome();
                this.addMessage(messagesContainer, welcomeMsg, 'ai');
            }
        }

        getPersonalizedWelcome() {
            const welcome = aiagentConfig.welcomeMessage;

            if (!this.userName) {
                return welcome;
            }

            // Check if welcome message already contains a greeting pattern
            const greetingPatterns =
                /^(hi|hello|hey|welcome|greetings|good\s*(morning|afternoon|evening))[,!.\s]*/i;

            if (greetingPatterns.test(welcome)) {
                // Replace the greeting with personalized version
                return welcome.replace(greetingPatterns, `Hi ${this.userName}! `);
            } else {
                // Prepend personalized greeting
                return `Hi ${this.userName}! ${welcome}`;
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
            const calendarModal = container.querySelector('.aiagent-calendar-modal');

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

            // Handle calendar booking
            if (calendarModal) {
                this.initCalendar(container, calendarModal);
            }

            // Initialize live agent functionality
            if (this.liveAgentEnabled) {
                this.initLiveAgent(container);
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
                    // Hide rating
                    container.classList.remove('show-rating');

                    // Show calendar prompt if enabled
                    if (this.calendarEnabled && this.calendarPromptAfterChat) {
                        this.showCalendarPrompt(container);
                    } else {
                        this.closeChat(container);
                    }
                });
            }
        }

        showRating(container) {
            container.classList.add('show-rating');
        }

        showCalendarPrompt(container) {
            // Only show if calendar is enabled and user has messages
            if (!this.calendarEnabled || !this.calendarPromptAfterChat || !this.hasMessages) {
                return;
            }

            // Determine which calendar to use (prefer Calendly if both enabled as it's simpler)
            let promptMessage = '';
            if (this.calendlyEnabled && this.calendlyPromptAfterChat) {
                promptMessage = this.calendlyPromptMessage;
                container.dataset.calendarType = 'calendly';
            } else if (this.gcalendarEnabled && this.gcalendarPromptAfterChat) {
                promptMessage = this.gcalendarPromptMessage;
                container.dataset.calendarType = 'gcalendar';
            }

            // Set the prompt text
            const promptText = container.querySelector('.aiagent-calendar-prompt-text');
            if (promptText) {
                promptText.textContent = promptMessage;
            }

            container.classList.add('show-calendar');
        }

        hideCalendarPrompt(container) {
            container.classList.remove('show-calendar');
            // Reset to first step
            const steps = container.querySelectorAll('.aiagent-calendar-step');
            steps.forEach((step) => {
                step.style.display = step.dataset.step === 'prompt' ? 'block' : 'none';
            });
            // Reset state
            this.currentDateIndex = 0;
            this.selectedSlot = null;
        }

        async submitRating(container, rating) {
            try {
                await fetch(aiagentConfig.restUrl + 'rate-conversation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        rating: rating,
                    }),
                });
            } catch (error) {
                console.error('AI Agent Error:', error);
            }

            // Hide rating
            container.classList.remove('show-rating');

            // Show calendar prompt if enabled
            if (this.calendarEnabled && this.calendarPromptAfterChat) {
                this.showCalendarPrompt(container);
            } else {
                this.closeChat(container, true);
            }
        }

        closeChat(container, endConversation = false) {
            container.classList.remove('show-rating');
            container.classList.remove('show-calendar');

            if (container.id === 'aiagent-chat-widget') {
                container.classList.remove('open');
            }

            // Reset for next conversation
            this.hasMessages = false;

            // Reset rating stars
            const stars = container.querySelectorAll('.aiagent-star');
            stars.forEach((s) => s.classList.remove('selected', 'active'));

            // Reset calendar state
            this.hideCalendarPrompt(container);

            // If ending conversation, clear messages and generate new session
            if (endConversation) {
                const messagesContainer = container.querySelector('.aiagent-messages');
                if (messagesContainer) {
                    messagesContainer.innerHTML = '';
                }
                // Generate new session ID for next conversation
                this.sessionId = this.generateSessionId();
            }
        }

        async handleUserInfoSubmit(container, form, messagesContainer) {
            const nameInput = form.querySelector('input[name="user_name"]');
            const emailInput = form.querySelector('input[name="user_email"]');
            const phoneInput = form.querySelector('input[name="user_phone"]');
            const consentAiInput = form.querySelector('input[name="consent_ai"]');
            const consentNewsletterInput = form.querySelector('input[name="consent_newsletter"]');
            const consentPromotionalInput = form.querySelector('input[name="consent_promotional"]');
            const submitBtn = form.querySelector('button[type="submit"]');

            const name = nameInput.value.trim();
            const email = emailInput.value.trim();
            const phone = phoneInput ? phoneInput.value.trim() : '';

            if (!name || !email) return;

            // Check if phone is required
            if (phoneInput && phoneInput.required && !phone) return;

            // Check if AI consent is required but not checked
            if (consentAiInput && consentAiInput.required && !consentAiInput.checked) return;

            // Disable form
            submitBtn.disabled = true;
            submitBtn.textContent = 'Starting...';

            try {
                const response = await fetch(aiagentConfig.restUrl + 'register-user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        name: name,
                        email: email,
                        phone: phone,
                        session_id: this.sessionId,
                        consent_ai: consentAiInput ? consentAiInput.checked : false,
                        consent_newsletter: consentNewsletterInput
                            ? consentNewsletterInput.checked
                            : false,
                        consent_promotional: consentPromotionalInput
                            ? consentPromotionalInput.checked
                            : false,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    // Save user info
                    this.userId = data.user_id;
                    this.userName = name;
                    this.saveUserInfo(data.user_id, name, email, phone);

                    if (data.session_id) {
                        this.sessionId = data.session_id;
                        this.saveSessionId();
                    }

                    // Hide form, show chat
                    container.classList.remove('show-user-form');

                    // Add personalized welcome message
                    const welcomeMsg = this.getPersonalizedWelcome();
                    this.addMessage(messagesContainer, welcomeMsg, 'ai');
                } else {
                    alert(data.message || 'Something went wrong. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML =
                        'Start Chat <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>';
                }
            } catch (error) {
                console.error('AI Agent Error:', error);
                alert('Unable to connect. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML =
                    'Start Chat <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>';
            }
        }

        async sendMessage(messagesContainer, input, message) {
            // Add user message
            this.addMessage(messagesContainer, message, 'user');
            this.hasMessages = true;
            input.value = '';
            input.disabled = true;

            // If in live agent mode, send to live agent instead of AI
            if (this.isLiveAgentMode && this.liveSessionId) {
                try {
                    await fetch(`${aiagentConfig.restUrl}live-agent/message`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': aiagentConfig.nonce,
                        },
                        body: JSON.stringify({
                            live_session_id: this.liveSessionId,
                            message: message,
                            sender_type: 'user',
                            sender_id: this.userId,
                        }),
                    });
                    // Message sent - agent will see it via their polling
                    // No need for typing indicator as agent is human
                } catch (error) {
                    console.error('Error sending live agent message:', error);
                    this.addMessage(
                        messagesContainer,
                        'Failed to send message. Please try again.',
                        'system'
                    );
                }
                input.disabled = false;
                return;
            }

            // Show typing indicator
            const typingEl = this.showTyping(messagesContainer);
            this.isTyping = true;

            try {
                // Check if this looks like a product search query
                const isProductQuery = this.wooEnabled && this.isProductSearchQuery(message);

                const response = await fetch(aiagentConfig.restUrl + 'chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: this.sessionId,
                        user_id: this.userId,
                    }),
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

                    // Track message count and check for live agent conditions
                    this.messageCount++;
                    if (this.liveAgentEnabled && this.isNegativeResponse(data.message)) {
                        this.negativeResponseCount++;
                    }
                    this.checkLiveAgentTrigger(messagesContainer.closest('.aiagent-widget, .aiagent-inline-chat'));

                    // If it was a product query, search and display products
                    if (isProductQuery) {
                        // Extract the actual product search term from the message
                        const searchTerm = this.extractProductSearchTerms(message);

                        // Try to search with the extracted term first
                        let productData = null;
                        if (searchTerm && searchTerm.length > 1) {
                            productData = await this.searchProducts(searchTerm, messagesContainer);
                        }

                        // If no products found with extracted term, try the full message
                        if (
                            !productData ||
                            !productData.products ||
                            productData.products.length === 0
                        ) {
                            productData = await this.searchProducts(message, messagesContainer);
                        }

                        // If still no products found, try to get featured/all products
                        if (
                            !productData ||
                            !productData.products ||
                            productData.products.length === 0
                        ) {
                            productData = await this.getFeaturedProducts();
                        }

                        if (
                            productData &&
                            productData.products &&
                            productData.products.length > 0
                        ) {
                            const introMessage =
                                searchTerm && searchTerm.length > 1
                                    ? `Here are products matching "${searchTerm}":`
                                    : 'Here are some products you might be interested in:';
                            this.addMessage(messagesContainer, introMessage, 'ai');
                            this.renderProducts(productData.products, messagesContainer, {
                                showCompareButton:
                                    aiagentConfig.wooShowComparison &&
                                    productData.products.length >= 2,
                            });

                            // Show related products if available
                            if (
                                productData.related &&
                                productData.related.length > 0 &&
                                aiagentConfig.wooShowRelated
                            ) {
                                this.addMessage(
                                    messagesContainer,
                                    'You might also like these related products:',
                                    'ai'
                                );
                                this.renderProducts(productData.related, messagesContainer, {
                                    showCompareButton: false,
                                });
                            }
                        }
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

        isProductSearchQuery(message) {
            const productKeywords = [
                'product',
                'products',
                'buy',
                'purchase',
                'shop',
                'shopping',
                'price',
                'cost',
                'how much',
                'looking for',
                'search for',
                'find',
                'show me',
                'recommend',
                'suggestion',
                'suggestions',
                'compare',
                'comparison',
                'best',
                'top',
                'popular',
                'sale',
                'discount',
                'offer',
                'deals',
                'cheap',
                'affordable',
                'available',
                'in stock',
                'order',
                'cart',
                'checkout',
                'i need',
                'i want',
                'looking to buy',
                'interested in',
                'do you have',
                'do you sell',
                'can i get',
                'where can i find',
                'any',
            ];

            const lowerMessage = message.toLowerCase();
            return productKeywords.some((keyword) => lowerMessage.includes(keyword));
        }

        // Extract potential product search terms from user message
        extractProductSearchTerms(message) {
            // Remove common question words and phrases to get the product name
            const removeWords = [
                'i need',
                'i want',
                'looking for',
                'search for',
                'find me',
                'show me',
                'can i get',
                'do you have',
                'do you sell',
                'where can i find',
                'interested in',
                'looking to buy',
                'i am looking for',
                "i'm looking for",
                'any',
                'some',
                'the',
                'a',
                'an',
                'please',
                'products',
                'product',
                'items',
                'item',
            ];

            let searchTerm = message.toLowerCase().trim();

            // Remove question marks and other punctuation
            searchTerm = searchTerm.replace(/[?!.,]/g, '');

            // Remove common phrases
            removeWords.forEach((word) => {
                searchTerm = searchTerm.replace(new RegExp('\\b' + word + '\\b', 'gi'), '');
            });

            // Clean up extra spaces
            searchTerm = searchTerm.replace(/\s+/g, ' ').trim();

            return searchTerm;
        }

        addMessage(container, text, type, options = {}) {
            // Support legacy playSound boolean parameter
            const opts = typeof options === 'boolean' ? { playSound: options } : options;
            const playSound = opts.playSound !== false;

            const messageEl = document.createElement('div');
            messageEl.className = `aiagent-message aiagent-message-${type}`;

            // For AI/agent messages, render markdown/HTML; for user/system messages, use plain text
            if (type === 'ai' || type === 'error' || type === 'agent') {
                messageEl.innerHTML = this.formatMessage(text);
            } else {
                messageEl.textContent = text;
            }

            // For agent messages, add agent name attribute if provided
            if (type === 'agent' && opts.agentName) {
                messageEl.setAttribute('data-agent-name', opts.agentName);
            }

            container.appendChild(messageEl);

            // Play notification sound for AI and agent messages (not welcome messages)
            if ((type === 'ai' || type === 'agent') && playSound && this.hasMessages) {
                this.playNotificationSound();
            }

            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        formatMessage(text) {
            // Escape HTML first to prevent XSS
            let formatted = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            // Convert markdown to HTML
            // Code blocks (```)
            formatted = formatted.replace(
                /```(\w*)\n?([\s\S]*?)```/g,
                '<pre><code>$2</code></pre>'
            );

            // Inline code (`)
            formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');

            // Bold (**text** or __text__)
            formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            formatted = formatted.replace(/__([^_]+)__/g, '<strong>$1</strong>');

            // Italic (*text* or _text_)
            formatted = formatted.replace(/\*([^*]+)\*/g, '<em>$1</em>');
            formatted = formatted.replace(/_([^_]+)_/g, '<em>$1</em>');

            // Links [text](url)
            formatted = formatted.replace(
                /\[([^\]]+)\]\(([^)]+)\)/g,
                '<a href="$2" target="_blank" rel="noopener">$1</a>'
            );

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

        async startNewConversation(container, messagesContainer, resetUser = false) {
            // Clear messages
            messagesContainer.innerHTML = '';
            this.hasMessages = false;

            // If reset user, clear user info
            if (resetUser) {
                this.clearUserInfo();
                this.userId = null;
                this.userName = null;
            }

            // Request new session
            try {
                const response = await fetch(aiagentConfig.restUrl + 'new-conversation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        user_id: this.userId,
                    }),
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
                const welcomeMsg = this.getPersonalizedWelcome();
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
                const userId = localStorage.getItem('aiagent_user_id');
                // Return null if empty or not a valid number
                if (!userId || userId === 'null' || userId === 'undefined') {
                    return null;
                }
                return parseInt(userId, 10) || null;
            } catch (e) {
                return null;
            }
        }

        getUserName() {
            try {
                const name = localStorage.getItem('aiagent_user_name');
                if (!name || name === 'null' || name === 'undefined') {
                    return null;
                }
                return name;
            } catch (e) {
                return null;
            }
        }

        saveUserInfo(userId, name, email, phone = '') {
            try {
                localStorage.setItem('aiagent_user_id', String(userId));
                localStorage.setItem('aiagent_user_name', name);
                localStorage.setItem('aiagent_user_email', email);
                if (phone) {
                    localStorage.setItem('aiagent_user_phone', phone);
                }
            } catch (e) {
                // Storage not available
            }
        }

        clearUserInfo() {
            try {
                localStorage.removeItem('aiagent_user_id');
                localStorage.removeItem('aiagent_user_name');
                localStorage.removeItem('aiagent_user_email');
                localStorage.removeItem('aiagent_user_phone');
                localStorage.removeItem('aiagent_session');
            } catch (e) {
                // Storage not available
            }
        }

        // WooCommerce Product Methods
        renderProducts(products, container, options = {}) {
            if (!this.wooEnabled || !products || products.length === 0) return;

            const productsHtml = `
                <div class="aiagent-products-grid">
                    ${products.map((product) => this.renderProductCard(product, options)).join('')}
                </div>
                ${
                    options.showCompareButton && products.length >= 2
                        ? `
                    <div class="aiagent-compare-actions">
                        <button class="aiagent-compare-btn" onclick="window.aiagentChat.showComparison()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>
                            </svg>
                            Compare Selected (${this.compareProducts.length})
                        </button>
                    </div>
                `
                        : ''
                }
            `;

            this.addProductMessage(container, productsHtml);
        }

        renderProductCard(product, options = {}) {
            const showPrices = aiagentConfig.wooShowPrices;
            const showAddToCart = aiagentConfig.wooShowAddToCart;
            const showCompare = aiagentConfig.wooShowComparison;
            const isCompared = this.compareProducts.includes(product.id);

            const placeholderImage = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='150' height='150' viewBox='0 0 150 150'%3E%3Crect fill='%23f0f0f0' width='150' height='150'/%3E%3Cpath fill='%23ccc' d='M75 45c-11 0-20 9-20 20s9 20 20 20 20-9 20-20-9-20-20-20zm0 35c-8.3 0-15-6.7-15-15s6.7-15 15-15 15 6.7 15 15-6.7 15-15 15zm35 15H40c-2.8 0-5 2.2-5 5v10h80v-10c0-2.8-2.2-5-5-5z'/%3E%3Ctext x='75' y='125' text-anchor='middle' fill='%23999' font-size='12' font-family='sans-serif'%3ENo Image%3C/text%3E%3C/svg%3E`;
            const productImage = product.image || placeholderImage;

            return `
                <div class="aiagent-product-card" data-product-id="${product.id}">
                    ${product.on_sale ? '<span class="aiagent-product-badge sale">Sale</span>' : ''}
                    ${!product.in_stock ? '<span class="aiagent-product-badge out-of-stock">Out of Stock</span>' : ''}
                    <div class="aiagent-product-image">
                        <img src="${productImage}" alt="${this.escapeHtml(product.name)}" loading="lazy" onerror="this.src='${placeholderImage}'">
                    </div>
                    <div class="aiagent-product-info">
                        <h4 class="aiagent-product-name">
                            <a href="${product.permalink}" target="_blank">${this.escapeHtml(product.name)}</a>
                        </h4>
                        ${product.short_desc ? `<p class="aiagent-product-desc">${this.truncateText(product.short_desc, 60)}</p>` : ''}
                        ${
                            showPrices && product.price_html
                                ? `
                            <div class="aiagent-product-price">${product.price_html}</div>
                        `
                                : ''
                        }
                        ${
                            product.rating > 0
                                ? `
                            <div class="aiagent-product-rating">
                                ${this.renderStars(product.rating)}
                                <span class="aiagent-review-count">(${product.review_count})</span>
                            </div>
                        `
                                : ''
                        }
                    </div>
                    <div class="aiagent-product-actions">
                        ${
                            showAddToCart && product.in_stock && product.is_purchasable
                                ? `
                            <button class="aiagent-add-to-cart-btn" data-product-id="${product.id}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="m1 1 4 4 14 0 -2.5 7.5H7.5L5 4"></path>
                                </svg>
                                Add to Cart
                            </button>
                        `
                                : ''
                        }
                        ${
                            showCompare
                                ? `
                            <button class="aiagent-compare-toggle ${isCompared ? 'active' : ''}" data-product-id="${product.id}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>
                                </svg>
                            </button>
                        `
                                : ''
                        }
                        <a href="${product.permalink}" class="aiagent-view-product-btn" target="_blank">
                            View
                        </a>
                    </div>
                </div>
            `;
        }

        renderStars(rating) {
            const fullStars = Math.floor(rating);
            const halfStar = rating % 1 >= 0.5;
            const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);

            return `
                <span class="aiagent-stars">
                    ${'★'.repeat(fullStars)}
                    ${halfStar ? '½' : ''}
                    ${'☆'.repeat(emptyStars)}
                </span>
            `;
        }

        addProductMessage(container, html) {
            const messageEl = document.createElement('div');
            messageEl.className = 'aiagent-message aiagent-message-ai aiagent-message-products';
            messageEl.innerHTML = html;
            container.appendChild(messageEl);
            container.scrollTop = container.scrollHeight;

            // Bind product action events
            this.bindProductEvents(messageEl);
        }

        bindProductEvents(container) {
            // Add to cart buttons
            container.querySelectorAll('.aiagent-add-to-cart-btn').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const productId = btn.dataset.productId;
                    this.addToCart(productId, btn);
                });
            });

            // Compare toggle buttons
            container.querySelectorAll('.aiagent-compare-toggle').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const productId = parseInt(btn.dataset.productId);
                    this.toggleCompare(productId, btn);
                });
            });
        }

        async addToCart(productId, button) {
            if (!this.wooEnabled) return;

            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="aiagent-spinner"></span>';

            try {
                const response = await fetch(aiagentConfig.restUrl + 'woocommerce/add-to-cart', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        product_id: parseInt(productId),
                        quantity: 1,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    button.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Added!
                    `;
                    button.classList.add('success');

                    // Show cart notification
                    this.showCartNotification(data);

                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.classList.remove('success');
                        button.disabled = false;
                    }, 2000);
                } else if (data.requires_variation) {
                    // Handle variable products
                    button.innerHTML = originalText;
                    button.disabled = false;
                    this.showVariationModal(productId, data.variations);
                } else {
                    throw new Error(data.message || 'Failed to add to cart');
                }
            } catch (error) {
                console.error('Add to cart error:', error);
                const errorMsg = error.message || 'Error adding to cart';
                button.innerHTML = `<span title="${this.escapeHtml(errorMsg)}">Error</span>`;
                button.classList.add('error');

                // Show error notification
                this.showErrorNotification(errorMsg);

                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('error');
                    button.disabled = false;
                }, 3000);
            }
        }

        showErrorNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'aiagent-cart-notification aiagent-cart-error';
            notification.innerHTML = `
                <div class="aiagent-cart-notification-content">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <span>${this.escapeHtml(message)}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        showCartNotification(data) {
            const notification = document.createElement('div');
            notification.className = 'aiagent-cart-notification';
            notification.innerHTML = `
                <div class="aiagent-cart-notification-content">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    <span>${data.message}</span>
                </div>
                <div class="aiagent-cart-notification-actions">
                    <a href="${data.cart_url}" class="aiagent-cart-link">View Cart (${data.cart_count})</a>
                    <a href="${data.checkout_url}" class="aiagent-checkout-link">Checkout</a>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        toggleCompare(productId, button) {
            const index = this.compareProducts.indexOf(productId);
            if (index > -1) {
                this.compareProducts.splice(index, 1);
                button.classList.remove('active');
            } else {
                if (this.compareProducts.length >= 4) {
                    alert('You can compare up to 4 products at a time.');
                    return;
                }
                this.compareProducts.push(productId);
                button.classList.add('active');
            }

            // Update compare button count
            document.querySelectorAll('.aiagent-compare-btn').forEach((btn) => {
                const count = this.compareProducts.length;
                btn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>
                    </svg>
                    Compare Selected (${count})
                `;
                btn.disabled = count < 2;
            });
        }

        async showComparison() {
            if (this.compareProducts.length < 2) {
                alert('Please select at least 2 products to compare.');
                return;
            }

            try {
                const response = await fetch(aiagentConfig.restUrl + 'woocommerce/compare', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        product_ids: this.compareProducts,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    this.renderComparisonModal(data.comparison);
                }
            } catch (error) {
                console.error('Compare products error:', error);
            }
        }

        renderComparisonModal(comparison) {
            const modal = document.createElement('div');
            modal.className = 'aiagent-comparison-modal';
            modal.innerHTML = `
                <div class="aiagent-comparison-overlay"></div>
                <div class="aiagent-comparison-content">
                    <div class="aiagent-comparison-header">
                        <h3>Product Comparison</h3>
                        <button class="aiagent-comparison-close">&times;</button>
                    </div>
                    <div class="aiagent-comparison-body">
                        <table class="aiagent-comparison-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    ${comparison.products
                                        .map(
                                            (p) => `
                                        <th>
                                            <img src="${p.image}" alt="${this.escapeHtml(p.name)}">
                                            <span>${this.escapeHtml(p.name)}</span>
                                        </th>
                                    `
                                        )
                                        .join('')}
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Price</strong></td>
                                    ${comparison.products.map((p) => `<td>${p.price_html || '-'}</td>`).join('')}
                                </tr>
                                <tr>
                                    <td><strong>Rating</strong></td>
                                    ${comparison.products.map((p) => `<td>${p.rating > 0 ? this.renderStars(p.rating) + ` (${p.review_count})` : '-'}</td>`).join('')}
                                </tr>
                                <tr>
                                    <td><strong>Stock</strong></td>
                                    ${comparison.products.map((p) => `<td class="${p.in_stock ? 'in-stock' : 'out-of-stock'}">${p.in_stock ? 'In Stock' : 'Out of Stock'}</td>`).join('')}
                                </tr>
                                ${
                                    comparison.products[0].sku
                                        ? `
                                    <tr>
                                        <td><strong>SKU</strong></td>
                                        ${comparison.products.map((p) => `<td>${p.sku || '-'}</td>`).join('')}
                                    </tr>
                                `
                                        : ''
                                }
                                ${comparison.attributes
                                    .map(
                                        (attr) => `
                                    <tr>
                                        <td><strong>${this.escapeHtml(attr)}</strong></td>
                                        ${comparison.products.map((p) => `<td>${p.attributes && p.attributes[attr] ? this.escapeHtml(p.attributes[attr]) : '-'}</td>`).join('')}
                                    </tr>
                                `
                                    )
                                    .join('')}
                                <tr class="aiagent-comparison-actions-row">
                                    <td></td>
                                    ${comparison.products
                                        .map(
                                            (p) => `
                                        <td>
                                            ${
                                                p.in_stock && p.is_purchasable
                                                    ? `
                                                <button class="aiagent-add-to-cart-btn" data-product-id="${p.id}">Add to Cart</button>
                                            `
                                                    : ''
                                            }
                                            <a href="${p.permalink}" class="aiagent-view-product-btn" target="_blank">View Details</a>
                                        </td>
                                    `
                                        )
                                        .join('')}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Close handlers
            modal
                .querySelector('.aiagent-comparison-close')
                .addEventListener('click', () => modal.remove());
            modal
                .querySelector('.aiagent-comparison-overlay')
                .addEventListener('click', () => modal.remove());

            // Bind add to cart events
            this.bindProductEvents(modal);

            // Clear compare list
            this.compareProducts = [];
            document
                .querySelectorAll('.aiagent-compare-toggle')
                .forEach((btn) => btn.classList.remove('active'));
        }

        showVariationModal(productId, variationData) {
            // Simple variation selection - for complex variations, redirect to product page
            const product = document.querySelector(`[data-product-id="${productId}"]`);
            if (product) {
                const link = product.querySelector('.aiagent-view-product-btn');
                if (link) {
                    window.open(link.href, '_blank');
                }
            }
        }

        truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substr(0, maxLength) + '...';
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Calendar Booking Methods
        initCalendar(container, calendarModal) {
            // Yes button - proceed to slot selection or open Calendly
            const yesBtn = calendarModal.querySelector('.aiagent-calendar-yes');
            if (yesBtn) {
                yesBtn.addEventListener('click', () => {
                    const calendarType = container.dataset.calendarType;

                    if (calendarType === 'calendly') {
                        this.openCalendly(container);
                    } else {
                        // Google Calendar - show slot selection
                        this.showCalendarStep(calendarModal, 'slots');
                        this.loadAvailableSlots(calendarModal);
                    }
                });
            }

            // No button - close calendar and chat
            const noBtn = calendarModal.querySelector('.aiagent-calendar-no');
            if (noBtn) {
                noBtn.addEventListener('click', () => {
                    this.closeChat(container, true);
                });
            }

            // Back buttons
            calendarModal.querySelectorAll('.aiagent-calendar-back').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const currentStep = btn.closest('.aiagent-calendar-step');
                    const step = currentStep.dataset.step;

                    if (step === 'slots') {
                        this.showCalendarStep(calendarModal, 'prompt');
                    } else if (step === 'details') {
                        this.showCalendarStep(calendarModal, 'slots');
                    }
                });
            });

            // Date navigation
            const prevBtn = calendarModal.querySelector('.aiagent-date-prev');
            const nextBtn = calendarModal.querySelector('.aiagent-date-next');

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.currentDateIndex > 0) {
                        this.currentDateIndex--;
                        this.renderCurrentDateSlots(calendarModal);
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    if (this.currentDateIndex < this.calendarSlots.length - 1) {
                        this.currentDateIndex++;
                        this.renderCurrentDateSlots(calendarModal);
                    }
                });
            }

            // Booking form
            const bookingForm = calendarModal.querySelector('.aiagent-calendar-form');
            if (bookingForm) {
                bookingForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.submitBooking(container, calendarModal, bookingForm);
                });
            }

            // Done button
            const doneBtn = calendarModal.querySelector('.aiagent-calendar-done');
            if (doneBtn) {
                doneBtn.addEventListener('click', () => {
                    this.closeChat(container, true);
                });
            }
        }

        showCalendarStep(calendarModal, step) {
            const steps = calendarModal.querySelectorAll('.aiagent-calendar-step');
            steps.forEach((s) => {
                s.style.display = s.dataset.step === step ? 'block' : 'none';
            });
        }

        async loadAvailableSlots(calendarModal) {
            const loadingEl = calendarModal.querySelector('.aiagent-calendar-loading');
            const slotsContainer = calendarModal.querySelector('.aiagent-calendar-slots-container');

            // Show loading
            if (loadingEl) loadingEl.style.display = 'flex';
            if (slotsContainer) slotsContainer.style.display = 'none';

            try {
                const response = await fetch(aiagentConfig.restUrl + 'gcalendar/slots', {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                });

                const data = await response.json();

                if (data.success && data.slots && data.slots.length > 0) {
                    this.calendarSlots = data.slots;
                    this.currentDateIndex = 0;
                    this.renderCurrentDateSlots(calendarModal);

                    // Hide loading, show slots
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (slotsContainer) slotsContainer.style.display = 'block';
                } else {
                    // No slots available
                    if (loadingEl) {
                        loadingEl.innerHTML = `
                            <p style="color: #666;">No available time slots found. Please try again later.</p>
                        `;
                    }
                }
            } catch (error) {
                console.error('Failed to load calendar slots:', error);
                if (loadingEl) {
                    loadingEl.innerHTML = `
                        <p style="color: #e53935;">Failed to load available times. Please try again.</p>
                    `;
                }
            }
        }

        renderCurrentDateSlots(calendarModal) {
            if (!this.calendarSlots || this.calendarSlots.length === 0) return;

            const dateData = this.calendarSlots[this.currentDateIndex];
            const dateLabel = calendarModal.querySelector('.aiagent-date-label');
            const slotsGrid = calendarModal.querySelector('.aiagent-slots-grid');
            const noSlots = calendarModal.querySelector('.aiagent-no-slots');
            const prevBtn = calendarModal.querySelector('.aiagent-date-prev');
            const nextBtn = calendarModal.querySelector('.aiagent-date-next');

            // Update date label
            if (dateLabel) {
                dateLabel.textContent = `${dateData.day_name}, ${dateData.date_label}`;
            }

            // Update nav buttons
            if (prevBtn) prevBtn.disabled = this.currentDateIndex === 0;
            if (nextBtn) nextBtn.disabled = this.currentDateIndex >= this.calendarSlots.length - 1;

            // Render slots
            if (slotsGrid) {
                if (dateData.slots && dateData.slots.length > 0) {
                    slotsGrid.innerHTML = dateData.slots
                        .map(
                            (slot) => `
                        <button type="button" class="aiagent-slot-btn" 
                            data-start="${slot.start_iso}" 
                            data-end="${slot.end_iso}" 
                            data-label="${slot.label}"
                            data-date="${dateData.date_label}">
                            ${slot.label}
                        </button>
                    `
                        )
                        .join('');

                    // Bind slot click events
                    slotsGrid.querySelectorAll('.aiagent-slot-btn').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            this.selectSlot(calendarModal, btn);
                        });
                    });

                    if (noSlots) noSlots.style.display = 'none';
                    slotsGrid.style.display = 'grid';
                } else {
                    slotsGrid.style.display = 'none';
                    if (noSlots) noSlots.style.display = 'block';
                }
            }
        }

        selectSlot(calendarModal, btn) {
            // Store selected slot
            this.selectedSlot = {
                start: btn.dataset.start,
                end: btn.dataset.end,
                label: btn.dataset.label,
                date: btn.dataset.date,
            };

            // Update selected time display
            const selectedTimeText = calendarModal.querySelector('.aiagent-selected-time-text');
            if (selectedTimeText) {
                selectedTimeText.textContent = `${this.selectedSlot.date} at ${this.selectedSlot.label}`;
            }

            // Show details step
            this.showCalendarStep(calendarModal, 'details');
        }

        async submitBooking(container, calendarModal, form) {
            if (!this.selectedSlot) return;

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            // Disable form
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="aiagent-spinner"></span> Booking...';

            const title = form.querySelector('input[name="event_title"]').value.trim();
            const description =
                form.querySelector('textarea[name="event_description"]')?.value.trim() || '';

            // Get user email from localStorage
            let userEmail = '';
            let userName = '';
            try {
                userEmail = localStorage.getItem('aiagent_user_email') || '';
                userName = localStorage.getItem('aiagent_user_name') || '';
            } catch (e) {
                // Storage not available
            }

            try {
                const response = await fetch(aiagentConfig.restUrl + 'gcalendar/create-event', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        title: title || 'Meeting',
                        description: description,
                        start: this.selectedSlot.start,
                        end: this.selectedSlot.end,
                        attendee_email: userEmail,
                        attendee_name: userName,
                        add_meet: true, // Add Google Meet link
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    // Show confirmation
                    const bookingTitle = calendarModal.querySelector('.aiagent-booking-title');
                    const bookingTime = calendarModal.querySelector('.aiagent-booking-time');

                    if (bookingTitle) bookingTitle.textContent = data.summary || title;
                    if (bookingTime)
                        bookingTime.textContent = `${this.selectedSlot.date} at ${this.selectedSlot.label}`;

                    this.showCalendarStep(calendarModal, 'confirmation');
                } else {
                    throw new Error(data.message || 'Failed to create event');
                }
            } catch (error) {
                console.error('Booking error:', error);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                alert('Failed to book appointment: ' + (error.message || 'Please try again.'));
            }
        }

        // Calendly Methods
        openCalendly(container) {
            if (!this.calendlyEnabled || !this.calendlySettings.scheduling_url) {
                console.error('Calendly not configured');
                return;
            }

            const url = this.calendlySettings.scheduling_url;
            const integrationType = this.calendlySettings.integration_type || 'popup';

            // Prefill user info if available
            let prefillUrl = url;
            const params = [];

            try {
                const userName = localStorage.getItem('aiagent_user_name');
                const userEmail = localStorage.getItem('aiagent_user_email');

                if (userName) {
                    params.push('name=' + encodeURIComponent(userName));
                }
                if (userEmail) {
                    params.push('email=' + encodeURIComponent(userEmail));
                }

                if (params.length > 0) {
                    prefillUrl += (url.includes('?') ? '&' : '?') + params.join('&');
                }
            } catch (e) {
                // Storage not available
            }

            if (integrationType === 'popup' && window.Calendly) {
                // Use Calendly popup widget
                window.Calendly.initPopupWidget({ url: prefillUrl });
                this.hideCalendarPrompt(container);
            } else if (integrationType === 'embed') {
                // Show embed in the calendar modal
                this.showCalendlyEmbed(container, prefillUrl);
            } else {
                // Open in new window
                window.open(prefillUrl, '_blank');
                this.closeChat(container, true);
            }
        }

        showCalendlyEmbed(container, url) {
            const calendarModal = container.querySelector('.aiagent-calendar-modal');
            if (!calendarModal) return;

            // Hide all steps
            const steps = calendarModal.querySelectorAll('.aiagent-calendar-step');
            steps.forEach((step) => {
                step.style.display = 'none';
            });

            // Create or show embed container
            let embedContainer = calendarModal.querySelector('.aiagent-calendly-embed');
            if (!embedContainer) {
                embedContainer = document.createElement('div');
                embedContainer.className = 'aiagent-calendly-embed';
                embedContainer.innerHTML = `
                    <div class="aiagent-calendar-header">
                        <button type="button" class="aiagent-calendar-back">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <h3>Schedule a Meeting</h3>
                    </div>
                    <div class="calendly-inline-widget" data-url="${url}" style="min-width:100%;height:${this.calendlySettings.embed_height || 500}px;"></div>
                `;
                calendarModal.querySelector('.aiagent-calendar-inner').appendChild(embedContainer);

                // Bind back button
                embedContainer
                    .querySelector('.aiagent-calendar-back')
                    .addEventListener('click', () => {
                        embedContainer.style.display = 'none';
                        this.showCalendarStep(calendarModal, 'prompt');
                    });

                // Load Calendly widget script if not loaded
                if (!window.Calendly) {
                    const script = document.createElement('script');
                    script.src = 'https://assets.calendly.com/assets/external/widget.js';
                    script.async = true;
                    document.head.appendChild(script);
                }
            } else {
                // Update URL
                const widget = embedContainer.querySelector('.calendly-inline-widget');
                if (widget) {
                    widget.dataset.url = url;
                }
            }

            embedContainer.style.display = 'block';

            // Reinitialize Calendly widget
            if (window.Calendly) {
                window.Calendly.initInlineWidget({
                    url: url,
                    parentElement: embedContainer.querySelector('.calendly-inline-widget'),
                });
            }
        }

        // Search products through chat
        async searchProducts(query, messagesContainer) {
            if (!this.wooEnabled) {
                return null;
            }

            try {
                const searchUrl =
                    aiagentConfig.restUrl +
                    'woocommerce/search?query=' +
                    encodeURIComponent(query) +
                    '&limit=' +
                    aiagentConfig.wooMaxProducts;

                const response = await fetch(searchUrl, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                });

                const data = await response.json();

                if (data.success && data.products && data.products.length > 0) {
                    return data;
                }
            } catch (error) {
                console.error('Product search error:', error);
            }

            return null;
        }

        // Get featured/all products as fallback
        async getFeaturedProducts() {
            if (!this.wooEnabled) {
                return null;
            }

            try {
                // Try featured products first
                let response = await fetch(
                    aiagentConfig.restUrl +
                        'woocommerce/featured?limit=' +
                        aiagentConfig.wooMaxProducts,
                    {
                        method: 'GET',
                        headers: {
                            'X-WP-Nonce': aiagentConfig.nonce,
                        },
                    }
                );

                let data = await response.json();

                if (data.success && data.products && data.products.length > 0) {
                    return data;
                }

                // If no featured products, try bestsellers
                response = await fetch(
                    aiagentConfig.restUrl +
                        'woocommerce/bestsellers?limit=' +
                        aiagentConfig.wooMaxProducts,
                    {
                        method: 'GET',
                        headers: {
                            'X-WP-Nonce': aiagentConfig.nonce,
                        },
                    }
                );

                data = await response.json();

                if (data.success && data.products && data.products.length > 0) {
                    return data;
                }

                // Last resort: search with empty query to get any products
                response = await fetch(
                    aiagentConfig.restUrl +
                        'woocommerce/search?query=&limit=' +
                        aiagentConfig.wooMaxProducts,
                    {
                        method: 'GET',
                        headers: {
                            'X-WP-Nonce': aiagentConfig.nonce,
                        },
                    }
                );

                data = await response.json();

                if (data.success && data.products && data.products.length > 0) {
                    return data;
                }
            } catch (error) {
                console.error('Get featured products error:', error);
            }

            return null;
        }

        // =====================================================
        // Live Agent Methods
        // =====================================================

        /**
         * Initialize live agent functionality
         * @param {HTMLElement} container - The chat widget container
         */
        initLiveAgent(container) {
            // Find the connect button (check both possible class names)
            const connectBtn = container.querySelector('.aiagent-connect-agent-btn, .aiagent-connect-live-agent-btn');
            if (connectBtn) {
                connectBtn.addEventListener('click', () => {
                    this.handleLiveAgentConnect(container);
                });
            }

            // Find cancel button in waiting modal
            const cancelBtn = container.querySelector('.aiagent-cancel-live-connect');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    this.cancelLiveAgentConnect(container);
                });
            }

            // Find start chatting button in connected modal
            const startChatBtn = container.querySelector('.aiagent-start-live-chat');
            if (startChatBtn) {
                startChatBtn.addEventListener('click', () => {
                    this.startLiveChatSession(container);
                });
            }

            // Find close offline modal button
            const closeOfflineBtn = container.querySelector('.aiagent-close-offline-modal');
            if (closeOfflineBtn) {
                closeOfflineBtn.addEventListener('click', () => {
                    this.hideAllLiveAgentModals(container);
                });
            }

            // Find end live chat button
            const endLiveChatBtn = container.querySelector('.aiagent-end-live-chat');
            if (endLiveChatBtn) {
                endLiveChatBtn.addEventListener('click', () => {
                    this.endLiveAgentSession(container);
                });
            }

            // Hide live agent banner initially - will show based on conditions
            this.hideLiveAgentBanner(container);

            console.log('Live Agent initialized:', {
                enabled: this.liveAgentEnabled,
                agentAvailable: this.isAgentAvailable
            });
        }

        /**
         * Update the agent availability status indicator
         * @param {HTMLElement} container - The chat widget container
         */
        updateAgentStatus(container) {
            const statusIndicator = container.querySelector('.aiagent-status-indicator');
            const statusText = container.querySelector('.aiagent-status-text');

            if (statusIndicator && statusText) {
                if (this.isAgentAvailable) {
                    statusIndicator.classList.add('online');
                    statusIndicator.classList.remove('offline');
                    statusText.textContent = 'Agent Online';
                } else {
                    statusIndicator.classList.remove('online');
                    statusIndicator.classList.add('offline');
                    statusText.textContent = 'Agent Offline';
                }
            }
        }

        /**
         * Check if response indicates AI couldn't help
         * @param {string} message - The AI response message
         * @returns {boolean} True if response is negative/unhelpful
         */
        isNegativeResponse(message) {
            const negativePatterns = [
                /i('m| am) (sorry|afraid|unable|not able)/i,
                /i (don't|do not|cannot|can't) (have|know|find|help|provide|access)/i,
                /unfortunately/i,
                /i couldn't find/i,
                /no (information|data|results|matches|records)/i,
                /not (available|found|sure|certain)/i,
                /beyond my (capabilities|scope|knowledge)/i,
                /please (contact|reach out to|speak with|talk to).*(support|agent|human|representative|team)/i,
                /i (recommend|suggest) (speaking|talking|contacting)/i,
                /for (more|further|additional) (help|assistance|support)/i,
            ];

            return negativePatterns.some((pattern) => pattern.test(message));
        }

        /**
         * Check if conditions are met to show live agent option
         * @param {HTMLElement} container - The chat widget container
         */
        checkLiveAgentTrigger(container) {
            if (!this.liveAgentEnabled || !container) return;

            // Get settings for trigger conditions
            const showAfterMessages = this.liveAgentSettings?.show_after_messages || 3;
            const showOnNoResults = this.liveAgentSettings?.show_on_no_results !== false;

            // Show live agent banner after configured messages with at least 1 negative response
            // or after 5+ total messages regardless of response type
            const shouldShow =
                (showOnNoResults && this.messageCount >= showAfterMessages && this.negativeResponseCount >= 1) ||
                this.messageCount >= 5;

            console.log('Live Agent Trigger Check:', {
                messageCount: this.messageCount,
                negativeResponseCount: this.negativeResponseCount,
                shouldShow,
                isAgentAvailable: this.isAgentAvailable
            });

            if (shouldShow && this.isAgentAvailable) {
                this.showLiveAgentBanner(container);
            }
        }

        /**
         * Show the live agent connect banner
         * @param {HTMLElement} container - The chat widget container
         */
        showLiveAgentBanner(container) {
            const banner = container.querySelector('.aiagent-live-agent-banner');
            if (banner) {
                banner.classList.add('visible');
                banner.style.display = 'block';

                // Scroll to show the banner
                const messagesContainer = container.querySelector('.aiagent-messages');
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }
        }

        /**
         * Hide the live agent connect banner
         * @param {HTMLElement} container - The chat widget container
         */
        hideLiveAgentBanner(container) {
            const banner = container.querySelector('.aiagent-live-agent-banner');
            if (banner) {
                banner.classList.remove('visible');
                banner.style.display = 'none';
            }
        }

        /**
         * Hide all live agent modals
         * @param {HTMLElement} container - The chat widget container
         */
        hideAllLiveAgentModals(container) {
            const modals = container.querySelectorAll('.aiagent-live-waiting-modal, .aiagent-live-connected-modal, .aiagent-live-offline-modal');
            modals.forEach(modal => {
                modal.classList.remove('visible');
                modal.style.display = 'none';
            });
        }

        /**
         * Show waiting modal
         * @param {HTMLElement} container - The chat widget container
         */
        showWaitingModal(container) {
            this.hideAllLiveAgentModals(container);
            const modal = container.querySelector('.aiagent-live-waiting-modal');
            if (modal) {
                modal.classList.add('visible');
                modal.style.display = 'flex';
            }
        }

        /**
         * Show connected modal
         * @param {HTMLElement} container - The chat widget container
         * @param {Object} agent - Agent info
         */
        showConnectedModal(container, agent) {
            this.hideAllLiveAgentModals(container);
            const modal = container.querySelector('.aiagent-live-connected-modal');
            if (modal) {
                // Update agent info if available
                if (agent) {
                    const nameEl = modal.querySelector('.aiagent-agent-name');
                    const avatarEl = modal.querySelector('.aiagent-agent-avatar');
                    if (nameEl) nameEl.textContent = agent.name || 'Agent';
                    if (avatarEl && agent.avatar) {
                        avatarEl.innerHTML = `<img src="${agent.avatar}" alt="${agent.name || 'Agent'}">`;
                    }
                }
                modal.classList.add('visible');
                modal.style.display = 'flex';
            }
        }

        /**
         * Show offline modal
         * @param {HTMLElement} container - The chat widget container
         */
        showOfflineModal(container) {
            this.hideAllLiveAgentModals(container);
            const modal = container.querySelector('.aiagent-live-offline-modal');
            if (modal) {
                modal.classList.add('visible');
                modal.style.display = 'flex';
            }
        }

        /**
         * Cancel live agent connection
         * @param {HTMLElement} container - The chat widget container
         */
        cancelLiveAgentConnect(container) {
            // Stop polling
            if (this.agentPollInterval) {
                clearInterval(this.agentPollInterval);
                this.agentPollInterval = null;
            }
            this.hideAllLiveAgentModals(container);
            this.showLiveAgentBanner(container);
        }

        /**
         * Start live chat session (after clicking Start Chatting)
         * @param {HTMLElement} container - The chat widget container
         */
        startLiveChatSession(container) {
            this.hideAllLiveAgentModals(container);
            this.isLiveAgentMode = true;
            
            // Show the live agent status bar
            const statusBar = container.querySelector('.aiagent-live-agent-status');
            if (statusBar) {
                statusBar.classList.add('visible');
                statusBar.style.display = 'block';
            }
            
            // Hide the banner
            this.hideLiveAgentBanner(container);
        }

        /**
         * Handle connection to live agent
         * @param {HTMLElement} container - The chat widget container
         */
        async handleLiveAgentConnect(container) {
            // Check if agent is available
            if (!this.isAgentAvailable) {
                this.showOfflineModal(container);
                return;
            }

            // Hide banner and show waiting modal
            this.hideLiveAgentBanner(container);
            this.showWaitingModal(container);

            try {
                const response = await fetch(`${aiagentConfig.restUrl}live-agent/connect`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiagentConfig.nonce,
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        user_id: this.userId,
                        conversation_id: this.conversationId,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    // Store the live session ID for messaging
                    this.liveSessionId = data.session_id || data.live_session_id;

                    // Check if already connected to an agent
                    if (data.status === 'active' && data.agent) {
                        this.showConnectedModal(container, data.agent);
                    } else if (data.status === 'waiting') {
                        // Update queue position if available
                        if (data.queue_position) {
                            const queueInfo = container.querySelector('.aiagent-live-queue-info');
                            const queuePos = container.querySelector('.aiagent-queue-position');
                            if (queueInfo && queuePos) {
                                queuePos.textContent = `Position in queue: ${data.queue_position}`;
                                queueInfo.style.display = 'block';
                            }
                        }
                    }

                    // Start polling for agent response/messages
                    this.startAgentPolling(container);
                } else {
                    // Show offline modal on error
                    this.showOfflineModal(container);
                }
            } catch (error) {
                console.error('Live agent connect error:', error);
                this.showOfflineModal(container);
            }
        }

        /**
         * Show pending state while waiting for agent
         * @param {HTMLElement} container - The chat widget container
         */
        showLiveAgentPendingState(container) {
            const liveAgentActions = container.querySelector('.aiagent-live-agent-actions');
            if (liveAgentActions) {
                liveAgentActions.innerHTML = `
                    <div class="aiagent-live-agent-pending">
                        <svg class="aiagent-spinner" viewBox="0 0 24 24" width="24" height="24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.4" stroke-dashoffset="10">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                            </circle>
                        </svg>
                        <span>Waiting for a live agent to connect...</span>
                    </div>
                `;
                liveAgentActions.style.display = 'block';
            }
        }

        /**
         * Start polling for agent messages
         * @param {HTMLElement} container - The chat widget container
         */
        startAgentPolling(container) {
            // Track last message ID to get only new messages
            this.lastMessageId = 0;

            // Poll every 3 seconds for session status and agent messages
            this.agentPollInterval = setInterval(async () => {
                try {
                    // First check session status
                    const statusResponse = await fetch(
                        `${aiagentConfig.restUrl}live-agent/session-status?session_id=${this.sessionId}`,
                        {
                            headers: {
                                'X-WP-Nonce': aiagentConfig.nonce,
                            },
                        }
                    );

                    const statusData = await statusResponse.json();
                    const messagesContainer = container.querySelector('.aiagent-messages');

                    if (statusData.success && statusData.has_session) {
                        // Update live session ID if available
                        if (statusData.live_session_id) {
                            this.liveSessionId = statusData.live_session_id;
                        }

                        // Check if agent has joined (status changed to active)
                        if (statusData.status === 'active' && !this.isLiveAgentMode && statusData.agent) {
                            this.showConnectedModal(container, statusData.agent);
                        }

                        // Check if session ended
                        if (statusData.status === 'ended') {
                            this.endLiveAgentSession(container, false);
                            return;
                        }

                        // If we have an active session, get new messages
                        if (this.liveSessionId && statusData.status === 'active') {
                            const msgResponse = await fetch(
                                `${aiagentConfig.restUrl}live-agent/messages?live_session_id=${this.liveSessionId}&after_id=${this.lastMessageId}`,
                                {
                                    headers: {
                                        'X-WP-Nonce': aiagentConfig.nonce,
                                    },
                                }
                            );

                            const msgData = await msgResponse.json();

                            // Display any new messages from the agent
                            if (msgData.success && msgData.messages && msgData.messages.length > 0) {
                                msgData.messages.forEach((msg) => {
                                    // Only show agent messages (not user's own)
                                    if (msg.sender_type === 'agent') {
                                        const agentUser = statusData.agent;
                                        this.addMessage(messagesContainer, msg.message, 'agent', {
                                            agentName: agentUser ? agentUser.name : 'Agent',
                                        });
                                    }
                                    // Update last message ID
                                    this.lastMessageId = Math.max(this.lastMessageId, msg.id);
                                });
                            }
                        }

                        // Update queue position if waiting
                        if (statusData.status === 'waiting' && statusData.queue_position) {
                            this.updateQueuePosition(container, statusData.queue_position);
                        }
                    }
                } catch (error) {
                    console.error('Agent polling error:', error);
                }
            }, 3000);
        }

        /**
         * Show connected state when agent joins
         * @param {HTMLElement} container - The chat widget container
         * @param {string} agentName - The agent's name
         */
        showLiveAgentConnectedState(container, agentName) {
            const liveAgentActions = container.querySelector('.aiagent-live-agent-actions');
            if (liveAgentActions) {
                liveAgentActions.innerHTML = `
                    <div class="aiagent-live-agent-connected">
                        <span class="aiagent-agent-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            ${agentName || 'Live Agent'}
                        </span>
                        <button type="button" class="aiagent-btn aiagent-btn-text aiagent-end-live-agent-btn">
                            End Chat
                        </button>
                    </div>
                `;

                // Add end chat handler
                const endBtn = liveAgentActions.querySelector('.aiagent-end-live-agent-btn');
                if (endBtn) {
                    endBtn.addEventListener('click', () => {
                        this.endLiveAgentSession(container);
                    });
                }
            }

            // Update status indicator
            const statusText = container.querySelector('.aiagent-status-text');
            if (statusText) {
                statusText.textContent = `Connected: ${agentName || 'Live Agent'}`;
            }
        }

        /**
         * Update queue position display
         * @param {HTMLElement} container - The chat widget container
         * @param {number} position - Queue position
         */
        updateQueuePosition(container, position) {
            const pendingDiv = container.querySelector('.aiagent-live-agent-pending');
            if (pendingDiv) {
                const positionText = position > 0 ? ` You are #${position} in the queue.` : '';
                pendingDiv.querySelector('span').textContent = `Waiting for a live agent to connect...${positionText}`;
            }
        }

        /**
         * End live agent session and return to AI
         * @param {HTMLElement} container - The chat widget container
         * @param {boolean} notifyServer - Whether to notify the server (default true)
         */
        async endLiveAgentSession(container, notifyServer = true) {
            // Stop polling
            if (this.agentPollInterval) {
                clearInterval(this.agentPollInterval);
                this.agentPollInterval = null;
            }

            this.isLiveAgentMode = false;

            // Notify server of session end if requested
            if (notifyServer && this.liveSessionId) {
                try {
                    await fetch(`${aiagentConfig.restUrl}live-agent/end`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': aiagentConfig.nonce,
                        },
                        body: JSON.stringify({
                            live_session_id: this.liveSessionId,
                            ended_by: 'user',
                        }),
                    });
                } catch (error) {
                    console.error('Error ending live agent session:', error);
                }
            }

            // Clear live session ID
            this.liveSessionId = null;
            this.lastMessageId = 0;

            // Reset UI
            const messagesContainer = container.querySelector('.aiagent-messages');
            this.addMessage(
                messagesContainer,
                "The live agent session has ended. You're now chatting with our AI assistant.",
                'system'
            );

            // Reset message counters
            this.messageCount = 0;
            this.negativeResponseCount = 0;

            // Reset live agent actions
            const liveAgentActions = container.querySelector('.aiagent-live-agent-actions');
            if (liveAgentActions) {
                liveAgentActions.innerHTML = `
                    <button type="button" class="aiagent-btn aiagent-btn-secondary aiagent-connect-live-agent-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        ${this.liveAgentConnectButtonText}
                    </button>
                `;
                liveAgentActions.style.display = 'none';

                // Re-attach event listener
                const connectBtn = liveAgentActions.querySelector('.aiagent-connect-live-agent-btn');
                if (connectBtn) {
                    connectBtn.addEventListener('click', () => {
                        this.handleLiveAgentConnect(container);
                    });
                }
            }

            // Update status
            this.updateAgentStatus(container);
        }
    }

    // Make instance globally available for inline handlers
    window.aiagentChat = null;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.aiagentChat = new AIAgentChat();
        });
    } else {
        window.aiagentChat = new AIAgentChat();
    }
})();
