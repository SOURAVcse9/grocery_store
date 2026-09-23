<?php
/**
 * ==========================================================================
 * admin/admins/reset-password.php — Super Admin Password Reset & OTP Generator
 * ==========================================================================
 * Allows Super Admin to reset another administrator's password.
 * Generates a new cryptographic One-Time Password, invalidates old sessions,
 * and displays the temporary OTP once.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_super_admin();

$pdo = db();
$adminId = (int) input('id', '0', 'get');
$targetAdmin = null;
$newOtp = null;
$error = null;

if ($adminId <= 0) {
    flash('admin_msg', 'Invalid administrator ID provided.', 'error');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, r.name AS role_name 
        FROM admins a
        JOIN admin_roles r ON r.id = a.role_id
        WHERE a.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $adminId]);
    $targetAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetAdmin) {
        flash('admin_msg', 'Administrator record not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/reset-password] fetch fail: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        try {
            // Generate new temporary OTP
            $newOtp = generate_temporary_admin_otp();
            $hash = password_hash($newOtp, PASSWORD_DEFAULT);

            // Invalidate old password and remember tokens
            $upStmt = $pdo->prepare("
                UPDATE admins SET 
                    password = :hash,
                    must_change_password = 1,
                    password_created_at = NOW(),
                    remember_token = NULL,
                    password_reset_token = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upStmt->execute([
                'hash' => $hash,
                'id'   => $adminId
            ]);

            log_admin_activity('password_reset', "Super Admin reset password for admin '@{$targetAdmin['username']}' (#{$adminId})");

        } catch (PDOException $e) {
            error_log('[admin/reset-password] reset fail: ' . $e->getMessage());
            $error = 'Failed to reset password due to database error.';
        }
    }
}

$pageTitle = 'Reset Admin Password — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Reset Administrator Password</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Generate a new temporary One-Time Password for staff member.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Admins list</a>
</div>

<?php if ($newOtp !== null): ?>
    <!-- Display New Generated OTP Once -->
    <div style="background: var(--color-surface); border: 2px solid #0ca678; border-radius: var(--radius-lg); padding: 28px; max-width: 650px; margin-bottom: var(--space-5); box-shadow: var(--shadow-md);">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #e6fcf5; color: #0ca678; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="fas fa-key"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 18px; color: var(--color-text); font-weight: 800;">Password Reset Successfully!</h3>
                <span style="font-size: 12px; color: var(--color-text-muted);">New credentials for <strong><?= e($targetAdmin['full_name']) ?></strong> (@<?= e($targetAdmin['username']) ?>)</span>
            </div>
        </div>

        <div style="background: var(--color-bg); border: 1px dashed var(--color-border); border-radius: var(--radius-md); padding: 18px; text-align: center; margin-bottom: 16px;">
            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); display: block; margin-bottom: 6px;">New One-Time Temporary Password</span>
            <div style="font-size: 24px; font-family: monospace; font-weight: 800; letter-spacing: 2px; color: var(--color-primary); user-select: all;" id="newOtpDisplay">
                <?= e($newOtp) ?>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap;">
            <button type="button" onclick="copyNewOtpToClipboard()" id="copyNewOtpBtn" class="btn btn-primary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 8px 18px; font-size: 13px;">
                <i class="fas fa-copy"></i> Copy Password
            </button>
            <a href="index.php" class="btn btn-secondary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 8px 18px; font-size: 13px;">
                Done & Return to Directory
            </a>
        </div>

        <div style="background: #fff9db; border: 1px solid #ffe066; border-radius: var(--radius-sm); padding: 10px 14px; font-size: 12px; color: #7f5f00; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-triangle-exclamation"></i>
            <span><strong>Notice:</strong> This password is shown <u>only once</u>. When this administrator logs in next, they will be forced to change this temporary password to a permanent one.</span>
        </div>
    </div>

    <script>
    function copyNewOtpToClipboard() {
        const text = document.getElementById('newOtpDisplay').innerText.trim();
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('copyNewOtpBtn');
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy"></i> Copy Password';
            }, 3000);
        });
    }
    </script>
<?php else: ?>

    <!-- Error Alert -->
    <?php if ($error !== null): ?>
        <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
            <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-card" style="padding:var(--space-6); max-width: 600px;">
        <div style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 8px 0; font-size: 16px; color: var(--color-text);">Confirm Password Reset</h3>
            <p style="margin: 0; font-size: 13px; color: var(--color-text-muted); line-height: 1.5;">
                Are you sure you want to generate a new One-Time Password for <strong><?= e($targetAdmin['full_name']) ?></strong> (@<?= e($targetAdmin['username']) ?> &bull; <?= e($targetAdmin['email']) ?>)?
            </p>
        </div>

        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 14px; font-size: 12px; color: var(--color-text-muted); margin-bottom: 24px;">
            <ul style="margin: 0; padding-left: 18px; line-height: 1.6;">
                <li>The administrator's current password will be immediately invalidated.</li>
                <li>All active "Remember Me" sessions for this account will be revoked.</li>
                <li>The user will be required to set a new password upon their next login.</li>
            </ul>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-primary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 10px 24px;">
                    <i class="fas fa-key"></i> Generate Temporary Password
                </button>
                <a href="index.php" class="btn btn-secondary" style="border-radius: var(--radius-pill); font-weight: 700; padding: 10px 20px;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>
