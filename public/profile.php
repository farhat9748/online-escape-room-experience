<?php
// public/profile.php - User profile & settings

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/function.php';

if (!is_logged_in()) {
    redirect('login.php?redirect=profile.php');
}

$current_user_id = (int)$_SESSION['user_id'];
$current_username = $_SESSION['username'];
$current_email = $_SESSION['email'] ?? '';

// Load full user record
$user_stmt = $pdo->prepare("
    SELECT id, username, email, created_at
    FROM users
    WHERE id = :id
");
$user_stmt->execute([':id' => $current_user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Should not happen if session is valid, but handle gracefully
    session_destroy();
    redirect('login.php');
}

// Stats: games played, completed, best score
$stats_stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS games_played,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS games_completed,
        MAX(total_score) AS best_score
    FROM game_sessions gs
    JOIN teams t ON t.id = gs.team_id
    JOIN team_members tm ON tm.team_id = t.id
    WHERE tm.user_id = :user_id
");
$stats_stmt->execute([':user_id' => $current_user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Messages for form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $new_username = trim($_POST['username'] ?? '');
        $new_email = trim($_POST['email'] ?? '');

        if ($new_username === '' || $new_email === '') {
            $message = 'Username and email are required.';
            $message_type = 'error';
        } else {
            // Check uniqueness (excluding current user)
            $check_stmt = $pdo->prepare("
                SELECT id FROM users
                WHERE (username = :username OR email = :email)
                  AND id != :id
            ");
            $check_stmt->execute([
                ':username' => $new_username,
                ':email' => $new_email,
                ':id' => $current_user_id
            ]);

            if ($check_stmt->fetch()) {
                $message = 'Username or email already in use.';
                $message_type = 'error';
            } else {
                $update_stmt = $pdo->prepare("
                    UPDATE users
                    SET username = :username, email = :email
                    WHERE id = :id
                ");
                $update_stmt->execute([
                    ':username' => $new_username,
                    ':email' => $new_email,
                    ':id' => $current_user_id
                ]);

                // Update session
                $_SESSION['username'] = $new_username;
                $_SESSION['email'] = $new_email;

                $message = 'Profile updated successfully.';
                $message_type = 'success';

                // Refresh user data
                $user_stmt->execute([':id' => $current_user_id]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $message = 'All password fields are required.';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New password and confirmation do not match.';
            $message_type = 'error';
        } else {
            // Verify current password
            $pass_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
            $pass_stmt->execute([':id' => $current_user_id]);
            $row = $pass_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($current_password, $row['password_hash'])) {
                $message = 'Current password is incorrect.';
                $message_type = 'error';
            } else {
                // Optional: enforce minimum length / complexity here

                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $update_pass_stmt = $pdo->prepare("
                    UPDATE users
                    SET password_hash = :password_hash
                    WHERE id = :id
                ");
                $update_pass_stmt->execute([
                    ':password_hash' => $new_hash,
                    ':id' => $current_user_id
                ]);

                $message = 'Password changed successfully.';
                $message_type = 'success';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - Escape Room</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
<header>
    <div class="container">
        <h1>My Profile</h1>
        <div class="user-info">
            Welcome, <?= htmlspecialchars($_SESSION['username']) ?> |
            <a href="index.php">Lobby</a> |
            <a href="leaderboard.php">Leaderboard</a> |
            <a href="logout.php">Logout</a>
        </div>
    </div>
</header>

<main class="container">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="profile-stats">
        <h2>Your Stats</h2>
        <ul>
            <li>Username: <strong><?= htmlspecialchars($user['username']) ?></strong></li>
            <li>Email: <strong><?= htmlspecialchars($user['email']) ?></strong></li>
            <li>Member since: <strong><?= htmlspecialchars($user['created_at']) ?></strong></li>
            <li>Games played: <strong><?= (int)$stats['games_played'] ?></strong></li>
            <li>Games completed: <strong><?= (int)$stats['games_completed'] ?></strong></li>
            <li>Best score: <strong><?= $stats['best_score'] !== null ? (int)$stats['best_score'] : '—' ?></strong></li>
        </ul>
    </section>

    <section class="profile-edit">
        <h2>Edit Profile</h2>
        <form method="post" action="profile.php">
            <input type="hidden" name="action" value="update_profile">

            <label>
                Username:
                <input
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars($user['username']) ?>"
                    required
                    maxlength="50"
                >
            </label>

            <label>
                Email:
                <input
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($user['email']) ?>"
                    required
                    maxlength="150"
                >
            </label>

            <button type="submit">Save Changes</button>
        </form>
    </section>

    <section class="profile-password">
        <h2>Change Password</h2>
        <form method="post" action="profile.php">
            <input type="hidden" name="action" value="change_password">

            <label>
                Current password:
                <input type="password" name="current_password" required maxlength="255">
            </label>

            <label>
                New password:
                <input type="password" name="new_password" required maxlength="255">
            </label>

            <label>
                Confirm new password:
                <input type="password" name="confirm_password" required maxlength="255">
            </label>

            <button type="submit">Change Password</button>
        </form>
    </section>
</main>

<footer class="container">
    <p>&copy; <?= date('Y') ?> Escape Room Platform</p>
</footer>

<script src="../js/main.js"></script>
</body>
</html>