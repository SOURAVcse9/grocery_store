<?php
/**
 * ==========================================================================
 * public/reset-password.php — Password Reset Processing Page
 * ==========================================================================
 * Authenticates active token hashes, collects new user passwords, validates
 * strength scores, updates credentials, and invalidates tokens.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$token = input('token', '', 'get');
if (empty($token)) {
    $token = input('token', '', 'post');
}

if (empty($token)) {
    flash('forgot', 'Missing password reset token.', 'error');
    redirect(url_for('forgot-password.php'));
}

$hashedToken = hash('sha256', $token);
$pdo = db();
$resetRecord = null;

try {
    // Lookup token
    $stmt = $pdo->prepare('
        SELECT pr.*, u.full_name, u.email 
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = :token AND pr.used = 0 AND pr.expires_at >= NOW()
        LIMIT 1
    ');
    $stmt->execute(['token' => $hashedToken]);
    $resetRecord = $stmt->fetch();

    if (!$resetRecord) {
        flash('forgot', 'Invalid or expired password reset link. Please request a new one.', 'error');
        redirect(url_for('forgot-password.php'));
    }

} catch (PDOException $e) {
    error_log('[reset-password.php] Error: ' . $e->getMessage());
    flash('forgot', 'A database error occurred. Please try again.', 'error');
    redirect(url_for('forgot-password.php'));
}

// Handle password updates
if (method_is('post')) {
    // Verify CSRF
    verify_csrf_or_fail();

    $password = input('password', '');
    $passwordConfirm = input('password_confirm', '');

    $v = new Validator();
    $v->required('password', $password, 'New password is required.')
      ->length('password', $password, 8, 100, 'Password must be at least 8 characters long.')
      ->custom('password', preg_match('/[0-9]/', $password) && preg_match('/[^A-Za-z0-9]/', $password), 'Password must contain at least one number and one special character.')
      ->required('password_confirm', $passwordConfirm, 'Please confirm your new password.')
      ->custom('password_confirm', $password === $passwordConfirm, 'Passwords do not match.');

    if ($v->hasErrors()) {
        flash('reset', $v->first(), 'error');
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update user password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateUser = $pdo->prepare('UPDATE users SET password = :pass WHERE id = :uid');
            $updateUser->execute([
                'pass' => $hashedPassword,
                'uid'  => (int) $resetRecord['user_id']
            ]);

            // 2. Invalidate reset token
            $updateToken = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id');
            $updateToken->execute(['id' => (int) $resetRecord['id']]);

            $pdo->commit();

            // Clear remember cookies to force login on all active devices
            setcookie('remember_me', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);

            flash('auth', 'Password reset successfully! You can now sign in with your new password.', 'success');
            redirect(url_for('login.php'));

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[reset-password.php] Reset failed: ' . $e->getMessage());
            flash('reset', 'Failed to update password. Please try again.', 'error');
        }
    }
}

$pageTitle = 'Choose New Password — ' . site_name();
$pageDescription = 'Choose a strong, secure new password for your customer account.';

$extraStylesheets = ['css/auth.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card">
            
            <div class="auth-header">
                <span class="auth-logo"><i class="fas fa-shopping-basket"></i> Grocery Store</span>
                <h1 class="auth-title">Choose New Password</h1>
                <p class="auth-subtext">Set a secure password for <strong><?= e($resetRecord['email']) ?></strong></p>
            </div>

            <!-- Validation alerts -->
            <?php display_flash_alerts('reset'); ?>

            <!-- Form -->
            <form action="<?= current_url() ?>" method="post" class="auth-form" id="resetPasswordForm">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-field-group">
                    <label for="password">New Password *</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Min. 8 characters with numbers & symbols" required autocomplete="new-password" autofocus>
                        <button type="button" class="password-toggle-btn" aria-label="Toggle Password Visibility">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    
                    <!-- Strength meter -->
                    <div class="strength-meter-container">
                        <div class="strength-bar-bg">
                            <div class="strength-bar-fill" id="strengthBar"></div>
                        </div>
                        <span class="strength-meter-text" id="strengthText">Strength: Too Short</span>
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="password_confirm">Confirm Password *</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password_confirm" name="password_confirm" placeholder="Confirm new password" required autocomplete="new-password">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle Confirm Password Visibility">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-auth-submit">Update Password</button>
            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Show / Hide Password Toggle
    const toggleBtns = document.querySelectorAll('.password-toggle-btn');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            const icon = btn.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('far', 'fas');
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fas', 'far');
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // Password strength calculation
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    passwordInput?.addEventListener('input', () => {
        const val = passwordInput.value;
        const result = checkPasswordStrength(val);

        strengthBar.style.width = result.percent + '%';
        strengthBar.style.backgroundColor = result.color;
        strengthText.textContent = 'Strength: ' + result.label;
    });

    function checkPasswordStrength(pass) {
        if (!pass || pass.length < 4) {
            return { percent: 10, color: '#f05252', label: 'Too Short' };
        }

        let score = 0;
        if (pass.length >= 8) score++;
        if (pass.length >= 12) score++;
        if (/[A-Z]/.test(pass)) score++;
        if (/[a-z]/.test(pass)) score++;
        if (/[0-9]/.test(pass)) score++;
        if (/[^A-Za-z0-9]/.test(pass)) score++;

        switch (score) {
            case 0:
            case 1:
            case 2:
                return { percent: 25, color: '#f05252', label: 'Weak' };
            case 3:
            case 4:
                return { percent: 60, color: '#ff9800', label: 'Medium' };
            case 5:
                return { percent: 80, color: '#eab308', label: 'Strong' };
            case 6:
            default:
                return { percent: 100, color: '#1a9d55', label: 'Very Strong' };
        }
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
