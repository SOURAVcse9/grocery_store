<?php
/**
 * ==============================================================================
 * admin/payments/reconciliation.php — Payment Gateway Reconciliation Dashboard
 * ==============================================================================
 * Real-time financial reconciliation engine. Detects and resolves:
 * 1. Status Mismatches between Gateway ledger and Order state.
 * 2. Amount Mismatches between authorized totals and order values.
 * 3. Stale Pending Transactions (abandoned checkout sessions > 30m).
 * 4. Missing IPN deliveries where only client callback succeeded.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
require_once __DIR__ . '/../../public/includes/PaymentService.php';

require_admin_auth();

$pdo = db();
$paymentService = new PaymentService($pdo);

// Handle Manual Re-sync Action
if (method_is('post') && input('action', '') === 'sync_transaction') {
    verify_csrf_or_fail();
    $targetTranId = trim((string)input('tran_id', ''));
    if ($targetTranId !== '') {
        $res = $paymentService->syncTransactionStatus($targetTranId);
        if ($res['success']) {
            flash('recon_msg', "Transaction {$targetTranId} reconciled successfully with gateway.", 'success');
        } else {
            flash('recon_msg', "Reconciliation query failed: " . ($res['error'] ?? 'Unknown error'), 'error');
        }
    }
    redirect('reconciliation.php');
}

// 1. Status Mismatch Anomaly: Payment is PAID, but Order is NOT paid
$statusMismatches = $pdo->query("
    SELECT p.id, p.tran_id, p.amount, p.status as payment_status, p.created_at,
           o.id as order_id, o.order_number, o.payment_status as order_pay_status
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE p.status = 'PAID' AND o.payment_status != 'paid'
")->fetchAll();

// 2. Amount Mismatch Anomaly: Payment amount differs from order amount
$amountMismatches = $pdo->query("
    SELECT p.id, p.tran_id, p.amount as payment_amount, p.status as payment_status, p.verification_status,
           o.id as order_id, o.order_number, o.total_amount as order_amount
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE (p.verification_status = 'AMOUNT_MISMATCH' OR ABS(p.amount - o.total_amount) >= 0.01) AND p.status IN ('PAID', 'REVIEW_REQUIRED')
")->fetchAll();

// 3. Stale In-Flight Pending (> 30 minutes old)
$stalePending = $pdo->query("
    SELECT p.id, p.tran_id, p.amount, p.status, p.created_at,
           o.id as order_id, o.order_number
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE p.status IN ('INITIATED', 'PENDING') 
      AND p.created_at < (NOW() - INTERVAL 30 MINUTE)
    ORDER BY p.id DESC
    LIMIT 20
")->fetchAll();

// 4. Missing IPNs (Paid via browser return, but server IPN never arrived)
$missingIpns = $pdo->query("
    SELECT p.id, p.tran_id, p.amount, p.paid_at,
           o.id as order_id, o.order_number
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE p.status = 'PAID' AND p.ipn_received = 0
    ORDER BY p.id DESC
    LIMIT 20
")->fetchAll();

$totalAnomalies = count($statusMismatches) + count($amountMismatches);

$pageTitle = 'Payment Gateway Reconciliation';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="admin-content-body">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">
                <a href="index.php" style="color: #6366f1; text-decoration: none;">Payments</a> &gt; Gateway Reconciliation
            </div>
            <h1 style="font-size: 24px; font-weight: 800; margin: 0; color: #1e293b;">
                <i class="fas fa-scale-balanced" style="color: #6366f1;"></i> Financial & Gateway Reconciliation
            </h1>
            <p style="margin: 0; color: #64748b; font-size: 14px;">
                Real-time anomaly detection between SSLCOMMERZ gateway, internal payments ledger, and orders table.
            </p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 16px; font-weight: 600; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Reconciliation Status Alert Banner -->
    <?php if ($totalAnomalies === 0): ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 18px 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
            <div style="font-size: 28px; color: #059669;"><i class="fas fa-circle-check"></i></div>
            <div>
                <strong style="color: #065f46; font-size: 15px;">All Payments Reconciled & Healthy</strong>
                <div style="color: #047857; font-size: 13px; margin-top: 2px;">
                    Zero critical discrepancies detected between order records and gateway transactions.
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 18px 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
            <div style="font-size: 28px; color: #dc2626;"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <strong style="color: #991b1b; font-size: 15px;"><?= $totalAnomalies ?> Financial Discrepancies Require Attention</strong>
                <div style="color: #b91c1c; font-size: 13px; margin-top: 2px;">
                    Action required to ensure financial books match gateway records accurately.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Anomaly Section 1: Amount Mismatches -->
    <?php if (!empty($amountMismatches)): ?>
        <div style="background: #fff; border-radius: 12px; border: 1px solid #fee2e2; margin-bottom: 24px; overflow: hidden;">
            <div style="padding: 16px 20px; background: #fef2f2; border-bottom: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center;">
                <strong style="color: #991b1b; font-size: 14px;"><i class="fas fa-money-bill-wave"></i> Amount Discrepancies (Critical Risk)</strong>
                <span class="badge" style="background: #dc2626; color: #fff;"><?= count($amountMismatches) ?> Found</span>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        <th style="padding: 12px 16px;">Tran ID</th>
                        <th style="padding: 12px 16px;">Order #</th>
                        <th style="padding: 12px 16px;">Gateway Amount</th>
                        <th style="padding: 12px 16px;">Order Total</th>
                        <th style="padding: 12px 16px;">Difference</th>
                        <th style="padding: 12px 16px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($amountMismatches as $row): 
                        $diff = (float)$row['payment_amount'] - (float)$row['order_amount'];
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 16px; font-family: monospace; font-weight: 700;"><?= e($row['tran_id']) ?></td>
                            <td style="padding: 12px 16px;">#<?= e($row['order_number']) ?></td>
                            <td style="padding: 12px 16px; font-weight: 700;">৳<?= number_format((float)$row['payment_amount'], 2) ?></td>
                            <td style="padding: 12px 16px; font-weight: 700;">৳<?= number_format((float)$row['order_amount'], 2) ?></td>
                            <td style="padding: 12px 16px; color: #dc2626; font-weight: 700;">৳<?= number_format($diff, 2) ?></td>
                            <td style="padding: 12px 16px; text-align: right;">
                                <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: 6px; text-decoration: none;">Inspect</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Anomaly Section 2: Status Mismatches -->
    <?php if (!empty($statusMismatches)): ?>
        <div style="background: #fff; border-radius: 12px; border: 1px solid #fef3c7; margin-bottom: 24px; overflow: hidden;">
            <div style="padding: 16px 20px; background: #fffbeb; border-bottom: 1px solid #fef3c7; display: flex; justify-content: space-between; align-items: center;">
                <strong style="color: #92400e; font-size: 14px;"><i class="fas fa-arrows-rotate"></i> Order Payment Status Mismatches (Ledger vs Order)</strong>
                <span class="badge" style="background: #d97706; color: #fff;"><?= count($statusMismatches) ?> Found</span>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        <th style="padding: 12px 16px;">Tran ID</th>
                        <th style="padding: 12px 16px;">Order #</th>
                        <th style="padding: 12px 16px;">Ledger Status</th>
                        <th style="padding: 12px 16px;">Order Payment Status</th>
                        <th style="padding: 12px 16px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statusMismatches as $row): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 16px; font-family: monospace; font-weight: 700;"><?= e($row['tran_id']) ?></td>
                            <td style="padding: 12px 16px;">#<?= e($row['order_number']) ?></td>
                            <td style="padding: 12px 16px;"><span class="badge" style="background: #dcfce7; color: #166534;"><?= e($row['payment_status']) ?></span></td>
                            <td style="padding: 12px 16px;"><span class="badge" style="background: #fee2e2; color: #991b1b;"><?= e($row['order_pay_status']) ?></span></td>
                            <td style="padding: 12px 16px; text-align: right;">
                                <form method="post" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="sync_transaction">
                                    <input type="hidden" name="tran_id" value="<?= e($row['tran_id']) ?>">
                                    <button type="submit" style="padding: 6px 12px; background: #6366f1; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">Sync</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Section 3: Stale In-Flight Pending Transactions -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <strong style="color: #334155; font-size: 14px;"><i class="fas fa-hourglass-half"></i> Stale In-Flight Transactions (> 30 minutes old)</strong>
            <span style="font-size: 12px; color: #64748b;"><?= count($stalePending) ?> records</span>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; text-transform: uppercase;">
                    <th style="padding: 12px 16px;">Tran ID</th>
                    <th style="padding: 12px 16px;">Order #</th>
                    <th style="padding: 12px 16px;">Amount</th>
                    <th style="padding: 12px 16px;">Initiated At</th>
                    <th style="padding: 12px 16px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stalePending)): ?>
                    <tr>
                        <td colspan="5" style="padding: 24px; text-align: center; color: #64748b;">
                            No stuck in-flight transactions detected.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stalePending as $row): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 16px; font-family: monospace; font-weight: 700;"><?= e($row['tran_id']) ?></td>
                            <td style="padding: 12px 16px;">#<?= e($row['order_number']) ?></td>
                            <td style="padding: 12px 16px; font-weight: 700;">৳<?= number_format((float)$row['amount'], 2) ?></td>
                            <td style="padding: 12px 16px; color: #64748b;"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td style="padding: 12px 16px; text-align: right;">
                                <form method="post" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="sync_transaction">
                                    <input type="hidden" name="tran_id" value="<?= e($row['tran_id']) ?>">
                                    <button type="submit" style="padding: 6px 12px; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                        <i class="fas fa-rotate"></i> Check Gateway
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Section 4: Missing IPNs -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <strong style="color: #334155; font-size: 14px;"><i class="fas fa-tower-broadcast"></i> Transactions Awaiting IPN Confirmation</strong>
            <span style="font-size: 12px; color: #64748b;"><?= count($missingIpns) ?> records</span>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; text-transform: uppercase;">
                    <th style="padding: 12px 16px;">Tran ID</th>
                    <th style="padding: 12px 16px;">Order #</th>
                    <th style="padding: 12px 16px;">Amount</th>
                    <th style="padding: 12px 16px;">Paid At</th>
                    <th style="padding: 12px 16px; text-align: right;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($missingIpns)): ?>
                    <tr>
                        <td colspan="5" style="padding: 24px; text-align: center; color: #64748b;">
                            All completed transactions have verified IPN deliveries.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($missingIpns as $row): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 16px; font-family: monospace; font-weight: 700;"><?= e($row['tran_id']) ?></td>
                            <td style="padding: 12px 16px;">#<?= e($row['order_number']) ?></td>
                            <td style="padding: 12px 16px; font-weight: 700;">৳<?= number_format((float)$row['amount'], 2) ?></td>
                            <td style="padding: 12px 16px; color: #64748b;"><?= date('M d, Y H:i', strtotime($row['paid_at'])) ?></td>
                            <td style="padding: 12px 16px; text-align: right;">
                                <span class="badge" style="background: #f1f5f9; color: #64748b;">Client Return Only</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
