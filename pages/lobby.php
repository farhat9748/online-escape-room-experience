<?php
$rooms = $GLOBALS['lobby_rooms'] ?? [];
$message = $GLOBALS['lobby_message'] ?? '';
$messageType = $GLOBALS['lobby_message_type'] ?? 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lobby - Escape Room</title>
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="container">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="page-header">
        <h2>Choose Your Room</h2>
        <p>Pick a room and join or create a team to start the escape.</p>
    </section>

    <?php if (empty($rooms)): ?>
        <p>No active rooms are currently available.</p>
    <?php else: ?>
        <?php foreach ($rooms as $entry): ?>
            <?php
            $room = $entry['room'];
            $teams = $entry['teams'] ?? [];
            $userTeam = $entry['user_team_for_room'] ?? null;
            ?>
            <section class="room-card">
                <div class="room-card-header">
                    <h3><?= htmlspecialchars($room['title']) ?></h3>
                    <span class="badge <?= htmlspecialchars($room['difficulty']) ?>">
                        <?= ucfirst(htmlspecialchars($room['difficulty'])) ?>
                    </span>
                </div>

                <p><?= htmlspecialchars($room['description'] ?? 'No description available.') ?></p>

                <div class="room-meta">
                    <span>Time limit: <?= (int)$room['time_limit_seconds'] ?>s</span>
                </div>

                <?php if ($userTeam): ?>
                    <div class="room-actions">
                        <a href="../public/room.php?team_id=<?= (int)$userTeam['team_id'] ?>" class="btn btn-primary">
                            Continue as <?= htmlspecialchars($userTeam['team_name']) ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="team-creation">
                        <form method="post" action="../public/index.php">
                            <input type="hidden" name="action" value="create_team">
                            <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                            <label>
                                Team name:
                                <input type="text" name="team_name" required maxlength="100" placeholder="Your team name">
                            </label>
                            <button type="submit">Create Team</button>
                        </form>
                    </div>

                    <?php if (!empty($teams)): ?>
                        <div class="team-list">
                            <h4>Open teams</h4>
                            <?php foreach ($teams as $team): ?>
                                <div class="team-item">
                                    <span><?= htmlspecialchars($team['team_name']) ?> (<?= (int)$team['member_count'] ?> members)</span>
                                    <form method="post" action="../public/index.php">
                                        <input type="hidden" name="action" value="join_team">
                                        <input type="hidden" name="team_id" value="<?= (int)$team['team_id'] ?>">
                                        <button type="submit">Join</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No teams yet. Create the first one for this room.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>
<script src="../js/main.js"></script>
</body>
</html>
