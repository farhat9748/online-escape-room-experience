<?php
// src/controllers/RoomController.php

namespace App\Controllers;

use App\Models\Room;
use App\Models\Team;
use App\Models\GameSession;
use App\Models\PuzzleProgress;
use App\Models\ChatMessage;

class RoomController
{
    /**
     * Show lobby (room selection, team create/join).
     */
    public function lobby(): void
    {
        if (!is_logged_in()) {
            redirect('login.php?redirect=index.php');
        }

        $userId = (int)$_SESSION['user_id'];

        // Load active rooms
        $rooms = Room::findAllActive();

        // For each room, load teams and user's existing team (if any)
        $roomsData = [];
        foreach ($rooms as $room) {
            $roomId = (int)$room['id'];

            $teams = Team::getTeamsForRoom($roomId);
            $userTeam = Team::getUserTeamForRoom($userId, $roomId);

            $roomsData[] = [
                'room' => $room,
                'teams' => $teams,
                'user_team_for_room' => $userTeam,
            ];
        }

        // Handle form submissions (create/join team)
        $message = '';
        $messageType = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'create_team') {
                $roomId = (int)($_POST['room_id'] ?? 0);
                $teamName = trim($_POST['team_name'] ?? '');

                if ($roomId <= 0 || $teamName === '') {
                    $message = 'Invalid input.';
                    $messageType = 'error';
                } else {
                    $room = Room::findById($roomId);
                    if (!$room || !$room['is_active']) {
                        $message = 'Room not found or inactive.';
                        $messageType = 'error';
                    } else {
                        // Check if user already in a team for this room
                        $existing = Team::getUserTeamForRoom($userId, $roomId);
                        if ($existing) {
                            $message = 'You are already in a team for this room.';
                            $messageType = 'error';
                        } else {
                            $teamId = Team::create($teamName, $roomId);
                            Team::addMember($teamId, $userId);
                            redirect('room.php?team_id=' . $teamId);
                        }
                    }
                }
            } elseif ($action === 'join_team') {
                $teamId = (int)($_POST['team_id'] ?? 0);

                if ($teamId <= 0) {
                    $message = 'Invalid team.';
                    $messageType = 'error';
                } else {
                    $team = Team::findById($teamId);
                    if (!$team) {
                        $message = 'Team not found.';
                        $messageType = 'error';
                    } else {
                        $roomId = (int)$team['room_id'];
                        $existing = Team::getUserTeamForRoom($userId, $roomId);
                        if ($existing) {
                            $message = 'You are already in a team for this room.';
                            $messageType = 'error';
                        } else {
                            Team::addMember($teamId, $userId);
                            redirect('room.php?team_id=' . $teamId);
                        }
                    }
                }
            }
        }

        // Pass data to view (pages/lobby.php)
        $GLOBALS['lobby_rooms'] = $roomsData;
        $GLOBALS['lobby_message'] = $message;
        $GLOBALS['lobby_message_type'] = $messageType;

        require_once __DIR__ . '/../../pages/lobby.php';
    }

    /**
     * Show game room page for a team.
     */
    public function showRoom(): void
    {
        if (!is_logged_in()) {
            redirect('login.php?redirect=room.php');
        }

        $userId = (int)$_SESSION['user_id'];
        $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

        if ($teamId <= 0) {
            http_response_code(400);
            exit('Invalid team.');
        }

        $team = Team::findById($teamId);
        if (!$team) {
            http_response_code(404);
            exit('Team not found.');
        }

        // Ensure user is a member
        if (!Team::isMember($teamId, $userId)) {
            http_response_code(403);
            exit('You are not a member of this team.');
        }

        $roomId = (int)$team['room_id'];
        $room = Room::findById($roomId);
        if (!$room) {
            http_response_code(404);
            exit('Room not found.');
        }

        $timeLimitSeconds = (int)$room['time_limit_seconds'];

        // Get or create active session
        $session = GameSession::getOrCreateActive($teamId, $roomId, $timeLimitSeconds);
        $sessionId = (int)$session['id'];

        // Compute time remaining (server-side reference)
        $startedAt = new \DateTime($session['started_at']);
        $now = new \DateTime();
        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
        $timeRemaining = max(0, $timeLimitSeconds - $elapsed);

        // If time up and still active, finalize
        if ($timeRemaining <= 0 && $session['status'] === 'active') {
            GameSession::finalizeSession($sessionId, $timeLimitSeconds);
            $session['status'] = 'completed';
        }

        // Load current unsolved puzzle
        $currentPuzzle = \App\Models\Puzzle::getNextUnsolvedForSession($sessionId);

        // Progress summary
        $progress = PuzzleProgress::getSummary($sessionId);

        // Initial chat last message ID
        $initialLastId = ChatMessage::getLastIdForSession($sessionId);

        $gameOver = ($session['status'] !== 'active' || $timeRemaining <= 0 || !$currentPuzzle);

        // Expose to view (room.php)
        $GLOBALS['room_team'] = $team;
        $GLOBALS['room_room'] = $room;
        $GLOBALS['room_session'] = $session;
        $GLOBALS['room_session_id'] = $sessionId;
        $GLOBALS['room_time_remaining'] = $timeRemaining;
        $GLOBALS['room_current_puzzle'] = $currentPuzzle;
        $GLOBALS['room_progress'] = $progress;
        $GLOBALS['room_initial_last_msg_id'] = $initialLastId;
        $GLOBALS['room_game_over'] = $gameOver;

        require_once __DIR__ . '/../../public/room.php';
    }
}