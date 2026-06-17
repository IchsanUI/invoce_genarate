<?php
/**
 * Logout
 *
 * Destroys the session and redirects to the login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

start_session();

// Log out (destroy session) — then redirect.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

// Start fresh for the redirect
session_start();
header('Location: index.php');
exit;
