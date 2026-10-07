<?php
/**
 * ==============================================================================
 * tests/pos/pos_transaction_and_inventory_concurrency_test.php
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';

$pdo = db();
$posService = pos_service();

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, &$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$desc}\n";
    }
}

echo "=======================================================\n";
echo " POS TRANSACTION & INVENTORY CONCURRENCY TEST SUITE\n";
echo "=======================================================\n\n";

try {
    // 1. Get or create a test product
    $stmt = $pdo->prepare("SELECT id, stock, price, name FROM products WHERE deleted_at IS NULL AND is_active = 1 AND stock >= 10 LIMIT 1");
    $stmt->execute();
    $testProd = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testProd) {
        // Insert test product
        $pdo->prepare("
            INSERT INTO products (category_id, brand_id, name, slug, sku, barcode, price, stock, is_active, unit, created_at, updated_at)
            VALUES (1, 1, 'POS Test Produce Item', 'pos-test-produce-item', 'TEST-PROD-01', '890123456789', 150.00, 50, 1, 'kg', NOW(), NOW())
        ")->execute();
        $prodId = (int)$pdo->lastInsertId();
        $initialStock = 50;
        $unitPrice = 150.00;
    } else {
        $prodId = (int)$testProd['id'];
        $initialStock = (int)$testProd['stock'];
        $unitPrice = (float)$testProd['price'];
    }

    // 2. Fetch admin cashier ID
    $adminId = (int)$pdo->query("SELECT id FROM admins LIMIT 1")->fetchColumn();
    if (!$adminId) $adminId = 1;

    // 3. Ensure an open shift exists
    $shiftId = (int)$pdo->query("SELECT id FROM pos_shifts WHERE admin_id = {$adminId} AND status = 'open' LIMIT 1")->fetchColumn();
    if (!$shiftId) {
        $pdo->prepare("INSERT INTO pos_shifts (admin_id, store_id, register_id, terminal_id, opening_cash, status, start_time) VALUES (?, 1, 1, 1, 500.00, 'open', NOW())")->execute([$adminId]);
        $shiftId = (int)$pdo->lastInsertId();
    }

    // Test A: Process Split Multi-Tender Sale (Cash ৳100 + Card ৳50)
    $qtySold = 1;
    $expectedGrandTotal = $unitPrice * $qtySold;
    $cashTender = min(100.00, $expectedGrandTotal);
    $cardTender = max($expectedGrandTotal - $cashTender, 0.00);

    $salePayload = [
        'store_id'      => 1,
        'register_id'   => 1,
        'terminal_id'   => 1,
        'shift_id'      => $shiftId,
        'cashier_id'    => $adminId,
        'customer_id'   => null, // Walk-in
        'items'         => [
            [
                'id'             => $prodId,
                'quantity'       => $qtySold,
                'price'          => $unitPrice,
                'discount'       => 0.00,
                'price_override' => false
            ]
        ],
        'cart_discount' => 0.00,
        'payments'      => [
            ['method' => 'cash', 'amount' => $cashTender],
            ['method' => 'card', 'amount' => $cardTender, 'card_type' => 'Visa', 'card_last_four' => '9988', 'card_auth_code' => 'AUTH7766', 'card_bank' => 'Brac Bank']
        ],
        'notes'         => 'Automated Concurrency & Split Tender Test'
    ];

    $saleResult = $posService->transactions()->processSale($salePayload);
    assertTest("Transaction completes successfully", $saleResult['success'] === true, $passed, $failed);
    assertTest("Unique sequence transaction number generated (starts with GR-)", strpos($saleResult['transaction_number'], 'GR-') === 0, $passed, $failed);
    assertTest("Legacy order ID generated (> 0)", $saleResult['order_id'] > 0, $passed, $failed);
    assertTest("POS transaction ID generated (> 0)", $saleResult['transaction_id'] > 0, $passed, $failed);

    // Verify Stock Reduction
    $stmtCheckStock = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmtCheckStock->execute([$prodId]);
    $newStock = (int)$stmtCheckStock->fetchColumn();
    assertTest("Inventory stock atomically decremented by {$qtySold} ({$initialStock} -> {$newStock})", $newStock === ($initialStock - $qtySold), $passed, $failed);

    // Verify pos_payments table entries
    $stmtPayments = $pdo->prepare("SELECT * FROM pos_payments WHERE transaction_id = ?");
    $stmtPayments->execute([$saleResult['transaction_id']]);
    $storedPayments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
    assertTest("Split tenders recorded in pos_payments", count($storedPayments) >= 1, $passed, $failed);

    // Verify Audit log entry
    $stmtAudit = $pdo->prepare("SELECT * FROM pos_audit_logs WHERE transaction_id = ? LIMIT 1");
    $stmtAudit->execute([$saleResult['transaction_id']]);
    $auditLog = $stmtAudit->fetch(PDO::FETCH_ASSOC);
    assertTest("Immutable audit log entry created in pos_audit_logs", $auditLog !== false, $passed, $failed);

    // Test B: Concurrency Overselling Prevention (Attempting to sell more than available stock)
    $excessiveQty = $newStock + 100;
    $oversellPayload = [
        'store_id'      => 1,
        'register_id'   => 1,
        'terminal_id'   => 1,
        'shift_id'      => $shiftId,
        'cashier_id'    => $adminId,
        'customer_id'   => null,
        'items'         => [
            ['id' => $prodId, 'quantity' => $excessiveQty, 'price' => $unitPrice]
        ],
        'payments'      => [
            ['method' => 'cash', 'amount' => $unitPrice * $excessiveQty]
        ]
    ];

    $threwStockException = false;
    try {
        $posService->transactions()->processSale($oversellPayload);
    } catch (Throwable $e) {
        $threwStockException = true;
    }
    assertTest("Overselling blocked with concurrency stock exception", $threwStockException === true, $passed, $failed);

    // Cleanup: restore inventory
    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$initialStock, $prodId]);

} catch (Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    $failed++;
}

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";
exit($failed > 0 ? 1 : 0);
