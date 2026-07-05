<?php
/**
 * ==========================================================================
 * admin/flash-sales/delete.php — Delete Flash Sale Campaign Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('flashsales.manage');

$pdo = db();
$fsId = (int) input('id', '0', 'get');

if ($fsId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT product_id FROM flash_sales WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $fsId]);
        $productId = $stmt->fetchColumn();

        if ($productId) {
            $del = $pdo->prepare("DELETE FROM flash_sales WHERE id = :id");
            $del->execute(['id' => $fsId]);

            log_admin_activity('flashsales.delete', "Deleted flash sale campaign for Product ID: {$productId}");
            flash('flash_sale_msg', 'Flash sale campaign deleted successfully.', 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/flash-sales/delete] failed: ' . $e->getMessage());
        flash('flash_sale_msg', 'Failed to delete campaign record.', 'error');
    }
}

header('Location: index.php');
exit;
