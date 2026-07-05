<?php
/**
 * ==========================================================================
 * admin/pos/receipt.php — Thermal Printer Printable POS Receipt Page
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('pos.manage');

$pdo = db();
$orderId = (int) input('id', '0', 'get');

if ($orderId <= 0) {
    echo "Invalid Order ID.";
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo "Order details not found.";
        exit;
    }

    $stmtItems = $pdo->prepare("
        SELECT oi.*, p.name AS product_name 
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $stmtItems->execute([$orderId]);
    $items = $stmtItems->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/pos/receipt] load failed: ' . $e->getMessage());
    echo "Database error while loading receipt details.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS Receipt - #<?= e($order['order_number']) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 10px;
            width: 280px;
            background: #fff;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .header { margin-bottom: 12px; }
        .header h1 { font-size: 16px; margin: 0; }
        .header p { margin: 2px 0; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .summary-row { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 4px; }
        .footer { margin-top: 16px; font-size: 10px; }
        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="header text-center">
        <h1>GROCO SUPERSTORE</h1>
        <p>Road 4, Mid Badda, Dhaka</p>
        <p>Phone: +8801700000000</p>
        <p>Date: <?= date('Y-m-d H:i:s', strtotime($order['created_at'])) ?></p>
        <p>Order: #<?= e($order['order_number']) ?></p>
    </div>

    <div class="line"></div>

    <!-- Items list -->
    <?php foreach ($items as $row): ?>
        <div class="item-row">
            <div style="flex: 2;"><?= e($row['product_name']) ?></div>
            <div style="flex: 1; text-align: center;"><?= $row['quantity'] ?>x</div>
            <div style="flex: 1.2; text-align: right;">৳<?= number_format((float)$row['price'], 2) ?></div>
        </div>
    <?php endforeach; ?>

    <div class="line"></div>

    <!-- Pricing Summary -->
    <div class="summary-row">
        <span>Subtotal:</span>
        <span>৳<?= number_format((float)$order['subtotal'], 2) ?></span>
    </div>
    <div class="summary-row">
        <span>Discount:</span>
        <span>-৳<?= number_format((float)$order['discount_amount'], 2) ?></span>
    </div>
    <div class="line"></div>
    <div class="summary-row" style="font-size: 14px;">
        <span>TOTAL DUE:</span>
        <span>৳<?= number_format((float)$order['total_amount'], 2) ?></span>
    </div>

    <div class="footer text-center">
        <p>Thank you for shopping with GroCo!</p>
        <p>Software Powered by GroCo POS System</p>
    </div>

</body>
</html>
