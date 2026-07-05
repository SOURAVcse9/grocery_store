<?php
/**
 * ==========================================================================
 * admin/expiry-products/index.php — Expiry Batch Alerts & Logs
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Expiry Tracking — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('inventory.manage');

$pdo = db();
$error = null;
$success = null;

try {
    $products = $pdo->query("SELECT id, name, stock FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC")->fetchAll();
    
    // Fetch expiry batch list
    $batches = $pdo->query("
        SELECT ep.*, p.name AS product_name 
        FROM expiry_products ep
        JOIN products p ON p.id = ep.product_id
        ORDER BY ep.expiry_date ASC
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/expiry-products] load failed: ' . $e->getMessage());
    $products = $batches = [];
}

if (method_is('post')) {
    verify_csrf_or_fail();
    $productId = (int) input('product_id', '0');
    $batchNumber = trim(input('batch_number', ''));
    $expiryDate = trim(input('expiry_date', ''));
    $qty = (int) input('quantity', '0');

    if ($productId <= 0 || empty($expiryDate) || $qty <= 0) {
        $error = 'Product selection, expiry date, and quantity are required fields.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO expiry_products (product_id, batch_number, expiry_date, quantity)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$productId, !empty($batchNumber) ? $batchNumber : null, $expiryDate, $qty]);
            
            log_admin_activity('inventory.expiry_batch', "Logged expiry batch for Product ID: {$productId}: Batch '{$batchNumber}' expiring {$expiryDate}");
            
            // Refresh data
            $batches = $pdo->query("
                SELECT ep.*, p.name AS product_name 
                FROM expiry_products ep
                JOIN products p ON p.id = ep.product_id
                ORDER BY ep.expiry_date ASC
            ")->fetchAll();
            
            $success = 'Expiry batch logged successfully!';
        } catch (PDOException $e) {
            error_log('[admin/expiry-products] failed to save: ' . $e->getMessage());
            $error = 'Failed to record expiry batch due to database error.';
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Expiry Batch Tracking</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Monitor product expiry timelines, track item batch numbers, and verify shelf life.</p>
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

<div style="display:grid; grid-template-columns:1.5fr 2.5fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left: Log expiry batch form -->
    <div class="dashboard-card" style="padding:var(--space-5); margin:0; align-self:start;">
        <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Log Expiry Batch</h3>
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
                <label style="font-weight:700;">Batch Number / Lot Number</label>
                <input type="text" name="batch_number" placeholder="E.g. B-9932" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:10px;" class="grid-2">
                <div class="form-field-group">
                    <label style="font-weight:700;">Expiry Date *</label>
                    <input type="date" name="expiry_date" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Quantity Units *</label>
                    <input type="number" name="quantity" min="1" required placeholder="E.g. 50" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-calendar-plus"></i> Save Batch</button>
        </form>
    </div>

    <!-- Right: Expiry Batch list -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Expiry Batch Directory</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Product Name</th>
                        <th style="padding:10px 15px;">Batch / Lot</th>
                        <th style="padding:10px 15px; text-align:center;">Qty</th>
                        <th style="padding:10px 15px;">Expiry Date</th>
                        <th style="padding:10px 15px; text-align:right;">Alert Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($batches)): ?>
                        <?php foreach ($batches as $row): 
                            $expiryTime = strtotime($row['expiry_date']);
                            $daysLeft = (int) ceil(($expiryTime - time()) / 86400);
                            
                            $badge = 'pill-completed';
                            $text = 'Active';
                            if ($daysLeft <= 0) {
                                $badge = 'pill-cancelled';
                                $text = 'EXPIRED';
                            } elseif ($daysLeft <= 30) {
                                $badge = 'pill-pending';
                                $text = "EXPIRING ({$daysLeft} d)";
                            }
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px;"><strong><?= e($row['product_name']) ?></strong></td>
                                <td style="padding:8px 15px; font-family:monospace;"><?= e($row['batch_number'] ?: 'N/A') ?></td>
                                <td style="padding:8px 15px; text-align:center; font-weight:700;"><?= $row['quantity'] ?></td>
                                <td style="padding:8px 15px; color:var(--color-text-muted);"><?= date('Y-m-d', $expiryTime) ?></td>
                                <td style="padding:8px 15px; text-align:right;">
                                    <span class="status-pill <?= $badge ?>" style="font-size:8px;">
                                        <?= $text ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding:20px; text-align:center; color:var(--color-text-faint);">No product expiry batches tracked.</td>
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
