<?php
/**
 * BookMyCourt — Shared HTML Header & Navigation
 *
 * Include at the top of every user-facing page (not admin).
 * Requires:
 *   $pageTitle  (string) — sets <title> tag
 *   $pageClass  (string, optional) — extra body class
 */

// Determine notification count if user is logged in
$notifCount = 0;
if (!empty($_SESSION['user_id'])) {
    try {
        $notifCount = unreadNotificationCount((int) $_SESSION['user_id']);
    } catch (Exception $e) {
        $notifCount = 0;
    }
}

$currentPage  = basename($_SERVER['PHP_SELF']);
$isLoggedIn   = !empty($_SESSION['user_id']);
$isAdmin      = !empty($_SESSION['admin_id']) && !empty($_SESSION['admin']);
$userName     = $isLoggedIn ? e($_SESSION['user_name']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($pageTitle ?? 'BookMyCourt'); ?> — BookMyCourt</title>
<meta name="description" content="<?php echo e($pageDescription ?? 'Book verified badminton courts across Pune with real-time slot availability.'); ?>">
<meta name="theme-color" content="#0a0f1e">

<!-- Preconnect for performance -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- Stylesheets -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
<?php if (!empty($pageCSS)): ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/<?php echo e($pageCSS); ?>">
<?php endif; ?>
</head>

<body class="<?php echo e($pageClass ?? ''); ?>">

<!-- ─── Navigation ─────────────────────────────────────────── -->
<nav class="site-nav" id="siteNav">
  <div class="nav-container">

    <!-- Logo -->
    <a href="<?php echo BASE_URL; ?>/index.php" class="nav-brand">
      <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="BookMyCourt" class="nav-logo-img">
      <span class="nav-brand-text">BookMyCourt</span>
    </a>

    <!-- Desktop Links -->
    <div class="nav-links" id="navLinks">
      <a href="<?php echo BASE_URL; ?>/courts.php"
         class="nav-link <?php echo $currentPage === 'courts.php' ? 'active' : ''; ?>">
        Courts
      </a>

      <?php if ($isLoggedIn): ?>
      <a href="<?php echo BASE_URL; ?>/my-bookings.php"
         class="nav-link <?php echo $currentPage === 'my-bookings.php' ? 'active' : ''; ?>">
        My Bookings
      </a>
      <?php endif; ?>
    </div>

    <!-- Right Side -->
    <div class="nav-actions">
      <?php if ($isLoggedIn): ?>

        <!-- Notification Bell -->
        <a href="<?php echo BASE_URL; ?>/notifications.php" class="nav-icon-btn" title="Notifications">
          <i class="bi bi-bell"></i>
          <?php if ($notifCount > 0): ?>
          <span class="notif-badge"><?php echo $notifCount > 9 ? '9+' : $notifCount; ?></span>
          <?php endif; ?>
        </a>

        <!-- User Dropdown -->
        <div class="nav-dropdown" id="userDropdown">
          <button class="nav-avatar-btn" onclick="toggleDropdown()" aria-haspopup="true">
            <span class="avatar-initials"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></span>
            <span class="avatar-name"><?php echo $userName; ?></span>
            <i class="bi bi-chevron-down dropdown-arrow"></i>
          </button>
          <div class="dropdown-menu" id="dropdownMenu" role="menu">
            <a href="<?php echo BASE_URL; ?>/profile.php" class="dropdown-item">
              <i class="bi bi-person-circle"></i> Profile
            </a>
            <a href="<?php echo BASE_URL; ?>/my-bookings.php" class="dropdown-item">
              <i class="bi bi-calendar-check"></i> My Bookings
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-item text-danger">
              <i class="bi bi-box-arrow-right"></i> Logout
            </a>
          </div>
        </div>

      <?php elseif ($isAdmin): ?>

        <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="btn btn-sm btn-outline-accent">
          Admin Panel
        </a>
        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm btn-ghost">Logout</a>

      <?php else: ?>

        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-ghost btn-sm">Login</a>
        <a href="<?php echo BASE_URL; ?>/login.php?tab=signup" class="btn btn-accent btn-sm">Get Started</a>

      <?php endif; ?>

      <!-- Mobile Hamburger -->
      <button class="nav-hamburger" id="navHamburger" onclick="toggleMobileNav()" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>

  </div>
</nav>

<!-- Spacer so page content doesn't go under fixed nav -->
<div class="nav-spacer"></div>

<!-- ─── Flash Messages ──────────────────────────────────────── -->
<?php
$flashSuccess = flashGet('success');
$flashError   = flashGet('error');
$flashInfo    = flashGet('info');
if ($flashSuccess || $flashError || $flashInfo): ?>
<div class="flash-container" id="flashContainer">
  <?php if ($flashSuccess): ?>
  <div class="flash flash-success">
    <i class="bi bi-check-circle-fill"></i>
    <span><?php echo e($flashSuccess); ?></span>
    <button onclick="this.parentElement.remove()" class="flash-close"><i class="bi bi-x"></i></button>
  </div>
  <?php endif; ?>
  <?php if ($flashError): ?>
  <div class="flash flash-error">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span><?php echo e($flashError); ?></span>
    <button onclick="this.parentElement.remove()" class="flash-close"><i class="bi bi-x"></i></button>
  </div>
  <?php endif; ?>
  <?php if ($flashInfo): ?>
  <div class="flash flash-info">
    <i class="bi bi-info-circle-fill"></i>
    <span><?php echo e($flashInfo); ?></span>
    <button onclick="this.parentElement.remove()" class="flash-close"><i class="bi bi-x"></i></button>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
