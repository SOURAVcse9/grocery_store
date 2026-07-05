<?php
/**
 * ==========================================================================
 * admin/forgot-password.php — Admin Password Reset Request Page
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/middleware/auth_middleware.php';

require_admin_guest();

$pdo = db();
$error = null;
$success = null;
$resetLink = null;

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $email = trim(input('email', ''));

        if (empty($email)) {
            $error = 'Please enter your registered email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Verify email belongs to an administrator
                $stmt = $pdo->prepare("SELECT id, username, full_name FROM admins WHERE email = :email AND is_active = 1 LIMIT 1");
                $stmt->execute(['email' => $email]);
                $admin = $stmt->fetch();

                if ($admin) {
                    $adminId = (int) $admin['id'];
                    
                    // Generate secure cryptographic token
                    $rawToken = bin2hex(random_bytes(32));
                    $hashedToken = hash('sha256', $rawToken);
                    
                    // Expire token in 1 hour
                    $up = $pdo->prepare("
                        UPDATE admins 
                        SET password_reset_token = :token, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                        WHERE id = :id
                    ");
                    $up->execute(['token' => $hashedToken, 'id' => $adminId]);

                    // Generate reset URL
                    $resetLink = BASE_URL . '/../admin/reset-password.php?token=' . $rawToken . '&email=' . urlencode($email);
                    
                    log_admin_activity('password_reset_request', "Requested password reset for username: '{$admin['username']}'");
                    
                    $success = 'Password reset instructions have been created successfully!';
                } else {
                    // Avoid user enumeration - show generic success or simple confirmation check
                    // But in admin context, showing a safe error is fine as it's restricted:
                    $error = 'No active administrator account was found with that email address.';
                }
            } catch (PDOException $e) {
                error_log('[admin/forgot-password] Reset transaction failed: ' . $e->getMessage());
                $error = 'Failed to generate reset link due to database error.';
            }
        }
    }
}

$pageTitle = 'Reset Password — ' . site_name();
require_once __DIR__ . '/../public/header.php';
?>

<div class="container" style="margin-top: 80px; margin-bottom: 80px; display: flex; justify-content: center;">
    <div class="dashboard-card" style="width: 100%; max-width: 420px; padding: var(--space-6); box-shadow: var(--shadow-md);">
        
        <!-- Header -->
        <div style="text-align: center; margin-bottom: var(--space-5);">
            <div style="font-size: 36px; color: var(--color-primary); margin-bottom: var(--space-2);"><i class="fas fa-key"></i></div>
            <h2 style="font-size: var(--fs-lg); font-weight: 800; color: var(--color-text); margin:0;">Forgot Password</h2>
            <p style="font-size: var(--fs-xs); color: var(--color-text-muted); margin: 4px 0 0 0;">Enter your email to receive recovery link.</p>
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
                
                <?php if ($resetLink !== null): ?>
                    <div style="margin-top: 12px; border-top: 1px dashed #b2f2bb; padding-top: 10px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #5c7cfa; display: block; font-weight:700; margin-bottom: 4px;">Mail Sandbox Simulation Link:</span>
                        <a href="<?= e($resetLink) ?>" style="font-weight: 700; color: var(--color-primary); text-decoration: underline; word-break: break-all;"><?= e($resetLink) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            
            <div class="form-field-group">
                <label for="recoveryEmail" style="font-weight:700;">Email Address *</label>
                <div style="position:relative; display:flex; align-items:center;">
                    <i class="fas fa-envelope" style="position:absolute; left:12px; color:var(--color-text-faint); font-size:13px;"></i>
                    <input type="email" id="recoveryEmail" name="email" placeholder="Enter administrative email" required style="width:100%; padding:10px 12px 10px 36px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px;">Send Recovery Link</button>
            
            <div style="text-align:center; margin-top:20px; font-size:12px;">
                <a href="login.php" style="color:var(--color-text-muted); font-weight:700; text-decoration:none;"><i class="fas fa-chevron-left"></i> Back to Login</a>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../public/footer.php'; ?>
