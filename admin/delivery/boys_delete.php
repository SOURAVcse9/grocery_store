<?php
/**
 * ==========================================================================
 * admin/delivery/boys_delete.php — Delete Delivery Boy Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('delivery.manage');

$pdo = db();
$boyId = (int) input('id', '0', 'get');

if ($boyId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM delivery_boys WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $boyId]);
        $name = $stmt->fetchColumn();

        if ($name) {
            $del = $pdo->prepare("DELETE FROM delivery_boys WHERE id = :id");
            $del->execute(['id' => $boyId]);

            log_admin_activity('delivery.boy_delete', "Deleted delivery personnel: '{$name}'");
            flash('boy_msg', "Delivery agent '{$name}' deleted successfully.", 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/delivery/boys_delete] failed: ' . $e->getMessage());
        flash('boy_msg', 'Failed to delete delivery agent due to active order assignments.', 'error');
    }
}

header('Location: boys.php');
exit;
