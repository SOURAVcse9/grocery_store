<?php
/**
 * ==========================================================================
 * admin/finance/cash-flow.php — Corporate Cash Flow Statement
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Cash Flow Statement — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();

try {
    // 1. Operating inflows (Sales collections)
    $salesInflow = (float) $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'delivered'")->fetchColumn();
    
    // 2. Ledger inflows
    $ledgerInflow = (float) $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn();

    // 3. Operating outflows
    $ledgerOutflow = (float) $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense'")->fetchColumn();
    $procurementOutflow = (float) $pdo->query("SELECT SUM(total_amount) FROM purchase_orders WHERE status = 'received'")->fetchColumn();

    $netOperatingCash = $salesInflow + $ledgerInflow - $ledgerOutflow - $procurementOutflow;

    // Investing & Financing flows (Placeholder or basic custom structures)
    $investingFlow = 0.0;
    $financingFlow = 0.0;

    $netCashChange = $netOperatingCash + $investingFlow + $financingFlow;

} catch (PDOException $e) {
    error_log('[admin/finance/cash-flow] calculation failed: ' . $e->getMessage());
    $salesInflow = $ledgerInflow = $ledgerOutflow = $procurementOutflow = $netOperatingCash = $netCashChange = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Cash Flow Statement</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">YTD cash movements categorized by operating, investing, and financing parameters.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Finance Hub</a>
</div>

<div class="dashboard-card" style="max-width:800px; padding:var(--space-6);">
    <div style="text-align:center; margin-bottom:30px;">
        <h2 style="font-size:18px; font-weight:800; color:var(--color-text); margin:0;">GROCO GROCERY E-COMMERCE</h2>
        <span style="font-size:12px; color:var(--color-text-faint); font-weight:700; text-transform:uppercase; display:block; margin-top:4px;">Cash Flows Analysis Statement</span>
        <span style="font-size:11px; color:var(--color-text-muted);">For Period: Year-To-Date (YTD)</span>
    </div>

    <!-- Operating Activities -->
    <div style="margin-bottom:20px;">
        <h3 style="font-size:13px; font-weight:800; color:var(--color-text); border-bottom:2px solid var(--color-text); padding-bottom:4px; margin:0 0 10px 0; text-transform:uppercase;">1. Operating Activities Cash Flow</h3>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Cash received from order sales:</span>
            <strong>+৳<?= number_format($salesInflow, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Cash received from other dynamic assets:</span>
            <strong>+৳<?= number_format($ledgerInflow, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Cash paid for procurement & suppliers:</span>
            <strong>-৳<?= number_format($procurementOutflow, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Cash paid for operation overheads:</span>
            <strong>-৳<?= number_format($ledgerOutflow, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0; font-weight:800; color:var(--color-text); border-top:1px solid var(--color-text);">
            <span>Net Operating Cash Flow:</span>
            <span style="color: #0ca678;">৳<?= number_format($netOperatingCash, 2) ?></span>
        </div>
    </div>

    <!-- Net Change -->
    <div style="border-top: 3px double var(--color-text); padding-top:12px; display:flex; justify-content:space-between; font-size:15px; font-weight:800; color:var(--color-text);">
        <span>NET ANNUAL CHANGE IN CASH:</span>
        <span style="color: <?= $netCashChange >= 0 ? '#0ca678' : '#e03131' ?>;">৳<?= number_format($netCashChange, 2) ?></span>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
