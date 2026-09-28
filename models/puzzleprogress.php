<?php
// src/models/PuzzleProgress.php

namespace App\Models;

use PDO;

class PuzzleProgress
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
     * Get progress row for a session & puzzle.
     *
     * @param int $sessionId
     * @param int $puzzleId
     * @return array|null
     */
    public static function get(int $sessionId, int $puzzleId): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT
                id,
                session_id,
                puzzle_id,
                is_solved,
                solved_at,
                attempts_count,
                hints_used_count,
                penalty_points
            FROM puzzle_progress
            WHERE session_id = :session_id AND puzzle_id = :puzzle_id
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':puzzle_id' => $puzzleId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all progress rows for a session.
     *
     * @param int $sessionId
     * @return array[]
     */
    public static function getAllForSession(int $sessionId): array
    {
        $stmt = self::pdo()->prepare("
            SELECT
                id,
                session_id,
                puzzle_id,
                is_solved,
                solved_at,
                attempts_count,
                hints_used_count,
                penalty_points
            FROM puzzle_progress
            WHERE session_id = :session_id
            ORDER BY puzzle_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Increment attempts count for a puzzle in a session.
     */
    public static function incrementAttempts(int $sessionId, int $puzzleId): void
    {
        $stmt = self::pdo()->prepare("
            UPDATE puzzle_progress
            SET attempts_count = attempts_count + 1
            WHERE session_id = :session_id AND puzzle_id = :puzzle_id
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':puzzle_id' => $puzzleId
        ]);
    }

    /**
     * Increment hints_used_count for a puzzle in a session.
     */
    public static function incrementHintsUsed(int $sessionId, int $puzzleId): void
    {
        $stmt = self::pdo()->prepare("
            UPDATE puzzle_progress
            SET hints_used_count = hints_used_count + 1
            WHERE session_id = :session_id AND puzzle_id = :puzzle_id
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':puzzle_id' => $puzzleId
        ]);
    }

    /**
     * Add penalty points to a puzzle progress row.
     */
    public static function addPenalty(int $sessionId, int $puzzleId, int $penalty): void
    {
        if ($penalty <= 0) {
            return;
        }

        $stmt = self::pdo()->prepare("
            UPDATE puzzle_progress
            SET penalty_points = penalty_points + :penalty
            WHERE session_id = :session_id AND puzzle_id = :puzzle_id
        ");
        $stmt->execute([
            ':penalty' => $penalty,
            ':session_id' => $sessionId,
            ':puzzle_id' => $puzzleId
        ]);
    }

    /**
     * Mark puzzle as solved and set solved_at.
     */
    public static function markSolved(int $sessionId, int $puzzleId): void
    {
        $stmt = self::pdo()->prepare("
            UPDATE puzzle_progress
            SET is_solved = 1, solved_at = NOW()
            WHERE session_id = :session_id AND puzzle_id = :puzzle_id
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':puzzle_id' => $puzzleId
        ]);
    }

    /**
     * Get summary stats for a session: total puzzles, solved count, total penalty.
     *
     * @param int $sessionId
     * @return array{total_puzzles:int,solved_count:int,total_penalty:int}
     */
    public static function getSummary(int $sessionId): array
    {
        $stmt = self::pdo()->prepare("
            SELECT
                COUNT(*) AS total_puzzles,
                SUM(CASE WHEN is_solved = 1 THEN 1 ELSE 0 END) AS solved_count,
                SUM(penalty_points) AS total_penalty
            FROM puzzle_progress
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_puzzles' => (int)($row['total_puzzles'] ?? 0),
            'solved_count' => (int)($row['solved_count'] ?? 0),
            'total_penalty' => (int)($row['total_penalty'] ?? 0),
        ];
    }

    /**
     * Check if all puzzles are solved for a session.
     */
    public static function allSolved(int $sessionId): bool
    {
        $summary = self::getSummary($sessionId);
        return $summary['solved_count'] > 0 && $summary['solved_count'] >= $summary['total_puzzles'];
    }
}