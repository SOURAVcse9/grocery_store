<?php
/**
 * ==============================================================================
 * tools/cron_payment_reconciliation.php — Asynchronous Payment Reconciliation Cron
 * ==============================================================================
 * Scheduled task (runs every 5-10 minutes via Crontab or Windows Task Scheduler).
 * Identifies PENDING payment sessions older than 10 minutes, queries the official
 * SSLCOMMERZ Transaction Query API (merchantTransIDvalidationAPI.php), and
 * authoritatively reconciles order status.
 *
 * Usage:
 *   php tools/cron_payment_reconciliation.php [--force] [--quiet]
 * ==============================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: CLI execution only.']);
    exit(1);
}

define('GROCO_CLI_TEST_MODE', true);
require_once dirname(__DIR__) . '/public/dbconnect.php';
require_once dirname(__DIR__) . '/public/includes/PaymentService.php';

$pdo = db();
$paymentService = new PaymentService($pdo);

echo "[" . date('Y-m-d H:i:s') . "] Starting SSLCOMMERZ Payment Reconciliation Cron...\n";

// Fetch PENDING or INITIATED payments created between 10 minutes and 24 hours ago
$stmt = $pdo->prepare("
    SELECT p.id, p.order_id, p.tran_id, p.amount, p.currency, p.status, p.created_at,
           o.order_number, o.payment_status as order_pay_status
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE p.status IN ('INITIATED', 'PENDING')
      AND p.created_at <= (NOW() - INTERVAL 10 MINUTE)
      AND p.created_at >= (NOW() - INTERVAL 24 HOUR)
    ORDER BY p.id ASC
    LIMIT 50
");
$stmt->execute();
$pendingList = $stmt->fetchAll();

$processed = 0;
$reconciled = 0;
$expired = 0;

foreach ($pendingList as $row) {
    $processed++;
    $tranId = (string)$row['tran_id'];
    $orderId = (int)$row['order_id'];
    echo "  Processing Tran: {$tranId} (Order #{$row['order_number']}, Created: {$row['created_at']})...\n";

    $syncRes = $paymentService->syncTransactionStatus($tranId);

    if ($syncRes['success'] && ($syncRes['status'] ?? '') === 'PAID') {
        $reconciled++;
        echo "    -> RECONCILED: Payment marked as PAID via gateway query.\n";
    } elseif ($syncRes['success'] && in_array($syncRes['gateway_status'] ?? '', ['FAILED', 'CANCELLED'], true)) {
        echo "    -> DECLINED: Gateway reported {$syncRes['gateway_status']}.\n";
    } else {
        // If older than 60 minutes with no gateway completion, transition to EXPIRED
        $createdTime = strtotime($row['created_at']);
        if ((time() - $createdTime) > 3600) {
            $pdo->prepare("UPDATE payments SET status = 'EXPIRED', updated_at = NOW() WHERE id = ?")->execute([$row['id']]);
            $paymentService->logAudit((int)$row['id'], $orderId, $tranId, 'SESSION_EXPIRED', 'PENDING', 'EXPIRED', (float)$row['amount'], (string)$row['currency'], null, 'Automated expiry after 60m inactivity.');
            $expired++;
            echo "    -> EXPIRED: Inactive session expired.\n";
        } else {
            echo "    -> PENDING: Awaiting customer completion or gateway clearing.\n";
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Reconciliation Cron finished. Processed: {$processed}, Reconciled to Paid: {$reconciled}, Expired: {$expired}.\n";
exit(0);
