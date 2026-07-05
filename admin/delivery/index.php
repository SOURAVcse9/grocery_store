<?php
/**
 * ==========================================================================
 * admin/delivery/index.php — Delivery Operations Dashboard
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Delivery Management — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('delivery.manage');

$pdo = db();

try {
    // Fetch assignments list
    $assignments = $pdo->query("
        SELECT da.*, db.name AS boy_name, db.phone AS boy_phone, o.order_number, o.status AS order_status
        FROM delivery_assignments da
        JOIN delivery_boys db ON db.id = da.delivery_boy_id
        JOIN orders o ON o.id = da.order_id
        ORDER BY da.created_at DESC
    ")->fetchAll();

    // Status counts
    $counts = [
        'assigned'  => 0,
        'picked_up' => 0,
        'delivered' => 0,
        'failed'    => 0,
        'returned'  => 0
    ];

    foreach ($assignments as $a) {
        $status = strtolower($a['status']);
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }

} catch (PDOException $e) {
    error_log('[admin/delivery/index] load failed: ' . $e->getMessage());
    $assignments = [];
    $counts = ['assigned'=>0, 'picked_up'=>0, 'delivered'=>0, 'failed'=>0, 'returned'=>0];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Delivery Management</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Dispatch delivery boys, verify orders OTP, and trace commissions payouts.</p>
    </div>
    
    <div style="display:flex; gap:10px;">
        <a href="assign.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-truck-dispatch"></i> Assign Order</a>
        <a href="boys.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-users"></i> Delivery Boys</a>
        <a href="reports.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-chart-pie"></i> Delivery Reports</a>
    </div>
</div>

<!-- Alert messages -->
<?php if (has_flash('delivery_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('delivery_msg') ?>
    </div>
<?php endif; ?>

<!-- Status counts cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #5c7cfa;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Assigned Dispatch</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $counts['assigned'] ?> Orders</h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #f08c00;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Picked Up</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $counts['picked_up'] ?> Orders</h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #0ca678;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Delivered</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $counts['delivered'] ?> Orders</h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #e03131;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Failed & Returned</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $counts['failed'] + $counts['returned'] ?> Orders</h2>
    </div>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Order Reference</th>
                    <th style="padding:16px 20px;">Assigned Delivery Boy</th>
                    <th style="padding:16px 20px;">Route Details</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">OTP Pin</th>
                    <th style="padding:16px 20px; width:130px;">Status</th>
                    <th style="padding:16px 20px; width:220px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $row): 
                        $status = strtolower($row['status']);
                        $pillClass = 'pill-pending';
                        if ($status === 'delivered') $pillClass = 'pill-completed';
                        if ($status === 'failed' || $status === 'returned') $pillClass = 'pill-cancelled';
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <strong><?= e($row['order_number']) ?></strong><br>
                                <span style="font-size:10px; color:var(--color-text-faint);">Assigned: <?= date('M d, H:i', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td style="padding:12px 20px;">
                                <strong><?= e($row['boy_name']) ?></strong><br>
                                <span style="font-size:11px; color:var(--color-text-faint);"><?= e($row['boy_phone']) ?></span>
                            </td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); font-size:12px;"><?= e($row['route_details'] ?: 'N/A') ?></td>
                            <td style="padding:12px 20px; text-align:center; font-family:monospace; font-weight:700; color:var(--color-primary);"><?= e($row['otp'] ?: 'N/A') ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill <?= $pillClass ?>" style="font-size:9px;">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <?php if ($status !== 'delivered' && $status !== 'returned'): ?>
                                        <a href="status.php?id=<?= $row['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen-to-square"></i> Update Status</a>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:var(--color-text-faint); font-weight:600;">Settled</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No order delivery assignments posted yet. Click "Assign Order" to start.</td>
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
