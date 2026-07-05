<?php
/**
 * ==========================================================================
 * admin/finance/balance-sheet.php — Corporate Balance Sheet Statement
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Balance Sheet — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();

try {
    // 1. Assets: Cash (sales + ledger income minus expenses)
    $salesIncome = (float) $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'delivered'")->fetchColumn();
    $ledgerIncome = (float) $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn();
    $ledgerExpense = (float) $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense'")->fetchColumn();
    $poExpense = (float) $pdo->query("SELECT SUM(total_amount) FROM purchase_orders WHERE status = 'received'")->fetchColumn();

    $cashAsset = $salesIncome + $ledgerIncome - $ledgerExpense - $poExpense;
    if ($cashAsset < 0) $cashAsset = 0; // prevent negative cash visual

    // 2. Assets: Inventory Book Valuation
    $inventoryAsset = (float) $pdo->query("SELECT SUM(price * stock) FROM products WHERE deleted_at IS NULL AND is_active = 1")->fetchColumn();

    $totalAssets = $cashAsset + $inventoryAsset;

    // 3. Liabilities: Accounts Payable (Outstanding / Pending Purchase Orders)
    $liabilitiesPayable = (float) $pdo->query("SELECT SUM(total_amount) FROM purchase_orders WHERE status = 'pending'")->fetchColumn();

    // 4. Equity
    $ownerEquity = $totalAssets - $liabilitiesPayable;

} catch (PDOException $e) {
    error_log('[admin/finance/balance-sheet] calculation failed: ' . $e->getMessage());
    $cashAsset = $inventoryAsset = $totalAssets = $liabilitiesPayable = $ownerEquity = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Balance Sheet</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Audit YTD corporate assets values, pending accounts payable liabilities, and owner equity balances.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Finance Hub</a>
</div>

<div class="dashboard-card" style="max-width:800px; padding:var(--space-6);">
    <div style="text-align:center; margin-bottom:30px;">
        <h2 style="font-size:18px; font-weight:800; color:var(--color-text); margin:0;">GROCO GROCERY E-COMMERCE</h2>
        <span style="font-size:12px; color:var(--color-text-faint); font-weight:700; text-transform:uppercase; display:block; margin-top:4px;">Corporate Balance Sheet Audit</span>
        <span style="font-size:11px; color:var(--color-text-muted);">As Of Date: <?= date('F d, Y') ?></span>
    </div>

    <!-- Assets -->
    <div style="margin-bottom:24px;">
        <h3 style="font-size:13px; font-weight:800; color:var(--color-text); border-bottom:2px solid var(--color-text); padding-bottom:4px; margin:0 0 10px 0; text-transform:uppercase;">1. Current & Fixed Assets</h3>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Cash & Cash Equivalents:</span>
            <strong>৳<?= number_format($cashAsset, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Merchandise Inventory Valuation (Book Value):</span>
            <strong>৳<?= number_format($inventoryAsset, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0; font-weight:800; color:var(--color-text); background:rgba(0,0,0,0.02);">
            <span>Total Corporate Assets:</span>
            <span>৳<?= number_format($totalAssets, 2) ?></span>
        </div>
    </div>

    <!-- Liabilities & Equity -->
    <div>
        <h3 style="font-size:13px; font-weight:800; color:var(--color-text); border-bottom:2px solid var(--color-text); padding-bottom:4px; margin:0 0 10px 0; text-transform:uppercase;">2. Liabilities & Owner Equity</h3>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Accounts Payable (Unpaid/Pending POs):</span>
            <strong>৳<?= number_format($liabilitiesPayable, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Owner Retained Earnings / Equity:</span>
            <strong>৳<?= number_format($ownerEquity, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0; font-weight:800; color:var(--color-text); background:rgba(0,0,0,0.02);">
            <span>Total Liabilities & Equity:</span>
            <span>৳<?= number_format($liabilitiesPayable + $ownerEquity, 2) ?></span>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
