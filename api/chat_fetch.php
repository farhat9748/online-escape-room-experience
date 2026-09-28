<?php
// api/chat_fetch.php - Fetch new chat messages for a session

namespace App\Api;

use App\Models\GameSession;
use App\Models\Team;

require_once __DIR__ . '/../models/gamesession.php';
require_once __DIR__ . '/../models/team.php';
require_once __DIR__ . '/../config/db.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$lastId    = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing session_id.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

// Validate session and membership
$session = GameSession::findById($sessionId);
if (!$session) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session not found.']);
    exit;
}

if (!Team::isMember((int)$session['team_id'], $userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Fetch messages newer than lastId
require_once __DIR__ . '/../config/db.php';
$pdo = $GLOBALS['pdo'] ?? \App\Config\get_pdo();

$stmt = $pdo->prepare("
    SELECT cm.id, cm.user_id, u.username, cm.message_text, cm.created_at
    FROM chat_messages cm
    JOIN users u ON u.id = cm.user_id
    WHERE cm.session_id = :session_id AND cm.id > :last_id
    ORDER BY cm.id ASC
    LIMIT 100
");
$stmt->execute([
    ':session_id' => $sessionId,
    ':last_id' => $lastId
]);

$messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo json_encode(['messages' => $messages]);