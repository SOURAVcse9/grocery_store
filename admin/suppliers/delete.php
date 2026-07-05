<?php
/**
 * ==========================================================================
 * admin/suppliers/delete.php — Delete Supplier Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('purchases.manage');

$pdo = db();
$supplierId = (int) input('id', '0', 'get');

if ($supplierId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $supplierId]);
        $name = $stmt->fetchColumn();

        if ($name) {
            $del = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
            $del->execute(['id' => $supplierId]);

            log_admin_activity('suppliers.delete', "Deleted supplier vendor: '{$name}'");
            flash('supplier_msg', "Supplier vendor '{$name}' deleted successfully.", 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/suppliers/delete] failed: ' . $e->getMessage());
        flash('supplier_msg', 'Failed to delete supplier vendor due to dependencies.', 'error');
    }
}

header('Location: index.php');
exit;
