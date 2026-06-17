<?php
/**
 * Admin Dashboard
 *
 * Landing page after login for non-super-admin users.
 * Super admins also land here if they reach this URL directly,
 * but they typically go to superadmin/dashboard.php instead.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

start_session();
require_login();

$pageTitle = 'Dashboard Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="public/assets/css/styles.css">
</head>
<body>

<nav class="topbar">
    <div class="topbar-inner">
        <span class="topbar-title"><?= h(APP_NAME) ?></span>
        <div class="topbar-actions">
            <span class="topbar-user">
                <?= h(current_user_name()) ?>
                <em>(<?= h($_SESSION['role'] ?? 'admin') ?>)</em>
            </span>
            <a href="logout.php">Keluar</a>
        </div>
    </div>
</nav>

<main class="content">

    <header class="content-header">
        <h1>Dashboard Admin</h1>
        <p>Selamat datang, <strong><?= h(current_user_name()) ?></strong>.</p>
    </header>

    <section class="placeholder-section">
        <p class="placeholder-note">
            Modul-modul ini akan diisi pada fase berikutnya:
        </p>
        <ul class="placeholder-list">
            <li><strong>Generate Invoice</strong> (Fase 5)</li>
            <li><strong>History Invoice Milik Saya</strong> (Fase 5)</li>
            <?php if (is_super_admin()): ?>
                <li><a href="superadmin/dashboard.php">Buka Panel Super Admin →</a></li>
            <?php endif ?>
        </ul>
    </section>

</main>

</body>
</html>
