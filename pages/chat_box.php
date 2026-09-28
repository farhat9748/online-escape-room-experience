```php
<?php
/**
 * Team Chat Box
 *
 * Optional variables:
 * $sessionId - Current game session ID
 * $gameOver  - Whether the game is over (true/false)
 * $messages  - Array of chat messages
 */

// Safe default values
$sessionId = $sessionId ?? 0;
$gameOver  = $gameOver ?? false;
$messages  = $messages ?? [];

// Make sure messages is an array
if (!is_array($messages)) {
    $messages = [];
}
?>

<section class="chat-section">

    <h3>Team Chat</h3>

    <div id="chat-messages" class="chat-messages">

        <?php if (!empty($messages)): ?>

            <?php foreach ($messages as $m): ?>

                <div
                    class="chat-message"
                    data-message-id="<?= (int)($m['id'] ?? 0) ?>"
                >

                    <span class="chat-meta">
                        [<?= htmlspecialchars($m['username'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>]

                        <?= htmlspecialchars(
                            $m['created_at'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <span class="chat-text">
                        <?= nl2br(
                            htmlspecialchars(
                                $m['message_text'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ) ?>
                    </span>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p class="no-messages">No messages yet.</p>

        <?php endif; ?>

    </div>


    <?php if (!$gameOver): ?>

        <form id="chat-form" class="chat-form">

            <label for="chat-input">
                Message:
            </label>

            <input
                type="text"
                id="chat-input"
                name="message"
                required
                maxlength="500"
                placeholder="Type your message..."
                autocomplete="off"
            >

            <button type="submit">
                Send
            </button>

        </form>

    <?php else: ?>

        <p class="chat-disabled-notice">
            Chat is disabled (game over).
        </p>

    <?php endif; ?>

</section>
```
