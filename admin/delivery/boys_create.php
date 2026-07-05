<?php
/**
 * ==========================================================================
 * admin/delivery/boys_create.php — Register New Delivery Boy
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Delivery Boy — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('delivery.manage');

$pdo = db();
$error = null;

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $name = trim(input('name', ''));
        $phone = trim(input('phone', ''));
        $email = trim(input('email', ''));
        $commissionRate = (float) input('commission_rate', '50.00');
        $status = input('status', 'active');

        if (empty($name) || empty($phone) || empty($email)) {
            $error = 'Name, Phone, and Email address are required fields.';
        } else {
            try {
                // Check uniqueness
                $check = $pdo->prepare("SELECT COUNT(*) FROM delivery_boys WHERE phone = ? OR email = ?");
                $check->execute([$phone, $email]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Phone number or email is already registered to another delivery personnel.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO delivery_boys (name, phone, email, commission_rate, status)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $phone, $email, $commissionRate, $status]);

                    log_admin_activity('delivery.boy_create', "Registered delivery agent: '{$name}'");
                    flash('boy_msg', "Delivery agent '{$name}' registered successfully!", 'success');
                    header('Location: boys.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[admin/delivery/boys_create] failed: ' . $e->getMessage());
                $error = 'Failed to register delivery agent due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add Delivery Agent</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Register new fleet dispatch personnel.</p>
    </div>
    <a href="boys.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Personnel list</a>
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
                <label style="font-weight:700;">Full Name *</label>
                <input type="text" name="name" required placeholder="E.g. Shajib Hossain" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Phone / Mobile *</label>
                <input type="text" name="phone" required placeholder="E.g. +8801700000000" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Email Address *</label>
                <input type="email" name="email" required placeholder="E.g. shajib@groco.com" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Commission Rate per Order (৳) *</label>
                <input type="number" name="commission_rate" step="0.01" value="50.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Duty Status</label>
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="active">Active (On Duty)</option>
                <option value="inactive">Inactive (Off Duty)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-plus"></i> Register Delivery Boy</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
