<?php
// public/leaderboard.php - Leaderboard view

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/function.php';
require_once __DIR__ . '/../controller/leaderboard.php';
require_once __DIR__ . '/../models/leaderboard.php';
require_once __DIR__ . '/../models/room.php';

// Optional: ensure logged in; if you want public leaderboard, remove this check
// if (!is_logged_in()) {
//     redirect('login.php');
// }

$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Filters from query string
$room_filter   = isset($_GET['room']) ? (int)$_GET['room'] : null;
$diff_filter   = isset($_GET['difficulty']) && in_array($_GET['difficulty'], ['easy','medium','hard'])
                 ? $_GET['difficulty']
                 : null;
$limit         = 50; // number of rows to show
$page          = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset        = ($page - 1) * $limit;

// Build base query for leaderboard entries
// Prefer leaderboard_entries if populated; otherwise compute from game_sessions
// Here we assume leaderboard_entries is maintained when sessions complete.

$count_sql = "
    SELECT COUNT(*) AS total
    FROM leaderboard_entries le
    JOIN rooms r ON r.id = le.room_id
    WHERE 1=1
";
$count_params = [];

$main_sql = "
    SELECT
        le.id AS entry_id,
        le.team_id,
        le.room_id,
        r.title AS room_title,
        r.difficulty,
        t.name AS team_name,
        le.total_score,
        le.time_taken_seconds,
        le.completed_at,
        RANK() OVER (
            ORDER BY le.total_score DESC, le.time_taken_seconds ASC
        ) AS global_rank
    FROM leaderboard_entries le
    JOIN teams t ON t.id = le.team_id
    JOIN rooms r ON r.id = le.room_id
    WHERE 1=1
";
$params = [];

if ($room_filter !== null) {
    $count_sql .= " AND le.room_id = :room_id";
    $main_sql  .= " AND le.room_id = :room_id";
    $params[':room_id'] = $room_filter;
}

if ($diff_filter !== null) {
    $count_sql .= " AND r.difficulty = :difficulty";
    $main_sql  .= " AND r.difficulty = :difficulty";
    $params[':difficulty'] = $diff_filter;
}

// Total count for pagination
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = max(1, (int)ceil($total_rows / $limit));

// Main leaderboard query with pagination
$main_sql .= "
    ORDER BY le.total_score DESC, le.time_taken_seconds ASC
    LIMIT :limit OFFSET :offset
";

$main_stmt = $pdo->prepare($main_sql);
foreach ($params as $key => $val) {
    $main_stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$main_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$main_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$main_stmt->execute();

$entries = $main_stmt->fetchAll(PDO::FETCH_ASSOC);

// Load rooms list for filter dropdown
$rooms_stmt = $pdo->prepare("
    SELECT id, title, difficulty
    FROM rooms
    WHERE is_active = 1
    ORDER BY difficulty, title
");
$rooms_stmt->execute();
$rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Optional: get current user's best entry for highlight
$user_best = null;
if ($current_user_id) {
    $user_best_sql = "
        SELECT
            le.id AS entry_id,
            le.team_id,
            le.room_id,
            r.title AS room_title,
            r.difficulty,
            t.name AS team_name,
            le.total_score,
            le.time_taken_seconds,
            le.completed_at,
            RANK() OVER (
                ORDER BY le.total_score DESC, le.time_taken_seconds ASC
            ) AS global_rank
        FROM leaderboard_entries le
        JOIN teams t ON t.id = le.team_id
        JOIN team_members tm ON tm.team_id = t.id
        JOIN rooms r ON r.id = le.room_id
        WHERE tm.user_id = :user_id
        ORDER BY le.total_score DESC, le.time_taken_seconds ASC
        LIMIT 1
    ";
    $user_best_stmt = $pdo->prepare($user_best_sql);
    $user_best_stmt->execute([':user_id' => $current_user_id]);
    $user_best = $user_best_stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leaderboard - Escape Room</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
<header>
    <div class="container">
        <h1>Escape Room Leaderboard</h1>
        <div class="user-info">
            <?php if (is_logged_in()): ?>
                Welcome, <?= htmlspecialchars($_SESSION['username']) ?> |
                <a href="index.php">Lobby</a> |
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a> |
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container">
    <section class="filters">
        <form method="get" action="leaderboard.php" class="filter-form">
            <label>
                Room:
                <select name="room">
                    <option value="">All rooms</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"
                            <?= ($room_filter === (int)$r['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['title']) ?> (<?= ucfirst(htmlspecialchars($r['difficulty'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Difficulty:
                <select name="difficulty">
                    <option value="">All</option>
                    <option value="easy" <?= ($diff_filter === 'easy') ? 'selected' : '' ?>>Easy</option>
                    <option value="medium" <?= ($diff_filter === 'medium') ? 'selected' : '' ?>>Medium</option>
                    <option value="hard" <?= ($diff_filter === 'hard') ? 'selected' : '' ?>>Hard</option>
                </select>
            </label>

            <button type="submit">Filter</button>
            <a href="leaderboard.php" class="btn-link">Clear</a>
        </form>
    </section>

    <?php if ($user_best): ?>
        <section class="your-best">
            <h2>Your best run</h2>
            <p>
                Team: <strong><?= htmlspecialchars($user_best['team_name']) ?></strong> |
                Room: <strong><?= htmlspecialchars($user_best['room_title']) ?></strong> |
                Score: <strong><?= (int)$user_best['total_score'] ?></strong> |
                Time: <strong><?= (int)$user_best['time_taken_seconds'] ?>s</strong> |
                Global rank: <strong>#<?= (int)$user_best['global_rank'] ?></strong>
            </p>
        </section>
    <?php endif; ?>

    <section class="leaderboard">
        <h2>Top Teams</h2>

        <?php if (empty($entries)): ?>
            <p>No completed games found for the selected filters.</p>
        <?php else: ?>
            <table class="leaderboard-table">
                <thead>
                <tr>
                    <th>Rank</th>
                    <th>Team</th>
                    <th>Room</th>
                    <th>Difficulty</th>
                    <th>Score</th>
                    <th>Time (s)</th>
                    <th>Completed At</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                    <tr <?= ($current_user_id && isset($user_best) && (int)$user_best['entry_id'] === (int)$e['entry_id']) ? 'class="your-row"' : '' ?>>
                        <td>#<?= (int)$e['global_rank'] ?></td>
                        <td><?= htmlspecialchars($e['team_name']) ?></td>
                        <td><?= htmlspecialchars($e['room_title']) ?></td>
                        <td><?= ucfirst(htmlspecialchars($e['difficulty'])) ?></td>
                        <td><?= (int)$e['total_score'] ?></td>
                        <td><?= (int)$e['time_taken_seconds'] ?></td>
                        <td><?= htmlspecialchars($e['completed_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $room_filter !== null ? '&room=' . $room_filter : '' ?><?= $diff_filter !== null ? '&difficulty=' . $diff_filter : '' ?>">
                        &laquo; Prev
                    </a>
                <?php endif; ?>

                <span class="page-info">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $room_filter !== null ? '&room=' . $room_filter : '' ?><?= $diff_filter !== null ? '&difficulty=' . $diff_filter : '' ?>">
                        Next &raquo;
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<footer class="container">
    <p>&copy; <?= date('Y') ?> Escape Room Platform</p>
</footer>

<script src="../js/main.js"></script>
</body>
</html>