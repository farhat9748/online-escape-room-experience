/**
 * timer.js - Countdown timer logic for escape room game
 * Works with public/room.php and game.js
 */

(function () {
    'use strict';

    const ER = window.ER;
    if (!ER) {
        console.error('main.js not loaded; ER utilities missing.');
        return;
    }

    ER.ready(function () {
        const config = window.ESCAPE_ROOM_CONFIG || {};
        const timerEl = ER.$('#timer');
        if (!timerEl) returnwindow.ESCAPE_ROOM_CONFIG = {
    sessionId: 123,
    initialTimeRemaining: 3600,
    initialLastMsgId: 0,
    gameOver: false
};

        let timeRemaining = typeof config.initialTimeRemaining === 'number'
            ? config.initialTimeRemaining
            : 0;

        const gameOverEl = ER.$('.game-over');
        const container = ER.$('.container');

        let intervalId = null;
        let isGameOver = !!config.gameOver || timeRemaining <= 0;

        function updateTimerDisplay() {
            timerEl.textContent = timeRemaining;
            document.title = '(' + timeRemaining + 's) Escape Room';
        }

        function endGame() {
            if (isGameOver) return;
            isGameOver = true;

            clearInterval(intervalId);
            intervalId = null;

            timeRemaining = 0;
            updateTimerDisplay();

            timerEl.parentElement.classList.add('game-over');

            // Show game over message if not already visible
            if (!gameOverEl) {
                const statusSection = ER.$('.game-status');
                if (statusSection) {
                    const goDiv = document.createElement('div');
                    goDiv.className = 'status-item game-over';
                    goDiv.textContent = 'Time is up! Game over.';
                    statusSection.appendChild(goDiv);
                }
            }

            // Disable interactions in game.js by updating config
            config.gameOver = true;
            window.ESCAPE_ROOM_CONFIG = config;

            ER.showAlert('Time is up! The game is over.', 'error', container);

            // Optionally reload after a delay to refresh state
            setTimeout(function () {
                window.location.reload();
            }, 2000);
        }

        function tick() {
            if (isGameOver) return;

            if (timeRemaining <= 0) {
                endGame();
                return;
            }

            timeRemaining -= 1;
            updateTimerDisplay();

            if (timeRemaining <= 0) {
                endGame();
            }
        }

        // Initial display
        updateTimerDisplay();

        // If already game over, don't start timer
        if (isGameOver) {
            return;
        }

        // Start countdown
        intervalId = setInterval(tick, 1000);

        // Optional: periodically re-sync with server near the end
        // to avoid client clock drift issues.
        const syncInterval = setInterval(function () {
            if (isGameOver) {
                clearInterval(syncInterval);
                return;
            }

            // Only sync when under 30 seconds to reduce load
            if (timeRemaining > 30) return;

            ER.get('api/session_status.php?session_id=' + encodeURIComponent(config.sessionId))
                .then(function (data) {
                    if (isGameOver) return;
                    // Expected: { game_over: true/false, time_remaining: number }
                    if (data && typeof data.time_remaining === 'number') {
                        const serverTime = data.time_remaining;
                        // Only adjust if difference is significant (>2s)
                        if (Math.abs(serverTime - timeRemaining) > 2) {
                            timeRemaining = Math.max(0, serverTime);
                            updateTimerDisplay();
                        }
                        if (data.game_over) {
                            endGame();
                        }
                    }
                })
                .catch(function () {
                    // Ignore sync errors; keep local timer running
                });
        }, 3000);
    });
})();