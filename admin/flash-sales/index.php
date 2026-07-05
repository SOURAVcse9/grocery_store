<?php
/**
 * ==========================================================================
 * admin/flash-sales/index.php — Flash Sales Campaign Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Flash Sales Manager — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('flashsales.manage');

$pdo = db();

try {
    $flashSales = $pdo->query("
        SELECT fs.*, p.name AS product_name, p.price AS product_price, p.image AS product_image
        FROM flash_sales fs
        JOIN products p ON p.id = fs.product_id
        ORDER BY fs.starts_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/flash-sales] load failed: ' . $e->getMessage());
    $flashSales = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Flash Sales Campaigns</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create, moderate, and schedule high-priority grocery item flash discounts for the home page countdown clock.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Flash Sale</a>
</div>

<!-- Alert messages -->
<?php if (has_flash('flash_sale_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('flash_sale_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:60px;">Image</th>
                    <th style="padding:16px 20px;">Product Name</th>
                    <th style="padding:16px 20px; width:120px; text-align:right;">Normal Price</th>
                    <th style="padding:16px 20px; width:120px; text-align:right;">Flash Discount</th>
                    <th style="padding:16px 20px; width:120px; text-align:right;">Flash Price</th>
                    <th style="padding:16px 20px; width:180px;">Scheduling Window</th>
                    <th style="padding:16px 20px; width:100px;">Status</th>
                    <th style="padding:16px 20px; width:130px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($flashSales)): ?>
                    <?php foreach ($flashSales as $fs): 
                        $status = (bool) ($fs['is_active'] ?? true);
                        $img = !empty($fs['product_image']) ? asset('uploads/products/' . $fs['product_image']) : asset('images/ui/placeholder.png');
                        $normalPrice = (float) $fs['product_price'];
                        $discountPct = (float) $fs['discount_percent'];
                        $flashPrice = $normalPrice * (1 - ($discountPct / 100));
                        
                        // Check if currently ongoing
                        $now = date('Y-m-d H:i:s');
                        $upcoming = ($fs['starts_at'] > $now);
                        $expired = ($fs['ends_at'] < $now);
                        
                        $schedBadge = 'pill-completed';
                        $schedText = 'Ongoing';
                        if ($upcoming) {
                            $schedBadge = 'pill-pending';
                            $schedText = 'Upcoming';
                        } elseif ($expired) {
                            $schedBadge = 'pill-cancelled';
                            $schedText = 'Expired';
                        }
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <div style="width:40px; height:40px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border); background:var(--color-bg);">
                                    <img src="<?= e($img) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($fs['product_name']) ?></strong></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-faint); text-decoration:line-through;">৳<?= number_format($normalPrice, 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; color:#f03e3e; font-weight:700;"><?= (int)$discountPct ?>% Off</td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format($flashPrice, 2) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); font-size:11px;">
                                <span class="status-pill <?= $schedBadge ?>" style="font-size:8px; display:inline-block; margin-bottom:4px;"><?= $schedText ?></span>
                                <div style="font-family:monospace;"><?= date('M d, H:i', strtotime($fs['starts_at'])) ?> – <?= date('M d, H:i', strtotime($fs['ends_at'])) ?></div>
                            </td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="edit.php?id=<?= $fs['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                    <a href="delete.php?id=<?= $fs['id'] ?>" onclick="return confirm('Permanently delete this flash sale campaign?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete campaign"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="padding:32px; text-align:center; color:var(--color-text-faint);">No flash sales campaigns configured. Click "Add Flash Sale" to create one.</td>
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
