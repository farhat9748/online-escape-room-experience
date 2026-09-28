/**
 * main.js - Shared utilities for Escape Room Platform
 */

(function () {
    'use strict';

    // Expose a global utility object
    window.ER = window.ER || {};

    const ER = window.ER;

    /**
     * DOM helpers
     */
    ER.$ = function (selector, context = document) {
        return context.querySelector(selector);
    };

    ER.$$ = function (selector, context = document) {
        return Array.from(context.querySelectorAll(selector));
    };

    ER.on = function (target, event, selector, handler) {
        if (typeof selector === 'function') {
            handler = selector;
            selector = null;
        }
        target.addEventListener(event, function (e) {
            if (!selector) {
                handler.call(e.target, e);
                return;
            }
            const match = e.target.closest(selector);
            if (match && target.contains(match)) {
                handler.call(match, e);
            }
        });
    };

    ER.ready = function (fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    };

    /**
     * Simple AJAX helper using fetch
     * options: { method, headers, body (object or FormData), timeout }
     */
    ER.request = async function (url, options = {}) {
        const {
            method = 'GET',
            headers = {},
            body = null,
            timeout = 15000
        } = options;

        let finalHeaders = { ...headers };
        let finalBody = null;

        if (body instanceof FormData) {
            finalBody = body;
            // Don't set Content-Type for FormData; browser will set it with boundary
        } else if (body && typeof body === 'object') {
            finalHeaders['Content-Type'] = 'application/json';
            finalBody = JSON.stringify(body);
        }

        const controller = new AbortController();
        const id = setTimeout(() => controller.abort(), timeout);

        try {
            const res = await fetch(url, {
                method,
                headers: finalHeaders,
                body: finalBody,
                signal: controller.signal,
                credentials: 'same-origin'
            });
            clearTimeout(id);

            const contentType = res.headers.get('Content-Type') || '';
            let data;
            if (contentType.includes('application/json')) {
                data = await res.json();
            } else {
                data = await res.text();
            }

            if (!res.ok) {
                const err = new Error(data?.message || `Request failed: ${res.status}`);
                err.status = res.status;
                err.data = data;
                throw err;
            }

            return data;
        } catch (err) {
            if (err.name === 'AbortError') {
                const timeoutErr = new Error('Request timed out');
                timeoutErr.code = 'TIMEOUT';
                throw timeoutErr;
            }
            throw err;
        }
    };

    ER.get = function (url, options = {}) {
        return ER.request(url, { method: 'GET', ...options });
    };

    ER.post = function (url, body, options = {}) {
        return ER.request(url, { method: 'POST', body, ...options });
    };

    /**
     * UI helpers
     */
    ER.showAlert = function (message, type = 'success', container = null) {
        if (!container) {
            container = ER.$('.container');
            if (!container) return;
        }

        const existing = ER.$('.alert', container);
        if (existing) existing.remove();

        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'error' : 'success'}`;
        alert.textContent = message;

        container.insertBefore(alert, container.firstChild);
        setTimeout(() => alert.remove(), 6000);
    };

    ER.escapeHtml = function (str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    ER.setElementText = function (el, text) {
        if (!el) return;
        el.textContent = text;
    };

    /**
     * Simple auth helpers (optional, for client-side checks only)
     */
    ER.isAuthenticated = function () {
        // This is just a hint; real auth is server-side via sessions.
        // You can set this from PHP if needed, e.g. via a global JS variable.
        return !!(window.ESCAPE_ROOM_AUTH && window.ESCAPE_ROOM_AUTH.isLoggedIn);
    };

    ER.getCurrentUser = function () {
        return (window.ESCAPE_ROOM_AUTH && window.ESCAPE_ROOM_AUTH.user) || null;
    };

    /**
     * Basic initialization (optional)
     */
    ER.ready(function () {
        // Example: attach global form validation behavior if desired
        ER.$$('form[data-validate="basic"]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const required = ER.$$('[required]', form);
                let valid = true;
                required.forEach(function (field) {
                    if (!field.value.trim()) {
                        valid = false;
                        field.focus();
                    }
                });
                if (!valid) {
                    e.preventDefault();
                    ER.showAlert('Please fill in all required fields.', 'error', form.closest('.container'));
                }
            });
        });
    });
})();