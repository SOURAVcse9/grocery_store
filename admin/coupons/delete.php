<?php
/**
 * ==========================================================================
 * admin/coupons/delete.php — Delete Coupon Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('coupons.manage');

$pdo = db();
$couponId = (int) input('id', '0', 'get');

if ($couponId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT code FROM coupons WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $couponId]);
        $code = $stmt->fetchColumn();

        if ($code) {
            $del = $pdo->prepare("DELETE FROM coupons WHERE id = :id");
            $del->execute(['id' => $couponId]);

            log_admin_activity('coupons.delete', "Deleted coupon code: '{$code}'");
            flash('coupon_msg', 'Coupon deleted successfully.', 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/coupons/delete] failed: ' . $e->getMessage());
        flash('coupon_msg', 'Failed to delete coupon.', 'error');
    }
}

header('Location: index.php');
exit;
