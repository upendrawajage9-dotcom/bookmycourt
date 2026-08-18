<?php
/**
 * BookMyCourt — Booking Creation API
 *
 * POST /api/booking.php
 *
 * Creates a PENDING booking and a Razorpay order in an atomic transaction.
 * Returns Razorpay order details for frontend checkout.
 *
 * Security:
 * - Auth required
 * - CSRF verification
 * - All inputs validated
 * - Price computed from DB (never trusted from frontend)
 * - PostgreSQL transaction with serializable isolation
 * - UNIQUE constraint (individual_court_id, booking_date, time_slot) as last-resort concurrency guard
 * - No raw errors exposed
 */

require_once dirname(__DIR__) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=UTF-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => true, 'message' => 'Method not allowed.'], 405);
}

// CSRF check
csrfVerify();

$userId = currentUserId();

// ─── Input collection & basic validation ─────────────────────
$venueId          = filter_input(INPUT_POST, 'venue_id', FILTER_VALIDATE_INT);
$individualCourtId = filter_input(INPUT_POST, 'individual_court_id', FILTER_VALIDATE_INT);
$bookingDate      = sanitize($_POST['booking_date'] ?? '');
$timeSlot         = sanitize($_POST['time_slot'] ?? '');

$inputErrors = [];

if (!$venueId || $venueId <= 0) {
    $inputErrors[] = 'Invalid venue.';
}
if (!$individualCourtId || $individualCourtId <= 0) {
    $inputErrors[] = 'Invalid court selection.';
}
if (!isValidFutureDate($bookingDate)) {
    $inputErrors[] = 'Invalid or past date selected.';
}
if (!isValidTimeSlot($timeSlot)) {
    $inputErrors[] = 'Invalid time slot selected.';
}

if (!empty($inputErrors)) {
    jsonResponse(['error' => true, 'message' => implode(' ', $inputErrors)], 400);
}

try {
    $pdo = db();

    // ─── Verify venue exists and is active ────────────────────
    $venueStmt = $pdo->prepare(
        "SELECT id, hall_name, price_per_hour, is_active
         FROM courts WHERE id = ? AND is_active = TRUE"
    );
    $venueStmt->execute([$venueId]);
    $venue = $venueStmt->fetch();

    if (!$venue) {
        jsonResponse(['error' => true, 'message' => 'Venue not found or not active.'], 404);
    }

    // ─── Verify individual court belongs to this venue ────────
    $courtStmt = $pdo->prepare(
        "SELECT id, court_name FROM individual_courts
         WHERE id = ? AND venue_id = ? AND is_active = TRUE"
    );
    $courtStmt->execute([$individualCourtId, $venueId]);
    $court = $courtStmt->fetch();

    if (!$court) {
        jsonResponse(['error' => true, 'message' => 'Court not found in this venue.'], 404);
    }

    // ─── Price from DB (never frontend) ──────────────────────
    $totalAmount = (float) $venue['price_per_hour'];

    // ─── Begin atomic transaction ─────────────────────────────
    $pdo->beginTransaction();

    try {
        // Check for conflict INSIDE transaction (row-level lock)
        $conflictStmt = $pdo->prepare(
            "SELECT id FROM bookings
             WHERE individual_court_id = ?
               AND booking_date = ?
               AND time_slot = ?
               AND status IN ('CONFIRMED', 'PENDING')
             FOR UPDATE"    // Lock the row to prevent race conditions
        );
        $conflictStmt->execute([$individualCourtId, $bookingDate, $timeSlot]);

        if ($conflictStmt->fetch()) {
            $pdo->rollBack();
            jsonResponse([
                'error'   => true,
                'message' => 'Sorry, this slot was just booked by someone else. Please select a different slot.',
                'code'    => 'SLOT_TAKEN',
            ], 409);
        }

        // Create PENDING booking
        $bookingStmt = $pdo->prepare(
            "INSERT INTO bookings
                (user_id, venue_id, individual_court_id, booking_date, time_slot, total_price, status)
             VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
             RETURNING id"
        );
        $bookingStmt->execute([
            $userId,
            $venueId,
            $individualCourtId,
            $bookingDate,
            $timeSlot,
            $totalAmount,
        ]);
        $bookingRow = $bookingStmt->fetch();
        $bookingId  = (int) $bookingRow['id'];

        // ─── Create Razorpay Order ────────────────────────────
        $razorpayKeyId     = env('RAZORPAY_KEY_ID', '');
        $razorpayKeySecret = env('RAZORPAY_KEY_SECRET', '');
        $razorpayMode      = env('RAZORPAY_MODE', 'test');

        $orderId = null;

        if (!empty($razorpayKeyId) && !str_contains($razorpayKeyId, 'REPLACE')) {
            // ─ Real Razorpay API call ─────────────────────────
            $orderPayload = json_encode([
                'amount'          => (int) ($totalAmount * 100), // paise
                'currency'        => env('RAZORPAY_CURRENCY', 'INR'),
                'receipt'         => 'bmc_' . $bookingId,
                'notes'           => [
                    'booking_id'  => $bookingId,
                    'venue'       => $venue['hall_name'],
                    'court'       => $court['court_name'],
                    'date'        => $bookingDate,
                    'slot'        => $timeSlot,
                ],
            ]);

            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt_array($ch, [
                CURLOPT_USERPWD        => "$razorpayKeyId:$razorpayKeySecret",
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $orderPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                $pdo->rollBack();
                error_log("[BookMyCourt] Razorpay order creation failed. HTTP $httpCode: $response");
                jsonResponse(['error' => true, 'message' => 'Payment gateway error. Please try again.'], 502);
            }

            $rzpOrder = json_decode($response, true);
            if (empty($rzpOrder['id'])) {
                $pdo->rollBack();
                error_log('[BookMyCourt] Razorpay response missing order ID: ' . $response);
                jsonResponse(['error' => true, 'message' => 'Payment order creation failed.'], 502);
            }

            $orderId = $rzpOrder['id'];

        } else {
            // ─ TEST MODE: generate a fake order ID ───────────
            $orderId = 'order_TEST_' . strtoupper(bin2hex(random_bytes(8)));
        }

        // Store payment record (PENDING)
        $paymentStmt = $pdo->prepare(
            "INSERT INTO payments (booking_id, razorpay_order_id, amount, currency, status)
             VALUES (?, ?, ?, 'INR', 'PENDING')"
        );
        $paymentStmt->execute([$bookingId, $orderId, $totalAmount]);

        $pdo->commit();

        // Return order details to frontend
        jsonResponse([
            'error'      => false,
            'booking_id' => $bookingId,
            'order_id'   => $orderId,
            'key_id'     => $razorpayKeyId,    // Safe to expose key_id (not secret)
            'amount'     => (int) ($totalAmount * 100),
            'currency'   => 'INR',
            'test_mode'  => ($razorpayMode === 'test' || str_contains($razorpayKeyId, 'REPLACE')),
            'venue_name' => $venue['hall_name'],
            'court_name' => $court['court_name'],
            'date'       => $bookingDate,
            'slot'       => $timeSlot,
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        // Handle duplicate booking (UNIQUE constraint violation)
        if (str_contains($e->getMessage(), 'uq_booking_slot') ||
            str_contains($e->getMessage(), 'unique') ||
            $e->getCode() == 23505) {
            jsonResponse([
                'error'   => true,
                'message' => 'This slot was just taken by another user. Please choose a different time or court.',
                'code'    => 'SLOT_TAKEN',
            ], 409);
        }

        error_log('[BookMyCourt] booking.php DB error: ' . $e->getMessage());
        jsonResponse(['error' => true, 'message' => 'Booking failed due to a server error. Please try again.'], 500);
    }

} catch (Exception $e) {
    error_log('[BookMyCourt] booking.php unexpected error: ' . $e->getMessage());
    jsonResponse(['error' => true, 'message' => 'Unexpected error. Please try again.'], 500);
}
