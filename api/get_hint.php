<?php
// api/get_hint.php - Get next hint for a puzzle

namespace App\Api;

use App\Models\GameSession;
use App\Models\Hint;
use App\Models\Team;

require_once __DIR__ . '/../models/gamesession.php';
require_once __DIR__ . '/../models/hint.php';
require_once __DIR__ . '/../models/team.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON.']);
    exit;
}

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$puzzleId  = isset($input['puzzle_id']) ? (int)$input['puzzle_id'] : 0;

if ($sessionId <= 0 || $puzzleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

$session = GameSession::findById($sessionId);
if (!$session) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session not found.']);
    exit;
}

if ($session['status'] !== 'active') {
    echo json_encode([
        'success' => false,
        'message' => 'This game session is over.',
        'game_over' => true
    ]);
    exit;
}

if (!Team::isMember((int)$session['team_id'], $userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Get next available hint
$hint = Hint::getNextForSession($sessionId, $puzzleId);
if (!$hint) {
    echo json_encode([
        'success' => false,
        'message' => 'No more hints available.',
    ]);
    exit;
}

// Grant hint (increment usage, apply penalty)
$granted = Hint::grantToSession($sessionId, $puzzleId, (int)$hint['id']);
if (!$granted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to grant hint.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Hint granted.',
    'hint' => $granted['hint_text'],
    'penalty' => (int)$granted['penalty_points'],
    'total_penalty' => (int)$granted['total_penalty']
]);