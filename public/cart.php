<?php
/**
 * ==========================================================================
 * public/cart.php — Storefront Shopping Cart Page
 * ==========================================================================
 * Displays list of items in the cart, lets users increase/decrease
 * quantities, remove items, apply valid coupons, and proceed to checkout.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pdo = db();
$cartId = current_cart_id();

// --------------------------------------------------------------------------
// 1. AJAX Coupon Operations (POST requests via apiPost)
// --------------------------------------------------------------------------
if (method_is('post')) {
    // Verify CSRF
    verify_csrf_or_fail(true);

    // ---- Apply Coupon ----
    if (isset($_POST['apply_coupon'])) {
        $code = trim(input('coupon_code', ''));

        if (empty($code)) {
            json_response(false, 'Please enter a coupon code.', [], 400);
        }

        // Look up coupon
        $cpStmt = $pdo->prepare('
            SELECT * FROM coupons 
            WHERE code = :code AND is_active = 1 AND valid_until >= NOW() AND valid_from <= NOW()
            LIMIT 1
        ');
        $cpStmt->execute(['code' => $code]);
        $coupon = $cpStmt->fetch();

        if (!$coupon) {
            json_response(false, 'Invalid or expired coupon code.', [], 422);
        }

        // Calculate current subtotal to check min order constraint
        $subtotalQuery = $pdo->prepare('SELECT SUM(quantity * price) FROM cart_items WHERE cart_id = :cart_id');
        $subtotalQuery->execute(['cart_id' => $cartId]);
        $subtotal = (float) $subtotalQuery->fetchColumn();

        if ($subtotal <= 0) {
            json_response(false, 'Your cart is empty.', [], 422);
        }

        $minOrder = (float) $coupon['min_order_amount'];
        if ($subtotal < $minOrder) {
            json_response(false, sprintf('Minimum order of %s is required to apply this coupon.', format_price($minOrder)), [], 422);
        }

        // Check usage limit
        $usageLimit = $coupon['usage_limit'] !== null ? (int) $coupon['usage_limit'] : null;
        $timesUsed = (int) $coupon['times_used'];
        if ($usageLimit !== null && $timesUsed >= $usageLimit) {
            json_response(false, 'This coupon has reached its maximum usage limit.', [], 422);
        }

        // Save coupon code in session
        $_SESSION['applied_coupon'] = $coupon['code'];

        json_response(true, 'Coupon applied successfully!', [
            'code' => $coupon['code'],
            'discount_percent' => (float) $coupon['discount_percent']
        ]);
    }

    // ---- Remove Coupon ----
    if (isset($_POST['remove_coupon'])) {
        unset($_SESSION['applied_coupon']);
        json_response(true, 'Coupon removed successfully.');
    }
}

// --------------------------------------------------------------------------
// 2. Fetch Cart Items & Compute Totals for SSR Page Load
// --------------------------------------------------------------------------
try {
    $stmt = $pdo->prepare('
        SELECT ci.id AS cart_item_id, ci.product_id, ci.quantity, ci.price,
               p.name, p.slug, p.thumbnail, p.stock, p.unit,
               c.name AS category_name, b.name AS brand_name
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE ci.cart_id = :cart_id
        ORDER BY ci.created_at DESC
    ');
    $stmt->execute(['cart_id' => $cartId]);
    $cartItems = $stmt->fetchAll();

    // Sum quantities & subtotal
    $subtotal = 0.0;
    $totalCount = 0;
    foreach ($cartItems as $item) {
        $subtotal += ((float) $item['price']) * ((int) $item['quantity']);
        $totalCount += (int) $item['quantity'];
    }

    // Validate applied coupon against DB details
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

            // Cap if max limit exists
            $maxDisc = $coupon['max_discount_amount'] !== null ? (float) $coupon['max_discount_amount'] : null;
            if ($maxDisc !== null && $discountAmount > $maxDisc) {
                $discountAmount = $maxDisc;
            }

            $couponData = $coupon;
        } else {
            // Remove invalid coupon
            unset($_SESSION['applied_coupon']);
            $couponCode = null;
        }
    }

    // Delivery charge calculation
    $deliveryCharge = 0.0;
    if ($subtotal > 0) {
        $deliveryCharge = default_delivery_charge();
        // Free delivery on orders over 1000 BDT
        if ($subtotal >= 1000.0) {
            $deliveryCharge = 0.0;
        }
    }

    // VAT (5% VAT)
    $vatAmount = 0.0;
    if ($subtotal > 0) {
        $taxable = max(0.0, $subtotal - $discountAmount);
        $vatAmount = $taxable * 0.05;
    }

    // Grand total
    $grandTotal = max(0.0, ($subtotal - $discountAmount) + $deliveryCharge + $vatAmount);

} catch (PDOException $e) {
    error_log('[cart.php] Error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Cart Database Error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Page layout meta
$pageTitle = 'Shopping Cart — ' . site_name();
$pageDescription = 'Review items in your grocery basket, apply discount coupons, and proceed to secure checkout.';

$extraStylesheets = ['css/cart.css'];
$extraScripts = [
    'js/cart.js',
    'js/quickview.js'
];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'Shopping Cart']
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container">
    
    <!-- Empty Cart State -->
    <div class="cart-empty-page" id="cartPageEmpty" style="display: <?= empty($cartItems) ? 'block' : 'none' ?>;">
        <div class="cart-empty-icon"><i class="fas fa-cart-shopping"></i></div>
        <h2>Your Cart is Empty</h2>
        <p>You have no products inside your shopping cart. Browse our categories and grab some fresh groceries!</p>
        <a href="<?= url_for('products.php') ?>" class="btn btn-primary">Start Shopping</a>
    </div>

    <!-- Active Cart Content -->
    <div class="cart-page-layout" id="cartPageContent" style="display: <?= empty($cartItems) ? 'none' : 'grid' ?>;">
        
        <!-- Left: Cart Items Table List -->
        <main class="cart-table-card">
            <h2 class="summary-title" style="margin-top:0; border-bottom:none; padding-bottom:0;">Shopping Basket</h2>
            <div class="cart-items-list">
                <?php if (!empty($cartItems)): ?>
                    <?php foreach ($cartItems as $item): 
                        $price = (float) $item['price'];
                        $qty = (int) $item['quantity'];
                        $stock = (int) $item['stock'];
                        $imageUrl = image_url($item['thumbnail'], 'products');
                        $productUrl = url_for('product.php?slug=' . e($item['slug']));
                    ?>
                        <div class="cart-item-row" data-price="<?= $price ?>" data-product-id="<?= $item['product_id'] ?>">
                            <!-- Image -->
                            <div class="cart-item-img">
                                <a href="<?= $productUrl ?>">
                                    <img src="<?= e($imageUrl) ?>" alt="<?= e($item['name']) ?>">
                                </a>
                            </div>

                            <!-- Title & Metadata -->
                            <div class="cart-item-details">
                                <h3 class="cart-item-name"><a href="<?= $productUrl ?>"><?= e($item['name']) ?></a></h3>
                                <div class="cart-item-meta">
                                    Category: <?= e($item['category_name'] ?? 'Other') ?> 
                                    <?php if ($item['brand_name']): ?>
                                        | Brand: <span><?= e($item['brand_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Quantity Adjuster -->
                            <div class="cart-item-qty">
                                <div class="cart-qty-adjuster">
                                    <button type="button" class="cart-qty-btn cart-qty-minus" data-product-id="<?= (int) $item['product_id'] ?>" aria-label="Decrease Quantity"><i class="fas fa-minus"></i></button>
                                    <input type="number" class="cart-qty-input" value="<?= $qty ?>" min="1" max="<?= $stock ?>" data-original="<?= $qty ?>" readonly>
                                    <button type="button" class="cart-qty-btn cart-qty-plus" data-product-id="<?= (int) $item['product_id'] ?>" aria-label="Increase Quantity"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Totals -->
                            <div class="cart-item-totals">
                                <span class="cart-item-line-total"><?= format_price($price * $qty) ?></span>
                                <span class="cart-item-price-unit"><?= format_price($price) ?> / <?= e($item['unit']) ?></span>
                            </div>

                            <!-- Remove Trigger -->
                            <div class="cart-item-remove">
                                <button type="button" class="mini-cart-remove-btn btn-remove-cart-item" data-product-id="<?= (int) $item['product_id'] ?>" title="Remove item" aria-label="Remove Item">
                                    <i class="far fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Actions block -->
            <div style="display:flex; justify-content:space-between; margin-top:var(--space-4);">
                <a href="<?= url_for('products.php') ?>" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-size:12px; font-weight:700;"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
            </div>
        </main>

        <!-- Right: Totals Summary Box -->
        <aside class="summary-panel">
            <h3 class="summary-title">Order Summary</h3>
            
            <div class="summary-row">
                <span>Subtotal:</span>
                <strong id="cartPageSubtotal"><?= format_price($subtotal) ?></strong>
            </div>

            <!-- Discount Row -->
            <div class="summary-row discount-row" id="cartPageDiscountRow" style="display: <?= $discountAmount > 0 ? 'flex' : 'none' ?>;">
                <span>Coupon Discount:</span>
                <strong id="cartPageDiscount">-<?= format_price($discountAmount) ?></strong>
            </div>

            <div class="summary-row">
                <span>Delivery Charge:</span>
                <strong id="cartPageDelivery"><?= format_price($deliveryCharge) ?></strong>
            </div>

            <div class="summary-row">
                <span>Est. Tax / VAT (5%):</span>
                <strong id="cartPageVat"><?= format_price($vatAmount) ?></strong>
            </div>

            <div class="summary-row total-row">
                <span>Total:</span>
                <strong class="grand-total-val" id="cartPageTotal"><?= format_price($grandTotal) ?></strong>
            </div>

            <!-- Coupon application box -->
            <div class="coupon-box">
                <label for="couponCodeInput" class="sort-label">Promo / Coupon Code</label>
                
                <?php if ($couponCode !== null && $couponData): ?>
                    <!-- Applied Coupon Info Badge -->
                    <div class="coupon-badge-success">
                        <span><i class="fas fa-tag"></i> Code: <strong><?= e($couponCode) ?></strong> (<?= (float)$couponData['discount_percent'] ?>% off)</span>
                        <button type="button" class="btn-coupon-remove" id="btnRemoveCoupon" title="Remove Coupon" aria-label="Remove Coupon"><i class="fas fa-trash"></i></button>
                    </div>
                <?php else: ?>
                    <form id="couponForm">
                        <div class="coupon-form-group">
                            <input type="text" id="couponCodeInput" class="coupon-input" placeholder="e.g. WELCOME20" required>
                            <button type="submit" class="btn btn-primary btn-coupon">Apply</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <a href="<?= url_for('checkout.php') ?>" class="btn btn-primary btn-checkout-proceed">Proceed to Checkout <i class="fas fa-arrow-right"></i></a>
        </aside>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
