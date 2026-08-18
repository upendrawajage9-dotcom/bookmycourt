<?php
/**
 * BookMyCourt — User Profile Page
 */
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$userId    = currentUserId();
$pageTitle = 'My Profile';
$pageClass = 'page-profile';

$formErrors  = [];
$formSuccess = '';

// ─── Handle Profile Update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrfVerify();

    $fullName = sanitize($_POST['full_name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');

    if (strlen($fullName) < 2) $formErrors['full_name'] = 'Name must be at least 2 characters.';
    if (!empty($email) && !isValidEmail($email)) $formErrors['email'] = 'Invalid email format.';
    if (!isValidPhone($phone)) $formErrors['phone'] = 'Invalid phone number.';

    if (empty($formErrors)) {
        try {
            $pdo = db();
            // Check uniqueness (exclude current user)
            $check = $pdo->prepare(
                "SELECT id FROM users WHERE (phone = ? OR (email = ? AND email != '')) AND id != ?"
            );
            $check->execute([$phone, $email, $userId]);
            if ($check->fetch()) {
                $formErrors['general'] = 'Phone or email already in use by another account.';
            } else {
                $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?"
                )->execute([$fullName, $email ?: null, $phone, $userId]);

                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email']= $email;
                $_SESSION['user_phone']= $phone;
                $formSuccess = 'Profile updated successfully.';
            }
        } catch (PDOException $e) {
            error_log('[BookMyCourt] profile update error: ' . $e->getMessage());
            $formErrors['general'] = 'Update failed. Please try again.';
        }
    }
}

// ─── Handle Password Change ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrfVerify();

    $currentPw = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPw, $user['password_hash'])) {
            $formErrors['current_password'] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 8) {
            $formErrors['new_password'] = 'New password must be at least 8 characters.';
        } elseif ($newPw !== $confirmPw) {
            $formErrors['confirm_password'] = 'Passwords do not match.';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
            $formSuccess = 'Password changed successfully.';
        }
    } catch (PDOException $e) {
        error_log('[BookMyCourt] password change error: ' . $e->getMessage());
        $formErrors['general'] = 'Password change failed.';
    }
}

// ─── Fetch user data + stats ──────────────────────────────────
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $stats = $pdo->prepare(
        "SELECT
            COUNT(*) FILTER (WHERE status = 'CONFIRMED') AS confirmed_count,
            COUNT(*) FILTER (WHERE status = 'CANCELLED') AS cancelled_count,
            COALESCE(SUM(p.amount) FILTER (WHERE p.status = 'SUCCESS'), 0) AS total_spent,
            COUNT(DISTINCT b.venue_id) AS unique_venues
         FROM bookings b
         LEFT JOIN payments p ON p.booking_id = b.id
         WHERE b.user_id = ?"
    );
    $stats->execute([$userId]);
    $userStats = $stats->fetch();

    // Favorite venues
    $favStmt = $pdo->prepare(
        "SELECT c.id, c.hall_name, c.location, c.price_per_hour
         FROM favorites f JOIN courts c ON c.id = f.venue_id
         WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 4"
    );
    $favStmt->execute([$userId]);
    $favorites = $favStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BookMyCourt] profile.php error: ' . $e->getMessage());
    $user = ['full_name' => currentUserName(), 'email' => '', 'phone' => ''];
    $userStats = ['confirmed_count' => 0, 'cancelled_count' => 0, 'total_spent' => 0, 'unique_venues' => 0];
    $favorites = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.profile-page { padding: var(--sp-8) 0 var(--sp-16); }
.profile-layout { display: grid; grid-template-columns: 300px 1fr; gap: var(--sp-8); align-items: start; }
.profile-sidebar { position: sticky; top: 80px; }
.profile-avatar {
  width: 80px; height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--c-accent), var(--c-accent-light));
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem; font-weight: 800; color: white;
  margin: 0 auto var(--sp-4);
}
.profile-name { font-size: 1.25rem; font-weight: 700; text-align: center; margin-bottom: 4px; }
.profile-member { font-size: 0.8rem; color: var(--c-text-muted); text-align: center; }
.profile-stats { display: grid; grid-template-columns: 1fr 1fr; gap: var(--sp-3); margin-top: var(--sp-5); }
.profile-stat { background: var(--c-surface-2); border: 1px solid var(--c-border); border-radius: var(--r-lg); padding: var(--sp-4); text-align: center; }
.profile-stat-value { font-size: 1.5rem; font-weight: 800; color: var(--c-text-bright); line-height: 1; }
.profile-stat-label { font-size: 0.7rem; color: var(--c-text-muted); margin-top: 3px; }
.form-section { margin-bottom: var(--sp-8); }
.form-section-title { font-size: 1.125rem; font-weight: 700; margin-bottom: var(--sp-5); padding-bottom: var(--sp-4); border-bottom: 1px solid var(--c-border); display: flex; align-items: center; gap: var(--sp-2); }
.form-section-title i { color: var(--c-accent-light); }
@media (max-width: 768px) {
  .profile-layout { grid-template-columns: 1fr; }
  .profile-sidebar { position: static; }
}
</style>

<div class="container profile-page">
  <div class="profile-layout">

    <!-- Sidebar -->
    <aside class="profile-sidebar">
      <div class="card">
        <div class="card-body" style="text-align:center;padding:var(--sp-8) var(--sp-6);">
          <div class="profile-avatar">
            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
          </div>
          <div class="profile-name"><?php echo e($user['full_name']); ?></div>
          <div class="profile-member">
            Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
          </div>
          <div class="profile-stats">
            <div class="profile-stat">
              <div class="profile-stat-value"><?php echo (int)$userStats['confirmed_count']; ?></div>
              <div class="profile-stat-label">Bookings</div>
            </div>
            <div class="profile-stat">
              <div class="profile-stat-value"><?php echo (int)$userStats['unique_venues']; ?></div>
              <div class="profile-stat-label">Venues</div>
            </div>
            <div class="profile-stat" style="grid-column: 1/-1;">
              <div class="profile-stat-value" style="font-size:1.25rem;"><?php echo formatPrice((float)$userStats['total_spent']); ?></div>
              <div class="profile-stat-label">Total Spent</div>
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($favorites)): ?>
      <div class="card mt-4">
        <div class="card-body">
          <h4 style="margin-bottom:var(--sp-4);font-size:0.875rem;"><i class="bi bi-heart-fill" style="color:var(--c-danger-light);"></i> Favorite Venues</h4>
          <?php foreach ($favorites as $fav): ?>
          <a href="<?php echo BASE_URL; ?>/court-details.php?id=<?php echo $fav['id']; ?>" class="dropdown-item" style="border-radius:var(--r-md);">
            <i class="bi bi-geo-alt"></i>
            <div>
              <div style="font-size:0.875rem;font-weight:600;"><?php echo e($fav['hall_name']); ?></div>
              <div style="font-size:0.75rem;color:var(--c-text-muted);"><?php echo e($fav['location']); ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </aside>

    <!-- Main content -->
    <main>

      <?php if ($formSuccess): ?>
      <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i><?php echo e($formSuccess); ?></div>
      <?php endif; ?>
      <?php if (!empty($formErrors['general'])): ?>
      <div class="alert alert-error mb-4"><i class="bi bi-exclamation-triangle-fill"></i><?php echo e($formErrors['general']); ?></div>
      <?php endif; ?>

      <!-- Profile Update Form -->
      <div class="card mb-6">
        <div class="card-body">
          <div class="form-section">
            <h2 class="form-section-title"><i class="bi bi-person-circle"></i> Personal Information</h2>
            <form method="POST">
              <?php csrfField(); ?>
              <input type="hidden" name="update_profile" value="1">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-5);">
                <div class="form-group" style="grid-column:1/-1;">
                  <label class="form-label">Full Name *</label>
                  <input type="text" name="full_name" class="form-control <?php echo isset($formErrors['full_name']) ? 'is-invalid' : ''; ?>"
                         value="<?php echo e($user['full_name']); ?>" required>
                  <?php if (!empty($formErrors['full_name'])): ?><div class="form-error"><?php echo e($formErrors['full_name']); ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control"
                         value="<?php echo e($user['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Phone *</label>
                  <input type="tel" name="phone" class="form-control"
                         value="<?php echo e($user['phone']); ?>" required>
                  <?php if (!empty($formErrors['phone'])): ?><div class="form-error"><?php echo e($formErrors['phone']); ?></div><?php endif; ?>
                </div>
              </div>
              <button type="submit" class="btn btn-accent">
                <i class="bi bi-check-lg"></i> Save Changes
              </button>
            </form>
          </div>

          <div class="divider"></div>

          <div class="form-section" style="margin-bottom:0;">
            <h2 class="form-section-title"><i class="bi bi-lock"></i> Change Password</h2>
            <form method="POST">
              <?php csrfField(); ?>
              <input type="hidden" name="change_password" value="1">
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--sp-5);">
                <div class="form-group">
                  <label class="form-label">Current Password</label>
                  <input type="password" name="current_password" class="form-control <?php echo isset($formErrors['current_password']) ? 'is-invalid' : ''; ?>" required>
                  <?php if (!empty($formErrors['current_password'])): ?><div class="form-error"><?php echo e($formErrors['current_password']); ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                  <label class="form-label">New Password</label>
                  <input type="password" name="new_password" class="form-control" minlength="8" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Confirm New Password</label>
                  <input type="password" name="confirm_password" class="form-control" required>
                  <?php if (!empty($formErrors['confirm_password'])): ?><div class="form-error"><?php echo e($formErrors['confirm_password']); ?></div><?php endif; ?>
                </div>
              </div>
              <button type="submit" class="btn btn-outline">
                <i class="bi bi-shield-lock"></i> Update Password
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Quick links -->
      <div class="flex gap-4 flex-wrap">
        <a href="<?php echo BASE_URL; ?>/my-bookings.php" class="btn btn-outline">
          <i class="bi bi-calendar-check"></i> My Bookings
        </a>
        <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-accent">
          <i class="bi bi-grid"></i> Browse Courts
        </a>
        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-ghost" style="color:var(--c-danger-light);">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>

    </main>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
