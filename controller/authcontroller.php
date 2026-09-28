<?php

namespace App\Controllers;

use App\Models\User;

class AuthController
{
    public function attemptLogin(): void
    {
        $identity = trim($_POST['username_or_email'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = $_POST['redirect'] ?? 'index.php';

        if ($identity === '' || $password === '') {
            $_SESSION['login_error'] = 'Please enter both username/email and password.';
            $_SESSION['login_input'] = ['username_or_email' => $identity];
            redirect('login.php');
        }

        $user = User::findByIdentity($identity);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_error'] = 'Invalid username/email or password.';
            $_SESSION['login_input'] = ['username_or_email' => $identity];
            redirect('login.php');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];

        $allowed = ['index.php', 'room.php', 'leaderboard.php', 'profile.php'];
        redirect(in_array(basename($redirect), $allowed, true) ? $redirect : 'index.php');
    }

    public function attemptRegister(): void
    {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['confirm_password'] ?? '';
        $errors = [];

        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be between 3 and 50 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email must be valid.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($password !== $confirmation) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$errors && (User::findByUsername($username) || User::findByEmail($email))) {
            $errors[] = 'Username or email already in use.';
        }

        if ($errors) {
            $_SESSION['register_errors'] = $errors;
            $_SESSION['register_input'] = ['username' => $username, 'email' => $email];
            redirect('register.php');
        }

        User::create($username, $email, $password);
        redirect('login.php?registered=1');
    }
}