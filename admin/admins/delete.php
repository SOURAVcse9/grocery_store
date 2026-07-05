<?php
/**
 * ==========================================================================
 * admin/admins/delete.php — Delete Administrative User Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('admins.manage');

$pdo = db();
$adminId = (int) input('id', '0', 'get');

if ($adminId > 0 && $adminId !== current_admin_id()) {
    try {
        $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $adminId]);
        $uname = $stmt->fetchColumn();

        if ($uname) {
            $del = $pdo->prepare("DELETE FROM admins WHERE id = :id");
            $del->execute(['id' => $adminId]);

            log_admin_activity('admins.delete', "Deleted administrative staff account: '@{$uname}'");
            flash('admin_msg', "Administrative account '@{$uname}' has been deleted.", 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/admins/delete] failed: ' . $e->getMessage());
        flash('admin_msg', 'Failed to delete administrative account.', 'error');
    }
}

header('Location: index.php');
exit;
