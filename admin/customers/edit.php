<?php
/**
 * ==========================================================================
 * admin/customers/edit.php — Modify Customer Profile
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_permission('customers.edit');

$pdo = db();
$userId = (int) input('id', '0', 'get');

if ($userId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role_id != 1 LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $u = $stmt->fetch();

    if (!$u) {
        flash('cust_msg', 'Customer details not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/customers/edit] load failed: ' . $e->getMessage());
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
        $gender = trim(input('gender', ''));
        $dob = trim(input('dob', ''));
        
        $isVerified = (int) input('is_verified', '0');
        $emailVerified = (int) input('email_verified', '0');
        $phoneVerified = (int) input('phone_verified', '0');
        $isActive = (int) input('is_active', '1');

        if (empty($fullName) || empty($email)) {
            $error = 'Full Name and Email Address are required fields.';
        } else {
            try {
                // Check uniqueness excluding current ID
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (email = :email OR (username = :uname AND username IS NOT NULL)) AND id != :id");
                $check->execute(['email' => $email, 'uname' => $username ?: null, 'id' => $userId]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'The email address or username is already taken by another account.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET
                            full_name = :name, username = :uname, email = :email, 
                            phone = :phone, gender = :gender, dob = :dob,
                            is_verified = :verified, email_verified = :em_ver, 
                            phone_verified = :ph_ver, is_active = :active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'name'     => $fullName,
                        'uname'    => !empty($username) ? $username : null,
                        'email'    => $email,
                        'phone'    => !empty($phone) ? $phone : null,
                        'gender'   => !empty($gender) ? $gender : null,
                        'dob'      => !empty($dob) ? $dob : null,
                        'verified' => $isVerified,
                        'em_ver'   => $emailVerified,
                        'ph_ver'   => $phoneVerified,
                        'active'   => $isActive,
                        'id'       => $userId
                    ]);

                    log_admin_activity('customers.edit', "Updated profile data for Customer ID: {$userId} ('{$fullName}')");
                    flash('cust_msg', 'Customer details updated successfully!', 'success');
                    header("Location: view.php?id={$userId}");
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[admin/customers/edit] Update failed: ' . $e->getMessage());
                $error = 'Failed to update customer record due to database error.';
            }
        }
    }
}
$pageTitle = 'Edit Customer — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit Customer Details</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Update account credentials, profile details, and verification status.</p>
    </div>
    <a href="view.php?id=<?= $userId ?>" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> View Profile</a>
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
                <input type="text" name="full_name" required value="<?= e($u['full_name']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Username</label>
                <input type="text" name="username" value="<?= e($u['username'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Email Address *</label>
                <input type="email" name="email" required value="<?= e($u['email']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Phone Number</label>
                <input type="text" name="phone" value="<?= e($u['phone'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Gender</label>
                <select name="gender" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Unspecified</option>
                    <option value="Male" <?= $u['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $u['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $u['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Date of Birth</label>
                <input type="date" name="dob" value="<?= $u['dob'] ? date('Y-m-d', strtotime($u['dob'])) : '' ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Identity Verified Status</label>
                <select name="is_verified" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1" <?= (int)$u['is_verified'] === 1 ? 'selected' : '' ?>>Verified</option>
                    <option value="0" <?= (int)$u['is_verified'] === 0 ? 'selected' : '' ?>>Unverified</option>
                </select>
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Email Verified</label>
                <select name="email_verified" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1" <?= (int)($u['email_verified'] ?? 0) === 1 ? 'selected' : '' ?>>Verified</option>
                    <option value="0" <?= (int)($u['email_verified'] ?? 0) === 0 ? 'selected' : '' ?>>Unverified</option>
                </select>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label style="font-weight:700;">Phone Verified</label>
                <select name="phone_verified" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1" <?= (int)($u['phone_verified'] ?? 0) === 1 ? 'selected' : '' ?>>Verified</option>
                    <option value="0" <?= (int)($u['phone_verified'] ?? 0) === 0 ? 'selected' : '' ?>>Unverified</option>
                </select>
            </div>
            <div class="form-field-group">
                <label style="font-weight:700;">Account Status</label>
                <select name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1" <?= (int)$u['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int)$u['is_active'] === 0 ? 'selected' : '' ?>>Deactivated (Suspended)</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
