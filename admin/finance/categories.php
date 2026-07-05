<?php
/**
 * ==========================================================================
 * admin/finance/categories.php — Expense Categories Configuration
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Expense Categories — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();
$error = null;
$success = null;

// Handle Add Category
if (method_is('post') && input('cat_action', '') === 'add') {
    verify_csrf_or_fail();
    $name = trim(input('name', ''));

    if (empty($name)) {
        $error = 'Category Name is a required field.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO expense_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            log_admin_activity('finance.category_add', "Created expense category: '{$name}'");
            $success = "Expense category '{$name}' created successfully.";
        } catch (PDOException $e) {
            $error = 'Expense category name must be unique.';
        }
    }
}

// Handle Delete Category
if (method_is('post') && input('cat_action', '') === 'delete') {
    verify_csrf_or_fail();
    $catId = (int) input('id', '0');
    if ($catId > 0) {
        try {
            $pdo->prepare("DELETE FROM expense_categories WHERE id = ?")->execute([$catId]);
            log_admin_activity('finance.category_delete', "Deleted expense category ID: {$catId}");
            $success = 'Expense category deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to delete category due to active dependency references.';
        }
    }
}

// Fetch all categories
try {
    $categories = $pdo->query("
        SELECT ec.*, 
               (SELECT COUNT(*) FROM transactions t WHERE t.category_id = ec.id) AS total_tx
        FROM expense_categories ec
        ORDER BY ec.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/finance/categories] load fail: ' . $e->getMessage());
    $categories = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Expense Categories</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage labels used for general ledger expense classification audits.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Finance Hub</a>
</div>

<!-- Alerts -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 2fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left: Add category form -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Create Category</h3>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="cat_action" value="add">

            <div class="form-field-group">
                <label style="font-weight:700;">Category Name *</label>
                <input type="text" name="name" required placeholder="E.g. Logistics & Banners" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-plus"></i> Create Category</button>
        </form>
    </div>

    <!-- Right: Categories directory -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Registered Categories</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Category Name</th>
                        <th style="padding:10px 15px; text-align:center; width:150px;">Associated Entries</th>
                        <th style="padding:10px 15px; text-align:right; width:100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px;"><strong><?= e($row['name']) ?></strong></td>
                                <td style="padding:8px 15px; text-align:center; font-weight:700; color:var(--color-primary);"><?= $row['total_tx'] ?> transactions</td>
                                <td style="padding:8px 15px; text-align:right;">
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Permanently delete this category?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="cat_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding:4px 8px; font-size:9px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; border:none; cursor:pointer;"><i class="fas fa-trash-can"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding:20px; text-align:center; color:var(--color-text-faint);">No expense categories found.</td>
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
