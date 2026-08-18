<?php
/**
 * BookMyCourt — Favorites API
 *
 * POST /api/favorites.php
 * action=add&venue_id=X    — add to favorites
 * action=remove&venue_id=X — remove from favorites
 */

require_once dirname(__DIR__) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => true, 'message' => 'Method not allowed.'], 405);
}

csrfVerify();

$userId  = currentUserId();
$action  = sanitize($_POST['action'] ?? '');
$venueId = filter_input(INPUT_POST, 'venue_id', FILTER_VALIDATE_INT);

if (!in_array($action, ['add', 'remove']) || !$venueId || $venueId <= 0) {
    jsonResponse(['error' => true, 'message' => 'Invalid request.'], 400);
}

try {
    $pdo = db();

    // Verify venue exists
    $venueStmt = $pdo->prepare("SELECT id FROM courts WHERE id = ? AND is_active = TRUE");
    $venueStmt->execute([$venueId]);
    if (!$venueStmt->fetch()) {
        jsonResponse(['error' => true, 'message' => 'Venue not found.'], 404);
    }

    if ($action === 'add') {
        // Upsert: ignore if already exists
        $pdo->prepare(
            "INSERT INTO favorites (user_id, venue_id) VALUES (?, ?)
             ON CONFLICT (user_id, venue_id) DO NOTHING"
        )->execute([$userId, $venueId]);
        jsonResponse(['error' => false, 'action' => 'added']);
    } else {
        $pdo->prepare(
            "DELETE FROM favorites WHERE user_id = ? AND venue_id = ?"
        )->execute([$userId, $venueId]);
        jsonResponse(['error' => false, 'action' => 'removed']);
    }

} catch (PDOException $e) {
    error_log('[BookMyCourt] favorites.php error: ' . $e->getMessage());
    jsonResponse(['error' => true, 'message' => 'Server error.'], 500);
}
