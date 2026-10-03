<?php
/**
 * ==============================================================================
 * tests/pos/pos_offline_sync_and_idempotency_test.php
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
echo " POS OFFLINE SYNC & IDEMPOTENCY TEST SUITE\n";
echo "=======================================================\n\n";

try {
    // Fetch test product & cashier admin
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

    // Test 1: Client UUID Idempotency
    $testUuid = 'offline-uuid-' . bin2hex(random_bytes(8));

    $payload = [
        'store_id'    => 1,
        'register_id' => 1,
        'terminal_id' => 1,
        'shift_id'    => $shiftId,
        'cashier_id'  => $adminId,
        'client_uuid' => $testUuid,
        'items'       => [
            ['id' => $prodId, 'quantity' => 1, 'price' => $unitPrice]
        ],
        'payments'    => [
            ['method' => 'cash', 'amount' => $unitPrice]
        ],
        'notes'       => 'Idempotency Test #1'
    ];

    $firstRes = $posService->transactions()->processSale($payload);
    assertTest("Initial transaction with UUID created successfully", $firstRes['success'] === true, $passed, $failed);
    $firstTxId = $firstRes['transaction_id'];

    // Check stock level after 1st transaction
    $stockAfterFirst = (int)$pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetchColumn();

    // Re-submit exact same UUID (simulating network retry or re-sync)
    $secondRes = $posService->transactions()->processSale($payload);
    assertTest("Second submission with same UUID recognizes idempotent hit", !empty($secondRes['is_idempotent_hit']) && $secondRes['is_idempotent_hit'] === true, $passed, $failed);
    assertTest("Second submission returns exact same transaction ID", $secondRes['transaction_id'] === $firstTxId, $passed, $failed);

    // Verify stock was NOT decremented twice
    $stockAfterSecond = (int)$pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetchColumn();
    assertTest("Inventory stock preserved and not double-deducted ({$stockAfterFirst} == {$stockAfterSecond})", $stockAfterFirst === $stockAfterSecond, $passed, $failed);

    // Test 2: Batch Offline Synchronization Engine
    $offlineBatch = [
        [
            'client_uuid' => 'batch-offline-' . bin2hex(random_bytes(6)),
            'customer_id' => null,
            'items'       => [
                ['id' => $prodId, 'quantity' => 1, 'price' => $unitPrice]
            ],
            'payments'    => [
                ['method' => 'cash', 'amount' => $unitPrice]
            ],
            'notes'       => 'Batch Offline Queue Tx 1'
        ],
        [
            'client_uuid' => 'batch-offline-' . bin2hex(random_bytes(6)),
            'customer_id' => null,
            'items'       => [
                ['id' => $prodId, 'quantity' => 1, 'price' => $unitPrice]
            ],
            'payments'    => [
                ['method' => 'bkash', 'amount' => $unitPrice, 'transaction_reference' => 'BK-SYNC-123']
            ],
            'notes'       => 'Batch Offline Queue Tx 2'
        ]
    ];

    $syncRes = $posService->sync()->syncBatch($offlineBatch, 1, 1, 1, $adminId, $shiftId);
    assertTest("Batch offline sync returns success status", $syncRes['success'] === true, $passed, $failed);
    assertTest("Batch offline sync processed exactly 2 transactions", $syncRes['synced_count'] === 2, $passed, $failed);
    assertTest("Batch offline sync reported 0 failed transactions", $syncRes['failed_count'] === 0, $passed, $failed);

    // Cleanup: restore inventory stock
    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$initialStock, $prodId]);

} catch (Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    $failed++;
}

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";
exit($failed > 0 ? 1 : 0);
