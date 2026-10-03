<?php
/**
 * ==============================================================================
 * tests/pos/pos_cart_and_pricing_test.php — Cart & Pricing Hierarchy Unit Test
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';

$pdo = db();
$cartService = new \Groco\Pos\PosCartService();
$discountService = new \Groco\Pos\PosDiscountService($pdo);

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
echo " POS CART & PRICING ENGINE TEST SUITE\n";
echo "=======================================================\n\n";

// Test 1: Standard integer quantity line totals
$items1 = [
    ['id' => 1, 'price' => 50.00, 'quantity' => 2, 'unit' => 'pcs'],
    ['id' => 2, 'price' => 120.00, 'quantity' => 1, 'unit' => 'pcs']
];
$res1 = $cartService->calculate($items1, 0.00, 0.00);
assertTest("Subtotal equals sum of line items (100 + 120 = 220.00)", abs($res1['subtotal'] - 220.00) < 0.001, $passed, $failed);
assertTest("Grand total equals subtotal with 0 discount", abs($res1['grand_total'] - 220.00) < 0.001, $passed, $failed);

// Test 2: Weighted decimal quantities (e.g. 2.75 kg rice @ 85.50/kg)
$items2 = [
    ['id' => 3, 'price' => 85.50, 'quantity' => 2.75, 'unit' => 'kg', 'is_weighted' => true]
];
$res2 = $cartService->calculate($items2, 0.00, 0.00);
$expectedLine = round(85.50 * 2.75, 2); // 235.13
assertTest("Weighted produce decimal calculation is exact (235.13)", abs($res2['subtotal'] - $expectedLine) < 0.001, $passed, $failed);

// Test 3: Line discount override & Cart-level discount combination
$items3 = [
    ['id' => 1, 'price' => 100.00, 'quantity' => 2, 'discount' => 10.00], // 100 * 2 - 10 = 190
    ['id' => 2, 'price' => 50.00, 'quantity' => 1, 'discount' => 0.00]    // 50
];
$res3 = $cartService->calculate($items3, 20.00, 0.00); // 240 - 20 = 220
assertTest("Combined line discount and cart-level discount deduction (220.00)", abs($res3['grand_total'] - 220.00) < 0.001, $passed, $failed);
assertTest("Total discount breakdown matches sum (10 + 20 = 30.00)", abs($res3['total_discount'] - 30.00) < 0.001, $passed, $failed);

// Test 4: Discount threshold validation
$allowedPassed = true;
try {
    $discountService->validateDiscounts(100.00, 10.00, false);
} catch (\Exception $e) {
    $allowedPassed = false;
}
assertTest("Discount within max threshold passes without exception", $allowedPassed === true, $passed, $failed);

$blockedPassed = false;
try {
    $discountService->validateDiscounts(100.00, 60.00, false);
} catch (\Exception $e) {
    $blockedPassed = true;
}
assertTest("Discount exceeding max threshold triggers exception", $blockedPassed === true, $passed, $failed);

// Test 5: Empty cart calculation
$resEmpty = $cartService->calculate([], 0.00, 0.00);
assertTest("Empty cart returns 0.00 totals cleanly", $resEmpty['grand_total'] === 0.00 && $resEmpty['item_count'] === 0, $passed, $failed);

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";
exit($failed > 0 ? 1 : 0);
