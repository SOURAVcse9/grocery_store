<?php
/**
 * ==========================================================================
 * admin/suppliers/create.php — Add New Supplier Vendor
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Supplier — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('purchases.manage');

$pdo = db();
$error = null;

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
                    INSERT INTO suppliers (name, contact_name, email, phone, address)
                    VALUES (:name, :contact_name, :email, :phone, :address)
                ");
                $stmt->execute([
                    'name'         => $name,
                    'contact_name' => !empty($contactName) ? $contactName : null,
                    'email'        => !empty($email) ? $email : null,
                    'phone'        => !empty($phone) ? $phone : null,
                    'address'      => !empty($address) ? $address : null
                ]);

                log_admin_activity('suppliers.create', "Registered supplier vendor: '{$name}'");
                flash('supplier_msg', "Supplier '{$name}' registered successfully!", 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/suppliers/create] failed: ' . $e->getMessage());
                $error = 'Failed to register supplier due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add Supplier Vendor</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add wholesale merchant contact and location registry details.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Suppliers List</a>
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
                <input type="text" name="name" required placeholder="E.g. PRAN-RFL Group Wholesale" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Primary Contact Person Name</label>
                <input type="text" name="contact_name" placeholder="E.g. Mr. Kamal Hossain" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Email Address</label>
                <input type="email" name="email" placeholder="E.g. sales@pranfoods.com" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Phone / Mobile</label>
                <input type="text" name="phone" placeholder="E.g. +8801700000000" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Business Address</label>
            <textarea name="address" rows="3" placeholder="E.g. PRAN-RFL Center, Middle Badda, Dhaka" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical; font-family:inherit;"></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-plus"></i> Register Supplier</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
