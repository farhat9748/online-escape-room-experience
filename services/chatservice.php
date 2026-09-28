<?php
// src/services/ChatService.php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\GameSession;
use App\Models\Team;

class ChatService
{
    private const MAX_MESSAGE_LENGTH = 500;
    private const MIN_MESSAGE_INTERVAL_MS = 500; // 0.5s between messages
    private const MAX_MESSAGES_PER_MINUTE = 30;

    /**
     * Send a chat message for a session.
     *
     * @param int $sessionId
     * @param int $userId
     * @param string $messageText
     * @return array{success:bool,message_id?:int,error?:string}
     */
    public function sendMessage(int $sessionId, int $userId, string $messageText): array
    {
        // Basic validation
        $messageText = trim($messageText);
        if ($messageText === '') {
            return ['success' => false, 'error' => 'Message cannot be empty.'];
        }

        if (strlen($messageText) > self::MAX_MESSAGE_LENGTH) {
            return ['success' => false, 'error' => 'Message too long (max ' . self::MAX_MESSAGE_LENGTH . ' characters).'];
        }

        // Validate session and membership
        $session = GameSession::findById($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Session not found.'];
        }

        if (!Team::isMember((int)$session['team_id'], $userId)) {
            return ['success' => false, 'error' => 'Access denied.'];
        }

        // Optional: simple rate limiting via session-based tracking
        // (In production, use Redis or a dedicated rate limiter.)
        $lastKey = 'chat_last_time_' . $userId;
        $countKey = 'chat_count_' . $userId . '_' . date('YmdHi');

        $nowMs = (int)(microtime(true) * 1000);
        $lastTime = $_SESSION[$lastKey] ?? 0;
        if ($nowMs - $lastTime < self::MIN_MESSAGE_INTERVAL_MS) {
            return ['success' => false, 'error' => 'You are sending messages too quickly.'];
        }

        // Count messages in current minute (simple in-memory approach)
        $count = $_SESSION[$countKey] ?? 0;
        if ($count >= self::MAX_MESSAGES_PER_MINUTE) {
            return ['success' => false, 'error' => 'Message limit reached for this minute.'];
        }
        $_SESSION[$countKey] = $count + 1;
        $_SESSION[$lastKey] = $nowMs;

        // Sanitize message (basic HTML strip; adjust based on your policy)
        $cleanText = strip_tags($messageText);
        $cleanText = htmlspecialchars($cleanText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Insert message
        try {
            $messageId = ChatMessage::create($sessionId, $userId, $cleanText);
            return ['success' => true, 'message_id' => $messageId];
        } catch (\PDOException $e) {
            // Log error in production
            return ['success' => false, 'error' => 'Failed to send message.'];
        }
    }

    /**
     * Fetch new messages for a session since a given ID.
     *
     * @param int $sessionId
     * @param int $lastId
     * @param int $limit
     * @return array[]
     */
    public function fetchNewMessages(int $sessionId, int $lastId, int $limit = 100): array
    {
        return ChatMessage::getNewerThan($sessionId, $lastId, $limit);
    }

    /**
     * Get recent messages for initial load.
     *
     * @param int $sessionId
     * @param int $limit
     * @return array[]
     */
    public function getRecentMessages(int $sessionId, int $limit = 50): array
    {
        return ChatMessage::getRecent($sessionId, $limit);
    }

    /**
     * Get last message ID for a session (for initializing chat).
     */
    public function getLastMessageId(int $sessionId): int
    {
        return ChatMessage::getLastIdForSession($sessionId);
    }
}