<?php
/**
 * BookMyCourt — CSRF Protection
 *
 * Provides token generation and validation for all state-changing forms.
 * All POST forms must include: <?php csrfField(); ?>
 * All POST handlers must call: csrfVerify();
 */

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__));

/**
 * Generate (or retrieve existing) CSRF token for this session.
 */
function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field.
 * Call inside any <form> that modifies state.
 */
function csrfField(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate the submitted CSRF token.
 * Call at the top of any POST handler.
 * Dies with 403 if token is missing or invalid.
 */
function csrfVerify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if (empty($submitted) || empty($expected) || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        // Rotate the token to prevent replay
        unset($_SESSION['csrf_token']);
        die(renderError(403, 'Invalid or expired security token. Please go back and try again.'));
    }
}

/**
 * Render a simple inline error page (used only for security/fatal errors).
 */
function renderError(int $code, string $message): string
{
    return "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>
    <title>Error $code</title>
    <style>body{font-family:sans-serif;background:#0a0f1e;color:#f1f5f9;display:flex;
    align-items:center;justify-content:center;min-height:100vh;margin:0;}
    .box{text-align:center;padding:40px;}h1{color:#ef4444;font-size:48px;}
    p{color:#94a3b8;}a{color:#3b82f6;}</style>
    </head><body><div class='box'>
    <h1>$code</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>
    <a href='javascript:history.back()'>← Go Back</a></div></body></html>";
}
