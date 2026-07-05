<?php
/**
 * ==========================================================================
 * admin/suppliers/index.php — Suppliers Directory
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Suppliers Manager — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('purchases.manage');

$pdo = db();

try {
    $suppliers = $pdo->query("
        SELECT s.*, 
               (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS total_orders
        FROM suppliers s
        ORDER BY s.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/suppliers/index] load failed: ' . $e->getMessage());
    $suppliers = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Suppliers Directory</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage external merchandise wholesale vendors, contact registry, and purchase parameters.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Supplier</a>
</div>

<!-- Alert messages -->
<?php if (has_flash('supplier_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('supplier_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Supplier Name</th>
                    <th style="padding:16px 20px;">Contact Person</th>
                    <th style="padding:16px 20px;">Email / Phone</th>
                    <th style="padding:16px 20px; width:300px;">Business Address</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Purchase Orders</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($s['name']) ?></strong></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($s['contact_name'] ?: 'N/A') ?></td>
                            <td style="padding:12px 20px;">
                                <strong><?= e($s['email'] ?: 'N/A') ?></strong><br>
                                <span style="font-size:11px; color:var(--color-text-faint);"><?= e($s['phone'] ?: 'N/A') ?></span>
                            </td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); font-size:12px;"><?= e($s['address'] ?: 'N/A') ?></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700; color:var(--color-primary);"><?= $s['total_orders'] ?> POs</td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                    <a href="delete.php?id=<?= $s['id'] ?>" onclick="return confirm('Permanently remove this supplier vendor?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete Supplier"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No suppliers currently registered. Click "Add Supplier" to register one.</td>
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
