/**
 * lobby.js - Room selection, team creation/joining
 * Works with public/index.php (lobby page)
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

        // -------------------------
        // Create team form handling
        // -------------------------
        const createTeamForms = ER.$$('form[action="index.php"] input[name="action"][value="create_team"]');
        createTeamForms.forEach(function (input) {
            const form = input.closest('form');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                const teamNameInput = ER.$('input[name="team_name"]', form);
                const teamName = teamNameInput ? teamNameInput.value.trim() : '';

                // Basic client-side validation
                if (teamName.length < 3) {
                    e.preventDefault();
                    ER.showAlert('Team name must be at least 3 characters.', 'error', container);
                    if (teamNameInput) teamNameInput.focus();
                    return;
                }

                // Optional: AJAX submit instead of normal form submit
                // If you want pure PHP form submit, remove the AJAX block and let the form submit normally.
                if (form.dataset.ajax === '1') {
                    e.preventDefault();

                    const submitBtn = ER.$('button[type="submit"]', form);
                    if (submitBtn) submitBtn.disabled = true;

                    const formData = new FormData(form);
                    const body = {};
                    formData.forEach(function (value, key) {
                        body[key] = value;
                    });

                    ER.post('index.php', body)
                        .then(function (data) {
                            // Since index.php returns HTML, we can't easily parse JSON here.
                            // For a fully AJAX flow, you'd create a dedicated API endpoint.
                            // For now, fallback to normal redirect on success by submitting the form.
                            form.submit();
                        })
                        .catch(function (err) {
                            ER.showAlert(err.message || 'Failed to create team.', 'error', container);
                            if (submitBtn) submitBtn.disabled = false;
                        });
                }
                // else: allow normal form submission to index.php (which handles everything in PHP)
            });
        });

        // -------------------------
        // Join team form handling
        // -------------------------
        const joinTeamForms = ER.$$('form[action="index.php"] input[name="action"][value="join_team"]');
        joinTeamForms.forEach(function (input) {
            const form = input.closest('form');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                // Optional: confirm before joining
                const teamNameEl = ER.$('.team-name', form.closest('.team-item'));
                const teamName = teamNameEl ? teamNameEl.textContent.trim() : 'this team';

                const confirmed = confirm('Join ' + teamName + '?');
                if (!confirmed) {
                    e.preventDefault();
                    return;
                }

                // Disable button temporarily
                const submitBtn = ER.$('button[type="submit"]', form);
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Joining...';
                }

                // Let the form submit normally to index.php; PHP handles logic and redirect.
                // If you want AJAX, you can implement similar to create-team above with a dedicated API.
            });
        });

        // -------------------------
        // General UX improvements
        // -------------------------
        // Highlight user's row in existing teams (if you add a data attribute in PHP later)
        ER.$$('.team-item').forEach(function (item) {
            // Example: if you add data-is-user-team="1" in PHP, you could style it here.
            // For now, this is a placeholder for future enhancements.
        });

        // Show a welcome message if user just registered (optional, if you pass ?registered=1)
        const params = new URLSearchParams(window.location.search);
        if (params.get('registered') === '1') {
            ER.showAlert('Registration successful. You can now create or join a team.', 'success', container);
            // Clean URL
            const newUrl = window.location.pathname;
            window.history.replaceState({}, '', newUrl);
        }
    });
})();