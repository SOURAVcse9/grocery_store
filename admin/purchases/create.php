<?php
/**
 * ==========================================================================
 * admin/purchases/create.php — Generate New Purchase Order (PO)
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Purchase Order — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('purchases.manage');

$pdo = db();
$error = null;

try {
    $suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();
    $products = $pdo->query("SELECT id, name, price FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/purchases/create] fetch options failed: ' . $e->getMessage());
    $suppliers = $products = [];
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $supplierId = (int) input('supplier_id', '0');
        $orderNumber = trim(input('order_number', ''));
        
        $items = $_POST['items'] ?? []; // format: array of [product_id, quantity, unit_cost]
        
        if ($supplierId <= 0 || empty($orderNumber)) {
            $error = 'Supplier selection and Purchase Order Number are required fields.';
        } else {
            try {
                // Check PO uniqueness
                $check = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE order_number = ?");
                $check->execute([$orderNumber]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = "A purchase order with number '{$orderNumber}' already exists.";
                } else {
                    $pdo->beginTransaction();

                    $stmtPO = $pdo->prepare("
                        INSERT INTO purchase_orders (supplier_id, order_number, status, total_amount, created_at)
                        VALUES (?, ?, 'pending', 0, NOW())
                    ");
                    $stmtPO->execute([$supplierId, $orderNumber]);
                    $poId = (int) $pdo->lastInsertId();

                    $totalAmount = 0.0;
                    $stmtItem = $pdo->prepare("
                        INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_cost)
                        VALUES (?, ?, ?, ?)
                    ");

                    foreach ($items as $item) {
                        $prodId = (int)($item['product_id'] ?? 0);
                        $qty = (int)($item['quantity'] ?? 0);
                        $cost = (float)($item['unit_cost'] ?? 0.00);

                        if ($prodId > 0 && $qty > 0 && $cost >= 0) {
                            $stmtItem->execute([$poId, $prodId, $qty, $cost]);
                            $totalAmount += ($qty * $cost);
                        }
                    }

                    // Update total amount on parent PO
                    $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?")->execute([$totalAmount, $poId]);

                    $pdo->commit();
                    log_admin_activity('purchases.create', "Generated purchase order: '{$orderNumber}' with value ৳{$totalAmount}");
                    flash('purchase_msg', "Purchase Order '{$orderNumber}' generated successfully!", 'success');
                    header('Location: index.php');
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin/purchases/create] PO Save failed: ' . $e->getMessage());
                $error = 'Failed to generate purchase order due to database transaction error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Create Purchase Order</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add a new vendor procurement schedule record.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Purchases list</a>
</div>

<!-- Errors display -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-6);">
    <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Select Supplier *</label>
                <select name="supplier_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Choose vendor...</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Purchase Order Number (PO #) *</label>
                <input type="text" name="order_number" required value="PO-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <h3 style="font-size:13px; font-weight:800; color:var(--color-text); margin:0 0 10px 0; border-bottom:1px solid var(--color-border); padding-bottom:6px;">Purchase Item Lines</h3>
        
        <!-- Procurement Item lines -->
        <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:10px;" class="grid-3">
                    <div class="form-field-group" style="margin:0;">
                        <select name="items[<?= $i ?>][product_id]" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; background:#fff;">
                            <option value="">Choose item...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (Current retail: ৳<?= number_format((float)$p['price'], 2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field-group" style="margin:0;">
                        <input type="number" name="items[<?= $i ?>][unit_cost]" step="0.01" min="0" placeholder="Unit wholesale cost (৳)" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px;">
                    </div>
                    <div class="form-field-group" style="margin:0;">
                        <input type="number" name="items[<?= $i ?>][quantity]" min="1" placeholder="Quantity units" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px;">
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-plus"></i> Generate Purchase Order</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
