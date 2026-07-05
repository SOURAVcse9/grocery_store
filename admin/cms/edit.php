<?php
/**
 * ==========================================================================
 * admin/cms/edit.php — Edit Static CMS Page
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Edit Page — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('cms.manage');

$pdo = db();
$pageId = (int) input('id', '0', 'get');

if ($pageId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $pageId]);
    $page = $stmt->fetch();
    
    if (!$page) {
        flash('cms_msg', 'CMS page details not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/cms/edit] Load fail: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $title = trim(input('title', ''));
        $content = trim($_POST['content'] ?? '');
        $metaTitle = trim(input('meta_title', ''));
        $metaDesc = trim(input('meta_description', ''));
        $metaKeywords = trim(input('meta_keywords', ''));

        if (empty($title) || empty($content)) {
            $error = 'Page Title and Content are required fields.';
        } else {
            try {
                $up = $pdo->prepare("
                    UPDATE cms_pages 
                    SET title = :title, content = :content, 
                        meta_title = :m_title, meta_description = :m_desc, meta_keywords = :m_key
                    WHERE id = :id
                ");
                $up->execute([
                    'title'   => $title,
                    'content' => $content,
                    'm_title' => $metaTitle,
                    'm_desc'  => $metaDesc,
                    'm_key'   => $metaKeywords,
                    'id'      => $pageId
                ]);

                log_admin_activity('cms.edit', "Updated static page details: '{$page['page_key']}'");
                flash('cms_msg', "Page '{$title}' updated successfully!", 'success');
                
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/cms/edit] Save failed: ' . $e->getMessage());
                $error = 'Failed to update CMS page due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit CMS Page: <?= e($page['title']) ?></h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Update HTML content and metadata tags configuration.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> CMS Pages Grid</a>
</div>

<!-- Alert notifications -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<form method="post" class="auth-form" style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6); align-items:start;" class="admin-dashboard-layout">
    <?= csrf_field() ?>
    
    <!-- Main Left Form details -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        <!-- General Info card -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-6);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-4); border-bottom:1px solid var(--color-border); padding-bottom:8px;">Page HTML Content</h2>
            
            <div class="form-field-group">
                <label for="cmsTitle" style="font-weight:700;">Page Title *</label>
                <input type="text" id="cmsTitle" name="title" required value="<?= e($page['title']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label for="cmsContent" style="font-weight:700;">HTML Content *</label>
                <textarea id="cmsContent" name="content" rows="12" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"><?= e($page['content']) ?></textarea>
                <span class="field-help-text">You can use standard HTML markup tags like <code>&lt;p&gt;</code>, <code>&lt;strong&gt;</code>, etc.</span>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Form details -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        <!-- SEO Configuration -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">SEO Metadata</h2>
            
            <div class="form-field-group">
                <label for="seoTitle" style="font-weight:700;">Meta Title</label>
                <input type="text" id="seoTitle" name="meta_title" value="<?= e($page['meta_title'] ?? '') ?>" placeholder="Search results title tag" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label for="seoDesc" style="font-weight:700;">Meta Description</label>
                <textarea id="seoDesc" name="meta_description" rows="3" placeholder="Search result summary snippet" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"><?= e($page['meta_description'] ?? '') ?></textarea>
            </div>

            <div class="form-field-group">
                <label for="seoKey" style="font-weight:700;">Meta Keywords</label>
                <input type="text" id="seoKey" name="meta_keywords" value="<?= e($page['meta_keywords'] ?? '') ?>" placeholder="comma, separated, list" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
        <a href="index.php" class="btn btn-secondary" style="width:100%; padding:12px; border-radius:var(--radius-pill); font-weight:700; text-align:center; display:block; text-decoration:none; font-size:13px;">Cancel</a>
    </div>

</form>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
