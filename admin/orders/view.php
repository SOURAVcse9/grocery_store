<?php
/**
 * ==========================================================================
 * admin/orders/view.php — Detailed Order View & Moderation Dashboard
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Order Details — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('orders.view');

$pdo = db();
$orderId = (int) input('id', '0', 'get');

if ($orderId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

try {
    // 1. Fetch Order details
    $orderStmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone 
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE o.id = :oid LIMIT 1
    ");
    $orderStmt->execute(['oid' => $orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        flash('orders_msg', 'Order details not found.', 'error');
        header('Location: index.php');
        exit;
    }

    // 2. Fetch Order Items
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, p.name, p.sku 
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = :oid
    ");
    $itemsStmt->execute(['oid' => $orderId]);
    $items = $itemsStmt->fetchAll();

    // 3. Fetch Addresses
    $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE id = :id LIMIT 1");
    
    $shippingAddr = null;
    if (!empty($order['shipping_address_id'])) {
        $addrStmt->execute(['id' => $order['shipping_address_id']]);
        $shippingAddr = $addrStmt->fetch();
    }
    
    $billingAddr = null;
    if (!empty($order['billing_address_id'])) {
        $addrStmt->execute(['id' => $order['billing_address_id']]);
        $billingAddr = $addrStmt->fetch();
    }

    // 4. Fetch status logs
    $histStmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = :oid ORDER BY created_at DESC");
    $histStmt->execute(['oid' => $orderId]);
    $historyLogs = $histStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/orders/view] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Handle Order Status Mutation Form
if (method_is('post') && isset($_POST['update_order_status'])) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $newStatus = trim(input('status', ''));
        $newPayStatus = trim(input('payment_status', ''));
        $courier = trim(input('courier', ''));
        $trackingNumber = trim(input('tracking_number', ''));
        $statusNote = trim(input('status_note', ''));

        try {
            // A. Restock if Order is being Cancelled
            if ($newStatus === 'cancelled' && $order['status'] !== 'cancelled') {
                foreach ($items as $item) {
                    // Increase stock back
                    $upStock = $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :pid");
                    $upStock->execute(['qty' => $item['quantity'], 'pid' => $item['product_id']]);

                    // Add inventory log
                    $logInv = $pdo->prepare("
                        INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note)
                        VALUES (:pid, :aid, 'increase', :qty, (SELECT stock FROM products WHERE id = :pid2), :note)
                    ");
                    $logInv->execute([
                        'pid' => $item['product_id'],
                        'aid' => current_admin_id(),
                        'qty' => $item['quantity'],
                        'pid2' => $item['product_id'],
                        'note' => "Restocked: Cancelled Order #{$order['order_number']}"
                    ]);
                }
            }

            // B. Update Orders Table Status details
            $upOrder = $pdo->prepare("
                UPDATE orders SET
                    status = :status, payment_status = :pay, 
                    courier = :courier, tracking_number = :track, 
                    updated_at = NOW()
                WHERE id = :oid
            ");
            $upOrder->execute([
                'status'  => $newStatus,
                'pay'     => $newPayStatus,
                'courier' => !empty($courier) ? $courier : null,
                'track'   => !empty($trackingNumber) ? $trackingNumber : null,
                'oid'     => $orderId
            ]);

            // C. Insert status history log
            $historyNote = !empty($statusNote) ? $statusNote : "Order status updated to '" . ucfirst($newStatus) . "'.";
            $insHist = $pdo->prepare("
                INSERT INTO order_status_history (order_id, status, note, created_at)
                VALUES (:oid, :status, :note, NOW())
            ");
            $insHist->execute([
                'oid'    => $orderId,
                'status' => $newStatus,
                'note'   => $historyNote
            ]);

            // D. Add customer activity log for customer timeline
            $cLog = $pdo->prepare("
                INSERT INTO customer_activities (user_id, activity_type, description)
                VALUES (:uid, 'order_status_change', :desc)
            ");
            $cLog->execute([
                'uid'  => $order['user_id'],
                'desc' => "Order #{$order['order_number']} status was updated to: " . strtoupper($newStatus)
            ]);

            // E. Create dynamic database notification for storefront notification bell
            $notif = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                VALUES (:uid, :title, :msg, 'order', 0, NOW())
            ");
            $notif->execute([
                'uid'   => $order['user_id'],
                'title' => "Order Status Update",
                'msg'   => "Your order #{$order['order_number']} is now: " . ucfirst($newStatus)
            ]);

            log_admin_activity('orders.status_update', "Updated status of Order #{$order['order_number']} to: {$newStatus}");
            flash('orders_msg', 'Order status updated successfully.', 'success');
            
            // Reload page to reflect updates
            redirect(current_url());

        } catch (PDOException $e) {
            error_log('[admin/orders/view] status update fail: ' . $e->getMessage());
            $error = 'Failed to update order status due to database transaction error.';
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Order #<?= e($order['order_number']) ?></h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Customer: <strong><?= e($order['full_name']) ?></strong> | Placed: <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="../invoices/print.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-print"></i> Print Invoice</a>
        <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Orders List</a>
    </div>
</div>

<!-- Form panels -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6); align-items:start;" class="admin-dashboard-layout">
    
    <!-- Left Column: Products table & Timeline -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- Ordered items list -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0 0 12px 0;">Ordered Items</h3>
            
            <div class="admin-table-wrapper" style="border-radius:var(--radius-sm);">
                <table class="admin-data-table" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th>Product Item</th>
                            <th>SKU</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $calculatedSubtotal = 0.00;
                        foreach ($items as $item): 
                            $subTotal = (float)$item['price'] * (int)$item['quantity'];
                            $calculatedSubtotal += $subTotal;
                        ?>
                            <tr>
                                <td><strong><?= e($item['name']) ?></strong></td>
                                <td style="font-family: monospace; color:var(--color-text-faint);"><?= e($item['sku']) ?></td>
                                <td style="text-align: right;">৳<?= number_format((float)$item['price'], 2) ?></td>
                                <td style="text-align: center;"><?= (int)$item['quantity'] ?></td>
                                <td style="text-align: right; font-weight:700; color:var(--color-text);">৳<?= number_format($subTotal, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Breakdown Panel -->
            <div style="display:flex; justify-content:flex-end; margin-top:20px; font-size:12px;">
                <div style="width: 250px; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between;"><span>Subtotal:</span><strong>৳<?= number_format($calculatedSubtotal, 2) ?></strong></div>
                    <?php if (!empty($order['discount_amount']) && (float)$order['discount_amount'] > 0): ?>
                        <div style="display:flex; justify-content:space-between; color:#f03e3e;"><span>Discount (<?= e($order['coupon_code']) ?>):</span><strong>-৳<?= number_format((float)$order['discount_amount'], 2) ?></strong></div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between;"><span>Shipping Charge:</span><strong>৳<?= number_format((float)$order['shipping_charge'], 2) ?></strong></div>
                    <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:800; border-top:1px solid var(--color-border); padding-top:8px; color:var(--color-primary);"><span>Grand Total:</span><strong>৳<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Tracking logs timeline -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; color:var(--color-text); margin:0 0 16px 0;">Order Status Timeline</h3>
            
            <div style="display:flex; flex-direction:column; gap:16px; border-left:2px solid var(--color-border); padding-left:20px; margin-left:10px;">
                <?php if (!empty($historyLogs)): ?>
                    <?php foreach ($historyLogs as $log): ?>
                        <div style="position:relative; font-size:12px;">
                            <span style="position:absolute; left:-27px; top:2px; width:12px; height:12px; border-radius:50%; background:var(--color-primary); border:2px solid #fff;"></span>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <strong style="text-transform:uppercase; color:var(--color-text); font-size:10px; background:var(--color-primary-light); color:var(--color-primary-dark); padding:2px 6px; border-radius:4px;"><?= e($log['status']) ?></strong>
                                <span style="font-size:10px; color:var(--color-text-faint);"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></span>
                            </div>
                            <p style="margin:0; color:var(--color-text-muted);"><?= e($log['note']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--color-text-faint); font-size:12px; margin:0; padding-left:10px;">No timeline updates available.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Column: Status updates & Customer Address Info -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- Status updater card -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Update Order Status</h3>
            
            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="update_order_status" value="1">
                
                <div class="form-field-group">
                    <label for="orderStatusSel" style="font-weight:700;">Order Status</label>
                    <select id="orderStatusSel" name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="packed" <?= $order['status'] === 'packed' ? 'selected' : '' ?>>Packed</option>
                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="out_for_delivery" <?= $order['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out For Delivery</option>
                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-field-group">
                    <label for="payStatusSel" style="font-weight:700;">Payment Status</label>
                    <select id="payStatusSel" name="payment_status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                        <option value="pending" <?= ($order['payment_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= ($order['payment_status'] ?? 'pending') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="failed" <?= ($order['payment_status'] ?? 'pending') === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="refunded" <?= ($order['payment_status'] ?? 'pending') === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <div class="form-field-group">
                    <label for="shipCourier" style="font-weight:700;">Courier Service</label>
                    <input type="text" id="shipCourier" name="courier" value="<?= e($order['courier'] ?? '') ?>" placeholder="E.g. Pathao, Steadfast..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>

                <div class="form-field-group">
                    <label for="shipTracking" style="font-weight:700;">Tracking Number</label>
                    <input type="text" id="shipTracking" name="tracking_number" value="<?= e($order['tracking_number'] ?? '') ?>" placeholder="Courier tracking number" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>

                <div class="form-field-group">
                    <label for="statusNote" style="font-weight:700;">Timeline Note / Reason</label>
                    <textarea id="statusNote" name="status_note" rows="2" placeholder="Note detail visible to customer..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;">Save Status Changes</button>
            </form>
        </div>

        <!-- Shipping details Card -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5); font-size:12px;">
            <h3 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Delivery Details</h3>
            
            <?php if ($shippingAddr): ?>
                <p style="margin: 0 0 6px 0;"><strong>Recipient:</strong> <?= e($shippingAddr['recipient_name']) ?></p>
                <p style="margin: 0 0 4px 0; color:var(--color-text-muted);"><?= e($shippingAddr['address_line1']) ?></p>
                <?php if (!empty($shippingAddr['address_line2'])): ?>
                    <p style="margin: 0 0 4px 0; color:var(--color-text-muted);"><?= e($shippingAddr['address_line2']) ?></p>
                <?php endif; ?>
                <p style="margin: 0 0 6px 0; color:var(--color-text-muted);"><?= e($shippingAddr['city']) ?>, <?= e($shippingAddr['district']) ?> - <?= e($shippingAddr['postal_code']) ?></p>
                <p style="margin: 0; color:var(--color-text-muted);"><strong>Phone:</strong> <?= e($shippingAddr['phone']) ?></p>
            <?php else: ?>
                <p style="color:var(--color-text-faint); margin:0;">No dedicated shipping address mapped. (Standard user phone: <?= e($order['phone']) ?>)</p>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
