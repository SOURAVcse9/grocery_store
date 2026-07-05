<?php
/**
 * ==========================================================================
 * admin/pos/discounts.php — POS Cashier Discount Override Rules
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'POS Override Discount Rules — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.discount');

$pdo = db();
$error = null;
$success = null;

// Handle saving configurations
if (method_is('post')) {
    verify_csrf_or_fail();
    $maxPct = (float) input('max_discount_pct', '15.00');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (key_name, value, updated_at)
            VALUES ('pos_max_discount_pct', :val, NOW())
            ON DUPLICATE KEY UPDATE value = :val2, updated_at = NOW()
        ");
        $stmt->execute(['val' => (string)$maxPct, 'val2' => (string)$maxPct]);
        log_admin_activity('pos.discounts_edit', "Updated maximum cashier discount override limit to {$maxPct}%");
        $success = 'Maximum override discount threshold updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to save settings to database.';
    }
}

// Fetch current setting
try {
    $maxLimit = (float) $pdo->query("SELECT value FROM settings WHERE key_name = 'pos_max_discount_pct'")->fetchColumn();
    if ($maxLimit <= 0) $maxLimit = 15.00; // default fallback
} catch (PDOException $e) {
    $maxLimit = 15.00;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Discount & Override Rules</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage maximum override discount thresholds cashiers can apply without requiring admin keys.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<!-- Alerts -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-5); max-width: 600px;">
    <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label style="font-weight:700;">Maximum Cashier Override Discount (%) *</label>
            <input type="number" name="max_discount_pct" step="0.01" value="<?= e((string)$maxLimit) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            <span style="font-size:10px; color:var(--color-text-faint);">Cashiers will be blocked from applying custom discounts exceeding this percentage during cart finalization.</span>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-check"></i> Save Rules</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
