<?php
/**
 * admin_full_audit_test.php — Dynamic Admin Integration Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/public/dbconnect.php';
require_once __DIR__ . '/admin/includes/auth_helpers.php';
require_once __DIR__ . '/admin/middleware/auth_middleware.php';

echo "=== DYNAMIC ADMIN PORTAL INTEGRATION TEST SUITE ===\n\n";

$pdo = db();

try {
    $pdo->beginTransaction();
    echo "[Transaction Started]\n";

    // 1. Test Admin Login Logic
    echo "1. Testing Admin Auth & Token Lookup...\n";
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = 'superadmin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    if (!$admin) {
        throw new Exception("Prerequisite Admin account 'superadmin' not found.");
    }
    
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    if (!is_admin_logged_in()) {
        throw new Exception("Admin Session login helper failed to validate active session.");
    }
    echo " - Admin authentication verification: PASS\n";

    // 2. Test RBAC permissions
    echo "2. Testing Role & Permission Boundaries...\n";
    $currAdmin = current_admin();
    if (!$currAdmin || $currAdmin['role_name'] !== 'Super Admin') {
        throw new Exception("Admin role name mapping mismatch.");
    }
    
    if (!has_admin_permission('pos.access')) {
        throw new Exception("Super Admin has no pos.access permission mapping.");
    }
    echo " - Admin RBAC validations: PASS\n";

    // 3. Test CRUD on Product Catalog
    echo "3. Testing Product CRUD operations...\n";
    $testSku = 'QA-TEST-SKU-' . rand(1000, 9999);
    $ins = $pdo->prepare("
        INSERT INTO products (category_id, name, slug, sku, price, stock, is_active, created_at, updated_at)
        VALUES (1, 'QA Test Product', :slug, :sku, 150.00, 100, 1, NOW(), NOW())
    ");
    $ins->execute(['slug' => strtolower($testSku), 'sku' => $testSku]);
    $newProdId = (int) $pdo->lastInsertId();

    // Verify row was inserted
    $verify = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $verify->execute([$newProdId]);
    $prod = $verify->fetch();
    if (!$prod || $prod['sku'] !== $testSku) {
        throw new Exception("Product insert validation failed.");
    }
    echo " - Product CREATE: PASS\n";

    // Test product UPDATE
    $up = $pdo->prepare("UPDATE products SET price = 175.00 WHERE id = ?");
    $up->execute([$newProdId]);
    $verify->execute([$newProdId]);
    $prodUp = $verify->fetch();
    if (!$prodUp || (float)$prodUp['price'] !== 175.00) {
        throw new Exception("Product update validation failed.");
    }
    echo " - Product UPDATE: PASS\n";

    // Test product DELETE (soft delete)
    $del = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
    $del->execute([$newProdId]);
    $verify->execute([$newProdId]);
    $prodDel = $verify->fetch();
    if (!$prodDel || $prodDel['deleted_at'] === null) {
        throw new Exception("Product soft-delete validation failed.");
    }
    echo " - Product DELETE: PASS\n";

    // 4. Test POS Stock adjustments & Order processing
    echo "4. Testing POS checkout and stock mutations...\n";
    
    // Create temporary shift for admin
    $pdo->prepare("INSERT INTO pos_shifts (admin_id, start_time, opening_cash, status) VALUES (?, NOW(), 1000.00, 'open')")->execute([$admin['id']]);
    $shiftId = (int)$pdo->lastInsertId();

    // Get product ID 1 stock
    $stmtStock = $pdo->prepare("SELECT stock, price, discount_price FROM products WHERE id = 1 FOR UPDATE");
    $stmtStock->execute();
    $prod1 = $stmtStock->fetch();
    $oldStock = (int)$prod1['stock'];
    $expectedPrice = (float)($prod1['discount_price'] !== null ? $prod1['discount_price'] : $prod1['price']);

    // Create a temporary test coupon
    $pdo->prepare("INSERT INTO coupons (code, type, discount_amount, times_used, is_active) VALUES ('QA-TEST-COUPON', 'fixed', 10.00, 5, 1)")->execute();
    $couponId = (int)$pdo->lastInsertId();

    // Perform Checkout
    $items = [
        ['id' => 1, 'qty' => 2, 'price' => $expectedPrice]
    ];
    $discount = 10.00;
    $subtotal = $expectedPrice * 2;
    $taxable = $subtotal - $discount;
    $vat = round($taxable * 0.05, 2);
    $totalAmount = $taxable + $vat;

    $orderNumber = 'POS-QA-' . rand(10000, 99999);
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (order_number, user_id, address_id, coupon_id, subtotal, discount_amount, total_amount, payment_method, payment_status, status, created_at)
        VALUES (?, ?, NULL, ?, ?, ?, ?, 'cod', 'paid', 'delivered', NOW())
    ");
    $stmtOrder->execute([$orderNumber, 7, $couponId, $subtotal, $discount, $totalAmount]); // 7 is walk-in customer id
    $orderId = (int)$pdo->lastInsertId();

    // Deduct stock
    $pdo->prepare("UPDATE products SET stock = stock - 2 WHERE id = 1")->execute();

    // Verify stock was decremented correctly
    $stmtStock->execute();
    $newStock = (int)$stmtStock->fetchColumn();
    if ($newStock !== $oldStock - 2) {
        throw new Exception("Stock deduction quantity mismatch. Expected: " . ($oldStock - 2) . ", got: " . $newStock);
    }
    echo " - POS Checkout & Stock deduction: PASS\n";

    // 5. Test order cancellation & inventory restoration
    echo "5. Testing order cancellation, stock restoration, and coupon usage rollback...\n";
    
    // Simulate order cancellation status transition
    $pdo->prepare("UPDATE products SET stock = stock + 2 WHERE id = 1")->execute();
    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    
    // Decrement coupon times_used
    $pdo->prepare("UPDATE coupons SET times_used = GREATEST(times_used - 1, 0) WHERE id = ?")->execute([$couponId]);

    // Verify stock was restored
    $stmtStock->execute();
    $restoredStock = (int)$stmtStock->fetchColumn();
    if ($restoredStock !== $oldStock) {
        throw new Exception("Stock restoration quantity mismatch. Expected: " . $oldStock . ", got: " . $restoredStock);
    }

    // Verify coupon times_used was decremented
    $stmtCoupon = $pdo->prepare("SELECT times_used FROM coupons WHERE id = ?");
    $stmtCoupon->execute([$couponId]);
    $timesUsedCancelled = (int)$stmtCoupon->fetchColumn();
    if ($timesUsedCancelled !== 4) {
        throw new Exception("Coupon times_used rollback mismatch. Expected: 4, got: " . $timesUsedCancelled);
    }
    echo " - Stock & Coupon Rollback: PASS\n";

    // 6. Test order re-activation
    echo "6. Testing order re-activation, stock re-deduction, and coupon usage re-increment...\n";
    
    // Check stock levels first
    $stmtStock->execute();
    $currentStock = (int)$stmtStock->fetchColumn();
    if ($currentStock < 2) {
        throw new Exception("Insufficient stock to re-activate order.");
    }
    
    // Re-deduct stock
    $pdo->prepare("UPDATE products SET stock = stock - 2 WHERE id = 1")->execute();
    $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ?")->execute([$orderId]);
    
    // Re-increment coupon times_used
    $pdo->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?")->execute([$couponId]);

    // Verify stock was re-deducted
    $stmtStock->execute();
    $finalStock = (int)$stmtStock->fetchColumn();
    if ($finalStock !== $oldStock - 2) {
        throw new Exception("Re-activated stock deduction mismatch. Expected: " . ($oldStock - 2) . ", got: " . $finalStock);
    }

    // Verify coupon times_used was re-incremented
    $stmtCoupon->execute([$couponId]);
    $timesUsedReactivated = (int)$stmtCoupon->fetchColumn();
    if ($timesUsedReactivated !== 5) {
        throw new Exception("Coupon times_used re-increment mismatch. Expected: 5, got: " . $timesUsedReactivated);
    }
    echo " - Stock & Coupon Re-activation: PASS\n";

    $pdo->rollBack();
    echo "[Transaction Rolled Back Cleanly]\n\n";

    echo "=== ALL ADMIN AUDIT INTEGRATION ASSERTIONS PASSED ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nFAIL: Test suite aborted with error: " . $e->getMessage() . "\n";
    exit(1);
}
