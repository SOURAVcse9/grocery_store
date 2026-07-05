<?php
/**
 * ==========================================================================
 * admin/pos/shift.php — POS Cash Drawer Shift Management
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Shift Management — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.manage');

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
    if ($activeShift) {
        // Calculate sales during this shift (delivered POS orders since start_time)
        $stmtSales = $pdo->prepare("
            SELECT SUM(total_amount) 
            FROM orders 
            WHERE status = 'delivered' 
              AND payment_method = 'pos_split'
              AND created_at >= ?
        ");
        $stmtSales->execute([$activeShift['start_time']]);
        $shiftSales = (float) $stmtSales->fetchColumn();
    }

} catch (PDOException $e) {
    error_log('[admin/pos/shift] load active failed: ' . $e->getMessage());
    $activeShift = null;
    $shiftSales = 0;
}

// 2. Handle Closing Cash Shift Register drawer
if (method_is('post') && input('pos_action', '') === 'close_shift' && $activeShift) {
    verify_csrf_or_fail();
    $actualCash = (float) input('actual_cash', '0.00');
    $expectedCash = (float)$activeShift['opening_cash'] + $shiftSales;

    try {
        $up = $pdo->prepare("
            UPDATE pos_shifts SET 
                end_time = NOW(),
                closing_cash = ?,
                actual_cash = ?,
                status = 'closed'
            WHERE id = ?
        ");
        $up->execute([$expectedCash, $actualCash, $activeShift['id']]);

        log_admin_activity('pos.close_shift', "Closed cash register shift ID: {$activeShift['id']}. Drawer Cash Count: ৳{$actualCash}");
        flash('pos_msg', 'Cash Register Shift closed and reconciled successfully!', 'success');
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        error_log('[admin/pos/shift] close shift failed: ' . $e->getMessage());
        $error = 'Failed to close register due to database error.';
    }
}

// 3. Fetch past shifts log history
try {
    $shifts = $pdo->query("
        SELECT ps.*, a.username 
        FROM pos_shifts ps
        JOIN admins a ON a.id = ps.admin_id
        ORDER BY ps.created_at DESC 
        LIMIT 30
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/pos/shift] history load failed: ' . $e->getMessage());
    $shifts = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Shift & Cashier Drawer Registry</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect POS cashier shifts records, close cash drawer balances, and trace drawer audit discrepancies.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<!-- Alerts -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Active shift status close drawer form -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Close Active Cash Register</h3>
        
        <?php if ($activeShift): ?>
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
                    <span>Shift POS Sales Inflow:</span>
                    <strong>+৳<?= number_format($shiftSales, 2) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:8px; font-size:14px; color:var(--color-text);">
                    <span>Expected Drawer Cash:</span>
                    <strong>৳<?= number_format((float)$activeShift['opening_cash'] + $shiftSales, 2) ?></strong>
                </div>
            </div>

            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="pos_action" value="close_shift">
                
                <div class="form-field-group">
                    <label style="font-weight:700;">Actual Drawer Cash Count (৳) *</label>
                    <input type="number" name="actual_cash" step="0.01" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    <span style="font-size:10px; color:var(--color-text-faint);">Count bills/change in the physical drawer before closing.</span>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; background:#f03e3e; font-size:13px;"><i class="fas fa-cash-register"></i> Close Shift Register</button>
            </form>
        <?php else: ?>
            <p style="text-align:center; color:var(--color-text-faint); font-size:13px; margin:0; padding:16px;">No active cashier shift drawer is currently open.</p>
        <?php endif; ?>
    </div>

    <!-- Right Column: Historic shifts log table -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Historic POS Shifts Log Registry</h3>
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

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
