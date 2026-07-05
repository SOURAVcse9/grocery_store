<?php
/**
 * ==========================================================================
 * admin/reports/sales.php — Sales Reports Summary
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Sales Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

$startDate = input('start_date', date('Y-m-d', strtotime('-30 days')), 'get');
$endDate = input('end_date', date('Y-m-d'), 'get');

try {
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) AS date, COUNT(*) AS orders_count, SUM(total_amount) AS daily_revenue
        FROM orders
        WHERE status = 'delivered' AND DATE(created_at) BETWEEN :start AND :end
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) DESC
    ");
    $stmt->execute(['start' => $startDate, 'end' => $endDate]);
    $sales = $stmt->fetchAll();

    // Aggregates
    $grossSales = 0.0;
    $totalOrders = 0;
    foreach ($sales as $s) {
        $grossSales += (float)$s['daily_revenue'];
        $totalOrders += (int)$s['orders_count'];
    }
    $aov = $totalOrders > 0 ? $grossSales / $totalOrders : 0;

} catch (PDOException $e) {
    error_log('[admin/reports/sales] failed: ' . $e->getMessage());
    $sales = [];
    $grossSales = $aov = $totalOrders = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Sales Performance Report</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Filter daily sales revenue, volume transaction trends, and average ticket sizes.</p>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
        <a href="exports.php?type=sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>
</div>

<!-- Filters Form -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:flex; gap:12px; align-items:end; max-width:600px;">
        <div class="form-field-group" style="margin:0; flex:1;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Start Date</label>
            <input type="date" name="start_date" value="<?= e($startDate) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>
        <div class="form-field-group" style="margin:0; flex:1;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">End Date</label>
            <input type="date" name="end_date" value="<?= e($endDate) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
    </form>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Gross Sales</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($grossSales, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #339af0;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Delivered Orders</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $totalOrders ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #f08c00;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Avg. Ticket Value (AOV)</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($aov, 2) ?></h2>
    </div>
</div>

<!-- Table list -->
<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Sales Date</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Orders Count</th>
                    <th style="padding:16px 20px; text-align:right; width:220px;">Daily Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sales)): ?>
                    <?php foreach ($sales as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong><?= date('M d, Y', strtotime($row['date'])) ?></strong></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700;"><?= $row['orders_count'] ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['daily_revenue'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="padding:32px; text-align:center; color:var(--color-text-faint);">No sales transactions found in the specified date range.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
