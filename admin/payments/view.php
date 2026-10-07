<?php
/**
 * ==============================================================================
 * admin/payments/view.php — Payment Transaction Inspector & Refund Terminal
 * ==============================================================================
 * Comprehensive inspection of SSLCOMMERZ transactions, bank authentication,
 * risk assessment scores, chronological audit logs, and refund execution.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
require_once __DIR__ . '/../../public/includes/PaymentService.php';

require_admin_auth();

$pdo = db();
$paymentService = new PaymentService($pdo);

$paymentId = (int)input('id', '0', 'get');
if ($paymentId <= 0) {
    flash('payments_msg', 'Invalid payment identifier.', 'error');
    redirect('index.php');
}

// Fetch Payment Details
$pStmt = $pdo->prepare('
    SELECT p.*, o.order_number, o.total_amount as order_total, o.payment_status as order_pay_status,
           u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM payments p
    LEFT JOIN orders o ON o.id = p.order_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
');
$pStmt->execute([$paymentId]);
$payment = $pStmt->fetch();

if (!$payment) {
    flash('payments_msg', 'Payment record not found.', 'error');
    redirect('index.php');
}

// Handle Gateway Synchronization Action
if (method_is('post') && input('action', '') === 'sync_gateway') {
    verify_csrf_or_fail();
    $syncResult = $paymentService->syncTransactionStatus((string)$payment['tran_id']);
    if ($syncResult['success']) {
        flash('payments_msg', 'Gateway synchronized successfully. Status: ' . ($syncResult['gateway_status'] ?? 'Updated'), 'success');
    } else {
        flash('payments_msg', 'Gateway synchronization failed: ' . ($syncResult['error'] ?? 'Unknown error'), 'error');
    }
    redirect("view.php?id={$paymentId}");
}

// Handle Refund Initiation Action
if (method_is('post') && input('action', '') === 'initiate_refund') {
    verify_csrf_or_fail();
    require_admin_permission('orders.edit');

    $refundAmount = (float)input('refund_amount', '0');
    $refundReason = trim((string)input('refund_reason', ''));

    if ($refundAmount <= 0) {
        flash('payments_msg', 'Refund amount must be greater than zero.', 'error');
    } elseif ($refundReason === '') {
        flash('payments_msg', 'Please provide a justification for this refund.', 'error');
    } else {
        $adminId = isset($_SESSION['admin_user']['id']) ? (int)$_SESSION['admin_user']['id'] : null;
        $refRes = $paymentService->processRefund($paymentId, $refundAmount, $refundReason, $adminId);

        if ($refRes['success']) {
            flash('payments_msg', "Refund of ৳" . number_format($refundAmount, 2) . " processed successfully via SSLCOMMERZ.", 'success');
        } else {
            flash('payments_msg', "Refund failed: " . ($refRes['error'] ?? 'Gateway rejected refund.'), 'error');
        }
    }
    redirect("view.php?id={$paymentId}");
}

// Fetch Refunds History
$refStmt = $pdo->prepare('SELECT * FROM payment_refunds WHERE payment_id = ? ORDER BY id DESC');
$refStmt->execute([$paymentId]);
$refunds = $refStmt->fetchAll();

// Fetch Audit Trail Logs
$auditStmt = $pdo->prepare('SELECT * FROM payment_audit_logs WHERE payment_id = ? OR tran_id = ? ORDER BY id ASC');
$auditStmt->execute([$paymentId, $payment['tran_id']]);
$auditLogs = $auditStmt->fetchAll();

$paidAmount = (float)$payment['amount'];
$refundedAmount = (float)$payment['refunded_amount'];
$maxRefundable = max(0.0, round($paidAmount - $refundedAmount, 2));

$pageTitle = 'Payment #' . $payment['tran_id'];
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="admin-content-body">
    <!-- Breadcrumb & Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">
                <a href="index.php" style="color: #6366f1; text-decoration: none;">Payments</a> &gt; Transaction Details
            </div>
            <h1 style="font-size: 24px; font-weight: 800; margin: 0; color: #1e293b;">
                Transaction: <span style="font-family: monospace; color: #6366f1;"><?= e($payment['tran_id']) ?></span>
            </h1>
        </div>
        <div style="display: flex; gap: 10px;">
            <form method="post" style="display: inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="sync_gateway">
                <button type="submit" class="btn btn-secondary" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 16px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-rotate"></i> Query Gateway Status
                </button>
            </form>
            <a href="index.php" class="btn btn-secondary" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 16px; font-weight: 600; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Main Grid Layout -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        
        <!-- Left Column: Details & Audit Trail -->
        <div>
            <!-- Transaction Overview Card -->
            <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px;">
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0; color: #1e293b;">
                        <i class="fas fa-shield-halved" style="color: #6366f1;"></i> Gateway Identification & State
                    </h2>
                    <span style="padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 800; background: <?= $payment['status'] === 'PAID' ? '#dcfce7' : '#fef3c7' ?>; color: <?= $payment['status'] === 'PAID' ? '#166534' : '#92400e' ?>;">
                        <?= e($payment['status']) ?>
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; font-size: 13px;">
                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">Authorized Amount</div>
                        <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px;">৳<?= number_format((float)$payment['amount'], 2) ?> <?= e($payment['currency']) ?></div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">Order Reference</div>
                        <div style="font-size: 16px; font-weight: 700; margin-top: 4px;">
                            <a href="../orders/view.php?id=<?= $payment['order_id'] ?>" style="color: #6366f1; text-decoration: none;">
                                #<?= e($payment['order_number'] ?? $payment['order_id']) ?>
                            </a>
                        </div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">Bank Transaction ID</div>
                        <div style="font-family: monospace; font-weight: 700; color: #334155; margin-top: 4px;"><?= e($payment['bank_tran_id'] ?? '—') ?></div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">Validation Token (val_id)</div>
                        <div style="font-family: monospace; font-size: 12px; color: #334155; margin-top: 4px;"><?= e($payment['val_id'] ?? '—') ?></div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">Payment Gateway / Method</div>
                        <div style="font-weight: 600; text-transform: uppercase; margin-top: 4px;"><?= e($payment['provider']) ?> (<?= e($payment['payment_method']) ?>)</div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">Card Type / Channel</div>
                        <div style="font-weight: 600; margin-top: 4px;"><?= e($payment['card_type'] ?? '—') ?></div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">Verification Status</div>
                        <div style="font-weight: 700; margin-top: 4px; color: <?= $payment['verification_status'] === 'VALIDATED' ? '#166534' : '#dc2626' ?>;">
                            <?= e($payment['verification_status']) ?>
                        </div>
                    </div>

                    <div>
                        <div style="color: #64748b; font-size: 12px; font-weight: 600;">IPN Webhook Status</div>
                        <div style="margin-top: 4px;">
                            <?php if ((int)$payment['ipn_received'] === 1): ?>
                                <span style="color: #059669; font-weight: 700;"><i class="fas fa-check-double"></i> Received (<?= date('M d, H:i', strtotime($payment['ipn_received_at'])) ?>)</span>
                            <?php else: ?>
                                <span style="color: #94a3b8;"><i class="fas fa-clock"></i> Not Yet Delivered</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($payment['failure_reason'])): ?>
                        <div style="grid-column: span 2; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-top: 10px;">
                            <strong style="color: #991b1b;"><i class="fas fa-circle-exclamation"></i> Failure / Audit Reason:</strong>
                            <div style="color: #b91c1c; font-size: 13px; margin-top: 4px;"><?= e($payment['failure_reason']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Audit Trail Timeline Card -->
            <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #1e293b;">
                    <i class="fas fa-timeline" style="color: #6366f1;"></i> Chronological Audit & Security Trail
                </h2>

                <div style="position: relative; padding-left: 20px; border-left: 2px solid #e2e8f0;">
                    <?php if (empty($auditLogs)): ?>
                        <p style="color: #64748b; font-size: 13px;">No explicit audit entries recorded for this transaction.</p>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <div style="margin-bottom: 20px; position: relative;">
                                <div style="position: absolute; left: -26px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: #6366f1; border: 2px solid #fff;"></div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <strong style="font-size: 13px; color: #0f172a;"><?= e($log['event']) ?></strong>
                                    <span style="font-size: 11px; color: #64748b;"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></span>
                                </div>
                                <div style="font-size: 12px; color: #475569;">
                                    <?php if ($log['old_status'] || $log['new_status']): ?>
                                        State: <code style="background: #f1f5f9; padding: 2px 4px; border-radius: 4px;"><?= e($log['old_status'] ?? 'NONE') ?></code> &rarr; <code style="background: #f1f5f9; padding: 2px 4px; border-radius: 4px; font-weight: 700;"><?= e($log['new_status'] ?? 'NONE') ?></code> |
                                    <?php endif; ?>
                                    IP: <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                                </div>
                                <?php if (!empty($log['error_message'])): ?>
                                    <div style="font-size: 12px; color: #dc2626; margin-top: 4px; font-weight: 600;">
                                        <?= e($log['error_message']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Customer Info & Refund Management -->
        <div>
            <!-- Customer Card -->
            <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="font-size: 14px; font-weight: 700; margin: 0 0 14px 0; color: #1e293b;">
                    <i class="fas fa-user"></i> Customer Information
                </h3>
                <div style="font-size: 13px; color: #334155; line-height: 1.6;">
                    <div><strong>Name:</strong> <?= e($payment['customer_name'] ?? 'Guest Customer') ?></div>
                    <div><strong>Email:</strong> <?= e($payment['customer_email'] ?? '—') ?></div>
                    <div><strong>Phone:</strong> <?= e($payment['customer_phone'] ?? '—') ?></div>
                </div>
            </div>

            <!-- Refund Management Terminal -->
            <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="font-size: 14px; font-weight: 700; margin: 0 0 14px 0; color: #1e293b;">
                    <i class="fas fa-arrow-rotate-left" style="color: #ef4444;"></i> Refund Operations
                </h3>

                <div style="background: #f8fafc; border-radius: 8px; padding: 14px; margin-bottom: 16px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>Total Paid:</span>
                        <strong>৳<?= number_format($paidAmount, 2) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>Total Refunded:</span>
                        <strong style="color: #dc2626;">৳<?= number_format($refundedAmount, 2) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px dashed #cbd5e1; padding-top: 8px; font-weight: 700; color: #059669;">
                        <span>Max Refundable:</span>
                        <span>৳<?= number_format($maxRefundable, 2) ?></span>
                    </div>
                </div>

                <?php if (in_array($payment['status'], ['PAID', 'PARTIALLY_REFUNDED'], true) && $maxRefundable > 0 && !empty($payment['bank_tran_id'])): ?>
                    <form method="post" onsubmit="return confirm('Confirm processing this refund of ৳' + document.getElementById('refund_amount_input').value + ' via SSLCOMMERZ?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="initiate_refund">

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Refund Amount (BDT) *</label>
                            <input type="number" id="refund_amount_input" name="refund_amount" step="0.01" min="1" max="<?= $maxRefundable ?>" value="<?= $maxRefundable ?>" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 700;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Reason for Refund *</label>
                            <textarea name="refund_reason" rows="2" placeholder="e.g. Customer requested cancellation / damaged goods..." required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; resize: vertical;"></textarea>
                        </div>

                        <button type="submit" style="width: 100%; padding: 10px; background: #dc2626; color: #fff; border: none; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer;">
                            <i class="fas fa-hand-holding-dollar"></i> Issue Official Refund
                        </button>
                    </form>
                <?php else: ?>
                    <div style="font-size: 12px; color: #64748b; text-align: center; padding: 10px; background: #f1f5f9; border-radius: 6px;">
                        <?= $maxRefundable <= 0 ? 'Payment has been fully refunded.' : 'Payment is not eligible for refund.' ?>
                    </div>
                <?php endif; ?>

                <!-- Refund History List -->
                <?php if (!empty($refunds)): ?>
                    <div style="margin-top: 18px; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                        <div style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px;">Refund Records</div>
                        <?php foreach ($refunds as $ref): ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 8px; font-size: 12px;">
                                <div style="display: flex; justify-content: space-between; font-weight: 700;">
                                    <span>৳<?= number_format((float)$ref['refund_amount'], 2) ?></span>
                                    <span style="color: <?= $ref['status'] === 'COMPLETED' ? '#166534' : '#dc2626' ?>;"><?= e($ref['status']) ?></span>
                                </div>
                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Ref: <?= e($ref['refund_ref_id'] ?? $ref['refund_trans_id']) ?></div>
                                <div style="font-size: 11px; color: #475569; margin-top: 2px;"><?= e($ref['reason']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
