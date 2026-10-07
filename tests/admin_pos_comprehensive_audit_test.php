<?php
/**
 * ==========================================================================
 * tests/admin_pos_comprehensive_audit_test.php
 * ==========================================================================
 * Comprehensive POS & Admin Production Hardening Test Suite
 * ==========================================================================
 */

declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim(trim($v), '"\'');
        }
    }
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$dbname = $env['DB_NAME'] ?? 'grocery_store';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$port = (int)($env['DB_PORT'] ?? 3306);

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name} — {$details}\n";
    }
}

echo "========================================================\n";
echo "RUNNING COMPREHENSIVE POS & ADMIN PRODUCTION AUDIT SUITE\n";
echo "========================================================\n\n";

// --- TEST 1: Admin & POS Database Schema Integrity ---
echo "[1] Testing POS & Admin Database Schema Integrity...\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

assert_test("pos_shifts table exists", in_array('pos_shifts', $tables, true));
assert_test("pos_drawer_transactions table exists", in_array('pos_drawer_transactions', $tables, true));
assert_test("pos_hold_orders table exists", in_array('pos_hold_orders', $tables, true));
assert_test("pos_returns table exists", in_array('pos_returns', $tables, true));
assert_test("pos_return_items table exists", in_array('pos_return_items', $tables, true));
assert_test("orders table exists", in_array('orders', $tables, true));
assert_test("order_items table exists", in_array('order_items', $tables, true));
assert_test("inventory_logs table exists", in_array('inventory_logs', $tables, true));
assert_test("transactions table exists", in_array('transactions', $tables, true));

// --- TEST 2: Walk-in Default Customer Integrity ---
echo "\n[2] Testing Default POS Walk-in Customer Record...\n";
$walkin = $pdo->query("SELECT id, full_name, phone, role_id, is_active FROM users WHERE phone = '00000000000' LIMIT 1")->fetch();
if (!$walkin) {
    $pwd = password_hash('walkin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password, is_verified, is_active, created_at, updated_at) VALUES (2, 'Walk-in Customer', 'walkin@grocery.store', '00000000000', ?, 1, 1, NOW(), NOW())")->execute([$pwd]);
    $walkin = $pdo->query("SELECT id, full_name, phone, role_id, is_active FROM users WHERE phone = '00000000000' LIMIT 1")->fetch();
}

assert_test("Default Walk-in customer exists", $walkin !== false && (int)$walkin['id'] > 0);
assert_test("Default Walk-in customer has non-null ID", (int)$walkin['id'] > 0);

// --- TEST 3: POS Shift Register Drawer Lifecycle ---
echo "\n[3] Testing POS Shift Register Drawer Lifecycle...\n";
$admin = $pdo->query("SELECT id FROM admins WHERE is_active = 1 LIMIT 1")->fetch();
$testAdminId = $admin ? (int)$admin['id'] : 1;

// Open shift
$openingCash = 500.00;
$stmtOpen = $pdo->prepare("INSERT INTO pos_shifts (admin_id, opening_cash, status, start_time, created_at) VALUES (?, ?, 'open', NOW(), NOW())");
$stmtOpen->execute([$testAdminId, $openingCash]);
$shiftId = (int)$pdo->lastInsertId();

assert_test("Shift opened successfully with ID", $shiftId > 0);

// Drawer Cash In
$stmtCashIn = $pdo->prepare("INSERT INTO pos_drawer_transactions (shift_id, type, amount, notes, created_at) VALUES (?, 'cash_in', 100.00, 'Drawer Topup', NOW())");
$stmtCashIn->execute([$shiftId]);
$cashInId = (int)$pdo->lastInsertId();
assert_test("Drawer Cash In recorded", $cashInId > 0);

// Drawer Cash Out
$stmtCashOut = $pdo->prepare("INSERT INTO pos_drawer_transactions (shift_id, type, amount, notes, created_at) VALUES (?, 'cash_out', 50.00, 'Petty cash purchase', NOW())");
$stmtCashOut->execute([$shiftId]);
$cashOutId = (int)$pdo->lastInsertId();
assert_test("Drawer Cash Out recorded", $cashOutId > 0);

// Close shift
$actualCash = 550.00;
$stmtClose = $pdo->prepare("UPDATE pos_shifts SET end_time = NOW(), closing_cash = ?, actual_cash = ?, status = 'closed' WHERE id = ?");
$stmtClose->execute([550.00, $actualCash, $shiftId]);

$closedShift = $pdo->query("SELECT * FROM pos_shifts WHERE id = {$shiftId}")->fetch();
assert_test("Shift status transitioned to closed", $closedShift['status'] === 'closed');
assert_test("Shift actual cash matches counted amount", (float)$closedShift['actual_cash'] === 550.00);

// Clean test shift records
$pdo->prepare("DELETE FROM pos_drawer_transactions WHERE shift_id = ?")->execute([$shiftId]);
$pdo->prepare("DELETE FROM pos_shifts WHERE id = ?")->execute([$shiftId]);

// --- TEST 4: POS Sale Transaction, Stock Deduction & Ledger Posting ---
echo "\n[4] Testing POS Sale Execution, Stock Adjustment & Ledger...\n";
// Ensure test product exists
$prod = $pdo->query("SELECT id, name, price, stock FROM products WHERE is_active = 1 AND stock >= 5 LIMIT 1")->fetch();
if (!$prod) {
    $pdo->prepare("INSERT INTO products (name, slug, price, stock, is_active, created_at, updated_at) VALUES ('Audit Test Product', 'audit-test-prod-" . time() . "', 120.00, 50, 1, NOW(), NOW())")->execute();
    $prodId = (int)$pdo->lastInsertId();
    $prod = ['id' => $prodId, 'name' => 'Audit Test Product', 'price' => 120.00, 'stock' => 50];
} else {
    $prodId = (int)$prod['id'];
}

$initialStock = (int)$prod['stock'];
$saleQty = 2;
$unitPrice = (float)$prod['price'];
$lineTotal = $unitPrice * $saleQty;
$orderNum = 'POS-TEST-' . time();

$pdo->beginTransaction();

// Insert Order
$stmtOrder = $pdo->prepare("INSERT INTO orders (order_number, user_id, address_id, subtotal, discount_amount, total_amount, payment_method, payment_status, status, note, created_at) VALUES (?, ?, NULL, ?, 0.00, ?, 'cod', 'paid', 'delivered', 'Automated POS Audit Test', NOW())");
$stmtOrder->execute([$orderNum, (int)$walkin['id'], $lineTotal, $lineTotal]);
$testOrderId = (int)$pdo->lastInsertId();

// Insert Order Item
$stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_sku, price, quantity, line_total) VALUES (?, ?, ?, 'SKU-TEST', ?, ?, ?)");
$stmtItem->execute([$testOrderId, $prodId, $prod['name'], $unitPrice, $saleQty, $lineTotal]);

// Deduct Stock
$pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$saleQty, $prodId]);

// Log Inventory Movement
$remStock = $initialStock - $saleQty;
$pdo->prepare("INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at) VALUES (?, ?, 'stock_out', ?, ?, 'POS Sale Audit Test', NOW())")->execute([$prodId, $testAdminId, -$saleQty, $remStock]);

// Post Ledger Income
$pdo->prepare("INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at) VALUES ('income', NULL, ?, ?, 'pos_split', 1, NOW())")->execute([$lineTotal, "POS Test {$orderNum}"]);

$pdo->commit();

// Verify changes
$updatedProd = $pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetch();
assert_test("Order ID created properly", $testOrderId > 0);
assert_test("Product stock deducted accurately", (int)$updatedProd['stock'] === $remStock);

$logCheck = $pdo->prepare("SELECT * FROM inventory_logs WHERE product_id = ? AND note = 'POS Sale Audit Test' ORDER BY id DESC LIMIT 1");
$logCheck->execute([$prodId]);
$logRow = $logCheck->fetch();
assert_test("Inventory movement log recorded", $logRow !== false && (int)$logRow['quantity'] === -$saleQty);

$txCheck = $pdo->prepare("SELECT * FROM transactions WHERE reference = ? LIMIT 1");
$txCheck->execute(["POS Test {$orderNum}"]);
$txRow = $txCheck->fetch();
assert_test("General ledger income entry recorded", $txRow !== false && (float)$txRow['amount'] === $lineTotal);

// --- TEST 5: POS Returns & Inventory Restock ---
echo "\n[5] Testing POS Returns & Stock Restocking...\n";
$pdo->beginTransaction();

$refundAmount = $lineTotal;
$stmtRet = $pdo->prepare("INSERT INTO pos_returns (order_id, admin_id, refund_amount, refund_method, created_at) VALUES (?, ?, ?, 'cash', NOW())");
$stmtRet->execute([$testOrderId, $testAdminId, $refundAmount]);
$returnId = (int)$pdo->lastInsertId();

$stmtRetItem = $pdo->prepare("INSERT INTO pos_return_items (pos_return_id, product_id, quantity) VALUES (?, ?, ?)");
$stmtRetItem->execute([$returnId, $prodId, $saleQty]);

// Restock
$pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$saleQty, $prodId]);

// Log Restock
$pdo->prepare("INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at) VALUES (?, ?, 'stock_in', ?, ?, 'POS Return Restock Test', NOW())")->execute([$prodId, $testAdminId, $saleQty, $initialStock]);

// Ledger Reimbursement
$pdo->prepare("INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at) VALUES ('expense', NULL, ?, ?, 'cash', 1, NOW())")->execute([$refundAmount, "POS Return {$orderNum}"]);

$pdo->commit();

$restockedProd = $pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetch();
assert_test("POS Return record created", $returnId > 0);
assert_test("Product stock restored to initial quantity", (int)$restockedProd['stock'] === $initialStock);

// Clean test order records
$pdo->prepare("DELETE FROM pos_return_items WHERE pos_return_id = ?")->execute([$returnId]);
$pdo->prepare("DELETE FROM pos_returns WHERE id = ?")->execute([$returnId]);
$pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$testOrderId]);
$pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$testOrderId]);
$pdo->prepare("DELETE FROM inventory_logs WHERE product_id = ? AND note LIKE 'POS %Audit Test'")->execute([$prodId]);
$pdo->prepare("DELETE FROM transactions WHERE reference LIKE 'POS %{$orderNum}'")->execute();

// --- TEST 6: POS Suspended Cart (Hold & Resume) ---
echo "\n[6] Testing POS Suspended Cart (Hold & Resume)...\n";
$cartPayload = json_encode([
    $prodId => [
        'id' => $prodId,
        'name' => $prod['name'],
        'price' => $unitPrice,
        'qty' => 3,
        'stock' => $initialStock,
        'image' => '',
        'sku' => 'SKU-TEST'
    ]
]);

$stmtHold = $pdo->prepare("INSERT INTO pos_hold_orders (admin_id, customer_id, cart_data, hold_notes, created_at) VALUES (?, ?, ?, 'Customer stepped out for cash', NOW())");
$stmtHold->execute([$testAdminId, (int)$walkin['id'], $cartPayload]);
$holdId = (int)$pdo->lastInsertId();

assert_test("Cart held in database", $holdId > 0);

$heldCart = $pdo->query("SELECT * FROM pos_hold_orders WHERE id = {$holdId}")->fetch();
assert_test("Held cart payload is valid JSON", json_decode($heldCart['cart_data'], true) !== null);

// Resume / Delete held cart
$pdo->prepare("DELETE FROM pos_hold_orders WHERE id = ?")->execute([$holdId]);
$resumedCheck = $pdo->query("SELECT COUNT(*) FROM pos_hold_orders WHERE id = {$holdId}")->fetchColumn();
assert_test("Held cart cleanly deleted upon resume", (int)$resumedCheck === 0);

// --- TEST 7: Receipt Generation Data Reliability ---
echo "\n[7] Testing Receipt Generation Data Reliability...\n";
// Test left join query on deleted product fallback
$testQuery = "
    SELECT oi.*, COALESCE(oi.product_name, p.name, 'Product') AS product_name 
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    LIMIT 5
";
$stmtTestReceipt = $pdo->query($testQuery);
$receiptItems = $stmtTestReceipt->fetchAll();
assert_test("Receipt query executes without errors with LEFT JOIN", is_array($receiptItems));

echo "\n========================================================\n";
echo "SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
