<?php
/**
 * ==========================================================================
 * admin/reports/customers.php — Top Customers Spending Report
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Top Customers Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    $topCustomers = $pdo->query("
        SELECT u.id, u.full_name, u.email, u.phone,
               COUNT(o.id) AS total_orders,
               SUM(o.total_amount) AS total_spent,
               ROUND(AVG(o.total_amount), 2) AS aov
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE o.status = 'delivered' AND u.role_id != 1
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 15
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/reports/customers] failed: ' . $e->getMessage());
    $topCustomers = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Top Customers Spending Report</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect registered customer lifetime contributions, order frequencies, and average purchase size.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Customer Profile</th>
                    <th style="padding:16px 20px;">Email / Phone</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Orders Fulfilled</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Avg Order Value</th>
                    <th style="padding:16px 20px; text-align:right; width:200px;">Lifetime Contribution</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($topCustomers)): ?>
                    <?php foreach ($topCustomers as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong><?= e($row['full_name']) ?></strong> <span style="color:var(--color-text-faint); font-size:11px;">#<?= $row['id'] ?></span></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($row['email']) ?><br><span style="font-size:11px;"><?= e($row['phone'] ?: 'N/A') ?></span></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700;"><?= $row['total_orders'] ?></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['aov'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_spent'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">No customer spending logs detected.</td>
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
