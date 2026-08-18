<?php
/**
 * BookMyCourt — Application Bootstrap
 *
 * This single file bootstraps everything needed for any page.
 * Every PHP page should start with: require_once __DIR__ . '/bootstrap.php';
 *
 * For pages in subdirectories (admin/, api/), use the ROOT_PATH constant.
 */

// Define the project root (parent of this file)
defined('ROOT_PATH') || define('ROOT_PATH', __DIR__);

// Load environment first
require_once ROOT_PATH . '/config/environment.php';

// Define BASE_URL from environment (used in links and redirects)
define('BASE_URL', rtrim(env('APP_URL', 'http://localhost/BookMyCourt'), '/'));

// Load database connector
require_once ROOT_PATH . '/config/database.php';

// Load shared utilities
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/admin_auth.php';

// Configure secure session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,   // Set to true in production with HTTPS
        'httponly' => true,    // Prevent JS access to session cookie
        'samesite' => 'Strict',
    ]);
    session_start();
}
