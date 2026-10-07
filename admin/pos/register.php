<?php
/**
 * ==========================================================================
 * admin/pos/register.php — Cash Register & Shift Drawer Controller (Extended)
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Cash Register Management — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.cash');
require_once __DIR__ . '/../includes/pos_lib.php';

$pdo = db();
$adminId = current_admin_id();
$error = null;
$success = null;

// 1. Fetch active shift
try {
    $stmtActive = $pdo->prepare("SELECT * FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
    $stmtActive->execute([$adminId]);
    $activeShift = $stmtActive->fetch();

    $shiftSales = 0.0;
    $cashInflow = 0.0;
    $cashOutflow = 0.0;
    $drawerTxLogs = [];

    $shiftSum = null;
    if ($activeShift) {
        // Single source of truth for drawer maths (cash tenders only, refunds, cash in/out).
        $shiftSum    = pos_shift_summary($pdo, $activeShift);
        $shiftSales  = $shiftSum['cash_sales'];
        $cashInflow  = $shiftSum['cash_in'];
        $cashOutflow = $shiftSum['cash_out'];

        $stmtTx = $pdo->prepare("SELECT * FROM pos_drawer_transactions WHERE shift_id = ? ORDER BY created_at DESC");
        $stmtTx->execute([$activeShift['id']]);
        $drawerTxLogs = $stmtTx->fetchAll();
    }

} catch (PDOException $e) {
    error_log('[admin/pos/register] load active shift failed: ' . $e->getMessage());
    $activeShift = null;
    $shiftSales = 0;
}

// 2. Handle Cash In / Cash Out Drawer transaction
if (method_is('post') && input('pos_action', '') === 'drawer_tx' && $activeShift) {
    verify_csrf_or_fail();
    try {
        pos_drawer_tx($pdo, (int) $adminId, (string) input('tx_type', ''), (float) input('amount', '0'), (string) input('notes', ''));
        log_admin_activity('pos.drawer_tx', 'Logged register drawer ' . input('tx_type', '') . ' of ৳' . (float) input('amount', '0'));
        flash('pos_msg', 'Register drawer transaction logged successfully!', 'success');
        header('Location: register.php');
        exit;
    } catch (PosException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[admin/pos/register] drawer tx failed: ' . $e->getMessage());
        $error = 'Failed to record drawer transaction.';
    }
}

// 3. Handle Opening Shift
if (method_is('post') && input('pos_action', '') === 'open_shift') {
    verify_csrf_or_fail();
    try {
        $newShiftId = pos_open_shift($pdo, (int) $adminId, (float) input('opening_cash', '0'));
        log_admin_activity('pos.open_shift', 'Opened cash register shift #' . $newShiftId . ' with opening cash ৳' . (float) input('opening_cash', '0'));
        flash('pos_msg', 'Cash Register opened successfully!', 'success');
        header('Location: index.php');
        exit;
    } catch (PosException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[admin/pos/register] open failed: ' . $e->getMessage());
        $error = 'Failed to open register.';
    }
}

// 4. Handle Closing Shift (Z-Report). The variance is stored exactly as counted — never silently adjusted.
if (method_is('post') && input('pos_action', '') === 'close_shift' && $activeShift) {
    verify_csrf_or_fail();
    try {
        $closed = pos_close_shift($pdo, (int) $adminId, (float) input('actual_cash', '0'));
        $diff = $closed['difference'];
        log_admin_activity('pos.close_shift', "Closed shift #{$closed['shift_id']}: expected ৳{$closed['expected']}, counted ৳{$closed['actual']}, difference ৳{$diff}");
        flash('pos_msg', 'Shift closed. Expected ৳' . number_format($closed['expected'], 2) . ', counted ৳' . number_format($closed['actual'], 2) . ', difference ' . ($diff >= 0 ? '+' : '-') . '৳' . number_format(abs($diff), 2), $diff == 0.0 ? 'success' : 'warning');
        header('Location: index.php');
        exit;
    } catch (PosException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[admin/pos/register] close shift failed: ' . $e->getMessage());
        $error = 'Failed to close register due to a database error.';
    }
}

// 5. Fetch past shifts log history
try {
    $shifts = $pdo->query("
        SELECT ps.*, a.username 
        FROM pos_shifts ps
        JOIN admins a ON a.id = ps.admin_id
        ORDER BY ps.created_at DESC 
        LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/pos/register] history load failed: ' . $e->getMessage());
    $shifts = [];
}

// 6. Handle Printable reports (X-Reading or Z-Reading layout)
$reportAction = input('action', '', 'get');
if ($reportAction === 'x_report' && $activeShift) {
    // Generate X Report (current reading, does not close the register)
    $expectedDrawer = $shiftSum['expected_cash'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>POS X-Report - Shift #<?= $activeShift['id'] ?></title>
        <style>
            body { font-family: 'Courier New', Courier, monospace; font-size: 12px; width: 280px; margin: 0; padding: 10px; background: #fff; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .header h1 { font-size: 14px; margin: 0; }
            .line { border-top: 1px dashed #000; margin: 8px 0; }
            .row-val { display: flex; justify-content: space-between; margin-bottom: 4px; }
        </style>
    </head>
    <body onload="window.print();">
        <div class="text-center header">
            <h1>REGISTER X-REPORT</h1>
            <p>Date: <?= date('Y-m-d H:i:s') ?></p>
            <p>Shift ID: #<?= $activeShift['id'] ?></p>
        </div>
        <div class="line"></div>
        <div class="row-val"><span>Opening Cash:</span><span>৳<?= number_format((float)$activeShift['opening_cash'], 2) ?></span></div>
        <div class="row-val"><span>Cash Sales:</span><span>৳<?= number_format($shiftSales, 2) ?></span></div>
        <div class="row-val"><span>Card Sales:</span><span>৳<?= number_format($shiftSum['card'], 2) ?></span></div>
        <div class="row-val"><span>Mobile Banking:</span><span>৳<?= number_format($shiftSum['bkash'], 2) ?></span></div>
        <div class="row-val"><span>Bank Transfer:</span><span>৳<?= number_format($shiftSum['bank'], 2) ?></span></div>
        <div class="row-val"><span>Wallet:</span><span>৳<?= number_format($shiftSum['wallet'], 2) ?></span></div>
        <div class="row-val"><span>Cash Refunds:</span><span>-৳<?= number_format($shiftSum['cash_refunds'], 2) ?></span></div>
        <div class="row-val"><span>Drawer Cash-Ins:</span><span>৳<?= number_format($cashInflow, 2) ?></span></div>
        <div class="row-val"><span>Drawer Cash-Outs:</span><span>৳<?= number_format($cashOutflow, 2) ?></span></div>
        <div class="line"></div>
        <div class="row-val" style="font-weight:bold;"><span>Expected Drawer Cash:</span><span>৳<?= number_format($expectedDrawer, 2) ?></span></div>
        <div class="line"></div>
        <div class="text-center" style="font-size:10px; margin-top:20px;">
            <p>Mid-day Register audit count sheet.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Register Drawer & Shift Controller</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage cash registers, log drawer cash-ins / safe drops, and generate corporate X/Z audits.</p>
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
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Open/Close Drawer Form & Cash In/Out -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
            <?php if ($activeShift): ?>
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Reconcile & Close Shift</h3>
                
                <div style="font-size:13px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Shift Started:</span>
                        <strong><?= date('M d, H:i', strtotime($activeShift['start_time'])) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Opening Cash:</span>
                        <strong>৳<?= number_format((float)$activeShift['opening_cash'], 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#0ca678;">
                        <span>Cash Sales:</span>
                        <strong>+৳<?= number_format($shiftSales, 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#0ca678;">
                        <span>Drawer Inflow:</span>
                        <strong>+৳<?= number_format($cashInflow, 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#e03131;">
                        <span>Drawer Outflow:</span>
                        <strong>-৳<?= number_format($cashOutflow, 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#e03131;">
                        <span>Cash Refunds:</span>
                        <strong>-৳<?= number_format((float)$shiftSum['cash_refunds'], 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:12px;">
                        <span>Card / Mobile / Bank / Wallet sales (not in drawer):</span>
                        <strong>৳<?= number_format((float)($shiftSum['card'] + $shiftSum['bkash'] + $shiftSum['bank'] + $shiftSum['wallet']), 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:8px; font-size:14px; color:var(--color-text);">
                        <span>Expected Drawer Cash:</span>
                        <strong>৳<?= number_format((float)$shiftSum['expected_cash'], 2) ?></strong>
                    </div>
                </div>

                <form method="post" class="auth-form" style="margin-bottom:12px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="close_shift">
                    
                    <div class="form-field-group">
                        <label style="font-weight:700;">Counted Actual Cash (৳) *</label>
                        <input type="number" name="actual_cash" step="0.01" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; background:#f03e3e; font-size:13px;"><i class="fas fa-cash-register"></i> Close Register (Z-Report)</button>
                </form>

                <a href="?action=x_report" target="_blank" class="btn btn-secondary" style="width:100%; text-align:center; font-weight:700; text-decoration:none; padding:10px; border-radius:var(--radius-pill); font-size:12px; display:block;"><i class="fas fa-print"></i> Print Mid-day X-Report</a>
            <?php else: ?>
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Open New Cash Register</h3>
                
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="open_shift">

                    <div class="form-field-group">
                        <label style="font-weight:700;">Opening Drawer Cash (৳) *</label>
                        <input type="number" name="opening_cash" step="0.01" value="1000.00" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-cash-register"></i> Open Cash Drawer</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($activeShift): ?>
            <!-- Cash In / Cash Out drawer transactions form -->
            <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Log Drawer Cash In / Out</h3>
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="drawer_tx">

                    <div class="form-field-group">
                        <label style="font-weight:700;">Type of entry *</label>
                        <select name="tx_type" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; background:var(--color-surface); color:var(--color-text);">
                            <option value="cash_in">Cash-In (Add Drawer Change)</option>
                            <option value="cash_out">Cash-Out (Safe Drop / Drawer Payout)</option>
                        </select>
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Amount (৳) *</label>
                        <input type="number" name="amount" step="0.01" required style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px;">
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Notes / Reference</label>
                        <input type="text" name="notes" placeholder="E.g. Safe Drop at 3PM" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; border-radius:var(--radius-pill); font-weight:700;">Log Drawer transaction</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Historic shifts log table -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        <!-- Drawer Transactions List -->
        <?php if ($activeShift && !empty($drawerTxLogs)): ?>
            <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
                <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
                    <h3 style="font-size:14px; font-weight:800; margin:0;">Active Shift Drawer Log entries</h3>
                </div>
                <div class="admin-table-wrapper" style="border:none;">
                    <table class="admin-data-table" style="font-size:12px;">
                        <thead>
                            <tr>
                                <th style="padding:10px;">Type</th>
                                <th style="padding:10px; text-align:right;">Amount</th>
                                <th style="padding:10px;">Reference Notes</th>
                                <th style="padding:10px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drawerTxLogs as $tx): ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px; font-weight:700; color:<?= ($tx['type'] === 'cash_in') ? '#0ca678' : '#e03131' ?>;"><?= strtoupper(str_replace('_', ' ', $tx['type'])) ?></td>
                                    <td style="padding:8px; text-align:right; font-weight:700;">৳<?= number_format((float)$tx['amount'], 2) ?></td>
                                    <td style="padding:8px; color:var(--color-text-muted);"><?= e($tx['notes']) ?></td>
                                    <td style="padding:8px; color:var(--color-text-faint);"><?= date('H:i', strtotime($tx['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Shift History -->
        <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
            <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
                <h3 style="font-size:14px; font-weight:800; margin:0;">POS Cash Register Shifts History</h3>
            </div>
            <div class="admin-table-wrapper" style="border:none;">
                <table class="admin-data-table" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th style="padding:10px 15px;">Cashier Operator</th>
                            <th style="padding:10px 15px; text-align:right;">Opening Drawer</th>
                            <th style="padding:10px 15px; text-align:right;">Expected Cash</th>
                            <th style="padding:10px 15px; text-align:right;">Actual Cash</th>
                            <th style="padding:10px 15px; text-align:center;">Drawer Discrepancy</th>
                            <th style="padding:10px 15px;">Shift Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($shifts)): ?>
                            <?php foreach ($shifts as $row): 
                                $expected = (float)$row['closing_cash'];
                                $actual = (float)$row['actual_cash'];
                                $diff = $actual - $expected;
                                
                                $color = '#339af0';
                                if ($row['status'] === 'closed') {
                                    $color = ($diff === 0.0) ? '#0ca678' : '#e03131';
                                }
                            ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px 15px;"><strong>@<?= e($row['username']) ?></strong></td>
                                    <td style="padding:8px 15px; text-align:right;">৳<?= number_format((float)$row['opening_cash'], 2) ?></td>
                                    <td style="padding:8px 15px; text-align:right;">৳<?= number_format($expected, 2) ?></td>
                                    <td style="padding:8px 15px; text-align:right; font-weight:700;">৳<?= number_format($actual, 2) ?></td>
                                    <td style="padding:8px 15px; text-align:center; font-weight:800; color:<?= $color ?>;">
                                        <?php if ($row['status'] === 'open'): ?>
                                            <span class="status-pill pill-pending" style="font-size:8px;">ACTIVE SHIFT</span>
                                        <?php else: ?>
                                            <?= ($diff >= 0) ? '+' : '' ?>৳<?= number_format($diff, 2) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 15px; color:var(--color-text-faint);"><?= date('M d, H:i', strtotime($row['start_time'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="padding:20px; text-align:center; color:var(--color-text-faint);">No historic cashier drawer shifts logged.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

