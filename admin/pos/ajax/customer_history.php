<?php
/**
 * ==============================================================================
 * admin/pos/ajax/customer_history.php — Customer CRM & Purchase History Endpoint
 * ==============================================================================
 * Reference: Matthew Cote (2015)
 *   "The Power of Point of Sale: Improving Growth, Profit, and Customer Service
 *    in a Retail Business" (Bryant University, pp. 4–5, 9, 14–15)
 * ==============================================================================
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../public/dbconnect.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/pos_lib.php';

if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!has_admin_permission('pos.sale') && !has_admin_permission('pos.access') && !has_admin_permission('pos.manage') && !has_admin_permission('pos.cash')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$customerId = (int) input('customer_id', 0, 'get');
if ($customerId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid customer ID']);
    exit;
}

$pdo = db();
$walkinId = pos_walkin_customer_id($pdo);
$isWalkin = ($customerId === $walkinId);

try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, phone, wallet_balance, reward_points, created_at
        FROM users 
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode(['success' => false, 'error' => 'Customer record not found.']);
        exit;
    }

    // Purchase statistics
    $stStats = $pdo->prepare("
        SELECT COUNT(*) as total_orders, 
               COALESCE(SUM(total_amount), 0) as total_spent,
               MAX(created_at) as last_order_date
        FROM orders
        WHERE user_id = ? AND status != 'cancelled'
    ");
    $stStats->execute([$customerId]);
    $stats = $stStats->fetch(PDO::FETCH_ASSOC);

    // Recent receipts (up to 8)
    $stOrders = $pdo->prepare("
        SELECT id, order_number, total_amount, payment_method, payment_status, status, status AS order_status, created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $stOrders->execute([$customerId]);
    $recentOrders = $stOrders->fetchAll(PDO::FETCH_ASSOC);

    // For each order, fetch items
    $ordersWithItems = [];
    $stItems = $pdo->prepare("
        SELECT id, order_id, product_id, product_name, product_name AS name, product_sku, price, quantity, line_total
        FROM order_items
        WHERE order_id = ?
        ORDER BY id ASC
    ");

    foreach ($recentOrders as $ord) {
        $stItems->execute([$ord['id']]);
        $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
        $ord['items'] = $items;
        $ordersWithItems[] = $ord;
    }

    echo json_encode([
        'success'  => true,
        'customer' => [
            'id'             => (int) $customer['id'],
            'name'           => $customer['full_name'],
            'full_name'      => $customer['full_name'],
            'email'          => $customer['email'],
            'phone'          => $customer['phone'] ?: 'N/A',
            'wallet_balance' => (float) ($customer['wallet_balance'] ?? 0),
            'reward_points'  => (int) ($customer['reward_points'] ?? 0),
            'lifetime_spent' => (float) ($stats['total_spent'] ?? 0),
            'total_orders'   => (int) ($stats['total_orders'] ?? 0),
            'registered_at'  => $customer['created_at'],
            'is_walkin'      => $isWalkin,
        ],
        'stats' => [
            'total_orders'    => (int) ($stats['total_orders'] ?? 0),
            'total_spent'     => (float) ($stats['total_spent'] ?? 0),
            'last_order_date' => $stats['last_order_date'] ?? null,
        ],
        'recent_orders' => $ordersWithItems,
    ]);
} catch (Throwable $e) {
    error_log('[admin/pos/ajax/customer_history] error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error retrieving customer history.']);
}
