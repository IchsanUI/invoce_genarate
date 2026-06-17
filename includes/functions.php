<?php
/**
 * Helper Functions
 *
 * Common utilities used throughout the application:
 * - Currency formatting (Rupiah)
 * - Input sanitization / escaping
 * - CSRF token generation & validation
 * - Timestamp utilities
 * - Random string helpers
 */

// ---- CSRF ---------------------------------------------------------------------

/**
 * Generate a new CSRF token and store it in the session.
 * Must be called once at the start of any page that renders forms.
 */
function generate_csrf_token(): void
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Return the current CSRF token (useful for templates).
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        generate_csrf_token();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token. Returns true / false.
 */
function validate_csrf_token(): bool
{
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Echo a hidden CSRF input field. Usage: <?= csrf_field() ?>
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// ---- Sanitization -------------------------------------------------------------

/**
 * Escape a value for safe HTML output.
 */
function h(string $value, int $flags = ENT_QUOTES): string
{
    return htmlspecialchars($value, $flags, 'UTF-8');
}

/**
 * Strip all HTML tags from a string.
 */
function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

// ---- Currency -----------------------------------------------------------------

/**
 * Format a number as Indonesian Rupiah (Rp X.XXX).
 */
function format_rupiah(int|float $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

// ---- Dates & Times ------------------------------------------------------------

/**
 * Get current datetime in Indonesia Western Time (UTC+7).
 */
function now_wib(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
}

/**
 * Format a date for display (17 Juni 2026 — Indonesian locale style).
 */
function format_date(string $date, string $format = 'd M Y'): string
{
    $converted = new DateTime($date);
    // Indonesian months
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $day  = $converted->format('j');
    $mon  = (int) $converted->format('n') - 1;
    $year = $converted->format('Y');

    return sprintf('%d %s %s', $day, $months[$mon], $year);
}

/**
 * Get today's date in YYYYMMDD format (used for invoice numbering).
 */
function date_yyyymmdd(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Ymd');
}

// ---- Random Strings -----------------------------------------------------------

/**
 * Generate a random alphanumeric string of a given length.
 */
function random_string(int $length = 16): string
{
    return substr(bin2hex(random_bytes(max(1, (int) ceil($length / 2)))), 0, $length);
}

// ---- Logging Helpers ----------------------------------------------------------

/**
 * Write an activity log entry.
 * Expected by FASE 4 / 5.
 */
function log_activity(string $action, string $description, int $user_id = 0): bool
{
    global $pdo; // Will be defined once auth.php sets it up; kept minimal here
    if (!isset($pdo)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        return true;
    } catch (Exception) {
        return false;
    }
}
