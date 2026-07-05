<?php
/**
 * ==========================================================================
 * admin/categories/index.php — Category Management Listing View
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Manage Categories — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('categories.manage');

$pdo = db();

// Process actions (such as delete)
if (method_is('post') && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf_or_fail();
    $catId = (int) input('id', '0');
    
    if ($catId > 0) {
        try {
            // Check if products are associated with this category
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :cid");
            $chk->execute(['cid' => $catId]);
            $prodCount = (int) $chk->fetchColumn();
            
            if ($prodCount > 0) {
                flash('cat_msg', 'Cannot delete this category. It contains ' . $prodCount . ' products. Move products to another category first.', 'error');
            } else {
                // Delete image from disk
                $imgQ = $pdo->prepare("SELECT image FROM categories WHERE id = :id LIMIT 1");
                $imgQ->execute(['id' => $catId]);
                $imgName = $imgQ->fetchColumn();
                
                if (!empty($imgName)) {
                    $imgPath = __DIR__ . '/../../public/uploads/categories/' . $imgName;
                    if (file_exists($imgPath)) {
                        @unlink($imgPath);
                    }
                }
                
                $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute(['id' => $catId]);
                log_admin_activity('categories.delete', "Deleted category ID #{$catId}");
                flash('cat_msg', 'Category deleted successfully!', 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/categories] Delete fail: ' . $e->getMessage());
            flash('cat_msg', 'Failed to delete category due to database error.', 'error');
        }
    }
    redirect(current_url());
}

try {
    // Fetch all categories with parent names and dynamic product counts
    $categories = $pdo->query("
        SELECT c.*, cp.name AS parent_name,
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
        FROM categories c
        LEFT JOIN categories cp ON cp.id = c.parent_id
        ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/categories] Index load fail: ' . $e->getMessage());
    $categories = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Categories</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage hierarchical product catalog categories, sub-categories, and images.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Category</a>
</div>

<!-- Alert messages -->
<?php display_flash_alerts('cat_msg'); ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:70px;">Image</th>
                    <th style="padding:16px 20px;">Category Name</th>
                    <th style="padding:16px 20px;">URL Slug</th>
                    <th style="padding:16px 20px;">Parent Category</th>
                    <th style="padding:16px 20px; width:100px;">Icon Class</th>
                    <th style="padding:16px 20px; width:120px; text-align:center;">Product Count</th>
                    <th style="padding:16px 20px; width:100px;">Status</th>
                    <th style="padding:16px 20px; width:120px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $c): 
                        $isSub = ($c['parent_id'] !== null);
                        $imgUrl = image_url($c['image'], 'categories');
                        $status = (bool) ($c['is_active'] ?? true);
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <div style="width:40px; height:40px; border-radius:4px; border:1px solid var(--color-border); overflow:hidden; background:var(--color-bg);">
                                    <img src="<?= e($imgUrl) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td style="padding:12px 20px;">
                                <?php if ($isSub): ?>
                                    <span style="color:var(--color-text-faint); margin-right:4px;">—</span>
                                    <strong><?= e($c['name']) ?></strong>
                                <?php else: ?>
                                    <strong style="color:var(--color-text); font-size:14px;"><?= e($c['name']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); font-family:monospace;"><?= e($c['slug']) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= e($c['parent_name'] ?? 'Root Category') ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);">
                                <?php if (!empty($c['icon'])): ?>
                                    <i class="<?= e($c['icon']) ?>" style="margin-right:6px; color:var(--color-primary);"></i> <span style="font-size:11px;"><?= e($c['icon']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--color-text-faint); font-size:11px;">None</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700; color:var(--color-text);"><?= (int) $c['product_count'] ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i></a>
                                    
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); border:none; background:#f03e3e; color:#fff;"><i class="fas fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="padding:32px; text-align:center; color:var(--color-text-faint);">No categories found in system.</td>
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
