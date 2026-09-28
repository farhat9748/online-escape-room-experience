<?php
// src/controllers/LeaderboardController.php

namespace App\Controllers;

use App\Models\Leaderboard;
use App\Models\User;

class LeaderboardController
{
    /**
     * Show leaderboard page (HTML).
     *
     * GET /leaderboard.php?room=123&difficulty=medium&page=2
     */
    public function show(): void
    {
        // Optional: require login or allow public view
        // if (!is_logged_in()) { redirect('login.php?redirect=leaderboard.php'); }

        $roomId = isset($_GET['room']) ? (int)$_GET['room'] : null;
        $difficulty = isset($_GET['difficulty']) && in_array($_GET['difficulty'], ['easy','medium','hard'], true)
            ? $_GET['difficulty']
            : null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $entries = Leaderboard::getPaginated($roomId, $difficulty, $limit, $offset);
        $total = Leaderboard::countTotal($roomId, $difficulty);
        $totalPages = max(1, (int)ceil($total / $limit));

        // User's best entry (if logged in)
        $userBest = null;
        if (is_logged_in()) {
            $userId = (int)$_SESSION['user_id'];
            $userBest = Leaderboard::getUserBest($userId);
        }

        // Rooms list for filter dropdown
        $rooms = \App\Models\Room::findAllActive();

        // Expose to view
        $GLOBALS['lb_entries'] = $entries;
        $GLOBALS['lb_total_pages'] = $totalPages;
        $GLOBALS['lb_current_page'] = $page;
        $GLOBALS['lb_room_filter'] = $roomId;
        $GLOBALS['lb_difficulty_filter'] = $difficulty;
        $GLOBALS['lb_user_best'] = $userBest;
        $GLOBALS['lb_rooms'] = $rooms;

        require_once __DIR__ . '/../../public/leaderboard.php';
    }

    /**
     * Get paginated leaderboard as JSON (API).
     *
     * GET /api/leaderboard.php?page=1&room=123&difficulty=medium
     */
    public function indexJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $roomId = isset($_GET['room']) ? (int)$_GET['room'] : null;
        $difficulty = isset($_GET['difficulty']) && in_array($_GET['difficulty'], ['easy','medium','hard'], true)
            ? $_GET['difficulty']
            : null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $entries = Leaderboard::getPaginated($roomId, $difficulty, $limit, $offset);
        $total = Leaderboard::countTotal($roomId, $difficulty);
        $totalPages = max(1, (int)ceil($total / $limit));

        echo json_encode([
            'entries' => $entries,
            'pagination' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ]);
    }

    /**
     * Get current user's best leaderboard entry (JSON API).
     *
     * GET /api/leaderboard_me.php
     */
    public function myBest(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated.']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        $best = Leaderboard::getUserBest($userId);

        if (!$best) {
            echo json_encode(['best' => null]);
        } else {
            echo json_encode(['best' => $best]);
        }
    }

    /**
     * Get top entries for a specific room (JSON API).
     *
     * GET /api/leaderboard_room.php?room_id=123&limit=10
     */
    public function topForRoom(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;

        if ($roomId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid room_id.']);
            return;
        }

        $top = \App\Models\Leaderboard::getTopForRoom($roomId, $limit);
        echo json_encode(['top' => $top]);
    }
}