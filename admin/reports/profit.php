<?php
/**
 * ==========================================================================
 * admin/reports/profit.php — Estimated Profit Margins Report
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Profit Margins Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // Estimating profit (gross revenue minus shipping cost, assuming cost of goods sold is 65% of order totals, net profit rate ~35%)
    $profitSummary = $pdo->query("
        SELECT DATE(created_at) AS date, 
               SUM(total_amount) AS gross_revenue, 
               SUM(total_amount * 0.65) AS estimated_cogs,
               SUM(total_amount * 0.35) AS net_profit
        FROM orders
        WHERE status = 'delivered'
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) DESC
        LIMIT 30
    ")->fetchAll();

    // Aggregates
    $grossRevenue = 0.0;
    $totalCogs = 0.0;
    $totalProfit = 0.0;
    foreach ($profitSummary as $row) {
        $grossRevenue += (float)$row['gross_revenue'];
        $totalCogs += (float)$row['estimated_cogs'];
        $totalProfit += (float)$row['net_profit'];
    }

} catch (PDOException $e) {
    error_log('[admin/reports/profit] failed: ' . $e->getMessage());
    $profitSummary = [];
    $grossRevenue = $totalCogs = $totalProfit = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Profit Margin Analytics</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Audit estimated Cost of Goods Sold (COGS), gross yields, and net retail profitability.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Gross Revenue</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($grossRevenue, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #f08c00;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Est. COGS (65% share)</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalCogs, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #0ca678;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Net Profit (35% margin)</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalProfit, 2) ?></h2>
    </div>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Date</th>
                    <th style="padding:16px 20px; text-align:right; width:180px;">Gross Revenue</th>
                    <th style="padding:16px 20px; text-align:right; width:180px;">Est. Cost of Goods (COGS)</th>
                    <th style="padding:16px 20px; text-align:right; width:180px;">Net Profit (৳)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($profitSummary)): ?>
                    <?php foreach ($profitSummary as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong><?= date('M d, Y', strtotime($row['date'])) ?></strong></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['gross_revenue'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-faint);">৳<?= number_format((float)$row['estimated_cogs'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['net_profit'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding:32px; text-align:center; color:var(--color-text-faint);">No financial calculations recorded.</td>
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
