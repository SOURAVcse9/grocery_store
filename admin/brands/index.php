<?php
/**
 * ==========================================================================
 * admin/brands/index.php — Brand Management Listing View
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Manage Brands — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('brands.manage');

$pdo = db();

// Process actions (such as delete)
if (method_is('post') && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf_or_fail();
    $brandId = (int) input('id', '0');
    
    if ($brandId > 0) {
        try {
            // Check if products are associated with this brand
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = :bid");
            $chk->execute(['bid' => $brandId]);
            $prodCount = (int) $chk->fetchColumn();
            
            if ($prodCount > 0) {
                flash('brand_msg', 'Cannot delete this brand. It contains ' . $prodCount . ' products. Move products to another brand first.', 'error');
            } else {
                // Delete logo from disk
                $logoQ = $pdo->prepare("SELECT logo FROM brands WHERE id = :id LIMIT 1");
                $logoQ->execute(['id' => $brandId]);
                $logoName = $logoQ->fetchColumn();
                
                if (!empty($logoName)) {
                    $logoPath = __DIR__ . '/../../public/uploads/brands/' . $logoName;
                    if (file_exists($logoPath)) {
                        @unlink($logoPath);
                    }
                }
                
                $pdo->prepare("DELETE FROM brands WHERE id = :id")->execute(['id' => $brandId]);
                log_admin_activity('brands.delete', "Deleted brand ID #{$brandId}");
                flash('brand_msg', 'Brand deleted successfully!', 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/brands] Delete fail: ' . $e->getMessage());
            flash('brand_msg', 'Failed to delete brand due to database error.', 'error');
        }
    }
    redirect(current_url());
}

try {
    // Fetch all brands with product counts
    $brands = $pdo->query("
        SELECT b.*, 
               (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.deleted_at IS NULL) AS product_count
        FROM brands b
        ORDER BY b.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/brands] Index load fail: ' . $e->getMessage());
    $brands = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Brands</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage store product brands, logo images, and descriptions.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Brand</a>
</div>

<!-- Alert messages -->
<?php if (has_flash('brand_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('brand_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:70px;">Logo</th>
                    <th style="padding:16px 20px;">Brand Name</th>
                    <th style="padding:16px 20px;">URL Slug</th>
                    <th style="padding:16px 20px;">Description</th>
                    <th style="padding:16px 20px; width:120px; text-align:center;">Product Count</th>
                    <th style="padding:16px 20px; width:100px;">Status</th>
                    <th style="padding:16px 20px; width:120px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($brands)): ?>
                    <?php foreach ($brands as $b): 
                        $logoUrl = !empty($b['logo']) ? asset('uploads/brands/' . $b['logo']) : asset('images/ui/placeholder.png');
                        $status = (bool) ($b['is_active'] ?? true);
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <div style="width:40px; height:40px; border-radius:4px; border:1px solid var(--color-border); overflow:hidden; background:var(--color-bg);">
                                    <img src="<?= e($logoUrl) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td style="padding:12px 20px;">
                                <strong style="color:var(--color-text); font-size:14px;"><?= e($b['name']) ?></strong>
                            </td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); font-family:monospace;"><?= e($b['slug']) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); max-width: 300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= e($b['description'] ?? 'No description provided.') ?>
                            </td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700; color:var(--color-text);"><?= (int) $b['product_count'] ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i></a>
                                    
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this brand?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); border:none; background:#f03e3e; color:#fff;"><i class="fas fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="padding:32px; text-align:center; color:var(--color-text-faint);">No brands registered in system.</td>
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
