/**
 * game.js - Puzzle interactions, answer submission, hints
 * Works with public/room.php
 */

(function () {
    'use strict';

    const ER = window.ER;
    if (!ER) {
        console.error('main.js not loaded; ER utilities missing.');
        return;
    }

    ER.ready(function () {
        const container = ER.$('.container');
        if (!container) return;

        const puzzleSection = ER.$('.puzzle-section');
        if (!puzzleSection) return;

        const sessionId = puzzleSection.dataset.sessionId;
        const puzzleId = puzzleSection.dataset.puzzleId;

        if (!sessionId || !puzzleId) {
            console.warn('Missing session or puzzle id on .puzzle-section');
            return;
        }

        const config = window.ESCAPE_ROOM_CONFIG || {};
        const isGameOver = !!config.gameOver;

        // -------------------------
        // Answer submission
        // -------------------------
        const answerForm = ER.$('#answer-form');
        const answerInput = ER.$('#answer-input');
        const answerResult = ER.$('#answer-result');

        if (answerForm && answerInput && answerResult) {
            answerForm.addEventListener('submit', function (e) {
                e.preventDefault();

                if (isGameOver) {
                    ER.showAlert('This game session is over.', 'error', container);
                    return;
                }

                const answer = answerInput.value.trim();
                if (!answer) {
                    ER.showAlert('Please enter an answer.', 'error', container);
                    answerInput.focus();
                    return;
                }

                const submitBtn = ER.$('button[type="submit"]', answerForm);
                if (submitBtn) submitBtn.disabled = true;

                answerResult.textContent = '';

                ER.post('../api/submit_answer.php', {
                    session_id: sessionId,
                    puzzle_id: puzzleId,
                    answer: answer
                })
                .then(function (data) {
                    // Expected JSON: { success: true/false, message: "...", solved: true/false }
                    const msg = data && data.message ? data.message : 'Answer processed.';
                    const success = !!(data && data.success);
                    const solved = !!(data && data.solved);

                    answerResult.textContent = msg;
                    answerResult.className = 'result-box ' + (success ? 'success' : 'error');

                    if (solved) {
                        // Disable input and button
                        if (answerInput) answerInput.disabled = true;
                        if (submitBtn) submitBtn.disabled = true;

                        ER.showAlert('Puzzle solved! Loading next puzzle...', 'success', container);

                        // Reload page to show next puzzle (simple approach)
                        setTimeout(function () {
                            window.location.reload();
                        }, 1200);
                    } else {
                        // Allow retry
                        if (submitBtn) submitBtn.disabled = false;
                        answerInput.focus();
                        answerInput.select();
                    }
                })
                .catch(function (err) {
                    const msg = err && err.message ? err.message : 'Failed to submit answer.';
                    answerResult.textContent = msg;
                    answerResult.className = 'result-box error';
                    ER.showAlert(msg, 'error', container);
                    if (submitBtn) submitBtn.disabled = false;
                });
            });
        }

        // -------------------------
        // Hints
        // -------------------------
        const getHintBtn = ER.$('#get-hint-btn');
        const hintsList = ER.$('#hints-list');
        const hintResult = ER.$('#hint-result');

        if (getHintBtn && hintsList && hintResult) {
            getHintBtn.addEventListener('click', function () {
                if (isGameOver) {
                    ER.showAlert('This game session is over.', 'error', container);
                    return;
                }

                getHintBtn.disabled = true;
                hintResult.textContent = '';

                ER.post('../api/get_hint.php', {
                    session_id: sessionId,
                    puzzle_id: puzzleId
                })
                .then(function (data) {
                    // Expected JSON:
                    // { success: true/false, message: "...", hint: "...", penalty: number }
                    const msg = data && data.message ? data.message : 'Hint processed.';
                    const success = !!(data && data.success);

                    hintResult.textContent = msg;
                    hintResult.className = 'result-box ' + (success ? 'success' : 'error');

                    if (success && data && data.hint) {
                        const hintItem = document.createElement('div');
                        hintItem.className = 'hint-item';
                        hintItem.textContent = 'Hint: ' + data.hint;
                        if (typeof data.penalty === 'number') {
                            hintItem.textContent += ' (Penalty: ' + data.penalty + ' points)';
                        }
                        hintsList.appendChild(hintItem);

                        // Update penalty display in status if present
                        const penaltyEl = ER.$('#penalty-points');
                        if (penaltyEl && typeof data.total_penalty === 'number') {
                            penaltyEl.textContent = data.total_penalty;
                        }
                    }

                    if (success) {
                        getHintBtn.disabled = false;
                    } else {
                        // No more hints or error; keep disabled
                        getHintBtn.disabled = true;
                    }
                })
                .catch(function (err) {
                    const msg = err && err.message ? err.message : 'Failed to get hint.';
                    hintResult.textContent = msg;
                    hintResult.className = 'result-box error';
                    ER.showAlert(msg, 'error', container);
                    getHintBtn.disabled = false;
                });
            });
        }

        // -------------------------
        // Optional: disable interactions if game over
        // -------------------------
        if (isGameOver) {
            if (answerInput) answerInput.disabled = true;
            const submitBtn = answerForm ? ER.$('button[type="submit"]', answerForm) : null;
            if (submitBtn) submitBtn.disabled = true;
            if (getHintBtn) getHintBtn.disabled = true;
        }
    });
})();