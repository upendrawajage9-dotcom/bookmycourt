<?php
/**
 * BookMyCourt — Admin Authentication Guard
 *
 * requireAdmin() — ensures an admin session exists, redirects to login if not.
 * Call at the very top of every admin page.
 */

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__));

function requireAdmin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['admin_id']) || $_SESSION['admin'] !== true) {
        // Clear any partial session state
        session_destroy();

        // Redirect to login with a flash hint
        header('Location: ' . BASE_URL . '/login.php?msg=admin_required');
        exit();
    }
}

/**
 * Return the current admin's ID (integer), or null.
 */
function currentAdminId(): ?int
{
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

/**
 * Return the current admin's display name.
 */
function currentAdminName(): string
{
    return $_SESSION['admin_name'] ?? 'Admin';
}
