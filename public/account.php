<?php
/**
 * ==========================================================================
 * public/account.php — Customer Dashboard Hub
 * ==========================================================================
 * Displays user overview, order statistics, recent purchases table,
 * wishlist counts, and default address cards.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page access
require_login();

$user = current_user();
$userId = (int) $user['id'];

try {
    $pdo = db();

    // 1. Fetch Dashboard Stats
    $totalOrdersStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :uid');
    $totalOrdersStmt->execute(['uid' => $userId]);
    $totalOrders = (int) $totalOrdersStmt->fetchColumn();

    $pendingOrdersStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = \'pending\'');
    $pendingOrdersStmt->execute(['uid' => $userId]);
    $pendingOrders = (int) $pendingOrdersStmt->fetchColumn();

    $completedOrdersStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = \'delivered\'');
    $completedOrdersStmt->execute(['uid' => $userId]);
    $completedOrders = (int) $completedOrdersStmt->fetchColumn();

    $wishlistCount = wishlist_item_count();

    // 2. Fetch 5 Recent Orders
    $recentStmt = $pdo->prepare('
        SELECT id, order_number, total_amount, payment_method, status, created_at
        FROM orders 
        WHERE user_id = :uid 
        ORDER BY created_at DESC 
        LIMIT 5
    ');
    $recentStmt->execute(['uid' => $userId]);
    $recentOrders = $recentStmt->fetchAll();

    // 3. Fetch Default Shipping Address
    $addrStmt = $pdo->prepare('
        SELECT * FROM addresses 
        WHERE user_id = :uid 
        ORDER BY is_default DESC, id DESC 
        LIMIT 1
    ');
    $addrStmt->execute(['uid' => $userId]);
    $defaultAddress = $addrStmt->fetch();

} catch (PDOException $e) {
    error_log('[account.php] Error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Dashboard Loading Error: ' . htmlspecialchars($e->getMessage()));
    }
}

$pageTitle = 'My Dashboard — ' . site_name();
$pageDescription = 'Access your personal dashboard to track packages, view statistics, and update profile settings.';

$extraStylesheets = ['css/account.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container" style="margin-top: var(--space-5);">
    <div class="account-layout">
        
        <!-- Left Sidebar Navigation Menu -->
        <aside class="account-sidebar">
            <ul class="account-menu-list">
                <li class="account-menu-item active">
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
            <!-- Welcome Banner Card -->
            <div class="welcome-card">
                <div class="welcome-info">
                    <div class="welcome-avatar-wrapper">
                        <?php 
                        $avatarUrl = image_url($user['avatar'], 'users');
                        ?>
                        <img class="welcome-avatar" src="<?= e($avatarUrl) ?>" alt="<?= e($user['full_name']) ?>">
                    </div>
                    <div class="welcome-details">
                        <h2>Hello, <?= e($user['full_name']) ?>!</h2>
                        <p><?= e($user['email']) ?> | <?= e($user['phone'] ?? 'No phone registered') ?></p>
                    </div>
                </div>
                <div class="welcome-meta">
                    Customer Since: <strong><?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?></strong>
                </div>
            </div>

            <!-- Statistics widgets row -->
            <div class="stats-widgets-grid">
                <div class="stats-card">
                    <div class="stats-icon-box total"><i class="fas fa-receipt"></i></div>
                    <div class="stats-info">
                        <span class="stats-value"><?= $totalOrders ?></span>
                        <span class="stats-label">Total Orders</span>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-icon-box pending"><i class="fas fa-arrows-spin"></i></div>
                    <div class="stats-info">
                        <span class="stats-value"><?= $pendingOrders ?></span>
                        <span class="stats-label">Pending</span>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-icon-box completed"><i class="fas fa-circle-check"></i></div>
                    <div class="stats-info">
                        <span class="stats-value"><?= $completedOrders ?></span>
                        <span class="stats-label">Delivered</span>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-icon-box wishlist"><i class="fas fa-heart"></i></div>
                    <div class="stats-info">
                        <span class="stats-value"><?= $wishlistCount ?></span>
                        <span class="stats-label">Wishlist</span>
                    </div>
                </div>
            </div>

            <!-- Secondary Grids: Recent orders & Addresses -->
            <div class="dashboard-columns">
                
                <!-- Left Grid column: Recent Orders Table -->
                <div class="dashboard-card" style="flex: 1;">
                    <h3 class="dashboard-card-title">
                        <span>Recent Orders</span>
                        <a href="<?= url_for('orders.php') ?>" style="font-size:10px; color:var(--color-primary); text-transform:none;">View All</a>
                    </h3>
                    
                    <div class="dashboard-table-wrapper">
                        <?php if (!empty($recentOrders)): ?>
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): 
                                        $statusClass = strtolower($order['status']);
                                        $paymentText = $order['payment_method'] === 'cod' ? 'COD' : ($order['payment_method'] === 'card' ? 'Online' : 'Mobile');
                                    ?>
                                        <tr>
                                            <td><strong><?= e($order['order_number']) ?></strong></td>
                                            <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                            <td><?= format_price((float)$order['total_amount']) ?></td>
                                            <td><?= $paymentText ?></td>
                                            <td><span class="order-badge <?= $statusClass ?>"><?= e($order['status']) ?></span></td>
                                            <td>
                                                <a href="<?= url_for('order-details.php?id=' . (int)$order['id']) ?>" style="color:var(--color-primary); font-weight:700;">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="font-size:var(--fs-xs); color:var(--color-text-muted); margin:0; padding: var(--space-3) 0; text-align:center;">You have not placed any orders yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Grid column: Default address widget -->
                <div class="dashboard-card" style="width: 100%;">
                    <h3 class="dashboard-card-title">Default Address</h3>
                    
                    <div class="address-snippet">
                        <?php if ($defaultAddress): ?>
                            <strong><?= e($defaultAddress['recipient_name']) ?></strong><br>
                            <?= e($defaultAddress['phone']) ?><br>
                            <?= e($defaultAddress['address_line1']) ?><?= $defaultAddress['address_line2'] ? ', ' . e($defaultAddress['address_line2']) : '' ?><br>
                            <?= e($defaultAddress['city']) ?><br>
                            <?= e($defaultAddress['country']) ?>
                            <div style="margin-top:var(--space-4);">
                                <a href="<?= url_for('addresses.php') ?>" class="btn btn-secondary" style="font-size:10px; border-radius:var(--radius-pill); padding: 6px 16px; border:none; display:inline-block;">Manage Book</a>
                            </div>
                        <?php else: ?>
                            <p style="margin:0 0 var(--space-3) 0;">No default address registered yet.</p>
                            <a href="<?= url_for('addresses.php') ?>" class="btn btn-primary" style="font-size:10px; border-radius:var(--radius-pill); padding: 6px 16px; border:none; display:inline-block;">Add Address</a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
