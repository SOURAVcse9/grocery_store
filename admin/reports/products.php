<?php
/**
 * ==========================================================================
 * admin/reports/products.php — Product Analytics Report
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Products Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // Best Selling Products
    $bestSellers = $pdo->query("
        SELECT p.id, p.name, p.price, SUM(oi.quantity) AS sold_qty, SUM(oi.quantity * oi.price) AS total_revenue
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        WHERE o.status = 'delivered'
        GROUP BY p.id
        ORDER BY sold_qty DESC
        LIMIT 10
    ")->fetchAll();

    // Low Selling Products (active products with zero or lowest sales counts)
    $lowSellers = $pdo->query("
        SELECT p.id, p.name, p.price, p.stock, COALESCE(SUM(oi.quantity), 0) AS sold_qty
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN orders o ON o.id = oi.order_id AND o.status = 'delivered'
        WHERE p.deleted_at IS NULL AND p.is_active = 1
        GROUP BY p.id
        ORDER BY sold_qty ASC, p.stock DESC
        LIMIT 10
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/reports/products] failed: ' . $e->getMessage());
    $bestSellers = $lowSellers = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Product Sales Analytics</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect best-selling commodities, gross item yields, and slow-moving stock units.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left: Best Sellers -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0; color:var(--color-primary);"><i class="fas fa-crown"></i> Top 10 Best Sellers</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:12px 15px;">Product</th>
                        <th style="padding:12px 15px; text-align:center;">Qty Sold</th>
                        <th style="padding:12px 15px; text-align:right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bestSellers)): ?>
                        <?php foreach ($bestSellers as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:10px 15px;"><strong><?= e($row['name']) ?></strong></td>
                                <td style="padding:10px 15px; text-align:center; font-weight:700;"><?= $row['sold_qty'] ?></td>
                                <td style="padding:10px 15px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding:20px; text-align:center; color:var(--color-text-faint);">No product sales recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Slow Sellers -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0; color:#e03131;"><i class="fas fa-triangle-exclamation"></i> Low Selling / Dead Stock</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:12px 15px;">Product</th>
                        <th style="padding:12px 15px; text-align:center;">Qty Sold</th>
                        <th style="padding:12px 15px; text-align:center;">Current Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($lowSellers)): ?>
                        <?php foreach ($lowSellers as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:10px 15px;"><strong><?= e($row['name']) ?></strong></td>
                                <td style="padding:10px 15px; text-align:center; font-weight:700;"><?= $row['sold_qty'] ?></td>
                                <td style="padding:10px 15px; text-align:center; color:var(--color-text-muted);"><?= $row['stock'] ?> left</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding:20px; text-align:center; color:var(--color-text-faint);">All active products have high sales volume.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
