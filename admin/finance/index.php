<?php
/**
 * ==========================================================================
 * admin/finance/index.php — Financial Accounting Dashboard
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Finance & Accounting — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();

try {
    // 1. Total order sales income (delivered orders)
    $salesIncome = (float) $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'delivered'")->fetchColumn();

    // 2. Total general ledger income
    $ledgerIncome = (float) $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn();

    $totalIncome = $salesIncome + $ledgerIncome;

    // 3. Total expenses (general ledger expenses + received POs costs)
    $ledgerExpense = (float) $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense'")->fetchColumn();
    $poExpense = (float) $pdo->query("SELECT SUM(total_amount) FROM purchase_orders WHERE status = 'received'")->fetchColumn();
    
    $totalExpense = $ledgerExpense + $poExpense;

    $netProfit = $totalIncome - $totalExpense;

} catch (PDOException $e) {
    error_log('[admin/finance/index] load failed: ' . $e->getMessage());
    $totalIncome = $totalExpense = $netProfit = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Finance & Accounting Hub</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Oversee corporate revenues, general ledger audits, balance sheets, and daily closings.</p>
    </div>
    
    <div style="display:inline-flex; gap:10px;">
        <a href="transactions.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-file-invoice-dollar"></i> General Ledger</a>
        <a href="daily-closing.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-calendar-check"></i> Daily Closings</a>
    </div>
</div>

<!-- Navigation tabs row -->
<div style="display:flex; gap:12px; margin-bottom:var(--space-5); flex-wrap:wrap;">
    <a href="profit-loss.php" class="btn btn-secondary" style="font-weight:700; border-radius:var(--radius-pill);"><i class="fas fa-chart-line"></i> Profit & Loss</a>
    <a href="balance-sheet.php" class="btn btn-secondary" style="font-weight:700; border-radius:var(--radius-pill);"><i class="fas fa-scale-balanced"></i> Balance Sheet</a>
    <a href="cash-flow.php" class="btn btn-secondary" style="font-weight:700; border-radius:var(--radius-pill);"><i class="fas fa-money-bill-transfer"></i> Cash Flow</a>
    <a href="reconciliation.php" class="btn btn-secondary" style="font-weight:700; border-radius:var(--radius-pill);"><i class="fas fa-handshake-simple"></i> Gateway Reconciliation</a>
    <a href="categories.php" class="btn btn-secondary" style="font-weight:700; border-radius:var(--radius-pill);"><i class="fas fa-tags"></i> Expense Categories</a>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-5); border-left: 4px solid #0ca678;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Gross Revenue (Sales + Ledger)</span>
        <h2 style="margin:4px 0 0 0; font-size:24px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalIncome, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-5); border-left: 4px solid #e03131;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Total Liabilities (Expenses + Cost POs)</span>
        <h2 style="margin:4px 0 0 0; font-size:24px; font-weight:800; color:var(--color-text);">৳<?= number_format($totalExpense, 2) ?></h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-5); border-left: 4px solid var(--color-primary);">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Estimated Net Margin</span>
        <h2 style="margin:4px 0 0 0; font-size:24px; font-weight:800; color:var(--color-text);">৳<?= number_format($netProfit, 2) ?></h2>
    </div>
</div>

<div class="dashboard-card" style="padding:var(--space-5);">
    <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Year-to-Date Performance Overview</h3>
    <div style="height: 320px; position: relative;">
        <!-- Placeholder for interactive charts representation -->
        <canvas id="financeChart" style="width: 100%; height: 100%;"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('financeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Total Revenues', 'Total Expenses', 'Net Profit Margin'],
            datasets: [{
                label: 'Financial Balances (৳)',
                data: [<?= $totalIncome ?>, <?= $totalExpense ?>, <?= $netProfit ?>],
                backgroundColor: [
                    'rgba(12, 166, 120, 0.75)',
                    'rgba(224, 49, 49, 0.75)',
                    'rgba(92, 124, 250, 0.75)'
                ],
                borderColor: [
                    '#0ca678',
                    '#e03131',
                    '#5c7cfa'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
