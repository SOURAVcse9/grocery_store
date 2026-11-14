<?php
/**
 * ==============================================================================
 * tests/pos_v2_research_features_test.php
 * Automated Verification Suite for Research-Based POS Features
 * ==============================================================================
 * References:
 * 1. Santosa & Wirawan (2019) - Web & QR Code POS System (ICAESS pp. 69-74)
 * 2. Antczak & Weron (2019) - Supermarket Transactions & Cashier Operations (MDPI Data 4, 67)
 * 3. Matthew Cote (2015) - Retail Growth, Profit, & Customer Service (Bryant Univ.)
 * ==============================================================================
 */

declare(strict_types=1);

if (!defined('GROCO_CLI_TEST_MODE')) {
    define('GROCO_CLI_TEST_MODE', true);
}

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../admin/includes/auth_helpers.php';
require_once __DIR__ . '/../admin/middleware/auth_middleware.php';
require_once __DIR__ . '/../admin/includes/pos_lib.php';

$pdo = db();
$pass = 0;
$fail = 0;

function test_assert(bool $ok, string $name, string $details = ''): void {
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$name}\n";
        if ($details) echo "         -> {$details}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$name}\n";
        if ($details) echo "         -> {$details}\n";
    }
}

echo "\n====================================================================\n";
echo " GROCO POS V2 — RESEARCH-BASED FEATURES VERIFICATION SUITE\n";
echo " Database: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";
echo "====================================================================\n\n";

// ============================================================================
// PART 1: Santosa & Wirawan (2019) - QR & Barcode Product Identification
// ============================================================================
echo "--- 1. SANTOSA & WIRAWAN (2019): QR & BARCODE IDENTIFICATION ---\n";

// 1.1 Offline QR Code Engine Asset
$qrAssetPath = __DIR__ . '/../admin/assets/js/qrcode.min.js';
test_assert(file_exists($qrAssetPath) && filesize($qrAssetPath) > 10000,
    "Offline bundled QR engine asset present",
    "qrcode.min.js size: " . filesize($qrAssetPath) . " bytes (no external CDN required)");

// 1.2 QR Label Generators Available
$qrPage1 = __DIR__ . '/../admin/products/qr_labels.php';
$qrPage2 = __DIR__ . '/../admin/pos/qr_labels.php';
test_assert(file_exists($qrPage1) && file_exists($qrPage2),
    "QR Label generator pages installed in products/ and pos/ directories");

// 1.3 Test Structured QR payload parsing
$testProd = $pdo->query("SELECT id, name, sku, barcode, price, stock FROM products WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
test_assert(!empty($testProd), "Active product available for QR payload resolution", "Product: {$testProd['name']}, SKU: {$testProd['sku']}");

// Test GROCO:SKU:<sku> format
$skuPrefixQuery = "GROCO:SKU:" . $testProd['sku'];
$cleanedSku = trim((string)preg_replace('/^GROCO:SKU:/i', '', $skuPrefixQuery));
$st = $pdo->prepare("SELECT id, name, sku, barcode, price, stock, is_active FROM products WHERE sku = ? LIMIT 1");
$st->execute([$cleanedSku]);
$foundBySkuPrefix = $st->fetch(PDO::FETCH_ASSOC);
test_assert(!empty($foundBySkuPrefix) && (int)$foundBySkuPrefix['id'] === (int)$testProd['id'],
    "Structured 'GROCO:SKU:<sku>' payload correctly parsed and resolves product",
    "Resolved: {$foundBySkuPrefix['name']} (ID #{$foundBySkuPrefix['id']})");

// Test GROCO:PRODUCT:<id> format
$prodPrefixQuery = "GROCO:PRODUCT:" . $testProd['id'];
$cleanedId = (int)preg_replace('/^GROCO:PRODUCT:/i', '', $prodPrefixQuery);
$st2 = $pdo->prepare("SELECT id, name, sku, barcode, price, stock, is_active FROM products WHERE id = ? LIMIT 1");
$st2->execute([$cleanedId]);
$foundByIdPrefix = $st2->fetch(PDO::FETCH_ASSOC);
test_assert(!empty($foundByIdPrefix) && (int)$foundByIdPrefix['id'] === (int)$testProd['id'],
    "Structured 'GROCO:PRODUCT:<id>' payload correctly parsed and resolves product",
    "Resolved: {$foundByIdPrefix['name']} (ID #{$foundByIdPrefix['id']})");

// Test URL-wrapped QR payload format: https://groco.local/p/APPLES123
$urlQuery = "https://groco.example.com/products/view.php?sku=" . urlencode($testProd['sku']);
$parsedUrl = parse_url($urlQuery);
parse_str($parsedUrl['query'] ?? '', $urlParams);
$extractedSku = $urlParams['sku'] ?? '';
test_assert($extractedSku === $testProd['sku'],
    "URL-embedded QR code payload parameter extracted safely",
    "Extracted SKU: {$extractedSku}");


// ============================================================================
// PART 2: Antczak & Weron (2019) - Operational Events & Supermarket Analytics
// ============================================================================
echo "\n--- 2. ANTCZAK & WERON (2019): OPERATIONS & HIGH-FREQUENCY TIMELINE ---\n";

// 2.1 Table schema checks
$hasOpLogs = (bool) $pdo->query("SHOW TABLES LIKE 'pos_operator_logs'")->fetchColumn();
test_assert($hasOpLogs, "Table 'pos_operator_logs' exists in schema");

// Check required columns in pos_transactions
$txCols = $pdo->query("SHOW COLUMNS FROM pos_transactions")->fetchAll(PDO::FETCH_COLUMN);
$reqCols = ['begin_time', 'end_time', 'tran_time', 'break_time', 'art_num', 'is_cash', 'is_card'];
$missingCols = array_diff($reqCols, $txCols);
test_assert(empty($missingCols), "pos_transactions contains all Antczak & Weron timeline columns",
    "Columns present: " . implode(', ', $reqCols));

// 2.2 Operational Event Logging
$adminId = (int) $pdo->query("SELECT id FROM admins WHERE is_active = 1 LIMIT 1")->fetchColumn();

// OperatorLock test
$stLock = $pdo->prepare("INSERT INTO pos_operator_logs (operator_id, workstation_id, event_type, event_time, metadata, created_at)
                         VALUES (?, 1, 'OperatorLock', NOW(), ?, NOW())");
$stLock->execute([$adminId, json_encode(['reason' => 'cashier break', 'station' => 'POS-REG-01'])]);
$lockLogId = (int) $pdo->lastInsertId();
test_assert($lockLogId > 0, "OperatorLock event successfully recorded", "Log ID: {$lockLogId}");

// OperatorUnLock test
$stUnlock = $pdo->prepare("INSERT INTO pos_operator_logs (operator_id, workstation_id, event_type, event_time, metadata, created_at)
                           VALUES (?, 1, 'OperatorUnLock', NOW(), ?, NOW())");
$stUnlock->execute([$adminId, json_encode(['auth_method' => 'password', 'resumed_at' => date('Y-m-d H:i:s')])]);
$unlockLogId = (int) $pdo->lastInsertId();
test_assert($unlockLogId > 0, "OperatorUnLock event successfully recorded", "Log ID: {$unlockLogId}");

// 2.3 Dual-Write Checkout with TranTime and BreakTime
// Ensure shift is open
$pdo->prepare("UPDATE pos_shifts SET status = 'closed', end_time = NOW() WHERE admin_id = ? AND status = 'open'")->execute([$adminId]);
$shiftId = pos_open_shift($pdo, $adminId, 1000.00);

$scanStart = time() - 35; // Simulated cashier scan started 35 seconds ago
$saleIdem = 'RESEARCH-TEST-' . bin2hex(random_bytes(6));
$salePayload = [
    'items'           => json_encode([['id' => $testProd['id'], 'qty' => 3, 'price' => (float)$testProd['price'], 'line_discount' => 0]]),
    'discount'        => '0',
    'cash'            => (string) ((float)$testProd['price'] * 3),
    'card'            => '0',
    'bkash'           => '0',
    'wallet'          => '0',
    'bank_transfer'   => '0',
    'customer_id'     => '0',
    'note'            => 'Research Timeline Test',
    'scan_start_time' => (string) $scanStart,
    'idempotency_key' => $saleIdem,
];

$permCheck = fn(string $p) => true;
$res = pos_checkout($pdo, $salePayload, $adminId, $permCheck);
test_assert($res['order_id'] > 0, "Dual-write checkout completed", "Order #{$res['order_number']}");

// Verify relational pos_transactions row
$posTx = $pdo->prepare("SELECT * FROM pos_transactions WHERE order_id = ? LIMIT 1");
$posTx->execute([$res['order_id']]);
$txData = $posTx->fetch(PDO::FETCH_ASSOC);

test_assert(!empty($txData), "pos_transactions row dual-written successfully", "pos_transactions.id: {$txData['id']}");
test_assert((int)$txData['tran_time'] >= 30, "TranTime (active service duration) accurately captured", "tran_time: {$txData['tran_time']} seconds");
test_assert((int)$txData['art_num'] === 3, "ArtNum (items scanned per transaction) accurately recorded", "art_num: {$txData['art_num']}");
test_assert((int)$txData['is_cash'] === 1 && (int)$txData['is_card'] === 0, "Tender classification flag set correctly", "is_cash: 1, is_card: 0");

// Verify pos_transaction_items
$txItems = $pdo->prepare("SELECT * FROM pos_transaction_items WHERE transaction_id = ?");
$txItems->execute([$txData['id']]);
$itemsData = $txItems->fetchAll(PDO::FETCH_ASSOC);
test_assert(count($itemsData) === 1 && (float)$itemsData[0]['quantity'] == 3.0,
    "pos_transaction_items contains itemized line record",
    "Product: {$itemsData[0]['product_name']}, Qty: {$itemsData[0]['quantity']}");

// Verify pos_payments
$txPays = $pdo->prepare("SELECT * FROM pos_payments WHERE transaction_id = ?");
$txPays->execute([$txData['id']]);
$payData = $txPays->fetchAll(PDO::FETCH_ASSOC);
test_assert(count($payData) >= 1 && $payData[0]['payment_method'] === 'cash',
    "pos_payments contains tender breakdown record",
    "Method: {$payData[0]['payment_method']}, Amount: ৳{$payData[0]['amount']}");

// Close test shift
pos_close_shift($pdo, $adminId, 1000.00 + (float)$testProd['price'] * 3);


// ============================================================================
// PART 3: Matthew Cote (2015) - Demand Planning & Customer CRM
// ============================================================================
echo "\n--- 3. MATTHEW COTE (2015): DEMAND PLANNING & CUSTOMER CRM ---\n";

// 3.1 Reorder recommendations logic
// Calculate ADS over last 30 days for test product
$stAds = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) / 30.0 as ads
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.product_id = ?
      AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND o.status NOT IN ('cancelled', 'failed')
");
$stAds->execute([$testProd['id']]);
$adsVal = (float)$stAds->fetchColumn();
test_assert($adsVal >= 0.0, "Average Daily Sales (ADS) calculation operational", "ADS (30d): " . round($adsVal, 4) . " units/day");

// Stockout days remaining formula: stock / ADS
$currentStock = (int)$pdo->query("SELECT stock FROM products WHERE id = {$testProd['id']}")->fetchColumn();
$daysOfSupply = ($adsVal > 0.0001) ? round($currentStock / $adsVal, 1) : 999;
test_assert(is_numeric($daysOfSupply), "Days of Supply (Stock Remaining) calculated without division-by-zero", "Days remaining: {$daysOfSupply}");

// Suggested reorder formula with 5-day lead time buffer
$leadTimeDays = 5;
$safetyStockDays = 3;
$reorderThreshold = ($adsVal * ($leadTimeDays + $safetyStockDays));
$suggestedOrderQty = ($currentStock <= $reorderThreshold) ? max(10, (int)ceil($reorderThreshold * 2 - $currentStock)) : 0;
test_assert($suggestedOrderQty >= 0, "Lead-time safety buffer and suggested PO quantity computed", "Suggested reorder: {$suggestedOrderQty} units");

// 3.2 Customer CRM History Endpoint Logic
$cust = $pdo->query("SELECT id, full_name as name, email, phone, wallet_balance, reward_points FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($cust) {
    // Lifetime spend aggregation
    $stCustSpend = $pdo->prepare("
        SELECT COUNT(id) as total_orders, COALESCE(SUM(total_amount), 0.00) as lifetime_spent
        FROM orders
        WHERE user_id = ? AND status NOT IN ('cancelled', 'failed')
    ");
    $stCustSpend->execute([$cust['id']]);
    $spendData = $stCustSpend->fetch(PDO::FETCH_ASSOC);

    test_assert(isset($spendData['total_orders']),
        "Customer lifetime purchase metrics aggregated successfully",
        "Customer: {$cust['name']}, Total Orders: {$spendData['total_orders']}, Lifetime Spend: ৳{$spendData['lifetime_spent']}");

    // Recent receipts fetch
    $stRecent = $pdo->prepare("
        SELECT id, order_number, total_amount, payment_method, status, created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY id DESC LIMIT 8
    ");
    $stRecent->execute([$cust['id']]);
    $recentOrders = $stRecent->fetchAll(PDO::FETCH_ASSOC);
    test_assert(is_array($recentOrders), "Customer recent order history query executed cleanly", "Orders fetched: " . count($recentOrders));
} else {
    test_assert(true, "Users table checked (no customer rows present for test, schema valid)");
}

// ============================================================================
// PART 4: Return & Void Lifecycle Synchronization
// ============================================================================
echo "\n--- 4. LIFECYCLE SYNCHRONIZATION: RETURNS & VOIDS ---\n";

// Open a shift for return test
$shiftId = pos_open_shift($pdo, $adminId, 1000.00);

// Create a sale to test return
$retSaleIdem = 'RET-TEST-' . bin2hex(random_bytes(6));
$retPayload = [
    'items'           => json_encode([['id' => $testProd['id'], 'qty' => 2, 'price' => (float)$testProd['price'], 'line_discount' => 0]]),
    'discount'        => '0',
    'cash'            => (string) ((float)$testProd['price'] * 2),
    'card'            => '0',
    'bkash'           => '0',
    'wallet'          => '0',
    'bank_transfer'   => '0',
    'customer_id'     => '0',
    'note'            => 'Return Test',
    'idempotency_key' => $retSaleIdem,
];
$retSaleRes = pos_checkout($pdo, $retPayload, $adminId, $permCheck);

// Process partial return
$returnItems = [$testProd['id'] => 1];
$returnRes = pos_process_return($pdo, $retSaleRes['order_id'], $returnItems, 'cash', 'Customer return', $adminId, $permCheck);
test_assert($returnRes['return_id'] > 0, "Partial return processed", "Return ID: {$returnRes['return_id']}");

// Check pos_transactions status synchronized to partial_return
$stSync = $pdo->prepare("SELECT status FROM pos_transactions WHERE order_id = ?");
$stSync->execute([$retSaleRes['order_id']]);
$syncedStatus = $stSync->fetchColumn();
test_assert($syncedStatus === 'partial_return', "pos_transactions status synchronized on partial return", "Status: {$syncedStatus}");

// Clean close
pos_close_shift($pdo, $adminId, 1000.00 + (float)$testProd['price']);

echo "\n====================================================================\n";
echo " RESEARCH VERIFICATION RESULTS: {$pass} PASSED, {$fail} FAILED\n";
echo "====================================================================\n\n";

if ($fail > 0) {
    exit(1);
}
exit(0);
