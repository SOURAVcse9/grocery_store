<?php
/**
 * ==========================================================================
 * public/analytics.php — Analytics & Business Intelligence Dashboard
 * ==========================================================================
 * Provides customers visual charts mapping orders, monthly spends, category
 * distributions, and purchase logs, alongside CSV reporting features.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page
require_login();

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();

// Initialize stats counts
$totalOrders = 0;
$totalSpent = 0.0;
$avgOrderValue = 0.0;
$wishlistCount = 0;
$couponSavings = 0.0;
$deliverySpent = 0.0;
$recentOrders = [];
$topProducts = [];
$statusBreakdown = [];

try {
    // 1. Core numeric totals
    $totalsStmt = $pdo->prepare('
        SELECT COUNT(*) AS total_orders, 
               COALESCE(SUM(total_amount), 0) AS total_spent, 
               COALESCE(AVG(total_amount), 0) AS avg_o_value,
               COALESCE(SUM(discount_amount), 0) AS coupon_savings,
               COALESCE(SUM(delivery_charge), 0) AS delivery_spent
        FROM orders 
        WHERE user_id = :uid AND status != \'cancelled\'
    ');
    $totalsStmt->execute(['uid' => $userId]);
    $totals = $totalsStmt->fetch();

    $totalOrders = (int) $totals['total_orders'];
    $totalSpent = (float) $totals['total_spent'];
    $avgOrderValue = (float) $totals['avg_o_value'];
    $couponSavings = (float) $totals['coupon_savings'];
    $deliverySpent = (float) $totals['delivery_spent'];

    // 2. Wishlist items count
    $wishlistStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = :uid');
    $wishlistStmt->execute(['uid' => $userId]);
    $wishlistCount = (int) $wishlistStmt->fetchColumn();

    // 3. Top products purchased
    $topStmt = $pdo->prepare('
        SELECT oi.product_name, SUM(oi.quantity) AS qty_sum
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.user_id = :uid AND o.status != \'cancelled\'
        GROUP BY oi.product_id
        ORDER BY qty_sum DESC
        LIMIT 5
    ');
    $topStmt->execute(['uid' => $userId]);
    $topProducts = $topStmt->fetchAll();

    // 4. Order status breakdown
    $statusStmt = $pdo->prepare('
        SELECT status, COUNT(*) AS count
        FROM orders
        WHERE user_id = :uid
        GROUP BY status
        ORDER BY count DESC
    ');
    $statusStmt->execute(['uid' => $userId]);
    $statusBreakdown = $statusStmt->fetchAll();

    // 5. Recent 5 orders
    $recentStmt = $pdo->prepare('
        SELECT * FROM orders 
        WHERE user_id = :uid 
        ORDER BY id DESC 
        LIMIT 5
    ');
    $recentStmt->execute(['uid' => $userId]);
    $recentOrders = $recentStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[analytics.php] Database load failed: ' . $e->getMessage());
}

$pageTitle = 'Analytics Dashboard — ' . site_name();
$pageDescription = 'Review monthly spending, order counts, favorite categories, and download order history logs.';

$extraStylesheets = ['css/account.css', 'css/analytics.css'];
$extraScripts = ['js/analytics.js'];

require_once __DIR__ . '/header.php';
?>

<div class="container" style="margin-top: var(--space-5); margin-bottom: var(--space-6);">
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
                <li class="account-menu-item active">
                    <a href="<?= url_for('analytics.php') ?>"><i class="fas fa-chart-line"></i> Analytics</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('logout.php') ?>" style="color:var(--color-danger);"><i class="fas fa-power-off" style="color:var(--color-danger);"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Right Main Panel -->
        <main class="account-main-content">
            
            <!-- Dashboard heading actions -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border); padding-bottom:var(--space-3); margin-bottom:var(--space-4); flex-wrap:wrap; gap:var(--space-2);">
                <h2 style="font-size:var(--fs-lg); font-weight:800; margin:0;"><i class="fas fa-chart-line" style="color:var(--color-primary); margin-right:6px;"></i> Purchase Insights</h2>
                <a href="<?= url_for('api/analytics.php?action=download_csv') ?>" class="btn btn-primary" style="font-size:11px; font-weight:700; border-radius:var(--radius-pill); border:none; padding: 10px 18px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-download"></i> Export CSV Summary
                </a>
            </div>

            <!-- Stats grid indicators -->
            <div class="analytics-stats-grid">
                <?php
                // Render KPI Stats Card 1: Total Orders
                $icon = 'fa-box-open';
                $title = 'Total Orders';
                $value = (string)$totalOrders;
                $context = 'Non-cancelled orders';
                $color = 'primary';
                include PUBLIC_PATH . '/components/stat-card.php';

                // Render KPI Stats Card 2: Total Spent
                $icon = 'fa-credit-card';
                $title = 'Total Spending';
                $value = format_price($totalSpent);
                $context = 'Life-time grocery investment';
                $color = 'success';
                include PUBLIC_PATH . '/components/stat-card.php';

                // Render KPI Stats Card 3: AOV
                $icon = 'fa-calculator';
                $title = 'Average Order Value';
                $value = format_price($avgOrderValue);
                $context = 'Average cost per cart';
                $color = 'warning';
                include PUBLIC_PATH . '/components/stat-card.php';

                // Render KPI Stats Card 4: Coupon Savings
                $icon = 'fa-tags';
                $title = 'Coupon Savings';
                $value = format_price($couponSavings);
                $context = 'Promotional codes discounts';
                $color = 'danger';
                include PUBLIC_PATH . '/components/stat-card.php';
                ?>
            </div>

            <!-- Charts visualizations grids -->
            <div class="analytics-charts-grid">
                <!-- 1. Spending timeline -->
                <?php include PUBLIC_PATH . '/components/spending-chart.php'; ?>

                <!-- 2. Order status status counts -->
                <?php include PUBLIC_PATH . '/components/order-summary.php'; ?>

                <!-- 3. Category distribution doughnut -->
                <?php
                $chartId = 'categoryDistributionChart';
                $chartTitle = 'Favorite Categories Breakdown';
                include PUBLIC_PATH . '/components/chart-card.php';
                ?>

                <!-- 4. Frequently bought products -->
                <?php
                $chartId = 'productPurchasesChart';
                $chartTitle = 'Frequently Purchased Products';
                include PUBLIC_PATH . '/components/chart-card.php';
                ?>

                <!-- 5. Order Status distribution pie -->
                <?php
                $chartId = 'orderStatusPieChart';
                $chartTitle = 'Order Status Distribution';
                include PUBLIC_PATH . '/components/chart-card.php';
                ?>

                <!-- 6. Top Products list -->
                <?php include PUBLIC_PATH . '/components/top-products.php'; ?>
            </div>

            <!-- Recent Orders Table -->
            <div style="display:grid; grid-template-columns: 1fr; gap:var(--space-4);">
                <?php include PUBLIC_PATH . '/components/recent-orders.php'; ?>
            </div>

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
