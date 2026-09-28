<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/function.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../controller/authcontroller.php';

if (is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new \App\Controllers\AuthController();
    $controller->attemptLogin();
    exit;
}

$error = $_SESSION['login_error'] ?? '';
$input = $_SESSION['login_input'] ?? [];
unset($_SESSION['login_error'], $_SESSION['login_input']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Escape Room</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
<?php include __DIR__ . '/../pages/header.php'; ?>

<main class="container">
    <section class="auth-box">
        <h2>Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <?php $redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : ''; if ($redirect): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>

            <label>
                Username or Email:
                <input type="text" name="username_or_email" value="<?= htmlspecialchars($input['username_or_email'] ?? '') ?>" required maxlength="150">
            </label>

            <label>
                Password:
                <input type="password" name="password" required maxlength="255">
            </label>

            <button type="submit">Login</button>
        </form>

        <p class="auth-note">
            Don’t have an account?
            <a href="register.php">Register here</a>.
        </p>
    </section>
</main>

<?php include __DIR__ . '/../pages/footer.php'; ?>
<script src="../js/main.js"></script>
</body>
</html>