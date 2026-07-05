<?php
/**
 * ==========================================================================
 * public/process_checkout.php — Checkout Form Processor
 * ==========================================================================
 * Verifies billing and shipping addresses, enforces stock locks,
 * registers guests seamlessly, registers order transactions, decrements
 * product stock, and directs to the confirmation page.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

$pdo = db();
$cartId = current_cart_id();

// Read inputs
$addressOption = input('address_id', 'new');
$paymentMethod = input('payment_method', 'cod');
$note = trim(input('note', ''));

// Validate Payment Method
if (!in_array($paymentMethod, ['cod', 'card', 'bkash', 'nagad', 'rocket', 'sslcommerz'], true)) {
    json_response(false, 'Invalid payment method selected.', [], 400);
}

// --------------------------------------------------------------------------
// 1. Initial Validation: Address Info
// --------------------------------------------------------------------------
$addressId = null;
$recipientName = '';
$phone = '';
$addressLine1 = '';
$city = '';
$postalCode = '';

if (is_logged_in() && $addressOption !== 'new') {
    // Validate saved address ownership
    $addressId = (int) $addressOption;
    $addrStmt = $pdo->prepare('SELECT id FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1');
    $addrStmt->execute(['id' => $addressId, 'uid' => current_user_id()]);
    if (!$addrStmt->fetch()) {
        json_response(false, 'Selected shipping address is invalid.', [], 400);
    }
} else {
    // Validate new address inputs
    $recipientName = trim(input('recipient_name', ''));
    $phone = trim(input('phone', ''));
    $addressLine1 = trim(input('address_line1', ''));
    $city = trim(input('city', ''));
    $postalCode = trim(input('postal_code', ''));

    $v = new Validator();
    $v->required('recipient_name', $recipientName, 'Recipient name is required.')
      ->length('recipient_name', $recipientName, 2, 100, 'Name must be between 2 and 100 characters.')
      ->required('phone', $phone, 'Phone number is required.')
      ->phone('phone', $phone)
      ->required('address_line1', $addressLine1, 'Street address is required.')
      ->length('address_line1', $addressLine1, 5, 255, 'Street address must be between 5 and 255 characters.')
      ->required('city', $city, 'City/District is required.')
      ->length('city', $city, 2, 100, 'City name must be between 2 and 100 characters.');

    // If guest, validate email address for registration
    $email = '';
    if (!is_logged_in()) {
        $email = trim(input('email', ''));
        $v->required('email', $email, 'Email address is required.')
          ->email('email', $email);
    }

    if ($v->hasErrors()) {
        json_response(false, $v->first() ?? 'Validation failed.', [], 422);
    }
}

// --------------------------------------------------------------------------
// 2. Transaction-safe Ordering Flow
// --------------------------------------------------------------------------
try {
    $pdo->beginTransaction();

    // ---- A. Fetch & Validate Cart Items ----
    $cartStmt = $pdo->prepare('
        SELECT ci.product_id, ci.quantity, ci.price,
               p.name, p.sku, p.stock, p.is_active
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = :cart_id
    ');
    $cartStmt->execute(['cart_id' => $cartId]);
    $cartItems = $cartStmt->fetchAll();

    if (empty($cartItems)) {
        $pdo->rollBack();
        json_response(false, 'Your cart is empty. Add items to checkout.', [], 422);
    }

    // ---- B. Enforce Stock Locks & Availability ----
    foreach ($cartItems as $item) {
        if ((int) $item['is_active'] === 0) {
            $pdo->rollBack();
            json_response(false, sprintf('Order failed: "%s" is no longer available.', $item['name']), [], 422);
        }

        $stockAvailable = (int) $item['stock'];
        $qtyRequested = (int) $item['quantity'];

        if ($qtyRequested > $stockAvailable) {
            $pdo->rollBack();
            json_response(false, sprintf('Order failed: "%s" only has %d units left in stock, but you requested %d.', $item['name'], $stockAvailable, $qtyRequested), [], 422);
        }
    }

    // ---- C. Resolve User ID (Seamless Guest Account Registration) ----
    $userId = null;
    if (is_logged_in()) {
        $userId = current_user_id();
    } else {
        // Guest user registration block
        $email = trim(input('email', ''));
        
        // Double-check if user already exists
        $userCheck = $pdo->prepare('SELECT id, role_id, full_name, email, is_active FROM users WHERE email = :email LIMIT 1');
        $userCheck->execute(['email' => $email]);
        $existingUser = $userCheck->fetch();

        if ($existingUser) {
            $pdo->rollBack();
            json_response(false, 'An account with this email address already exists. Please log in first to continue.', [], 422);
        }

        // Create seamless user account with random password
        $rawPassword = bin2hex(random_bytes(6)); // e.g. "5a9d8c..."
        $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

        $regStmt = $pdo->prepare('
            INSERT INTO users (role_id, full_name, email, phone, password, is_verified, is_active)
            VALUES (2, :name, :email, :phone, :password, 0, 1)
        ');
        $regStmt->execute([
            'name'     => $recipientName,
            'email'    => $email,
            'phone'    => $phone,
            'password' => $hashedPassword
        ]);
        
        $userId = (int) $pdo->lastInsertId();

        // Fetch fresh row to log them in
        $newRowStmt = $pdo->prepare('SELECT id, role_id, full_name, email, is_active FROM users WHERE id = :id LIMIT 1');
        $newRowStmt->execute(['id' => $userId]);
        $newGuestUser = $newRowStmt->fetch();

        if ($newGuestUser) {
            login_user($newGuestUser);
        }
        
        // Retain password to display as a convenience on thank-you page!
        $_SESSION['guest_temp_pass'] = $rawPassword;
    }

    // ---- D. Shipping Address Creation ----
    if ($addressId === null) {
        $addrInsert = $pdo->prepare('
            INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, postal_code, city, country, is_default)
            VALUES (:uid, :label, :name, :phone, :address1, :postal, :city, \'Bangladesh\', 0)
        ');
        $addrInsert->execute([
            'uid'      => $userId,
            'label'    => 'Shipping',
            'name'     => $recipientName,
            'phone'    => $phone,
            'address1' => $addressLine1,
            'postal'   => $postalCode,
            'city'     => $city
        ]);
        $addressId = (int) $pdo->lastInsertId();
    }

    // ---- E. Calculate Cost Totals & Coupon Deduction ----
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $subtotal += ((float) $item['price']) * ((int) $item['quantity']);
    }

    $discountAmount = 0.0;
    $couponCode = $_SESSION['applied_coupon'] ?? null;
    $couponId = null;

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

            $couponId = (int) $coupon['id'];

            // Increment coupon usages
            $pdo->prepare('UPDATE coupons SET times_used = times_used + 1 WHERE id = :id')
                ->execute(['id' => $couponId]);
        } else {
            unset($_SESSION['applied_coupon']);
        }
    }

    $deliveryCharge = default_delivery_charge();
    if ($subtotal >= 1000.0) {
        $deliveryCharge = 0.0;
    }

    $vatAmount = max(0.0, $subtotal - $discountAmount) * 0.05; // 5% VAT
    $grandTotal = max(0.0, ($subtotal - $discountAmount) + $deliveryCharge + $vatAmount);

    // ---- F. Place Order ----
    // Generate secure order number: ORD-[Ymd]-[Random bytes hex]
    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

    $orderInsert = $pdo->prepare('
        INSERT INTO orders (order_number, user_id, address_id, coupon_id, subtotal, discount_amount, delivery_charge, total_amount, payment_method, payment_status, status, note, created_at, updated_at)
        VALUES (:order_num, :uid, :address_id, :coupon_id, :subtotal, :discount, :delivery, :total, :method, \'unpaid\', \'pending\', :note, NOW(), NOW())
    ');
    $orderInsert->execute([
        'order_num'  => $orderNumber,
        'uid'        => $userId,
        'address_id' => $addressId,
        'coupon_id'  => $couponId,
        'subtotal'   => $subtotal,
        'discount'   => $discountAmount,
        'delivery'   => $deliveryCharge,
        'total'      => $grandTotal,
        'method'     => $paymentMethod,
        'note'       => $note
    ]);
    
    $orderId = (int) $pdo->lastInsertId();

    // ---- G. Save Order Items ----
    $itemInsert = $pdo->prepare('
        INSERT INTO order_items (order_id, product_id, product_name, product_sku, price, quantity, line_total)
        VALUES (:order_id, :pid, :pname, :psku, :price, :qty, :line_total)
    ');

    $stockUpdate = $pdo->prepare('UPDATE products SET stock = stock - :qty WHERE id = :id');

    foreach ($cartItems as $item) {
        $priceVal = (float) $item['price'];
        $qtyVal = (int) $item['quantity'];
        $lineTotal = $priceVal * $qtyVal;

        // Insert Item
        $itemInsert->execute([
            'order_id'   => $orderId,
            'pid'        => $item['product_id'],
            'pname'      => $item['name'],
            'psku'       => $item['sku'],
            'price'      => $priceVal,
            'qty'        => $qtyVal,
            'line_total' => $lineTotal
        ]);

        // Reduce Product Inventory Stock
        $stockUpdate->execute([
            'qty' => $qtyVal,
            'id'  => $item['product_id']
        ]);
    }

    // ---- H. Order Status History Tracker ----
    $historyInsert = $pdo->prepare('
        INSERT INTO order_status_history (order_id, status, note, created_at)
        VALUES (:order_id, \'pending\', \'Order placed successfully. Awaiting processing.\', NOW())
    ');
    $historyInsert->execute(['order_id' => $orderId]);

    // ---- I. Clear Cart Lines ----
    $cartClear = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
    $cartClear->execute(['cart_id' => $cartId]);

    // Clear applied session coupons
    unset($_SESSION['applied_coupon']);

    // Generate Order Placed and Payment notifications
    $notifInsert = $pdo->prepare('
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
        VALUES (:uid, :title, :msg, \'order\', 0, NOW())
    ');
    $notifInsert->execute([
        'uid'   => $userId,
        'title' => 'Order Placed!',
        'msg'   => 'Your order #' . $orderNumber . ' has been successfully placed. We will process it shortly.'
    ]);
    $notifInsert->execute([
        'uid'   => $userId,
        'title' => 'Payment Method Set',
        'msg'   => 'Payment method ' . strtoupper($paymentMethod) . ' selected for order #' . $orderNumber . '.'
    ]);

    $pdo->commit();

    // Store order summary in session to display in thank-you.php
    $_SESSION['last_order'] = [
        'id'           => $orderId,
        'number'       => $orderNumber,
        'grand_total'  => $grandTotal,
        'email'        => !is_logged_in() ? trim(input('email', '')) : current_user()['email']
    ];

    $redirectUrl = url_for('thank-you.php');
    if ($paymentMethod !== 'cod') {
        $redirectUrl = url_for('payment-gateway.php?order_id=' . $orderId);
    }

    json_response(true, 'Order placed successfully!', [
        'redirect' => $redirectUrl
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[process_checkout.php] Transaction failed: ' . $e->getMessage());
    json_response(false, 'Failed to process order due to database error. Please try again.', [], 500);
}
