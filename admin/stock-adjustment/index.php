<?php
/**
 * ==========================================================================
 * admin/stock-adjustment/index.php — Manual Inventory Stock Adjustment
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Stock Adjustments — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('inventory.manage');

$pdo = db();
$error = null;
$success = null;

try {
    $products = $pdo->query("SELECT id, name, stock FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/stock-adjustment] load products failed: ' . $e->getMessage());
    $products = [];
}

if (method_is('post')) {
    verify_csrf_or_fail();
    $productId = (int) input('product_id', '0');
    $adjustmentQty = (int) input('adjustment_qty', '0'); // can be positive or negative
    $reason = trim(input('reason', ''));

    if ($productId <= 0 || $adjustmentQty === 0 || empty($reason)) {
        $error = 'Product selection, non-zero adjustment units, and reason are required fields.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtProd = $pdo->prepare("SELECT stock, name FROM products WHERE id = :id FOR UPDATE");
            $stmtProd->execute(['id' => $productId]);
            $prod = $stmtProd->fetch();

            if ($prod) {
                $oldStock = (int) $prod['stock'];
                $newStock = $oldStock + $adjustmentQty;

                if ($newStock < 0) {
                    $error = 'Adjustment quantity exceeds current stock. Final stock cannot be negative.';
                    $pdo->rollBack();
                } else {
                    // Update stock
                    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$newStock, $productId]);

                    // Log inventory logs
                    $stmtLog = $pdo->prepare("
                        INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at)
                        VALUES (:pid, :admin_id, 'adjustment', :qty, :rem_stock, :note, NOW())
                    ");
                    $stmtLog->execute([
                        'pid'      => $productId,
                        'admin_id' => current_admin_id(),
                        'qty'      => $adjustmentQty,
                        'rem_stock' => $newStock,
                        'note'     => "Manual Stock Adjustment. Reason: {$reason}"
                    ]);

                    $pdo->commit();
                    log_admin_activity('inventory.adjustment', "Adjusted stock for '{$prod['name']}' by {$adjustmentQty} units.");
                    $success = "Stock adjusted successfully for {$prod['name']}! New stock: {$newStock} units.";
                }
            } else {
                $pdo->rollBack();
                $error = 'Product not found.';
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin/stock-adjustment] Adjust failed: ' . $e->getMessage());
            $error = 'Failed to execute stock adjustment transaction due to server error.';
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Stock Adjustments</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Apply positive/negative stock corrections based on physical inventory counts.</p>
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
            <label style="font-weight:700;">Adjustment Quantity (Positive to add, Negative to subtract) *</label>
            <input type="number" name="adjustment_qty" required placeholder="E.g. -5 or 12" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Reason *</label>
            <select name="reason" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">Select reason...</option>
                <option value="Physical Count Reconciled">Physical Count Reconciled</option>
                <option value="Data Entry Correction">Data Entry Correction</option>
                <option value="Item Shrinkage / Theft">Item Shrinkage / Theft</option>
                <option value="Vendor Shortage">Vendor Shortage</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-check"></i> Execute Stock Adjustment</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
