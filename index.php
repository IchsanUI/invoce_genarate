<?php
/**
 * Login Page
 *
 * GET: shows login form
 * POST: processes login attempt
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
start_session();
if (is_logged_in()) {
    if ($_SESSION['role'] === 'superadmin') {
        header('Location: superadmin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$errors = [];
$lockedFor = 0;

// Process POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    generate_csrf_token();

    // CSRF validation
    if (!validate_csrf_token()) {
        $errors[] = 'Verifikasi keamanan gagal. Silakan coba lagi.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $result   = attempt_login($username, $password);

        if (!$result['ok']) {
            $errors[] = $result['error'];
            if (!empty($result['locked_for'])) {
                $lockedFor = $result['locked_for'];
            }
        } else {
            // Successful login — redirect based on role
            $role = $result['user']['role'] ?? 'admin';
            header('Location: ' . ($role === 'superadmin' ? 'superadmin/dashboard.php' : 'dashboard.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?> — Login</title>
    <link rel="stylesheet" href="public/assets/css/styles.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1><?= h(APP_NAME) ?></h1>
        <p class="auth-subtitle">Masuk untuk melanjutkan</p>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?>
                    <p><?= h($err) ?></p>
                <?php endforeach ?>
                <?php if ($lockedFor > 0): ?>
                    <p class="lockout-hint">Tercoba <?= $lockedFor ?> detik lagi untuk login kembali.</p>
                <?php endif ?>
            </div>
        <?php endif ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Masukkan username"
                    autocomplete="username"
                    required
                    autofocus
                    value="<?= h($_POST['username'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit">Masuk</button>
        </form>
    </div>
</div>

</body>
</html>
