<?php
/**
 * ==========================================================================
 * admin/admins/create.php — Add New Administrative User
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Admin — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('admins.manage');

$pdo = db();
$error = null;

try {
    $roles = $pdo->query("SELECT id, name FROM admin_roles ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/admins/create] failed roles fetch: ' . $e->getMessage());
    $roles = [];
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $fullName = trim(input('full_name', ''));
        $username = trim(input('username', ''));
        $email = trim(input('email', ''));
        $phone = trim(input('phone', ''));
        $password = trim(input('password', ''));
        $roleId = (int) input('role_id', '0');
        $isActive = (int) input('is_active', '1');

        if (empty($fullName) || empty($username) || empty($email) || empty($password) || $roleId <= 0) {
            $error = 'Full Name, Username, Email Address, Password, and Role selection are required fields.';
        } else {
            try {
                // Check uniqueness of username and email
                $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = :uname OR email = :email");
                $check->execute(['uname' => $username, 'email' => $email]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'The username or email address is already taken by another admin account.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO admins (role_id, username, email, password, full_name, phone, is_active, created_at)
                        VALUES (:role_id, :username, :email, :password, :full_name, :phone, :active, NOW())
                    ");
                    $stmt->execute([
                        'role_id'   => $roleId,
                        'username'  => $username,
                        'email'     => $email,
                        'password'  => $hash,
                        'full_name' => $fullName,
                        'phone'     => !empty($phone) ? $phone : null,
                        'active'    => $isActive
                    ]);

                    log_admin_activity('admins.create', "Created admin account: '{$username}' with role ID: {$roleId}");
                    flash('admin_msg', "Admin account '@{$username}' created successfully!", 'success');
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[admin/admins/create] failed: ' . $e->getMessage());
                $error = 'Failed to create admin record due to server database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Create Administrative Account</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add backoffice personnel with dedicated role permission scopes.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Admins list</a>
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
                <input type="text" name="full_name" required placeholder="E.g. Tanvir Ahmed" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Username *</label>
                <input type="text" name="username" required placeholder="E.g. tanvir_admin" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Email Address *</label>
                <input type="email" name="email" required placeholder="E.g. admin@grocerystore.com" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Phone Number</label>
                <input type="text" name="phone" placeholder="E.g. +8801700000000" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Account Password *</label>
                <input type="password" name="password" required placeholder="Minimum 6 characters..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Assign Security Role *</label>
                <select name="role_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Select role...</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Active Account Status</label>
            <select name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="1">Enabled (Active)</option>
                <option value="0">Disabled (Locked)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-user-plus"></i> Create Admin Account</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
