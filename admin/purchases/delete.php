<?php
/**
 * ==========================================================================
 * admin/purchases/delete.php — Cancel / Delete Purchase Order Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('purchases.manage');

$pdo = db();
$poId = (int) input('id', '0', 'get');
$action = input('action', 'cancel', 'get');

if ($poId > 0) {
    try {
        $stmtPO = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = :id LIMIT 1");
        $stmtPO->execute(['id' => $poId]);
        $po = $stmtPO->fetch();

        if ($po) {
            if ($action === 'cancel' && $po['status'] === 'pending') {
                $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?")->execute([$poId]);
                log_admin_activity('purchases.cancel', "Cancelled procurement purchase order: '{$po['order_number']}'");
                flash('purchase_msg', "Purchase Order '{$po['order_number']}' cancelled successfully.", 'success');
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?")->execute([$poId]);
                log_admin_activity('purchases.delete', "Deleted purchase order record: '{$po['order_number']}'");
                flash('purchase_msg', "Purchase Order '{$po['order_number']}' deleted successfully.", 'success');
            }
        }
    } catch (PDOException $e) {
        error_log('[admin/purchases/delete] Action failed: ' . $e->getMessage());
        flash('purchase_msg', 'Failed to update or delete purchase order.', 'error');
    }
}

header('Location: index.php');
exit;
