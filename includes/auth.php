<?php
/**
 * BookMyCourt — Authentication Guards
 *
 * requireLogin()  — ensures a user session exists, redirects to login if not.
 * requireGuest()  — redirects logged-in users away from login/register pages.
 */

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__));

/**
 * Enforce that a valid user session exists.
 * Call at the top of every user-protected page.
 */
function requireLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        // Store intended destination for post-login redirect
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

/**
 * Redirect already-authenticated users away from login/register.
 */
function requireGuest(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/courts.php');
        exit();
    }

    if (!empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit();
    }
}

/**
 * Return the current user ID (integer), or null if not logged in.
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Return the current user's display name.
 */
function currentUserName(): string
{
    return $_SESSION['user_name'] ?? 'User';
}
