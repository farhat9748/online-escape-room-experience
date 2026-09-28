/**
 * chat.js - Team chat (AJAX polling)
 * Works with public/room.php
 */

(function () {
    'use strict';

    const ER = window.ER;
    if (!ER) {
        console.error('main.js not loaded; ER utilities missing.');
        return;
    }

    ER.ready(function () {
        const config = window.ESCAPE_ROOM_CONFIG || {};
        const sessionId = config.sessionId;
        if (!sessionId) {
            console.warn('No sessionId in ESCAPE_ROOM_CONFIG; chat disabled.');
            return;
        }

        const container = ER.$('.container');
        const chatMessagesEl = ER.$('#chat-messages');
        const chatForm = ER.$('#chat-form');
        const chatInput = ER.$('#chat-input');

        if (!chatMessagesEl || !chatForm || !chatInput) {
            console.warn('Chat elements not found; chat disabled.');
            return;
        }

        let lastMsgId = typeof config.initialLastMsgId === 'number' ? config.initialLastMsgId : 0;
        let pollIntervalId = null;
        let isGameOver = !!config.gameOver;

        // -------------------------
        // Helpers
        // -------------------------

        function isNearBottom(element, threshold = 50) {
            const scrollBottom = element.scrollTop + element.clientHeight;
            const diff = element.scrollHeight - scrollBottom;
            return diff <= threshold;
        }

        function scrollToBottom(element) {
            element.scrollTop = element.scrollHeight;
        }

        function appendMessages(messages) {
            if (!messages || messages.length === 0) return;

            const wasNearBottom = isNearBottom(chatMessagesEl);

            messages.forEach(function (msg) {
                const row = document.createElement('div');
                row.className = 'chat-message';

                const meta = document.createElement('span');
                meta.className = 'chat-meta';
                // Format: [username] time:
                const timeStr = msg.created_at ? new Date(msg.created_at).toLocaleTimeString() : '';
                const username = msg.username || 'User';
                meta.textContent = '[' + username + '] ' + timeStr + ' ';

                const text = document.createElement('span');
                text.textContent = msg.message_text || msg.message || '';

                row.appendChild(meta);
                row.appendChild(text);
                chatMessagesEl.appendChild(row);
            });

            // Auto-scroll only if user was near bottom
            if (wasNearBottom) {
                scrollToBottom(chatMessagesEl);
            }
        }

        function fetchMessages() {
            if (isGameOver) {
                stopPolling();
                return;
            }

            ER.get('../api/chat_fetch.php?session_id=' + encodeURIComponent(sessionId) + '&last_id=' + encodeURIComponent(lastMsgId))
                .then(function (data) {
                    // Expected: { messages: [ { id, user_id, username, message_text, created_at }, ... ] }
                    const messages = (data && Array.isArray(data.messages)) ? data.messages : [];
                    if (messages.length > 0) {
                        appendMessages(messages);
                        const last = messages[messages.length - 1];
                        if (last && typeof last.id === 'number') {
                            lastMsgId = last.id;
                        }
                    }
                })
                .catch(function (err) {
                    // Silently ignore polling errors to avoid spamming alerts
                    console.warn('Chat fetch error:', err);
                });
        }

        function startPolling() {
            if (pollIntervalId) return;
            // Initial fetch
            fetchMessages();
            // Then poll every 3 seconds
            pollIntervalId = setInterval(fetchMessages, 3000);
        }

        function stopPolling() {
            if (!pollIntervalId) return;
            clearInterval(pollIntervalId);
            pollIntervalId = null;
        }

        // -------------------------
        // Send message
        // -------------------------

        chatForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (isGameOver) {
                ER.showAlert('Chat is disabled; the game is over.', 'error', container);
                return;
            }

            const text = chatInput.value.trim();
            if (!text) {
                ER.showAlert('Message cannot be empty.', 'error', container);
                return;
            }

            const sendBtn = ER.$('button[type="submit"]', chatForm);
            if (sendBtn) sendBtn.disabled = true;

            ER.post('../api/chat_send.php', {
                session_id: sessionId,
                message: text
            })
            .then(function (data) {
                // Expected: { success: true }
                if (data && data.success) {
                    chatInput.value = '';
                    // Immediately fetch to show the new message
                    fetchMessages();
                } else {
                    const msg = (data && data.message) ? data.message : 'Failed to send message.';
                    ER.showAlert(msg, 'error', container);
                }
            })
            .catch(function (err) {
                const msg = err && err.message ? err.message : 'Failed to send message.';
                ER.showAlert(msg, 'error', container);
            })
            .finally(function () {
                if (sendBtn) sendBtn.disabled = false;
            });
        });

        // -------------------------
        // Init
        // -------------------------

        // Start polling
        startPolling();

        // Stop polling if game ends (config updated by timer.js)
        const originalConfig = window.ESCAPE_ROOM_CONFIG;
        Object.defineProperty(window, 'ESCAPE_ROOM_CONFIG', {
            get: function () {
                return originalConfig;
            },
            set: function (value) {
                if (value && value.gameOver) {
                    isGameOver = true;
                    stopPolling();
                    chatInput.disabled = true;
                    const sendBtn = ER.$('button[type="submit"]', chatForm);
                    if (sendBtn) sendBtn.disabled = true;
                }
                // Update original config reference if needed
                Object.assign(originalConfig, value);
            }
        });
    });
})();