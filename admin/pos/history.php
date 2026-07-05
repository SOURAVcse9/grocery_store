<?php
/**
 * ==========================================================================
 * admin/pos/history.php — POS Counter Sales History & Void Controller
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'POS Sales History — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.access');

$pdo = db();
$error = null;
$success = null;

// Handle Void Sale (requires pos.void permission)
if (method_is('post') && input('action', '') === 'void') {
    verify_csrf_or_fail();
    
    if (!has_admin_permission('pos.void')) {
        $error = 'You do not have administrative permission to void invoice checkouts.';
    } else {
        $orderId = (int) input('order_id', '0');

        try {
            $pdo->beginTransaction();

            // Load order status
            $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
            $stmtOrder->execute([$orderId]);
            $order = $stmtOrder->fetch();

            if (!$order || $order['status'] === 'cancelled') {
                throw new Exception("Selected order cannot be voided.");
            }

            // Restore stock levels and log stock movements
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$orderId]);
            $items = $stmtItems->fetchAll();

            $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmtLog = $pdo->prepare("
                INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at)
                VALUES (:pid, :admin_id, 'stock_in', :qty, :rem, :note, NOW())
            ");
            $stmtGetStock = $pdo->prepare("SELECT stock FROM products WHERE id = ?");

            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];

                $stmtUpdateStock->execute([$qty, $pid]);
                
                $stmtGetStock->execute([$pid]);
                $remStock = (int)$stmtGetStock->fetchColumn();

                $stmtLog->execute([
                    'pid'      => $pid,
                    'admin_id' => current_admin_id(),
                    'qty'      => $qty,
                    'rem'      => $remStock,
                    'note'     => "Voided POS Counter sales transaction Order #{$order['order_number']}"
                ]);
            }

            // Update order status to cancelled
            $stmtCancel = $pdo->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'refunded' WHERE id = ?");
            $stmtCancel->execute([$orderId]);

            // Adjust general ledger (payout refund entry)
            $pdo->prepare("
                INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
                VALUES ('expense', NULL, ?, ?, 'cash', 1, NOW())
            ")->execute([$order['total_amount'], "Voided POS Invoice refund: {$order['order_number']}"]);

            $pdo->commit();
            log_admin_activity('pos.void_sale', "Voided checkout invoice sales for order ID: {$orderId} / #{$order['order_number']}");
            $success = "Sales Invoice #{$order['order_number']} voided and inventory stock replenished successfully.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin/pos/history] void failed: ' . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}

$filterDate = trim(input('date_range', '', 'get')); // 'today', 'yesterday', 'all'
$where = ["order_number LIKE 'POS-%'"];

if ($filterDate === 'today') {
    $where[] = 'DATE(created_at) = CURRENT_DATE()';
} elseif ($filterDate === 'yesterday') {
    $where[] = 'DATE(created_at) = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    $stmt = $pdo->query("
        SELECT o.*, u.full_name AS customer_name 
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        {$whereClause}
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/pos/history] load logs failed: ' . $e->getMessage());
    $orders = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">POS Sales Registry</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect front-counter walk-in checkouts history, reprint invoices, and void transactions.</p>
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

<!-- Filters -->
<div class="dashboard-card" style="padding:var(--space-4); margin-bottom:var(--space-4);">
    <form method="get" style="display:flex; gap:12px; align-items:end; max-width:500px;">
        <div class="form-field-group" style="margin:0; flex:1;">
            <select name="date_range" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="all">All Historic POS Sales</option>
                <option value="today" <?= $filterDate === 'today' ? 'selected' : '' ?>>Today's Sales</option>
                <option value="yesterday" <?= $filterDate === 'yesterday' ? 'selected' : '' ?>>Yesterday's Sales</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter Logs</button>
    </form>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Order Number</th>
                    <th style="padding:16px 20px;">Customer Profile</th>
                    <th style="padding:16px 20px; text-align:right; width:120px;">Subtotal</th>
                    <th style="padding:16px 20px; text-align:right; width:100px;">Discount</th>
                    <th style="padding:16px 20px; text-align:right; width:120px;">Total Amount</th>
                    <th style="padding:16px 20px; width:100px; text-align:center;">Status</th>
                    <th style="padding:16px 20px; width:200px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $row): 
                        $isCancelled = ($row['status'] === 'cancelled');
                        $pill = $isCancelled ? 'pill-pending' : 'pill-completed';
                        $statusText = $isCancelled ? 'VOIDED' : 'PAID';
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle; opacity: <?= $isCancelled ? '0.6' : '1' ?>;">
                            <td style="padding:12px 20px;"><strong><?= e($row['order_number']) ?></strong></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($row['customer_name'] ?: 'Walk-in Customer') ?></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-faint);">৳<?= number_format((float)$row['subtotal'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; color:#e03131;">-৳<?= number_format((float)$row['discount_amount'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_amount'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:center;">
                                <span class="status-pill <?= $pill ?>" style="font-size:9px;"><?= $statusText ?></span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="receipts.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-print"></i> Reprint</a>
                                    
                                    <?php if (!$isCancelled && has_admin_permission('pos.void')): ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to void this invoice sale?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="void">
                                            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; border:none; cursor:pointer;"><i class="fas fa-ban"></i> Void</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="padding:32px; text-align:center; color:var(--color-text-faint);">No counter POS invoices recorded in selection.</td>
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
