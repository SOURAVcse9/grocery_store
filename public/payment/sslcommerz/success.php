<?php
/**
 * ==============================================================================
 * public/payment/sslcommerz/success.php — SSLCOMMERZ Success Return Callback
 * ==============================================================================
 * The customer is returned here after completing payment on the gateway.
 *
 * CRITICAL SECURITY PRINCIPLE:
 * Returning to this URL DOES NOT mark the order as paid!
 * The server calls the official SSLCOMMERZ Order Validation API using the val_id
 * to independently verify transaction identity, amount, and currency before
 * transitioning any state.
 * ==============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/dbconnect.php';
require_once dirname(__DIR__, 2) . '/includes/PaymentService.php';

$pdo = db();
$paymentService = new PaymentService($pdo);

// Extract POST parameters sent by SSLCOMMERZ
$valId  = trim((string)($_POST['val_id'] ?? $_GET['val_id'] ?? ''));
$tranId = trim((string)($_POST['tran_id'] ?? $_GET['tran_id'] ?? ''));

if ($valId === '' || $tranId === '') {
    flash('checkout_error', 'Invalid payment return parameters. No verification token received.', 'error');
    redirect(url_for('cart.php'));
}

// Execute server-side authoritative Order Validation API
$result = $paymentService->validateAndFinalizePayment($valId, $tranId, $_POST, 'callback');

if ($result['success']) {
    $order = $result['order'];
    $payment = $result['payment'];

    // Populate session for thank-you page
    $_SESSION['last_order'] = [
        'id'           => (int)$order['id'],
        'number'       => (string)$order['order_number'],
        'grand_total'  => (float)$order['total_amount'],
        'payment_status' => 'paid',
        'tran_id'      => $tranId,
        'bank_tran_id' => $payment['bank_tran_id'] ?? null,
        'email'        => is_logged_in() ? (current_user()['email'] ?? '') : ''
    ];

    flash('checkout_success', 'Your online payment was verified and processed successfully!', 'success');
    redirect(url_for('thank-you.php?order_id=' . (int)$order['id']));
}

// Payment validation failed or amount mismatched
$orderId = $result['order']['id'] ?? 0;
$errorMessage = $result['message'] ?? 'Payment validation failed. Please contact customer support.';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification Issue — <?= e(site_name()) ?></title>
    <link rel="stylesheet" href="<?= asset_url('css/styles.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .pay-status-container { max-width: 600px; margin: 60px auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; }
        .pay-icon-warning { font-size: 50px; color: #e03131; margin-bottom: 20px; }
        .pay-title { font-size: 22px; font-weight: 700; color: #212529; margin-bottom: 12px; }
        .pay-desc { font-size: 15px; color: #495057; line-height: 1.6; margin-bottom: 24px; }
        .pay-meta-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 24px; text-align: left; font-size: 14px; }
        .pay-meta-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .btn-action-group { display: flex; gap: 12px; justify-content: center; }
        .btn-retry { background: #0ca678; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; }
        .btn-support { background: #495057; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body class="bg-light">
    <div class="pay-status-container">
        <div class="pay-icon-warning"><i class="fas fa-triangle-exclamation"></i></div>
        <h1 class="pay-title">Payment Verification Required</h1>
        <p class="pay-desc">
            We received a response from SSLCOMMERZ, but the server was unable to verify the transaction details automatically.
        </p>

        <div class="pay-meta-box">
            <div class="pay-meta-row"><strong>Transaction ID:</strong> <span><?= e($tranId) ?></span></div>
            <div class="pay-meta-row"><strong>Status:</strong> <span class="badge badge-warning"><?= e($result['status']) ?></span></div>
            <div class="pay-meta-row"><strong>Details:</strong> <span><?= e($errorMessage) ?></span></div>
        </div>

        <div class="btn-action-group">
            <?php if ($orderId > 0): ?>
                <a href="<?= url_for('order-pay.php?order_id=' . $orderId) ?>" class="btn-retry"><i class="fas fa-rotate-right"></i> Retry Payment</a>
            <?php endif; ?>
            <a href="<?= url_for('contact.php') ?>" class="btn-support"><i class="fas fa-headset"></i> Contact Support</a>
        </div>
    </div>
</body>
</html>
