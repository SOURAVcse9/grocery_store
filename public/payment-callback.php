<?php
/**
 * ==========================================================================
 * public/payment-callback.php — Payment Gateway Callbacks & IPN Handler
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$orderId = (int) input('order_id', '0', 'get');
$status = input('status', 'failed', 'get');
$gateway = input('gateway', 'bkash', 'get');

if ($orderId <= 0) {
    echo "Invalid request parameters.";
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo "Order details not found.";
        exit;
    }

    if ($status === 'success') {
        $pdo->beginTransaction();

        // 1. Update order payment status
        $up = $pdo->prepare("UPDATE orders SET payment_status = 'paid', updated_at = NOW() WHERE id = ?");
        $up->execute([$orderId]);

        // 2. Generate dynamic transaction ID
        $txnId = 'TXN-' . strtoupper(bin2hex(random_bytes(6)));

        // 3. Log to payment logs
        $stmtLog = $pdo->prepare("
            INSERT INTO payment_gateway_logs (order_id, gateway, transaction_id, amount, status, response_payload, created_at)
            VALUES (?, ?, ?, ?, 'success', ?, NOW())
        ");
        $payload = json_encode([
            'simulated_status' => 'success',
            'order_number' => $order['order_number'],
            'gateway_channel' => $gateway,
            'processed_at' => date('Y-m-d H:i:s')
        ]);
        $stmtLog->execute([$orderId, $gateway, $txnId, $order['total_amount'], $payload]);

        // 4. Record income in ledger
        $pdo->prepare("
            INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
            VALUES ('income', NULL, ?, ?, ?, 1, NOW())
        ")->execute([$order['total_amount'], "Online Checkout payment Order #{$order['order_number']}", $gateway]);

        $pdo->commit();
        log_action('payment_callback', "Online payment callback success for order #{$order['order_number']}. Gateway: {$gateway}, Txn: {$txnId}");

        // Store order details in session to let thank-you.php load summary
        $_SESSION['last_order'] = [
            'id'           => $orderId,
            'number'       => $order['order_number'],
            'grand_total'  => (float) $order['total_amount'],
            'email'        => !is_logged_in() ? 'guest@groco.com' : current_user()['email']
        ];

        flash('checkout_success', 'Payment processed successfully!', 'success');
        header('Location: thank-you.php');
        exit;

    } else {
        // Payment failed or cancelled
        $pdo->beginTransaction();

        // Restore stock
        $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll();

        $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        foreach ($items as $item) {
            $stmtUpdateStock->execute([(int)$item['quantity'], (int)$item['product_id']]);
        }

        // Cancel order status
        $pdo->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'failed', updated_at = NOW() WHERE id = ?")->execute([$orderId]);

        // Log transaction payout failed
        $stmtLog = $pdo->prepare("
            INSERT INTO payment_gateway_logs (order_id, gateway, transaction_id, amount, status, response_payload, created_at)
            VALUES (?, ?, 'FAILED-TXN', ?, ?, ?, NOW())
        ");
        $payload = json_encode([
            'simulated_status' => $status,
            'order_number' => $order['order_number'],
            'gateway_channel' => $gateway,
            'processed_at' => date('Y-m-d H:i:s')
        ]);
        $stmtLog->execute([$orderId, $gateway, $order['total_amount'], $status, $payload]);

        $pdo->commit();
        log_action('payment_callback', "Online payment callback failed/cancelled for order #{$order['order_number']}. Status: {$status}");

        flash('checkout_error', "Online payment was {$status}. Please try checkout again.", 'danger');
        header('Location: checkout.php');
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[payment-callback.php] error: ' . $e->getMessage());
    echo "Processing callback failed.";
    exit;
}
