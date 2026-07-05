<?php
/**
 * ==========================================================================
 * admin/inventory/index.php — Master Inventory Database & Valuation Sheet
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Inventory Manager — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('inventory.manage');

$pdo = db();
$search = trim(input('search', '', 'get'));
$stockAlert = trim(input('stock_alert', '', 'get')); // 'low', 'out'

$where = ['p.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $where[] = 'p.name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

if ($stockAlert === 'low') {
    $where[] = 'p.stock <= 10 AND p.stock > 0';
} elseif ($stockAlert === 'out') {
    $where[] = 'p.stock = 0';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    $inventory = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.stock, p.image, (p.price * p.stock) AS valuation
        FROM products p
        {$whereClause}
        ORDER BY p.stock ASC, p.name ASC
    ");
    $inventory->execute($params);
    $items = $inventory->fetchAll();

    // Total counts
    $totalValuation = 0.0;
    $lowStockCount = 0;
    $outOfStockCount = 0;

    foreach ($items as $row) {
        $totalValuation += (float)$row['valuation'];
        $stock = (int)$row['stock'];
        if ($stock === 0) {
            $outOfStockCount++;
        } elseif ($stock <= 10) {
            $lowStockCount++;
        }
    }

} catch (PDOException $e) {
    error_log('[admin/inventory/index] load failed: ' . $e->getMessage());
    $items = [];
    $totalValuation = 0;
    $lowStockCount = $outOfStockCount = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Master Inventory Ledger</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect current warehouse stock levels, low-stock warnings, and book values.</p>
    </div>
    <a href="export.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-file-csv"></i> Export Valuation Sheet</a>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Total Inventory Valuation</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalValuation, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #f08c00;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Low Stock Warnings</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $lowStockCount ?> Products</h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #e03131;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Out of Stock</span>
        <h2 style="margin:4px 0 0 0; font-size:20px; font-weight:800; color:var(--color-text);"><?= $outOfStockCount ?> Products</h2>
    </div>
</div>

<!-- Search & Filters -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:flex; gap:12px; align-items:end; max-width:600px;">
        <div class="form-field-group" style="margin:0; flex:1.5;">
            <input type="text" name="search" placeholder="Search by product name..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>
        <div class="form-field-group" style="margin:0; flex:1;">
            <select name="stock_alert" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Stock Levels</option>
                <option value="low" <?= $stockAlert === 'low' ? 'selected' : '' ?>>Low Stock Only</option>
                <option value="out" <?= $stockAlert === 'out' ? 'selected' : '' ?>>Out of Stock Only</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
        <a href="index.php" class="btn btn-secondary" style="padding:9px 18px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; font-weight:700; text-align:center;">Clear</a>
    </form>
</div>

<!-- Inventory table -->
<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:60px;">Image</th>
                    <th style="padding:16px 20px;">Product Name</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Unit Price</th>
                    <th style="padding:16px 20px; text-align:center; width:150px;">Stock Units</th>
                    <th style="padding:16px 20px; text-align:right; width:180px;">Valuation</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $row): 
                        $stock = (int)$row['stock'];
                        $stockBadge = 'pill-completed';
                        $stockText = "{$stock} Units";
                        
                        if ($stock === 0) {
                            $stockBadge = 'pill-cancelled';
                            $stockText = 'OUT OF STOCK';
                        } elseif ($stock <= 10) {
                            $stockBadge = 'pill-pending';
                            $stockText = "LOW STOCK ({$stock})";
                        }

                        $img = !empty($row['image']) ? asset('uploads/products/' . $row['image']) : asset('images/ui/placeholder.png');
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <div style="width:40px; height:40px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border); background:var(--color-bg);">
                                    <img src="<?= e($img) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td style="padding:12px 20px;"><strong><?= e($row['name']) ?></strong> <span style="color:var(--color-text-faint); font-size:11px;">#<?= $row['id'] ?></span></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['price'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:center;">
                                <span class="status-pill <?= $stockBadge ?>" style="font-size:9px;">
                                    <?= $stockText ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['valuation'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right;">
                                <a href="ledger.php?id=<?= $row['id'] ?>" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-list-check"></i> Stock Movements</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No product stock records found matching criteria.</td>
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
