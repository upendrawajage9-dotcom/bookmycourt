<?php
/**
 * BookMyCourt — My Bookings Page
 *
 * Tabbed view: Upcoming / Completed / Cancelled
 * With cancellation (POST + CSRF, server-side user validation).
 */

require_once __DIR__ . '/bootstrap.php';
requireLogin();

$userId = currentUserId();
$pageTitle = 'My Bookings';
$pageClass = 'page-bookings';

// ─── Handle Cancellation ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    csrfVerify();

    $cancelId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

    if ($cancelId) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Verify this booking belongs to this user and is CONFIRMED
            $stmt = $pdo->prepare(
                "SELECT id, status FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE"
            );
            $stmt->execute([$cancelId, $userId]);
            $booking = $stmt->fetch();

            if ($booking && $booking['status'] === 'CONFIRMED') {
                $pdo->prepare(
                    "UPDATE bookings SET status = 'CANCELLED', updated_at = NOW() WHERE id = ?"
                )->execute([$cancelId]);

                // If there's a payment, mark it for refund review
                $pdo->prepare(
                    "UPDATE payments SET status = 'REFUNDED', updated_at = NOW()
                     WHERE booking_id = ? AND status = 'SUCCESS'"
                )->execute([$cancelId]);

                notify($userId, 'Booking Cancelled',
                    'Your booking #' . formatBookingId($cancelId) . ' has been cancelled. Refund will be processed in 5-7 business days.',
                    'info'
                );

                $pdo->commit();
                flashSet('success', 'Booking ' . formatBookingId($cancelId) . ' has been cancelled. Refund (if any) will be processed in 5-7 business days.');
            } else {
                $pdo->rollBack();
                flashSet('error', 'This booking cannot be cancelled.');
            }

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[BookMyCourt] my-bookings cancellation error: ' . $e->getMessage());
            flashSet('error', 'Cancellation failed. Please try again.');
        }
    }

    redirect(BASE_URL . '/my-bookings.php');
}

// ─── Fetch Bookings ───────────────────────────────────────────
try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT b.id, b.booking_date, b.time_slot, b.total_price, b.status, b.created_at,
                ic.court_name,
                c.hall_name, c.location, c.id AS venue_id,
                p.status AS payment_status, p.razorpay_payment_id
         FROM bookings b
         JOIN individual_courts ic ON ic.id = b.individual_court_id
         JOIN courts c ON c.id = b.venue_id
         LEFT JOIN payments p ON p.booking_id = b.id
         WHERE b.user_id = ?
         ORDER BY b.booking_date DESC, b.created_at DESC"
    );
    $stmt->execute([$userId]);
    $allBookings = $stmt->fetchAll();

    // Separate by status
    $today    = date('Y-m-d');
    $upcoming = array_filter($allBookings, fn($b) => in_array($b['status'], ['CONFIRMED', 'PENDING']) && $b['booking_date'] >= $today);
    $completed= array_filter($allBookings, fn($b) => $b['status'] === 'COMPLETED' || ($b['status'] === 'CONFIRMED' && $b['booking_date'] < $today));
    $cancelled= array_filter($allBookings, fn($b) => $b['status'] === 'CANCELLED');

    // Booking stats
    $totalSpent = array_reduce(
        array_filter($allBookings, fn($b) => $b['payment_status'] === 'SUCCESS'),
        fn($carry, $b) => $carry + (float)$b['total_price'], 0
    );

} catch (PDOException $e) {
    error_log('[BookMyCourt] my-bookings.php error: ' . $e->getMessage());
    $allBookings = $upcoming = $completed = $cancelled = [];
    $totalSpent = 0;
}

// Active tab from query string
$activeTab = in_array($_GET['tab'] ?? '', ['completed', 'cancelled']) ? $_GET['tab'] : 'upcoming';

// Highlight confirmed booking (from payment redirect)
$confirmedId = filter_input(INPUT_GET, 'confirmed', FILTER_VALIDATE_INT);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.bookings-page { padding: var(--sp-8) 0 var(--sp-16); }
.bookings-header { margin-bottom: var(--sp-8); }
.bookings-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--sp-4);
  margin-bottom: var(--sp-8);
}
.bookings-tabs { margin-bottom: var(--sp-6); }
.booking-card {
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  padding: var(--sp-5) var(--sp-6);
  margin-bottom: var(--sp-4);
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--sp-5);
  align-items: center;
  transition: all var(--t-base);
}
.booking-card:hover { border-color: var(--c-border-hover); box-shadow: var(--shadow-md); }
.booking-card.highlighted {
  border-color: var(--c-success);
  box-shadow: 0 0 0 2px rgba(22,163,74,0.2);
  animation: pulse 2s ease 2;
}

.booking-card-icon {
  width: 52px; height: 52px;
  border-radius: var(--r-lg);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem;
}
.booking-card-info { min-width: 0; }
.booking-card-venue {
  font-size: 1rem; font-weight: 700; color: var(--c-text-bright); margin-bottom: 4px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.booking-card-meta { display: flex; flex-wrap: wrap; gap: var(--sp-4); }
.booking-meta-item { display: flex; align-items: center; gap: 5px; font-size: 0.8rem; color: var(--c-text-muted); }
.booking-meta-item i { color: var(--c-accent-light); }

.booking-card-actions { display: flex; flex-direction: column; align-items: flex-end; gap: var(--sp-3); flex-shrink: 0; }
.booking-card-id { font-size: 0.7rem; color: var(--c-text-muted); font-family: monospace; }
.booking-card-price { font-size: 1.125rem; font-weight: 800; color: var(--c-success-light); }

@media (max-width: 640px) {
  .booking-card { grid-template-columns: 1fr; }
  .booking-card-icon { display: none; }
  .booking-card-actions { align-items: flex-start; }
}
</style>

<div class="container bookings-page">

  <!-- Header -->
  <div class="bookings-header">
    <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-ghost btn-sm mb-4">
      <i class="bi bi-arrow-left"></i> Browse Courts
    </a>
    <h1 class="section-title mb-2">My Bookings</h1>
    <p style="color:var(--c-text-muted);">Track all your badminton court reservations in one place.</p>
  </div>

  <!-- Stats Row -->
  <div class="bookings-stats">
    <div class="stat-card">
      <div class="stat-icon stat-icon-blue"><i class="bi bi-calendar-check"></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Bookings</div>
        <div class="stat-value"><?php echo count($allBookings); ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-green"><i class="bi bi-clock"></i></div>
      <div class="stat-info">
        <div class="stat-label">Upcoming</div>
        <div class="stat-value"><?php echo count($upcoming); ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-purple"><i class="bi bi-currency-rupee"></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Spent</div>
        <div class="stat-value"><?php echo formatPrice($totalSpent); ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-red"><i class="bi bi-x-circle"></i></div>
      <div class="stat-info">
        <div class="stat-label">Cancelled</div>
        <div class="stat-value"><?php echo count($cancelled); ?></div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="bookings-tabs">
    <div class="tabs">
      <button class="tab-btn <?php echo $activeTab === 'upcoming'   ? 'active' : ''; ?>"
              onclick="switchBookingTab('upcoming')">
        Upcoming <span class="tab-count"><?php echo count($upcoming); ?></span>
      </button>
      <button class="tab-btn <?php echo $activeTab === 'completed'  ? 'active' : ''; ?>"
              onclick="switchBookingTab('completed')">
        Completed <span class="tab-count"><?php echo count($completed); ?></span>
      </button>
      <button class="tab-btn <?php echo $activeTab === 'cancelled'  ? 'active' : ''; ?>"
              onclick="switchBookingTab('cancelled')">
        Cancelled <span class="tab-count"><?php echo count($cancelled); ?></span>
      </button>
    </div>
  </div>

  <?php
  function renderBookings(array $bookings, string $tabType, int $userId, int|null $confirmedId): void {
    if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
      <h3>No <?php echo $tabType; ?> bookings</h3>
      <p>
        <?php if ($tabType === 'upcoming'): ?>
          You have no upcoming bookings. Browse courts to book your next game!
        <?php else: ?>
          No <?php echo $tabType; ?> bookings found.
        <?php endif; ?>
      </p>
      <?php if ($tabType === 'upcoming'): ?>
      <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-accent">Browse Courts</a>
      <?php endif; ?>
    </div>
    <?php return; endif;

    foreach ($bookings as $b):
      $statusBadge = statusBadgeClass($b['status']);
      $payBadge    = statusBadgeClass($b['payment_status'] ?? 'pending');
      $isHighlighted = $confirmedId && (int)$b['id'] === $confirmedId;
      $images      = getVenueImages((int)$b['venue_id']);
      $canCancel   = $b['status'] === 'CONFIRMED' && $b['booking_date'] >= date('Y-m-d');
  ?>
    <div class="booking-card animate-on-scroll <?php echo $isHighlighted ? 'highlighted' : ''; ?>">

      <!-- Icon -->
      <div class="booking-card-icon stat-icon-<?php echo $b['status'] === 'CONFIRMED' ? 'green' : ($b['status'] === 'CANCELLED' ? 'red' : 'yellow'); ?>">
        <i class="bi bi-<?php echo $b['status'] === 'CONFIRMED' ? 'check-circle' : ($b['status'] === 'CANCELLED' ? 'x-circle' : 'clock'); ?>"></i>
      </div>

      <!-- Info -->
      <div class="booking-card-info">
        <div class="booking-card-venue"><?php echo e($b['hall_name']); ?></div>
        <div class="booking-card-meta">
          <span class="booking-meta-item"><i class="bi bi-grid-3x3-gap"></i><?php echo e($b['court_name']); ?></span>
          <span class="booking-meta-item"><i class="bi bi-calendar3"></i><?php echo formatDate($b['booking_date']); ?></span>
          <span class="booking-meta-item"><i class="bi bi-clock"></i><?php echo e($b['time_slot']); ?></span>
          <span class="booking-meta-item"><i class="bi bi-geo-alt-fill"></i><?php echo e($b['location']); ?></span>
        </div>
        <div style="display:flex;gap:var(--sp-2);margin-top:var(--sp-3);">
          <span class="badge <?php echo $statusBadge; ?>"><?php echo ucfirst(strtolower($b['status'])); ?></span>
          <?php if ($b['payment_status']): ?>
          <span class="badge <?php echo $payBadge; ?>">
            <?php echo match($b['payment_status']) {
              'SUCCESS'  => 'Payment Done',
              'FAILED'   => 'Payment Failed',
              'REFUNDED' => 'Refund Pending',
              default    => 'Payment Pending',
            }; ?>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Actions -->
      <div class="booking-card-actions">
        <div class="booking-card-id"><?php echo formatBookingId((int)$b['id']); ?></div>
        <div class="booking-card-price"><?php echo formatPrice((float)$b['total_price']); ?></div>
        <?php if ($canCancel): ?>
        <form method="POST" onsubmit="return confirm('Cancel booking <?php echo formatBookingId((int)$b['id']); ?>? This cannot be undone.')">
          <?php csrfField(); ?>
          <input type="hidden" name="cancel_booking" value="1">
          <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
          <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger-light);border-color:var(--c-danger);">
            <i class="bi bi-x-circle"></i> Cancel
          </button>
        </form>
        <?php endif; ?>
      </div>

    </div>
  <?php endforeach;
  }
  ?>

  <!-- Upcoming Tab -->
  <div id="tab-upcoming" class="tab-content <?php echo $activeTab === 'upcoming' ? 'active' : ''; ?>">
    <?php renderBookings(array_values($upcoming), 'upcoming', $userId, $confirmedId); ?>
  </div>

  <!-- Completed Tab -->
  <div id="tab-completed" class="tab-content <?php echo $activeTab === 'completed' ? 'active' : ''; ?>">
    <?php renderBookings(array_values($completed), 'completed', $userId, $confirmedId); ?>
  </div>

  <!-- Cancelled Tab -->
  <div id="tab-cancelled" class="tab-content <?php echo $activeTab === 'cancelled' ? 'active' : ''; ?>">
    <?php renderBookings(array_values($cancelled), 'cancelled', $userId, $confirmedId); ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
function switchBookingTab(tab) {
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
        btn.classList.toggle('active', ['upcoming','completed','cancelled'][i] === tab);
    });
    document.querySelectorAll('.tab-content').forEach(c => {
        c.classList.toggle('active', c.id === 'tab-' + tab);
    });
}
</script>

<style>
.tab-count {
  background: rgba(255,255,255,0.1);
  border-radius: var(--r-full);
  padding: 1px 7px;
  font-size: 0.7rem;
  margin-left: 4px;
}
</style>
