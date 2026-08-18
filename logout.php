<?php
/**
 * BookMyCourt — Secure Logout
 *
 * Destroys the session completely and redirects to login.
 * This is intentionally minimal — no HTML output, just server action.
 */

require_once __DIR__ . '/bootstrap.php';

// 1. Unset all session variables
$_SESSION = [];

// 2. Delete the session cookie from client
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]
    );
}

// 3. Destroy the session on server
session_destroy();

// 4. Redirect to login with a success flash
// Use a fresh session only to carry the flash message
session_start();
$_SESSION['flash']['success'] = 'You have been logged out successfully.';
session_write_close();

header('Location: ' . BASE_URL . '/login.php');
exit();
