<?php
/**
 * ==========================================================================
 * admin/reports/inventory.php — Stock Levels & Inventory Value Report
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Inventory Valuation — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // List all active products, their stocks, unit prices, and stock valuation
    $inventory = $pdo->query("
        SELECT id, name, price, stock, (price * stock) AS valuation
        FROM products
        WHERE deleted_at IS NULL AND is_active = 1
        ORDER BY stock ASC, valuation DESC
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/reports/inventory] failed: ' . $e->getMessage());
    $inventory = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Warehouse Inventory Valuation</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Audit real-time warehouse stock volumes, units distribution, and book values.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:100px;">Product ID</th>
                    <th style="padding:16px 20px;">Product Name</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Unit Price</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">In Stock</th>
                    <th style="padding:16px 20px; text-align:right; width:200px;">Stock Valuation</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($inventory)): ?>
                    <?php foreach ($inventory as $row): 
                        $stock = (int)$row['stock'];
                        $stockBadge = 'pill-completed';
                        if ($stock <= 0) {
                            $stockBadge = 'pill-cancelled';
                        } elseif ($stock <= 10) {
                            $stockBadge = 'pill-pending';
                        }
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong>#<?= $row['id'] ?></strong></td>
                            <td style="padding:12px 20px;"><strong><?= e($row['name']) ?></strong></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['price'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:center;">
                                <span class="status-pill <?= $stockBadge ?>" style="font-size:9px;">
                                    <?= $stock ?> UNITS
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['valuation'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">No items found in active inventory.</td>
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
