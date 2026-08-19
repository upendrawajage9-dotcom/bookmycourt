<?php
/**
 * BookMyCourt — Availability API
 *
 * GET /api/availability.php?venue_id=X&date=YYYY-MM-DD&individual_court_id=Y
 *
 * Returns JSON: list of time slots with booked/available status
 * for a specific individual court on a specific date.
 *
 * Also supports: ?venue_id=X&date=Y (returns all courts for that venue)
 *
 * Security:
 * - Auth required (must be logged in)
 * - All inputs validated and parameterized
 * - No raw DB errors exposed
 */

require_once dirname(__DIR__) . '/bootstrap.php';
// Note: Read-only availability check does not require login (booking creation does)

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache');

// All available time slots in the system
$allSlots = [
    '6:00-7:00 AM', '7:00-8:00 AM', '8:00-9:00 AM',
    '9:00-10:00 AM', '10:00-11:00 AM', '11:00-12:00 PM',
    '4:00-5:00 PM', '5:00-6:00 PM', '6:00-7:00 PM',
    '7:00-8:00 PM', '8:00-9:00 PM', '9:00-10:00 PM',
];

// ─── Input Validation ─────────────────────────────────────────
$venueId          = filter_input(INPUT_GET, 'venue_id', FILTER_VALIDATE_INT);
$date             = trim($_GET['date'] ?? '');
$individualCourtId = filter_input(INPUT_GET, 'individual_court_id', FILTER_VALIDATE_INT);

if (!$venueId || $venueId <= 0) {
    jsonResponse(['error' => true, 'message' => 'Invalid venue ID.'], 400);
}

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

try {
    $pdo = db();

    // ─── Fetch venue ──────────────────────────────────────────
    $stmt = $pdo->prepare("SELECT id, hall_name FROM courts WHERE id = ?");
    $stmt->execute([$venueId]);
    $venue = $stmt->fetch();

    if (!$venue) {
        $venue = ['id' => $venueId, 'hall_name' => 'Venue'];
    }

    if ($individualCourtId) {
        // ─── Single court: return slot availability ───────────
        $courtStmt = $pdo->prepare("SELECT id, court_name, court_number FROM individual_courts WHERE id = ?");
        $courtStmt->execute([$individualCourtId]);
        $court = $courtStmt->fetch();

        if (!$court) {
            $court = ['id' => $individualCourtId, 'court_name' => 'Court'];
        }

        // Get all booked slots for this court on this date
        $bookedSlots = [];
        try {
            $bookedStmt = $pdo->prepare(
                "SELECT time_slot FROM bookings
                 WHERE individual_court_id = ?
                   AND booking_date = ?
                   AND status IN ('CONFIRMED', 'PENDING')"
            );
            $bookedStmt->execute([$individualCourtId, $date]);
            $bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            error_log('[BookMyCourt] Booked slots fetch warning: ' . $e->getMessage());
        }

        // Build slot data
        $slots = [];
        foreach ($allSlots as $slot) {
            $slots[] = [
                'slot'     => $slot,
                'is_booked' => in_array($slot, $bookedSlots, true),
            ];
        }

        jsonResponse([
            'error'   => false,
            'venue'   => ['id' => $venue['id'], 'name' => $venue['hall_name']],
            'court'   => ['id' => $court['id'], 'name' => $court['court_name']],
            'date'    => $date,
            'slots'   => $slots,
        ]);

    } else {
        // ─── All courts for venue: return court-level summary ─

        $courtStmt = $pdo->prepare(
            "SELECT ic.id, ic.court_name, ic.court_number,
                    COUNT(b.id) AS booked_count
             FROM individual_courts ic
             LEFT JOIN bookings b
                ON b.individual_court_id = ic.id
               AND b.booking_date = ?
               AND b.status IN ('CONFIRMED', 'PENDING')
             WHERE ic.venue_id = ? AND ic.is_active = TRUE
             GROUP BY ic.id, ic.court_name, ic.court_number
             ORDER BY ic.court_number"
        );
        $courtStmt->execute([$date, $venueId]);
        $courts = $courtStmt->fetchAll();

        $totalSlots = count($allSlots);
        $courtData = [];
        foreach ($courts as $c) {
            $bookedCount = (int) $c['booked_count'];
            $courtData[] = [
                'id'            => (int) $c['id'],
                'name'          => $c['court_name'],
                'number'        => (int) $c['court_number'],
                'booked_slots'  => $bookedCount,
                'free_slots'    => $totalSlots - $bookedCount,
                'is_fully_booked' => ($bookedCount >= $totalSlots),
            ];
        }

        jsonResponse([
            'error'  => false,
            'venue'  => ['id' => $venue['id'], 'name' => $venue['hall_name']],
            'date'   => $date,
            'courts' => $courtData,
        ]);
    }

} catch (PDOException $e) {
    error_log('[BookMyCourt] availability.php error: ' . $e->getMessage());
    jsonResponse(['error' => true, 'message' => 'Server error. Please try again.'], 500);
}
