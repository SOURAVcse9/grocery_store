<?php
/**
 * ==========================================================================
 * admin/files/index.php — Uploaded Media File Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'File Manager — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('settings.manage'); // Reuse settings.manage

$pdo = db();
$success = null;

$productUploadDir = __DIR__ . '/../../public/uploads/products';
$bannerUploadDir = __DIR__ . '/../../public/uploads/banners';

// Scan upload directories and calculate statistics
function scanFolderStats(string $dir): array {
    $count = 0;
    $size = 0;
    $filesList = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        if ($files) {
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $count++;
                    $size += filesize($dir . '/' . $file);
                    $filesList[] = $file;
                }
            }
        }
    }
    return ['count' => $count, 'size' => $size, 'files' => $filesList];
}

$prodStats = scanFolderStats($productUploadDir);
$bannerStats = scanFolderStats($bannerUploadDir);

// Identify unused images by querying active database assets
$usedImages = [];

try {
    $prodImgs = $pdo->query("SELECT image FROM products WHERE image IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $prodExtraImgs = $pdo->query("SELECT image_path FROM product_images")->fetchAll(PDO::FETCH_COLUMN);
    $bannerImgs = $pdo->query("SELECT image_path FROM banners")->fetchAll(PDO::FETCH_COLUMN);

    $usedImages = array_unique(array_merge($prodImgs, $prodExtraImgs, $bannerImgs));
} catch (PDOException $e) {
    error_log('[admin/files/index] query used files failed: ' . $e->getMessage());
}

$unusedProdFiles = [];
foreach ($prodStats['files'] as $f) {
    if (!in_array($f, $usedImages, true)) {
        $unusedProdFiles[] = $f;
    }
}

// Handle purge of unused files
if (method_is('post') && input('file_action', '') === 'purge_unused') {
    verify_csrf_or_fail();
    $purged = 0;
    foreach ($unusedProdFiles as $f) {
        $path = $productUploadDir . '/' . $f;
        if (file_exists($path) && is_file($path)) {
            @unlink($path);
            $purged++;
        }
    }
    log_admin_activity('files.purge', "Purged {$purged} unused product images from directory uploads.");
    $success = "Successfully deleted {$purged} unused media files!";
    
    // Refresh stats
    $prodStats = scanFolderStats($productUploadDir);
    $unusedProdFiles = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Uploaded Media Files</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect catalog image directory allocations, view files volumes, and delete orphaned attachments.</p>
    </div>
    
    <?php if (!empty($unusedProdFiles)): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="file_action" value="purge_unused">
            <button type="submit" class="btn btn-primary" style="background:#f03e3e; border:none; border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-trash-can"></i> Purge Unused Images</button>
        </form>
    <?php endif; ?>
</div>

<!-- Notifications -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Product Images stats -->
    <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;"><i class="fas fa-box"></i> Product Upload Directory</h3>
        <p>Total files: <strong><?= $prodStats['count'] ?></strong></p>
        <p>Allocated Size: <strong><?= round($prodStats['size'] / 1024 / 1024, 2) ?> MB</strong></p>
        <p>Unused / Orphaned: <strong><?= count($unusedProdFiles) ?> files</strong></p>
    </div>

    <!-- Banner Images stats -->
    <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;"><i class="fas fa-image"></i> Sliders & Banners Directory</h3>
        <p>Total files: <strong><?= $bannerStats['count'] ?></strong></p>
        <p>Allocated Size: <strong><?= round($bannerStats['size'] / 1024 / 1024, 2) ?> MB</strong></p>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
