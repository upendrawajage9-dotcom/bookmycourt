<?php
/**
 * BookMyCourt — Database Connection
 *
 * Returns a persistent PDO connection using environment variables.
 * Uses a singleton pattern so we never open more than one connection per request.
 *
 * Supports:
 *   1. DATABASE_URL (Railway / Heroku style)
 *   2. Individual DB_* env vars
 *   3. Railway PG* env vars as fallback
 * 
 * Usage:
 *   $pdo = db();
 *   $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
 *   $stmt->execute([$id]);
 *   $row = $stmt->fetch();
 */

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/environment.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // 1. Try DATABASE_URL first (Railway / Heroku format)
    $databaseUrl = env('DATABASE_URL', '');

    if (!empty($databaseUrl)) {
        $parsed = parse_url($databaseUrl);
        $host     = $parsed['host']   ?? 'localhost';
        $port     = $parsed['port']   ?? 5432;
        $dbname   = ltrim($parsed['path'] ?? '/badminton_booking', '/');
        $user     = $parsed['user']   ?? 'postgres';
        $password = $parsed['pass']   ?? '';
    } else {
        // 2. Individual DB_* vars, with Railway PG* as fallback
        $host     = env('DB_HOST',     env('PGHOST',     'localhost'));
        $port     = env('DB_PORT',     env('PGPORT',     '5432'));
        $dbname   = env('DB_NAME',     env('PGDATABASE', 'badminton_booking'));
        $user     = env('DB_USER',     env('PGUSER',     'postgres'));
        $password = env('DB_PASSWORD', env('PGPASSWORD', ''));
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
            PDO::ATTR_PERSISTENT         => false,   // no persistent connections (safer)
        ]);

        // Enforce UTF-8 and timezone
        $pdo->exec("SET NAMES 'UTF8'");
        $pdo->exec("SET TIME ZONE 'Asia/Kolkata'");

    } catch (PDOException $e) {
        // Log the real error server-side, show a safe message to user
        error_log('[BookMyCourt DB] Connection failed: ' . $e->getMessage());

        // Do NOT expose connection details
        http_response_code(503);
        die(json_encode([
            'error' => true,
            'message' => 'Database connection failed. Please try again later.'
        ]));
    }

    return $pdo;
}

