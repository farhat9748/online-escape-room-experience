<?php
// templates/partials/hint_panel.php
//
// Expected variables (set before include):
//   $sessionId   - Current game session ID (int)
//   $puzzleId    - Current puzzle ID (int)
//   $gameOver    - bool, whether the game is over
//   $hints       - array of granted hint rows (optional), each with hint_text, penalty_points
//
// Usage:
//   include __DIR__ . '/../templates/partials/hint_panel.php';
?>

<div class="hints-section">
    <h3>Hints</h3>

    <button id="get-hint-btn" <?= $gameOver ? 'disabled' : '' ?>>
        Get a hint
    </button>

    <?php if (!empty($hints)): ?>
        <div id="hints-list">
            <?php foreach ($hints as $i => $h): ?>
                <div class="hint-item">
                    <strong>Hint #<?= $i + 1 ?>:</strong>
                    <?= nl2br(htmlspecialchars($h['hint_text'] ?? '')) ?>
                    <?php if (!empty($h['penalty_points'])): ?>
                        <span class="hint-penalty">
                            (Penalty: <?= (int)$h['penalty_points'] ?> points)
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div id="hints-list"></div>
    <?php endif; ?>

    <div id="hint-result" class="result-box"></div>
</div>