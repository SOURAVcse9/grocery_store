<?php
/**
 * ==============================================================================
 * tests/pos/pos_returns_and_refunds_test.php
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
echo " POS RETURNS & REFUNDS ENGINE TEST SUITE\n";
echo "=======================================================\n\n";

try {
    // 1. Prepare product & cashier
    $testProd = $pdo->query("SELECT id, stock, price FROM products WHERE deleted_at IS NULL AND is_active = 1 AND stock >= 10 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $prodId = (int)$testProd['id'];
    $unitPrice = (float)$testProd['price'];
    $initialStock = (int)$testProd['stock'];

    $adminId = (int)$pdo->query("SELECT id FROM admins LIMIT 1")->fetchColumn() ?: 1;
    $shiftId = (int)$pdo->query("SELECT id FROM pos_shifts WHERE admin_id = {$adminId} AND status = 'open' LIMIT 1")->fetchColumn();
    if (!$shiftId) {
        $pdo->prepare("INSERT INTO pos_shifts (admin_id, store_id, register_id, terminal_id, opening_cash, status, start_time) VALUES (?, 1, 1, 1, 500.00, 'open', NOW())")->execute([$adminId]);
        $shiftId = (int)$pdo->lastInsertId();
    }

    // 2. Perform a test sale of 3 units
    $salePayload = [
        'store_id'    => 1,
        'register_id' => 1,
        'terminal_id' => 1,
        'shift_id'    => $shiftId,
        'cashier_id'  => $adminId,
        'customer_id' => null,
        'items'       => [
            ['id' => $prodId, 'quantity' => 3, 'price' => $unitPrice]
        ],
        'payments'    => [
            ['method' => 'cash', 'amount' => $unitPrice * 3]
        ],
        'notes'       => 'Return Base Sale'
    ];

    $saleRes = $posService->transactions()->processSale($salePayload);
    $orderId = (int)$saleRes['order_id'];

    // Stock should have decreased by 3
    $stockAfterSale = (int)$pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetchColumn();
    assertTest("Sale decremented 3 units correctly", $stockAfterSale === ($initialStock - 3), $passed, $failed);

    // 3. Process Partial Return of 1 unit
    $returnItems = [
        $prodId => 1
    ];

    $returnRes = $posService->returns()->processReturn($orderId, $adminId, $returnItems, 'cash', 'Customer returned sealed item');
    assertTest("Return processed successfully", $returnRes['success'] === true, $passed, $failed);
    assertTest("Refund amount matches 1 unit price", abs((float)$returnRes['refund_amount'] - $unitPrice) < 0.001, $passed, $failed);

    // Verify Restock in products table
    $stockAfterReturn = (int)$pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetchColumn();
    assertTest("Restock added 1 unit back to inventory ({$stockAfterSale} + 1 == {$stockAfterReturn})", $stockAfterReturn === ($stockAfterSale + 1), $passed, $failed);

    // 4. Test Excessive Return Quantity Blocking
    $excessiveReturnItems = [
        $prodId => 5 // Attempting to return 5 when only 2 remain in order
    ];

    $threwReturnError = false;
    try {
        $posService->returns()->processReturn($orderId, $adminId, $excessiveReturnItems, 'cash', 'Excess return');
    } catch (Throwable $e) {
        $threwReturnError = true;
    }
    assertTest("Excessive return quantity blocked with validation exception", $threwReturnError === true, $passed, $failed);

    // Cleanup: restore inventory stock
    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$initialStock, $prodId]);

} catch (Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    $failed++;
}

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";
exit($failed > 0 ? 1 : 0);
