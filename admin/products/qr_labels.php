<?php
/**
 * ==============================================================================
 * admin/products/qr_labels.php — Research-Based QR & Barcode Label Generator
 * ==============================================================================
 * Reference: Santosa & Wirawan (2019)
 *   "Design of Point of Sales (POS) Information Systems Based on Web and
 *    Quick Response (QR) Code" (ICAESS 2019, pp. 69–74, Fig. 7 & Fig. 14)
 *
 * Capabilities:
 *  - Generates secure, machine-scannable QR code labels for products
 *  - Encodes standardized payload: GROCO:SKU:<sku> or product barcode
 *  - Supports single item, batch category, or custom product list
 *  - Formats for standard adhesive label sheets (3-column shelf grid)
 *  - Fully functional offline via bundled qrcode.min.js
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_permission('products.view');

$pdo = db();

// Input parameters
$productId   = (int) input('product_id', 0, 'get');
$categoryId  = (int) input('category_id', 0, 'get');
$selectedIds = input('ids', '', 'get');
$copies      = max(1, min(50, (int) input('copies', 1, 'get')));
$labelSize   = input('size', 'standard', 'get'); // standard | compact | shelftalker

// Build query
$where = ['p.deleted_at IS NULL', 'p.is_active = 1'];
$params = [];

if ($productId > 0) {
    $where[] = 'p.id = ?';
    $params[] = $productId;
} elseif ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
} elseif ($selectedIds !== '') {
    $rawIds = explode(',', $selectedIds);
    $cleanIds = array_filter(array_map('intval', $rawIds));
    if (!empty($cleanIds)) {
        $ph = implode(',', array_fill(0, count($cleanIds), '?'));
        $where[] = "p.id IN ($ph)";
        $params = array_merge($params, $cleanIds);
    }
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.sku, p.barcode, p.price, p.discount_price, p.unit, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE {$whereSql}
    ORDER BY p.name ASC
    LIMIT 150
");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$siteName = get_setting('site_name', 'GroCo Grocery Store');
$currency = get_setting('currency_symbol', '৳');

$pageTitle = 'Print QR Code Product Labels';
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
/* Screen Display Controls */
.qr-print-controls {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: var(--radius-md, 8px);
    padding: 18px 24px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.qr-control-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.qr-control-group label {
    font-size: 12px;
    font-weight: 700;
    color: var(--color-text-muted, #64748b);
    text-transform: uppercase;
}

/* Label Grid Layout */
.qr-labels-sheet {
    display: grid;
    gap: 12px;
    background: transparent;
}

/* Standard Shelf Label (approx 65mm x 45mm) */
.qr-labels-sheet.format-standard {
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
}

.qr-label-card {
    background: #ffffff;
    color: #0f172a;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    padding: 10px;
    display: flex;
    gap: 10px;
    align-items: center;
    box-sizing: border-box;
    page-break-inside: avoid;
    break-inside: avoid;
}

.qr-label-code {
    flex-shrink: 0;
    width: 72px;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
}

.qr-label-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.qr-label-store {
    font-size: 9px;
    font-weight: 800;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.qr-label-title {
    font-size: 13px;
    font-weight: 800;
    line-height: 1.2;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.qr-label-sku {
    font-size: 10px;
    font-family: monospace;
    font-weight: 600;
    color: #334155;
}

.qr-label-price-box {
    margin-top: 4px;
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.qr-label-price {
    font-size: 16px;
    font-weight: 900;
    color: #047857;
}

.qr-label-unit {
    font-size: 10px;
    color: #64748b;
    font-weight: 600;
}

/* Compact Format */
.qr-labels-sheet.format-compact {
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
}
.qr-labels-sheet.format-compact .qr-label-card {
    padding: 6px;
    gap: 6px;
}
.qr-labels-sheet.format-compact .qr-label-code {
    width: 52px;
    height: 52px;
}
.qr-labels-sheet.format-compact .qr-label-title {
    font-size: 11px;
}
.qr-labels-sheet.format-compact .qr-label-price {
    font-size: 13px;
}

/* Shelf Talker Format */
.qr-labels-sheet.format-shelftalker {
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
}
.qr-labels-sheet.format-shelftalker .qr-label-card {
    padding: 16px;
    border: 2px solid #0f172a;
}
.qr-labels-sheet.format-shelftalker .qr-label-code {
    width: 96px;
    height: 96px;
}
.qr-labels-sheet.format-shelftalker .qr-label-title {
    font-size: 15px;
}
.qr-labels-sheet.format-shelftalker .qr-label-price {
    font-size: 22px;
}

/* Print Specific Rules (Hide layout elements) */
@media print {
    @page {
        margin: 8mm;
        size: auto;
    }
    body, html {
        background: #ffffff !important;
        color: #000000 !important;
        font-family: system-ui, -apple-system, sans-serif !important;
    }
    .admin-sidebar,
    .admin-topbar,
    .qr-print-controls,
    .admin-footer,
    .btn,
    nav,
    aside {
        display: none !important;
    }
    .admin-main, .admin-content, .admin-dashboard-layout {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .qr-labels-sheet {
        display: grid !important;
        gap: 6mm !important;
    }
    .qr-labels-sheet.format-standard {
        grid-template-columns: repeat(3, 1fr) !important;
    }
    .qr-labels-sheet.format-compact {
        grid-template-columns: repeat(4, 1fr) !important;
    }
    .qr-labels-sheet.format-shelftalker {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    .qr-label-card {
        border: 1px solid #94a3b8 !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>

<div class="admin-dashboard-layout">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1 style="font-size:24px; font-weight:800; margin:0 0 4px 0; color:var(--color-text);">
                <i class="fas fa-qrcode" style="color:var(--color-primary); margin-right:8px;"></i>
                Product QR Code & Barcode Labels
            </h1>
            <p style="font-size:13px; color:var(--color-text-muted); margin:0;">
                Generate and print scannable QR labels for retail shelves, produce bins, and packaging.
                (Ref: Santosa &amp; Wirawan 2019)
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="<?= BASE_URL ?>/../admin/products/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
            <button type="button" onclick="window.print();" class="btn btn-primary" style="background:#047857;">
                <i class="fas fa-print"></i> Print Labels Now
            </button>
        </div>
    </div>

    <!-- Filter & Configuration Toolbar -->
    <form method="get" action="qr_labels.php" class="qr-print-controls">
        <div class="qr-control-group" style="flex:2; min-width:200px;">
            <label for="category_id">Filter by Category</label>
            <select name="category_id" id="category_id" class="form-control" onchange="this.form.submit();">
                <option value="0">-- All Categories (<?= count($products) ?> items) --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="qr-control-group" style="flex:1; min-width:140px;">
            <label for="size">Label Format</label>
            <select name="size" id="size" class="form-control" onchange="this.form.submit();">
                <option value="standard" <?= $labelSize === 'standard' ? 'selected' : '' ?>>Standard Shelf (65×45mm)</option>
                <option value="compact" <?= $labelSize === 'compact' ? 'selected' : '' ?>>Compact Sticker (45×25mm)</option>
                <option value="shelftalker" <?= $labelSize === 'shelftalker' ? 'selected' : '' ?>>Shelf Talker (Large)</option>
            </select>
        </div>

        <div class="qr-control-group" style="width:110px;">
            <label for="copies">Copies / Label</label>
            <input type="number" name="copies" id="copies" class="form-control" min="1" max="50" value="<?= $copies ?>" onchange="this.form.submit();">
        </div>

        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button type="button" onclick="window.print();" class="btn btn-success">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </form>

    <?php if (empty($products)): ?>
        <div class="dashboard-card" style="text-align:center; padding:48px;">
            <i class="fas fa-barcode" style="font-size:48px; color:var(--color-text-muted); margin-bottom:16px;"></i>
            <h3>No products found for label generation</h3>
            <p style="color:var(--color-text-muted);">Please select a category or verify that products are published and active.</p>
        </div>
    <?php else: ?>
        <!-- Labels Sheet Container -->
        <div class="qr-labels-sheet format-<?= e($labelSize) ?>" id="labelsSheet">
            <?php foreach ($products as $p): ?>
                <?php 
                    $effPrice = (!empty($p['discount_price']) && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['price'])
                        ? (float)$p['discount_price']
                        : (float)$p['price'];
                    // Structured QR Payload: GROCO:SKU:<sku>
                    $qrPayload = 'GROCO:SKU:' . (!empty($p['sku']) ? $p['sku'] : 'ID' . $p['id']);
                ?>
                <?php for ($c = 0; $c < $copies; $c++): ?>
                    <div class="qr-label-card">
                        <div class="qr-label-code" data-qr="<?= e($qrPayload) ?>" id="qr-<?= $p['id'] ?>-<?= $c ?>">
                            <!-- Rendered by QRCode.js -->
                        </div>
                        <div class="qr-label-info">
                            <span class="qr-label-store"><?= e($siteName) ?></span>
                            <div class="qr-label-title" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></div>
                            <div class="qr-label-sku">
                                SKU: <?= e($p['sku'] ?: 'N/A') ?>
                                <?php if (!empty($p['barcode'])): ?>
                                    | BC: <?= e($p['barcode']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="qr-label-price-box">
                                <span class="qr-label-price"><?= $currency ?><?= number_format($effPrice, 2) ?></span>
                                <?php if (!empty($p['unit'])): ?>
                                    <span class="qr-label-unit">/ <?= e($p['unit']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Bundled Offline QRCode Generator -->
<script src="<?= BASE_URL ?>/../admin/assets/js/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const codeElements = document.querySelectorAll('.qr-label-code');
    const size = '<?= $labelSize ?>';
    let pixelSize = 64;
    if (size === 'compact') pixelSize = 48;
    if (size === 'shelftalker') pixelSize = 88;

    codeElements.forEach(function (el) {
        const payload = el.getAttribute('data-qr');
        if (!payload) return;
        
        el.innerHTML = '';
        try {
            new QRCode(el, {
                text: payload,
                width: pixelSize,
                height: pixelSize,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        } catch (err) {
            console.error('QR Render error:', err);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
