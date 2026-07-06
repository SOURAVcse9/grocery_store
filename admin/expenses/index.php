<?php
/**
 * ==========================================================================
 * admin/expenses/index.php — Operational Expenses Registry
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Expenses Management — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('expenses.manage');

$pdo = db();
$error = null;
$success = null;

// Handle Add Expense POST Action
if (method_is('post') && input('action', '') === 'add_expense') {
    verify_csrf_or_fail();
    
    $category = input('category', '');
    $amount = (float) input('amount', '0.00');
    $note = trim(input('note', ''));

    if (empty($category) || $amount <= 0) {
        $error = 'Please select a category and enter a valid expense amount.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, note, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$category, $amount, $note]);
            
            log_admin_activity('expenses.add', "Added expense: Category - {$category}, Amount - ৳{$amount}");
            $success = 'Expense logged successfully!';
        } catch (PDOException $e) {
            error_log('[admin/expenses] add failed: ' . $e->getMessage());
            $error = 'Failed to save expense due to a database error.';
        }
    }
}

// Fetch Expenses Log
try {
    $stmtExpenses = $pdo->query("SELECT * FROM expenses ORDER BY created_at DESC LIMIT 100");
    $expenses = $stmtExpenses->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/expenses] load failed: ' . $e->getMessage());
    $expenses = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Operational Expenses</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Log daily and monthly operational costs including rent, transport, salaries, utility bills, and other overheads.</p>
    </div>
</div>

<!-- Alerts -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Add Expense Form -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Record Daily Expense</h3>
        
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_expense">
            
            <div class="form-field-group">
                <label for="expenseCategory" style="font-weight:700;">Expense Category *</label>
                <select id="expenseCategory" name="category" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">-- Choose Category --</option>
                    <option value="electricity">Electricity</option>
                    <option value="rent">Rent</option>
                    <option value="salary">Salary</option>
                    <option value="internet">Internet</option>
                    <option value="transport">Transport</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="miscellaneous">Miscellaneous</option>
                </select>
            </div>

            <div class="form-field-group">
                <label for="expenseAmount" style="font-weight:700;">Expense Amount (৳) *</label>
                <input type="number" id="expenseAmount" name="amount" step="0.01" min="0.01" required placeholder="0.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label for="expenseNote" style="font-weight:700;">Memo / Note</label>
                <textarea id="expenseNote" name="note" rows="2" placeholder="Describe the reason for payment..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-file-invoice-dollar"></i> Log Expense Item</button>
        </form>
    </div>

    <!-- Right Column: Expense History Logs -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Operational Expense Ledgers</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Category</th>
                        <th style="padding:10px 15px; text-align:right;">Amount</th>
                        <th style="padding:10px 15px;">Memo</th>
                        <th style="padding:10px 15px;">Date Logged</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px;"><span class="status-pill pill-processing" style="text-transform: uppercase; font-size:9px; font-weight:700;"><?= e($row['category']) ?></span></td>
                                <td style="padding:8px 15px; text-align:right; font-weight:700; color:var(--color-danger);">৳<?= number_format((float)$row['amount'], 2) ?></td>
                                <td style="padding:8px 15px; color:var(--color-text-muted);"><?= e($row['note'] ?: '-') ?></td>
                                <td style="padding:8px 15px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding:20px; text-align:center; color:var(--color-text-faint);">No operational expense entries logged.</td>
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
