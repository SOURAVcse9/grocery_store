<?php
/**
 * ==========================================================================
 * public/components/notification-dropdown.php
 * ==========================================================================
 * Header Notifications Panel Dropdown component.
 * Fetches latest 5 notifications and renders them.
 * ==========================================================================
 */

declare(strict_types=1);

if (!is_logged_in()) {
    ?>
    <div class="notification-dropdown-header">
        <h4>Notifications</h4>
    </div>
    <div class="notification-dropdown-body" style="padding: var(--space-4); text-align: center; color: var(--color-text-muted); font-size: var(--fs-xs);">
        <p>Please <a href="<?= url_for('login.php') ?>" style="color:var(--color-primary); font-weight:700;">login</a> to view notifications.</p>
    </div>
    <?php
    return;
}

$userId = current_user_id();
$notifications = [];

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5');
    $stmt->execute(['uid' => $userId]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[notification-dropdown.php] Load failed: ' . $e->getMessage());
}

$unreadCount = unread_notification_count();
?>

<!-- Dropdown Header -->
<div class="notification-dropdown-header">
    <h4>Notifications (<?= $unreadCount ?>)</h4>
    <?php if ($unreadCount > 0): ?>
        <button type="button" class="btn-mark-all-read" id="btnDropdownMarkAllRead">Mark all as read</button>
    <?php endif; ?>
</div>

<!-- Dropdown Body list -->
<div class="notification-dropdown-body">
    <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $notification): ?>
            <?php include PUBLIC_PATH . '/components/notification-item.php'; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="notification-dropdown-empty">
            <i class="far fa-bell" style="font-size: 24px; margin-bottom: 6px;"></i>
            <p>You have no notifications yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Dropdown Footer -->
<div class="notification-dropdown-footer">
    <a href="<?= url_for('notifications.php') ?>" class="view-all-notif-link">View All Notifications</a>
</div>
