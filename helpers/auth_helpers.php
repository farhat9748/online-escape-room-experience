<?php
// src/helpers/auth_helpers.php

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!is_logged_in()) {
            $current = $_SERVER['REQUEST_URI'] ?? 'index.php';
            $redirect = urlencode($current);
            redirect('login.php?redirect=' . $redirect);
        }
    }
}