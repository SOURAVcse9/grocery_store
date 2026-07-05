<?php
/**
 * ==========================================================================
 * admin/finance/daily-closing.php — Day-end Balance Closings Logbook
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Daily Closings — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();
$error = null;
$success = null;

// Calculate closing aggregates for today
$today = date('Y-m-d');
try {
    // 1. Opening balance = previous closing balance
    $prevClose = (float) $pdo->query("SELECT closing_balance FROM daily_closings ORDER BY closing_date DESC LIMIT 1")->fetchColumn();
    
    // 2. Today's sales income
    $todaySales = (float) $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE status = 'delivered' AND DATE(created_at) = ?");
    $todaySales->execute([$today]);
    $salesIncome = (float) $todaySales->fetchColumn();

    // 3. Today's ledger income
    $todayLedgerInc = (float) $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND DATE(created_at) = ?");
    $todayLedgerInc->execute([$today]);
    $ledgerIncome = (float) $todayLedgerInc->fetchColumn();

    $totalIncome = $salesIncome + $ledgerIncome;

    // 4. Today's ledger expense
    $todayLedgerExp = (float) $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND DATE(created_at) = ?");
    $todayLedgerExp->execute([$today]);
    $ledgerExpense = (float) $todayLedgerExp->fetchColumn();

    // 5. Today's PO expense
    $todayPoExp = (float) $pdo->prepare("SELECT SUM(total_amount) FROM purchase_orders WHERE status = 'received' AND DATE(created_at) = ?");
    $todayPoExp->execute([$today]);
    $poExpense = (float) $todayPoExp->fetchColumn();

    $totalExpense = $ledgerExpense + $poExpense;

    $closingBalance = $prevClose + $totalIncome - $totalExpense;

} catch (PDOException $e) {
    error_log('[admin/finance/daily-closing] calculation failed: ' . $e->getMessage());
    $prevClose = $totalIncome = $totalExpense = $closingBalance = 0;
}

// Handle posting EOD closing
if (method_is('post')) {
    verify_csrf_or_fail();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO daily_closings (closing_date, opening_balance, total_income, total_expense, closing_balance)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                opening_balance = VALUES(opening_balance),
                total_income = VALUES(total_income),
                total_expense = VALUES(total_expense),
                closing_balance = VALUES(closing_balance)
        ");
        $stmt->execute([
            $today,
            $prevClose,
            $totalIncome,
            $totalExpense,
            $closingBalance
        ]);

        log_admin_activity('finance.closing', "Posted Daily Day-End closing for date {$today}: balance ৳{$closingBalance}");
        $success = "Daily closing for date {$today} successfully posted!";
    } catch (PDOException $e) {
        error_log('[admin/finance/daily-closing] post failed: ' . $e->getMessage());
        $error = 'Failed to post daily closing balance.';
    }
}

// Fetch daily closing history
try {
    $closings = $pdo->query("SELECT * FROM daily_closings ORDER BY closing_date DESC LIMIT 30")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/finance/daily-closing] history load failed: ' . $e->getMessage());
    $closings = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">End-of-Day Closings</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Compute and freeze daily revenue intakes, opening/closing balance sheets, and operational margins.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Finance Hub</a>
</div>

<!-- Alerts -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left: Today's EOD stats preview & Submit EOD button -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Post Today's Closing</h3>
        
        <div style="font-size:13px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between;">
                <span>Opening Balance:</span>
                <strong>৳<?= number_format($prevClose, 2) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; color:#0ca678;">
                <span>Today's Total Credit:</span>
                <strong>+৳<?= number_format($totalIncome, 2) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; color:#e03131;">
                <span>Today's Total Debit:</span>
                <strong>-৳<?= number_format($totalExpense, 2) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:8px; font-size:14px; color:var(--color-text);">
                <span>Estimated Closing:</span>
                <strong>৳<?= number_format($closingBalance, 2) ?></strong>
            </div>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-calendar-check"></i> Post EOD Closing</button>
        </form>
    </div>

    <!-- Right: Historic Log list -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Daily Closing Logs History</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Date</th>
                        <th style="padding:10px 15px; text-align:right;">Opening Balance</th>
                        <th style="padding:10px 15px; text-align:right;">Total Credit</th>
                        <th style="padding:10px 15px; text-align:right;">Total Debit</th>
                        <th style="padding:10px 15px; text-align:right;">Closing Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($closings)): ?>
                        <?php foreach ($closings as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px;"><strong><?= date('M d, Y', strtotime($row['closing_date'])) ?></strong></td>
                                <td style="padding:8px 15px; text-align:right; color:var(--color-text-faint);">৳<?= number_format((float)$row['opening_balance'], 2) ?></td>
                                <td style="padding:8px 15px; text-align:right; color:#0ca678;">+৳<?= number_format((float)$row['total_income'], 2) ?></td>
                                <td style="padding:8px 15px; text-align:right; color:#e03131;">-৳<?= number_format((float)$row['total_expense'], 2) ?></td>
                                <td style="padding:8px 15px; text-align:right; font-weight:700;">৳<?= number_format((float)$row['closing_balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding:20px; text-align:center; color:var(--color-text-faint);">No historic daily closings recorded.</td>
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
