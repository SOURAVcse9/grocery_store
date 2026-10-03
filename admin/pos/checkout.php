<?php
/**
 * ==========================================================================
 * admin/pos/checkout.php — Enterprise POS Checkout API (AJAX POST)
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!has_admin_permission('pos.sale') && !has_admin_permission('pos.access') && !has_admin_permission('pos.manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$pdo = db();
$adminId = current_admin_id();

// Verify active shift exists
$activeShift = $pdo->prepare("SELECT id, store_id, register_id, terminal_id FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
$activeShift->execute([$adminId]);
$shift = $activeShift->fetch();
if (!$shift) {
    echo json_encode(['success' => false, 'error' => 'No active cashier shift open.']);
    exit;
}

if (!method_is('post')) {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!verify_csrf()) {
    echo json_encode(['success' => false, 'error' => 'Invalid security request (CSRF check failed).']);
    exit;
}

$rawItems = json_decode(input('items', '[]'), true);
$discount = (float) input('discount', '0.00');
$cashPaid = (float) input('cash', '0.00');
$cardPaid = (float) input('card', '0.00');
$bkashPaid = (float) input('bkash', '0.00');
$walletPaid = (float) input('wallet', '0.00');
$bankTransferPaid = (float) input('bank_transfer', '0.00');
$customerId = (int) input('customer_id', '0');
$note = trim(input('note', 'POS Counter Checkout'));
$clientUuid = trim((string)input('client_uuid', ''));

if (empty($rawItems)) {
    echo json_encode(['success' => false, 'error' => 'Cart is empty.']);
    exit;
}

// Convert items payload to domain service format
$items = [];
foreach ($rawItems as $it) {
    $items[] = [
        'id'             => (int)($it['id'] ?? 0),
        'quantity'       => (float)($it['qty'] ?? 1),
        'price'          => (float)($it['price'] ?? 0),
        'discount'       => (float)($it['discount'] ?? 0),
        'price_override' => !empty($it['price_override']),
        'override_reason'=> $it['override_reason'] ?? null
    ];
}

// Construct payment tenders list
$payments = [];
if ($cashPaid > 0) {
    $payments[] = [
        'method' => 'cash',
        'amount' => $cashPaid
    ];
}
if ($cardPaid > 0) {
    $payments[] = [
        'method'          => 'card',
        'amount'          => $cardPaid,
        'card_type'       => input('card_type', 'Visa'),
        'card_last_four'  => input('card_last_four', input('card_no', '')),
        'card_auth_code'  => input('card_auth_code', input('card_ref', '')),
        'card_bank'       => input('card_bank', '')
    ];
}
if ($bkashPaid > 0) {
    $payments[] = [
        'method'                => 'bkash',
        'amount'                => $bkashPaid,
        'transaction_reference' => input('bkash_txn_id', input('mobile_txn_id', ''))
    ];
}
if ($walletPaid > 0) {
    $payments[] = [
        'method' => 'wallet',
        'amount' => $walletPaid
    ];
}
if ($bankTransferPaid > 0) {
    $payments[] = [
        'method'                => 'bank_transfer',
        'amount'                => $bankTransferPaid,
        'bank_name'             => input('bank_name', ''),
        'transaction_reference' => input('bank_ref', '')
    ];
}

// Default fallback to cash if no payment explicitly separated
if (empty($payments)) {
    $totalCalc = 0.0;
    foreach ($items as $it) {
        $totalCalc += ($it['price'] * $it['quantity']);
    }
    $totalCalc = max($totalCalc - $discount, 0.0);
    $payments[] = ['method' => 'cash', 'amount' => $totalCalc];
}

try {
    $posService = pos_service();
    $result = $posService->transactions()->processSale([
        'store_id'      => (int)($shift['store_id'] ?? 1),
        'register_id'   => (int)($shift['register_id'] ?? 1),
        'terminal_id'   => (int)($shift['terminal_id'] ?? 1),
        'shift_id'      => (int)$shift['id'],
        'cashier_id'    => $adminId,
        'customer_id'   => $customerId > 0 ? $customerId : null,
        'items'         => $items,
        'cart_discount' => $discount,
        'payments'      => $payments,
        'client_uuid'   => $clientUuid !== '' ? $clientUuid : null,
        'notes'         => $note,
        'is_offline'    => false
    ]);

    log_admin_activity('pos.checkout', "Completed POS checkout transaction {$result['transaction_number']} for order ID {$result['order_id']}");

    echo json_encode([
        'success'            => true,
        'order_id'           => $result['order_id'],
        'transaction_id'     => $result['transaction_id'],
        'transaction_number' => $result['transaction_number'],
        'total_amount'       => $result['total_amount'],
        'paid_amount'        => $result['paid_amount'],
        'change_amount'      => $result['change_amount']
    ]);
} catch (Exception $e) {
    error_log('[admin/pos/checkout] POS checkout error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
