<?php
/**
 * ==========================================================================
 * admin/finance/transactions.php — General Ledger & Transaction Registry
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'General Ledger — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();
$error = null;
$success = null;

try {
    $categories = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/finance/transactions] load categories failed: ' . $e->getMessage());
    $categories = [];
}

// Handle Add Transaction
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $type = input('type', 'expense');
        $catId = (int) input('category_id', '0');
        $amount = (float) input('amount', '0.00');
        $reference = trim(input('reference', ''));
        $payMethod = input('payment_method', 'cash');
        $reconciled = (int) input('reconciled', '0');

        if ($amount <= 0 || empty($reference)) {
            $error = 'Positive transaction amount and reference details are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $type,
                    $catId > 0 ? $catId : null,
                    $amount,
                    $reference,
                    $payMethod,
                    $reconciled
                ]);

                log_admin_activity('finance.transaction', "Recorded general ledger {$type}: '{$reference}' of value ৳{$amount}");
                $success = 'General ledger transaction logged successfully!';
            } catch (PDOException $e) {
                error_log('[admin/finance/transactions] insert failed: ' . $e->getMessage());
                $error = 'Failed to record transaction due to database error.';
            }
        }
    }
}

// Handle Reconcile Toggle action
$action = input('action', '', 'get');
$transId = (int) input('id', '0', 'get');
if ($transId > 0 && ($action === 'reconcile' || $action === 'unreconcile')) {
    $state = ($action === 'reconcile') ? 1 : 0;
    try {
        $pdo->prepare("UPDATE transactions SET reconciled = ? WHERE id = ?")->execute([$state, $transId]);
        log_admin_activity('finance.reconcile', "Toggled reconciliation status of transaction ID: {$transId} to {$state}");
        $success = 'Transaction reconciliation status updated.';
    } catch (PDOException $e) {
        error_log('[admin/finance/transactions] reconcile failed: ' . $e->getMessage());
    }
}

// Filters & listings
$filterType = trim(input('filter_type', '', 'get'));
$filterReconciled = trim(input('filter_reconciled', '', 'get'));

$where = ['1=1'];
$params = [];

if (!empty($filterType)) {
    $where[] = 't.type = :type';
    $params['type'] = $filterType;
}
if ($filterReconciled !== '') {
    $where[] = 't.reconciled = :reconciled';
    $params['reconciled'] = (int) $filterReconciled;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    $transactions = $pdo->prepare("
        SELECT t.*, c.name AS category_name 
        FROM transactions t
        LEFT JOIN expense_categories c ON c.id = t.category_id
        {$whereClause}
        ORDER BY t.created_at DESC
    ");
    $transactions->execute($params);
    $items = $transactions->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/finance/transactions] load fail: ' . $e->getMessage());
    $items = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">General Ledger Transactions</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect, post, and reconcile corporate credit/debit transaction lines.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Finance Hub</a>
</div>

<!-- Alerts -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Post new transaction -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Post General Transaction</h3>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>

            <div class="form-field-group">
                <label style="font-weight:700;">Transaction Type *</label>
                <select name="type" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="expense">Expense (Debit)</option>
                    <option value="income">Income (Credit)</option>
                </select>
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Expense category (If Expense)</label>
                <select name="category_id" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">None / Not Applicable</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Transaction Amount *</label>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="E.g. 500.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Reference / Memo *</label>
                <input type="text" name="reference" required placeholder="E.g. Office Stationery buy" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:10px;" class="grid-2">
                <div class="form-field-group">
                    <label style="font-weight:700;">Payment Method</label>
                    <select name="payment_method" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                        <option value="cash">Cash</option>
                        <option value="bkash">bKash</option>
                        <option value="rocket">Rocket</option>
                        <option value="card">Credit Card</option>
                    </select>
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Reconciled Status</label>
                    <select name="reconciled" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                        <option value="0">Unreconciled</option>
                        <option value="1">Reconciled</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-file-invoice-dollar"></i> Post Entry</button>
        </form>
    </div>

    <!-- Right Column: Ledger listing with filters -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        
        <!-- Filter panel -->
        <div class="dashboard-card" style="padding:var(--space-4); margin:0;">
            <form method="get" style="display:flex; gap:12px; align-items:end; max-width:600px;">
                <div class="form-field-group" style="margin:0; flex:1;">
                    <select name="filter_type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                        <option value="">All Transactions</option>
                        <option value="expense" <?= $filterType === 'expense' ? 'selected' : '' ?>>Expenses Only</option>
                        <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Income Only</option>
                    </select>
                </div>
                <div class="form-field-group" style="margin:0; flex:1;">
                    <select name="filter_reconciled" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                        <option value="">All Reconciliation states</option>
                        <option value="1" <?= $filterReconciled === '1' ? 'selected' : '' ?>>Reconciled</option>
                        <option value="0" <?= $filterReconciled === '0' ? 'selected' : '' ?>>Unreconciled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
            </form>
        </div>

        <!-- Ledger table -->
        <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
            <div class="admin-table-wrapper" style="border:none;">
                <table class="admin-data-table" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th style="padding:10px 15px;">Date</th>
                            <th style="padding:10px 15px;">Type</th>
                            <th style="padding:10px 15px;">Memo Reference</th>
                            <th style="padding:10px 15px; text-align:right;">Amount</th>
                            <th style="padding:10px 15px; text-align:center;">Reconciled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $row): 
                                $isIncome = ($row['type'] === 'income');
                            ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px 15px; color:var(--color-text-faint);"><?= date('m-d H:i', strtotime($row['created_at'])) ?></td>
                                    <td style="padding:8px 15px;">
                                        <span class="status-pill pill-<?= $isIncome ? 'completed' : 'cancelled' ?>" style="font-size:8px; text-transform:uppercase;">
                                            <?= $row['type'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:8px 15px;">
                                        <strong><?= e($row['reference']) ?></strong><br>
                                        <span style="font-size:9px; color:var(--color-text-faint);"><?= e($row['category_name'] ?: 'No category') ?> &bull; <?= strtoupper($row['payment_method']) ?></span>
                                    </td>
                                    <td style="padding:8px 15px; text-align:right; font-weight:700; color: <?= $isIncome ? '#0ca678' : '#e03131' ?>;">
                                        <?= $isIncome ? '+' : '-' ?>৳<?= number_format((float)$row['amount'], 2) ?>
                                    </td>
                                    <td style="padding:8px 15px; text-align:center;">
                                        <?php if ((bool)$row['reconciled']): ?>
                                            <a href="?action=unreconcile&id=<?= $row['id'] ?>" class="btn btn-primary" style="padding:2px 6px; font-size:8px; border-radius:var(--radius-sm); background:#0ca678; border-color:#0ca678; color:#fff; text-decoration:none;"><i class="fas fa-check"></i> Reconciled</a>
                                        <?php else: ?>
                                            <a href="?action=reconcile&id=<?= $row['id'] ?>" class="btn btn-secondary" style="padding:2px 6px; font-size:8px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-clock"></i> Reconcile</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="padding:20px; text-align:center; color:var(--color-text-faint);">No ledger transaction matching search.</td>
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
</div>
