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
    $controller->attemptRegister();
    exit;
}

$errors = $_SESSION['register_errors'] ?? [];
$input = $_SESSION['register_input'] ?? [];
unset($_SESSION['register_errors'], $_SESSION['register_input']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Escape Room</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
<?php include __DIR__ . '/../pages/header.php'; ?>

<main class="container">
    <section class="auth-box">
        <h2>Create an Account</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0; padding-left:1.2em;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <label>
                Username:
                <input type="text" name="username" value="<?= htmlspecialchars($input['username'] ?? '') ?>" required maxlength="50">
            </label>

            <label>
                Email:
                <input type="email" name="email" value="<?= htmlspecialchars($input['email'] ?? '') ?>" required maxlength="150">
            </label>

            <label>
                Password:
                <input type="password" name="password" required maxlength="255">
            </label>

            <label>
                Confirm Password:
                <input type="password" name="confirm_password" required maxlength="255">
            </label>

            <button type="submit">Register</button>
        </form>

        <p class="auth-note">
            Already have an account?
            <a href="login.php">Login here</a>.
        </p>
    </section>
</main>

<?php include __DIR__ . '/../pages/footer.php'; ?>
<script src="../js/main.js"></script>
</body>
</html>