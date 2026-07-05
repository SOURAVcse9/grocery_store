<?php
/**
 * ==========================================================================
 * admin/health/index.php — Backoffice System Diagnostics Panel
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'System Health — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('settings.manage');

$pdo = db();

try {
    $mysqlVer = $pdo->query("SELECT VERSION()")->fetchColumn();
} catch (PDOException $e) {
    $mysqlVer = 'Unknown / Error';
}

// Disk diagnostic calculations
$totalDisk = disk_total_space(__DIR__) ?: 1;
$freeDisk = disk_free_space(__DIR__) ?: 1;
$usedDisk = $totalDisk - $freeDisk;
$diskUsagePct = ($usedDisk / $totalDisk) * 100;

function formatBytes(float $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">System Diagnostics</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect backoffice PHP runtimes, database health, disk utilization limits, and storage metrics.</p>
    </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left: Diagnostics tables -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- Environment metrics -->
        <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Software & Environment Details</h3>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="grid-2">
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">PHP Version</span>
                    <strong><?= PHP_VERSION ?></strong>
                </div>
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">MySQL Database Version</span>
                    <strong><?= e($mysqlVer) ?></strong>
                </div>
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Server Software</span>
                    <strong><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></strong>
                </div>
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Server Local Time</span>
                    <strong><?= date('Y-m-d H:i:s T') ?></strong>
                </div>
            </div>
        </div>

        <!-- Configurations limits -->
        <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Runtime Configuration Constraints</h3>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="grid-2">
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Max File Upload Limit</span>
                    <strong><?= ini_get('upload_max_filesize') ?></strong>
                </div>
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Max Post Request Size</span>
                    <strong><?= ini_get('post_max_size') ?></strong>
                </div>
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Runtime Memory Allocation</span>
                    <strong><?= ini_get('memory_limit') ?></strong>
                </div>
                <div>
                    <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Maximum Script Execution Time</span>
                    <strong><?= ini_get('max_execution_time') ?>s</strong>
                </div>
            </div>
        </div>

    </div>

    <!-- Right: Disk space & active usages -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- Disk card usage -->
        <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Disk Storage Allocation</h3>
            
            <div style="text-align:center; margin-bottom:16px;">
                <div style="font-size:24px; font-weight:800; color:var(--color-primary);"><?= round($diskUsagePct, 1) ?>%</div>
                <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; font-weight:700;">Disk Storage Utilized</span>
            </div>
            
            <div style="font-size:12px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:8px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>Total Space:</span>
                    <strong><?= formatBytes($totalDisk) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Free Space:</span>
                    <strong><?= formatBytes($freeDisk) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Used Space:</span>
                    <strong><?= formatBytes($usedDisk) ?></strong>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
