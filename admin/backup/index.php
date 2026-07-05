<?php
/**
 * ==========================================================================
 * admin/backup/index.php — Database Backup & Recovery Panel
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Database Backups — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('backup.manage');

$pdo = db();
$error = null;
$success = null;

$backupDir = __DIR__ . '/../../storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

// Handle Backup Operations
if (method_is('post')) {
    verify_csrf_or_fail();
    $action = input('backup_action', '');

    if ($action === 'create_backup') {
        try {
            // Self-contained PHP MySQL table schema & data dump logic (extremely robust)
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $sqlDump = "-- GroCo Database SQL Backup Dump\n";
            $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $tbl) {
                // Table schema
                $createTbl = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch();
                $sqlDump .= "DROP TABLE IF EXISTS `{$tbl}`;\n";
                $sqlDump .= $createTbl['Create Table'] . ";\n\n";

                // Table data rows
                $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $escapedValues = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $escapedValues[] = 'NULL';
                        } else {
                            $escapedValues[] = $pdo->quote((string)$val);
                        }
                    }
                    $sqlDump .= "INSERT INTO `{$tbl}` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
                }
                $sqlDump .= "\n";
            }
            $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

            $filename = 'backup_' . date('Ymd_His') . '_' . uniqid('', false) . '.sql';
            $filepath = $backupDir . '/' . $filename;
            
            if (file_put_contents($filepath, $sqlDump) !== false) {
                $fileSize = filesize($filepath);
                
                // Save to database log history
                $stmt = $pdo->prepare("INSERT INTO system_backups (filename, filepath, file_size) VALUES (?, ?, ?)");
                $stmt->execute([$filename, $filename, $fileSize]);

                log_admin_activity('backups.create', "Generated database backup file: '{$filename}'");
                $success = 'Database backup generated successfully!';
            } else {
                $error = 'Failed to write backup dump file to disk storage.';
            }

        } catch (Exception $e) {
            error_log('[admin/backup] Generate failed: ' . $e->getMessage());
            $error = 'Backup generation failed: ' . $e->getMessage();
        }
    } elseif ($action === 'restore_backup') {
        $filename = input('filename', '');
        $filepath = $backupDir . '/' . $filename;

        if (empty($filename) || !file_exists($filepath)) {
            $error = 'Backup source file not found.';
        } else {
            try {
                $sql = file_get_contents($filepath);
                if ($sql !== false) {
                    $pdo->exec($sql);
                    log_admin_activity('backups.restore', "Restored database from backup file: '{$filename}'");
                    $success = 'Database restored successfully from backup file!';
                } else {
                    $error = 'Failed to read backup file contents.';
                }
            } catch (PDOException $e) {
                error_log('[admin/backup] Restore failed: ' . $e->getMessage());
                $error = 'Database restore execution failed: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_backup') {
        $filename = input('filename', '');
        $filepath = $backupDir . '/' . $filename;
        
        if (empty($filename) || !file_exists($filepath)) {
            $error = 'Backup file not found on disk.';
        } else {
            unlink($filepath);
            $pdo->prepare("DELETE FROM system_backups WHERE filename = ?")->execute([$filename]);
            log_admin_activity('backups.delete', "Deleted backup file: '{$filename}'");
            $success = 'Backup file deleted successfully.';
        }
    }
}

// Fetch backup history logs
try {
    $backups = $pdo->query("SELECT * FROM system_backups ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/backup] history load failed: ' . $e->getMessage());
    $backups = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Database Backups & Recovery</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create manual backup restoration snapshots, download raw SQL scripts, or recover store data states.</p>
    </div>
    
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="backup_action" value="create_backup">
        <button type="submit" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-database"></i> Generate Backup</button>
    </form>
</div>

<!-- Notifications -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<!-- Backups table -->
<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Backup Filename</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">File Size</th>
                    <th style="padding:16px 20px; width:220px;">Created Date</th>
                    <th style="padding:16px 20px; width:250px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($backups)): ?>
                    <?php foreach ($backups as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong><?= e($row['filename']) ?></strong></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);"><?= round((float)$row['file_size'] / 1024, 2) ?> KB</td>
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Restore database to this state? Current tables will be overwritten.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="backup_action" value="restore_backup">
                                        <input type="hidden" name="filename" value="<?= e($row['filename']) ?>">
                                        <button type="submit" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm);"><i class="fas fa-rotate-left"></i> Restore</button>
                                    </form>
                                    
                                    <form method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="backup_action" value="delete_backup">
                                        <input type="hidden" name="filename" value="<?= e($row['filename']) ?>">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Permanently delete backup snapshot?');" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; border:none; cursor:pointer;"><i class="fas fa-trash-can"></i> Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding:32px; text-align:center; color:var(--color-text-faint);">No database backup snapshots created yet.</td>
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
