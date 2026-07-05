<?php
/**
 * ==========================================================================
 * public/api/cart.php
 * ==========================================================================
 * Fetch current cart totals and pre-rendered Mini-Cart HTML.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

try {
    $pdo = db();
    $cartId = current_cart_id();

    // Query cart items with product details
    $stmt = $pdo->prepare('
        SELECT ci.id AS cart_item_id, ci.product_id, ci.quantity, ci.price,
               p.name, p.slug, p.thumbnail, p.stock, p.unit
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = :cart_id
        ORDER BY ci.created_at DESC
    ');
    $stmt->execute(['cart_id' => $cartId]);
    $cartItems = $stmt->fetchAll();

    // Calculate totals
    $subtotal = 0.0;
    $itemCount = 0;
    foreach ($cartItems as $item) {
        $subtotal += ((float) $item['price']) * ((int) $item['quantity']);
        $itemCount += (int) $item['quantity'];
    }

    // Coupon Calculations
    $discountAmount = 0.0;
    $couponCode = $_SESSION['applied_coupon'] ?? null;
    $couponDetails = null;

    if ($couponCode !== null) {
        // Query coupon details
        $cpStmt = $pdo->prepare('
            SELECT * FROM coupons 
            WHERE code = :code AND is_active = 1 AND valid_until >= NOW() AND valid_from <= NOW()
            LIMIT 1
        ');
        $cpStmt->execute(['code' => $couponCode]);
        $coupon = $cpStmt->fetch();

        if ($coupon) {
            $minOrder = (float) $coupon['min_order_amount'];
            
            // Check if usage limits are met
            $usageLimit = $coupon['usage_limit'] !== null ? (int) $coupon['usage_limit'] : null;
            $timesUsed = (int) $coupon['times_used'];
            $limitOk = ($usageLimit === null || $timesUsed < $usageLimit);

            if ($subtotal >= $minOrder && $limitOk) {
                $pct = (float) $coupon['discount_percent'];
                $discountAmount = $subtotal * ($pct / 100.0);
                
                // Cap discount if max_discount_amount exists
                $maxDisc = $coupon['max_discount_amount'] !== null ? (float) $coupon['max_discount_amount'] : null;
                if ($maxDisc !== null && $discountAmount > $maxDisc) {
                    $discountAmount = $maxDisc;
                }
                
                $couponDetails = [
                    'code' => $coupon['code'],
                    'discount_percent' => $pct,
                    'discount_amount' => $discountAmount
                ];
            } else {
                // Remove invalid coupon
                unset($_SESSION['applied_coupon']);
            }
        } else {
            unset($_SESSION['applied_coupon']);
        }
    }

    // Delivery charge (from settings table, fallback to 50 BDT)
    $deliveryCharge = 0.0;
    if ($subtotal > 0) {
        $deliveryCharge = default_delivery_charge();
        // Free delivery on orders over 1000 BDT
        if ($subtotal >= 1000.0) {
            $deliveryCharge = 0.0;
        }
    }

    // Tax/VAT: Let's assume a standard 5% VAT on subtotal minus discount
    $vatAmount = 0.0;
    if ($subtotal > 0) {
        $taxableAmount = max(0.0, $subtotal - $discountAmount);
        $vatAmount = $taxableAmount * 0.05; // 5% VAT
    }

    // Grand Total
    $grandTotal = max(0.0, ($subtotal - $discountAmount) + $deliveryCharge + $vatAmount);

    // Capture the pre-rendered Mini Cart HTML
    ob_start();
    include PUBLIC_PATH . '/components/mini-cart.php';
    $html = ob_get_clean();

    json_response(true, 'Cart data retrieved.', [
        'cart_count'      => $itemCount,
        'subtotal'        => $subtotal,
        'discount_amount' => $discountAmount,
        'delivery_charge' => $deliveryCharge,
        'vat_amount'      => $vatAmount,
        'grand_total'     => $grandTotal,
        'coupon'          => $couponDetails,
        'html'            => $html
    ]);

} catch (PDOException $e) {
    error_log('[api/cart.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred while fetching cart data.', [], 500);
}
