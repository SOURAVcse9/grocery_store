<?php
/**
 * ==========================================================================
 * public/notifications.php — Notification Center Page
 * ==========================================================================
 * Lists all user notifications with category badges, status controls,
 * and paginated layouts.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page access
require_login();

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();

$notifications = [];
$totalNotifications = 0;
$limit = 8;
$page = (int) input('page', '1', 'get');
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

try {
    // Count total rows
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid');
    $countStmt->execute(['uid' => $userId]);
    $totalNotifications = (int) $countStmt->fetchColumn();

    // Query paginated notifications list
    $stmt = $pdo->prepare('
        SELECT * FROM notifications 
        WHERE user_id = :uid 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ');
    $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[notifications.php] Load failed: ' . $e->getMessage());
}

$pageTitle = 'Notification Center — ' . site_name();
$pageDescription = 'Track order statuses, coupon additions, security alerts, and account warnings.';

$extraStylesheets = ['css/account.css', 'css/notifications.css'];
$extraScripts = ['js/notifications.js'];

require_once __DIR__ . '/header.php';
?>

<div class="container" style="margin-top: var(--space-5);">
    <div class="account-layout">
        
        <!-- Left Sidebar Navigation Menu -->
        <aside class="account-sidebar">
            <ul class="account-menu-list">
                <li class="account-menu-item">
                    <a href="<?= url_for('account.php') ?>"><i class="fas fa-gauge"></i> Dashboard</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('profile.php') ?>"><i class="fas fa-user-gear"></i> Edit Profile</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('addresses.php') ?>"><i class="fas fa-map-location-dot"></i> Saved Addresses</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('orders.php') ?>"><i class="fas fa-box-open"></i> My Orders</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('analytics.php') ?>"><i class="fas fa-chart-line"></i> Analytics</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('logout.php') ?>" style="color:var(--color-danger);"><i class="fas fa-power-off" style="color:var(--color-danger);"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Right Main Panel -->
        <main class="account-main-content">
            
            <div class="dashboard-card">
                <!-- Header Actions -->
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border); padding-bottom:var(--space-3); margin-bottom:var(--space-4);">
                    <h3 class="dashboard-card-title" style="border:none; margin:0;"><i class="far fa-bell" style="color:var(--color-primary); margin-right:6px;"></i> Notification Center</h3>
                    <?php if ($totalNotifications > 0): ?>
                        <button type="button" class="btn btn-secondary btn-mark-all-read-center" style="font-size:11px; font-weight:700; border-radius:var(--radius-pill); border:none; padding: 8px 16px;">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Notifications log list -->
                <div class="notif-list-wrapper" id="notificationsListContainer">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php include PUBLIC_PATH . '/components/notification-card.php'; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
                            <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="far fa-bell-slash"></i></div>
                            <h2>No Notifications Yet</h2>
                            <p>We will alert you here on order status changes, new coupon offers, and security warnings!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination container -->
                <div class="catalog-pagination" id="notificationsPaginationContainer" style="display:flex; justify-content:center; margin-top:var(--space-5);">
                    <?php
                    $totalPages = (int) ceil($totalNotifications / $limit);
                    $baseUrl = 'notifications.php';
                    $queryParams = $_GET;
                    unset($queryParams['page']);

                    if ($totalPages > 1) {
                        include PUBLIC_PATH . '/components/pagination.php';
                    }
                    ?>
                </div>

            </div>

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
