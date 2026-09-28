<?php
// api/submit_answer.php - Submit puzzle answer

namespace App\Api;

use App\Models\GameSession;
use App\Models\Puzzle;
use App\Models\Team;

require_once __DIR__ . '/../models/gamesession.php';
require_once __DIR__ . '/../models/puzzle.php';
require_once __DIR__ . '/../models/team.php';

// Start session to access user info
session_start();

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON.']);
    exit;
}

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$puzzleId  = isset($input['puzzle_id']) ? (int)$input['puzzle_id'] : 0;
$answer    = isset($input['answer']) ? trim((string)$input['answer']) : '';

if ($sessionId <= 0 || $puzzleId <= 0 || $answer === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

// Load session and validate membership
$session = GameSession::findById($sessionId);
if (!$session) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session not found.']);
    exit;
}

// Ensure session is active
if ($session['status'] !== 'active') {
    echo json_encode([
        'success' => false,
        'message' => 'This game session is over.',
        'game_over' => true
    ]);
    exit;
}

// Verify user is in the team for this session
if (!Team::isMember((int)$session['team_id'], $userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Load puzzle (with answer for verification)
$puzzle = Puzzle::findById($puzzleId, true);
if (!$puzzle || (int)$puzzle['room_id'] !== (int)$session['room_id']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Puzzle not found.']);
    exit;
}

// Increment attempt count
GameSession::incrementAttempts($sessionId, $puzzleId);

// Verify answer
$isCorrect = Puzzle::verifyAnswer($puzzleId, $answer);

if ($isCorrect) {
    // Compute points: base points minus any existing penalty for this puzzle
    // (penalties already applied via hints; we just award the puzzle's base points)
    $pointsEarned = (int)$puzzle['points'];

    // Mark solved and add points
    GameSession::markPuzzleSolved($sessionId, $puzzleId, $pointsEarned);

    echo json_encode([
        'success' => true,
        'message' => 'Correct answer!',
        'solved' => true,
        'points_earned' => $pointsEarned
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect answer. Try again.',
        'solved' => false
    ]);
}