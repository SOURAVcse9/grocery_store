<?php
/**
 * ==========================================================================
 * admin/delivery/reports.php — Delivery Commissions & Performance Reports
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Delivery Reports — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('delivery.manage');

$pdo = db();

try {
    // 1. Deliveries summary per boy (delivered counts and accumulated commission amount)
    $report = $pdo->query("
        SELECT db.name, db.phone,
               COUNT(da.id) AS total_assignments,
               SUM(CASE WHEN da.status = 'delivered' THEN 1 ELSE 0 END) AS successful_deliveries,
               SUM(CASE WHEN da.status = 'failed' THEN 1 ELSE 0 END) AS failed_deliveries,
               SUM(CASE WHEN da.status = 'returned' THEN 1 ELSE 0 END) AS returned_deliveries,
               SUM(CASE WHEN da.status = 'delivered' THEN da.commission_amount ELSE 0.00 END) AS earned_commissions
        FROM delivery_boys db
        LEFT JOIN delivery_assignments da ON da.delivery_boy_id = db.id
        GROUP BY db.id
        ORDER BY earned_commissions DESC
    ")->fetchAll();

    // 2. Aggregate stats
    $totalComm = 0.0;
    $totalDel = 0;
    foreach ($report as $row) {
        $totalComm += (float) $row['earned_commissions'];
        $totalDel += (int) $row['successful_deliveries'];
    }

} catch (PDOException $e) {
    error_log('[admin/delivery/reports] query failed: ' . $e->getMessage());
    $report = [];
    $totalComm = $totalDel = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Logistics & Commission Reports</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Track individual delivery boys statistics, successful dispatches, and outstanding commissions payouts.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Dispatch Feed</a>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Accumulated Delivery Commissions</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalComm, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #339af0;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Successful Fleet Deliveries</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $totalDel ?> Orders</h2>
    </div>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Delivery Boy</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Total Dispatches</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Successful</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Failed / Returned</th>
                    <th style="padding:16px 20px; text-align:right; width:200px;">Earned Commission (৳)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($report)): ?>
                    <?php foreach ($report as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <strong><?= e($row['name']) ?></strong><br>
                                <span style="font-size:11px; color:var(--color-text-faint);"><?= e($row['phone']) ?></span>
                            </td>
                            <td style="padding:12px 20px; text-align:center; color:var(--color-text-muted);"><?= $row['total_assignments'] ?></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700; color:#0ca678;"><?= $row['successful_deliveries'] ?></td>
                            <td style="padding:12px 20px; text-align:center; color:#e03131;"><?= $row['failed_deliveries'] ?> / <?= $row['returned_deliveries'] ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['earned_commissions'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">No logistics reports recorded yet.</td>
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
