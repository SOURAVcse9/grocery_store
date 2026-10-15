<?php
/**
 * ==============================================================================
 * admin/inventory/reorder_recommendations.php — Demand Planning & Reorder Intelligence
 * ==============================================================================
 * Reference: Matthew Cote (2015)
 *   "The Power of Point of Sale: Improving Growth, Profit, and Customer Service
 *    in a Retail Business" (Bryant University, pp. 6, 8, 10, 16)
 *
 * Capabilities:
 *  - Average Daily Sales (ADS) velocity tracking across 7d / 14d / 30d / 60d
 *  - Lead-time buffer and dynamic reorder threshold calculation
 *  - Estimated Days of Stock Remaining (Days of Supply)
 *  - Stockout detection & lost sales warning
 *  - CSV export for purchasing managers
 *  - Direct link to generate Purchase Order with pre-populated suggested quantities
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_permission('products.view');

$pdo = db();

// Query Parameters
$windowDays = max(7, min(90, (int) input('window', '30', 'get')));
$leadTimeDays = max(1, min(30, (int) input('lead_time', '5', 'get'))); // Default 5 days (Cote 2015, p. 8)
$categoryId = (int) input('category_id', '0', 'get');
$filterStatus = input('status', 'all', 'get'); // all | reorder_only | stockouts
$search = trim(input('search', '', 'get'));
$isExport = (input('export', '', 'get') === 'csv');

// Build product query with aggregate sales in historical window
$where = ['p.deleted_at IS NULL', 'p.is_active = 1'];
$params = [];

if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereSql = implode(' AND ', $where);

// Aggregate total quantity sold per product within the selected window
$stSales = $pdo->prepare("
    SELECT oi.product_id, COALESCE(SUM(oi.quantity), 0) AS total_sold
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status != 'cancelled'
      AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY oi.product_id
");
$stSales->execute([$windowDays]);
$salesMap = [];
foreach ($stSales->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $salesMap[(int) $s['product_id']] = (float) $s['total_sold'];
}

// Fetch products
$stProd = $pdo->prepare("
    SELECT p.id, p.name, p.sku, p.barcode, p.stock, p.min_stock, p.price, p.cost_price, p.unit,
           c.name AS category_name, b.name AS brand_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN brands b ON b.id = p.brand_id
    WHERE {$whereSql}
    ORDER BY p.stock ASC, p.name ASC
");
$stProd->execute($params);
$rawProducts = $stProd->fetchAll(PDO::FETCH_ASSOC);

// Process reorder metrics for each product
$recommendations = [];
$totalReorderValue = 0.0;
$stockoutCount = 0;
$reorderNeededCount = 0;

foreach ($rawProducts as $p) {
    $pid = (int) $p['id'];
    $currentStock = (float) ($p['stock'] ?? 0);
    $minStock = (float) (!empty($p['min_stock']) ? $p['min_stock'] : 5);
    $soldInWindow = $salesMap[$pid] ?? 0.0;
    
    // Average Daily Sales
    $ads = $soldInWindow / $windowDays;
    
    // Estimated days of stock remaining
    $daysRemaining = ($ads > 0.0001) ? ($currentStock / $ads) : (($currentStock > 0) ? 999.0 : 0.0);
    
    // Target safety stock + lead-time demand: (ADS * LeadTime) + MinStock
    $targetLevel = ($ads * $leadTimeDays) + $minStock;
    $suggestedReorder = max(0.0, ceil($targetLevel - $currentStock));
    
    // Perishable / Stock status
    $isStockout = ($currentStock <= 0.001);
    $isBelowMin = ($currentStock <= $minStock);
    $needsReorder = ($suggestedReorder > 0 || $isBelowMin || $isStockout);
    
    if ($isStockout) $stockoutCount++;
    if ($needsReorder) $reorderNeededCount++;

    $cost = (float) (!empty($p['cost_price']) ? $p['cost_price'] : ($p['price'] * 0.7));
    $estimatedCost = $suggestedReorder * $cost;
    if ($needsReorder) {
        $totalReorderValue += $estimatedCost;
    }

    $item = [
        'id'                => $pid,
        'name'              => $p['name'],
        'sku'               => $p['sku'],
        'barcode'           => $p['barcode'],
        'unit'              => $p['unit'] ?: 'pcs',
        'category'          => $p['category_name'] ?: 'General',
        'brand'             => $p['brand_name'] ?: 'N/A',
        'current_stock'     => $currentStock,
        'min_stock'         => $minStock,
        'sold_in_window'    => $soldInWindow,
        'ads'               => round($ads, 2),
        'days_remaining'    => ($daysRemaining >= 999) ? '999+' : (string) round($daysRemaining, 1),
        'suggested_reorder' => (int) $suggestedReorder,
        'unit_cost'         => $cost,
        'reorder_cost'      => round($estimatedCost, 2),
        'is_stockout'       => $isStockout,
        'is_below_min'      => $isBelowMin,
        'needs_reorder'     => $needsReorder,
    ];

    // Status filter
    if ($filterStatus === 'reorder_only' && !$needsReorder) continue;
    if ($filterStatus === 'stockouts' && !$isStockout) continue;

    $recommendations[] = $item;
}

// CSV Export Handler
if ($isExport) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="groco_reorder_recommendations_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Product ID', 'Product Name', 'SKU', 'Barcode', 'Category', 'Current Stock', 'Min Stock', 'Window Days', 'Units Sold', 'Average Daily Sales', 'Days Remaining', 'Lead Time (Days)', 'Suggested Reorder Qty', 'Est Unit Cost', 'Est Reorder Budget']);
    foreach ($recommendations as $r) {
        fputcsv($out, [
            $r['id'], $r['name'], $r['sku'], $r['barcode'], $r['category'],
            $r['current_stock'], $r['min_stock'], $windowDays, $r['sold_in_window'],
            $r['ads'], $r['days_remaining'], $leadTimeDays, $r['suggested_reorder'],
            $r['unit_cost'], $r['reorder_cost']
        ]);
    }
    fclose($out);
    exit;
}

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$currency = get_setting('currency_symbol', '৳');
$pageTitle = 'Inventory Demand Forecasting & Reorder Recommendations';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-dashboard-layout">
    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:24px; font-weight:800; margin:0 0 4px 0; color:var(--color-text);">
                <i class="fas fa-boxes-packing" style="color:var(--color-primary); margin-right:8px;"></i>
                Demand Forecasting &amp; Reorder Recommendations
            </h1>
            <p style="font-size:13px; color:var(--color-text-muted); margin:0;">
                Data-driven inventory replenishment based on historical sales velocity, safety stock, and lead time.
                (Ref: Matthew Cote 2015, pp. 6–10)
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-secondary">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <a href="<?= BASE_URL ?>/../admin/purchases/create.php" class="btn btn-primary" style="background:#047857;">
                <i class="fas fa-plus"></i> New Purchase Order
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
        <div class="dashboard-card" style="padding:16px; margin:0; border-left:4px solid #ef4444;">
            <span style="font-size:12px; font-weight:700; color:#ef4444; text-transform:uppercase;">Critical Stockouts</span>
            <div style="font-size:28px; font-weight:900; margin-top:4px; color:#ef4444;"><?= $stockoutCount ?></div>
            <span style="font-size:11px; color:var(--color-text-muted);">Unmet demand is unrecorded lost sales</span>
        </div>
        <div class="dashboard-card" style="padding:16px; margin:0; border-left:4px solid #f59e0b;">
            <span style="font-size:12px; font-weight:700; color:#f59e0b; text-transform:uppercase;">Products Requiring Reorder</span>
            <div style="font-size:28px; font-weight:900; margin-top:4px; color:#f59e0b;"><?= $reorderNeededCount ?></div>
            <span style="font-size:11px; color:var(--color-text-muted);">Stock below minimum or lead-time threshold</span>
        </div>
        <div class="dashboard-card" style="padding:16px; margin:0; border-left:4px solid #047857;">
            <span style="font-size:12px; font-weight:700; color:#047857; text-transform:uppercase;">Estimated Reorder Budget</span>
            <div style="font-size:28px; font-weight:900; margin-top:4px; color:#047857;"><?= $currency ?><?= number_format($totalReorderValue, 2) ?></div>
            <span style="font-size:11px; color:var(--color-text-muted);">Capital needed to replenish suggested items</span>
        </div>
        <div class="dashboard-card" style="padding:16px; margin:0; border-left:4px solid #3b82f6;">
            <span style="font-size:12px; font-weight:700; color:#3b82f6; text-transform:uppercase;">Analysis Window</span>
            <div style="font-size:28px; font-weight:900; margin-top:4px; color:#3b82f6;"><?= $windowDays ?> Days</div>
            <span style="font-size:11px; color:var(--color-text-muted);">Lead time configured: <?= $leadTimeDays ?> days</span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="dashboard-card" style="padding:16px 20px; margin-bottom:20px;">
        <form method="get" action="reorder_recommendations.php" style="display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end;">
            <div style="flex:2; min-width:180px;">
                <label style="font-size:12px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Search Product</label>
                <input type="text" name="search" class="form-control" placeholder="Search product name, SKU or barcode..." value="<?= e($search) ?>">
            </div>

            <div style="flex:1; min-width:140px;">
                <label style="font-size:12px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Category</label>
                <select name="category_id" class="form-control">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1; min-width:130px;">
                <label style="font-size:12px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Products</option>
                    <option value="reorder_only" <?= $filterStatus === 'reorder_only' ? 'selected' : '' ?>>Reorder Needed Only</option>
                    <option value="stockouts" <?= $filterStatus === 'stockouts' ? 'selected' : '' ?>>Stockouts Only (0 stock)</option>
                </select>
            </div>

            <div style="width:110px;">
                <label style="font-size:12px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">History Window</label>
                <select name="window" class="form-control">
                    <option value="7" <?= $windowDays === 7 ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="14" <?= $windowDays === 14 ? 'selected' : '' ?>>Last 14 Days</option>
                    <option value="30" <?= $windowDays === 30 ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="60" <?= $windowDays === 60 ? 'selected' : '' ?>>Last 60 Days</option>
                </select>
            </div>

            <div style="width:100px;">
                <label style="font-size:12px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Lead Time</label>
                <select name="lead_time" class="form-control">
                    <option value="3" <?= $leadTimeDays === 3 ? 'selected' : '' ?>>3 Days</option>
                    <option value="5" <?= $leadTimeDays === 5 ? 'selected' : '' ?>>5 Days</option>
                    <option value="7" <?= $leadTimeDays === 7 ? 'selected' : '' ?>>7 Days</option>
                    <option value="14" <?= $leadTimeDays === 14 ? 'selected' : '' ?>>14 Days</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary" style="padding:8px 18px;">
                    <i class="fas fa-filter"></i> Apply Analysis
                </button>
            </div>
        </form>
    </div>

    <!-- Recommendations Table -->
    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="table" style="width:100%; border-collapse:collapse; margin:0;">
                <thead>
                    <tr style="background:var(--color-bg-subtle, #f8fafc); border-bottom:1px solid var(--color-border, #e2e8f0); text-align:left; font-size:12px; color:var(--color-text-muted);">
                        <th style="padding:12px 16px;">Product / SKU</th>
                        <th style="padding:12px 14px;">Current Stock</th>
                        <th style="padding:12px 14px;">Min Threshold</th>
                        <th style="padding:12px 14px;"><?= $windowDays ?>d Sales</th>
                        <th style="padding:12px 14px;">Avg Daily Sales</th>
                        <th style="padding:12px 14px;">Days Left</th>
                        <th style="padding:12px 14px; text-align:center;">Suggested Reorder</th>
                        <th style="padding:12px 14px;">Est. Cost</th>
                        <th style="padding:12px 16px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php if (empty($recommendations)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:32px; color:var(--color-text-muted);">
                                No products match the current filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recommendations as $r): ?>
                            <?php 
                                $rowBg = '';
                                if ($r['is_stockout']) {
                                    $rowBg = 'background:rgba(239, 68, 68, 0.05);';
                                } elseif ($r['needs_reorder']) {
                                    $rowBg = 'background:rgba(245, 158, 11, 0.05);';
                                }
                            ?>
                            <tr style="border-bottom:1px solid var(--color-border, #f1f5f9); <?= $rowBg ?>">
                                <td style="padding:12px 16px;">
                                    <strong><?= e($r['name']) ?></strong>
                                    <div style="font-size:11px; color:var(--color-text-muted); font-family:monospace;">
                                        SKU: <?= e($r['sku']) ?> | <?= e($r['category']) ?>
                                    </div>
                                </td>
                                <td style="padding:12px 14px;">
                                    <?php if ($r['is_stockout']): ?>
                                        <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:800;">OUT OF STOCK</span>
                                    <?php elseif ($r['is_below_min']): ?>
                                        <span class="badge" style="background:#fef3c7; color:#b45309; font-weight:700;"><?= $r['current_stock'] ?> <?= e($r['unit']) ?></span>
                                    <?php else: ?>
                                        <span style="font-weight:600;"><?= $r['current_stock'] ?> <?= e($r['unit']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 14px; color:var(--color-text-muted);"><?= $r['min_stock'] ?> <?= e($r['unit']) ?></td>
                                <td style="padding:12px 14px; font-weight:600;"><?= $r['sold_in_window'] ?></td>
                                <td style="padding:12px 14px;"><?= $r['ads'] ?> / day</td>
                                <td style="padding:12px 14px;">
                                    <?php if ($r['days_remaining'] === '0'): ?>
                                        <span style="color:#ef4444; font-weight:800;">0 Days</span>
                                    <?php elseif ((float)$r['days_remaining'] <= 5): ?>
                                        <span style="color:#f59e0b; font-weight:700;"><?= $r['days_remaining'] ?> Days</span>
                                    <?php else: ?>
                                        <span><?= $r['days_remaining'] ?> Days</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 14px; text-align:center;">
                                    <?php if ($r['suggested_reorder'] > 0): ?>
                                        <span class="badge" style="background:#dcfce7; color:#15803d; font-size:13px; font-weight:800; padding:4px 10px;">
                                            +<?= $r['suggested_reorder'] ?> <?= e($r['unit']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--color-text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 14px; font-weight:700;">
                                    <?php if ($r['reorder_cost'] > 0): ?>
                                        <?= $currency ?><?= number_format($r['reorder_cost'], 2) ?>
                                    <?php else: ?>
                                        <span style="color:var(--color-text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 16px; text-align:right;">
                                    <?php if ($r['suggested_reorder'] > 0): ?>
                                        <a href="<?= BASE_URL ?>/../admin/purchases/create.php?product_id=<?= $r['id'] ?>&suggested_qty=<?= $r['suggested_reorder'] ?>" 
                                           class="btn btn-sm btn-primary" title="Create Purchase Order for this item">
                                            <i class="fas fa-cart-plus"></i> Order
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/../admin/products/edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary" title="View details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
