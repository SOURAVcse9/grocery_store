<?php
/**
 * ==========================================================================
 * admin/banners/index.php — Storefront Promotional Banners Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Banners Manager — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('banners.manage');

$pdo = db();

try {
    $banners = $pdo->query("SELECT * FROM banners ORDER BY priority ASC, created_at DESC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/banners] load failed: ' . $e->getMessage());
    $banners = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Banners & Carousels</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage desktop/mobile sliders, sidebar cards, category promo grids, and popup announcements.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Banner</a>
</div>

<!-- Alert notifications -->
<?php if (has_flash('banner_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('banner_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:150px;">Preview Image</th>
                    <th style="padding:16px 20px;">Banner Title</th>
                    <th style="padding:16px 20px; width:120px;">Display Type</th>
                    <th style="padding:16px 20px; width:100px; text-align:center;">Priority</th>
                    <th style="padding:16px 20px; width:220px;">Target Link URL</th>
                    <th style="padding:16px 20px; width:100px;">Status</th>
                    <th style="padding:16px 20px; width:130px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($banners)): ?>
                    <?php foreach ($banners as $b): 
                        $status = (bool) ($b['is_active'] ?? true);
                        $img = image_url($b['image_path'], 'banners');
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <div style="width:120px; height:60px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border); background:var(--color-bg);">
                                    <img src="<?= e($img) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($b['title']) ?></strong></td>
                            <td style="padding:12px 20px; text-transform:uppercase; font-size:10px; font-weight:700; color:var(--color-text-muted);"><?= e($b['type']) ?></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700; color:var(--color-text);"><?= (int) $b['priority'] ?></td>
                            <td style="padding:12px 20px; font-family:monospace; font-size:11px; color:var(--color-text-muted);"><?= e($b['link_url'] ?: 'No redirect link set.') ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                    <a href="delete.php?id=<?= $b['id'] ?>" onclick="return confirm('Permanently delete this banner card?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete banner"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="padding:32px; text-align:center; color:var(--color-text-faint);">No storefront banners configured. Click "Add Banner" to upload one.</td>
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
