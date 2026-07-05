<?php
/**
 * ==========================================================================
 * admin/damaged-products/index.php — Damaged Stock Tracker & Write-offs
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Damaged Stock — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('inventory.manage');

$pdo = db();
$error = null;
$success = null;

try {
    $products = $pdo->query("SELECT id, name, stock FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC")->fetchAll();
    
    // Fetch damaged list
    $damaged = $pdo->query("
        SELECT dp.*, p.name AS product_name 
        FROM damaged_products dp
        JOIN products p ON p.id = dp.product_id
        ORDER BY dp.created_at DESC
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/damaged-products] load failed: ' . $e->getMessage());
    $products = $damaged = [];
}

if (method_is('post')) {
    verify_csrf_or_fail();
    $productId = (int) input('product_id', '0');
    $qty = (int) input('quantity', '0');
    $reason = trim(input('reason', ''));

    if ($productId <= 0 || $qty <= 0 || empty($reason)) {
        $error = 'Product selection, positive units quantity, and write-off reason are required.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtProd = $pdo->prepare("SELECT stock, name FROM products WHERE id = :id FOR UPDATE");
            $stmtProd->execute(['id' => $productId]);
            $prod = $stmtProd->fetch();

            if ($prod) {
                $oldStock = (int) $prod['stock'];
                $newStock = $oldStock - $qty;

                if ($newStock < 0) {
                    $error = 'Damaged quantity write-off exceeds current available stock.';
                    $pdo->rollBack();
                } else {
                    // Update stock
                    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$newStock, $productId]);

                    // Insert to damaged_products table
                    $stmtDam = $pdo->prepare("INSERT INTO damaged_products (product_id, quantity, reason) VALUES (?, ?, ?)");
                    $stmtDam->execute([$productId, $qty, $reason]);

                    // Log inventory logs
                    $stmtLog = $pdo->prepare("
                        INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at)
                        VALUES (:pid, :admin_id, 'stock_out', :qty, :rem_stock, :note, NOW())
                    ");
                    $stmtLog->execute([
                        'pid'      => $productId,
                        'admin_id' => current_admin_id(),
                        'qty'      => -$qty,
                        'rem_stock' => $newStock,
                        'note'     => "Damaged Stock Write-off. Reason: {$reason}"
                    ]);

                    $pdo->commit();
                    log_admin_activity('inventory.damaged', "Wrote off {$qty} damaged units of '{$prod['name']}'");
                    
                    // Refresh data
                    $damaged = $pdo->query("
                        SELECT dp.*, p.name AS product_name 
                        FROM damaged_products dp
                        JOIN products p ON p.id = dp.product_id
                        ORDER BY dp.created_at DESC
                    ")->fetchAll();
                    
                    $success = "Damaged stock successfully written off for {$prod['name']}!";
                }
            } else {
                $pdo->rollBack();
                $error = 'Product not found.';
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin/damaged-products] Write-off failed: ' . $e->getMessage());
            $error = 'Failed to record write-off due to database transaction error.';
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Damaged Products Write-off</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Audit and report broken, spoiled, or spilled products, and reduce stock levels automatically.</p>
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

<div style="display:grid; grid-template-columns:1.5fr 2fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left: Log write-off form -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Record Damaged Assets</h3>
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
                <label style="font-weight:700;">Quantity Units *</label>
                <input type="number" name="quantity" min="1" required placeholder="E.g. 3" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Write-off Reason *</label>
                <select name="reason" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Select reason...</option>
                    <option value="Item Expired / Rot / Mold">Item Expired / Rot / Mold</option>
                    <option value="Packaging Broken / Leak">Packaging Broken / Leak</option>
                    <option value="Fell from rack / Shelf accident">Fell from rack / Shelf accident</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-trash-can"></i> Record Write-off</button>
        </form>
    </div>

    <!-- Right: History log list -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Logged Damaged History</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Product Name</th>
                        <th style="padding:10px 15px; text-align:center;">Qty</th>
                        <th style="padding:10px 15px;">Write-off Reason</th>
                        <th style="padding:10px 15px;">Logged Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($damaged)): ?>
                        <?php foreach ($damaged as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px;"><strong><?= e($row['product_name']) ?></strong></td>
                                <td style="padding:8px 15px; text-align:center; font-weight:700; color:#f03e3e;"><?= $row['quantity'] ?> units</td>
                                <td style="padding:8px 15px; color:var(--color-text-muted);"><?= e($row['reason']) ?></td>
                                <td style="padding:8px 15px; color:var(--color-text-faint);"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding:20px; text-align:center; color:var(--color-text-faint);">No damaged inventory logged yet.</td>
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
