<?php
/**
 * ==========================================================================
 * admin/reports/coupons.php — Coupon Usage Reports
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Coupons Usage — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    $coupons = $pdo->query("
        SELECT id, code, type, usage_limit, times_used, is_active
        FROM coupons
        ORDER BY times_used DESC, created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/reports/coupons] failed: ' . $e->getMessage());
    $coupons = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Coupons Usage Analytics</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect promotional coupon popularity, remaining limits, and active states.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Coupon Code</th>
                    <th style="padding:16px 20px;">Type</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Redemptions</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Usage Limit</th>
                    <th style="padding:16px 20px; width:120px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($coupons)): ?>
                    <?php foreach ($coupons as $row): 
                        $status = (bool)$row['is_active'];
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong style="color:var(--color-primary);"><?= e($row['code']) ?></strong></td>
                            <td style="padding:12px 20px; text-transform:uppercase; font-size:11px;"><?= e(str_replace('_', ' ', $row['type'])) ?></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700;"><?= $row['times_used'] ?></td>
                            <td style="padding:12px 20px; text-align:center; color:var(--color-text-muted);"><?= $row['usage_limit'] ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">No promotional coupons registered.</td>
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
