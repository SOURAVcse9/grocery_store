<?php
/**
 * ==========================================================================
 * admin/cms/index.php — Content Management System (CMS Pages) Listing
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'CMS Pages — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('cms.manage');

$pdo = db();

try {
    $pages = $pdo->query("SELECT * FROM cms_pages ORDER BY title ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/cms] load fail: ' . $e->getMessage());
    $pages = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Content Management (CMS)</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage store static pages, terms guidelines, career postings, and search engine titles.</p>
    </div>
</div>

<!-- Alert messages -->
<?php if (has_flash('cms_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('cms_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Page Key</th>
                    <th style="padding:16px 20px;">Page Title</th>
                    <th style="padding:16px 20px;">SEO Meta Title</th>
                    <th style="padding:16px 20px;">SEO Description</th>
                    <th style="padding:16px 20px; width:150px;">Last Updated</th>
                    <th style="padding:16px 20px; width:100px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pages)): ?>
                    <?php foreach ($pages as $p): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px; font-family:monospace; font-weight:700; color:var(--color-primary);"><?= e($p['page_key']) ?></td>
                            <td style="padding:12px 20px;"><strong style="color:var(--color-text);"><?= e($p['title']) ?></strong></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($p['meta_title'] ?? 'Default') ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint); max-width: 250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= e($p['meta_description'] ?? 'No meta description set.') ?>
                            </td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= date('M d, Y H:i', strtotime($p['updated_at'])) ?></td>
                            <td style="padding:12px 20px; text-align:right;">
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No CMS pages registered.</td>
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
