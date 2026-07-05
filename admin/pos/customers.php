<?php
/**
 * ==========================================================================
 * admin/pos/customers.php — POS Customer Loyalty & Wallet Credits Settings
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'POS Customers Loyalty — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.access');

$pdo = db();
$error = null;
$success = null;

// Handle Adjustments (Wallet credits / Loyalty points)
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $customerId = (int) input('customer_id', '0');
        $walletAdjust = (float) input('wallet_adjustment', '0.00');
        $pointsAdjust = (int) input('points_adjustment', '0');

        if ($customerId > 0) {
            try {
                $pdo->beginTransaction();

                // Apply updates
                if ($walletAdjust !== 0.00) {
                    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$walletAdjust, $customerId]);
                    log_admin_activity('pos.wallet_adjust', "Adjusted wallet balance for Customer ID {$customerId} by ৳{$walletAdjust}");
                }

                if ($pointsAdjust !== 0) {
                    $pdo->prepare("UPDATE users SET reward_points = reward_points + ? WHERE id = ?")->execute([$pointsAdjust, $customerId]);
                    log_admin_activity('pos.points_adjust', "Adjusted reward points for Customer ID {$customerId} by {$pointsAdjust} pts");
                }

                $pdo->commit();
                $success = 'Loyalty parameters adjusted successfully!';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin/pos/customers] adjustment failed: ' . $e->getMessage());
                $error = 'Failed to execute loyalty adjustments due to database error.';
            }
        }
    }
}

// Fetch all customer profiles
try {
    $customers = $pdo->query("
        SELECT id, full_name, email, phone, wallet_balance, reward_points 
        FROM users 
        WHERE role_id != 1 AND deleted_at IS NULL 
        ORDER BY full_name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/pos/customers] load failed: ' . $e->getMessage());
    $customers = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Customer Loyalty & Wallets</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Moderate store credits, reward point accounts, and client details lookup.</p>
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
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr 2.5fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Adjustment form -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Loyalty Adjuster Form</h3>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>

            <div class="form-field-group">
                <label style="font-weight:700;">Select Customer *</label>
                <select name="customer_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Choose customer...</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['full_name']) ?> (<?= e($c['phone']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Add/Subtract Wallet Balance (৳)</label>
                <input type="number" name="wallet_adjustment" step="0.01" value="0.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                <span style="font-size:10px; color:var(--color-text-faint);">Use negative values to subtract balances.</span>
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Add/Subtract Reward Points</label>
                <input type="number" name="points_adjustment" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-check"></i> Save Adjustments</button>
        </form>
    </div>

    <!-- Right Column: Listing profiles -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px;">Customer Profile</th>
                        <th style="padding:16px 20px;">Contact Phone</th>
                        <th style="padding:16px 20px; text-align:right; width:180px;">Wallet Balance</th>
                        <th style="padding:16px 20px; text-align:center; width:150px;">Reward Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px;">
                                    <strong><?= e($row['full_name']) ?></strong><br>
                                    <span style="font-size:11px; color:var(--color-text-faint);"><?= e($row['email']) ?></span>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($row['phone'] ?: 'N/A') ?></td>
                                <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['wallet_balance'], 2) ?></td>
                                <td style="padding:12px 20px; text-align:center; font-weight:700; color:#339af0;"><?= $row['reward_points'] ?> pts</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding:32px; text-align:center; color:var(--color-text-faint);">No customers registered.</td>
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
