<?php
/**
 * ==============================================================================
 * tests/pos/pos_api_endpoints_test.php
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
echo " POS REST API v1 ENDPOINTS TEST SUITE\n";
echo "=======================================================\n\n";

try {
    // 1. Test Product Catalog Lookup
    $stmt = $pdo->prepare("
        SELECT id, name, sku, barcode, price, discount_price, stock, unit 
        FROM products 
        WHERE deleted_at IS NULL AND is_active = 1 AND stock > 0 
        LIMIT 5
    ");
    $stmt->execute();
    $catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    assertTest("Catalog lookup returns available products", count($catalog) > 0, $passed, $failed);
    $first = $catalog[0];
    assertTest("Product object contains essential POS fields (sku, barcode, price, stock)", isset($first['sku'], $first['barcode'], $first['price'], $first['stock']), $passed, $failed);

    // 2. Test Receipt Data Generation via Domain Service
    $order = $pdo->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        $orderId = (int)$order['id'];
        $receipt = $posService->receipts()->getReceiptData($orderId);
        assertTest("Receipt service returns valid store object", isset($receipt['store']['name']), $passed, $failed);
        assertTest("Receipt service formats grand_total and items list", isset($receipt['grand_total'], $receipt['items']), $passed, $failed);
        assertTest("Receipt contains return policy text", !empty($receipt['return_policy']), $passed, $failed);
    } else {
        echo "  [SKIP] No orders found to test receipt generation\n";
    }

    // 3. Test Customer Lookup & Loyalty Points
    $customer = $pdo->query("SELECT id, full_name, phone, wallet_balance, reward_points FROM users WHERE role_id != 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        $searchRes = $posService->customers()->search($customer['phone']);
        assertTest("Customer search resolves by exact phone number", count($searchRes) > 0 && $searchRes[0]['id'] === (int)$customer['id'], $passed, $failed);
    }

    // 4. Test Shift Lifecycle State
    $activeShift = $posService->shifts()->getCurrentShiftSummary(1);
    assertTest("Shift summary returns boolean has_active_shift flag", is_bool($activeShift['has_active_shift']), $passed, $failed);

} catch (Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    $failed++;
}

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";
exit($failed > 0 ? 1 : 0);
