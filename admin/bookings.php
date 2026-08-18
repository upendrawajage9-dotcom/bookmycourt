<?php
/**
 * BookMyCourt — Admin Bookings Management
 *
 * View, search, filter, and manage all bookings.
 * Allows admin to confirm pending bookings or cancel confirmed ones.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
requireAdmin();

$pageTitle = 'Manage Bookings';

// ─── Admin Actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrfVerify();

    $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $action    = sanitize($_POST['action']);

    if ($bookingId && in_array($action, ['confirm', 'cancel'])) {
        try {
            $pdo = db();
            $newStatus = $action === 'confirm' ? 'CONFIRMED' : 'CANCELLED';

            $pdo->prepare(
                "UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$newStatus, $bookingId]);

            flashSet('success', "Booking #$bookingId has been $newStatus.");
        } catch (PDOException $e) {
            error_log('[BookMyCourt Admin] bookings action error: ' . $e->getMessage());
            flashSet('error', 'Action failed.');
        }
    }
    redirect(BASE_URL . '/admin/bookings.php');
}

// ─── Filters ──────────────────────────────────────────────────
$search   = sanitize($_GET['search']   ?? '');
$status   = sanitize($_GET['status']   ?? '');
$venueId  = filter_input(INPUT_GET, 'venue_id', FILTER_VALIDATE_INT) ?: 0;
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo   = sanitize($_GET['date_to']   ?? '');
$page     = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

try {
    $pdo = db();

    $where  = ['1=1'];
    $params = [];

    if ($search) {
        $where[]  = "(u.full_name ILIKE ? OR u.phone ILIKE ? OR c.hall_name ILIKE ?)";
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($status) {
        $where[]  = "b.status = ?";
        $params[] = strtoupper($status);
    }
    if ($venueId > 0) {
        $where[]  = "b.venue_id = ?";
        $params[] = $venueId;
    }
    if ($dateFrom) {
        $where[]  = "b.booking_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[]  = "b.booking_date <= ?";
        $params[] = $dateTo;
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        JOIN courts c ON c.id = b.venue_id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    $stmt = $pdo->prepare("
        SELECT b.id, b.booking_date, b.time_slot, b.total_price, b.status, b.created_at,
               u.full_name, u.phone, u.email,
               c.hall_name, ic.court_name, c.id AS venue_id,
               p.status AS payment_status, p.razorpay_payment_id, p.amount AS paid_amount
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        JOIN courts c ON c.id = b.venue_id
        JOIN individual_courts ic ON ic.id = b.individual_court_id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE $whereClause
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $bookings = $stmt->fetchAll();

    // Venues for filter
    $venues = $pdo->query("SELECT id, hall_name FROM courts WHERE is_active = TRUE ORDER BY hall_name")->fetchAll();

} catch (PDOException $e) {
    error_log('[BookMyCourt Admin] bookings.php error: ' . $e->getMessage());
    $bookings = $venues = [];
    $totalCount = $totalPages = 0;
}

$flashSuccess = flashGet('success');
$flashError   = flashGet('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bookings — Admin · BookMyCourt</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<!-- Sidebar (same as dashboard) -->
<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-sidebar-logo">
    <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="Logo" width="32" height="32">
    <div>
      <div class="admin-brand">BookMyCourt</div>
      <div class="admin-brand-sub">Admin Panel</div>
    </div>
  </div>
  <nav class="admin-nav">
    <div class="admin-nav-label">Main</div>
    <a href="dashboard.php" class="admin-nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    <a href="bookings.php"  class="admin-nav-link active"><i class="bi bi-calendar-check-fill"></i> Bookings</a>
    <a href="venues.php"    class="admin-nav-link"><i class="bi bi-building-fill"></i> Venues</a>
    <a href="courts.php"    class="admin-nav-link"><i class="bi bi-grid-3x3-gap-fill"></i> Courts</a>
    <a href="users.php"     class="admin-nav-link"><i class="bi bi-people-fill"></i> Users</a>
    <div class="admin-nav-label" style="margin-top:var(--sp-4);">Finance</div>
    <a href="payments.php"  class="admin-nav-link"><i class="bi bi-credit-card-2-front-fill"></i> Payments</a>
    <a href="reports.php"   class="admin-nav-link"><i class="bi bi-bar-chart-fill"></i> Reports</a>
    <div class="admin-nav-label" style="margin-top:var(--sp-4);">Content</div>
    <a href="reviews.php"   class="admin-nav-link"><i class="bi bi-chat-quote-fill"></i> Reviews</a>
  </nav>
  <div class="admin-sidebar-footer">
    <a href="<?php echo BASE_URL; ?>/logout.php" class="admin-nav-link" style="color:var(--c-danger-light);">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>

<div class="admin-main">
  <header class="admin-topbar">
    <button class="admin-menu-btn" onclick="document.getElementById('adminSidebar').classList.toggle('open')"><i class="bi bi-list"></i></button>
    <div class="admin-topbar-title">Booking Management</div>
    <div style="margin-left:auto;">
      <span class="badge badge-secondary"><?php echo $totalCount; ?> total records</span>
    </div>
  </header>

  <div class="admin-content">

    <?php if ($flashSuccess): ?>
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?php echo e($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="alert alert-error mb-4"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($flashError); ?></div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="admin-panel mb-5">
      <div class="admin-panel-header">
        <h3 class="admin-panel-title"><i class="bi bi-funnel"></i> Filter Bookings</h3>
      </div>
      <div style="padding: var(--sp-5);">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:var(--sp-4);align-items:end;">
          <div>
            <label class="form-label">Search (User/Phone/Venue)</label>
            <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Search...">
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="">All</option>
              <option value="CONFIRMED" <?php echo $status === 'CONFIRMED' ? 'selected' : ''; ?>>Confirmed</option>
              <option value="PENDING"   <?php echo $status === 'PENDING'   ? 'selected' : ''; ?>>Pending</option>
              <option value="CANCELLED" <?php echo $status === 'CANCELLED' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
          </div>
          <div>
            <label class="form-label">Venue</label>
            <select name="venue_id" class="form-control">
              <option value="">All Venues</option>
              <?php foreach ($venues as $v): ?>
              <option value="<?php echo $v['id']; ?>" <?php echo $venueId === (int)$v['id'] ? 'selected' : ''; ?>><?php echo e($v['hall_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo e($dateFrom); ?>">
          </div>
          <div>
            <label class="form-label">To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo e($dateTo); ?>">
          </div>
          <div>
            <button type="submit" class="btn btn-accent">Filter</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Bookings Table -->
    <div class="admin-panel">
      <div class="table-wrapper" style="border:none;border-radius:0;">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Venue / Court</th>
              <th>Date / Slot</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Booked At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bookings)): ?>
            <tr><td colspan="9" style="text-align:center;padding:var(--sp-10);color:var(--c-text-muted);">No bookings match your filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($bookings as $b): ?>
            <tr>
              <td style="font-family:monospace;font-size:0.8rem;"><?php echo formatBookingId((int)$b['id']); ?></td>
              <td>
                <div class="cell-primary"><?php echo e($b['full_name']); ?></div>
                <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo e($b['phone']); ?></div>
              </td>
              <td>
                <div class="cell-primary" style="font-size:0.875rem;"><?php echo e($b['hall_name']); ?></div>
                <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo e($b['court_name']); ?></div>
              </td>
              <td>
                <div><?php echo formatDate($b['booking_date']); ?></div>
                <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo e($b['time_slot']); ?></div>
              </td>
              <td class="cell-primary" style="color:var(--c-success-light);"><?php echo formatPrice((float)$b['total_price']); ?></td>
              <td><span class="badge <?php echo statusBadgeClass($b['status']); ?>"><?php echo ucfirst(strtolower($b['status'])); ?></span></td>
              <td>
                <?php if ($b['payment_status']): ?>
                <span class="badge <?php echo statusBadgeClass($b['payment_status'] ?? 'pending'); ?>">
                  <?php echo match($b['payment_status']) { 'SUCCESS' => 'Paid', 'FAILED' => 'Failed', 'REFUNDED' => 'Refunded', default => 'Pending' }; ?>
                </span>
                <?php else: ?>
                <span class="badge badge-secondary">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem;color:var(--c-text-muted);"><?php echo date('d M, H:i', strtotime($b['created_at'])); ?></td>
              <td>
                <div style="display:flex;gap:var(--sp-2);">
                  <?php if ($b['status'] === 'PENDING'): ?>
                  <form method="POST" style="display:inline;">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                    <button type="submit" class="btn btn-success btn-sm">Confirm</button>
                  </form>
                  <?php endif; ?>
                  <?php if (in_array($b['status'], ['CONFIRMED', 'PENDING'])): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking?')">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div style="display:flex;align-items:center;justify-content:center;gap:var(--sp-2);padding:var(--sp-5);border-top:1px solid var(--c-border);">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"
           class="btn btn-sm <?php echo $p === $page ? 'btn-accent' : 'btn-outline'; ?>">
          <?php echo $p; ?>
        </a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<div id="toastContainer" class="toast-container"></div>
</body>
</html>
