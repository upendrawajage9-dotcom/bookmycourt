<?php
/**
 * BookMyCourt — Payment Verification API
 *
 * POST /api/payment-verify.php
 *
 * Called by frontend after Razorpay payment completes.
 * Server-side verifies HMAC-SHA256 signature before marking booking as CONFIRMED.
 *
 * IMPORTANT SECURITY NOTE:
 * The signature is the ONLY trusted signal of payment success.
 * We NEVER trust the frontend's claim that a payment succeeded.
 * The razorpay_payment_id sent from frontend is useless without valid signature.
 *
 * Razorpay signature algorithm:
 *   signature = HMAC_SHA256(razorpay_order_id + '|' + razorpay_payment_id, key_secret)
 */

require_once dirname(__DIR__) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => true, 'message' => 'Method not allowed.'], 405);
}

csrfVerify();

$userId = currentUserId();

// ─── Input ────────────────────────────────────────────────────
$bookingId        = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$razorpayOrderId  = sanitize($_POST['razorpay_order_id']  ?? '');
$razorpayPaymentId= sanitize($_POST['razorpay_payment_id'] ?? '');
$razorpaySignature= sanitize($_POST['razorpay_signature']  ?? '');

if (!$bookingId || empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
    jsonResponse(['error' => true, 'message' => 'Invalid payment response. Missing required fields.'], 400);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    // ─── Verify booking belongs to this user ──────────────────
    $bookingStmt = $pdo->prepare(
        "SELECT b.id, b.user_id, b.venue_id, b.individual_court_id,
                b.booking_date, b.time_slot, b.total_price, b.status,
                p.id AS payment_id, p.razorpay_order_id, p.status AS payment_status
         FROM bookings b
         LEFT JOIN payments p ON p.booking_id = b.id
         WHERE b.id = ? AND b.user_id = ?
         FOR UPDATE"
    );
    $bookingStmt->execute([$bookingId, $userId]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        jsonResponse(['error' => true, 'message' => 'Booking not found.'], 404);
    }

    if ($booking['status'] === 'CONFIRMED') {
        // Already confirmed — idempotent response
        $pdo->rollBack();
        jsonResponse([
            'error'      => false,
            'already_confirmed' => true,
            'booking_id' => $bookingId,
            'message'    => 'Booking is already confirmed.',
        ]);
    }

    if ($booking['status'] === 'CANCELLED') {
        $pdo->rollBack();
        jsonResponse(['error' => true, 'message' => 'This booking has been cancelled.'], 400);
    }

    if ($booking['razorpay_order_id'] !== $razorpayOrderId) {
        $pdo->rollBack();
        error_log("[BookMyCourt] Order ID mismatch for booking $bookingId: expected {$booking['razorpay_order_id']}, got $razorpayOrderId");
        jsonResponse(['error' => true, 'message' => 'Payment order mismatch. Invalid request.'], 400);
    }

    // ─── CRITICAL: Verify Razorpay Signature ──────────────────
    $keySecret = env('RAZORPAY_KEY_SECRET', '');
    $testMode  = (env('RAZORPAY_MODE', 'test') === 'test' || str_contains(env('RAZORPAY_KEY_ID', ''), 'REPLACE'));

    $isSignatureValid = false;

    if ($testMode && str_contains($razorpayOrderId, 'order_TEST_')) {
        // Test mode: accept any signature (Razorpay isn't involved)
        $isSignatureValid = true;
    } else {
        // Production: verify HMAC-SHA256 signature
        $expectedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            $keySecret
        );
        // Use constant-time comparison to prevent timing attacks
        $isSignatureValid = hash_equals($expectedSignature, $razorpaySignature);
    }

    if (!$isSignatureValid) {
        // Mark payment as failed
        $pdo->prepare(
            "UPDATE payments SET status = 'FAILED', failure_reason = 'Invalid payment signature'
             WHERE booking_id = ?"
        )->execute([$bookingId]);

        $pdo->commit();
        error_log("[BookMyCourt] Invalid Razorpay signature for booking $bookingId. Payment ID: $razorpayPaymentId");
        jsonResponse([
            'error'   => true,
            'message' => 'Payment verification failed. Your card has not been charged. Please contact support if you see a deduction.',
            'code'    => 'SIGNATURE_INVALID',
        ], 400);
    }

    // ─── Signature valid — confirm booking ────────────────────
    $pdo->prepare(
        "UPDATE bookings SET status = 'CONFIRMED', updated_at = NOW() WHERE id = ?"
    )->execute([$bookingId]);

    $pdo->prepare(
        "UPDATE payments
         SET status = 'SUCCESS',
             razorpay_payment_id = ?,
             razorpay_signature = ?,
             updated_at = NOW()
         WHERE booking_id = ?"
    )->execute([$razorpayPaymentId, $razorpaySignature, $bookingId]);

    // ─── Create notification for user ─────────────────────────
    notify(
        $userId,
        'Booking Confirmed!',
        "Your court has been booked for {$booking['booking_date']} at {$booking['time_slot']}. Booking ID: " . formatBookingId($bookingId),
        'success'
    );

    $pdo->commit();

    jsonResponse([
        'error'      => false,
        'booking_id' => $bookingId,
        'booking_ref'=> formatBookingId($bookingId),
        'message'    => 'Payment verified. Your booking is confirmed!',
        'redirect'   => BASE_URL . '/my-bookings.php?confirmed=' . $bookingId,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[BookMyCourt] payment-verify.php DB error: ' . $e->getMessage());
    jsonResponse(['error' => true, 'message' => 'Server error during payment verification. Please contact support with your payment ID.'], 500);
}
