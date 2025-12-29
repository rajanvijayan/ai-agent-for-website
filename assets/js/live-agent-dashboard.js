/**
 * Live Agent Dashboard JavaScript
 */

(function ($) {
    'use strict';

    const Dashboard = {
        currentSessionId: null,
        pollInterval: null,
        lastMessageId: 0,

        init: function () {
            this.bindEvents();
            this.loadSessions();
            this.startPolling();
        },

        bindEvents: function () {
            // Toggle agent status
            $('#toggle-agent-status').on('click', () => this.toggleStatus());

            // Send message
            $('#agent-message-form').on('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });

            // End chat
            $('#end-chat-btn').on('click', () => this.endChat());
        },

        toggleStatus: function () {
            const $btn = $('#toggle-agent-status');
            const $badge = $('#agent-status-indicator');
            const isOnline = $badge.hasClass('online');
            const newStatus = isOnline ? 'offline' : 'available';

            $btn.prop('disabled', true).text('Updating...');

            $.ajax({
                url: aiagentLiveAgent.restUrl + 'live-agent/set-status',
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                contentType: 'application/json',
                data: JSON.stringify({ status: newStatus }),
                success: (response) => {
                    if (response.success) {
                        if (newStatus === 'available') {
                            $badge.removeClass('offline').addClass('online');
                            $badge.find('.status-text').text('Online');
                            $btn.removeClass('button-primary')
                                .addClass('button-secondary')
                                .text('Go Offline');
                        } else {
                            $badge.removeClass('online').addClass('offline');
                            $badge.find('.status-text').text('Offline');
                            $btn.removeClass('button-secondary')
                                .addClass('button-primary')
                                .text('Go Online');
                        }
                    }
                    $btn.prop('disabled', false);
                },
                error: () => {
                    alert('Failed to update status');
                    $btn.prop('disabled', false).text(isOnline ? 'Go Offline' : 'Go Online');
                },
            });
        },

        loadSessions: function () {
            $.ajax({
                url: aiagentLiveAgent.restUrl + 'live-agent/sessions',
                method: 'GET',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                success: (response) => {
                    this.renderSessions(response.sessions || []);
                },
                error: () => {
                    $('#sessions-list').html(
                        '<div class="aiagent-no-sessions"><span class="dashicons dashicons-warning"></span><p>Failed to load sessions</p></div>'
                    );
                },
            });
        },

        renderSessions: function (sessions) {
            const $list = $('#sessions-list');
            $('#sessions-count').text(sessions.length);

            if (sessions.length === 0) {
                $list.html(`
                    <div class="aiagent-no-sessions">
                        <span class="dashicons dashicons-format-chat"></span>
                        <p>${aiagentLiveAgent.strings.noSessions}</p>
                    </div>
                `);
                return;
            }

            let html = '';
            sessions.forEach((session) => {
                const isActive = this.currentSessionId === session.id;
                const statusClass = session.status === 'waiting' ? 'waiting' : 'active';
                const statusText =
                    session.status === 'waiting'
                        ? aiagentLiveAgent.strings.waiting
                        : aiagentLiveAgent.strings.active;
                const time = this.formatTime(session.started_at);

                html += `
                    <div class="aiagent-session-item ${isActive ? 'active' : ''} ${statusClass}" data-session-id="${session.id}">
                        <div class="aiagent-session-avatar">
                            <span class="dashicons dashicons-admin-users"></span>
                        </div>
                        <div class="aiagent-session-info">
                            <span class="aiagent-session-name">${session.user_name || aiagentLiveAgent.strings.visitor}</span>
                            <span class="aiagent-session-preview">${session.user_email || 'No email'}</span>
                            <div class="aiagent-session-meta">
                                <span class="aiagent-session-time">${time}</span>
                                <span class="aiagent-session-status ${statusClass}">${statusText}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            $list.html(html);

            // Bind click events
            $('.aiagent-session-item').on('click', function () {
                const sessionId = $(this).data('session-id');
                Dashboard.selectSession(sessionId);
            });
        },

        selectSession: function (sessionId) {
            this.currentSessionId = sessionId;
            this.lastMessageId = 0;

            // Update UI
            $('.aiagent-session-item').removeClass('active');
            $(`.aiagent-session-item[data-session-id="${sessionId}"]`).addClass('active');

            // Get session details
            const $session = $(`.aiagent-session-item[data-session-id="${sessionId}"]`);
            const userName = $session.find('.aiagent-session-name').text();
            const userEmail = $session.find('.aiagent-session-preview').text();
            const isWaiting = $session.hasClass('waiting');

            // Update header
            $('#chat-user-name').text(userName);
            $('#chat-user-email').text(userEmail);
            $('#end-chat-btn').show();

            // Load messages
            this.loadMessages(sessionId, isWaiting);
        },

        loadMessages: function (sessionId, isWaiting) {
            const $messages = $('#chat-messages');
            $messages.html(
                '<div class="aiagent-loading"><span class="spinner is-active"></span> Loading messages...</div>'
            );

            $.ajax({
                url: aiagentLiveAgent.restUrl + 'live-agent/messages',
                method: 'GET',
                data: { live_session_id: sessionId, after_id: 0 },
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                success: (response) => {
                    this.renderMessages(response.messages || [], isWaiting);
                    $('#chat-input-area').show();
                },
                error: () => {
                    $messages.html(
                        '<div class="aiagent-empty-state"><p>Failed to load messages</p></div>'
                    );
                },
            });
        },

        renderMessages: function (messages, isWaiting) {
            const $messages = $('#chat-messages');
            let html = '';

            // Show accept button if waiting
            if (isWaiting) {
                html += `
                    <div class="aiagent-accept-chat">
                        <p>This visitor is waiting for an agent.</p>
                        <button type="button" class="button button-primary" id="accept-chat-btn">
                            ${aiagentLiveAgent.strings.acceptChat}
                        </button>
                    </div>
                `;
            }

            messages.forEach((msg) => {
                const isAgent = msg.sender_type === 'agent';
                const senderName = isAgent
                    ? aiagentLiveAgent.strings.you
                    : aiagentLiveAgent.strings.visitor;
                const time = this.formatTime(msg.created_at);

                html += `
                    <div class="aiagent-message ${isAgent ? 'agent' : 'user'}">
                        <div class="aiagent-message-bubble">
                            <span class="aiagent-message-sender">${senderName}</span>
                            ${this.escapeHtml(msg.message)}
                            <span class="aiagent-message-time">${time}</span>
                        </div>
                    </div>
                `;

                this.lastMessageId = Math.max(this.lastMessageId, msg.id);
            });

            if (messages.length === 0 && !isWaiting) {
                html =
                    '<div class="aiagent-system-message"><span>Chat started. Waiting for messages...</span></div>';
            }

            $messages.html(html);
            $messages.scrollTop($messages[0].scrollHeight);

            // Bind accept button
            $('#accept-chat-btn').on('click', () => this.acceptChat());
        },

        acceptChat: function () {
            if (!this.currentSessionId) return;

            $.ajax({
                url: aiagentLiveAgent.restUrl + 'live-agent/accept',
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                contentType: 'application/json',
                data: JSON.stringify({ live_session_id: this.currentSessionId }),
                success: (response) => {
                    if (response.success) {
                        this.loadSessions();
                        this.loadMessages(this.currentSessionId, false);
                    }
                },
                error: () => {
                    alert('Failed to accept chat');
                },
            });
        },

        sendMessage: function () {
            const $input = $('#agent-message-input');
            const message = $input.val().trim();

            if (!message || !this.currentSessionId) return;

            $input.val('').prop('disabled', true);

            $.ajax({
                url: aiagentLiveAgent.restUrl + 'live-agent/message',
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                contentType: 'application/json',
                data: JSON.stringify({
                    live_session_id: this.currentSessionId,
                    message: message,
                    sender_type: 'agent',
                    sender_id: aiagentLiveAgent.userId,
                }),
                success: (response) => {
                    if (response.success) {
                        // Add message to UI
                        const html = `
                            <div class="aiagent-message agent">
                                <div class="aiagent-message-bubble">
                                    <span class="aiagent-message-sender">${aiagentLiveAgent.strings.you}</span>
                                    ${this.escapeHtml(message)}
                                    <span class="aiagent-message-time">Just now</span>
                                </div>
                            </div>
                        `;
                        $('#chat-messages').append(html);
                        $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
                    }
                    $input.prop('disabled', false).focus();
                },
                error: () => {
                    alert('Failed to send message');
                    $input.val(message).prop('disabled', false);
                },
            });
        },

        endChat: function () {
            if (!this.currentSessionId) return;

            if (!confirm('Are you sure you want to end this chat?')) return;

            $.ajax({
                url: aiagentLiveAgent.restUrl + 'live-agent/end',
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                contentType: 'application/json',
                data: JSON.stringify({
                    live_session_id: this.currentSessionId,
                    ended_by: 'agent',
                }),
                success: (response) => {
                    if (response.success) {
                        this.currentSessionId = null;
                        this.loadSessions();
                        this.resetChatPanel();
                    }
                },
                error: () => {
                    alert('Failed to end chat');
                },
            });
        },

        resetChatPanel: function () {
            $('#chat-user-name').text('Select a chat');
            $('#chat-user-email').text('');
            $('#end-chat-btn').hide();
            $('#chat-input-area').hide();
            $('#chat-messages').html(`
                <div class="aiagent-empty-state">
                    <span class="dashicons dashicons-format-chat"></span>
                    <p>Select a chat session from the list to start responding.</p>
                </div>
            `);
        },

        startPolling: function () {
            // Poll every 3 seconds for updates
            this.pollInterval = setInterval(() => {
                this.loadSessions();
                if (this.currentSessionId) {
                    this.pollMessages();
                }
            }, 3000);

            // Also send heartbeat to stay online
            setInterval(() => {
                $.ajax({
                    url: aiagentLiveAgent.restUrl + 'live-agent/heartbeat',
                    method: 'POST',
                    beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                });
            }, 60000); // Every minute
        },

        pollMessages: function () {
            $.ajax({
                url: aiagentLiveAgent.restUrl + 'live-agent/messages',
                method: 'GET',
                data: { live_session_id: this.currentSessionId, after_id: this.lastMessageId },
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', aiagentLiveAgent.nonce),
                success: (response) => {
                    if (response.messages && response.messages.length > 0) {
                        response.messages.forEach((msg) => {
                            // Only show user messages (agent messages already shown when sent)
                            if (msg.sender_type === 'user') {
                                const time = this.formatTime(msg.created_at);
                                const html = `
                                    <div class="aiagent-message user">
                                        <div class="aiagent-message-bubble">
                                            <span class="aiagent-message-sender">${aiagentLiveAgent.strings.visitor}</span>
                                            ${this.escapeHtml(msg.message)}
                                            <span class="aiagent-message-time">${time}</span>
                                        </div>
                                    </div>
                                `;
                                $('#chat-messages').append(html);
                                $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
                            }
                            this.lastMessageId = Math.max(this.lastMessageId, msg.id);
                        });
                    }
                },
            });
        },

        formatTime: function (dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';

            return date.toLocaleDateString();
        },

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    };

    // Initialize on document ready
    $(document).ready(function () {
        Dashboard.init();
    });
})(jQuery);
