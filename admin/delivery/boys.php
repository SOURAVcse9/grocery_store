<?php
/**
 * ==========================================================================
 * admin/delivery/boys.php — Delivery Boys Directory
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Delivery Personnel — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('delivery.manage');

$pdo = db();

try {
    $boys = $pdo->query("
        SELECT db.*,
               (SELECT COUNT(*) FROM delivery_assignments da WHERE da.delivery_boy_id = db.id) AS total_deliveries
        FROM delivery_boys db
        ORDER BY db.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/delivery/boys] load failed: ' . $e->getMessage());
    $boys = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Delivery Personnel</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage logistics agents, active dispatch status, and commission parameters.</p>
    </div>
    
    <div style="display:flex; gap:10px;">
        <a href="boys_create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-plus"></i> Add Delivery Boy</a>
        <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Dispatch Feed</a>
    </div>
</div>

<!-- Alert messages -->
<?php if (has_flash('boy_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('boy_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Name</th>
                    <th style="padding:16px 20px;">Contact Details</th>
                    <th style="padding:16px 20px; text-align:right; width:180px;">Commission Rate / Delivery</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Total Dispatches</th>
                    <th style="padding:16px 20px; width:120px;">Status</th>
                    <th style="padding:16px 20px; width:180px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($boys)): ?>
                    <?php foreach ($boys as $b): 
                        $status = strtolower($b['status']);
                        $isActive = ($status === 'active');
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($b['name']) ?></strong></td>
                            <td style="padding:12px 20px;">
                                <strong><?= e($b['email']) ?></strong><br>
                                <span style="font-size:11px; color:var(--color-text-faint);"><?= e($b['phone']) ?></span>
                            </td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$b['commission_rate'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700;"><?= $b['total_deliveries'] ?> orders</td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $isActive ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="boys_edit.php?id=<?= $b['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                    <a href="boys_delete.php?id=<?= $b['id'] ?>" onclick="return confirm('Permanently remove this delivery personnel from registry?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete Personnel"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No delivery personnel registered. Click "Add Delivery Boy" to add one.</td>
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
