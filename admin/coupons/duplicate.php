<?php
/**
 * ==========================================================================
 * admin/coupons/duplicate.php — Duplicate / Clone Existing Coupon Code
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
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $couponId]);
        $c = $stmt->fetch();

        if ($c) {
            $newCode = $c['code'] . '_COPY_' . rand(10, 99);
            
            $ins = $pdo->prepare("
                INSERT INTO coupons (
                    code, type, discount_percent, discount_amount, 
                    min_order_amount, max_discount_amount, usage_limit, times_used,
                    valid_from, valid_until, is_active, created_at
                ) VALUES (
                    :code, :type, :pct, :amt, 
                    :min, :max, :limit, 0,
                    :from, :until, :active, NOW()
                )
            ");
            $ins->execute([
                'code'   => $newCode,
                'type'   => $c['type'],
                'pct'    => $c['discount_percent'],
                'amt'    => $c['discount_amount'],
                'min'    => $c['min_order_amount'],
                'max'    => $c['max_discount_amount'],
                'limit'  => $c['usage_limit'],
                'from'   => $c['valid_from'],
                'until'  => $c['valid_until'],
                'active' => $c['is_active']
            ]);

            log_admin_activity('coupons.duplicate', "Duplicated coupon '{$c['code']}' to '{$newCode}'");
            flash('coupon_msg', "Coupon cloned successfully as '{$newCode}'!", 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/coupons/duplicate] clone failed: ' . $e->getMessage());
        flash('coupon_msg', 'Failed to clone coupon code.', 'error');
    }
}

header('Location: index.php');
exit;
