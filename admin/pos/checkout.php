<?php
/**
 * ==========================================================================
 * admin/pos/checkout.php — POS Checkout API (AJAX POST, JSON)
 * ==========================================================================
 * Thin controller. ALL pricing, discount, stock, payment and change logic is
 * recomputed server-side in includes/pos_lib.php::pos_checkout().
 * The browser's totals are never trusted.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/pos_lib.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

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
if (!method_is('post')) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
if (!verify_csrf()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid security request (CSRF check failed). Refresh the page and try again.']);
    exit;
}

$pdo = db();
$adminId = (int) current_admin_id();

try {
    $res = pos_checkout($pdo, [
        'items'           => input('items', '[]'),
        'discount'        => input('discount', '0'),
        'cash'            => input('cash', '0'),
        'card'            => input('card', '0'),
        'bkash'           => input('bkash', '0'),
        'wallet'          => input('wallet', '0'),
        'bank_transfer'   => input('bank_transfer', '0'),
        'customer_id'     => input('customer_id', '0'),
        'note'            => input('note', 'POS Checkout'),
        'idempotency_key' => input('idempotency_key', ''),
    ], $adminId, static fn(string $perm): bool => has_admin_permission($perm));

    if (!$res['duplicate']) {
        log_admin_activity('pos.checkout', "POS sale {$res['order_number']} total ৳{$res['total']} paid ৳{$res['paid']} change ৳{$res['change']}");
    }
    echo json_encode([
        'success' => true, 'order_id' => $res['order_id'], 'order_number' => $res['order_number'],
        'subtotal' => $res['subtotal'], 'discount' => $res['discount'], 'vat' => $res['vat'], 'total' => $res['total'],
        'paid' => $res['paid'], 'change' => $res['change'], 'duplicate' => $res['duplicate'],
    ]);
} catch (PosException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[admin/pos/checkout] failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Checkout failed due to a server error. Nothing was charged; please retry.']);
}
