<?php
/**
 * ==========================================================================
 * tests/pos_v2/pos_v2_benchmark_test.php — High-Performance POS Benchmark Suite
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';

$pdo = db();
$pos = pos_service();
$cartService = new \Groco\Pos\PosCartService();

$passed = 0;
$failed = 0;

function assertBenchmark(string $description, callable $fn, float $maxTimeMs) {
    global $passed, $failed;
    $start = microtime(true);
    $result = $fn();
    $elapsedMs = (microtime(true) - $start) * 1000;
    
    if ($result === true && $elapsedMs <= $maxTimeMs) {
        $passed++;
        echo "  [PASS] {$description} (" . number_format($elapsedMs, 2) . "ms <= " . number_format($maxTimeMs, 2) . "ms)\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description} (" . number_format($elapsedMs, 2) . "ms > " . number_format($maxTimeMs, 2) . "ms or condition false)\n";
    }
}

echo "=======================================================\n";
echo " POS V2 HIGH-PERFORMANCE BENCHMARK SUITE\n";
echo "=======================================================\n\n";

// 1. Barcode Lookup Latency Benchmark (< 50ms)
assertBenchmark("Barcode lookup latency is ultra-fast (< 50ms)", function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT id, name, price, stock, barcode, sku, unit FROM products WHERE is_active = 1 AND barcode IS NOT NULL LIMIT 1");
    $stmt->execute();
    $prod = $stmt->fetch();
    if (!$prod) return true;
    
    $searchStmt = $pdo->prepare("SELECT id, name, price, stock, barcode, sku, unit FROM products WHERE barcode = ? AND is_active = 1");
    $searchStmt->execute([$prod['barcode']]);
    $res = $searchStmt->fetch();
    return !empty($res);
}, 50.0);

// 2. Catalog Query Latency (< 60ms)
assertBenchmark("Product catalog query latency (< 60ms)", function() use ($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, name, price, discount_price, stock, barcode, sku 
        FROM products 
        WHERE is_active = 1 AND deleted_at IS NULL 
        LIMIT 50
    ");
    $stmt->execute();
    $prods = $stmt->fetchAll();
    return count($prods) >= 0;
}, 60.0);

// 3. Cart Price & Produce Weight Calculations (< 10ms)
assertBenchmark("Cart calculation & weight stepper calculations (< 10ms)", function() use ($cartService) {
    $items = [
        ['id' => 1, 'price' => 120.00, 'quantity' => 2.50, 'unit' => 'kg', 'discount' => 5.00],
        ['id' => 2, 'price' => 45.00, 'quantity' => 1.00, 'unit' => 'pcs', 'discount' => 0.00],
        ['id' => 3, 'price' => 850.00, 'quantity' => 0.75, 'unit' => 'kg', 'discount' => 20.00]
    ];
    for ($i = 0; $i < 100; $i++) {
        $calc = $cartService->calculate($items, 15.00, 0.00);
    }
    return isset($calc['grand_total']) && $calc['grand_total'] > 0;
}, 10.0);

// 4. End-to-End Atomic Transaction Checkout Latency (< 100ms)
assertBenchmark("Atomic POS transaction end-to-end checkout (< 100ms)", function() use ($pos, $pdo) {
    $product = $pdo->query("SELECT id, price, stock FROM products WHERE is_active = 1 AND stock > 50 LIMIT 1")->fetch();
    if (!$product) return true;
    
    $shift = $pdo->query("SELECT id, admin_id FROM pos_shifts WHERE status = 'open' LIMIT 1")->fetch();
    $shiftId = $shift ? (int)$shift['id'] : 1;
    $adminId = $shift ? (int)$shift['admin_id'] : 1;
    
    $salePayload = [
        'store_id'      => 1,
        'register_id'   => 1,
        'terminal_id'   => 1,
        'shift_id'      => $shiftId,
        'cashier_id'    => $adminId,
        'customer_id'   => null,
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
            ['method' => 'cash', 'amount' => (float)$product['price']]
        ],
        'notes'         => 'Benchmark Sale'
    ];
    
    $res = $pos->transactions()->processSale($salePayload);
    return !empty($res['success']) && $res['success'] === true;
}, 100.0);

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";

if ($failed > 0) {
    exit(1);
}
