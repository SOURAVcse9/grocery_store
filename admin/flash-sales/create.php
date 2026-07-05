<?php
/**
 * ==========================================================================
 * admin/flash-sales/create.php — Create New Flash Sale Campaign
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Flash Sale — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('flashsales.manage');

$pdo = db();
$error = null;

try {
    $products = $pdo->query("SELECT id, name, price FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/flash-sales/create] fetch products fail: ' . $e->getMessage());
    $products = [];
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $productId = (int) input('product_id', '0');
        $discountPercent = (float) input('discount_percent', '0.00');
        $startsAt = trim(input('starts_at', ''));
        $endsAt = trim(input('ends_at', ''));
        $isActive = (int) input('is_active', '1');

        if ($productId <= 0 || $discountPercent <= 0 || empty($startsAt) || empty($endsAt)) {
            $error = 'Product selection, discount percentage, start window, and expiry window are required.';
        } else {
            try {
                // Check if campaign already exists for this product
                $check = $pdo->prepare("SELECT COUNT(*) FROM flash_sales WHERE product_id = :pid AND is_active = 1 AND ends_at > NOW()");
                $check->execute(['pid' => $productId]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'An active flash sale campaign is already running for the selected product.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO flash_sales (product_id, discount_percent, starts_at, ends_at, is_active)
                        VALUES (:pid, :pct, :start, :end, :active)
                    ");
                    $stmt->execute([
                        'pid'    => $productId,
                        'pct'    => $discountPercent,
                        'start'  => $startsAt,
                        'end'    => $endsAt,
                        'active' => $isActive
                    ]);

                    log_admin_activity('flashsales.create', "Created flash sale campaign for Product ID: {$productId} with {$discountPercent}% discount.");
                    flash('flash_sale_msg', 'Flash sale campaign created successfully!', 'success');
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[admin/flash-sales/create] Save failed: ' . $e->getMessage());
                $error = 'Failed to create campaign due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add Flash Sale Campaign</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add a new promotional price discount with countdown scheduling.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Campaigns List</a>
</div>

<!-- Errors display -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-6); max-width: 600px;">
    <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label for="fsProduct" style="font-weight:700;">Select Product *</label>
            <select id="fsProduct" name="product_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">Choose product...</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (৳<?= number_format((float)$p['price'], 2) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="fsDiscount" style="font-weight:700;">Discount percentage (%) *</label>
                <input type="number" id="fsDiscount" name="discount_percent" min="0.01" max="100" step="0.01" required placeholder="E.g. 15.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="fsStatus" style="font-weight:700;">Campaign status</label>
                <select id="fsStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1">Enabled (Active)</option>
                    <option value="0">Disabled (Inactive)</option>
                </select>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="fsStart" style="font-weight:700;">Starts At *</label>
                <input type="datetime-local" id="fsStart" name="starts_at" required value="<?= date('Y-m-d\TH:i') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="fsEnd" style="font-weight:700;">Ends At *</label>
                <input type="datetime-local" id="fsEnd" name="ends_at" required value="<?= date('Y-m-d\TH:i', strtotime('+24 hours')) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-plus"></i> Create Campaign</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
