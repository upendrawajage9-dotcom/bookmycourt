<?php
/**
 * BookMyCourt — Notifications API
 *
 * GET  /api/notifications.php         — list unread notifications
 * POST /api/notifications.php         — mark notifications as read
 */

require_once dirname(__DIR__) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=UTF-8');

$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return unread notifications
    try {
        $pdo  = db();
        $stmt = $pdo->prepare(
            "SELECT id, title, message, type, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 20"
        );
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();

        $unreadCount = array_reduce($notifications, fn($carry, $n) => $carry + ($n['is_read'] ? 0 : 1), 0);

        jsonResponse([
            'error'         => false,
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ]);
    } catch (PDOException $e) {
        error_log('[BookMyCourt] notifications GET error: ' . $e->getMessage());
        jsonResponse(['error' => true, 'message' => 'Could not load notifications.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $action = sanitize($_POST['action'] ?? '');

    try {
        $pdo = db();

        if ($action === 'mark_all_read') {
            $pdo->prepare(
                "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE"
            )->execute([$userId]);
            jsonResponse(['error' => false, 'message' => 'All notifications marked as read.']);
        }

        if ($action === 'mark_read' && !empty($_POST['notification_id'])) {
            $notifId = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
            if ($notifId) {
                $pdo->prepare(
                    "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?"
                )->execute([$notifId, $userId]);
                jsonResponse(['error' => false]);
            }
        }

    } catch (PDOException $e) {
        error_log('[BookMyCourt] notifications POST error: ' . $e->getMessage());
        jsonResponse(['error' => true, 'message' => 'Could not update notification.'], 500);
    }
}

jsonResponse(['error' => true, 'message' => 'Method not allowed.'], 405);
