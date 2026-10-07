<?php
/**
 * ==============================================================================
 * tools/cron_release_expired_orders.php — Stock Reservation Expiry Release Cron
 * ==============================================================================
 * Automatically cancels unpaid online orders older than 15 minutes and safely
 * releases reserved inventory stock back into product availability.
 *
 * Usage:
 *   php tools/cron_release_expired_orders.php
 * ==============================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI execution only.\n";
    exit(1);
}

define('GROCO_CLI_TEST_MODE', true);
require_once dirname(__DIR__) . '/public/dbconnect.php';

$pdo = db();

echo "[" . date('Y-m-d H:i:s') . "] Starting Stock Reservation Expiry Cron (15 min window)...\n";

// Fetch unpaid non-COD orders pending longer than 15 minutes
$orderStmt = $pdo->prepare("
    SELECT id, order_number, user_id, inventory_deducted, created_at
    FROM orders
    WHERE payment_status = 'unpaid'
      AND payment_method != 'cod'
      AND status = 'pending'
      AND created_at <= (NOW() - INTERVAL 15 MINUTE)
    ORDER BY id ASC
    LIMIT 100
");
$orderStmt->execute();
$expiredOrders = $orderStmt->fetchAll();

$releasedCount = 0;
$stockItemsRestored = 0;

foreach ($expiredOrders as $order) {
    $orderId = (int)$order['id'];
    $orderNumber = (string)$order['order_number'];

    try {
        $pdo->beginTransaction();

        // 1. Lock order row
        $lockStmt = $pdo->prepare('SELECT id, status, payment_status, inventory_deducted FROM orders WHERE id = ? FOR UPDATE');
        $lockStmt->execute([$orderId]);
        $lockedOrder = $lockStmt->fetch();

        // Guard: double check order was not paid concurrently
        if (!$lockedOrder || $lockedOrder['payment_status'] === 'paid' || $lockedOrder['status'] !== 'pending') {
            $pdo->rollBack();
            continue;
        }

        // 2. Restore stock if previously reserved
        if ((int)$lockedOrder['inventory_deducted'] === 1) {
            $itemsStmt = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll();

            $stockRestorer = $pdo->prepare('UPDATE products SET stock = stock + :qty WHERE id = :id');
            foreach ($items as $item) {
                $stockRestorer->execute([
                    'qty' => (int)$item['quantity'],
                    'id'  => (int)$item['product_id']
                ]);
                $stockItemsRestored += (int)$item['quantity'];
            }
        }

        // 3. Mark order cancelled and reset inventory_deducted
        $pdo->prepare("
            UPDATE orders 
            SET status = 'cancelled', payment_status = 'cancelled', inventory_deducted = 0, updated_at = NOW() 
            WHERE id = ?
        ")->execute([$orderId]);

        // 4. Expire any associated payments
        $pdo->prepare("
            UPDATE payments 
            SET status = 'EXPIRED', expired_at = NOW(), updated_at = NOW() 
            WHERE order_id = ? AND status IN ('INITIATED', 'PENDING')
        ")->execute([$orderId]);

        // 5. Append history & event audit log
        $pdo->prepare("
            INSERT INTO order_status_history (order_id, status, note, created_at)
            VALUES (?, 'cancelled', 'Order automatically cancelled: 15-minute checkout window expired. Reserved stock released back to catalog.', NOW())
        ")->execute([$orderId]);

        $pdo->prepare("
            INSERT INTO payment_events (order_id, event_type, payload, ip, created_at)
            VALUES (?, 'STOCK_RESERVATION_RELEASED', ?, 'CLI/Cron', NOW())
        ")->execute([
            $orderId,
            json_encode(['reason' => '15_min_timeout_expired', 'order_number' => $orderNumber])
        ]);

        $pdo->commit();
        $releasedCount++;
        echo "  [RELEASED] Order #{$orderNumber} (ID: {$orderId}) cancelled; stock restored.\n";

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[cron_release_expired_orders] Failed for order #{$orderId}: " . $e->getMessage());
        echo "  [ERROR] Failed to process order #{$orderId}: {$e->getMessage()}\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Completed: {$releasedCount} expired orders cancelled, {$stockItemsRestored} units restored to inventory.\n";
exit(0);
