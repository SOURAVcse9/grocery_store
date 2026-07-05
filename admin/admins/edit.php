<?php
/**
 * ==========================================================================
 * admin/admins/edit.php — Modify Administrative User
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Edit Admin — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('admins.manage');

$pdo = db();
$adminId = (int) input('id', '0', 'get');

if ($adminId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $adminId]);
    $ad = $stmt->fetch();

    if (!$ad) {
        flash('admin_msg', 'Admin details not found.', 'error');
        header('Location: index.php');
        exit;
    }

    $roles = $pdo->query("SELECT id, name FROM admin_roles ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/admins/edit] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
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

        if (empty($fullName) || empty($username) || empty($email) || $roleId <= 0) {
            $error = 'Full Name, Username, Email Address, and Role selection are required fields.';
        } else {
            try {
                // Check uniqueness excluding current ID
                $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE (username = :uname OR email = :email) AND id != :id");
                $check->execute(['uname' => $username, 'email' => $email, 'id' => $adminId]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'The username or email address is already taken by another admin account.';
                } else {
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE admins SET
                                role_id = :role_id, username = :username, email = :email, 
                                password = :password, full_name = :full_name, phone = :phone, 
                                is_active = :active
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            'role_id'   => $roleId,
                            'username'  => $username,
                            'email'     => $email,
                            'password'  => $hash,
                            'full_name' => $fullName,
                            'phone'     => !empty($phone) ? $phone : null,
                            'active'    => $isActive,
                            'id'        => $adminId
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE admins SET
                                role_id = :role_id, username = :username, email = :email, 
                                full_name = :full_name, phone = :phone, is_active = :active
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            'role_id'   => $roleId,
                            'username'  => $username,
                            'email'     => $email,
                            'full_name' => $fullName,
                            'phone'     => !empty($phone) ? $phone : null,
                            'active'    => $isActive,
                            'id'        => $adminId
                        ]);
                    }

                    log_admin_activity('admins.edit', "Updated administrative account details: '{$username}'");
                    flash('admin_msg', "Admin account '@{$username}' updated successfully!", 'success');
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[admin/admins/edit] update failed: ' . $e->getMessage());
                $error = 'Failed to update administrative user details.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit Administrative Account</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Modify assigned permissions and reset backoffice passcodes.</p>
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
                <input type="text" name="full_name" required value="<?= e($ad['full_name']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Username *</label>
                <input type="text" name="username" required value="<?= e($ad['username']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Email Address *</label>
                <input type="email" name="email" required value="<?= e($ad['email']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Phone Number</label>
                <input type="text" name="phone" value="<?= e($ad['phone'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Account Password</label>
                <input type="password" name="password" placeholder="Leave blank if keeping current password..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Assign Security Role *</label>
                <select name="role_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Select role...</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $r['id'] === $ad['role_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Active Account Status</label>
            <select name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="1" <?= (int)$ad['is_active'] === 1 ? 'selected' : '' ?>>Enabled (Active)</option>
                <option value="0" <?= (int)$ad['is_active'] === 0 ? 'selected' : '' ?>>Disabled (Locked)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
