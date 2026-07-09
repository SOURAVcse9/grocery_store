<?php
/**
 * ==========================================================================
 * admin/pos/receipts.php — Thermal Printer Printable POS Invoice
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('pos.access');

$pdo = db();
$orderId = (int) input('id', '0', 'get');

if ($orderId > 0) {
    // RENDER PRINTABLE THERMAL RECEIPT
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
        error_log('[admin/pos/receipts] load failed: ' . $e->getMessage());
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
            .header h1 { font-size: 15px; margin: 0; }
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
        <?php
            $taxable = max((float)$order['subtotal'] - (float)$order['discount_amount'], 0);
            $vat = round($taxable * 0.05, 2);
        ?>
        <div class="summary-row">
            <span>VAT (5%):</span>
            <span>৳<?= number_format($vat, 2) ?></span>
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
    <?php
    exit;
}

// OTHERWISE RENDER DIRECTORY LIST VIEW IN LAYOUT
$pageTitle = 'POS Invoices — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">POS Invoices Directory</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect POS sales invoices, print thermal sheets, or download YTD logs.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Order Number</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Order Subtotal</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Discount</th>
                    <th style="padding:16px 20px; text-align:right; width:180px;">Total Due</th>
                    <th style="padding:16px 20px; width:220px;">Created Date</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $orders = $pdo->query("SELECT * FROM orders WHERE order_number LIKE 'POS-%' ORDER BY created_at DESC LIMIT 30")->fetchAll();
                } catch (PDOException $e) {
                    $orders = [];
                }
                if (!empty($orders)):
                    foreach ($orders as $row):
                ?>
                    <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                        <td style="padding:12px 20px;"><strong><?= e($row['order_number']) ?></strong></td>
                        <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['subtotal'], 2) ?></td>
                        <td style="padding:12px 20px; text-align:right; color:#e03131;">-৳<?= number_format((float)$row['discount_amount'], 2) ?></td>
                        <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_amount'], 2) ?></td>
                        <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                        <td style="padding:12px 20px; text-align:right;">
                            <a href="?id=<?= $row['id'] ?>" target="_blank" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-print"></i> Print Receipt</a>
                        </td>
                    </tr>
                <?php
                    endforeach;
                else:
                ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No counter sales invoices logged in the specified period.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
