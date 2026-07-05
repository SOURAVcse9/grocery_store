<?php
/**
 * ==========================================================================
 * admin/coupons/create.php — Create New Coupon
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Coupon — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('coupons.manage');

$pdo = db();
$error = null;

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $code = strtoupper(trim(input('code', '')));
        $type = trim(input('type', 'percentage'));
        
        $discountPercent = (float) input('discount_percent', '0.00');
        $discountAmount = (float) input('discount_amount', '0.00');
        $minOrderAmount = (float) input('min_order_amount', '0.00');
        $maxDiscountAmount = (float) input('max_discount_amount', '0.00');
        
        $usageLimit = (int) input('usage_limit', '100');
        $validFrom = trim(input('valid_from', ''));
        $validUntil = trim(input('valid_until', ''));
        $isActive = (int) input('is_active', '1');

        if (empty($code) || empty($validFrom) || empty($validUntil)) {
            $error = 'Coupon Code, Start Date, and Expiry Date are required fields.';
        } else {
            try {
                // Check uniqueness
                $check = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE code = :code");
                $check->execute(['code' => $code]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = "A coupon code named '{$code}' already exists.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO coupons (
                            code, type, discount_percent, discount_amount, 
                            min_order_amount, max_discount_amount, usage_limit, times_used,
                            valid_from, valid_until, is_active, created_at
                        ) VALUES (
                            :code, :type, :pct, :amt, 
                            :min, :max, :limit, 0,
                            :from, :until, :active, NOW()
                        )
                    ");
                    $stmt->execute([
                        'code'   => $code,
                        'type'   => $type,
                        'pct'    => $discountPercent,
                        'amt'    => $discountAmount,
                        'min'    => $minOrderAmount,
                        'max'    => $maxDiscountAmount,
                        'limit'  => $usageLimit,
                        'from'   => $validFrom,
                        'until'  => $validUntil,
                        'active' => $isActive
                    ]);

                    log_admin_activity('coupons.create', "Created coupon discount code: '{$code}'");
                    flash('coupon_msg', "Coupon Code '{$code}' created successfully!", 'success');
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[admin/coupons/create] Save failed: ' . $e->getMessage());
                $error = 'Failed to create coupon record due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Create Coupon Code</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add a new shopping cart discount coupon.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Coupons List</a>
</div>

<!-- Errors display -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-6); max-width: 700px;">
    <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="couponCode" style="font-weight:700;">Coupon Code *</label>
                <input type="text" id="couponCode" name="code" required placeholder="E.g. SAVE30" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="couponType" style="font-weight:700;">Discount Type *</label>
                <select id="couponType" name="type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="percentage">Percentage Off (%)</option>
                    <option value="fixed">Fixed Amount Off (৳)</option>
                    <option value="free_shipping">Free Shipping</option>
                </select>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="couponPct" style="font-weight:700;">Discount Percent (%)</label>
                <input type="number" id="couponPct" name="discount_percent" min="0" max="100" step="0.01" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="couponAmt" style="font-weight:700;">Fixed Discount Amount (৳)</label>
                <input type="number" id="couponAmt" name="discount_amount" min="0" step="0.01" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="couponMin" style="font-weight:700;">Minimum Purchase (৳)</label>
                <input type="number" id="couponMin" name="min_order_amount" min="0" step="0.01" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="couponMax" style="font-weight:700;">Maximum Discount (৳)</label>
                <input type="number" id="couponMax" name="max_discount_amount" min="0" step="0.01" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="couponFrom" style="font-weight:700;">Valid From Date *</label>
                <input type="date" id="couponFrom" name="valid_from" required value="<?= date('Y-m-d') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="couponUntil" style="font-weight:700;">Valid Until Date *</label>
                <input type="date" id="couponUntil" name="valid_until" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="couponLimit" style="font-weight:700;">Total Usage Limit</label>
                <input type="number" id="couponLimit" name="usage_limit" min="1" value="100" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="couponStatus" style="font-weight:700;">Active Status</label>
                <select id="couponStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1">Enabled (Active)</option>
                    <option value="0">Disabled (Inactive)</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-plus"></i> Create Coupon</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
