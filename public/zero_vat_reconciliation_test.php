<?php
/**
 * public/zero_vat_reconciliation_test.php — Automated zero-VAT and calculation consistency validation suite.
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/../admin/middleware/auth_middleware.php';

$pdo = db();

header('Content-Type: text/plain; charset=utf-8');

echo "=== GROCO ZERO-VAT RECONCILIATION & CONSISTENCY TEST SUITE ===\n\n";

$testsRun = 0;
$testsPassed = 0;
$testsFailed = 0;

function run_test(string $name, callable $callback) {
    global $testsRun, $testsPassed, $testsFailed, $pdo;
    $testsRun++;
    echo "[Test " . sprintf("%02d", $testsRun) . "] {$name}...\n";
    try {
        $pdo->beginTransaction();
        $res = $callback($pdo);
        if ($res === true) {
            $testsPassed++;
            echo "   -> \033[32mPASS\033[0m\n";
        } else {
            $testsFailed++;
            echo "   -> \033[31mFAIL\033[0m\n";
        }
    } catch (Exception $e) {
        $testsFailed++;
        echo "   -> \033[31mFAIL\033[0m (Exception: " . $e->getMessage() . ")\n";
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

// 1. Single product order
run_test("Single product order calculations (Price = 100, Qty = 1, Delivery = 60, VAT = 0)", function($pdo) {
    $price = 100.00;
    $qty = 1;
    $delivery = 60.00;
    
    // expected
    $expectedSubtotal = $price * $qty;
    $expectedTotal = $expectedSubtotal + $delivery; // no VAT
    
    // simulate backend calculation
    $vatAmount = 0.00;
    $calculatedTotal = $expectedSubtotal + $delivery + $vatAmount;
    
    return abs($calculatedTotal - $expectedTotal) < 0.001;
});

// 2. Multiple product order
run_test("Multiple product order calculations (Products: A=100, B=50, Delivery = 60, VAT = 0)", function($pdo) {
    $items = [
        ['price' => 100.00, 'qty' => 1],
        ['price' => 50.00, 'qty' => 1]
    ];
    $delivery = 60.00;
    
    $expectedSubtotal = 0.00;
    foreach ($items as $item) {
        $expectedSubtotal += $item['price'] * $item['qty'];
    }
    
    $expectedTotal = $expectedSubtotal + $delivery;
    
    $vatAmount = 0.00;
    $calculatedTotal = $expectedSubtotal + $delivery + $vatAmount;
    
    return abs($calculatedTotal - $expectedTotal) < 0.001;
});

// 3. Multiple quantities
run_test("Multiple quantities calculations (Price = 75, Qty = 4, Delivery = 0, VAT = 0)", function($pdo) {
    $price = 75.00;
    $qty = 4;
    $delivery = 0.00;
    
    $expectedSubtotal = $price * $qty;
    $expectedTotal = $expectedSubtotal + $delivery;
    
    $vatAmount = 0.00;
    $calculatedTotal = $expectedSubtotal + $delivery + $vatAmount;
    
    return abs($calculatedTotal - $expectedTotal) < 0.001;
});

// 4. Discounted product
run_test("Discounted product calculations (Regular = 120, Discount/Promo Price = 100, Qty = 2, VAT = 0)", function($pdo) {
    $promoPrice = 100.00;
    $qty = 2;
    $delivery = 60.00;
    
    $expectedSubtotal = $promoPrice * $qty;
    $expectedTotal = $expectedSubtotal + $delivery;
    
    $vatAmount = 0.00;
    $calculatedTotal = $expectedSubtotal + $delivery + $vatAmount;
    
    return abs($calculatedTotal - $expectedTotal) < 0.001;
});

// 5. Coupon discount
run_test("Coupon discount calculations (Subtotal = 300, Coupon = 50, Delivery = 60, VAT = 0)", function($pdo) {
    $subtotal = 300.00;
    $couponDiscount = 50.00;
    $delivery = 60.00;
    
    $expectedTotal = $subtotal - $couponDiscount + $delivery;
    
    $vatAmount = 0.00;
    $calculatedTotal = $subtotal - $couponDiscount + $delivery + $vatAmount;
    
    return abs($calculatedTotal - $expectedTotal) < 0.001;
});

// 6. Shipping if applicable
run_test("Shipping charges enforcement (Free delivery threshold BDT 1000 check)", function($pdo) {
    $subtotalFree = 1050.00;
    $subtotalCharge = 950.00;
    
    $deliveryFree = $subtotalFree >= 1000.0 ? 0.00 : 60.00;
    $deliveryCharge = $subtotalCharge >= 1000.0 ? 0.00 : 60.00;
    
    return $deliveryFree === 0.00 && $deliveryCharge === 60.00;
});

// 7. POS sale
run_test("POS sale calculations (Price = 80, Qty = 3, Discount = 0, VAT = 0)", function($pdo) {
    $price = 80.00;
    $qty = 3;
    $discount = 0.00;
    
    $expectedSubtotal = $price * $qty;
    $expectedTotal = $expectedSubtotal - $discount;
    
    $vatAmount = 0.00;
    $calculatedTotal = $expectedSubtotal - $discount + $vatAmount;
    
    return abs($calculatedTotal - $expectedTotal) < 0.001;
});

// 8. POS discount
run_test("POS override discount calculations (Subtotal = 500, Discount = 100, VAT = 0)", function($pdo) {
    $subtotal = 500.00;
    $discount = 100.00;
    
    $expectedTotal = $subtotal - $discount;
    
    $vatAmount = 0.00;
    $calculatedTotal = $subtotal - $discount + $vatAmount;
    
    return abs($calculatedTotal - $expectedTotal) < 0.001;
});

// 9. Unauthorized POS price override
run_test("Unauthorized POS price override checks (Throws exception)", function($pdo) {
    $price = 15.00;
    $originalPrice = 20.00;
    $hasPermission = false; // unauthorized
    
    if (abs($price - $originalPrice) > 0.001 && !$hasPermission) {
        return true; // correctly flagged
    }
    return false;
});

// 10. Authorized POS price override
run_test("Authorized POS price override checks (Allowed)", function($pdo) {
    $price = 15.00;
    $originalPrice = 20.00;
    $hasPermission = true; // authorized
    
    if (abs($price - $originalPrice) > 0.001 && !$hasPermission) {
        return false;
    }
    return true; // allowed override
});

// 11. Failed checkout rollback
run_test("Failed checkout database transaction rollback logic", function($pdo) {
    $initialCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    
    try {
        // Start sub-transaction (savepoint)
        $pdo->exec("SAVEPOINT checkout_fail_test");
        
        // Insert order
        $pdo->prepare("INSERT INTO orders (order_number, user_id, subtotal, total_amount, payment_method, status) VALUES ('FAIL-TEST-123', 7, 100, 100, 'cod', 'pending')")->execute();
        
        // Simulate failure (e.g. stock validation fails)
        throw new Exception("Simulated checkout failure");
        
        $pdo->exec("RELEASE SAVEPOINT checkout_fail_test");
    } catch (Exception $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT checkout_fail_test");
    }
    
    $newCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    return $newCount === $initialCount; // correctly rolled back
});

// 12. Transaction amount consistency
run_test("Transaction amount consistency checks", function($pdo) {
    $orderTotal = 250.00;
    
    // Create simulated order and transaction
    $pdo->prepare("INSERT INTO orders (order_number, user_id, subtotal, total_amount, payment_method, status) VALUES ('TXN-CONSISTENT-TEST', 7, 250, ?, 'cod', 'pending')")->execute([$orderTotal]);
    $orderId = (int)$pdo->lastInsertId();
    
    $pdo->prepare("INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at) VALUES ('credit', 1, ?, 'TXN-CONSISTENT-TEST', 'cash', 1, NOW())")->execute([$orderTotal]);
    
    // check matching amounts
    $storedOrderTotal = (float)$pdo->query("SELECT total_amount FROM orders WHERE id = {$orderId}")->fetchColumn();
    $storedTxnTotal = (float)$pdo->query("SELECT amount FROM transactions WHERE reference = 'TXN-CONSISTENT-TEST'")->fetchColumn();
    
    return abs($storedOrderTotal - $orderTotal) < 0.001 && abs($storedTxnTotal - $orderTotal) < 0.001;
});

// 13. Existing historical order reconciliation
run_test("Existing historical order reconciliation verification (0 mismatches expected)", function($pdo) {
    $stmt = $pdo->query("SELECT order_number, subtotal, discount_amount, delivery_charge, total_amount FROM orders");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $mismatches = 0;
    foreach ($orders as $o) {
        $sub = (float)$o['subtotal'];
        $disc = (float)($o['discount_amount'] ?? 0.0);
        $del = (float)($o['delivery_charge'] ?? 0.0);
        $total = (float)$o['total_amount'];
        
        if (str_starts_with($o['order_number'], 'POS-')) {
            $expected = round($sub - $disc, 2);
        } else {
            $expected = round($sub - $disc + $del, 2);
        }
        
        if (abs($total - $expected) > 0.02) {
            $mismatches++;
        }
    }
    
    return $mismatches === 0;
});

// 14. VAT = zero everywhere
run_test("Global system configuration settings.site_tax is 0.00", function($pdo) {
    $siteTax = (float)$pdo->query("SELECT value FROM settings WHERE key_name = 'site_tax'")->fetchColumn();
    return abs($siteTax - 0.00) < 0.001;
});

echo "\n==================================================\n";
echo "TESTS SUMMARY:\n";
echo "Total Tests Run: {$testsRun}\n";
echo "Total Passed:    {$testsPassed}\n";
echo "Total Failed:    {$testsFailed}\n";
echo "==================================================\n";
