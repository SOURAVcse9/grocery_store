<?php
/**
 * ==========================================================================
 * admin/stock-transfer/index.php — Stock Bins/Shelves Transfer Logger
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Stock Transfers — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('inventory.manage');

$pdo = db();
$error = null;
$success = null;

try {
    $products = $pdo->query("SELECT id, name, stock FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/stock-transfer] load failed: ' . $e->getMessage());
    $products = [];
}

if (method_is('post')) {
    verify_csrf_or_fail();
    $productId = (int) input('product_id', '0');
    $qty = (int) input('quantity', '0');
    $fromBin = trim(input('from_bin', ''));
    $toBin = trim(input('to_bin', ''));

    if ($productId <= 0 || $qty <= 0 || empty($fromBin) || empty($toBin)) {
        $error = 'Product selection, quantity, source bin, and target bin are required fields.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT stock, name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $prod = $stmt->fetch();

            if ($prod) {
                if ((int)$prod['stock'] < $qty) {
                    $error = 'Insufficient physical stock to complete the transfer.';
                } else {
                    // Log transfer inside inventory logs (stock is moved between bins, so net change is 0 but we log the location transfer note!)
                    $log = $pdo->prepare("
                        INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at)
                        VALUES (?, ?, 'transfer', 0, ?, ?, NOW())
                    ");
                    $note = "Transferred {$qty} units from shelf/bin '{$fromBin}' to '{$toBin}'";
                    $log->execute([$productId, current_admin_id(), $prod['stock'], $note]);

                    log_admin_activity('inventory.transfer', "Logged bin transfer for Product ID: {$productId}: {$note}");
                    $success = "Successfully logged stock transfer for {$prod['name']}!";
                }
            }
        } catch (PDOException $e) {
            error_log('[admin/stock-transfer] save failed: ' . $e->getMessage());
            $error = 'Database error while logging stock transfer.';
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Stock Bins Transfers</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Log internal movement of items between virtual warehouses, display shelves, or storage bins.</p>
    </div>
    <a href="../inventory/index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Inventory Ledger</a>
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

<div class="dashboard-card" style="padding:var(--space-6); max-width: 600px;">
    <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label style="font-weight:700;">Select Product *</label>
            <select name="product_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">Choose item...</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (Current: <?= $p['stock'] ?> units)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Transfer Quantity Units *</label>
            <input type="number" name="quantity" min="1" required placeholder="E.g. 10" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Source Location/Bin *</label>
                <input type="text" name="from_bin" required placeholder="E.g. Shelf A-4" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Destination Location/Bin *</label>
                <input type="text" name="to_bin" required placeholder="E.g. Disp-Counter 1" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-truck-ramp-box"></i> Log Transfer</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
