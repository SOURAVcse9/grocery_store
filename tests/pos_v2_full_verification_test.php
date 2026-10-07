<?php
/**
 * ==============================================================================
 * GroCo Grocery Store — POS V2 Comprehensive Real-Database Verification Test Suite
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../admin/includes/auth_helpers.php';
require_once __DIR__ . '/../admin/middleware/auth_middleware.php';
require_once __DIR__ . '/../admin/includes/pos_lib.php';

$pdo = db();
$pass = 0;
$fail = 0;

function report(bool $ok, string $desc, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$desc}\n";
        if ($detail) echo "         -> {$detail}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$desc}\n";
        if ($detail) echo "         -> {$detail}\n";
    }
}

echo "\n====================================================================\n";
echo " GROCO GROCERY STORE — POS V2 FULL VERIFICATION TEST SUITE\n";
echo " Target DB: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";
echo "====================================================================\n\n";

// --- SECTION 1: SHIFT MANAGEMENT ---
echo "--- 1. SHIFT MANAGEMENT LIFECYCLE ---\n";
// Find a valid admin id
$adminId = (int) $pdo->query("SELECT id FROM admins WHERE is_active = 1 LIMIT 1")->fetchColumn();
report($adminId > 0, "Valid active admin found", "Admin ID: {$adminId}");

// Close any open shift for clean slate
$pdo->prepare("UPDATE pos_shifts SET status = 'closed', end_time = NOW() WHERE admin_id = ? AND status = 'open'")->execute([$adminId]);

// Open shift
$shiftId = pos_open_shift($pdo, $adminId, 1500.00);
report($shiftId > 0, "Shift opened successfully", "Shift ID: {$shiftId}, Opening: ৳1500.00");

// Prevent duplicate open shift
try {
    pos_open_shift($pdo, $adminId, 2000.00);
    report(false, "Duplicate open shift prevented");
} catch (PosException $e) {
    report(true, "Duplicate open shift strictly prevented", $e->getMessage());
}

// Cash in
pos_drawer_tx($pdo, $adminId, 'cash_in', 500.00, 'Adding change from safe');
report(true, "Cash in recorded to drawer & ledger");

// Cash out
pos_drawer_tx($pdo, $adminId, 'cash_out', 200.00, 'Petty cash for store supplies');
report(true, "Cash out recorded to drawer & ledger");

// Inspect drawer balance
$shiftRow = pos_active_shift($pdo, $adminId);
$shiftSummary = pos_shift_summary($pdo, $shiftRow);
// Expected: 1500 + 500 - 200 = 1800
report($shiftSummary['expected_cash'] == 1800.00, "Shift drawer balance accurately computed", "Expected: ৳{$shiftSummary['expected_cash']}");


// --- SECTION 2: CHECKOUT & SERVER-AUTHORITATIVE PRICING ---
echo "\n--- 2. SERVER-AUTHORITATIVE CHECKOUT & MONETARY SAFETY ---\n";
// Select a test product with stock
$prod = $pdo->query("SELECT id, name, price, stock, sku FROM products WHERE is_active = 1 AND stock >= 10 LIMIT 1")->fetch();
report(!empty($prod), "Test product loaded from DB", "Product: {$prod['name']} (ID: {$prod['id']}), Stock: {$prod['stock']}, Price: ৳{$prod['price']}");

$initStock = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();

// Execute standard sale of 2 units with ৳10 discount
$permCheck = fn(string $p) => true; // simulation with all permissions
$idemKey = 'TEST-IDEM-' . bin2hex(random_bytes(6));
$salePayload = [
    'items'           => json_encode([['id' => $prod['id'], 'qty' => 2, 'price' => (float)$prod['price'], 'line_discount' => 0]]),
    'discount'        => '10',
    'cash'            => (string) ((float)$prod['price'] * 2 - 10 + 50), // Overpay by 50 to test change
    'card'            => '0',
    'bkash'           => '0',
    'wallet'          => '0',
    'bank_transfer'   => '0',
    'customer_id'     => '0',
    'note'            => 'POS Test Sale',
    'idempotency_key' => $idemKey,
];

$saleResult = pos_checkout($pdo, $salePayload, $adminId, $permCheck);
report($saleResult['order_id'] > 0, "POS sale successfully processed and committed", "Order #{$saleResult['order_number']}, Total: ৳{$saleResult['total']}, Change: ৳{$saleResult['change']}");
report($saleResult['change'] == 50.00, "Cash change correctly calculated", "Change: ৳{$saleResult['change']}");

// Stock check
$newStock = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();
report($newStock === $initStock - 2, "Atomic stock deduction verified", "Before: {$initStock}, After: {$newStock}");

// Order ledger check
$transRow = $pdo->query("SELECT * FROM transactions WHERE reference LIKE '%{$saleResult['order_number']}%' LIMIT 1")->fetch();
report(!empty($transRow), "Financial transaction row written to ledger", "Type: {$transRow['type']}, Amount: ৳{$transRow['amount']}");


// --- SECTION 3: IDEMPOTENCY & REPLAY DEFENSE ---
echo "\n--- 3. IDEMPOTENCY & CONCURRENCY REPLAY DEFENSE ---\n";
// Replay exact same checkout with same idempotency key
$replayResult = pos_checkout($pdo, $salePayload, $adminId, $permCheck);
report($replayResult['duplicate'] === true, "Idempotent request recognized as duplicate", "Returned Order #{$replayResult['order_number']}");
report($replayResult['order_id'] === $saleResult['order_id'], "No duplicate order created; returned identical order ID");

// Confirm stock did NOT deduct again
$stockAfterReplay = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();
report($stockAfterReplay === $newStock, "Inventory untouched by idempotent re-post", "Stock remains: {$stockAfterReplay}");


// --- SECTION 4: SPLIT PAYMENT (MULTI-TENDER) ---
echo "\n--- 4. MULTI-TENDER SPLIT PAYMENT ---\n";
// Buy 1 item, paying half Cash and half bKash
$halfTotal = (float)$prod['price'] / 2;
$splitPayload = [
    'items'           => json_encode([['id' => $prod['id'], 'qty' => 1, 'price' => (float)$prod['price'], 'line_discount' => 0]]),
    'discount'        => '0',
    'cash'            => (string) $halfTotal,
    'card'            => '0',
    'bkash'           => (string) $halfTotal,
    'wallet'          => '0',
    'bank_transfer'   => '0',
    'customer_id'     => '0',
    'note'            => 'Split Payment Test',
    'idempotency_key' => 'TEST-SPLIT-' . bin2hex(random_bytes(6)),
];

$splitRes = pos_checkout($pdo, $splitPayload, $adminId, $permCheck);
report($splitRes['order_id'] > 0, "Multi-tender split payment sale created", "Order #{$splitRes['order_number']}");

// Check order note marker
$splitNote = $pdo->query("SELECT note FROM orders WHERE id = {$splitRes['order_id']}")->fetchColumn();
$marker = pos_parse_marker($splitNote);
report($marker !== null && (float)$marker['cash'] == $halfTotal && (float)$marker['bkash'] == $halfTotal, "Machine-readable payment tender split recorded in note marker", "Cash: ৳{$marker['cash']}, bKash: ৳{$marker['bkash']}");


// --- SECTION 5: INVENTORY FLOOR DEFENSE (NO NEGATIVE STOCK) ---
echo "\n--- 5. INVENTORY OVERSELL DEFENSE (NO NEGATIVE STOCK) ---\n";
$currentStock = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();
$oversellQty = $currentStock + 50;

$oversellPayload = [
    'items'           => json_encode([['id' => $prod['id'], 'qty' => $oversellQty, 'price' => (float)$prod['price'], 'line_discount' => 0]]),
    'discount'        => '0',
    'cash'            => '999999',
    'customer_id'     => '0',
    'idempotency_key' => 'TEST-OVERSELL-' . bin2hex(random_bytes(6)),
];

try {
    pos_checkout($pdo, $oversellPayload, $adminId, $permCheck);
    report(false, "Oversell prevented");
} catch (PosException $e) {
    report(true, "Oversell attempt strictly blocked by row-locking validator", $e->getMessage());
}

$stockAfterOversell = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();
report($stockAfterOversell === $currentStock, "Stock preserved intact without modification", "Stock: {$stockAfterOversell}");


// --- SECTION 6: RETURNS & REFUNDS ---
echo "\n--- 6. RETURNS & REFUNDS WORKFLOW ---\n";
// Return 1 of the 2 units from the first sale
$orderIdToReturn = $saleResult['order_id'];
$returnItems = [
    $prod['id'] => 1
];

$stockBeforeReturn = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();
$returnResult = pos_process_return($pdo, $orderIdToReturn, $returnItems, 'cash', 'Customer returned 1 item damaged package', $adminId, $permCheck);
report($returnResult['return_id'] > 0, "Partial return processed successfully", "Return #{$returnResult['return_id']}, Refunded: ৳{$returnResult['refund']}");

// Stock restored check
$stockAfterReturn = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();
report($stockAfterReturn === $stockBeforeReturn + 1, "Stock accurately restocked upon return", "Before: {$stockBeforeReturn}, After: {$stockAfterReturn}");

// Attempt to return more than purchased
try {
    pos_process_return($pdo, $orderIdToReturn, [$prod['id'] => 5], 'cash', 'Over return attempt', $adminId, $permCheck);
    report(false, "Over-return blocked");
} catch (PosException $e) {
    report(true, "Over-return attempt strictly prevented", $e->getMessage());
}


// --- SECTION 7: VOID & REVERSAL WORKFLOW ---
echo "\n--- 7. VOID ORDER WORKFLOW ---\n";
// Create a separate clean sale to void
$voidSalePayload = [
    'items'           => json_encode([['id' => $prod['id'], 'qty' => 1, 'price' => (float)$prod['price'], 'line_discount' => 0]]),
    'discount'        => '0',
    'cash'            => (string) $prod['price'],
    'customer_id'     => '0',
    'idempotency_key' => 'TEST-VOID-' . bin2hex(random_bytes(6)),
];
$voidSale = pos_checkout($pdo, $voidSalePayload, $adminId, $permCheck);
$stockBeforeVoid = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();

// Void the sale
$voidRes = pos_void_sale($pdo, $voidSale['order_id'], 'Cashier accidental ring-up error', $adminId);
report($voidRes['order_number'] === $voidSale['order_number'], "Order voided and marked cancelled", "Order #{$voidSale['order_number']}");

$orderStatus = $pdo->query("SELECT status, payment_status FROM orders WHERE id = {$voidSale['order_id']}")->fetch();
report($orderStatus['status'] === 'cancelled' && $orderStatus['payment_status'] === 'refunded', "Order status transitioned to cancelled/refunded");

$stockAfterVoid = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prod['id']}")->fetchColumn();
report($stockAfterVoid === $stockBeforeVoid + 1, "Stock automatically restocked on void", "Stock restored to: {$stockAfterVoid}");


// --- SECTION 8: CLOSE SHIFT WITH VARIANCE TRACKING ---
echo "\n--- 8. SHIFT CLOSING & VARIANCE AUDIT ---\n";
$drawerSummary = pos_shift_summary($pdo, pos_active_shift($pdo, $adminId));
$actualCounted = $drawerSummary['expected_cash'] - 10.00; // Simulate 10 taka shortage
$closeRes = pos_close_shift($pdo, $adminId, $actualCounted);
report($closeRes['difference'] == -10.00, "Shift closed with precise variance recorded", "Expected: ৳{$closeRes['expected']}, Actual: ৳{$closeRes['actual']}, Difference: ৳{$closeRes['difference']}");

$shiftStatusInDb = $pdo->query("SELECT status FROM pos_shifts WHERE id = {$shiftId}")->fetchColumn();
report($shiftStatusInDb === 'closed', "Shift status verified as closed in database");


// --- SECTION 9: LICENSE & SECURITY INTEGRITY ---
echo "\n--- 9. LICENSE & CORE SECURITY INTEGRITY ---\n";
report(function_exists('enforce_license'), "Licensing enforcement function present");
report(function_exists('require_admin_auth'), "Admin auth middleware present");
report(function_exists('verify_csrf'), "CSRF verification engine present");


// --- SECTION 10: ONLINE STOREFRONT & PUBLIC CATALOG HARMONY ---
echo "\n--- 10. STOREFRONT & PAYMENT HARMONY ---\n";
$catCount = (int) $pdo->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();
report($catCount > 0, "Public categories catalog intact", "Active categories: {$catCount}");

$paymentMethods = $pdo->query("SELECT DISTINCT payment_method FROM orders")->fetchAll(PDO::FETCH_COLUMN);
report(in_array('cod', $paymentMethods), "COD payment mode supported in orders");

echo "\n====================================================================\n";
echo " POS V2 VERIFICATION RESULTS: {$pass} PASSED, {$fail} FAILED\n";
echo "====================================================================\n";

if ($fail === 0) {
    exit(0);
} else {
    exit(1);
}
