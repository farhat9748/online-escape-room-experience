<?php
namespace App\Api;
use App\Models\GameSession;
use App\Models\Team;
require_once __DIR__ . '/../models/gamesession.php';
require_once __DIR__ . '/../models/team.php';
require_once __DIR__ . '/../models/chatmessage.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed.']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($input['session_id'] ?? 0);
$message = trim((string)($input['message'] ?? ''));
if ($sessionId <= 0 || $message === '' || strlen($message) > 500) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid message.']); exit; }
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Not authenticated.']); exit; }
$session = GameSession::findById($sessionId);
if (!$session || !Team::isMember((int)$session['team_id'], (int)$_SESSION['user_id'])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Access denied.']); exit; }
\App\Models\ChatMessage::create($sessionId, (int)$_SESSION['user_id'], $message);
echo json_encode(['success' => true]);
