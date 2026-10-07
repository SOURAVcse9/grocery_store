<?php
/**
 * ==========================================================================
 * admin/pos/returns.php — POS Returns, Refunds & Voids
 * ==========================================================================
 * Rules enforced server-side (includes/pos_lib.php):
 *  - references the original sale; the sale row is never deleted
 *  - cannot return more than purchased minus already-returned (no duplicates)
 *  - refund = what the customer actually paid per unit (discounts pro-rated)
 *  - reason mandatory; large refunds can require manager approval
 *  - VOID of a whole sale requires pos.override (manager)
 */

declare(strict_types=1);

$pageTitle = 'POS Returns & Refunds — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_once __DIR__ . '/../includes/pos_lib.php';
require_admin_permission('pos.return');

$pdo = db();
$adminId = (int) current_admin_id();
$error = null;
$success = null;

$canOverride = has_admin_permission('pos.override');
$canVoid = $canOverride || has_admin_permission('pos.void');
$searchOrder = trim(input('order_number', '', 'get'));
$can = static fn(string $perm): bool => has_admin_permission($perm);

// ---------------------------------------------------------------- POST actions
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $action  = (string) input('pos_action', 'return');
        $orderId = (int) input('order_id', '0');
        try {
            if ($action === 'void') {
                if (!$canVoid) {
                    log_admin_activity('permission_denied', 'Attempted POS void without pos.void');
                    throw new PosException('Voiding a completed sale requires manager authorization (pos.void or pos.override).');
                }
                $v = pos_void_sale($pdo, $orderId, (string) input('reason', ''), $adminId);
                log_admin_activity('pos.void', "Voided POS sale {$v['order_number']} (৳{$v['total']}). Reason: " . pos_clip((string) input('reason', ''), 250));
                $success = 'Sale ' . e($v['order_number']) . ' voided. Stock restored and ledger reversed; the original record is kept.';
                $searchOrder = $v['order_number'];
            } else {
                $returns = [];
                foreach ((array) ($_POST['returns'] ?? []) as $pid => $q) {
                    $returns[(int) $pid] = is_scalar($q) ? $q : 0;
                }
                $r = pos_process_return($pdo, $orderId, $returns, (string) input('refund_method', 'cash'), (string) input('reason', ''), $adminId, $can);
                log_admin_activity('pos.return', "Return #{$r['return_id']} refund ৳{$r['refund']} for {$r['order_number']}. Reason: " . pos_clip((string) input('reason', ''), 250));
                $success = 'Return #' . $r['return_id'] . ' processed. Refund of ৳' . number_format($r['refund'], 2) . ' settled.';
                $searchOrder = $r['order_number'];
            }
        } catch (PosException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('[admin/pos/returns] failed: ' . $e->getMessage());
            $error = 'The operation failed due to a server error. Nothing was changed.';
        }
    }
}

// ------------------------------------------------------------ load order view
$order = null;
$items = [];
$already = [];
$history = [];
$factor = 1.0;
if ($searchOrder !== '') {
    try {
        $st = $pdo->prepare('SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.order_number = ? LIMIT 1');
        $st->execute([$searchOrder]);
        $order = $st->fetch() ?: null;
        if (!$order) {
            $error = $error ?? 'No sale found with reference "' . $searchOrder . '".';
        } elseif (strpos((string) $order['order_number'], 'POS-') !== 0) {
            $error = $error ?? 'That is an online order. Handle its returns from Orders, not the POS.';
            $order = null;
        } else {
            $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
            $st->execute([$order['id']]);
            $items = $st->fetchAll();
            $already = pos_returned_qty_map($pdo, (int) $order['id']);
            $factor = pos_refund_factor($order, $items);
            $st = $pdo->prepare('SELECT r.id, r.refund_amount, r.refund_method, r.created_at, a.username FROM pos_returns r LEFT JOIN admins a ON a.id = r.admin_id WHERE r.order_id = ? ORDER BY r.id DESC');
            $st->execute([$order['id']]);
            $history = $st->fetchAll();
        }
    } catch (Throwable $e) {
        error_log('[admin/pos/returns] load order failed: ' . $e->getMessage());
        $error = $error ?? 'Could not load the sale.';
    }
}
$walkinId = 0;
try { $walkinId = pos_walkin_customer_id($pdo); } catch (Throwable $e) { $walkinId = 0; }
$isVoided = $order && $order['status'] === 'cancelled';
$decimalOk = pos_decimal_qty_enabled($pdo);
$field = 'width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:var(--color-surface); color:var(--color-text);';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Returns, Refunds &amp; Voids</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Every return references the original sale. Sales are never deleted &mdash; they are returned or voided.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<?php if ($success !== null): ?>
    <div role="status" style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div role="alert" style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Find POS Invoice</h3>
        <form method="get" style="display:flex; flex-direction:column; gap:12px;">
            <div class="form-field-group" style="margin:0;">
                <label for="retOrderNo" style="font-weight:700;">Receipt / Order Number *</label>
                <input id="retOrderNo" type="text" name="order_number" required autofocus value="<?= e($searchOrder) ?>" placeholder="POS-20260705-123456" style="<?= $field ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-magnifying-glass"></i> Search Invoice</button>
        </form>
        <?php if ($order && !empty($history)): ?>
            <h4 style="font-size:12px; font-weight:800; margin:18px 0 8px;">Previous returns on this sale</h4>
            <?php foreach ($history as $h): ?>
                <div style="font-size:12px; display:flex; justify-content:space-between; border-bottom:1px solid var(--color-border); padding:6px 0;">
                    <span>#<?= (int) $h['id'] ?> &bull; <?= e($h['refund_method']) ?> &bull; <?= e($h['username'] ?? '') ?><br><span style="color:var(--color-text-faint);"><?= e($h['created_at']) ?></span></span>
                    <strong>৳<?= number_format((float) $h['refund_amount'], 2) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Return Items Selection</h3>

        <?php if ($order && !empty($items)): ?>
            <div style="font-size:12px; color:var(--color-text-muted); display:flex; gap:20px; flex-wrap:wrap; margin-bottom:16px; background:var(--color-bg); padding:10px; border-radius:var(--radius-sm);">
                <span>Sale: <strong><?= e($order['order_number']) ?></strong></span>
                <span>Date: <strong><?= e($order['created_at']) ?></strong></span>
                <span>Customer: <strong><?= e($order['customer_name'] ?? 'Walk-in') ?></strong></span>
                <span>Total paid: <strong>৳<?= number_format((float) $order['total_amount'], 2) ?></strong></span>
                <?php if ($isVoided): ?><span style="color:#e03131; font-weight:800;">VOIDED</span><?php endif; ?>
            </div>

            <?php if ($isVoided): ?>
                <p style="color:#e03131; font-weight:600;">This sale was voided and cannot be returned.</p>
            <?php else: ?>
            <form method="post" class="auth-form" id="returnForm">
                <?= csrf_field() ?>
                <input type="hidden" name="pos_action" value="return">
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">

                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
                    <?php foreach ($items as $row):
                        $pid = (int) $row['product_id'];
                        $remaining = max(0.0, (float) $row['quantity'] - ($already[$pid] ?? 0.0));
                        $unitPaid = ((float) $row['line_total'] / max((float) $row['quantity'], 0.001)) * $factor;
                    ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border); padding-bottom:8px; font-size:13px;">
                            <div style="flex:2;">
                                <strong><?= e($row['product_name']) ?></strong><br>
                                <span style="font-size:11px; color:var(--color-text-faint);">
                                    Bought <?= e(pos_fmt_qty((float) $row['quantity'])) ?> &bull; already returned <?= e(pos_fmt_qty($already[$pid] ?? 0.0)) ?> &bull;
                                    refundable <strong><?= e(pos_fmt_qty($remaining)) ?></strong> @ ৳<?= number_format($unitPaid, 2) ?> paid each
                                </span>
                            </div>
                            <div style="flex:1; text-align:right;">
                                <label for="ret<?= $pid ?>" style="font-size:10px; color:var(--color-text-faint); display:block;">Return Qty</label>
                                <input id="ret<?= $pid ?>" type="number" inputmode="decimal" name="returns[<?= $pid ?>]" min="0" max="<?= e(pos_fmt_qty($remaining)) ?>" step="<?= $decimalOk ? '0.001' : '1' ?>" value="0" <?= $remaining <= 0 ? 'disabled' : '' ?> style="width:80px; padding:6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:center; background:var(--color-surface); color:var(--color-text);">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;" class="grid-2">
                    <div class="form-field-group" style="margin:0;">
                        <label for="refundMethod" style="font-weight:700;">Refund Method</label>
                        <select id="refundMethod" name="refund_method" style="<?= $field ?>">
                            <option value="cash">Cash from drawer</option>
                            <?php if ((int) $order['user_id'] > 0 && (int) $order['user_id'] !== $walkinId): ?>
                                <option value="wallet">Credit customer wallet</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-field-group" style="margin:0;">
                        <label for="retReason" style="font-weight:700;">Reason *</label>
                        <input id="retReason" type="text" name="reason" required maxlength="250" placeholder="Damaged / wrong item / customer changed mind" style="<?= $field ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-rotate-left"></i> Settle Return &amp; Refund</button>
            </form>

            <?php if ($canVoid && empty($history)): ?>
                <form method="post" onsubmit="return confirm('VOID this entire sale? Stock will be restored and the ledger reversed. This cannot be undone.');" style="margin-top:20px; border-top:1px dashed var(--color-border); padding-top:16px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="void">
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <label for="voidReason" style="font-weight:700; font-size:12px;">Manager void &mdash; reason *</label>
                    <div style="display:flex; gap:10px; margin-top:6px;">
                        <input id="voidReason" type="text" name="reason" required maxlength="250" placeholder="E.g. cashier scanned wrong basket" style="<?= $field ?>">
                        <button type="submit" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; color:#e03131; white-space:nowrap;"><i class="fas fa-ban"></i> Void Sale</button>
                    </div>
                </form>
            <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <p style="text-align:center; color:var(--color-text-faint); font-size:13px; margin:0; padding:32px;">Search a POS receipt number to load its lines.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
