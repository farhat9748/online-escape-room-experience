<?php
// templates/partials/puzzle_card.php
//
// Expected variables (set before include):
//   $puzzle        - Puzzle row (id, title, puzzle_type, question_text, points, order_in_room, ...)
//   $sessionId     - Current game session ID (int)
//   $puzzleId      - Current puzzle ID (int)
//   $gameOver      - bool, whether the game is over
//
// Usage:
//   include __DIR__ . '/../templates/partials/puzzle_card.php';
?>

<?php if (empty($puzzle)): ?>
    <section class="puzzle-section">
        <h3>No puzzles available</h3>
        <p>This room has no puzzles or all puzzles have been solved.</p>
    </section>
<?php else: ?>
    <section
        class="puzzle-section"
        data-session-id="<?= (int)$sessionId ?>"
        data-puzzle-id="<?= (int)$puzzleId ?>"
    >
        <h2>
            Puzzle #<?= (int)$puzzle['order_in_room'] ?>:
            <?= htmlspecialchars($puzzle['title'] ?? 'Untitled') ?>
        </h2>

        <p class="puzzle-meta">
            Type: <strong><?= ucfirst(htmlspecialchars($puzzle['puzzle_type'] ?? 'Unknown')) ?></strong> |
            Points: <strong><?= (int)$puzzle['points'] ?></strong>
        </p>

        <div class="puzzle-question">
            <?= nl2br(htmlspecialchars($puzzle['question_text'] ?? '')) ?>
        </div>

        <?php if (!$gameOver): ?>
            <div class="answer-form">
                <h3>Submit your answer</h3>
                <form id="answer-form">
                    <label>
                        Answer:
                        <input
                            type="text"
                            id="answer-input"
                            name="answer"
                            required
                            maxlength="255"
                            <?= $gameOver ? 'disabled' : '' ?>
                        >
                    </label>
                    <button type="submit" <?= $gameOver ? 'disabled' : '' ?>>Submit</button>
                </form>
                <div id="answer-result" class="result-box"></div>
            </div>

            <div class="hints-section">
                <h3>Hints</h3>
                <button id="get-hint-btn" <?= $gameOver ? 'disabled' : '' ?>>Get a hint</button>
                <div id="hints-list"></div>
                <div id="hint-result" class="result-box"></div>
            </div>
        <?php else: ?>
            <div class="game-over-notice">
                <p>This puzzle is no longer active (game over or session ended).</p>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>