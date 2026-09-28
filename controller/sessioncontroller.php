<?php
// src/controllers/SessionController.php

namespace App\Controllers;

use App\Models\GameSession;
use App\Models\Room;
use App\Models\Team;
use App\Models\PuzzleProgress;

class SessionController
{
    /**
     * Get session status and time remaining (JSON API).
     *
     * GET /api/session_status.php?session_id=123
     */
    public function getStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
            return;
        }

        session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated.']);
            return;
        }
        $userId = (int)$_SESSION['user_id'];

        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
        if ($sessionId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing or invalid session_id.']);
            return;
        }

        $session = GameSession::findById($sessionId);
        if (!$session) {
            echo json_encode([
                'game_over' => true,
                'time_remaining' => 0,
                'status' => 'not_found'
            ]);
            return;
        }

        // Verify membership
        if (!Team::isMember((int)$session['team_id'], $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied.']);
            return;
        }

        // Load room to get time limit
        $room = Room::findById((int)$session['room_id']);
        if (!$room) {
            echo json_encode([
                'game_over' => true,
                'time_remaining' => 0,
                'status' => 'room_not_found'
            ]);
            return;
        }

        $timeLimitSeconds = (int)$room['time_limit_seconds'];
        $statusInfo = GameSession::getStatusAndTimeRemaining($sessionId, $timeLimitSeconds);

        echo json_encode($statusInfo);
    }

    /**
     * Get session progress summary (JSON API).
     *
     * GET /api/session_progress.php?session_id=123
     */
    public function getProgress(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
            return;
        }

        session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated.']);
            return;
        }
        $userId = (int)$_SESSION['user_id'];

        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
        if ($sessionId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing or invalid session_id.']);
            return;
        }

        $session = GameSession::findById($sessionId);
        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found.']);
            return;
        }

        if (!Team::isMember((int)$session['team_id'], $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied.']);
            return;
        }

        $summary = PuzzleProgress::getSummary($sessionId);
        echo json_encode($summary);
    }

    /**
     * Finalize a session manually (admin/debug or edge-case handling).
     *
     * POST /api/session_finalize.php
     * Body: { "session_id": 123 }
     */
    public function finalize(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
            return;
        }

        session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON.']);
            return;
        }

        $sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
        if ($sessionId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing session_id.']);
            return;
        }

        $session = GameSession::findById($sessionId);
        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found.']);
            return;
        }

        // Optional: restrict to admins or team members only
        if (!Team::isMember((int)$session['team_id'], (int)$_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied.']);
            return;
        }

        if ($session['status'] !== 'active') {
            echo json_encode([
                'success' => false,
                'message' => 'Session is not active.'
            ]);
            return;
        }

        // Compute time taken so far
        $startedAt = new \DateTime($session['started_at']);
        $now = new \DateTime();
        $timeTaken = $now->getTimestamp() - $startedAt->getTimestamp();

        GameSession::finalizeSession($sessionId, $timeTaken);

        echo json_encode([
            'success' => true,
            'message' => 'Session finalized.',
            'time_taken_seconds' => $timeTaken
        ]);
    }
}