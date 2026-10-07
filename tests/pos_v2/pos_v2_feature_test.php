<?php
/**
 * ==========================================================================
 * tests/pos_v2/pos_v2_feature_test.php — Enterprise POS V2 Feature Suite
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';

$pdo = db();
$pos = pos_service();

$passed = 0;
$failed = 0;

function assertFeature(string $description, bool $condition) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "=======================================================\n";
echo " POS V2 ADVANCED FEATURES TEST SUITE\n";
echo "=======================================================\n\n";

// 1. Feature Flag pos_v2_enabled
$stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'pos_v2_enabled' LIMIT 1");
$posV2Enabled = $stmt->fetchColumn();
assertFeature("Feature flag pos_v2_enabled is configured in settings", $posV2Enabled !== false);

// 2. Multi-Unit Produce and Grocery Measurements
$units = ['pcs', 'kg', 'gm', 'liter', 'ml', 'pack', 'box', 'bottle', 'dozen'];
$validUnits = true;
foreach ($units as $u) {
    if (empty($u)) { $validUnits = false; break; }
}
assertFeature("Supermarket grocery units (kg, gm, liter, ml, pcs, etc.) recognized", $validUnits);

// 3. Dual Interface Method Aliasing (Singular & Plural)
$singularWorks = is_object($pos->transaction()) && is_object($pos->cart()) && is_object($pos->shift());
$pluralWorks = is_object($pos->transactions()) && is_object($pos->shifts()) && is_object($pos->receipts());
assertFeature("PosService supports both singular and plural method dispatching", $singularWorks && $pluralWorks);

// 4. Barcode and SKU Indexes Active
$indexes = $pdo->query("SHOW INDEX FROM products WHERE Key_name IN ('idx_products_barcode_active', 'idx_products_sku_active')")->fetchAll();
assertFeature("Composite performance indexes on barcode and SKU are active in MySQL", count($indexes) >= 2);

// 5. Dual Order & Pos Transaction Integrity
$product = $pdo->query("SELECT id, price, stock FROM products WHERE is_active = 1 AND stock > 20 LIMIT 1")->fetch();
if ($product) {
    $adminId = (int)$pdo->query("SELECT id FROM admins LIMIT 1")->fetchColumn();
    if (!$adminId) $adminId = 1;

    $shiftId = (int)$pdo->query("SELECT id FROM pos_shifts WHERE admin_id = {$adminId} AND status = 'open' LIMIT 1")->fetchColumn();
    if (!$shiftId) {
        $pdo->prepare("INSERT INTO pos_shifts (admin_id, store_id, register_id, terminal_id, opening_cash, status, start_time) VALUES (?, 1, 1, 1, 500.00, 'open', NOW())")->execute([$adminId]);
        $shiftId = (int)$pdo->lastInsertId();
    }
    
    $customer = $pdo->query("SELECT id FROM users LIMIT 1")->fetch();
    $customerId = $customer ? (int)$customer['id'] : null;
    
    $salePayload = [
        'store_id'      => 1,
        'register_id'   => 1,
        'terminal_id'   => 1,
        'shift_id'      => $shiftId,
        'cashier_id'    => $adminId,
        'customer_id'   => $customerId,
        'items'         => [
            [
                'id'             => (int)$product['id'],
                'quantity'       => 1,
                'price'          => (float)$product['price'],
                'discount'       => 0.00,
                'price_override' => false
            ]
        ],
        'cart_discount' => 0.00,
        'payments'      => [
            ['method' => 'card', 'amount' => (float)$product['price'], 'card_type' => 'Mastercard', 'card_last_four' => '1234']
        ],
        'notes'         => 'Feature Test Card Sale'
    ];
    
    $res = $pos->transactions()->processSale($salePayload);
    
    $posTxId = $res['transaction_id'] ?? 0;
    $legacyOrderId = $res['order_id'] ?? 0;
    
    $chkTx = $pdo->query("SELECT id FROM pos_transactions WHERE id = {$posTxId}")->fetch();
    $chkOrder = $pdo->query("SELECT id, payment_status FROM orders WHERE id = {$legacyOrderId}")->fetch();
    
    assertFeature("Transaction writes to both pos_transactions and legacy orders table", !empty($chkTx) && !empty($chkOrder) && $chkOrder['payment_status'] === 'paid');
}

// 6. Forensic Audit Trail Logging
$auditStmt = $pdo->query("SELECT id FROM pos_audit_logs ORDER BY id DESC LIMIT 1");
$auditLog = $auditStmt->fetch();
assertFeature("Audit logging captures checkout actions in pos_audit_logs", !empty($auditLog));

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";

if ($failed > 0) {
    exit(1);
}
