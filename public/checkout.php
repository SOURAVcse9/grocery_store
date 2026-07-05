<?php
/**
 * ==========================================================================
 * public/checkout.php — Checkout & Order Review Page
 * ==========================================================================
 * Allows logged-in users and guests to fill in delivery addresses, select
 * payment methods, review their items, and place their order.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pdo = db();
$cartId = current_cart_id();

try {
    // 1. Fetch Cart Items to verify cart is not empty
    $stmt = $pdo->prepare('
        SELECT ci.product_id, ci.quantity, ci.price,
               p.name, p.slug, p.thumbnail, p.stock, p.unit
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = :cart_id
    ');
    $stmt->execute(['cart_id' => $cartId]);
    $cartItems = $stmt->fetchAll();

    if (empty($cartItems)) {
        flash('cart', 'Your cart is empty. Add items to checkout.', 'error');
        redirect(url_for('cart.php'));
    }

    // 2. Calculate Totals (subtotal, coupon, delivery, VAT, grand total)
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $subtotal += ((float) $item['price']) * ((int) $item['quantity']);
    }

    $discountAmount = 0.0;
    $couponCode = $_SESSION['applied_coupon'] ?? null;
    $couponData = null;

    if ($couponCode !== null) {
        $cpStmt = $pdo->prepare('
            SELECT * FROM coupons 
            WHERE code = :code AND is_active = 1 AND valid_until >= NOW() AND valid_from <= NOW()
            LIMIT 1
        ');
        $cpStmt->execute(['code' => $couponCode]);
        $coupon = $cpStmt->fetch();

        if ($coupon && $subtotal >= (float) $coupon['min_order_amount']) {
            $pct = (float) $coupon['discount_percent'];
            $discountAmount = $subtotal * ($pct / 100.0);
            
            $maxDisc = $coupon['max_discount_amount'] !== null ? (float) $coupon['max_discount_amount'] : null;
            if ($maxDisc !== null && $discountAmount > $maxDisc) {
                $discountAmount = $maxDisc;
            }
            $couponData = $coupon;
        } else {
            unset($_SESSION['applied_coupon']);
            $couponCode = null;
        }
    }

    $deliveryCharge = default_delivery_charge();
    if ($subtotal >= 1000.0) {
        $deliveryCharge = 0.0;
    }

    $vatAmount = max(0.0, $subtotal - $discountAmount) * 0.05; // 5% VAT
    $grandTotal = max(0.0, ($subtotal - $discountAmount) + $deliveryCharge + $vatAmount);

    // 3. If user is logged in, fetch saved addresses
    $savedAddresses = [];
    if (is_logged_in()) {
        $addrStmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC');
        $addrStmt->execute(['uid' => current_user_id()]);
        $savedAddresses = $addrStmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log('[checkout.php] Error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Checkout Database Error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Layout configuration
$pageTitle = 'Secure Checkout — ' . site_name();
$pageDescription = 'Finalize your grocery details, select payment, and place your order securely.';

$extraStylesheets = ['css/cart.css', 'css/checkout.css'];
$extraScripts = [
    'js/checkout.js'
];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'Shopping Cart', 'link' => 'cart.php'],
    ['title' => 'Checkout']
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container">
    
    <form id="checkoutForm" method="post" action="<?= url_for('process_checkout.php') ?>">
        <?= csrf_field() ?>
        
        <div class="checkout-layout">
            
            <!-- Left Panel: Address Forms & Payments -->
            <main class="checkout-main-panel">
                
                <!-- Billing/Shipping Section -->
                <section class="checkout-card">
                    <h2 class="checkout-card-title"><i class="fas fa-truck-ramp-box"></i> Delivery Information</h2>
                    
                    <?php if (is_logged_in() && !empty($savedAddresses)): ?>
                        <!-- Saved Address List Selector -->
                        <div class="address-selector-group">
                            <label class="sort-label">Select Shipping Address:</label>
                            <div class="saved-addresses-grid">
                                <?php foreach ($savedAddresses as $addr): ?>
                                    <label class="address-card-option <?= $addr['is_default'] ? 'selected' : '' ?>">
                                        <input type="radio" name="address_id" value="<?= $addr['id'] ?>" <?= $addr['is_default'] ? 'checked' : '' ?>>
                                        <div class="address-card-content">
                                            <div class="address-label-badge"><?= e($addr['label']) ?></div>
                                            <div class="address-recipient"><strong><?= e($addr['recipient_name']) ?></strong></div>
                                            <div class="address-phone"><?= e($addr['phone']) ?></div>
                                            <div class="address-text">
                                                <?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? ', ' . e($addr['address_line2']) : '' ?><br>
                                                <?= e($addr['city']) ?><?= $addr['state'] ? ', ' . e($addr['state']) : '' ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                                
                                <label class="address-card-option add-new-card">
                                    <input type="radio" name="address_id" value="new">
                                    <div class="address-card-content">
                                        <div class="add-new-icon"><i class="fas fa-plus"></i></div>
                                        <div class="add-new-text">Use A New Address</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- New Address Form (Fades in if guest, or if "new" radio is checked) -->
                    <div class="new-address-form-box" id="newAddressFormBox" style="display: <?= (is_logged_in() && !empty($savedAddresses)) ? 'none' : 'block' ?>;">
                        <h3 class="checkout-sub-title" style="margin-top:0;">Recipient &amp; Shipping Details</h3>
                        
                        <div class="checkout-form-grid">
                            <div class="form-field-group">
                                <label for="recipient_name">Full Name *</label>
                                <input type="text" id="recipient_name" name="recipient_name" placeholder="e.g. Karim Rahman" <?= (is_logged_in() && !empty($savedAddresses)) ? '' : 'required' ?>>
                            </div>
                            
                            <div class="form-field-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" placeholder="e.g. 01700000000" <?= (is_logged_in() && !empty($savedAddresses)) ? '' : 'required' ?>>
                            </div>

                            <?php if (!is_logged_in()): ?>
                                <div class="form-field-group col-span-2">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" placeholder="e.g. customer@gmail.com" required>
                                    <span class="field-help-text">An account will be automatically created for you to track orders.</span>
                                </div>
                            <?php endif; ?>

                            <div class="form-field-group col-span-2">
                                <label for="address_line1">Street Address *</label>
                                <input type="text" id="address_line1" name="address_line1" placeholder="House #, Road #, Flat / Apartment / Area" <?= (is_logged_in() && !empty($savedAddresses)) ? '' : 'required' ?>>
                            </div>

                            <div class="form-field-group">
                                <label for="city">City / District *</label>
                                <input type="text" id="city" name="city" placeholder="e.g. Dhaka" <?= (is_logged_in() && !empty($savedAddresses)) ? '' : 'required' ?>>
                            </div>

                            <div class="form-field-group">
                                <label for="postal_code">Postal / Zip Code</label>
                                <input type="text" id="postal_code" name="postal_code" placeholder="e.g. 1207">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Payment Selection Section -->
                <section class="checkout-card">
                    <h2 class="checkout-card-title"><i class="fas fa-credit-card"></i> Payment Method</h2>
                    <div class="payment-methods-grid">
                        
                        <!-- COD Card -->
                        <label class="payment-card selected">
                            <input type="radio" name="payment_method" value="cod" checked>
                            <div class="payment-card-content">
                                <div class="payment-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="payment-name">Cash on Delivery</div>
                                <div class="payment-desc">Pay cash when groceries arrive.</div>
                            </div>
                        </label>

                        <!-- Card payment placeholder -->
                        <label class="payment-card disabled" title="Card payments are coming soon.">
                            <input type="radio" name="payment_method" value="card" disabled>
                            <div class="payment-card-content">
                                <div class="payment-icon"><i class="fab fa-cc-visa"></i></div>
                                <div class="payment-name">Online Payment</div>
                                <div class="payment-desc">Visa / Mastercard (coming soon).</div>
                            </div>
                        </label>

                        <!-- Mobile banking placeholder -->
                        <label class="payment-card disabled" title="Mobile banking is coming soon.">
                            <input type="radio" name="payment_method" value="mobile_banking" disabled>
                            <div class="payment-card-content">
                                <div class="payment-icon"><i class="fas fa-mobile-screen-button"></i></div>
                                <div class="payment-name">Mobile Banking</div>
                                <div class="payment-desc">bKash / Nagad (coming soon).</div>
                            </div>
                        </label>

                    </div>
                </section>

                <!-- Additional Notes Section -->
                <section class="checkout-card">
                    <h2 class="checkout-card-title"><i class="far fa-note-sticky"></i> Delivery Instructions (Optional)</h2>
                    <div class="form-field-group col-span-2">
                        <textarea name="note" class="review-form-textarea" style="margin-bottom:0;" placeholder="e.g. Leave it at the gate reception, call before arriving..." aria-label="Delivery Note"></textarea>
                    </div>
                </section>

            </main>

            <!-- Right Panel: Review Items & Place Order -->
            <aside class="summary-panel">
                <h3 class="summary-title" style="margin-bottom: var(--space-3);">Review Items</h3>
                
                <!-- Basket items preview -->
                <div class="checkout-items-preview">
                    <?php foreach ($cartItems as $item): 
                        $price = (float) $item['price'];
                        $qty = (int) $item['quantity'];
                        $img = image_url($item['thumbnail'], 'products');
                    ?>
                        <div class="checkout-item-preview-row">
                            <div class="checkout-item-preview-img">
                                <img src="<?= e($img) ?>" alt="<?= e($item['name']) ?>">
                            </div>
                            <div class="checkout-item-preview-details">
                                <div class="checkout-item-preview-name"><?= e($item['name']) ?></div>
                                <div class="checkout-item-preview-meta">Qty: <?= $qty ?> &times; <?= format_price($price) ?></div>
                            </div>
                            <div class="checkout-item-preview-total"><?= format_price($price * $qty) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-row" style="border-top: 1px solid var(--color-border); padding-top: var(--space-3);">
                    <span>Subtotal:</span>
                    <strong><?= format_price($subtotal) ?></strong>
                </div>

                <?php if ($discountAmount > 0): ?>
                    <div class="summary-row discount-row">
                        <span>Coupon Applied (<?= e($couponCode) ?>):</span>
                        <strong>-<?= format_price($discountAmount) ?></strong>
                    </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span>Delivery Charge:</span>
                    <strong><?= format_price($deliveryCharge) ?></strong>
                </div>

                <div class="summary-row">
                    <span>VAT (5%):</span>
                    <strong><?= format_price($vatAmount) ?></strong>
                </div>

                <div class="summary-row total-row">
                    <span>Grand Total:</span>
                    <strong class="grand-total-val"><?= format_price($grandTotal) ?></strong>
                </div>

                <button type="submit" class="btn btn-primary btn-checkout-proceed" id="btnPlaceOrder" style="margin-top: var(--space-4);">Place Order <i class="fas fa-check-double"></i></button>
            </aside>

        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
