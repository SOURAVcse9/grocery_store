<?php
/**
 * ==========================================================================
 * admin/reports/tax.php — Tax Collection Summary Reports
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Tax Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // Collect delivered order totals, calculate tax share (assuming 5% of order totals is tax collected)
    $taxSummary = $pdo->query("
        SELECT DATE(created_at) AS date, SUM(total_amount) AS revenue, SUM(total_amount * 0.05) AS tax_collected
        FROM orders
        WHERE status = 'delivered'
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) DESC
        LIMIT 30
    ")->fetchAll();

    // Aggregates
    $totalRevenue = 0.0;
    $totalTax = 0.0;
    foreach ($taxSummary as $row) {
        $totalRevenue += (float)$row['revenue'];
        $totalTax += (float)$row['tax_collected'];
    }

} catch (PDOException $e) {
    error_log('[admin/reports/tax] failed: ' . $e->getMessage());
    $taxSummary = [];
    $totalRevenue = $totalTax = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Tax Collections Summary</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect tax liabilities, gross revenues, and net tax collection margins.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Gross Delivered Revenue</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalRevenue, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #339af0;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Total Estimated Tax Collected (5% rate)</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalTax, 2) ?></h2>
    </div>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Date</th>
                    <th style="padding:16px 20px; text-align:right; width:220px;">Gross Revenue</th>
                    <th style="padding:16px 20px; text-align:right; width:220px;">Tax Collected (৳)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($taxSummary)): ?>
                    <?php foreach ($taxSummary as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong><?= date('M d, Y', strtotime($row['date'])) ?></strong></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['revenue'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['tax_collected'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="padding:32px; text-align:center; color:var(--color-text-faint);">No tax records logged in the specified period.</td>
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
