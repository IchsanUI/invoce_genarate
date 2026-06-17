<?php
/**
 * Super Admin Dashboard
 *
 * Landing page for super admins. Provides overview, user management,
 * company settings, and full invoice history.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_role('superadmin');

$pdo = db();

// Quick stats
$totalInvoices   = (int) $pdo->query("SELECT COUNT(*) FROM `invoices`")->fetchColumn();
$totalUsers      = (int) $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$activeUsers     = (int) $pdo->query("SELECT COUNT(*) FROM `users` WHERE `is_active` = 1")->fetchColumn();
$totalVerifications = (int) $pdo->query("SELECT COUNT(*) FROM `verification_logs`")->fetchColumn();

$pageTitle = 'Super Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="../public/assets/css/styles.css">
</head>
<body>

<nav class="topbar">
    <div class="topbar-inner">
        <span class="topbar-title"><?= h(APP_NAME) ?> — Super Admin</span>
        <div class="topbar-actions">
            <a href="../dashboard.php">Dashboard Saya</a>
            <a href="../logout.php">Keluar</a>
        </div>
    </div>
</nav>

<main class="content">

    <header class="content-header">
        <h1>Super Admin Dashboard</h1>
        <p>Selamat datang, <strong><?= h(current_user_name()) ?></strong>.</p>
    </header>

    <section class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Total Invoice</span>
            <span class="stat-value"><?= h(number_format($totalInvoices, 0, ',', '.')) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total User</span>
            <span class="stat-value"><?= h($totalUsers) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">User Aktif</span>
            <span class="stat-value"><?= h($activeUsers) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Verifikasi QR</span>
            <span class="stat-value"><?= h(number_format($totalVerifications, 0, ',', '.')) ?></span>
        </div>
    </section>

    <section class="placeholder-section">
        <p class="placeholder-note">Modul-modul ini akan diisi pada fase berikutnya:</p>
        <ul class="placeholder-list">
            <li><strong>Manajemen User</strong> (Fase 4)</li>
            <li><strong>Pengaturan Perusahaan</strong> (Fase 4)</li>
            <li><strong>Semua Invoice</strong> (Fase 4)</li>
            <li><strong>Log Aktivitas</strong> (Fase 4)</li>
        </ul>
    </section>

</main>

</body>
</html>
