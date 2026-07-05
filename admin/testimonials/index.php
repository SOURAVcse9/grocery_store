<?php
/**
 * ==========================================================================
 * admin/testimonials/index.php — Customer Testimonials Directory Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Testimonials — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('testimonials.manage');

$pdo = db();

try {
    $testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/testimonials] load failed: ' . $e->getMessage());
    $testimonials = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Testimonials Management</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create and manage client feedback and rating metrics displayed on the storefront about/homepage carousels.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Testimonial</a>
</div>

<!-- Alert messages -->
<?php if (has_flash('testi_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('testi_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Customer Name</th>
                    <th style="padding:16px 20px; width:150px;">Designation</th>
                    <th style="padding:16px 20px; width:120px; text-align:center;">Rating</th>
                    <th style="padding:16px 20px;">Comment / Review</th>
                    <th style="padding:16px 20px; width:100px;">Status</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($testimonials)): ?>
                    <?php foreach ($testimonials as $t): 
                        $status = (bool) ($t['is_active'] ?? true);
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($t['name']) ?></strong></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($t['designation'] ?? 'Buyer') ?></td>
                            <td style="padding:12px 20px; text-align:center; color:#fcc419; font-weight:700;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?= $i <= (int)$t['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); max-width: 300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($t['comment']) ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Visible' : 'Hidden' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                    <a href="delete.php?id=<?= $t['id'] ?>" onclick="return confirm('Permanently delete this testimonial?');" class="btn btn-secondary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;"><i class="fas fa-trash"></i> Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No testimonials registered. Click "Add Testimonial" to create one.</td>
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
