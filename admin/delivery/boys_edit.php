<?php
/**
 * ==========================================================================
 * admin/delivery/boys_edit.php — Modify Delivery Boy Details
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Edit Delivery Boy — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('delivery.manage');

$pdo = db();
$boyId = (int) input('id', '0', 'get');

if ($boyId <= 0) {
    header('Location: boys.php');
    exit;
}

$error = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM delivery_boys WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $boyId]);
    $b = $stmt->fetch();

    if (!$b) {
        flash('boy_msg', 'Delivery personnel not found.', 'error');
        header('Location: boys.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/delivery/boys_edit] load failed: ' . $e->getMessage());
    header('Location: boys.php');
    exit;
}

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
                // Check uniqueness excluding current ID
                $check = $pdo->prepare("SELECT COUNT(*) FROM delivery_boys WHERE (phone = ? OR email = ?) AND id != ?");
                $check->execute([$phone, $email, $boyId]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Phone number or email is already registered to another delivery agent.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE delivery_boys SET
                            name = ?, phone = ?, email = ?, commission_rate = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $phone, $email, $commissionRate, $status, $boyId]);

                    log_admin_activity('delivery.boy_edit', "Updated delivery agent details: '{$name}'");
                    flash('boy_msg', "Delivery agent '{$name}' details updated successfully!", 'success');
                    header('Location: boys.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[admin/delivery/boys_edit] update failed: ' . $e->getMessage());
                $error = 'Failed to update delivery agent record.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit Delivery Agent</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Modify duty states and fixed commission levels.</p>
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
                <input type="text" name="name" required value="<?= e($b['name']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Phone / Mobile *</label>
                <input type="text" name="phone" required value="<?= e($b['phone']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Email Address *</label>
                <input type="email" name="email" required value="<?= e($b['email']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Commission Rate per Order (৳) *</label>
                <input type="number" name="commission_rate" step="0.01" value="<?= e($b['commission_rate']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Duty Status</label>
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="active" <?= $b['status'] === 'active' ? 'selected' : '' ?>>Active (On Duty)</option>
                <option value="inactive" <?= $b['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Off Duty)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
