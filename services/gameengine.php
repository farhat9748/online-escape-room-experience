<?php
// src/services/GameEngine.php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Puzzle;
use App\Models\Hint;
use App\Models\PuzzleProgress;
use App\Models\Leaderboard;
use App\Models\Room;

class GameEngine
{
    /**
     * Calculate final score for a session.
     *
     * Formula example:
     *   score = sum(puzzle points) - sum(hint penalties) + time_bonus
     *   time_bonus = max(0, time_limit - time_taken) * time_bonus_per_second
     *
     * @param int $sessionId
     * @param int $timeLimitSeconds
     * @param int $timeTakenSeconds
     * @param int $timeBonusPerSecond
     * @return int
     */
    public function calculateFinalScore(
        int $sessionId,
        int $timeLimitSeconds,
        int $timeTakenSeconds,
        int $timeBonusPerSecond = 0
    ): int {
        // Sum of base points for solved puzzles
        $stmt = \App\Config\get_pdo()->prepare("
            SELECT SUM(p.points) AS base_points
            FROM puzzle_progress pp
            JOIN puzzles p ON p.id = pp.puzzle_id
            WHERE pp.session_id = :session_id AND pp.is_solved = 1
        ");
        $stmt->execute([':session_id' => $sessionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $basePoints = (int)($row['base_points'] ?? 0);

        // Sum of penalties (hints)
        $penalty = PuzzleProgress::getSummary($sessionId)['total_penalty'];

        // Time bonus (optional)
        $timeBonus = 0;
        if ($timeBonusPerSecond > 0) {
            $remaining = max(0, $timeLimitSeconds - $timeTakenSeconds);
            $timeBonus = $remaining * $timeBonusPerSecond;
        }

        return max(0, $basePoints - $penalty + $timeBonus);
    }

    /**
     * Grant a hint to a session and apply penalty.
     *
     * @param int $sessionId
     * @param int $puzzleId
     * @return array|null Granted hint row with penalty info, or null if no hints left.
     */
    public function grantHint(int $sessionId, int $puzzleId): ?array
    {
        $hint = Hint::getNextForSession($sessionId, $puzzleId);
        if (!$hint) {
            return null;
        }

        $granted = Hint::grantToSession($sessionId, $puzzleId, (int)$hint['id']);
        if (!$granted) {
            return null;
        }

        // Also update session penalty via PuzzleProgress (already done in Hint::grantToSession)
        return $granted;
    }

    /**
     * Mark a puzzle as solved and update session score.
     *
     * @param int $sessionId
     * @param int $puzzleId
     * @return bool True if solved successfully.
     */
    public function solvePuzzle(int $sessionId, int $puzzleId): bool
    {
        $puzzle = Puzzle::findById($puzzleId, true);
        if (!$puzzle) {
            return false;
        }

        // Verify it's unsolved in this session
        $progress = PuzzleProgress::get($sessionId, $puzzleId);
        if (!$progress || $progress['is_solved']) {
            return false;
        }

        // Award base points (penalties already tracked separately)
        $pointsEarned = (int)$puzzle['points'];
        GameSession::markPuzzleSolved($sessionId, $puzzleId, $pointsEarned);

        return true;
    }

    /**
     * Finalize a session: compute final score, update session, update leaderboard.
     *
     * @param int $sessionId
     * @param int $timeLimitSeconds
     * @param int $timeBonusPerSecond
     * @return array Session data after finalization.
     */
    public function finalizeSession(int $sessionId, int $timeLimitSeconds, int $timeBonusPerSecond = 0): array
    {
        $session = GameSession::findById($sessionId);
        if (!$session) {
            throw new \RuntimeException('Session not found.');
        }

        if ($session['status'] !== 'active') {
            // Already finalized; just return current state
            return $session;
        }

        $timeTaken = (int)$session['time_taken_seconds'];
        if ($timeTaken <= 0) {
            // Compute from started_at if not set
            $startedAt = new \DateTime($session['started_at']);
            $now = new \DateTime();
            $timeTaken = max(0, $now->getTimestamp() - $startedAt->getTimestamp());
        }

        $finalScore = $this->calculateFinalScore($sessionId, $timeLimitSeconds, $timeTaken, $timeBonusPerSecond);

        // Update session total_score and finalize
        $pdo = \App\Config\get_pdo();
        $upd = $pdo->prepare("
            UPDATE game_sessions
            SET total_score = :total_score, status = 'completed', ended_at = NOW()
            WHERE id = :session_id
        ");
        $upd->execute([
            ':total_score' => $finalScore,
            ':session_id' => $sessionId
        ]);

        // Update leaderboard
        Leaderboard::upsertFromSession($sessionId);

        // Reload session
        return GameSession::findById($sessionId);
    }

    /**
     * Check if all puzzles are solved for a session.
     */
    public function allPuzzlesSolved(int $sessionId): bool
    {
        return PuzzleProgress::allSolved($sessionId);
    }

    /**
     * Get remaining unsolved puzzle count for a session.
     */
    public function remainingPuzzlesCount(int $sessionId): int
    {
        $summary = PuzzleProgress::getSummary($sessionId);
        return max(0, $summary['total_puzzles'] - $summary['solved_count']);
    }
}