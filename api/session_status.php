<?php
// api/session_status.php - Get game session status and time remaining

namespace App\Api;

use App\Models\GameSession;
use App\Models\Team;
use App\Models\Room;

require_once __DIR__ . '/../models/gamesession.php';
require_once __DIR__ . '/../models/team.php';
require_once __DIR__ . '/../models/room.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

// Accept GET (for simplicity with query params)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid session_id.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

// Load session
$session = GameSession::findById($sessionId);
if (!$session) {
    echo json_encode([
        'game_over' => true,
        'time_remaining' => 0,
        'status' => 'not_found'
    ]);
    exit;
}

// Verify user is in the team for this session
if (!Team::isMember((int)$session['team_id'], $userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

// Load room to get time limit
$room = \App\Models\Room::findById((int)$session['room_id']);
if (!$room) {
    // Room missing; treat as game over
    echo json_encode([
        'game_over' => true,
        'time_remaining' => 0,
        'status' => 'room_not_found'
    ]);
    exit;
}

$timeLimitSeconds = (int)$room['time_limit_seconds'];

// Get status and time remaining
$statusInfo = GameSession::getStatusAndTimeRemaining($sessionId, $timeLimitSeconds);

echo json_encode($statusInfo);