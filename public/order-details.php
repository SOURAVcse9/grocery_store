<?php
/**
 * ==========================================================================
 * public/order-details.php — Detailed Invoice & Package Tracking Page
 * ==========================================================================
 * Verifies authorization, reads billing/shipping details, loops purchase
 * logs, renders status timelines, and outputs print-ready invoices.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page access
require_login();

$user = current_user();
$userId = (int) $user['id'];
$orderId = (int) input('id', '0', 'get');

if ($orderId <= 0) {
    flash('orders', 'Invalid order selection.', 'error');
    redirect(url_for('orders.php'));
}

$pdo = db();

// --------------------------------------------------------------------------
// 1. AJAX Reorder POST Handler (Matches public/orders.php)
// --------------------------------------------------------------------------
if (method_is('post') && input('action', '') === 'reorder') {
    verify_csrf_or_fail(true);

    try {
        // Verify order ownership
        $orderStmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id AND user_id = :uid LIMIT 1');
        $orderStmt->execute(['id' => $orderId, 'uid' => $userId]);
        if (!$orderStmt->fetch()) {
            json_response(false, 'Order not found or unauthorized.', [], 403);
        }

        // Fetch items from this order
        $itemsStmt = $pdo->prepare('
            SELECT oi.product_id, oi.quantity, p.price, p.stock, p.is_active
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :oid
        ');
        $itemsStmt->execute(['oid' => $orderId]);
        $pastItems = $itemsStmt->fetchAll();

        if (empty($pastItems)) {
            json_response(false, 'No valid items found in this order.', [], 422);
        }

        $cartId = current_cart_id();
        $addedCount = 0;

        $upsertStmt = $pdo->prepare('
            INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
            VALUES (:cart_id, :product_id, :qty, :price, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                quantity = LEAST(quantity + VALUES(quantity), :max_stock),
                updated_at = NOW()
        ');

        foreach ($pastItems as $item) {
            if ((int) $item['is_active'] === 0 || (int) $item['stock'] <= 0) {
                continue;
            }
            $qtyToAdd = min((int) $item['quantity'], (int) $item['stock']);
            
            $upsertStmt->bindValue('cart_id', $cartId, PDO::PARAM_INT);
            $upsertStmt->bindValue('product_id', (int) $item['product_id'], PDO::PARAM_INT);
            $upsertStmt->bindValue('qty', $qtyToAdd, PDO::PARAM_INT);
            $upsertStmt->bindValue('price', (float) $item['price'], PDO::PARAM_STR);
            $upsertStmt->bindValue('max_stock', (int) $item['stock'], PDO::PARAM_INT);
            $upsertStmt->execute();

            $addedCount++;
        }

        if ($addedCount === 0) {
            json_response(false, 'All products in this order are currently out of stock.', [], 422);
        }

        $cartCount = cart_item_count();

        json_response(true, "Successfully added {$addedCount} items to your cart!", [
            'cart_count' => $cartCount
        ]);

    } catch (PDOException $e) {
        error_log('[order-details.php] Reorder failed: ' . $e->getMessage());
        json_response(false, 'Failed to reorder items.', [], 500);
    }
}

// --------------------------------------------------------------------------
// 2. Fetch Order Details & History Logs for SSR Display
// --------------------------------------------------------------------------
try {
    // A. Fetch Order Details (verifying ownership)
    $stmt = $pdo->prepare('
        SELECT o.*, c.code AS coupon_code, c.discount_percent AS coupon_percent
        FROM orders o
        LEFT JOIN coupons c ON c.id = o.coupon_id
        WHERE o.id = :id AND o.user_id = :uid
        LIMIT 1
    ');
    $stmt->execute(['id' => $orderId, 'uid' => $userId]);
    $order = $stmt->fetch();

    if (!$order) {
        flash('orders', 'Order not found or unauthorized.', 'error');
        redirect(url_for('orders.php'));
    }

    // B. Fetch Shipping Address Details
    $addrStmt = $pdo->prepare('SELECT * FROM addresses WHERE id = :id LIMIT 1');
    $addrStmt->execute(['id' => (int) $order['address_id']]);
    $address = $addrStmt->fetch();

    // C. Fetch Order Line Items
    $itemsStmt = $pdo->prepare('
        SELECT oi.*, p.thumbnail, p.slug
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = :oid
        ORDER BY oi.id ASC
    ');
    $itemsStmt->execute(['oid' => $orderId]);
    $items = $itemsStmt->fetchAll();

    // D. Fetch Tracking History Logs
    $historyStmt = $pdo->prepare('
        SELECT status, note, created_at 
        FROM order_status_history 
        WHERE order_id = :oid 
        ORDER BY created_at ASC
    ');
    $historyStmt->execute(['oid' => $orderId]);
    $history = $historyStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[order-details.php] Load error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Order Database Load Error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Meta configurations
$pageTitle = 'Order Details #' . $order['order_number'] . ' — ' . site_name();
$pageDescription = 'Track shipping milestones and download invoice details for Order #' . $order['order_number'];

$extraStylesheets = ['css/account.css', 'css/orders.css', 'css/cart.css'];
$extraScripts = ['js/orders.js'];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'My Dashboard', 'link' => 'account.php'],
    ['title' => 'Order History', 'link' => 'orders.php'],
    ['title' => 'Order #' . $order['order_number']]
];
?>

<!-- Breadcrumbs -->
<div class="no-print">
    <?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>
</div>

<div class="container" style="margin-top: var(--space-5);">
    <div class="account-layout">
        
        <!-- Left Sidebar Navigation Menu -->
        <aside class="account-sidebar no-print">
            <ul class="account-menu-list">
                <li class="account-menu-item">
                    <a href="<?= url_for('account.php') ?>"><i class="fas fa-gauge"></i> Dashboard</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('profile.php') ?>"><i class="fas fa-user-gear"></i> Edit Profile</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('addresses.php') ?>"><i class="fas fa-map-location-dot"></i> Saved Addresses</a>
                </li>
                <li class="account-menu-item active">
                    <a href="<?= url_for('orders.php') ?>"><i class="fas fa-box-open"></i> My Orders</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('logout.php') ?>" style="color:var(--color-danger);"><i class="fas fa-power-off" style="color:var(--color-danger);"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Right Main Panel / Invoice Area -->
        <main class="account-main-content print-invoice-area">
            
            <!-- Timeline Tracking Widget -->
            <div class="dashboard-card no-print">
                <h3 class="dashboard-card-title">Package Milestones</h3>
                <?php include PUBLIC_PATH . '/components/order-timeline.php'; ?>
            </div>

            <!-- Invoice / Details Wrapper -->
            <div class="invoice-wrapper">
                <!-- Branding Header -->
                <div class="invoice-header-box">
                    <div class="invoice-branding">
                        <h2>Grocery Store</h2>
                        <p><?= CONTACT_ADDRESS ?></p>
                        <p>Email: <?= CONTACT_EMAIL ?> | Phone: <?= CONTACT_PHONE ?></p>
                    </div>
                    <div class="invoice-meta-info">
                        <h1>INVOICE</h1>
                        <p>Invoice #: <strong><?= e($order['order_number']) ?></strong></p>
                        <p>Date: <?= date('M d, Y', strtotime($order['created_at'])) ?></p>
                        <p>Payment: <strong style="text-transform:uppercase;"><?= e($order['payment_method']) ?></strong> | Status: <strong style="text-transform:uppercase; color:var(--color-primary);"><?= e($order['payment_status']) ?></strong></p>
                    </div>
                </div>

                <!-- Addresses Details Grid -->
                <div class="invoice-details-grid">
                    <div class="invoice-address-card">
                        <h4>Shipping Address</h4>
                        <?php if ($address): ?>
                            <p>
                                <strong><?= e($address['recipient_name']) ?></strong><br>
                                Phone: <?= e($address['phone']) ?><br>
                                <?= e($address['address_line1']) ?><?= $address['address_line2'] ? ', ' . e($address['address_line2']) : '' ?><br>
                                <?= e($address['city']) ?><?= $address['state'] ? ', ' . e($address['state']) : '' ?><br>
                                <?= e($address['country']) ?>
                            </p>
                        <?php else: ?>
                            <p>No address details linked.</p>
                        <?php endif; ?>
                    </div>

                    <div class="invoice-address-card">
                        <h4>Billing Information</h4>
                        <p>
                            <strong><?= e($user['full_name']) ?></strong><br>
                            Email: <?= e($user['email']) ?><br>
                            Phone: <?= e($user['phone'] ?? 'No phone registered') ?>
                        </p>
                    </div>
                </div>

                <!-- Items Purchased Table -->
                <div style="padding: var(--space-4);">
                    <table class="dashboard-table invoice-items-table" style="font-size:12px;">
                        <thead>
                            <tr>
                                <th>Item Preview</th>
                                <th>Product Details</th>
                                <th>SKU</th>
                                <th style="text-align:right;">Unit Price</th>
                                <th style="text-align:center;">Quantity</th>
                                <th style="text-align:right;">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $thumbUrl = image_url($item['thumbnail'], 'products');
                                $productUrl = url_for('product.php?slug=' . e($item['slug']));
                            ?>
                                <tr>
                                    <td>
                                        <div class="checkout-item-preview-img" style="width:48px; height:48px;">
                                            <img src="<?= e($thumbUrl) ?>" alt="<?= e($item['product_name']) ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?= $productUrl ?>" style="font-weight:700; color:var(--color-text);"><?= e($item['product_name']) ?></a>
                                        <?php if ($order['status'] === 'delivered' && ($order['payment_status'] === 'paid' || $order['payment_method'] === 'cod')): ?>
                                            <div style="margin-top: 6px;">
                                                <a href="<?= $productUrl ?>#tabReviews" class="btn btn-primary no-print" style="padding: 4px 10px; font-size: 10px; border-radius: var(--radius-pill); border: none; text-decoration: none; display: inline-block; font-weight: 700;"><i class="far fa-star"></i> Review Product</a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($item['product_sku']) ?></td>
                                    <td style="text-align:right;"><?= format_price((float)$item['price']) ?></td>
                                    <td style="text-align:center;"><?= (int)$item['quantity'] ?></td>
                                    <td style="text-align:right; font-weight:700; color:var(--color-text);"><?= format_price((float)$item['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals Summary Breakdown -->
                <div style="display:flex; justify-content:flex-end; padding:var(--space-4) var(--space-5); border-top:1px solid var(--color-border); background:rgba(0,0,0,0.005);">
                    <div style="width:280px; display:flex; flex-direction:column; gap:8px; font-size:12px;">
                        <div style="display:flex; justify-content:space-between; color:var(--color-text-muted);">
                            <span>Subtotal:</span>
                            <strong><?= format_price((float)$order['subtotal']) ?></strong>
                        </div>
                        
                        <?php if ((float)$order['discount_amount'] > 0): ?>
                            <div style="display:flex; justify-content:space-between; color:var(--color-danger);">
                                <span>Coupon Applied<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?>:</span>
                                <strong>-<?= format_price((float)$order['discount_amount']) ?></strong>
                            </div>
                        <?php endif; ?>

                        <div style="display:flex; justify-content:space-between; color:var(--color-text-muted);">
                            <span>Delivery Charge:</span>
                            <strong><?= format_price((float)$order['delivery_charge']) ?></strong>
                        </div>

                        <?php 
                        // Estimate tax (which is already compiled inside total_amount)
                        $taxable = max(0.0, (float)$order['subtotal'] - (float)$order['discount_amount']);
                        $estTax = $taxable * 0.05;
                        ?>
                        <div style="display:flex; justify-content:space-between; color:var(--color-text-muted);">
                            <span>VAT (5%):</span>
                            <strong><?= format_price($estTax) ?></strong>
                        </div>

                        <div style="display:flex; justify-content:space-between; font-size:14px; border-top:1px solid var(--color-border); padding-top:8px; font-weight:800; color:var(--color-text);">
                            <span>Grand Total:</span>
                            <strong style="color:var(--color-primary); font-size:16px;"><?= format_price((float)$order['total_amount']) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Invoice Notes / Terms -->
                <?php if (!empty($order['note'])): ?>
                    <div style="padding:var(--space-4); border-top:1px solid var(--color-border); font-size:11px; color:var(--color-text-muted); line-height:1.4;">
                        <strong>Customer Note:</strong><br>
                        <?= nl2br(e($order['note'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Page actions -->
            <div class="no-print" style="display:flex; gap:var(--space-2); justify-content:space-between; margin-bottom:var(--space-5);">
                <a href="<?= url_for('orders.php') ?>" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700;"><i class="fas fa-arrow-left"></i> Back to Orders</a>
                <div style="display:flex; gap:var(--space-2);">
                    <button type="button" class="btn btn-secondary btn-reorder-products" data-order-id="<?= $orderId ?>" style="font-size:11px; font-weight:700; border-radius:var(--radius-pill); border:none;"><i class="fas fa-arrows-rotate"></i> Reorder All Items</button>
                    <button type="button" class="btn btn-primary" id="btnPrintInvoice" style="font-size:11px; font-weight:700; border-radius:var(--radius-pill); border:none;"><i class="fas fa-print"></i> Print Invoice</button>
                </div>
            </div>

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
