<?php
/**
 * ==========================================================================
 * admin/invoices/print.php — A4 Print Friendly Invoice Controller
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('orders.view');

$pdo = db();
$orderId = (int) input('id', '0', 'get');

if ($orderId <= 0) {
    echo "Invalid Order ID.";
    exit;
}

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
        echo "Order details not found.";
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

} catch (PDOException $e) {
    error_log('[admin/invoices/print] load failed: ' . $e->getMessage());
    echo "Database error occurred.";
    exit;
}

// Log administrative action
log_admin_activity('invoices.print', "Printed invoice for Order Number: #{$order['order_number']}");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= e($order['order_number']) ?> — GroCo Store</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #333;
            margin: 0;
            padding: 40px;
            background-color: #fff;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0ca678;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .brand h1 {
            color: #0ca678;
            margin: 0 0 6px 0;
            font-size: 26px;
            font-weight: 800;
        }
        .brand p {
            margin: 0;
            color: #777;
            font-size: 11px;
        }
        .title h2 {
            margin: 0 0 6px 0;
            font-size: 22px;
            font-weight: 700;
            color: #222;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .meta-card h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            text-transform: uppercase;
            color: #777;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
        }
        .meta-card p {
            margin: 0 0 4px 0;
            line-height: 1.5;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 10px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            text-align: left;
        }
        .invoice-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 40px;
        }
        .totals-table {
            width: 250px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 10px;
            font-size: 13px;
        }
        .totals-table tr.grand-total td {
            font-weight: 800;
            font-size: 16px;
            color: #0ca678;
            border-top: 2px solid #0ca678;
            padding-top: 10px;
        }
        .footer {
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 50px;
            color: #777;
            font-size: 11px;
        }
        
        /* Print Styles */
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Print Control Bar -->
    <div class="no-print" style="max-width: 800px; margin: 0 auto 20px auto; background: #e6fcf5; border: 1px solid #c3fae8; padding: 12px 20px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
        <span style="color:#0ca678; font-weight:700;">A4 Invoice Preview Mode</span>
        <button onclick="window.print();" style="background:#0ca678; border:none; color:#fff; font-weight:700; padding:8px 16px; border-radius:4px; cursor:pointer;"><i class="fas fa-print"></i> Print Invoice</button>
    </div>

    <div class="invoice-box">
        <!-- Header -->
        <div class="invoice-header">
            <div class="brand">
                <h1>GROCO STORE</h1>
                <p>Flat 4A, House 12, Road 4, Banani, Dhaka, Bangladesh</p>
                <p>Phone: +880 1712 345678 | Email: support@groco.com.bd</p>
            </div>
            <div class="title" style="text-align: right;">
                <h2>INVOICE</h2>
                <p style="margin: 0; font-weight: 700; color: #555;">Order #<?= e($order['order_number']) ?></p>
                <p style="margin: 4px 0 0 0; color: #999; font-size: 11px;">Date: <?= date('M d, Y', strtotime($order['created_at'])) ?></p>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="meta-grid">
            <!-- Shipping Details -->
            <div class="meta-card">
                <h3>Delivery Address</h3>
                <?php if ($shippingAddr): ?>
                    <p><strong><?= e($shippingAddr['recipient_name']) ?></strong></p>
                    <p><?= e($shippingAddr['address_line1']) ?></p>
                    <?php if (!empty($shippingAddr['address_line2'])): ?>
                        <p><?= e($shippingAddr['address_line2']) ?></p>
                    <?php endif; ?>
                    <p><?= e($shippingAddr['city']) ?>, <?= e($shippingAddr['district']) ?> - <?= e($shippingAddr['postal_code']) ?></p>
                    <p>Phone: <?= e($shippingAddr['phone']) ?></p>
                <?php else: ?>
                    <p>Customer Profile: <?= e($order['full_name']) ?></p>
                    <p>Contact Phone: <?= e($order['phone']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Billing details -->
            <div class="meta-card">
                <h3>Billing & Payment</h3>
                <p><strong>Method:</strong> <?= strtoupper($order['payment_method']) ?></p>
                <p><strong>Status:</strong> <?= strtoupper($order['payment_status'] ?? 'pending') ?></p>
                <?php if (!empty($order['notes'])): ?>
                    <p style="margin-top: 10px; font-style: italic; color:#666;">Note: "<?= e($order['notes']) ?>"</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="width: 100px;">SKU</th>
                    <th>Product Item</th>
                    <th style="width: 100px; text-align: right;">Price</th>
                    <th style="width: 80px; text-align: center;">Qty</th>
                    <th style="width: 120px; text-align: right;">Subtotal</th>
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
                        <td style="font-family: monospace; color:#666;"><?= e($item['sku']) ?></td>
                        <td><strong><?= e($item['name']) ?></strong></td>
                        <td style="text-align: right;">৳<?= number_format((float)$item['price'], 2) ?></td>
                        <td style="text-align: center;"><?= (int)$item['quantity'] ?></td>
                        <td style="text-align: right;">৳<?= number_format($subTotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Table -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">৳<?= number_format($calculatedSubtotal, 2) ?></td>
                </tr>
                <?php if (!empty($order['discount_amount']) && (float)$order['discount_amount'] > 0): ?>
                    <tr>
                        <td>Coupon (<?= e($order['coupon_code'] ?? 'Discount') ?>):</td>
                        <td style="text-align: right; color:#f03e3e;">-৳<?= number_format((float)$order['discount_amount'], 2) ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>Delivery Charge:</td>
                    <td style="text-align: right;">৳<?= number_format((float)($order['shipping_charge'] ?? 0.0), 2) ?></td>
                </tr>
                <?php if (!empty($order['tax_amount']) && (float)$order['tax_amount'] > 0): ?>
                    <tr>
                        <td>Tax / VAT:</td>
                        <td style="text-align: right;">৳<?= number_format((float)$order['tax_amount'], 2) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td>Grand Total:</td>
                    <td style="text-align: right;">৳<?= number_format((float)$order['total_amount'], 2) ?></td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for shopping at GroCo Grocery Store!</p>
            <p>This is a computer generated document. No signature required.</p>
        </div>
    </div>

</body>
</html>
