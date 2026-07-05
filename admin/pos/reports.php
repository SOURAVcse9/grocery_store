<?php
/**
 * ==========================================================================
 * admin/pos/reports.php — POS Performance Reports Summary
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'POS Performance Reports — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.report');

$pdo = db();

try {
    // 1. Total POS sales count & value
    $posSummary = $pdo->query("
        SELECT COUNT(*) AS total_tx, SUM(total_amount) AS total_sales
        FROM orders
        WHERE order_number LIKE 'POS-%' AND status = 'delivered'
    ")->fetch();

    $totalSalesVal = (float) ($posSummary['total_sales'] ?? 0.00);
    $totalSalesCount = (int) ($posSummary['total_tx'] ?? 0);

    // 2. Gateway splits (Cash register cash vs cards)
    // For simplicity, we can fetch dynamic POS payment shares
    $splits = $pdo->query("
        SELECT payment_method, COUNT(*) AS count, SUM(total_amount) AS total 
        FROM orders 
        WHERE order_number LIKE 'POS-%' AND status = 'delivered'
        GROUP BY payment_method
    ")->fetchAll();

    // 3. Hourly checkout sales performance
    $hourly = $pdo->query("
        SELECT HOUR(created_at) AS hour, COUNT(*) AS count, SUM(total_amount) AS revenue 
        FROM orders 
        WHERE order_number LIKE 'POS-%' AND status = 'delivered'
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/pos/reports] calculation failed: ' . $e->getMessage());
    $totalSalesVal = $totalSalesCount = 0;
    $splits = $hourly = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">POS Sales Analytics</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect hourly sales timelines, payment split shares, and counter transaction statistics.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Counter YTD Sales Volume</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalSalesVal, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #339af0;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Total Counter Checkouts</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $totalSalesCount ?> transactions</h2>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-5);" class="admin-dashboard-layout">
    
    <!-- Left: Payment Gateway splits share -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Payment Splits Breakdown</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Payment Gateway</th>
                        <th style="padding:10px 15px; text-align:center;">Transactions</th>
                        <th style="padding:10px 15px; text-align:right;">Accumulated Revenue (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($splits)): ?>
                        <?php foreach ($splits as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px; text-transform:uppercase;"><strong><?= e($row['payment_method']) ?></strong></td>
                                <td style="padding:8px 15px; text-align:center;"><?= $row['count'] ?> sales</td>
                                <td style="padding:8px 15px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding:20px; text-align:center; color:var(--color-text-faint);">No split payments recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Hourly sales traffic distribution -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Hourly Transaction Distributions</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Hour Period</th>
                        <th style="padding:10px 15px; text-align:center;">Checkouts</th>
                        <th style="padding:10px 15px; text-align:right;">Sales Value (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($hourly)): ?>
                        <?php foreach ($hourly as $row): 
                            $hourLabel = date('h:00 A', strtotime("{$row['hour']}:00"));
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px;"><strong><?= $hourLabel ?></strong></td>
                                <td style="padding:8px 15px; text-align:center;"><?= $row['count'] ?> checkouts</td>
                                <td style="padding:8px 15px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding:20px; text-align:center; color:var(--color-text-faint);">No hourly sales logs recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
