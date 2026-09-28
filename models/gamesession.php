<?php
// src/models/GameSession.php

namespace App\Models;

use PDO;
use DateTime;

class GameSession
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
     * Find session by ID.
     */
    public static function findById(int $id): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT id, team_id, room_id, started_at, ended_at, status, total_score, time_taken_seconds
            FROM game_sessions
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get or create an active session for a team & room.
     *
     * @param int $teamId
     * @param int $roomId
     * @param int $timeLimitSeconds
     * @return array Session row (active or newly created).
     */
    public static function getOrCreateActive(int $teamId, int $roomId, int $timeLimitSeconds): array
    {
        // Find latest session for this team+room
        $stmt = self::pdo()->prepare("
            SELECT id, team_id, room_id, started_at, ended_at, status, total_score, time_taken_seconds
            FROM game_sessions
            WHERE team_id = :team_id AND room_id = :room_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':team_id' => $teamId,
            ':room_id' => $roomId
        ]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = new DateTime();

        if (!$session || $session['status'] !== 'active') {
            // Create new session
            $insert = self::pdo()->prepare("
                INSERT INTO game_sessions (team_id, room_id, started_at, status)
                VALUES (:team_id, :room_id, NOW(), 'active')
            ");
            $insert->execute([
                ':team_id' => $teamId,
                ':room_id' => $roomId
            ]);
            $sessionId = (int)self::pdo()->lastInsertId();

            // Initialize puzzle progress for all puzzles in this room
            $puzzlesStmt = self::pdo()->prepare("
                SELECT id FROM puzzles
                WHERE room_id = :room_id
                ORDER BY order_in_room
            ");
            $puzzlesStmt->execute([':room_id' => $roomId]);
            $puzzleIds = $puzzlesStmt->fetchAll(PDO::FETCH_COLUMN);

            $progStmt = self::pdo()->prepare("
                INSERT INTO puzzle_progress (session_id, puzzle_id, is_solved, attempts_count, hints_used_count, penalty_points)
                VALUES (:session_id, :puzzle_id, 0, 0, 0, 0)
            ");
            foreach ($puzzleIds as $pid) {
                $progStmt->execute([
                    ':session_id' => $sessionId,
                    ':puzzle_id' => (int)$pid
                ]);
            }

            // Reload and return
            return self::findById($sessionId);
        }

        // Check if time is up for active session
        $startedAt = new DateTime($session['started_at']);
        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
        $timeRemaining = max(0, $timeLimitSeconds - $elapsed);

        if ($timeRemaining <= 0 && $session['status'] === 'active') {
            self::finalizeSession((int)$session['id'], $timeLimitSeconds);
            $session['status'] = 'completed';
        }

        return $session;
    }

    /**
     * Get current puzzle progress for a session.
     */
    public static function getProgressSummary(int $sessionId): array
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
     * Mark a puzzle as solved and add points.
     *
     * @param int $sessionId
     * @param int $puzzleId
     * @param int $pointsEarned Points after any penalties (if you compute that externally).
     */
    public static function markPuzzleSolved(int $sessionId, int $puzzleId, int $pointsEarned): void
    {
        // Mark solved
        $stmt = self::pdo()->prepare("
            UPDATE puzzle_progress
            SET is_solved = 1, solved_at = NOW()
            WHERE session_id = :session_id AND puzzle_id = :puzzle_id
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':puzzle_id' => $puzzleId
        ]);

        // Add points to session total
        $updScore = self::pdo()->prepare("
            UPDATE game_sessions
            SET total_score = total_score + :points
            WHERE id = :session_id
        ");
        $updScore->execute([
            ':points' => $pointsEarned,
            ':session_id' => $sessionId
        ]);

        // Check if all puzzles solved -> finalize
        $progress = self::getProgressSummary($sessionId);
        if ($progress['solved_count'] >= $progress['total_puzzles']) {
            $session = self::findById($sessionId);
            $timeTaken = $session ? (int)((new DateTime())->getTimestamp() - (new DateTime($session['started_at']))->getTimestamp()) : 0;
            self::finalizeSession($sessionId, $timeTaken);
        }
    }

    /**
     * Increment attempts for a puzzle.
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
     * Add penalty points for hints.
     */
    public static function addPenalty(int $sessionId, int $puzzleId, int $penalty): void
    {
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
     * Finalize session (time up or all puzzles solved).
     * Also updates leaderboard.
     */
    public static function finalizeSession(int $sessionId, int $timeTakenSeconds): void
    {
        $stmt = self::pdo()->prepare("
            UPDATE game_sessions
            SET status = 'completed', ended_at = NOW(), time_taken_seconds = :time_taken
            WHERE id = :session_id
        ");
        $stmt->execute([
            ':time_taken' => $timeTakenSeconds,
            ':session_id' => $sessionId
        ]);

        // Update leaderboard
        Leaderboard::upsertFromSession($sessionId);
    }

    /**
     * Get session status and time remaining.
     *
     * @param int $sessionId
     * @param int $timeLimitSeconds
     * @return array{game_over:bool,time_remaining:int,status:string}
     */
    public static function getStatusAndTimeRemaining(int $sessionId, int $timeLimitSeconds): array
    {
        $session = self::findById($sessionId);
        if (!$session) {
            return ['game_over' => true, 'time_remaining' => 0, 'status' => 'not_found'];
        }

        if ($session['status'] !== 'active') {
            return [
                'game_over' => true,
                'time_remaining' => 0,
                'status' => $session['status']
            ];
        }

        $startedAt = new DateTime($session['started_at']);
        $now = new DateTime();
        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
        $timeRemaining = max(0, $timeLimitSeconds - $elapsed);

        if ($timeRemaining <= 0) {
            self::finalizeSession($sessionId, $timeLimitSeconds);
            return ['game_over' => true, 'time_remaining' => 0, 'status' => 'completed'];
        }

        return [
            'game_over' => false,
            'time_remaining' => $timeRemaining,
            'status' => 'active'
        ];
    }
}