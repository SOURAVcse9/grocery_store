<?php
/**
 * ==========================================================================
 * admin/cache/index.php — Cache & Temporary Files Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Cache Management — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('cache.manage');

$pdo = db();
$success = null;
$error = null;

$tempDir = __DIR__ . '/../../storage/scratch';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

// Calculate scratch directory details
$tempFilesCount = 0;
$tempFilesSize = 0;

if (is_dir($tempDir)) {
    $files = scandir($tempDir);
    if ($files) {
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $tempFilesCount++;
                $tempFilesSize += filesize($tempDir . '/' . $file);
            }
        }
    }
}

// Handle cache purges
if (method_is('post')) {
    verify_csrf_or_fail();
    $action = input('cache_action', '');

    if ($action === 'clear_temp') {
        if (is_dir($tempDir)) {
            $files = scandir($tempDir);
            if ($files) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        @unlink($tempDir . '/' . $file);
                    }
                }
            }
        }
        log_admin_activity('cache.clear', 'Cleared temporary application cache and scratch files.');
        $success = 'Application cache cleared successfully!';
        $tempFilesCount = 0;
        $tempFilesSize = 0;
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Cache Management</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Optimize systems speeds by cleaning out accumulated database queries cache or temporary files.</p>
    </div>
</div>

<!-- Notifications -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-5); max-width: 600px;">
    <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Cache & Storage Utilization</h3>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:13px;">
        <div>
            <span style="color:var(--color-text-muted); display:block; font-size:11px; text-transform:uppercase; font-weight:700;">Temporary Files Count</span>
            <strong style="font-size:18px; color:var(--color-text);"><?= $tempFilesCount ?> files</strong>
        </div>
        <div>
            <span style="color:var(--color-text-muted); display:block; font-size:11px; text-transform:uppercase; font-weight:700;">Accumulated Storage Space</span>
            <strong style="font-size:18px; color:var(--color-text);"><?= round($tempFilesSize / 1024, 2) ?> KB</strong>
        </div>
    </div>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="cache_action" value="clear_temp">
        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px;"><i class="fas fa-broom"></i> Clear Application Cache</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
