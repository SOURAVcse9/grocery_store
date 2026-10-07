<?php
/**
 * ==============================================================================
 * public/order-pay.php — Customer Payment & Retry Portal
 * ==============================================================================
 * Allows customers to complete or retry online payment for unpaid orders
 * through official SSLCOMMERZ V4 session generation, or switch to Cash on Delivery.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/includes/PaymentService.php';

require_login();

$pdo = db();
$userId = current_user_id();
$orderId = (int)input('order_id', '0', 'get');

if ($orderId <= 0) {
    flash('orders', 'Invalid order specified.', 'error');
    redirect(url_for('orders.php'));
}

// 1. Fetch Order and verify ownership (IDOR protection)
$orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1');
$orderStmt->execute(['id' => $orderId, 'uid' => $userId]);
$order = $orderStmt->fetch();

if (!$order) {
    flash('orders', 'Order not found or unauthorized access.', 'error');
    redirect(url_for('orders.php'));
}

// If already paid, redirect to invoice
if ($order['payment_status'] === 'paid') {
    flash('orders', 'Order #' . $order['order_number'] . ' is already paid in full.', 'info');
    redirect(url_for('order-details.php?id=' . $orderId));
}

if ($order['status'] === 'cancelled') {
    flash('orders', 'This order has been cancelled and cannot be paid.', 'error');
    redirect(url_for('order-details.php?id=' . $orderId));
}

// Fetch shipping address
$addrStmt = $pdo->prepare('SELECT * FROM addresses WHERE id = ? LIMIT 1');
$addrStmt->execute([(int)$order['address_id']]);
$address = $addrStmt->fetch() ?: [
    'recipient_name' => current_user()['name'] ?? 'Customer',
    'phone'          => current_user()['phone'] ?? '01700000000',
    'address_line1'  => 'Dhaka, Bangladesh',
    'city'           => 'Dhaka',
    'postal_code'    => '1000'
];

$errorMessage = null;

// Handle Payment Submission
if (method_is('post')) {
    verify_csrf_or_fail();
    $chosenMethod = input('payment_method', 'sslcommerz');

    if ($chosenMethod === 'cod') {
        // Switch to Cash on Delivery
        $pdo->prepare('UPDATE orders SET payment_method = \'cod\', updated_at = NOW() WHERE id = ?')
            ->execute([$orderId]);

        flash('orders', 'Payment method updated to Cash on Delivery for Order #' . $order['order_number'] . '.', 'success');
        redirect(url_for('order-details.php?id=' . $orderId));
    }

    // Initiate fresh SSLCOMMERZ gateway session
    $paymentService = new PaymentService($pdo);
    $shippingPayload = [
        'name'     => (string)$address['recipient_name'],
        'phone'    => (string)$address['phone'],
        'address1' => (string)$address['address_line1'],
        'city'     => (string)$address['city'],
        'postal'   => (string)($address['postal_code'] ?? '1000')
    ];

    $res = $paymentService->initiateOnlinePayment($orderId, $userId, $shippingPayload, $chosenMethod);

    if ($res['success'] && !empty($res['gateway_url'])) {
        header('Location: ' . $res['gateway_url']);
        exit;
    }

    $errorMessage = $res['error'] ?? 'Unable to establish connection with SSLCOMMERZ gateway. Please try again.';
}

$pageTitle = 'Complete Payment — Order #' . $order['order_number'];
$extraStylesheets = ['css/account.css', 'css/cart.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container" style="max-width: 650px; margin: 50px auto;">
    <div class="card" style="background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); padding: 30px; border: 1px solid var(--color-border);">
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="font-size: 40px; color: var(--color-primary); margin-bottom: 10px;">
                <i class="fas fa-credit-card"></i>
            </div>
            <h1 style="font-size: 22px; font-weight: 700; margin: 0 0 6px 0;">Complete Your Order Payment</h1>
            <p style="color: var(--color-text-muted); font-size: 14px; margin: 0;">Order Reference: <strong>#<?= e($order['order_number']) ?></strong></p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px; font-size: 14px;">
                <i class="fas fa-circle-exclamation"></i> <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['notice'])): ?>
            <div class="alert alert-warning" style="margin-bottom: 20px; font-size: 14px;">
                <i class="fas fa-triangle-exclamation"></i> <?= e($_GET['notice']) ?>
            </div>
        <?php endif; ?>

        <!-- Order Summary Box -->
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 18px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                <span>Subtotal:</span>
                <span><?= format_price((float)$order['subtotal']) ?></span>
            </div>
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: var(--color-danger);">
                    <span>Discount:</span>
                    <span>-<?= format_price((float)$order['discount_amount']) ?></span>
                </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px;">
                <span>Delivery Charge:</span>
                <span><?= format_price((float)$order['delivery_charge']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 800; border-top: 1px dashed #ced4da; padding-top: 10px; color: var(--color-primary);">
                <span>Total Due:</span>
                <span><?= format_price((float)$order['total_amount']) ?></span>
            </div>
        </div>

        <!-- Payment Selection Form -->
        <form method="post" action="<?= url_for('order-pay.php?order_id=' . $orderId) ?>">
            <?= csrf_field() ?>

            <label style="display: block; font-weight: 700; font-size: 14px; margin-bottom: 12px;">Choose Payment Option</label>
            
            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 25px;">
                
                <!-- SSLCOMMERZ Online Gateway -->
                <label style="display: flex; align-items: center; gap: 14px; border: 2px solid var(--color-primary); background: rgba(92,124,250,0.03); border-radius: 10px; padding: 15px; cursor: pointer;">
                    <input type="radio" name="payment_method" value="sslcommerz" checked style="transform: scale(1.2);">
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 15px; color: var(--color-text);">SSLCOMMERZ Secure Gateway</div>
                        <div style="font-size: 12px; color: var(--color-text-muted);">Cards (Visa, Mastercard, AMEX), bKash, Nagad, Rocket, Net Banking</div>
                    </div>
                    <div style="font-size: 20px; color: var(--color-primary);"><i class="fas fa-shield-halved"></i></div>
                </label>

                <!-- Cash on Delivery -->
                <label style="display: flex; align-items: center; gap: 14px; border: 1px solid var(--color-border); border-radius: 10px; padding: 15px; cursor: pointer;">
                    <input type="radio" name="payment_method" value="cod" style="transform: scale(1.2);">
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 15px; color: var(--color-text);">Cash on Delivery</div>
                        <div style="font-size: 12px; color: var(--color-text-muted);">Switch order to Cash on Delivery. Pay upon item arrival.</div>
                    </div>
                    <div style="font-size: 20px; color: var(--color-success);"><i class="fas fa-money-bill-wave"></i></div>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 15px; font-weight: 700; border-radius: 8px; border: none; cursor: pointer;">
                <i class="fas fa-lock"></i> Proceed to Pay ৳<?= number_format((float)$order['total_amount'], 2) ?>
            </button>
            <div style="text-align: center; margin-top: 15px;">
                <a href="<?= url_for('order-details.php?id=' . $orderId) ?>" style="font-size: 13px; color: var(--color-text-muted); text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Cancel and Return to Invoice
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
