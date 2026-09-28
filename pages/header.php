<?php
// templates/partials/header.php
// Assumes session is already started and auth helpers are available.
?>
<header>
    <div class="container">
        <a href="../public/index.php" class="site-title">
            <h1>Escape Room</h1>
        </a>

        <nav class="main-nav">
            <a href="../public/index.php">Lobby</a>
            <a href="../public/leaderboard.php">Leaderboard</a>

            <?php if (is_logged_in()): ?>
                <a href="../public/profile.php">Profile</a>
                <a href="../public/logout.php" class="btn-logout">Logout</a>
                <span class="user-greeting">
                    Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
            <?php else: ?>
                <a href="../public/login.php">Login</a>
                <a href="../public/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>