<?php
// src/models/Room.php

namespace App\Models;

use PDO;

class Room
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
     * Get all active rooms, optionally filtered by difficulty.
     *
     * @param string|null $difficulty One of 'easy','medium','hard' or null.
     * @return array[] List of room rows.
     */
    public static function findAllActive(?string $difficulty = null): array
    {
        $sql = "
            SELECT id, title, slug, description, difficulty, time_limit_seconds
            FROM rooms
            WHERE is_active = 1
        ";
        $params = [];

        if ($difficulty !== null && in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $sql .= " AND difficulty = :difficulty";
            $params[':difficulty'] = $difficulty;
        }

        $sql .= " ORDER BY difficulty, title";

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find room by ID.
     *
     * @param int $id
     * @return array|null Room row or null if not found.
     */
    public static function findById(int $id): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT id, title, slug, description, difficulty, time_limit_seconds, is_active, created_at
            FROM rooms
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find room by slug.
     *
     * @param string $slug
     * @return array|null Room row or null if not found.
     */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT id, title, slug, description, difficulty, time_limit_seconds, is_active, created_at
            FROM rooms
            WHERE slug = :slug AND is_active = 1
        ");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get rooms with simple stats: number of puzzles and average points.
     *
     * @return array[]
     */
    public static function findAllWithStats(): array
    {
        $sql = "
            SELECT
                r.id,
                r.title,
                r.slug,
                r.difficulty,
                r.time_limit_seconds,
                COUNT(p.id) AS puzzle_count,
                COALESCE(AVG(p.points), 0) AS avg_points
            FROM rooms r
            LEFT JOIN puzzles p ON p.room_id = r.id
            WHERE r.is_active = 1
            GROUP BY r.id, r.title, r.slug, r.difficulty, r.time_limit_seconds
            ORDER BY r.difficulty, r.title
        ";

        $stmt = self::pdo()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get puzzles for a room, ordered by order_in_room.
     *
     * @param int $roomId
     * @param bool $includeAnswer Set to true only in admin contexts.
     * @return array[]
     */
    public static function getPuzzlesByRoom(int $roomId, bool $includeAnswer = false): array
    {
        $cols = $includeAnswer
            ? 'id, room_id, title, puzzle_type, question_text, answer_text, answer_case_sensitive, order_in_room, points, created_at'
            : 'id, room_id, title, puzzle_type, question_text, answer_case_sensitive, order_in_room, points, created_at';

        $stmt = self::pdo()->prepare("
            SELECT {$cols}
            FROM puzzles
            WHERE room_id = :room_id
            ORDER BY order_in_room
        ");
        $stmt->execute([':room_id' => $roomId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get basic leaderboard summary for a room (top 10 sessions).
     *
     * @param int $roomId
     * @return array[]
     */
    public static function getLeaderboardSummary(int $roomId): array
    {
        $stmt = self::pdo()->prepare("
            SELECT
                le.id AS entry_id,
                le.team_id,
                t.name AS team_name,
                le.total_score,
                le.time_taken_seconds,
                le.completed_at
            FROM leaderboard_entries le
            JOIN teams t ON t.id = le.team_id
            WHERE le.room_id = :room_id
            ORDER BY le.total_score DESC, le.time_taken_seconds ASC
            LIMIT 10
        ");
        $stmt->execute([':room_id' => $roomId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}