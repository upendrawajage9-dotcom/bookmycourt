<?php
/**
 * BookMyCourt — Notifications Page
 *
 * Displays all in-app notifications for the logged-in user.
 * Allows marking all notifications as read.
 *
 * Security:
 *   - Auth required
 *   - CSRF on POST (mark-read action)
 *   - All output escaped
 */

require_once __DIR__ . '/bootstrap.php';
requireLogin();

$userId    = currentUserId();
$pageTitle = 'Notifications';
$pageClass = 'page-notifications';

// ─── Mark All Read (POST) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    csrfVerify();
    try {
        $pdo = db();
        $pdo->prepare(
            "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE"
        )->execute([$userId]);
        flashSet('success', 'All notifications marked as read.');
    } catch (PDOException $e) {
        error_log('[BookMyCourt] notifications page mark_all_read error: ' . $e->getMessage());
        flashSet('error', 'Could not update notifications. Please try again.');
    }
    redirect(BASE_URL . '/notifications.php');
}

// ─── Fetch Notifications ──────────────────────────────────────
try {
    $pdo  = db();
    $stmt = $pdo->prepare(
        "SELECT id, title, message, type, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 50"
    );
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

    $unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

} catch (PDOException $e) {
    error_log('[BookMyCourt] notifications.php error: ' . $e->getMessage());
    $notifications = [];
    $unreadCount   = 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.notifications-page  { padding: var(--sp-8) 0 var(--sp-16); }
.notifications-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--sp-8); flex-wrap: wrap; gap: var(--sp-4); }
.notif-list { display: flex; flex-direction: column; gap: var(--sp-3); }
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: var(--sp-4);
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: var(--r-xl);
    padding: var(--sp-4) var(--sp-5);
    transition: all var(--t-base);
}
.notif-item.unread {
    background: var(--c-surface-2);
    border-color: var(--c-accent);
    box-shadow: 0 0 0 1px rgba(37,99,235,0.15);
}
.notif-icon {
    width: 40px; height: 40px;
    border-radius: var(--r-lg);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.notif-icon.success { background: rgba(22,163,74,0.15); color: var(--c-success-light); }
.notif-icon.error   { background: rgba(239,68,68,0.15);  color: var(--c-danger-light);  }
.notif-icon.warning { background: rgba(217,119,6,0.15);  color: var(--c-warning-light); }
.notif-icon.info    { background: rgba(37,99,235,0.15);  color: var(--c-accent-light);  }
.notif-body  { flex: 1; min-width: 0; }
.notif-title { font-weight: 600; color: var(--c-text-bright); margin-bottom: 2px; }
.notif-msg   { font-size: 0.875rem; color: var(--c-text-dim); line-height: 1.5; }
.notif-time  { font-size: 0.75rem; color: var(--c-text-muted); margin-top: 4px; }
.unread-dot  { width: 8px; height: 8px; border-radius: 50%; background: var(--c-accent-light); flex-shrink: 0; margin-top: 6px; }
.empty-state { text-align: center; padding: var(--sp-16) 0; color: var(--c-text-muted); }
.empty-state i { font-size: 3rem; margin-bottom: var(--sp-4); display: block; opacity: 0.4; }
</style>

<div class="container notifications-page">

    <div class="notifications-header">
        <div>
            <h1 class="section-title mb-1">Notifications</h1>
            <p style="color:var(--c-text-muted);">
                <?php if ($unreadCount > 0): ?>
                    <?php echo $unreadCount; ?> unread notification<?php echo $unreadCount > 1 ? 's' : ''; ?>
                <?php else: ?>
                    All caught up!
                <?php endif; ?>
            </p>
        </div>
        <?php if ($unreadCount > 0): ?>
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="mark_all_read" value="1">
            <button type="submit" class="btn btn-outline btn-sm">
                <i class="bi bi-check2-all"></i> Mark All Read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="empty-state">
        <i class="bi bi-bell-slash"></i>
        <h3>No notifications yet</h3>
        <p>When you make bookings or receive updates, they'll appear here.</p>
        <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-accent mt-4">
            <i class="bi bi-search"></i> Browse Courts
        </a>
    </div>
    <?php else: ?>
    <div class="notif-list">
        <?php foreach ($notifications as $n): ?>
        <?php
            $iconMap = [
                'success' => 'bi-check-circle-fill',
                'error'   => 'bi-exclamation-triangle-fill',
                'warning' => 'bi-exclamation-circle-fill',
                'info'    => 'bi-info-circle-fill',
            ];
            $icon = $iconMap[$n['type']] ?? 'bi-bell-fill';
            $isUnread = !$n['is_read'];
        ?>
        <div class="notif-item <?php echo $isUnread ? 'unread' : ''; ?>">
            <div class="notif-icon <?php echo e($n['type']); ?>">
                <i class="bi <?php echo $icon; ?>"></i>
            </div>
            <div class="notif-body">
                <div class="notif-title"><?php echo e($n['title']); ?></div>
                <div class="notif-msg"><?php echo e($n['message']); ?></div>
                <div class="notif-time">
                    <i class="bi bi-clock"></i>
                    <?php echo date('d M Y, H:i', strtotime($n['created_at'])); ?>
                </div>
            </div>
            <?php if ($isUnread): ?>
            <div class="unread-dot" title="Unread"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
