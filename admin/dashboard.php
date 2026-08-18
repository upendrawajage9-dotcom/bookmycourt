<?php
/**
 * BookMyCourt — Admin Dashboard
 *
 * Requires admin session.
 * Shows key metrics, recent bookings, and quick actions.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
requireAdmin();

$pageTitle = 'Admin Dashboard';

try {
    $pdo = db();

    // Key Metrics
    $metrics = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM users WHERE is_active = TRUE)                          AS total_users,
            (SELECT COUNT(*) FROM courts WHERE is_active = TRUE)                         AS total_venues,
            (SELECT COUNT(*) FROM bookings WHERE status = 'CONFIRMED')                   AS total_bookings,
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'SUCCESS')      AS total_revenue,
            (SELECT COUNT(*) FROM bookings WHERE status = 'CONFIRMED'
               AND booking_date = CURRENT_DATE)                                          AS todays_bookings,
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'SUCCESS'
               AND DATE_TRUNC('month', created_at) = DATE_TRUNC('month', NOW()))         AS month_revenue,
            (SELECT COUNT(*) FROM bookings WHERE status = 'CANCELLED')                   AS cancelled_count,
            (SELECT COUNT(*) FROM bookings WHERE status = 'PENDING')                     AS pending_count
    ")->fetch();

    // Recent Bookings (last 10)
    $recentBookings = $pdo->query("
        SELECT b.id, b.booking_date, b.time_slot, b.total_price, b.status, b.created_at,
               u.full_name AS user_name, u.phone,
               c.hall_name AS venue_name,
               ic.court_name,
               p.status AS payment_status
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        JOIN courts c ON c.id = b.venue_id
        JOIN individual_courts ic ON ic.id = b.individual_court_id
        LEFT JOIN payments p ON p.booking_id = b.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ")->fetchAll();

    // Revenue by venue (top 5)
    $venueRevenue = $pdo->query("
        SELECT c.hall_name, COUNT(b.id) AS booking_count,
               COALESCE(SUM(p.amount), 0) AS revenue
        FROM courts c
        LEFT JOIN bookings b ON b.venue_id = c.id AND b.status = 'CONFIRMED'
        LEFT JOIN payments p ON p.booking_id = b.id AND p.status = 'SUCCESS'
        GROUP BY c.id, c.hall_name
        ORDER BY revenue DESC
        LIMIT 5
    ")->fetchAll();

    // Bookings per day (last 7 days)
    $dailyBookings = $pdo->query("
        SELECT booking_date::text AS day,
               COUNT(*) AS count,
               COALESCE(SUM(p.amount), 0) AS revenue
        FROM bookings b
        LEFT JOIN payments p ON p.booking_id = b.id AND p.status = 'SUCCESS'
        WHERE b.booking_date >= CURRENT_DATE - INTERVAL '6 days'
          AND b.status = 'CONFIRMED'
        GROUP BY booking_date
        ORDER BY booking_date
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[BookMyCourt Admin] dashboard error: ' . $e->getMessage());
    $metrics = ['total_users'=>0,'total_venues'=>0,'total_bookings'=>0,'total_revenue'=>0,'todays_bookings'=>0,'month_revenue'=>0,'cancelled_count'=>0,'pending_count'=>0];
    $recentBookings = $venueRevenue = $dailyBookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($pageTitle); ?> — BookMyCourt Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<!-- ─── Admin Sidebar ─────────────────────────────────────────── -->
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
    <a href="dashboard.php"   class="admin-nav-link active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    <a href="bookings.php"    class="admin-nav-link"><i class="bi bi-calendar-check-fill"></i> Bookings</a>
    <a href="venues.php"      class="admin-nav-link"><i class="bi bi-building-fill"></i> Venues</a>
    <a href="courts.php"      class="admin-nav-link"><i class="bi bi-grid-3x3-gap-fill"></i> Courts</a>
    <a href="users.php"       class="admin-nav-link"><i class="bi bi-people-fill"></i> Users</a>

    <div class="admin-nav-label" style="margin-top:var(--sp-4);">Finance</div>
    <a href="payments.php"    class="admin-nav-link"><i class="bi bi-credit-card-2-front-fill"></i> Payments</a>
    <a href="reports.php"     class="admin-nav-link"><i class="bi bi-bar-chart-fill"></i> Reports</a>

    <div class="admin-nav-label" style="margin-top:var(--sp-4);">Content</div>
    <a href="reviews.php"     class="admin-nav-link"><i class="bi bi-chat-quote-fill"></i> Reviews</a>
  </nav>

  <div class="admin-sidebar-footer">
    <div class="admin-user-info">
      <div class="avatar-initials" style="width:34px;height:34px;font-size:0.875rem;">
        <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
      </div>
      <div>
        <div style="font-size:0.875rem;font-weight:600;"><?php echo e($_SESSION['admin_name'] ?? 'Admin'); ?></div>
        <div style="font-size:0.75rem;color:var(--c-text-muted);">Administrator</div>
      </div>
    </div>
    <a href="<?php echo BASE_URL; ?>/logout.php" class="admin-nav-link" style="margin-top:var(--sp-3);color:var(--c-danger-light);">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>

<!-- ─── Admin Main ────────────────────────────────────────────── -->
<div class="admin-main">

  <!-- Topbar -->
  <header class="admin-topbar">
    <button class="admin-menu-btn" onclick="toggleAdminSidebar()">
      <i class="bi bi-list"></i>
    </button>
    <div class="admin-topbar-title">Dashboard Overview</div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:var(--sp-4);">
      <span style="font-size:0.875rem;color:var(--c-text-muted);">
        <?php echo date('l, d F Y'); ?>
      </span>
      <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline btn-sm" target="_blank">
        <i class="bi bi-box-arrow-up-right"></i> View Site
      </a>
    </div>
  </header>

  <div class="admin-content">

    <!-- ─── Metric Cards ─────────────────────────────────────── -->
    <div class="metrics-grid">
      <div class="stat-card animate-on-scroll">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-info">
          <div class="stat-label">Total Revenue</div>
          <div class="stat-value"><?php echo formatPrice((float)$metrics['total_revenue']); ?></div>
          <div class="stat-change up"><i class="bi bi-arrow-up"></i> <?php echo formatPrice((float)$metrics['month_revenue']); ?> this month</div>
        </div>
      </div>

      <div class="stat-card animate-on-scroll delay-1">
        <div class="stat-icon stat-icon-green"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info">
          <div class="stat-label">Total Bookings</div>
          <div class="stat-value"><?php echo (int)$metrics['total_bookings']; ?></div>
          <div class="stat-change up"><i class="bi bi-arrow-up"></i> <?php echo (int)$metrics['todays_bookings']; ?> today</div>
        </div>
      </div>

      <div class="stat-card animate-on-scroll delay-2">
        <div class="stat-icon stat-icon-purple"><i class="bi bi-people"></i></div>
        <div class="stat-info">
          <div class="stat-label">Registered Users</div>
          <div class="stat-value"><?php echo (int)$metrics['total_users']; ?></div>
        </div>
      </div>

      <div class="stat-card animate-on-scroll delay-3">
        <div class="stat-icon stat-icon-yellow"><i class="bi bi-building"></i></div>
        <div class="stat-info">
          <div class="stat-label">Active Venues</div>
          <div class="stat-value"><?php echo (int)$metrics['total_venues']; ?></div>
        </div>
      </div>

      <div class="stat-card animate-on-scroll delay-1">
        <div class="stat-icon stat-icon-red"><i class="bi bi-x-circle"></i></div>
        <div class="stat-info">
          <div class="stat-label">Cancelled</div>
          <div class="stat-value"><?php echo (int)$metrics['cancelled_count']; ?></div>
        </div>
      </div>

      <div class="stat-card animate-on-scroll delay-2">
        <div class="stat-icon stat-icon-cyan"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-info">
          <div class="stat-label">Pending</div>
          <div class="stat-value"><?php echo (int)$metrics['pending_count']; ?></div>
          <?php if ($metrics['pending_count'] > 0): ?>
          <div class="stat-change down"><i class="bi bi-exclamation-triangle"></i> Needs attention</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ─── Charts + Tables ──────────────────────────────────── -->
    <div class="admin-two-col">

      <!-- Recent Bookings -->
      <div class="admin-panel">
        <div class="admin-panel-header">
          <h3 class="admin-panel-title"><i class="bi bi-calendar-check"></i> Recent Bookings</h3>
          <a href="bookings.php" class="btn btn-ghost btn-sm">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="table-wrapper" style="border:none;border-radius:0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>User</th>
                <th>Venue / Court</th>
                <th>Date & Slot</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentBookings as $b): ?>
              <tr>
                <td style="font-family:monospace;font-size:0.8rem;"><?php echo formatBookingId((int)$b['id']); ?></td>
                <td>
                  <div class="cell-primary"><?php echo e($b['user_name']); ?></div>
                  <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo e($b['phone']); ?></div>
                </td>
                <td>
                  <div class="cell-primary" style="font-size:0.875rem;"><?php echo e($b['venue_name']); ?></div>
                  <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo e($b['court_name']); ?></div>
                </td>
                <td>
                  <div><?php echo formatDate($b['booking_date']); ?></div>
                  <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo e($b['time_slot']); ?></div>
                </td>
                <td class="cell-primary" style="color:var(--c-success-light);"><?php echo formatPrice((float)$b['total_price']); ?></td>
                <td><span class="badge <?php echo statusBadgeClass($b['status']); ?>"><?php echo ucfirst(strtolower($b['status'])); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top Venues by Revenue -->
      <div class="admin-panel">
        <div class="admin-panel-header">
          <h3 class="admin-panel-title"><i class="bi bi-trophy"></i> Top Venues by Revenue</h3>
        </div>
        <div style="padding: 0 var(--sp-5) var(--sp-5);">
          <?php foreach ($venueRevenue as $i => $vr): ?>
          <div class="venue-revenue-row">
            <div class="venue-revenue-rank"><?php echo $i + 1; ?></div>
            <div class="venue-revenue-info">
              <div class="cell-primary"><?php echo e($vr['hall_name']); ?></div>
              <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo (int)$vr['booking_count']; ?> bookings</div>
            </div>
            <div class="venue-revenue-amount" style="color:var(--c-success-light);">
              <?php echo formatPrice((float)$vr['revenue']); ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

  </div><!-- /.admin-content -->
</div><!-- /.admin-main -->

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
function toggleAdminSidebar() {
    document.getElementById('adminSidebar').classList.toggle('open');
}

// Intersection observer for animations
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
    }, { threshold: 0.1 });
    document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));
}

function showToast(msg, type='info', dur=4000) {
    const icons = { success: 'bi-check-circle-fill', error: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill', warning: 'bi-exclamation-circle-fill' };
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="bi ${icons[type]||icons.info}"></i><span>${msg}</span><button onclick="this.parentElement.remove()" class="toast-close"><i class="bi bi-x"></i></button>`;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, dur);
}
</script>
</body>
</html>
