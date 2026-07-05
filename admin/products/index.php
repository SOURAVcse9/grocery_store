<?php
/**
 * ==========================================================================
 * admin/products/index.php — Enterprise Products List & Controls
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Manage Products — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('products.view');

$pdo = db();

// Handle Bulk Actions
if (method_is('post') && isset($_POST['bulk_action'])) {
    verify_csrf_or_fail();
    
    $action = input('bulk_action', '');
    $selectedIds = $_POST['selected_products'] ?? [];
    
    if (is_array($selectedIds) && !empty($selectedIds)) {
        // Clean ids
        $ids = array_map('intval', $selectedIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        try {
            if ($action === 'delete') {
                $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                log_admin_activity('products.bulk_delete', 'Soft-deleted ' . count($ids) . ' products in bulk.');
                flash('products_msg', 'Selected products have been soft-deleted.', 'success');
            } elseif ($action === 'restore') {
                $stmt = $pdo->prepare("UPDATE products SET deleted_at = NULL WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                log_admin_activity('products.bulk_restore', 'Restored ' . count($ids) . ' products in bulk.');
                flash('products_msg', 'Selected products have been restored.', 'success');
            } elseif ($action === 'publish') {
                $stmt = $pdo->prepare("UPDATE products SET status = 'Published', is_active = 1 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                log_admin_activity('products.bulk_publish', 'Published ' . count($ids) . ' products in bulk.');
                flash('products_msg', 'Selected products are now published.', 'success');
            } elseif ($action === 'draft') {
                $stmt = $pdo->prepare("UPDATE products SET status = 'Draft', is_active = 0 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                log_admin_activity('products.bulk_draft', 'Set ' . count($ids) . ' products to draft status in bulk.');
                flash('products_msg', 'Selected products set to draft status.', 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/products] Bulk action fail: ' . $e->getMessage());
            flash('products_msg', 'Bulk updates failed due to internal error.', 'error');
        }
    }
    redirect(current_url());
}

// Fetch Search and Filter query parameters
$search = trim(input('search', '', 'get'));
$catFilter = (int) input('category_id', '0', 'get');
$brandFilter = (int) input('brand_id', '0', 'get');
$stockFilter = trim(input('stock', '', 'get')); // 'low', 'out', 'all'
$statusFilter = trim(input('status', '', 'get')); // 'Draft', 'Published', 'Hidden', 'Archived', 'Inactive'
$viewFilter = trim(input('view', 'active', 'get')); // 'active', 'trash'
$sort = trim(input('sort', 'newest', 'get'));
$page = (int) input('page', '1', 'get');

if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Construct SQL query
$where = [];
$params = [];

if ($viewFilter === 'trash') {
    $where[] = 'p.deleted_at IS NOT NULL';
} else {
    $where[] = 'p.deleted_at IS NULL';
}

if (!empty($search)) {
    $where[] = '(p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search OR p.slug LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($catFilter > 0) {
    $where[] = 'p.category_id = :cat';
    $params['cat'] = $catFilter;
}

if ($brandFilter > 0) {
    $where[] = 'p.brand_id = :brand';
    $params['brand'] = $brandFilter;
}

if ($stockFilter === 'low') {
    $where[] = 'p.stock <= p.min_stock AND p.stock > 0';
} elseif ($stockFilter === 'out') {
    $where[] = 'p.stock = 0';
}

if (!empty($statusFilter)) {
    $where[] = 'p.status = :status';
    $params['status'] = $statusFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Sort mapping
$orderBy = 'p.created_at DESC';
if ($sort === 'oldest') {
    $orderBy = 'p.created_at ASC';
} elseif ($sort === 'price_asc') {
    $orderBy = 'p.price ASC';
} elseif ($sort === 'price_desc') {
    $orderBy = 'p.price DESC';
} elseif ($sort === 'stock_asc') {
    $orderBy = 'p.stock ASC';
}

try {
    // Count total matches
    $countSql = "
        SELECT COUNT(*) 
        FROM products p
        {$whereClause}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    // Fetch products
    $selectSql = "
        SELECT p.*, c.name AS category_name, b.name AS brand_name 
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        {$whereClause}
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Fetch Categories & Brands for filters
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();

    // Count totals for tabs
    $activeCount = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
    $trashCount = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NOT NULL")->fetchColumn();

} catch (PDOException $e) {
    error_log('[admin/products] Index fetch fail: ' . $e->getMessage());
    $products = $categories = $brands = [];
    $totalCount = $activeCount = $trashCount = 0;
    $totalPages = 1;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Product Inventory</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create, edit, duplicate, adjust stock values, and organize storefront items.</p>
    </div>
    <?php if (has_admin_permission('products.create')): ?>
        <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add New Product</a>
    <?php endif; ?>
</div>

<!-- Trashed / Active Tabs -->
<div style="display:flex; gap:12px; margin-bottom:var(--space-4); border-bottom:1px solid var(--color-border); padding-bottom:10px;">
    <a href="?view=active" style="text-decoration:none; font-size:12px; font-weight:700; color:<?= $viewFilter === 'active' ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>; border-bottom:2px solid <?= $viewFilter === 'active' ? 'var(--color-primary)' : 'transparent' ?>; padding-bottom:8px;">
        Active Products (<?= $activeCount ?>)
    </a>
    <a href="?view=trash" style="text-decoration:none; font-size:12px; font-weight:700; color:<?= $viewFilter === 'trash' ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>; border-bottom:2px solid <?= $viewFilter === 'trash' ? 'var(--color-primary)' : 'transparent' ?>; padding-bottom:8px;">
        Trash Bin (<?= $trashCount ?>)
    </a>
</div>

<!-- Alert messages -->
<?php display_flash_alerts('products_msg'); ?>

<!-- Filters Form -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) auto; gap:12px; align-items:end;" class="grid-7">
        <input type="hidden" name="view" value="<?= e($viewFilter) ?>">
        
        <!-- Search -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Keyword Search</label>
            <input type="text" name="search" placeholder="Name, SKU, Slug..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <!-- Category -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Category</label>
            <select name="category_id" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Brand -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Brand</label>
            <select name="brand_id" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="0">All Brands</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $brandFilter === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Stock -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Stock Level</label>
            <select name="stock" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Stocks</option>
                <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock</option>
                <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>

        <!-- Status -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Status</label>
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Statuses</option>
                <option value="Published" <?= $statusFilter === 'Published' ? 'selected' : '' ?>>Published</option>
                <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Hidden" <?= $statusFilter === 'Hidden' ? 'selected' : '' ?>>Hidden</option>
                <option value="Archived" <?= $statusFilter === 'Archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>

        <!-- Sort -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Sort Order</label>
            <select name="sort" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>Stock: Low to High</option>
            </select>
        </div>

        <!-- Buttons -->
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
            <a href="?view=<?= e($viewFilter) ?>" class="btn btn-secondary" style="padding:9px 18px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; font-weight:700; text-align:center;">Clear</a>
        </div>
    </form>
</div>

<!-- Bulk Selection Form -->
<form method="post" id="productsTableForm">
    <?= csrf_field() ?>

    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <!-- Table Action Header -->
        <div style="padding:var(--space-3) var(--space-5); border-bottom:1px solid var(--color-border); background:var(--color-bg); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <!-- Bulk select action dropdown -->
            <div style="display:flex; align-items:center; gap:10px;">
                <select name="bulk_action" id="bulkActionSelect" style="padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; background:#fff; outline:none; font-weight:600;">
                    <option value="">Bulk Actions</option>
                    <?php if ($viewFilter === 'trash'): ?>
                        <option value="restore">Bulk Restore</option>
                    <?php else: ?>
                        <option value="delete">Bulk Soft Delete</option>
                        <option value="publish">Set Status: Published</option>
                        <option value="draft">Set Status: Draft</option>
                    <?php endif; ?>
                </select>
                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction();" style="padding:6px 12px; font-size:12px; border:none; border-radius:var(--radius-sm); font-weight:700;">Apply</button>
            </div>
            <span style="font-size:12px; color:var(--color-text-muted); font-weight:600;"><?= $totalCount ?> items found</span>
        </div>

        <!-- Data table -->
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px; width:40px; text-align:center;">
                            <input type="checkbox" id="selectAllCheckbox" style="cursor:pointer; width:14px; height:14px;">
                        </th>
                        <th style="padding:16px 20px; width:60px;">Image</th>
                        <th style="padding:16px 20px;">Product Name</th>
                        <th style="padding:16px 20px; width:100px;">SKU</th>
                        <th style="padding:16px 20px;">Category</th>
                        <th style="padding:16px 20px;">Brand</th>
                        <th style="padding:16px 20px; width:100px;">Price</th>
                        <th style="padding:16px 20px; width:80px;">Stock</th>
                        <th style="padding:16px 20px; width:90px;">Status</th>
                        <th style="padding:16px 20px; width:140px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): 
                            $status = $p['status'];
                            $badgeColor = '#adb5bd';
                            if ($status === 'Published') $badgeColor = '#0ca678';
                            elseif ($status === 'Draft') $badgeColor = '#f59f00';
                            elseif ($status === 'Hidden') $badgeColor = '#7048e8';
                            
                            $stock = (int) $p['stock'];
                            $minStock = (int) ($p['min_stock'] ?? 5);
                            $stockClass = 'color:var(--color-text);';
                            if ($stock === 0) {
                                $stockClass = 'color:#f03e3e; font-weight:800;';
                            } elseif ($stock <= $minStock) {
                                $stockClass = 'color:#f59f00; font-weight:700;';
                            }
                            
                            $imgUrl = image_url($p['thumbnail'], 'products');
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px; text-align:center;">
                                    <input type="checkbox" name="selected_products[]" value="<?= $p['id'] ?>" class="product-item-checkbox" style="cursor:pointer; width:14px; height:14px;">
                                </td>
                                <td style="padding:12px 20px;">
                                    <div style="width:40px; height:40px; border-radius:4px; border:1px solid var(--color-border); overflow:hidden; background:var(--color-bg);">
                                        <img src="<?= e($imgUrl) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                </td>
                                <td style="padding:12px 20px;">
                                    <strong style="color:var(--color-text); display:block;"><?= e($p['name']) ?></strong>
                                    <?php if ((bool)($p['is_featured'] ?? false)): ?>
                                        <span style="font-size:9px; background:#e6fcf5; color:#0ca678; font-weight:700; padding:1px 4px; border-radius:2px; display:inline-block; margin-top:2px;">FEATURED</span>
                                    <?php endif; ?>
                                    <?php if ((bool)($p['is_flash_sale'] ?? false)): ?>
                                        <span style="font-size:9px; background:#fff5f5; color:#f03e3e; font-weight:700; padding:1px 4px; border-radius:2px; display:inline-block; margin-top:2px;">FLASH SALE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-muted); font-family:monospace;"><?= e($p['sku']) ?></td>
                                <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($p['category_name'] ?? 'Uncategorized') ?></td>
                                <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($p['brand_name'] ?? 'No Brand') ?></td>
                                <td style="padding:12px 20px;">
                                    <?php if (!empty($p['discount_price']) && (float)$p['discount_price'] > 0): ?>
                                        <strong style="color:#f03e3e;">৳<?= number_format((float)$p['discount_price'], 2) ?></strong>
                                        <span style="font-size:10px; text-decoration:line-through; color:var(--color-text-faint); display:block;">৳<?= number_format((float)$p['price'], 2) ?></span>
                                    <?php else: ?>
                                        <strong>৳<?= number_format((float)$p['price'], 2) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 20px; <?= $stockClass ?>"><?= $stock ?></td>
                                <td style="padding:12px 20px;">
                                    <span style="background:<?= $badgeColor ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; text-transform:uppercase;">
                                        <?= e($status) ?>
                                    </span>
                                </td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <div style="display:inline-flex; gap:6px;">
                                        <?php if ($viewFilter === 'trash'): ?>
                                            <!-- Restore Action -->
                                            <a href="delete.php?action=restore&id=<?= $p['id'] ?>&csrf_token=<?= csrf_token() ?>" onclick="return confirm('Restore this product?');" class="btn btn-primary" style="padding:4px 10px; font-size:10px; background:#0ca678; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-rotate-left"></i> Restore</a>
                                            <!-- Permanent Delete Action -->
                                            <a href="delete.php?action=permanent&id=<?= $p['id'] ?>&csrf_token=<?= csrf_token() ?>" onclick="return confirm('PERMANENTLY DELETE this product and delete its files? This is irreversible.');" class="btn btn-secondary" style="padding:4px 10px; font-size:10px; background:#f03e3e; border-radius:var(--radius-sm); text-decoration:none; color:#fff;"><i class="fas fa-trash-can"></i> Destroy</a>
                                        <?php else: ?>
                                            <!-- Clone/Duplicate Action -->
                                            <a href="duplicate.php?id=<?= $p['id'] ?>&csrf_token=<?= csrf_token() ?>" onclick="return confirm('Clone this product and create a draft duplicate?');" class="btn btn-secondary" style="padding:4px 10px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none; background:#4263eb; color:#fff;" title="Clone / Duplicate"><i class="far fa-copy"></i></a>
                                            <!-- Edit Action -->
                                            <?php if (has_admin_permission('products.edit')): ?>
                                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i></a>
                                            <?php endif; ?>
                                            <!-- Soft Delete Action -->
                                            <?php if (has_admin_permission('products.delete')): ?>
                                                <a href="delete.php?action=soft&id=<?= $p['id'] ?>&csrf_token=<?= csrf_token() ?>" onclick="return confirm('Soft-delete this product and move it to the trash bin?');" class="btn btn-secondary" style="padding:4px 10px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none; background:#f03e3e; color:#fff;" title="Move to Trash"><i class="fas fa-trash-can"></i></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="padding:32px; text-align:center; color:var(--color-text-faint);">No products match your search filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; padding:16px; background:var(--color-bg); border-top:1px solid var(--color-border); gap:6px;">
                <?php for ($p = 1; $p <= $totalPages; $p++): 
                    $paramsQuery = $_GET;
                    $paramsQuery['page'] = $p;
                    $url = '?' . http_build_query($paramsQuery);
                ?>
                    <a href="<?= $url ?>" class="pagination-link <?= $page === $p ? 'active' : '' ?>" style="display:inline-flex; width:32px; height:32px; align-items:center; justify-content:center; border:1px solid var(--color-border); border-radius:50%; text-decoration:none; font-size:11px; font-weight:700; <?= $page === $p ? 'background:var(--color-primary); color:#fff; border-color:var(--color-primary);' : 'color:var(--color-text);' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    </div>
</form>

<script>
// Select/Deselect All Checkboxes logic
document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAllCheckbox');
    const items = document.querySelectorAll('.product-item-checkbox');
    
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            items.forEach(box => {
                box.checked = selectAll.checked;
            });
        });
    }
});

function confirmBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    if (!action) {
        alert('Please select a bulk action first.');
        return false;
    }
    
    const checked = document.querySelectorAll('.product-item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one product.');
        return false;
    }
    
    return confirm(`Apply bulk action "${action}" to ${checked.length} selected products?`);
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
