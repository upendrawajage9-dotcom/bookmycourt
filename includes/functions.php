<?php
/**
 * BookMyCourt — Shared Utility Functions
 *
 * Contains reusable helpers used across all pages.
 * All functions are pure (no side effects) unless documented otherwise.
 */

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__));

// ─── Output Security ─────────────────────────────────────────

/**
 * Safely HTML-encode a value for output in HTML context.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize and trim a string from user input.
 */
function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

// ─── Formatting ───────────────────────────────────────────────

/**
 * Format a price in Indian Rupees.
 * e.g. 1200 → "₹1,200"
 */
function formatPrice(float|int $amount): string
{
    return '₹' . number_format($amount, 0, '.', ',');
}

/**
 * Format a date for display.
 * e.g. "2025-03-15" → "15 Mar 2025"
 */
function formatDate(string $dateStr): string
{
    $ts = strtotime($dateStr);
    return $ts !== false ? date('d M Y', $ts) : $dateStr;
}

/**
 * Format a date with day of week.
 * e.g. "2025-03-15" → "Saturday, 15 Mar 2025"
 */
function formatDateLong(string $dateStr): string
{
    $ts = strtotime($dateStr);
    return $ts !== false ? date('l, d M Y', $ts) : $dateStr;
}

/**
 * Clamp a numeric value between min and max.
 */
function clamp(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

// ─── Images ──────────────────────────────────────────────────

/**
 * Get the web-accessible path for a venue image.
 * Handles case-insensitive file extension (jpg / JPG / jpeg).
 */
function getVenueImage(int $venueId, int $imageNum, string $baseUrl = ''): string
{
    $extensions = ['jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG'];
    $basePath   = ROOT_PATH . "/assets/images/courts/court{$venueId}/";
    $webBase    = ($baseUrl ?: BASE_URL) . "/assets/images/courts/court{$venueId}/";

    foreach ($extensions as $ext) {
        if (file_exists($basePath . $imageNum . '.' . $ext)) {
            return $webBase . $imageNum . '.' . $ext;
        }
    }

    // Fallback to logo
    return ($baseUrl ?: BASE_URL) . '/assets/images/logo.png';
}

/**
 * Return an array of 3 image URLs for a venue.
 */
function getVenueImages(int $venueId, string $baseUrl = ''): array
{
    return [
        getVenueImage($venueId, 1, $baseUrl),
        getVenueImage($venueId, 2, $baseUrl),
        getVenueImage($venueId, 3, $baseUrl),
    ];
}

// ─── Booking ─────────────────────────────────────────────────

/**
 * Generate a formatted booking reference number.
 * e.g. 42 → "BMC-000042"
 */
function formatBookingId(int $id): string
{
    return 'BMC-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

/**
 * Determine the CSS status class for a booking/payment status.
 */
function statusBadgeClass(string $status): string
{
    return match (strtolower($status)) {
        'confirmed', 'success', 'completed' => 'badge-success',
        'pending'                            => 'badge-warning',
        'cancelled', 'failed'               => 'badge-danger',
        'refunded'                           => 'badge-info',
        default                              => 'badge-secondary',
    };
}

// ─── Notifications ────────────────────────────────────────────

/**
 * Create an in-app notification for a user.
 * Side effect: inserts a row into notifications table.
 */
function notify(int $userId, string $title, string $message, string $type = 'info'): void
{
    try {
        $pdo  = db();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $title, $message, $type]);
    } catch (Exception $e) {
        error_log('[BookMyCourt] notify() failed: ' . $e->getMessage());
    }
}

/**
 * Get unread notification count for a user.
 */
function unreadNotificationCount(int $userId): int
{
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// ─── Validation ───────────────────────────────────────────────

/**
 * Validate that a date string is a valid future date (YYYY-MM-DD).
 */
function isValidFutureDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $ts = strtotime($date);
    return $ts !== false && $ts >= strtotime(date('Y-m-d'));
}

/**
 * Validate a time slot string against the allowed set.
 */
function isValidTimeSlot(string $slot): bool
{
    $allowed = [
        '6:00-7:00 AM', '7:00-8:00 AM', '8:00-9:00 AM',
        '9:00-10:00 AM', '10:00-11:00 AM', '11:00-12:00 PM',
        '4:00-5:00 PM', '5:00-6:00 PM', '6:00-7:00 PM',
        '7:00-8:00 PM', '8:00-9:00 PM', '9:00-10:00 PM',
    ];
    return in_array($slot, $allowed, true);
}

/**
 * Validate a phone number (Indian format: 10 digits).
 */
function isValidPhone(string $phone): bool
{
    return (bool) preg_match('/^[6-9]\d{9}$/', $phone);
}

/**
 * Validate an email address.
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ─── Session Flash Messages ───────────────────────────────────

/**
 * Set a flash message to be shown on the next page load.
 */
function flashSet(string $key, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'][$key] = $message;
}

/**
 * Get and clear a flash message.
 */
function flashGet(string $key): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// ─── Response Helpers ─────────────────────────────────────────

/**
 * Send a JSON response and exit. Used in API endpoints.
 */
function jsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): never
{
    header("Location: $url");
    exit();
}
