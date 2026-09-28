<?php
// public/room.php - Main game page (puzzle UI, timer, chat)

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/function.php';
require_once __DIR__ . '/../controller/roomcontroller.php';
require_once __DIR__ . '/../models/room.php';
require_once __DIR__ . '/../models/team.php';
require_once __DIR__ . '/../models/gamesession.php';
require_once __DIR__ . '/../models/puzzle.php';
require_once __DIR__ . '/../models/puzzleprogress.php';
require_once __DIR__ . '/../models/chatmessage.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];

// Get team_id from query
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
if ($team_id <= 0) {
    die('Invalid team.');
}

// Load team and ensure user is a member
$team_stmt = $pdo->prepare("
    SELECT t.id AS team_id, t.name AS team_name, t.room_id, r.title AS room_title,
           r.time_limit_seconds, r.difficulty
    FROM teams t
    JOIN rooms r ON r.id = t.room_id
    WHERE t.id = :team_id
");
$team_stmt->execute([':team_id' => $team_id]);
$team = $team_stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    die('Team not found.');
}

// Check membership
$member_stmt = $pdo->prepare("
    SELECT id FROM team_members
    WHERE team_id = :team_id AND user_id = :user_id
");
$member_stmt->execute([
    ':team_id' => $team_id,
    ':user_id' => $current_user_id
]);
if (!$member_stmt->fetch()) {
    die('You are not a member of this team.');
}

$room_id = (int)$team['room_id'];
$time_limit_seconds = (int)$team['time_limit_seconds'];

// Find or create a game session for this team & room
$session_stmt = $pdo->prepare("
    SELECT id, started_at, ended_at, status, total_score, time_taken_seconds
    FROM game_sessions
    WHERE team_id = :team_id AND room_id = :room_id
    ORDER BY id DESC
    LIMIT 1
");
$session_stmt->execute([
    ':team_id' => $team_id,
    ':room_id' => $room_id
]);
$session = $session_stmt->fetch(PDO::FETCH_ASSOC);

$now = new DateTime();

if (!$session || $session['status'] !== 'active') {
    // Create new session if none or not active
    $insert_stmt = $pdo->prepare("
        INSERT INTO game_sessions (team_id, room_id, started_at, status)
        VALUES (:team_id, :room_id, NOW(), 'active')
    ");
    $insert_stmt->execute([
        ':team_id' => $team_id,
        ':room_id' => $room_id
    ]);
    $session_id = (int)$pdo->lastInsertId();

    // Initialize puzzle progress for all puzzles in this room
    $puzzles_stmt = $pdo->prepare("SELECT id FROM puzzles WHERE room_id = :room_id ORDER BY order_in_room");
    $puzzles_stmt->execute([':room_id' => $room_id]);
    $puzzle_ids = $puzzles_stmt->fetchAll(PDO::FETCH_COLUMN);

    $prog_stmt = $pdo->prepare("
        INSERT INTO puzzle_progress (session_id, puzzle_id, is_solved, attempts_count, hints_used_count, penalty_points)
        VALUES (:session_id, :puzzle_id, 0, 0, 0, 0)
    ");
    foreach ($puzzle_ids as $pid) {
        $prog_stmt->execute([
            ':session_id' => $session_id,
            ':puzzle_id' => (int)$pid
        ]);
    }

    // Reload session
    $session_stmt->execute([
        ':team_id' => $team_id,
        ':room_id' => $room_id
    ]);
    $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
    $session_id = (int)$session['id'];
    $started_at = new DateTime($session['started_at']);
} else {
    $session_id = (int)$session['id'];
    $started_at = new DateTime($session['started_at']);
}

// Compute time remaining (server-side reference)
$elapsed = $now->getTimestamp() - $started_at->getTimestamp();
$time_remaining = max(0, $time_limit_seconds - $elapsed);

// If time is up and session still active, end it
if ($time_remaining <= 0 && $session['status'] === 'active') {
    $update_stmt = $pdo->prepare("
        UPDATE game_sessions
        SET status = 'completed', ended_at = NOW(), time_taken_seconds = :time_taken
        WHERE id = :session_id
    ");
    $update_stmt->execute([
        ':time_taken' => $time_limit_seconds,
        ':session_id' => $session_id
    ]);
    $session['status'] = 'completed';
    $time_remaining = 0;

    // Optionally finalize leaderboard entry here or in a separate job
}

// Load current unsolved puzzle for this session
$puzzle_stmt = $pdo->prepare("
    SELECT p.id, p.title, p.puzzle_type, p.question_text, p.order_in_room, p.points
    FROM puzzles p
    JOIN puzzle_progress pp ON pp.puzzle_id = p.id
    WHERE pp.session_id = :session_id AND pp.is_solved = 0
    ORDER BY p.order_in_room
    LIMIT 1
");
$puzzle_stmt->execute([':session_id' => $session_id]);
$current_puzzle = $puzzle_stmt->fetch(PDO::FETCH_ASSOC);

// Load progress summary
$progress_stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_puzzles,
        SUM(CASE WHEN is_solved = 1 THEN 1 ELSE 0 END) AS solved_count,
        SUM(penalty_points) AS total_penalty
    FROM puzzle_progress
    WHERE session_id = :session_id
");
$progress_stmt->execute([':session_id' => $session_id]);
$progress = $progress_stmt->fetch(PDO::FETCH_ASSOC);

// For chat: get last message id initially
$last_msg_stmt = $pdo->prepare("
    SELECT MAX(id) AS last_id FROM chat_messages WHERE session_id = :session_id
");
$last_msg_stmt->execute([':session_id' => $session_id]);
$last_msg_row = $last_msg_stmt->fetch(PDO::FETCH_ASSOC);
$initial_last_id = (int)($last_msg_row['last_id'] ?? 0);

// If session completed and no current puzzle, show end screen
$game_over = ($session['status'] !== 'active' || $time_remaining <= 0 || !$current_puzzle);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($team['room_title']) ?> - Escape Room</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
<header>
    <div class="container">
        <h1><?= htmlspecialchars($team['room_title']) ?></h1>
        <div class="user-info">
            Team: <?= htmlspecialchars($team['team_name']) ?> |
            Player: <?= htmlspecialchars($current_username) ?> |
            <a href="index.php">Lobby</a>
        </div>
    </div>
</header>

<main class="container">
    <section class="game-status">
        <div class="status-item">
            Time remaining: <strong id="timer"><?= $time_remaining ?></strong> seconds
        </div>
        <div class="status-item">
            Puzzles solved: <strong id="solved-count"><?= (int)$progress['solved_count'] ?>/<?= (int)$progress['total_puzzles'] ?></strong>
        </div>
        <div class="status-item">
            Total penalty: <strong id="penalty-points"><?= (int)$progress['total_penalty'] ?></strong>
        </div>
        <?php if ($game_over): ?>
            <div class="status-item game-over">
                Game over. <a href="index.php">Back to lobby</a>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$game_over && $current_puzzle): ?>
        <section class="puzzle-section" data-session-id="<?= $session_id ?>" data-puzzle-id="<?= (int)$current_puzzle['id'] ?>">
            <h2>Puzzle #<?= (int)$current_puzzle['order_in_room'] ?>: <?= htmlspecialchars($current_puzzle['title'] ?? 'Untitled') ?></h2>
            <p class="puzzle-meta">
                Type: <strong><?= ucfirst(htmlspecialchars($current_puzzle['puzzle_type'])) ?></strong> |
                Points: <strong><?= (int)$current_puzzle['points'] ?></strong>
            </p>
            <div class="puzzle-question">
                <?= nl2br(htmlspecialchars($current_puzzle['question_text'])) ?>
            </div>

            <div class="answer-form">
                <h3>Submit your answer</h3>
                <form id="answer-form">
                    <label>
                        Answer:
                        <input type="text" id="answer-input" name="answer" required maxlength="255">
                    </label>
                    <button type="submit">Submit</button>
                </form>
                <div id="answer-result" class="result-box"></div>
            </div>

            <div class="hints-section">
                <h3>Hints</h3>
                <button id="get-hint-btn">Get a hint</button>
                <div id="hints-list"></div>
                <div id="hint-result" class="result-box"></div>
            </div>
        </section>
    <?php elseif (!$game_over): ?>
        <section class="puzzle-section">
            <h3>All puzzles solved!</h3>
            <p>Congratulations! Your session will be finalized shortly.</p>
        </section>
    <?php endif; ?>

    <section class="chat-section">
        <h3>Team Chat</h3>
        <div id="chat-messages" class="chat-messages"></div>
        <form id="chat-form" class="chat-form">
            <label>
                Message:
                <input type="text" id="chat-input" name="message" required maxlength="500">
            </label>
            <button type="submit">Send</button>
        </form>
    </section>
</main>

<footer class="container">
    <p>&copy; <?= date('Y') ?> Escape Room Platform</p>
</footer>

<script>
// Pass some initial data to JS
window.ESCAPE_ROOM_CONFIG = {
    sessionId: <?= $session_id ?>,
    initialTimeRemaining: <?= $time_remaining ?>,
    initialLastMsgId: <?= $initial_last_id ?>,
    gameOver: <?= $game_over ? 'true' : 'false' ?>
};
</script>
<script src="../js/main.js"></script>
<script src="../js/timer.js"></script>
<script src="../js/game.js"></script>
<script src="../js/chat.js"></script>
</body>
</html>