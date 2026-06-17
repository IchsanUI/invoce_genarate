<?php
/**
 * Authentication Helper
 *
 * Session management, login/logout, role checking middleware.
 * Used by every protected page in FASE 2–5.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

// ---- Session Bootstrap --------------------------------------------------------

/**
 * Start the session with secure settings.
 * Must be called ONCE at the very top of every script (before any output).
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false, // will become true in Fase 9 (HTTPS)
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Refresh session: create a new session ID and regenerate the ID.
 * Call this on every successful login.
 */
function refresh_session(): void
{
    $old = $_SESSION ?? [];
    session_regenerate_id(true);
    $_SESSION = $old;
    $_SESSION['_last_refresh'] = time();
}

// ---- Login / Logout -----------------------------------------------------------

/**
 * Mark the current user as logged in and record last_login.
 */
function set_login_session(int $user_id, string $username, string $role, string $full_name): void
{
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id']   = $user_id;
    $_SESSION['username']  = $username;
    $_SESSION['role']      = $role;       // 'admin' or 'superadmin'
    $_SESSION['full_name'] = $full_name;
    $_SESSION['_created']  = time();
}

/**
 * Destroy the session and redirect to login page.
 */
function logout(string $redirect = 'index.php'): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . $redirect);
    exit;
}

// ---- Middleware ---------------------------------------------------------------

/**
 * Require the user to be logged in. If not, redirect to login.
 */
function require_login(): void
{
    if (empty($_SESSION['logged_in']) !== true) {
        // Session exists but logged_in is missing/false
    }

    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }

    // Session idle?
    if (isset($_SESSION['_created']) && (time() - $_SESSION['_created']) > SESSION_LIFETIME) {
        logout();
    }
}

/**
 * Require the user to have a specific role.
 * Usage: require_role('superadmin')  — only super admins can proceed.
 */
function require_role(string $role): void
{
    require_login();

    if ($_SESSION['role'] !== $role) {
        // Admin trying to access super-admin area? Redirect to own dashboard.
        header('Location: dashboard.php');
        exit;
    }
}

// ---- Authenticated helper functions -------------------------------------------

/**
 * Return true if user is logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Return true if current user is superadmin.
 */
function is_super_admin(): bool
{
    return is_logged_in() && $_SESSION['role'] === 'superadmin';
}

/**
 * Return current logged-in user's ID, or 0.
 */
function current_user_id(): int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : 0;
}

/**
 * Return current logged-in user's full name, or empty string.
 */
function current_user_name(): string
{
    return is_logged_in() ? (string) $_SESSION['full_name'] : '';
}

// ---- Login Attempt Tracking (Rate-Limit) -------------------------------------

/**
 * Returns the count of consecutive failed login attempts for a given username
 * within the last LOCKOUT_MINUTES window.
 *
 * @return int Number of failed attempts (0 if none)
 */
function get_failed_attempts(string $username): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `activity_logs`
         WHERE `action` = 'login_failed'
           AND `description` LIKE ?
           AND `created_at` >= (NOW() - INTERVAL ? MINUTE)"
    );
    // LIKE pattern to match by username in description
    $pattern = '%username=' . $username . '%';
    $stmt->execute([$pattern, LOCKOUT_MINUTES]);
    return (int) $stmt->fetchColumn();
}

/**
 * Returns the time (UNIX timestamp) when the lockout will expire for the
 * given username, or 0 if not currently locked out.
 */
function lockout_expires_at(string $username): int
{
    $attempts = get_failed_attempts($username);
    if ($attempts < MAX_LOGIN_ATTEMPTS) {
        return 0;
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT UNIX_TIMESTAMP(MAX(`created_at`)) + (? * 60)
         FROM `activity_logs`
         WHERE `action` = 'login_failed'
           AND `description` LIKE ?
           AND `created_at` >= (NOW() - INTERVAL ? MINUTE)"
    );
    $pattern = '%username=' . $username . '%';
    $stmt->execute([LOCKOUT_MINUTES, $pattern, LOCKOUT_MINUTES]);
    return (int) $stmt->fetchColumn();
}

/**
 * Returns true if the given username is currently locked out.
 */
function is_locked_out(string $username): bool
{
    $expiresAt = lockout_expires_at($username);
    if ($expiresAt === 0) {
        return false;
    }
    return time() < $expiresAt;
}

/**
 * Returns the number of seconds until the lockout expires, or 0 if not locked out.
 */
function lockout_seconds_remaining(string $username): int
{
    $expiresAt = lockout_expires_at($username);
    if ($expiresAt === 0) {
        return 0;
    }
    $remaining = $expiresAt - time();
    return $remaining > 0 ? $remaining : 0;
}

// ---- Login --------------------------------------------------------------------

/**
 * Attempt to authenticate a user with username + password.
 *
 * Side effects (on success):
 *   - Refresh session ID
 *   - Mark session as logged in
 *   - Update last_login in users table
 *   - Log a 'login_success' activity
 *
 * On failure:
 *   - Log a 'login_failed' activity (used for rate-limit counting)
 *   - Do NOT log the password (privacy)
 *
 * @return array{ok: bool, user?: array, error?: string, locked_for?: int}
 */
function attempt_login(string $username, string $password): array
{
    $username = trim($username);
    $password = (string) $password;

    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'Username dan password wajib diisi.'];
    }

    // Check lockout BEFORE attempting to find the user (avoid timing leak)
    if (is_locked_out($username)) {
        return [
            'ok'         => false,
            'error'      => 'Akun terkunci sementara karena terlalu banyak percobaan gagal.',
            'locked_for' => lockout_seconds_remaining($username),
        ];
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT `id`, `username`, `password_hash`, `full_name`, `role`, `is_active`
         FROM `users`
         WHERE `username` = ?
         LIMIT 1"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Always run password_verify, even if user not found, to prevent
    // timing-based user enumeration. Compare against a known-bad hash.
    $dummyHash = '$2y$12$0000000000000000000000000000000000000000000000000000';
    $hashToCheck = $user['password_hash'] ?? $dummyHash;

    if (!$user || !password_verify($password, $hashToCheck)) {
        log_login_failure($username);
        return ['ok' => false, 'error' => 'Username atau password salah.'];
    }

    if ((int) $user['is_active'] !== 1) {
        log_login_failure($username, 'inactive');
        return ['ok' => false, 'error' => 'Akun ini nonaktif. Hubungi super admin.'];
    }

    // SUCCESS: refresh session, set logged-in flags, update last_login
    refresh_session();
    set_login_session(
        (int) $user['id'],
        (string) $user['username'],
        (string) $user['role'],
        (string) $user['full_name']
    );

    $stmt = $pdo->prepare("UPDATE `users` SET `last_login` = NOW() WHERE `id` = ?");
    $stmt->execute([(int) $user['id']]);

    log_login_success($user['username'], $user['role']);

    return ['ok' => true, 'user' => $user];
}

/**
 * Log a failed login attempt.
 */
function log_login_failure(string $username, string $reason = 'bad_credentials'): void
{
    $pdo = db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $description = sprintf(
        'username=%s reason=%s',
        $username,
        $reason
    );
    $stmt = $pdo->prepare(
        "INSERT INTO `activity_logs` (`user_id`, `action`, `description`, `ip_address`, `created_at`)
         VALUES (NULL, 'login_failed', ?, ?, NOW())"
    );
    $stmt->execute([$description, $ip]);
}

/**
 * Log a successful login.
 */
function log_login_success(string $username, string $role): void
{
    $pdo = db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $description = sprintf('username=%s role=%s', $username, $role);
    $stmt = $pdo->prepare(
        "INSERT INTO `activity_logs` (`user_id`, `action`, `description`, `ip_address`, `created_at`)
         VALUES ((SELECT `id` FROM `users` WHERE `username` = ?), 'login_success', ?, ?, NOW())"
    );
    $stmt->execute([$username, $description, $ip]);
}
