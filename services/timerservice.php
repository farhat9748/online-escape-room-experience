<?php
// src/services/TimerService.php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Room;

class TimerService
{
    /**
     * Calculate time remaining for a session.
     *
     * @param array $session Session row with 'started_at' and 'room_id'.
     * @param int|null $timeLimitSeconds Optional override; if null, loads from room.
     * @return int Seconds remaining (0 if time is up).
     */
    public function getTimeRemaining(array $session, ?int $timeLimitSeconds = null): int
    {
        if (!isset($session['started_at'])) {
            return 0;
        }

        if ($timeLimitSeconds === null) {
            $room = Room::findById((int)$session['room_id']);
            if (!$room) {
                return 0;
            }
            $timeLimitSeconds = (int)$room['time_limit_seconds'];
        }

        $startedAt = new \DateTime($session['started_at']);
        $now = new \DateTime();
        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();

        return max(0, $timeLimitSeconds - $elapsed);
    }

    /**
     * Check if a session has expired (time is up).
     *
     * @param array $session
     * @param int|null $timeLimitSeconds
     * @return bool
     */
    public function isExpired(array $session, ?int $timeLimitSeconds = null): bool
    {
        return $this->getTimeRemaining($session, $timeLimitSeconds) <= 0;
    }

    /**
     * Finalize all active sessions that have expired.
     *
     * @param int $batchSize Max sessions to process in one run.
     * @return int Number of sessions finalized.
     */
    public function finalizeExpiredSessions(int $batchSize = 100): int
    {
        $pdo = \App\Config\get_pdo();

        // Fetch active sessions with room time limits
        $stmt = $pdo->prepare("
            SELECT gs.id, gs.team_id, gs.room_id, gs.started_at, gs.status,
                   r.time_limit_seconds
            FROM game_sessions gs
            JOIN rooms r ON r.id = gs.room_id
            WHERE gs.status = 'active'
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
        $stmt->execute();
        $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $gameEngine = new GameEngine();
        $finalizedCount = 0;

        foreach ($sessions as $session) {
            $timeLimit = (int)$session['time_limit_seconds'];
            if ($this->isExpired($session, $timeLimit)) {
                // Compute time taken
                $startedAt = new \DateTime($session['started_at']);
                $now = new \DateTime();
                $timeTaken = max(0, $now->getTimestamp() - $startedAt->getTimestamp());

                // Finalize via GameEngine (handles scoring + leaderboard)
                $gameEngine->finalizeSession((int)$session['id'], $timeLimit, 0);
                $finalizedCount++;
            }
        }

        return $finalizedCount;
    }

    /**
     * Get session status with time remaining (for API responses).
     *
     * @param int $sessionId
     * @return array{session_id:int,status:string,time_remaining:int,time_limit:int,started_at:string}
     */
    public function getSessionStatus(int $sessionId): array
    {
        $session = GameSession::findById($sessionId);
        if (!$session) {
            return [
                'session_id' => $sessionId,
                'status' => 'not_found',
                'time_remaining' => 0,
                'time_limit' => 0,
                'started_at' => ''
            ];
        }

        $room = Room::findById((int)$session['room_id']);
        $timeLimit = $room ? (int)$room['time_limit_seconds'] : 0;
        $timeRemaining = $this->getTimeRemaining($session, $timeLimit);

        $status = $session['status'];
        if ($status === 'active' && $timeRemaining <= 0) {
            $status = 'expired';
        }

        return [
            'session_id' => (int)$session['id'],
            'status' => $status,
            'time_remaining' => $timeRemaining,
            'time_limit' => $timeLimit,
            'started_at' => $session['started_at']
        ];
    }

    /**
     * Format seconds into human-readable string (e.g., "12m 34s").
     */
    public function formatTime(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }

        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($m > 0) {
            $parts[] = $m . 'm';
        }
        if ($s > 0 || empty($parts)) {
            $parts[] = $s . 's';
        }

        return implode(' ', $parts);
    }
}