<?php
/**
 * ==========================================================================
 * public/thank-you.php — Order Success Page
 * ==========================================================================
 * Displays order summary, invoice details, and temporary password info for
 * seamless guest signups. Clears last order session details after load.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$lastOrder = $_SESSION['last_order'] ?? null;

// Redirect to catalog if page is visited directly without an active checkout session
if ($lastOrder === null) {
    redirect(url_for('products.php'));
}

$orderNumber = $lastOrder['number'] ?? '';
$grandTotal = (float) ($lastOrder['grand_total'] ?? 0.0);
$email = $lastOrder['email'] ?? '';

$tempPass = $_SESSION['guest_temp_pass'] ?? null;

// Configure metadata
$pageTitle = 'Order Success — ' . $orderNumber . ' — ' . site_name();
$pageDescription = 'Thank you for your order! Your grocery order has been successfully received.';

$extraStylesheets = ['css/cart.css', 'css/checkout.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <div class="cart-empty-page" style="margin-top: var(--space-5); margin-bottom: var(--space-7); padding: var(--space-6) var(--space-5);">
        
        <div class="cart-empty-icon" style="color: var(--color-primary);">
            <i class="fas fa-circle-check"></i>
        </div>
        
        <h2 style="font-size: var(--fs-xl); font-weight: 800; color: var(--color-text); margin-bottom: var(--space-2);">Thank You For Your Order!</h2>
        <p style="font-size: var(--fs-base); color: var(--color-text-muted); max-width: 500px; margin: 0 auto var(--space-5) auto;">
            Your order has been successfully placed. We are preparing your fresh groceries for delivery!
        </p>

        <!-- Order Summary Detail Box -->
        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); max-width: 480px; margin: 0 auto var(--space-5) auto; padding: var(--space-4); text-align: left;">
            <h3 style="font-size: var(--fs-sm); font-weight: 800; margin-top: 0; margin-bottom: var(--space-3); border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-2); color: var(--color-text);">Order Details</h3>
            
            <div style="display: flex; justify-content: space-between; font-size: var(--fs-xs); margin-bottom: var(--space-2);">
                <span style="color: var(--color-text-muted);">Order Reference:</span>
                <strong style="color: var(--color-text);"><?= e($orderNumber) ?></strong>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: var(--fs-xs); margin-bottom: var(--space-2);">
                <span style="color: var(--color-text-muted);">Payment Method:</span>
                <strong style="color: var(--color-text);">Cash on Delivery (COD)</strong>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: var(--fs-xs); margin-bottom: var(--space-2);">
                <span style="color: var(--color-text-muted);">Order Status:</span>
                <strong style="color: var(--color-primary); text-transform: uppercase;">Pending Approval</strong>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: var(--fs-sm); margin-top: var(--space-3); border-top: 1px dashed var(--color-border); padding-top: var(--space-2);">
                <span style="font-weight: 700; color: var(--color-text);">Amount Charged:</span>
                <strong style="font-weight: 800; color: var(--color-primary); font-size: var(--fs-base);"><?= format_price($grandTotal) ?></strong>
            </div>
        </div>

        <!-- Seamless Guest SignUp Account details -->
        <?php if ($tempPass !== null): ?>
            <div style="background: #e6f6ec; border: 1px dashed var(--color-success); border-radius: var(--radius-md); max-width: 480px; margin: 0 auto var(--space-5) auto; padding: var(--space-4); text-align: left;">
                <h3 style="font-size: var(--fs-sm); font-weight: 800; margin-top: 0; color: var(--color-primary-dark); margin-bottom: var(--space-2); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-lock"></i> Account Created Automatically!
                </h3>
                <p style="font-size: var(--fs-xs); color: var(--color-text-muted); line-height: 1.4; margin-bottom: var(--space-3);">
                    We have registered an account under your email so you can track this delivery and view your order history. Write down your temporary sign-in credentials below:
                </p>
                
                <div style="display: flex; flex-direction: column; gap: var(--space-1); font-size: var(--fs-xs); background: var(--color-surface); padding: var(--space-3); border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                    <div>Username/Email: <strong><?= e($email) ?></strong></div>
                    <div>Temporary Password: <strong style="color: var(--color-danger); letter-spacing: 0.5px;"><?= e($tempPass) ?></strong></div>
                </div>
                
                <p style="font-size: 10px; color: var(--color-text-faint); margin-top: var(--space-2); margin-bottom: 0;">
                    * You can modify your password and update profiles inside your Account Panel.
                </p>
            </div>
        <?php endif; ?>

        <!-- CTAs -->
        <div style="display: flex; gap: var(--space-3); justify-content: center; flex-wrap: wrap;">
            <a href="<?= url_for('products.php') ?>" class="btn btn-secondary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 10px 24px;">Continue Shopping</a>
            <a href="<?= url_for('orders.php') ?>" class="btn btn-primary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 10px 28px;">Track My Order</a>
        </div>

    </div>
</div>

<?php
// Clean up order success states so refreshing doesn't keep displaying them
unset($_SESSION['last_order']);
unset($_SESSION['guest_temp_pass']);

require_once __DIR__ . '/footer.php';
?>
