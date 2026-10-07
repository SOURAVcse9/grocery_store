<?php
/**
 * ==============================================================================
 * public/payment/sslcommerz/fail.php — SSLCOMMERZ Payment Failure Handler
 * ==============================================================================
 * Displays friendly payment failure information and provides one-click retry
 * options to recover the customer's order without losing their items.
 * ==============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/dbconnect.php';
require_once dirname(__DIR__, 2) . '/includes/PaymentService.php';

$pdo = db();
$paymentService = new PaymentService($pdo);

$tranId = trim((string)($_POST['tran_id'] ?? $_GET['tran_id'] ?? ''));
$failedReason = trim((string)($_POST['error'] ?? $_POST['failedreason'] ?? 'Payment was cancelled or declined by your bank / provider.'));

$orderId = 0;
$orderNumber = '';
$totalAmount = 0.0;

if ($tranId !== '') {
    $res = $paymentService->handlePaymentFailure($tranId, $failedReason, $_POST);
    
    // Look up order info
    $stmt = $pdo->prepare('
        SELECT o.id, o.order_number, o.total_amount 
        FROM payments p 
        JOIN orders o ON o.id = p.order_id 
        WHERE p.tran_id = ? 
        LIMIT 1
    ');
    $stmt->execute([$tranId]);
    $orderData = $stmt->fetch();
    if ($orderData) {
        $orderId = (int)$orderData['id'];
        $orderNumber = (string)$orderData['order_number'];
        $totalAmount = (float)$orderData['total_amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Unsuccessful — <?= e(site_name()) ?></title>
    <link rel="stylesheet" href="<?= asset_url('css/styles.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .pay-status-container { max-width: 600px; margin: 60px auto; padding: 35px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; }
        .pay-icon-danger { font-size: 55px; color: #fa5252; margin-bottom: 20px; }
        .pay-title { font-size: 24px; font-weight: 700; color: #212529; margin-bottom: 12px; }
        .pay-desc { font-size: 15px; color: #495057; line-height: 1.6; margin-bottom: 24px; }
        .pay-meta-box { background: #fff5f5; border: 1px solid #ffc9c9; border-radius: 8px; padding: 16px; margin-bottom: 24px; text-align: left; font-size: 14px; }
        .pay-meta-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .btn-action-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-retry { background: #0ca678; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline { background: #fff; color: #495057; border: 1px solid #ced4da; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; }
        .btn-retry:hover { background: #099268; }
        .btn-outline:hover { background: #f8f9fa; }
    </style>
</head>
<body class="bg-light">
    <div class="pay-status-container">
        <div class="pay-icon-danger"><i class="fas fa-circle-xmark"></i></div>
        <h1 class="pay-title">Payment Unsuccessful</h1>
        <p class="pay-desc">
            Your payment could not be processed at this time. Your account was not charged.
        </p>

        <div class="pay-meta-box">
            <?php if ($orderNumber !== ''): ?>
                <div class="pay-meta-row"><strong>Order Reference:</strong> <span>#<?= e($orderNumber) ?></span></div>
                <div class="pay-meta-row"><strong>Payable Amount:</strong> <span>৳<?= number_format($totalAmount, 2) ?></span></div>
            <?php endif; ?>
            <?php if ($tranId !== ''): ?>
                <div class="pay-meta-row"><strong>Transaction ID:</strong> <span><?= e($tranId) ?></span></div>
            <?php endif; ?>
            <div class="pay-meta-row"><strong>Gateway Message:</strong> <span class="text-danger"><?= e($failedReason) ?></span></div>
        </div>

        <div class="btn-action-group">
            <?php if ($orderId > 0): ?>
                <a href="<?= url_for('order-pay.php?order_id=' . $orderId) ?>" class="btn-retry">
                    <i class="fas fa-rotate-right"></i> Retry Payment
                </a>
            <?php endif; ?>
            <a href="<?= url_for('index.php') ?>" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Return to Store
            </a>
            <a href="<?= url_for('contact.php') ?>" class="btn-outline">
                <i class="fas fa-headset"></i> Need Help?
            </a>
        </div>
    </div>
</body>
</html>
