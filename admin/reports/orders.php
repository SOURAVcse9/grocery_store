<?php
/**
 * ==========================================================================
 * admin/reports/orders.php — Order Reports Summary
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Orders Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // Orders count grouped by status
    $statusLogs = $pdo->query("
        SELECT status, COUNT(*) AS count, SUM(total_amount) AS revenue 
        FROM orders 
        GROUP BY status
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/reports/orders] failed: ' . $e->getMessage());
    $statusLogs = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Orders Volume Analysis</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect order fulfillment distributions and sales conversions by order state.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<!-- Table summary -->
<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Order Status</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Orders Count</th>
                    <th style="padding:16px 20px; text-align:right; width:220px;">Gross Volume</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($statusLogs)): ?>
                    <?php foreach ($statusLogs as $row): 
                        $status = strtolower($row['status']);
                        $pillClass = 'pill-pending';
                        if ($status === 'delivered') $pillClass = 'pill-completed';
                        if ($status === 'cancelled') $pillClass = 'pill-cancelled';
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <span class="status-pill <?= $pillClass ?>" style="font-size:10px;">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700;"><?= $row['count'] ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-text);">৳<?= number_format((float)$row['revenue'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="padding:32px; text-align:center; color:var(--color-text-faint);">No order transactions found.</td>
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
