<?php
/**
 * ==========================================================================
 * admin/finance/profit-loss.php — Profit & Loss (P&L) Statement
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Profit & Loss Statement — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();

try {
    // 1. Operating revenues (Delivered Sales)
    $salesRevenue = (float) $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'delivered'")->fetchColumn();
    $otherIncome = (float) $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn();

    $grossRevenue = $salesRevenue + $otherIncome;

    // 2. Cost of Goods Sold (COGS) - based on received purchase orders
    $cogs = (float) $pdo->query("SELECT SUM(total_amount) FROM purchase_orders WHERE status = 'received'")->fetchColumn();

    $grossProfit = $grossRevenue - $cogs;

    // 3. Operating Expenses by category
    $expensesRaw = $pdo->query("
        SELECT ec.name, SUM(t.amount) AS total_amount
        FROM transactions t
        JOIN expense_categories ec ON ec.id = t.category_id
        WHERE t.type = 'expense'
        GROUP BY ec.id
        ORDER BY total_amount DESC
    ")->fetchAll();

    $totalExpenses = 0.0;
    foreach ($expensesRaw as $ex) {
        $totalExpenses += (float)$ex['total_amount'];
    }

    $netProfit = $grossProfit - $totalExpenses;

} catch (PDOException $e) {
    error_log('[admin/finance/profit-loss] calculation failed: ' . $e->getMessage());
    $salesRevenue = $otherIncome = $grossRevenue = $cogs = $grossProfit = $totalExpenses = $netProfit = 0;
    $expensesRaw = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Profit & Loss Statement</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">YTD operating revenues, estimated cost of goods sold, overhead expenses, and bottom-line net profit.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Finance Hub</a>
</div>

<div class="dashboard-card" style="max-width:800px; padding:var(--space-6);">
    <div style="text-align:center; margin-bottom:30px;">
        <h2 style="font-size:18px; font-weight:800; color:var(--color-text); margin:0;">GROCO GROCERY E-COMMERCE</h2>
        <span style="font-size:12px; color:var(--color-text-faint); font-weight:700; text-transform:uppercase; display:block; margin-top:4px;">Income & Expenditure Audit Statement</span>
        <span style="font-size:11px; color:var(--color-text-muted);">For Period: Year-To-Date (YTD)</span>
    </div>

    <!-- Revenues -->
    <div style="margin-bottom:20px;">
        <h3 style="font-size:13px; font-weight:800; color:var(--color-text); border-bottom:2px solid var(--color-text); padding-bottom:4px; margin:0 0 10px 0; text-transform:uppercase;">1. Operating Revenues</h3>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Order Deliveries Sales Revenue:</span>
            <strong>৳<?= number_format($salesRevenue, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Other General Credit Incomes:</span>
            <strong>৳<?= number_format($otherIncome, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0; font-weight:800; color:var(--color-text);">
            <span>Total Gross Revenues:</span>
            <span>৳<?= number_format($grossRevenue, 2) ?></span>
        </div>
    </div>

    <!-- COGS -->
    <div style="margin-bottom:20px;">
        <h3 style="font-size:13px; font-weight:800; color:var(--color-text); border-bottom:2px solid var(--color-text); padding-bottom:4px; margin:0 0 10px 0; text-transform:uppercase;">2. Cost of Goods Sold (COGS)</h3>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
            <span>Wholesale Inventory Stock Procurement:</span>
            <strong>৳<?= number_format($cogs, 2) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0; font-weight:800; color:var(--color-text); border-top:1px solid var(--color-text);">
            <span>Gross Margin Yield (Revenues - COGS):</span>
            <span style="color: #0ca678;">৳<?= number_format($grossProfit, 2) ?></span>
        </div>
    </div>

    <!-- Expenses -->
    <div style="margin-bottom:20px;">
        <h3 style="font-size:13px; font-weight:800; color:var(--color-text); border-bottom:2px solid var(--color-text); padding-bottom:4px; margin:0 0 10px 0; text-transform:uppercase;">3. General & Administrative Expenses</h3>
        <?php foreach ($expensesRaw as $ex): ?>
            <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--color-border);">
                <span><?= e($ex['name']) ?>:</span>
                <strong>৳<?= number_format((float)$ex['total_amount'], 2) ?></strong>
            </div>
        <?php endforeach; ?>
        <div style="display:flex; justify-content:space-between; font-size:13px; padding:8px 0; font-weight:800; color:var(--color-text);">
            <span>Total Operational Expenses:</span>
            <span>৳<?= number_format($totalExpenses, 2) ?></span>
        </div>
    </div>

    <!-- Net Profit -->
    <div style="border-top: 3px double var(--color-text); padding-top:12px; display:flex; justify-content:space-between; font-size:16px; font-weight:800; color:var(--color-text);">
        <span>NET PROFIT / MARGIN YIELD:</span>
        <span style="color: <?= $netProfit >= 0 ? '#0ca678' : '#e03131' ?>;">৳<?= number_format($netProfit, 2) ?></span>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
