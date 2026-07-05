<?php
/**
 * ==========================================================================
 * admin/reset-password.php — Admin Password Reset Processor Page
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/middleware/auth_middleware.php';

require_admin_guest();

$pdo = db();
$error = null;
$success = null;

$token = trim(input('token', ''));
$email = trim(input('email', ''));

if (empty($token) || empty($email)) {
    $error = 'Invalid recovery request. Missing token or email key.';
} else {
    try {
        $hashedToken = hash('sha256', $token);
        
        // Lookup admin with non-expired token
        $stmt = $pdo->prepare("
            SELECT id, username 
            FROM admins 
            WHERE email = :email 
              AND password_reset_token = :token 
              AND reset_token_expires_at > NOW() 
              AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute(['email' => $email, 'token' => $hashedToken]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $error = 'The password recovery link is invalid or has expired.';
        } else {
            $adminId = (int) $admin['id'];

            // Process Reset Form POST
            if (method_is('post')) {
                if (!verify_csrf()) {
                    $error = 'Invalid security request (CSRF check failed).';
                } else {
                    $password = input('password', '');
                    $confirm = input('confirm_password', '');

                    if (empty($password) || empty($confirm)) {
                        $error = 'Please enter and confirm your new password.';
                    } elseif ($password !== $confirm) {
                        $error = 'Password and confirm password do not match.';
                    } elseif (strlen($password) < 8) {
                        $error = 'Password must be at least 8 characters long.';
                    } else {
                        // Update Password & Clear Token columns
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        
                        $up = $pdo->prepare("
                            UPDATE admins 
                            SET password = :pass, 
                                password_reset_token = NULL, 
                                reset_token_expires_at = NULL, 
                                updated_at = NOW() 
                            WHERE id = :id
                        ");
                        $up->execute(['pass' => $newHash, 'id' => $adminId]);

                        log_admin_login($adminId, $email, true, 'Password reset successfully');
                        log_admin_activity('password_reset_success', "Password reset succeeded for administrative account: '{$admin['username']}'");

                        $success = 'Your password has been successfully reset! You can now log in.';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log('[admin/reset-password] DB query error: ' . $e->getMessage());
        $error = 'Failed to verify recovery token due to database error.';
    }
}

$pageTitle = 'Choose New Password — ' . site_name();
require_once __DIR__ . '/../public/header.php';
?>

<div class="container" style="margin-top: 80px; margin-bottom: 80px; display: flex; justify-content: center;">
    <div class="dashboard-card" style="width: 100%; max-width: 420px; padding: var(--space-6); box-shadow: var(--shadow-md);">
        
        <!-- Header -->
        <div style="text-align: center; margin-bottom: var(--space-5);">
            <div style="font-size: 36px; color: var(--color-primary); margin-bottom: var(--space-2);"><i class="fas fa-lock-open"></i></div>
            <h2 style="font-size: var(--fs-lg); font-weight: 800; color: var(--color-text); margin:0;">Reset Password</h2>
            <p style="font-size: var(--fs-xs); color: var(--color-text-muted); margin: 4px 0 0 0;">Choose a secure new login password.</p>
        </div>

        <!-- Banners -->
        <?php if ($error !== null): ?>
            <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-xs); font-weight:600; line-height:1.4; margin-bottom:var(--space-4);">
                <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== null): ?>
            <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:14px; border-radius:var(--radius-sm); font-size:var(--fs-xs); font-weight:600; line-height:1.5; margin-bottom:var(--space-4);">
                <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
                <div style="margin-top: 12px; text-align: center;">
                    <a href="login.php" class="btn btn-primary" style="padding: 8px 20px; border-radius: var(--radius-pill); border:none; text-decoration:none; display:inline-block; font-weight:700;">Go to Login</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form fields (only display if token is valid and no success banner is active) -->
        <?php if ($error === null && $success === null): ?>
            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                
                <!-- Password input -->
                <div class="form-field-group">
                    <label for="newPassword" style="font-weight:700;">New Password *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <i class="fas fa-lock" style="position:absolute; left:12px; color:var(--color-text-faint); font-size:13px;"></i>
                        <input type="password" id="newPassword" name="password" placeholder="At least 8 characters" required minlength="8" style="width:100%; padding:10px 12px 10px 36px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>
                </div>

                <!-- Confirm Password input -->
                <div class="form-field-group">
                    <label for="confirmPassword" style="font-weight:700;">Confirm New Password *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <i class="fas fa-lock" style="position:absolute; left:12px; color:var(--color-text-faint); font-size:13px;"></i>
                        <input type="password" id="confirmPassword" name="confirm_password" placeholder="Retype new password" required style="width:100%; padding:10px 12px 10px 36px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px;">Reset My Password</button>
            </form>
        <?php endif; ?>

        <?php if ($success === null): ?>
            <div style="text-align:center; margin-top:20px; font-size:12px;">
                <a href="login.php" style="color:var(--color-text-muted); font-weight:700; text-decoration:none;"><i class="fas fa-chevron-left"></i> Return to Login</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
