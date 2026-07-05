<?php
/**
 * ==========================================================================
 * admin/reports/payments.php — Payment Gateway Reports Summary
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Payments Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // Group transactions by payment method and count completed amounts
    $payments = $pdo->query("
        SELECT payment_method, COUNT(*) AS transactions, SUM(total_amount) AS total_processed
        FROM orders
        WHERE status = 'delivered'
        GROUP BY payment_method
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/reports/payments] failed: ' . $e->getMessage());
    $payments = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Payment Gateway Summary</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect payment method usage statistics and gateway transaction shares.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Payment Method</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Transactions Volume</th>
                    <th style="padding:16px 20px; text-align:right; width:220px;">Total Funds Cleared</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px; text-transform:uppercase;"><strong><?= e(str_replace('_', ' ', $row['payment_method'])) ?></strong></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700;"><?= $row['transactions'] ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_processed'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="padding:32px; text-align:center; color:var(--color-text-faint);">No payment logs recorded.</td>
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
