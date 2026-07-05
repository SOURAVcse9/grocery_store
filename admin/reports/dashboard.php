<?php
/**
 * ==========================================================================
 * admin/reports/dashboard.php — Enterprise Reporting Hub
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Reports Hub — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // 1. Core aggregates
    $totalSales = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'delivered'")->fetchColumn();
    $totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
    $totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role_id != 1 AND deleted_at IS NULL")->fetchColumn();
    $inventoryValue = (float) $pdo->query("SELECT COALESCE(SUM(price * stock), 0) FROM products WHERE deleted_at IS NULL")->fetchColumn();
    
    // Tax collected (let's assume 5% of delivered orders total is tax as metadata)
    $taxCollected = $totalSales * 0.05;
    // Estimated profit (let's assume profit is 15% of delivered orders total)
    $netProfit = $totalSales * 0.15;

    // 2. Sales graph data (Last 7 days)
    $salesGraph = $pdo->query("
        SELECT DATE(created_at) AS date, COALESCE(SUM(total_amount), 0) AS total
        FROM orders 
        WHERE status = 'delivered' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ")->fetchAll();

    $dates = [];
    $salesAmounts = [];
    foreach ($salesGraph as $sg) {
        $dates[] = date('M d', strtotime($sg['date']));
        $salesAmounts[] = (float)$sg['total'];
    }

} catch (PDOException $e) {
    error_log('[admin/reports/dashboard] aggregates load failed: ' . $e->getMessage());
    $totalSales = $inventoryValue = $taxCollected = $netProfit = 0;
    $totalOrders = $totalProducts = $totalCustomers = 0;
    $dates = $salesAmounts = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Enterprise Reports Hub</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect sales performance, profit margins, inventory valuation, and customer statistics.</p>
    </div>
</div>

<!-- Grid submenus -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:var(--space-5);" class="reports-submenus">
    <a href="sales.php" class="btn btn-secondary" style="padding:12px; text-decoration:none; font-weight:700; text-align:center;"><i class="fas fa-chart-line"></i> Sales Report</a>
    <a href="orders.php" class="btn btn-secondary" style="padding:12px; text-decoration:none; font-weight:700; text-align:center;"><i class="fas fa-shopping-bag"></i> Orders Analysis</a>
    <a href="products.php" class="btn btn-secondary" style="padding:12px; text-decoration:none; font-weight:700; text-align:center;"><i class="fas fa-box"></i> Product Analytics</a>
    <a href="customers.php" class="btn btn-secondary" style="padding:12px; text-decoration:none; font-weight:700; text-align:center;"><i class="fas fa-users"></i> Top Customers</a>
    <a href="inventory.php" class="btn btn-secondary" style="padding:12px; text-decoration:none; font-weight:700; text-align:center;"><i class="fas fa-warehouse"></i> Inventory Stock</a>
    <a href="profit.php" class="btn btn-secondary" style="padding:12px; text-decoration:none; font-weight:700; text-align:center;"><i class="fas fa-dollar-sign"></i> Profit Margins</a>
</div>

<!-- Aggregates row -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Lifetime Sales</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalSales, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #339af0;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Total Orders</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $totalOrders ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #f08c00;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Inventory Value</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($inventoryValue, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #e03131;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Net Profit (Est.)</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($netProfit, 2) ?></h2>
    </div>
</div>

<!-- Chart area -->
<div class="dashboard-card" style="padding:var(--space-5);">
    <h3 style="font-size:14px; font-weight:800; margin:0 0 16px 0; border-bottom:1px solid var(--color-border); padding-bottom:6px;">Delivered Sales (Last 7 Days)</h3>
    <div style="height: 300px; width: 100%;">
        <canvas id="salesChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($dates) ?>,
            datasets: [{
                label: 'Sales (৳)',
                data: <?= json_encode($salesAmounts) ?>,
                borderColor: '#0ca678',
                backgroundColor: 'rgba(12, 166, 120, 0.1)',
                borderWidth: 3,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
