<?php
/**
 * ==========================================================================
 * admin/suppliers/edit.php — Modify Supplier Vendor Details
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Edit Supplier — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('purchases.manage');

$pdo = db();
$supplierId = (int) input('id', '0', 'get');

if ($supplierId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $supplierId]);
    $s = $stmt->fetch();

    if (!$s) {
        flash('supplier_msg', 'Supplier vendor not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/suppliers/edit] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $name = trim(input('name', ''));
        $contactName = trim(input('contact_name', ''));
        $email = trim(input('email', ''));
        $phone = trim(input('phone', ''));
        $address = trim(input('address', ''));

        if (empty($name)) {
            $error = 'Supplier Name is a required field.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE suppliers SET
                        name = :name, contact_name = :contact_name,
                        email = :email, phone = :phone, address = :address
                    WHERE id = :id
                ");
                $stmt->execute([
                    'name'         => $name,
                    'contact_name' => !empty($contactName) ? $contactName : null,
                    'email'        => !empty($email) ? $email : null,
                    'phone'        => !empty($phone) ? $phone : null,
                    'address'      => !empty($address) ? $address : null,
                    'id'           => $supplierId
                ]);

                log_admin_activity('suppliers.edit', "Updated supplier details: '{$name}'");
                flash('supplier_msg', "Supplier '{$name}' updated successfully!", 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/suppliers/edit] update failed: ' . $e->getMessage());
                $error = 'Failed to update supplier record details.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit Supplier Details</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Update company parameters, email domains, and phone numbers.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Suppliers list</a>
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
                <label style="font-weight:700;">Supplier Company Name *</label>
                <input type="text" name="name" required value="<?= e($s['name']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Primary Contact Person Name</label>
                <input type="text" name="contact_name" value="<?= e($s['contact_name'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Email Address</label>
                <input type="email" name="email" value="<?= e($s['email'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Phone / Mobile</label>
                <input type="text" name="phone" value="<?= e($s['phone'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Business Address</label>
            <textarea name="address" rows="3" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical; font-family:inherit;"><?= e($s['address'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
