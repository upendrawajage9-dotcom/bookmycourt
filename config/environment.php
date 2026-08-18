<?php
/**
 * BookMyCourt — Environment Loader
 * 
 * Loads .env file into constants and $_ENV superglobal.
 * Must be included before database.php and any page logic.
 */

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__));

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        // .env missing — fall back to system env vars (useful in production)
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip inline comments
        if (($pos = strpos($value, ' #')) !== false) {
            $value = trim(substr($value, 0, $pos));
        }

        // Strip surrounding quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Load the .env file
loadEnv(ROOT_PATH . '/.env');

// ─── Convenience helper ───────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ─── Global app config constants ─────────────────────────────
define('APP_ENV',   env('APP_ENV',   'production'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define('APP_NAME',  env('APP_NAME',  'BookMyCourt'));
define('APP_URL',   env('APP_URL',   'http://localhost'));

// Production safety: disable error display when not in debug mode
if (!APP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
