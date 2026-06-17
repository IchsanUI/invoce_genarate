<?php
/**
 * Authentication Helper
 *
 * Session management, login/logout, role checking middleware.
 * Used by every protected page in FASE 2–5.
 */

require_once __DIR__ . '/functions.php';

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
