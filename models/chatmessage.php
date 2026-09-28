<?php
// src/models/ChatMessage.php

namespace App\Models;

use PDO;

class ChatMessage
{
    /** @var PDO */
    private static ?PDO $pdo = null;

    private static function pdo(): PDO
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/../config/db.php';
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                self::$pdo = $GLOBALS['pdo'];
            } else {
                self::$pdo = \App\Config\get_pdo();
            }
        }
        return self::$pdo;
    }

    /**
     * Insert a new chat message.
     *
     * @param int $sessionId
     * @param int $userId
     * @param string $messageText
     * @return int New message ID.
     */
    public static function create(int $sessionId, int $userId, string $messageText): int
    {
        $stmt = self::pdo()->prepare("
            INSERT INTO chat_messages (session_id, user_id, message_text, created_at)
            VALUES (:session_id, :user_id, :message_text, NOW())
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':user_id' => $userId,
            ':message_text' => $messageText
        ]);
        return (int)self::pdo()->lastInsertId();
    }

    /**
     * Fetch messages for a session newer than a given ID.
     *
     * @param int $sessionId
     * @param int $lastId
     * @param int $limit
     * @return array[]
     */
    public static function getNewerThan(int $sessionId, int $lastId, int $limit = 100): array
    {
        $stmt = self::pdo()->prepare("
            SELECT cm.id, cm.user_id, u.username, cm.message_text, cm.created_at
            FROM chat_messages cm
            JOIN users u ON u.id = cm.user_id
            WHERE cm.session_id = :session_id AND cm.id > :last_id
            ORDER BY cm.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':last_id', $lastId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent messages for a session (latest N).
     *
     * @param int $sessionId
     * @param int $limit
     * @return array[]
     */
    public static function getRecent(int $sessionId, int $limit = 50): array
    {
        $stmt = self::pdo()->prepare("
            SELECT cm.id, cm.user_id, u.username, cm.message_text, cm.created_at
            FROM chat_messages cm
            JOIN users u ON u.id = cm.user_id
            WHERE cm.session_id = :session_id
            ORDER BY cm.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        // Return in ascending order for display
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_reverse($rows);
    }

    /**
     * Count total messages for a session.
     */
    public static function countForSession(int $sessionId): int
    {
        $stmt = self::pdo()->prepare("
            SELECT COUNT(*) AS total
            FROM chat_messages
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get last message ID for a session (useful for initializing last_id).
     */
    public static function getLastIdForSession(int $sessionId): int
    {
        $stmt = self::pdo()->prepare("
            SELECT MAX(id) AS last_id
            FROM chat_messages
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['last_id'] ?? 0);
    }
}