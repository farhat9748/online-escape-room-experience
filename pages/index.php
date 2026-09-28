<?php
require_once __DIR__ . '/../config/db.php';
?>

<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

```
<title>Online Escape Room</title>

<link rel="stylesheet" href="../css/game.css">
```

</head>

<body>

<?php
$headerFile = __DIR__ . '/../templates/partials/header.php';

if (file_exists($headerFile)) {
    require_once $headerFile;
}
?>

<main class="container">

```
<section class="hero">
    <h1>Welcome to the Online Escape Room</h1>

    <p>
        Solve puzzles, discover clues, complete challenges,
        and escape before time runs out!
    </p>

    <div class="hero-actions">
        <a href="game.php" class="btn">Start Game</a>
        <a href="leaderboard.php" class="btn">Leaderboard</a>
    </div>
</section>

<section class="game-info">

    <div class="info-card">
        <h2>🔐 Solve Puzzles</h2>
        <p>
            Find clues and solve challenging puzzles to progress
            through the escape room.
        </p>
    </div>

    <div class="info-card">
        <h2>⏱️ Beat the Clock</h2>
        <p>
            Complete the room before the countdown reaches zero.
        </p>
    </div>

    <div class="info-card">
        <h2>🏆 Compete</h2>
        <p>
            Check the leaderboard and compare your score with
            other players.
        </p>
    </div>

</section>
```

</main>

<?php
$footerFile = __DIR__ . '/../templates/partials/footer.php';

if (file_exists($footerFile)) {
    require_once $footerFile;
}
?>

</body>
</html>
