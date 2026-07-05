<?php
/**
 * ==========================================================================
 * admin/pos/checkout.php — Enterprise POS Checkout API (AJAX POST)
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('pos.sale');

header('Content-Type: application/json');

$pdo = db();
$adminId = current_admin_id();

// Verify active shift exists
$activeShift = $pdo->prepare("SELECT id FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
$activeShift->execute([$adminId]);
if (!$activeShift->fetch()) {
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

$items = json_decode(input('items', '[]'), true);
$discount = (float) input('discount', '0.00');
$cashPaid = (float) input('cash', '0.00');
$cardPaid = (float) input('card', '0.00');
$bkashPaid = (float) input('bkash', '0.00');
$walletPaid = (float) input('wallet', '0.00');
$customerId = (int) input('customer_id', '0');

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Cart is empty.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verify customer wallet balance if wallet payment used
    if ($customerId > 0 && $walletPaid > 0) {
        $stmtCust = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmtCust->execute([$customerId]);
        $walletBal = (float) $stmtCust->fetchColumn();

        if ($walletBal < $walletPaid) {
            throw new Exception("Insufficient customer wallet balance. Available: ৳{$walletBal}");
        }
    }

    // 2. Validate stocks
    $stmtCheck = $pdo->prepare("SELECT stock, name FROM products WHERE id = ? FOR UPDATE");
    foreach ($items as $item) {
        $stmtCheck->execute([(int)$item['id']]);
        $prod = $stmtCheck->fetch();
        if (!$prod || (int)$prod['stock'] < (int)$item['qty']) {
            throw new Exception("Product '" . ($prod['name'] ?? 'Unknown') . "' has insufficient stock.");
        }
    }

    // 3. Compute totals
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += ((float)$item['price'] * (int)$item['qty']);
    }
    $totalAmount = max($subtotal - $discount, 0);

    // 4. Update customer wallet and reward points
    if ($customerId > 0) {
        if ($walletPaid > 0) {
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")->execute([$walletPaid, $customerId]);
        }
        // Reward points: 1 point per 100 BDT spent
        $earnedPoints = (int) floor($totalAmount / 100);
        if ($earnedPoints > 0) {
            $pdo->prepare("UPDATE users SET reward_points = reward_points + ? WHERE id = ?")->execute([$earnedPoints, $customerId]);
        }
    }

    // 5. Create POS Order
    $orderNumber = 'POS-' . date('Ymd') . '-' . rand(1000, 9999);
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (order_number, user_id, address_id, subtotal, discount_amount, total_amount, payment_method, payment_status, status, created_at)
        VALUES (?, ?, NULL, ?, ?, ?, 'pos_split', 'paid', 'delivered', NOW())
    ");
    $stmtOrder->execute([
        $orderNumber,
        $customerId > 0 ? $customerId : null,
        $subtotal,
        $discount,
        $totalAmount
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // 6. Save items & adjust stock levels
    $stmtOrderItem = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $stmtLog = $pdo->prepare("
        INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at)
        VALUES (:pid, :admin_id, 'stock_out', :qty, :rem, :note, NOW())
    ");
    
    $stmtGetStock = $pdo->prepare("SELECT stock FROM products WHERE id = ?");

    foreach ($items as $item) {
        $pid = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];

        $stmtOrderItem->execute([$orderId, $pid, $qty, $price]);
        $stmtUpdateStock->execute([$qty, $pid]);

        // Get remaining stock
        $stmtGetStock->execute([$pid]);
        $remStock = (int)$stmtGetStock->fetchColumn();

        $stmtLog->execute([
            'pid'      => $pid,
            'admin_id' => $adminId,
            'qty'      => -$qty,
            'rem'      => $remStock,
            'note'     => "POS Counter sales checkout transaction Order #{$orderNumber}"
        ]);
    }

    // 7. Post ledger income
    $stmtLedger = $pdo->prepare("
        INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
        VALUES ('income', NULL, ?, ?, 'pos_split', 1, NOW())
    ");
    $stmtLedger->execute([$totalAmount, "POS Counter checkout sales: {$orderNumber}"]);

    $pdo->commit();
    log_admin_activity('pos.checkout', "Completed POS checkout transaction for order '{$orderNumber}' value ৳{$totalAmount}");
    echo json_encode(['success' => true, 'order_id' => $orderId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[admin/pos/checkout] POS checkout failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
