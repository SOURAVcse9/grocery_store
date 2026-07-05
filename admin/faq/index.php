<?php
/**
 * ==========================================================================
 * admin/faq/index.php — FAQ Accordion Q&A Directory List Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'FAQ Accordions — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('faq.manage');

$pdo = db();

try {
    $faqs = $pdo->query("SELECT * FROM faqs ORDER BY category ASC, sort_order ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/faq] load failed: ' . $e->getMessage());
    $faqs = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">FAQ Management</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create, edit, sort, and enable accordion questions and answers for the storefront help desk.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add FAQ</a>
</div>

<!-- Notifications -->
<?php if (has_flash('faq_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('faq_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:150px;">Category</th>
                    <th style="padding:16px 20px;">Question</th>
                    <th style="padding:16px 20px; width:100px; text-align:center;">Sort Order</th>
                    <th style="padding:16px 20px; width:100px;">Status</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($faqs)): ?>
                    <?php foreach ($faqs as $f): 
                        $status = (bool) ($f['is_active'] ?? true);
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><span style="font-size:11px; text-transform:uppercase; background:#f1f3f5; color:#495057; font-weight:700; padding:2px 6px; border-radius:4px;"><?= e($f['category']) ?></span></td>
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($f['question']) ?></strong></td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700; color:var(--color-text);"><?= (int) $f['sort_order'] ?></td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                    <a href="delete.php?id=<?= $f['id'] ?>" onclick="return confirm('Permanently delete this FAQ?');" class="btn btn-secondary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;"><i class="fas fa-trash"></i> Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">No FAQ items found. Click "Add FAQ" to create one.</td>
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
