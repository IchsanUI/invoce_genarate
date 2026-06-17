<?php
/**
 * APPLICATION CONFIGURATION
 *
 * 1. Copy this file to config.php
 * 2. Update the values below to match your environment
 * 3. config.php is gitignored — never commit real secrets
 */

// ---- Database -----------------------------------------------------------------
define('DB_HOST',     'localhost');         // XAMPP default
define('DB_PORT',     '3306');               // XAMPP default
define('DB_NAME',     'invoice_system');     // nama database
define('DB_USER',     'root');               // XAMPP default (kosong = root)
define('DB_PASS',     '');                   // XAMPP default (kosong)
define('DB_CHARSET',  'utf8mb4');

// ---- Secrets ------------------------------------------------------------------
define('SECRET_KEY',  'CHANGE-ME-generate-a-random-hmac-secret-key');
define('HMAC_SECRET', 'CHANGE-ME-generate-a-random-signature-secret-key');

// ---- App ----------------------------------------------------------------------
define('APP_NAME',  'Invoice Generator — AS Stuff');
define('APP_BASE_URL', 'http://localhost');  // berubah saat SSL diaktifkan
define('APP_DEBUG', true);                   // false di production

// ---- Session ------------------------------------------------------------------
define('SESSION_LIFETIME',  3600);            // 1 jam
define('SESSION_NAME',      'invoice_sid');

// ---- Security -----------------------------------------------------------------
define('ALLOWED_ORIGINS', ['http://localhost', 'https://localhost']); // CORS untuk masa depan
define('MAX_LOGIN_ATTEMPTS', 5);             // lockout setelah X gagal login
define('LOCKOUT_MINUTES',    15);            // durasi lockout (menit)
define('LOGO_MAX_SIZE_KB',  500);            // maks ukuran logo (KB)

// ---- Company ------------------------------------------------------------------
define('COMPANY_CODE',  'ASSIG');            // kode perusahaan untuk nomor invoice
