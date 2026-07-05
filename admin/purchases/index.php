<?php
/**
 * ==========================================================================
 * admin/purchases/index.php — Purchase Orders (PO) List & GRN Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Purchase Orders — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('purchases.manage');

$pdo = db();

try {
    $orders = $pdo->query("
        SELECT po.*, s.name AS supplier_name 
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        ORDER BY po.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/purchases/index] load failed: ' . $e->getMessage());
    $orders = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Purchase Orders (PO)</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create procurement purchase orders, receive inventory stock shipments, and track vendor payments.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Purchase Order</a>
</div>

<!-- Alert messages -->
<?php if (has_flash('purchase_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('purchase_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">PO Number</th>
                    <th style="padding:16px 20px;">Wholesale Supplier</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Total Amount</th>
                    <th style="padding:16px 20px; width:180px;">Created Date</th>
                    <th style="padding:16px 20px; width:120px;">Fulfillment</th>
                    <th style="padding:16px 20px; width:220px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $row): 
                        $status = strtolower($row['status']);
                        $pillClass = 'pill-pending';
                        if ($status === 'received') $pillClass = 'pill-completed';
                        if ($status === 'cancelled') $pillClass = 'pill-cancelled';
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong><?= e($row['order_number']) ?></strong></td>
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($row['supplier_name']) ?></strong></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_amount'], 2) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill <?= $pillClass ?>" style="font-size:9px;">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <?php if ($status === 'pending'): ?>
                                        <a href="receive.php?id=<?= $row['id'] ?>" onclick="return confirm('Confirm Goods Received Note (GRN)? This will adjust stock levels of products.');" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-truck-ramp-box"></i> Receive Stock</a>
                                        <a href="delete.php?id=<?= $row['id'] ?>&action=cancel" onclick="return confirm('Cancel this procurement purchase order?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Cancel Order"><i class="fas fa-ban"></i></a>
                                    <?php endif; ?>
                                    <a href="delete.php?id=<?= $row['id'] ?>&action=delete" onclick="return confirm('Permanently delete this PO record?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete Record"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No purchase orders (POs) registered. Click "Add Purchase Order" to generate one.</td>
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
