<?php
/**
 * ==========================================================================
 * admin/index.php — Enterprise Dashboard Main Hub Page
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Manager Dashboard — GroCo Admin';

require_once __DIR__ . '/layouts/dashboard_layout.php';

$pdo = db();

try {
    // 1. Sales metrics calculations
    $todaySales = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn();
    $yesterdaySales = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status != 'cancelled'")->fetchColumn();
    $weeklySales = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status != 'cancelled'")->fetchColumn();
    $monthlySales = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status != 'cancelled'")->fetchColumn();
    $totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'delivered'")->fetchColumn();

    // 2. Orders status calculations
    $totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $processingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
    $completedOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
    $cancelledOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();

    // 3. Catalog totals
    $totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role_id != 1")->fetchColumn();
    $totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $outOfStock = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0 AND is_active = 1")->fetchColumn();
    $lowStock = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND stock > 0 AND is_active = 1")->fetchColumn();

    // 4. Panel details lists queries
    $recentOrders = $pdo->query("
        SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at, u.full_name 
        FROM orders o
        JOIN users u ON u.id = o.user_id
        ORDER BY o.created_at DESC 
        LIMIT 5
    ")->fetchAll();

    $lowStockItems = $pdo->query("
        SELECT id, name, sku, stock, price, slug 
        FROM products 
        WHERE stock <= 5 AND is_active = 1
        ORDER BY stock ASC 
        LIMIT 5
    ")->fetchAll();

    $recentActivities = $pdo->query("
        SELECT l.*, a.full_name 
        FROM admin_activity_logs l
        JOIN admins a ON a.id = l.admin_id
        ORDER BY l.created_at DESC 
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/index] stats fetch failure: ' . $e->getMessage());
    $todaySales = $yesterdaySales = $weeklySales = $monthlySales = $totalRevenue = 0.0;
    $totalOrders = $pendingOrders = $processingOrders = $completedOrders = $cancelledOrders = 0;
    $totalCustomers = $totalProducts = $outOfStock = $lowStock = 0;
    $recentOrders = $lowStockItems = $recentActivities = [];
}
?>

<!-- Headline Greetings -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Dashboard Overview</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Welcome back, <strong><?= e($__admin['full_name']) ?></strong>! Here is what's happening at your store today.</p>
    </div>
    <div style="font-size:12px; color:var(--color-text-muted); font-weight:600; background:#fff; padding:8px 16px; border:1px solid var(--color-border); border-radius:var(--radius-pill);">
        <i class="far fa-calendar-days" style="color:var(--color-primary); margin-right:4px;"></i> <?= date('l, M d, Y') ?>
    </div>
</div>

<!-- 1. Stats Counter Widgets -->
<?php if (has_admin_permission('dashboard.view')): ?>
    <section class="stats-cards-grid" aria-label="Quick stats widgets">
        <!-- Card 1 -->
        <div class="stat-widget-card">
            <div class="stat-widget-icon theme-green"><i class="fas fa-sack-dollar"></i></div>
            <div class="stat-widget-info">
                <span class="stat-widget-label">Today's Sales</span>
                <span class="stat-widget-value">৳<?= number_format($todaySales, 2) ?></span>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="stat-widget-card">
            <div class="stat-widget-icon theme-blue"><i class="fas fa-chart-line"></i></div>
            <div class="stat-widget-info">
                <span class="stat-widget-label">Monthly Sales</span>
                <span class="stat-widget-value">৳<?= number_format($monthlySales, 2) ?></span>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="stat-widget-card">
            <div class="stat-widget-icon theme-purple"><i class="fas fa-wallet"></i></div>
            <div class="stat-widget-info">
                <span class="stat-widget-label">Total Revenue</span>
                <span class="stat-widget-value">৳<?= number_format($totalRevenue, 2) ?></span>
            </div>
        </div>

        <!-- Card 4 -->
        <div class="stat-widget-card">
            <div class="stat-widget-icon theme-orange"><i class="fas fa-truck-ramp-box"></i></div>
            <div class="stat-widget-info">
                <span class="stat-widget-label">Pending Orders</span>
                <span class="stat-widget-value"><?= $pendingOrders ?></span>
            </div>
        </div>

        <!-- Card 5 -->
        <div class="stat-widget-card">
            <div class="stat-widget-icon theme-teal"><i class="fas fa-users"></i></div>
            <div class="stat-widget-info">
                <span class="stat-widget-label">Total Buyers</span>
                <span class="stat-widget-value"><?= $totalCustomers ?></span>
            </div>
        </div>

        <!-- Card 6 -->
        <div class="stat-widget-card">
            <div class="stat-widget-icon theme-red"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="stat-widget-info">
                <span class="stat-widget-label">Low Stock Items</span>
                <span class="stat-widget-value"><?= $lowStock ?></span>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- 2. Charts Analytics Section -->
<?php if (has_admin_permission('reports.view')): ?>
    <section style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6); margin-bottom:var(--space-6);" class="admin-dashboard-layout" aria-label="Sales Charts Area">
        <!-- Sales Trend Line Chart -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0 0 16px 0; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Sales Performance Trend</h3>
            <div style="height:260px; position:relative;">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        <!-- Order Status Distribution Doughnut Chart -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0 0 16px 0; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Order Status Ratios</h3>
            <div style="height:260px; position:relative; display:flex; justify-content:center;">
                <canvas id="orderStatusChart" style="max-height:100%;"></canvas>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- 3. Main Data Panels (Two Columns) -->
<div class="dashboard-panels-layout">
    
    <!-- Left Panel: Recent Orders & Quick Actions -->
    <div style="display:flex; flex-direction:column; gap:var(--space-6);">
        
        <!-- Recent Orders Table -->
        <?php if (has_admin_permission('orders.view')): ?>
            <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0;">Recent Orders</h3>
                    <a href="orders/index.php" style="font-size:11px; color:var(--color-primary); font-weight:700; text-decoration:none;">View All Orders <i class="fas fa-chevron-right" style="font-size:9px;"></i></a>
                </div>
                
                <div class="admin-table-wrapper">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total Price</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentOrders)): ?>
                                <?php foreach ($recentOrders as $o): ?>
                                    <tr>
                                        <td><strong>#<?= e($o['order_number']) ?></strong></td>
                                        <td><?= e($o['full_name']) ?></td>
                                        <td><strong>৳<?= number_format((float)$o['total_amount'], 2) ?></strong></td>
                                        <td>
                                            <span class="status-pill pill-<?= e($o['status']) ?>">
                                                <?= e($o['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, H:i', strtotime($o['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:20px; color:var(--color-text-faint);">No orders placed yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Panel -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0 0 12px 0;">Quick Action Shortcuts</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:10px;" class="grid-4">
                <?php if (has_admin_permission('products.create')): ?>
                    <a href="products/create.php" class="btn btn-secondary" style="font-size:12px; padding:10px; text-align:center; border-radius:var(--radius-sm); font-weight:700; text-decoration:none; display:flex; flex-direction:column; align-items:center; gap:6px;">
                        <i class="fas fa-plus-circle" style="color:var(--color-primary); font-size:16px;"></i> Add Product
                    </a>
                <?php endif; ?>

                <?php if (has_admin_permission('settings.manage')): ?>
                    <a href="coupons/index.php" class="btn btn-secondary" style="font-size:12px; padding:10px; text-align:center; border-radius:var(--radius-sm); font-weight:700; text-decoration:none; display:flex; flex-direction:column; align-items:center; gap:6px;">
                        <i class="fas fa-ticket" style="color:#7048e8; font-size:16px;"></i> Create Coupon
                    </a>
                <?php endif; ?>

                <?php if (has_admin_permission('products.edit')): ?>
                    <a href="categories/index.php" class="btn btn-secondary" style="font-size:12px; padding:10px; text-align:center; border-radius:var(--radius-sm); font-weight:700; text-decoration:none; display:flex; flex-direction:column; align-items:center; gap:6px;">
                        <i class="fas fa-folder-tree" style="color:#0ca678; font-size:16px;"></i> Add Category
                    </a>
                <?php endif; ?>

                <?php if (has_admin_permission('reviews.manage')): ?>
                    <a href="reviews/index.php" class="btn btn-secondary" style="font-size:12px; padding:10px; text-align:center; border-radius:var(--radius-sm); font-weight:700; text-decoration:none; display:flex; flex-direction:column; align-items:center; gap:6px;">
                        <i class="fas fa-comments-dollar" style="color:#f59f00; font-size:16px;"></i> Moderate Reviews
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Panel: Low Stock Warnings & Activities -->
    <div style="display:flex; flex-direction:column; gap:var(--space-6);">
        
        <!-- Low Stock / Out of Stock alerts panel -->
        <?php if (has_admin_permission('products.view')): ?>
            <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0 0 12px 0;">Stock Alerts</h3>
                
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php if (!empty($lowStockItems)): ?>
                        <?php foreach ($lowStockItems as $p): 
                            $isOut = ((int)$p['stock'] === 0);
                            $badgeBg = $isOut ? '#fff5f5' : '#fff9db';
                            $badgeColor = $isOut ? '#f03e3e' : '#f59f00';
                            $badgeText = $isOut ? 'SOLD OUT' : $p['stock'] . ' left';
                        ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:10px 12px; background:var(--color-bg); font-size:12px;">
                                <div style="display:flex; flex-direction:column; gap:2px; max-width:180px;">
                                    <strong style="color:var(--color-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= e($p['name']) ?></strong>
                                    <span style="font-size:10px; color:var(--color-text-faint);">SKU: <?= e($p['sku']) ?></span>
                                </div>
                                <div style="text-align:right;">
                                    <span style="background:<?= $badgeBg ?>; color:<?= $badgeColor ?>; font-size:9px; font-weight:800; padding:2px 8px; border-radius:4px; display:inline-block; margin-bottom:4px;"><?= $badgeText ?></span>
                                    <a href="<?= url_for('product.php?slug=' . $p['slug']) ?>" target="_blank" style="font-size:10px; color:var(--color-primary); font-weight:700; display:block; text-decoration:none;">View Item <i class="fas fa-arrow-up-right-from-square" style="font-size:8px;"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--color-text-faint); font-size:12px; text-align:center; padding:10px 0; margin:0;">All inventory stocks healthy.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Activities log list -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0 0 12px 0;">Recent Activities</h3>
            
            <div style="display:flex; flex-direction:column; gap:10px; max-height:280px; overflow-y:auto;">
                <?php if (!empty($recentActivities)): ?>
                    <?php foreach ($recentActivities as $log): ?>
                        <div style="font-size:11px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <strong style="color:var(--color-text);"><?= e($log['full_name']) ?></strong>
                                <span style="font-size:9px; color:var(--color-text-faint);"><?= time_ago($log['created_at']) ?></span>
                            </div>
                            <p style="margin:0; color:var(--color-text-muted); line-height:1.4;"><?= e($log['description']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--color-text-faint); font-size:12px; text-align:center; padding:10px 0; margin:0;">No activities logged yet.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- AJAX Chart Render Scripts -->
<?php if (has_admin_permission('reports.view')): ?>
<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('api/dashboard_charts.php');
        const json = await res.json();

        if (json.success && json.data) {
            const data = json.data;

            // 1. Line Chart: Daily Sales
            const dailyLabels = data.daily_sales.map(item => item.date_label);
            const dailyData = data.daily_sales.map(item => parseFloat(item.total_sales));

            new Chart(document.getElementById('salesTrendChart'), {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Sales Revenue (৳)',
                        data: dailyData,
                        borderColor: '#0ca678',
                        backgroundColor: 'rgba(12, 166, 120, 0.05)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f3f5' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // 2. Doughnut Chart: Order Status Distribution
            const statusLabels = data.status_distribution.map(item => item.status.toUpperCase());
            const statusData = data.status_distribution.map(item => parseInt(item.count));
            const statusColors = data.status_distribution.map(item => {
                if (item.status === 'delivered') return '#0ca678';
                if (item.status === 'pending') return '#f59f00';
                if (item.status === 'processing') return '#4263eb';
                return '#f03e3e';
            });

            new Chart(document.getElementById('orderStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: statusColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
                    }
                }
            });
        }
    } catch (err) {
        console.error('Failed to render analytics graphs:', err);
    }
});
</script>
<?php endif; ?>

<?php
// Close layout wrapper
require_once __DIR__ . '/layouts/footer.php';
?>
</div>
