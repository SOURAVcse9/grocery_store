<?php
/**
 * ==========================================================================
 * admin/notifications/index.php — Notification Center & Broadcast Console
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Notification Center — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('notifications.manage');

$pdo = db();
$error = null;
$success = null;

// Handle Read & Delete Operations
if (method_is('post')) {
    verify_csrf_or_fail();
    $action = input('notif_action', '');

    if ($action === 'mark_read') {
        $notifId = (int) input('id', '0');
        if ($notifId > 0) {
            $pdo->prepare("UPDATE dashboard_notifications SET is_read = 1 WHERE id = ?")->execute([$notifId]);
            $success = 'Notification marked as read.';
        }
    } elseif ($action === 'delete') {
        $notifId = (int) input('id', '0');
        if ($notifId > 0) {
            $pdo->prepare("DELETE FROM dashboard_notifications WHERE id = ?")->execute([$notifId]);
            $success = 'Notification deleted successfully.';
        }
    } elseif ($action === 'broadcast') {
        $target = input('target_audience', 'customers');
        $title = trim(input('title', ''));
        $message = trim(input('message', ''));
        $type = input('notif_type', 'account');

        if (empty($title) || empty($message)) {
            $error = 'Title and Message details are required fields.';
        } else {
            try {
                if ($target === 'customers') {
                    // Fetch all customer IDs (role_id != 1)
                    $custIds = $pdo->query("SELECT id FROM users WHERE role_id != 1 AND deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
                    
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO customer_notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    foreach ($custIds as $cid) {
                        $stmt->execute([(int)$cid, $title, $message, $type]);
                    }
                    $pdo->commit();
                    
                    log_admin_activity('notifications.broadcast', 'Broadcasted in-app message to all customer accounts.');
                    $success = 'Broadcast notification dispatched to all customers successfully!';
                } else {
                    // Broadcast to admins (dashboard_notifications table)
                    $stmt = $pdo->prepare("INSERT INTO dashboard_notifications (type, title, message, link) VALUES (?, ?, ?, '#')");
                    $stmt->execute([$type, $title, $message]);
                    
                    log_admin_activity('notifications.broadcast', 'Broadcasted console notification to all administrators.');
                    $success = 'Broadcast notification dispatched to administrative feed!';
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin/notifications] Broadcast failed: ' . $e->getMessage());
                $error = 'Failed to broadcast notification due to server error.';
            }
        }
    }
}

// Fetch dashboard notifications
try {
    $notifications = $pdo->query("SELECT * FROM dashboard_notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/notifications] load failed: ' . $e->getMessage());
    $notifications = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Notification Center</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect dashboard logs feeds, clear logs alerts, and dispatch broadcast notifications to customers.</p>
    </div>
</div>

<!-- Notifications -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1.5fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Broadcast console Form -->
    <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Broadcast Notification Dispatcher</h3>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="notif_action" value="broadcast">

            <div class="form-field-group">
                <label style="font-weight:700;">Target Audience</label>
                <select name="target_audience" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="customers">All Store Customers (role_id != 1)</option>
                    <option value="admins">All Administrators Feed</option>
                </select>
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Broadcast Title *</label>
                <input type="text" name="title" required placeholder="E.g. Summer Mega Sale Starts Tonight!" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Notification Message Details *</label>
                <textarea name="message" rows="4" required placeholder="Type details, link anchors, or alerts..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical; font-family:inherit;"></textarea>
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Notification Style Category</label>
                <select name="notif_type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="promo">Promo / Coupon Alert</option>
                    <option value="account">Account Status Warning</option>
                    <option value="alert">System Alert Notice</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px;"><i class="fas fa-bullhorn"></i> Dispatch Broadcast Notification</button>
        </form>
    </div>

    <!-- Right Column: Dashboard Feed Alerts list -->
    <div class="dashboard-card" style="margin:0; padding:0; overflow:hidden;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;"><i class="fas fa-bell"></i> Dashboard Notifications Feed</h3>
        </div>
        <div style="padding:var(--space-4); display:flex; flex-direction:column; gap:12px; max-height: 500px; overflow-y: auto;">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $row): 
                    $unread = !(bool)$row['is_read'];
                ?>
                    <div style="border:1px solid var(--color-border); padding:12px; border-radius:var(--radius-sm); font-size:12px; background: <?= $unread ? 'rgba(12, 166, 120, 0.05)' : '#fff' ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <strong style="color:var(--color-text);"><?= e($row['title']) ?></strong>
                            <div style="display:inline-flex; gap:6px;">
                                <?php if ($unread): ?>
                                    <form method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="notif_action" value="mark_read">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding:2px 6px; font-size:9px; border-radius:var(--radius-sm); border:none; background:#0ca678; color:#fff; cursor:pointer;" title="Mark Read"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="notif_action" value="delete">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding:2px 6px; font-size:9px; border-radius:var(--radius-sm); border:none; background:#f03e3e; color:#fff; cursor:pointer;" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <p style="margin:2px 0 4px 0; color:var(--color-text-muted);"><?= e($row['message']) ?></p>
                        <span style="font-size:9px; color:var(--color-text-faint);"><?= date('M d, H:i', strtotime($row['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; color:var(--color-text-faint); padding:20px; margin:0; font-size:12px;">No notification logs in feed.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
