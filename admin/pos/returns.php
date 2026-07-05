<?php
/**
 * ==========================================================================
 * admin/pos/returns.php — POS Returns & Refunds Management
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'POS Returns & Exchange — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.return');

$pdo = db();
$error = null;
$success = null;

$searchOrder = trim(input('order_number', '', 'get'));
$order = null;
$items = [];

if (!empty($searchOrder)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :num LIMIT 1");
        $stmt->execute(['num' => $searchOrder]);
        $order = $stmt->fetch();

        if ($order) {
            $stmtItems = $pdo->prepare("
                SELECT oi.*, p.name AS product_name, p.stock
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
            ");
            $stmtItems->execute([$order['id']]);
            $items = $stmtItems->fetchAll();
        } else {
            $error = "Order with number '{$searchOrder}' was not found in the database.";
        }
    } catch (PDOException $e) {
        error_log('[admin/pos/returns] load order failed: ' . $e->getMessage());
    }
}

// Handle Return Submission
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $orderId = (int) input('order_id', '0');
        $refundMethod = input('refund_method', 'cash');
        $returns = $_POST['returns'] ?? []; // format: array of [product_id] => quantity_returned

        if ($orderId <= 0 || empty($returns)) {
            $error = 'No items specified for refund return.';
        } else {
            try {
                $pdo->beginTransaction();

                // Calculate refund amount
                $refundTotal = 0.0;
                $stmtPrice = $pdo->prepare("SELECT price FROM order_items WHERE order_id = ? AND product_id = ? LIMIT 1");
                
                $returnItemsList = [];
                foreach ($returns as $prodId => $qty) {
                    $prodId = (int)$prodId;
                    $qty = (int)$qty;
                    if ($qty > 0) {
                        $stmtPrice->execute([$orderId, $prodId]);
                        $unitPrice = (float) $stmtPrice->fetchColumn();
                        $refundTotal += ($unitPrice * $qty);
                        $returnItemsList[] = ['pid' => $prodId, 'qty' => $qty];
                    }
                }

                if ($refundTotal > 0) {
                    // Create return log
                    $stmtRet = $pdo->prepare("INSERT INTO pos_returns (order_id, admin_id, refund_amount, refund_method, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmtRet->execute([$orderId, current_admin_id(), $refundTotal, $refundMethod]);
                    $returnId = (int)$pdo->lastInsertId();

                    $stmtRetItem = $pdo->prepare("INSERT INTO pos_return_items (pos_return_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    
                    $stmtLog = $pdo->prepare("
                        INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at)
                        VALUES (:pid, :admin_id, 'stock_in', :qty, :rem, :note, NOW())
                    ");
                    $stmtGetStock = $pdo->prepare("SELECT stock FROM products WHERE id = ?");

                    foreach ($returnItemsList as $retItem) {
                        $pid = $retItem['pid'];
                        $qty = $retItem['qty'];

                        // Insert return item
                        $stmtRetItem->execute([$returnId, $pid, $qty]);
                        
                        // Restock product
                        $stmtUpdateStock->execute([$qty, $pid]);

                        // Get remaining stock
                        $stmtGetStock->execute([$pid]);
                        $remStock = (int)$stmtGetStock->fetchColumn();

                        // Log inventory movement
                        $stmtLog->execute([
                            'pid'      => $pid,
                            'admin_id' => current_admin_id(),
                            'qty'      => $qty,
                            'rem'      => $remStock,
                            'note'     => "Customer merchandise return for POS Order #{$searchOrder}"
                        ]);
                    }

                    // If refund to wallet chosen, and customer exists, credit wallet
                    if ($refundMethod === 'wallet' && $order['user_id'] !== null) {
                        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$refundTotal, $order['user_id']]);
                    }

                    // Post expense transaction in general ledger (as customer refund)
                    $pdo->prepare("
                        INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
                        VALUES ('expense', (SELECT id FROM expense_categories WHERE name = 'Customer Refunds' LIMIT 1), ?, ?, 'cash', 1, NOW())
                    ")->execute([$refundTotal, "POS Refund for Order: {$searchOrder}"]);

                    $pdo->commit();
                    log_admin_activity('pos.return', "Processed product return and refund value ৳{$refundTotal} for POS Order: '{$searchOrder}'");
                    $success = "POS return processed successfully! Refund amount of ৳" . number_format($refundTotal, 2) . " settled.";
                    $order = null;
                    $items = [];
                } else {
                    $pdo->rollBack();
                    $error = 'Refund item quantities must be greater than zero.';
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin/pos/returns] Save return failed: ' . $e->getMessage());
                $error = 'Failed to process return due to database transaction error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Returns & Exchanges</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Process client merchandise returns, restore catalog inventory levels, and dispense cash refunds.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<!-- Alerts -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Search order -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Find POS Invoice</h3>
        <form method="get" style="display:flex; flex-direction:column; gap:12px;">
            <div class="form-field-group" style="margin:0;">
                <label style="font-weight:700;">Order Reference Number *</label>
                <input type="text" name="order_number" required value="<?= e($searchOrder) ?>" placeholder="E.g. POS-20260705-1234" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-magnifying-glass"></i> Search Invoice</button>
        </form>
    </div>

    <!-- Right Column: Return details form -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Return Items Selection</h3>
        
        <?php if ($order && !empty($items)): ?>
            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                <div style="font-size:12px; color:var(--color-text-muted); display:flex; gap:20px; margin-bottom:16px; background:var(--color-bg); padding:10px; border-radius:var(--radius-sm);">
                    <span>Order: <strong><?= e($order['order_number']) ?></strong></span>
                    <span>Total Paid: <strong>৳<?= number_format((float)$order['total_amount'], 2) ?></strong></span>
                </div>

                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
                    <?php foreach ($items as $row): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border); padding-bottom:8px; font-size:13px;">
                            <div style="flex:2;">
                                <strong><?= e($row['product_name']) ?></strong><br>
                                <span style="font-size:10px; color:var(--color-text-faint);">Purchase qty: <?= $row['quantity'] ?> units &bull; ৳<?= number_format((float)$row['price'], 2) ?> each</span>
                            </div>
                            <div style="flex:1; text-align:right;">
                                <label style="font-size:10px; color:var(--color-text-faint); display:block;">Return Quantity</label>
                                <input type="number" name="returns[<?= $row['product_id'] ?>]" min="0" max="<?= $row['quantity'] ?>" value="0" style="width:70px; padding:4px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:center;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;" class="grid-2">
                    <div class="form-field-group" style="margin:0;">
                        <label style="font-weight:700;">Refund Settled Method</label>
                        <select name="refund_method" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                            <option value="cash">Dispense Cash Drawer Refund</option>
                            <?php if ($order['user_id'] !== null): ?>
                                <option value="wallet">Credit Customer Digital Wallet</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-rotate-left"></i> Settle Return & Refund</button>
            </form>
        <?php else: ?>
            <p style="text-align:center; color:var(--color-text-faint); font-size:13px; margin:0; padding:32px;">Please search for a valid POS reference number in the left panel to load invoice lines.</p>
        <?php endif; ?>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
