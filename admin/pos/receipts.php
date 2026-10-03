<?php
/**
 * ==========================================================================
 * admin/pos/receipts.php — Thermal Printer Printable POS Invoice & Directory
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('pos.access');

$pdo = db();
$orderId = (int) input('id', '0', 'get');
$format = trim((string) input('format', '80mm', 'get'));
if (!in_array($format, ['80mm', '58mm', 'a4'], true)) {
    $format = '80mm';
}

if ($orderId > 0) {
    try {
        $posService = pos_service();
        $receipt = $posService->receipts()->getReceiptData($orderId, false, current_admin_id());
    } catch (Exception $e) {
        // Fallback to basic query
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            echo "Order details not found.";
            exit;
        }
        $stmtItems = $pdo->prepare("
            SELECT oi.*, COALESCE(oi.product_name, p.name, 'Product') AS product_name 
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$orderId]);
        $receipt = [
            'store'              => ['name' => 'GroCo Superstore', 'address' => 'Road 4, Mid Badda, Dhaka', 'phone' => '+8801700000000', 'vat_reg_no' => 'BIN-192837465'],
            'transaction_number' => $order['order_number'],
            'order_number'       => $order['order_number'],
            'date_time'          => $order['created_at'],
            'cashier'            => 'Cashier',
            'customer_name'      => 'Walk-in Customer',
            'customer_phone'     => 'N/A',
            'items'              => $stmtItems->fetchAll(),
            'subtotal'           => (float)$order['subtotal'],
            'discount_amount'    => (float)$order['discount_amount'],
            'tax_amount'         => 0.00,
            'grand_total'        => (float)$order['total_amount'],
            'paid_amount'        => (float)$order['total_amount'],
            'change_amount'      => 0.00,
            'payments'           => [['method' => $order['payment_method'] ?? 'cash', 'amount' => (float)$order['total_amount']]],
            'return_policy'      => 'Exchange within 3 days with receipt in original condition.',
            'thank_you_message'  => 'Thank you for shopping at GroCo Superstore!'
        ];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>POS Receipt — <?= e($receipt['transaction_number']) ?></title>
        <style>
            * { box-sizing: border-box; }
            body {
                font-family: 'Courier New', Courier, monospace, sans-serif;
                color: #000;
                margin: 0;
                padding: 10px;
                background: #f1f3f5;
            }
            .no-print {
                display: flex;
                gap: 8px;
                justify-content: center;
                margin-bottom: 16px;
            }
            .btn-print {
                background: #228be6;
                color: #fff;
                border: none;
                padding: 6px 14px;
                font-size: 12px;
                font-weight: bold;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .btn-format {
                background: #fff;
                color: #333;
                border: 1px solid #ced4da;
                padding: 6px 12px;
                font-size: 12px;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
            }
            .btn-format.active {
                background: #339af0;
                color: #fff;
                border-color: #339af0;
            }
            .receipt-container {
                background: #fff;
                margin: 0 auto;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            /* Format sizes */
            .format-80mm { width: 80mm; max-width: 300px; padding: 12px; font-size: 12px; }
            .format-58mm { width: 58mm; max-width: 220px; padding: 8px; font-size: 10px; }
            .format-a4   { width: 100%; max-width: 750px; padding: 30px; font-size: 13px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .font-bold { font-weight: bold; }
            .header { margin-bottom: 10px; }
            .header h1 { font-size: 15px; margin: 0; font-weight: 800; letter-spacing: 0.5px; }
            .format-58mm .header h1 { font-size: 13px; }
            .format-a4 .header h1 { font-size: 24px; }
            .header p { margin: 2px 0; }
            .divider { border-top: 1px dashed #000; margin: 8px 0; }
            .format-a4 .divider { border-top: 1px solid #dee2e6; margin: 16px 0; }
            .item-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
            .item-table th, .item-table td { padding: 3px 0; text-align: left; }
            .item-table .qty-col { text-align: center; width: 40px; }
            .item-table .price-col { text-align: right; width: 70px; }
            .summary-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
            .total-row { display: flex; justify-content: space-between; font-size: 14px; font-weight: bold; margin-top: 4px; padding-top: 4px; border-top: 1px dashed #000; }
            .format-58mm .total-row { font-size: 12px; }
            .format-a4 .total-row { font-size: 18px; border-top: 2px solid #000; }
            .payments-section { margin-top: 6px; padding-top: 4px; border-top: 1px dotted #666; font-size: 11px; }
            .format-58mm .payments-section { font-size: 9px; }
            .footer { margin-top: 14px; font-size: 10px; }
            .format-58mm .footer { font-size: 8px; }
            .barcode-box { letter-spacing: 4px; font-weight: bold; font-size: 14px; margin: 8px 0; }

            @media print {
                body { background: #fff; padding: 0; }
                .no-print { display: none !important; }
                .receipt-container { box-shadow: none; margin: 0; }
                .format-80mm { width: 80mm; max-width: 80mm; padding: 0; }
                .format-58mm { width: 58mm; max-width: 58mm; padding: 0; }
                .format-a4 { width: 100%; padding: 0; }
            }
        </style>
    </head>
    <body>

        <div class="no-print">
            <button class="btn-print" onclick="window.print();">🖨️ Print Receipt</button>
            <a href="?id=<?= $orderId ?>&format=80mm" class="btn-format <?= $format === '80mm' ? 'active' : '' ?>">80mm Thermal</a>
            <a href="?id=<?= $orderId ?>&format=58mm" class="btn-format <?= $format === '58mm' ? 'active' : '' ?>">58mm Thermal</a>
            <a href="?id=<?= $orderId ?>&format=a4" class="btn-format <?= $format === 'a4' ? 'active' : '' ?>">A4 Invoice</a>
            <button class="btn-format" onclick="window.close();">✕ Close</button>
        </div>

        <div class="receipt-container format-<?= htmlspecialchars($format) ?>">
            
            <div class="header text-center">
                <h1><?= e($receipt['store']['name'] ?? 'GROCO SUPERSTORE') ?></h1>
                <p><?= e($receipt['store']['address'] ?? 'Road 4, Mid Badda, Dhaka') ?></p>
                <p>Phone: <?= e($receipt['store']['phone'] ?? '+8801700000000') ?></p>
                <?php if (!empty($receipt['store']['vat_reg_no'])): ?>
                    <p>VAT BIN: <?= e($receipt['store']['vat_reg_no']) ?></p>
                <?php endif; ?>
                <div class="divider"></div>
                <div style="display:flex; justify-content:space-between; font-size:10px; margin-bottom:2px;">
                    <span>Txn: <strong><?= e($receipt['transaction_number']) ?></strong></span>
                    <span><?= date('d/m/Y H:i', strtotime($receipt['date_time'])) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:10px;">
                    <span>Cashier: <?= e($receipt['cashier']) ?></span>
                    <span>Cust: <?= e($receipt['customer_name']) ?></span>
                </div>
            </div>

            <div class="divider"></div>

            <table class="item-table">
                <thead>
                    <tr style="border-bottom: 1px dashed #000;">
                        <th>Item</th>
                        <th class="qty-col">Qty</th>
                        <th class="price-col">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipt['items'] as $it): 
                        $pName = $it['product_name'] ?? ($it['name'] ?? 'Item');
                        $qty = (float)($it['quantity'] ?? 1);
                        $uPrice = (float)($it['unit_price'] ?? ($it['price'] ?? 0));
                        $lineTot = (float)($it['line_total'] ?? ($qty * $uPrice));
                        $unit = $it['unit'] ?? 'pcs';
                    ?>
                        <tr>
                            <td>
                                <div><?= e($pName) ?></div>
                                <div style="font-size:9px; color:#555;">@ ৳<?= number_format($uPrice, 2) ?><?= $unit !== 'pcs' ? '/' . e($unit) : '' ?></div>
                            </td>
                            <td class="qty-col"><?= $qty ?></td>
                            <td class="price-col">৳<?= number_format($lineTot, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="divider"></div>

            <div class="summary-row">
                <span>Subtotal:</span>
                <span>৳<?= number_format((float)$receipt['subtotal'], 2) ?></span>
            </div>
            <?php if ((float)$receipt['discount_amount'] > 0): ?>
                <div class="summary-row" style="color: #c92a2a;">
                    <span>Discount:</span>
                    <span>-৳<?= number_format((float)$receipt['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((float)$receipt['tax_amount'] > 0): ?>
                <div class="summary-row">
                    <span>VAT (incl):</span>
                    <span>৳<?= number_format((float)$receipt['tax_amount'], 2) ?></span>
                </div>
            <?php endif; ?>

            <div class="total-row">
                <span>GRAND TOTAL:</span>
                <span>৳<?= number_format((float)$receipt['grand_total'], 2) ?></span>
            </div>

            <div class="payments-section">
                <div style="font-weight:bold; margin-bottom:2px;">Tender Breakdown:</div>
                <?php if (!empty($receipt['payments'])): ?>
                    <?php foreach ($receipt['payments'] as $pay): ?>
                        <div class="summary-row">
                            <span style="text-transform: capitalize;"><?= e($pay['payment_method'] ?? ($pay['method'] ?? 'Cash')) ?>:</span>
                            <span>৳<?= number_format((float)$pay['amount'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="summary-row">
                        <span>Paid:</span>
                        <span>৳<?= number_format((float)$receipt['paid_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ((float)$receipt['change_amount'] > 0): ?>
                    <div class="summary-row" style="font-weight:bold; margin-top:2px;">
                        <span>Change Given:</span>
                        <span>৳<?= number_format((float)$receipt['change_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="divider"></div>

            <div class="footer text-center">
                <div class="barcode-box">*<?= e($receipt['transaction_number']) ?>*</div>
                <p class="font-bold"><?= e($receipt['thank_you_message']) ?></p>
                <p><?= e($receipt['return_policy']) ?></p>
                <p style="font-size:8px; color:#666; margin-top:6px;">Printed: <?= date('Y-m-d H:i:s') ?> | System: GroCo POS v2.6 Enterprise</p>
            </div>

        </div>

        <script>
            // Auto-trigger print dialog after render
            window.addEventListener('DOMContentLoaded', () => {
                if (window.location.search.indexOf('noprint') === -1) {
                    setTimeout(() => window.print(), 300);
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// OTHERWISE RENDER DIRECTORY LIST VIEW IN DASHBOARD LAYOUT
$pageTitle = 'POS Invoices & Transaction Receipts — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">POS Sales Invoices & Receipts</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect counter sales records, print thermal slips (80mm/58mm), or export transaction history.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="index.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-cash-register"></i> Open POS Terminal</a>
    </div>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Transaction #</th>
                    <th style="padding:16px 20px;">Order #</th>
                    <th style="padding:16px 20px;">Customer</th>
                    <th style="padding:16px 20px; text-align:right;">Subtotal</th>
                    <th style="padding:16px 20px; text-align:right;">Discount</th>
                    <th style="padding:16px 20px; text-align:right;">Total Due</th>
                    <th style="padding:16px 20px;">Date & Time</th>
                    <th style="padding:16px 20px; width:220px; text-align:right;">Print Thermal Formats</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $invoices = $pdo->query("
                        SELECT o.*, pt.transaction_number, pt.id as pos_tx_id, u.full_name as cust_name
                        FROM orders o
                        LEFT JOIN pos_transactions pt ON pt.order_id = o.id
                        LEFT JOIN users u ON u.id = o.user_id
                        WHERE o.order_number LIKE 'POS-%' OR pt.id IS NOT NULL
                        ORDER BY o.created_at DESC 
                        LIMIT 50
                    ")->fetchAll();
                } catch (PDOException $e) {
                    $invoices = [];
                }
                if (!empty($invoices)):
                    foreach ($invoices as $row):
                        $txNum = $row['transaction_number'] ?: $row['order_number'];
                        $custName = $row['cust_name'] ?: 'Walk-in Customer';
                ?>
                    <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                        <td style="padding:12px 20px;"><strong style="color:var(--admin-color-primary);"><?= e($txNum) ?></strong></td>
                        <td style="padding:12px 20px; font-weight:600;"><?= e($row['order_number']) ?></td>
                        <td style="padding:12px 20px;"><?= e($custName) ?></td>
                        <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['subtotal'], 2) ?></td>
                        <td style="padding:12px 20px; text-align:right; color:#e03131;">-৳<?= number_format((float)$row['discount_amount'], 2) ?></td>
                        <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_amount'], 2) ?></td>
                        <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                        <td style="padding:12px 20px; text-align:right;">
                            <div style="display:inline-flex; gap:4px;">
                                <a href="?id=<?= $row['id'] ?>&format=80mm" target="_blank" class="btn btn-secondary" style="padding:3px 8px; font-size:10px; border-radius:var(--radius-sm);"><i class="fas fa-print"></i> 80mm</a>
                                <a href="?id=<?= $row['id'] ?>&format=58mm" target="_blank" class="btn btn-secondary" style="padding:3px 8px; font-size:10px; border-radius:var(--radius-sm);"><i class="fas fa-receipt"></i> 58mm</a>
                                <a href="?id=<?= $row['id'] ?>&format=a4" target="_blank" class="btn btn-secondary" style="padding:3px 8px; font-size:10px; border-radius:var(--radius-sm);"><i class="fas fa-file-invoice"></i> A4</a>
                            </div>
                        </td>
                    </tr>
                <?php
                    endforeach;
                else:
                ?>
                    <tr>
                        <td colspan="8" style="padding:32px; text-align:center; color:var(--color-text-faint);">No counter sales invoices logged in the database yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
