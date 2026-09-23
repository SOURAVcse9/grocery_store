<?php
/**
 * ==========================================================================
 * admin/admins/create.php — Add New Administrator (Super Admin OTP Generator)
 * ==========================================================================
 * Allows Super Admin to provision a new administrative staff account.
 * Automatically generates a cryptographically secure One-Time Password (OTP)
 * and displays it once for safe onboarding.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_permission('admins.manage');

$pdo = db();
$error = null;
$createdAdmin = null;

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
        $email = strtolower(trim(input('email', '')));
        $phone = trim(input('phone', ''));
        $roleId = (int) input('role_id', '0');
        $isActive = (int) input('is_active', '1');

        $__admin = current_admin();
        if (empty($fullName) || empty($username) || empty($email) || $roleId <= 0) {
            $error = 'Full Name, Username, Email Address, and Role selection are required fields.';
        } elseif ($roleId === 1 && $__admin['role_name'] !== 'Super Admin') {
            $error = 'Unauthorized! Only Super Admin can assign the Super Admin role.';
        } else {
            try {
                // Check uniqueness of username and email
                $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = :uname OR email = :email");
                $check->execute(['uname' => $username, 'email' => $email]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'The username or email address is already taken by another admin account.';
                } else {
                    // Generate cryptographically secure One-Time Password (OTP)
                    $temporaryOtp = generate_temporary_admin_otp();
                    $hash = password_hash($temporaryOtp, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO admins (
                            role_id, username, email, password, full_name, phone, 
                            is_active, must_change_password, password_created_at, created_at, updated_at
                        ) VALUES (
                            :role_id, :username, :email, :password, :full_name, :phone, 
                            :active, 1, NOW(), NOW(), NOW()
                        )
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

                    log_admin_activity('admins.create', "Created admin account '@{$username}' ({$email}) with role ID {$roleId}");

                    $createdAdmin = [
                        'username' => $username,
                        'email'    => $email,
                        'full_name'=> $fullName,
                        'otp'      => $temporaryOtp,
                    ];
                }
            } catch (PDOException $e) {
                error_log('[admin/admins/create] failed: ' . $e->getMessage());
                $error = 'Failed to create admin record due to server database error.';
            }
        }
    }
}

$pageTitle = 'Add Administrator — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Create Administrative Account</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add backoffice personnel with automatic one-time password onboarding.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Admins list</a>
</div>

<?php if ($createdAdmin !== null): ?>
    <!-- One-Time Password Display Modal / Alert Card -->
    <div style="background: var(--color-surface); border: 2px solid #0ca678; border-radius: var(--radius-lg); padding: 28px; max-width: 700px; margin-bottom: var(--space-5); box-shadow: var(--shadow-md);">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #e6fcf5; color: #0ca678; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="fas fa-check"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 18px; color: var(--color-text); font-weight: 800;">Administrator Account Created Successfully!</h3>
                <span style="font-size: 12px; color: var(--color-text-muted);">Account for <strong><?= e($createdAdmin['full_name']) ?></strong> (<?= e($createdAdmin['email']) ?>) is ready.</span>
            </div>
        </div>

        <div style="background: var(--color-bg); border: 1px dashed var(--color-border); border-radius: var(--radius-md); padding: 18px; text-align: center; margin-bottom: 16px;">
            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); display: block; margin-bottom: 6px;">One-Time Temporary Password</span>
            <div style="font-size: 24px; font-family: monospace; font-weight: 800; letter-spacing: 2px; color: var(--color-primary); user-select: all;" id="otpCodeDisplay">
                <?= e($createdAdmin['otp']) ?>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap;">
            <button type="button" onclick="copyOtpToClipboard()" id="copyOtpBtn" class="btn btn-primary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 8px 18px; font-size: 13px;">
                <i class="fas fa-copy"></i> Copy Password
            </button>
            <a href="index.php" class="btn btn-secondary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 8px 18px; font-size: 13px;">
                Done & Return to Directory
            </a>
        </div>

        <div style="background: #fff9db; border: 1px solid #ffe066; border-radius: var(--radius-sm); padding: 10px 14px; font-size: 12px; color: #7f5f00; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-triangle-exclamation"></i>
            <span><strong>Important:</strong> This temporary password is shown <u>only once</u> and cannot be retrieved later. The new administrator must enter this temporary password and will be required to set a permanent password upon first login.</span>
        </div>
    </div>

    <script>
    function copyOtpToClipboard() {
        const text = document.getElementById('otpCodeDisplay').innerText.trim();
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('copyOtpBtn');
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy"></i> Copy Password';
            }, 3000);
        });
    }
    </script>
<?php else: ?>

    <!-- Errors display -->
    <?php if ($error !== null): ?>
        <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
            <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-card" style="padding:var(--space-6); max-width: 700px;">
        <form method="post" class="auth-form">
            <?= csrf_field() ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="grid-2">
                <div class="form-field-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" value="<?= e(old('full_name', '')) ?>" required placeholder="e.g. Abdullah Al Mamun" style="padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-bg); color: var(--color-text);">
                </div>

                <div class="form-field-group">
                    <label for="username">Username *</label>
                    <input type="text" name="username" id="username" value="<?= e(old('username', '')) ?>" required placeholder="e.g. abdullah_admin" style="padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-bg); color: var(--color-text);">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top: 14px;" class="grid-2">
                <div class="form-field-group">
                    <label for="email">Email Address *</label>
                    <input type="email" name="email" id="email" value="<?= e(old('email', '')) ?>" required placeholder="staff@groco.com.bd" style="padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-bg); color: var(--color-text);">
                </div>

                <div class="form-field-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" value="<?= e(old('phone', '')) ?>" placeholder="e.g. 01700000000" style="padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-bg); color: var(--color-text);">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top: 14px;" class="grid-2">
                <div class="form-field-group">
                    <label for="role_id">Assigned Role *</label>
                    <select name="role_id" id="role_id" required style="padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-bg); color: var(--color-text);">
                        <option value="">-- Select Role --</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= (int)old('role_id', '0') === (int)$r['id'] ? 'selected' : '' ?>>
                                <?= e($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field-group">
                    <label for="is_active">Account Status</label>
                    <select name="is_active" id="is_active" style="padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-bg); color: var(--color-text);">
                        <option value="1" <?= old('is_active', '1') === '1' ? 'selected' : '' ?>>Active / Enabled</option>
                        <option value="0" <?= old('is_active', '1') === '0' ? 'selected' : '' ?>>Suspended / Disabled</option>
                    </select>
                </div>
            </div>

            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 14px 18px; margin-top: 20px;">
                <div style="display: flex; gap: 10px; align-items: center; color: var(--color-primary); font-size: 13px; font-weight: 700;">
                    <i class="fas fa-shield-keyhole"></i>
                    <span>Automated Cryptographic Password Generation</span>
                </div>
                <p style="margin: 6px 0 0 0; font-size: 12px; color: var(--color-text-muted); line-height: 1.5;">
                    The system will automatically generate a secure One-Time Password (OTP) for this administrator. The temporary password will be displayed once on screen upon saving.
                </p>
            </div>

            <div style="margin-top: 24px; text-align: right;">
                <button type="submit" class="btn btn-primary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 12px 28px;">
                    <i class="fas fa-plus-circle"></i> Create Administrator
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>
