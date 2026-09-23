<?php
/**
 * ==========================================================================
 * public/reset-password.php — Password Reset Completion Page
 * ==========================================================================
 * Validates cryptographically hashed reset tokens, enforces strong password
 * policies, updates customer password, and invalidates previous sessions.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$pageTitle = 'Reset Password — ' . site_name();
$pageDescription = 'Set a new secure password for your GroCo customer account.';
$extraStylesheets = ['css/auth.css'];

$rawToken = trim(input('token', '', 'get') ?: input('token', '', 'post'));
$tokenState = 'invalid'; // 'valid' | 'invalid' | 'used' | 'expired'
$resetRecord = null;
$pdo = db();

if (!empty($rawToken)) {
    $tokenHash = hash('sha256', $rawToken);

    $tokenStmt = $pdo->prepare('
        SELECT pr.*, u.id as customer_id, u.email, u.full_name, u.is_active
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = :token
        LIMIT 1
    ');
    $tokenStmt->execute(['token' => $tokenHash]);
    $resetRecord = $tokenStmt->fetch();

    if (!$resetRecord || (int)$resetRecord['is_active'] !== 1) {
        $tokenState = 'invalid';
    } elseif ((int)$resetRecord['used'] === 1) {
        $tokenState = 'used';
    } elseif (strtotime((string)$resetRecord['expires_at']) <= time()) {
        $tokenState = 'expired';
    } else {
        $tokenState = 'valid';
    }
}

// Process POST Password Reset
if (method_is('post') && $tokenState === 'valid') {
    if (!verify_csrf()) {
        flash('auth', 'Security token expired. Please try submitting again.', 'error');
    } else {
        $newPassword = input('new_password', '');
        $confirmPassword = input('confirm_password', '');

        $v = new Validator();
        $v->required('new_password', $newPassword, 'New password is required.')
          ->length('new_password', $newPassword, 8, 100, 'Password must be at least 8 characters long.')
          ->required('confirm_password', $confirmPassword, 'Please confirm your new password.')
          ->custom('confirm_password', $newPassword === $confirmPassword, 'Passwords do not match.');

        // Verify password complexity (uppercase, lowercase, number)
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $v->addError('new_password', 'Password must include uppercase, lowercase, and a number.');
        }

        if ($v->hasErrors()) {
            flash('auth', $v->first() ?? 'Validation failed.', 'error');
        } else {
            try {
                $pdo->beginTransaction();

                $customerId = (int) $resetRecord['customer_id'];
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update user password and increment session_version to invalidate old sessions
                $updateUser = $pdo->prepare('
                    UPDATE users 
                    SET password = :pass, 
                        failed_logins = 0,
                        last_password_change = NOW(),
                        session_version = session_version + 1,
                        remember_token = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ');
                $updateUser->execute([
                    'pass' => $hashedPassword,
                    'id'   => $customerId
                ]);

                // Mark reset token as used and invalidate all active tokens for this user
                $markUsed = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid');
                $markUsed->execute(['uid' => $customerId]);

                $pdo->commit();

                flash('auth', 'Password updated successfully. Your password has been changed. You can now sign in with your email and new password.', 'success');
                redirect(url_for('login.php'));

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('[reset-password.php] Error: ' . $e->getMessage());
                flash('auth', 'An error occurred while resetting your password. Please try again.', 'error');
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="auth-page-container">
    <div class="auth-wrapper">
        <div class="auth-card" role="main">
            
            <!-- Brand Section -->
            <div class="auth-brand-section">
                <a href="<?= url_for('index.php') ?>" class="auth-brand-logo" aria-label="GroCo Grocery Store">
                    <span class="brand-icon"><i class="fas fa-shopping-basket"></i></span>
                    <span class="brand-name"><?= e(site_name()) ?></span>
                </a>
            </div>

            <!-- Header -->
            <div class="auth-header">
                <h1 class="auth-title">Reset Your Password</h1>
                <p class="auth-subtext">
                    <?= ($tokenState === 'valid') ? 'Enter and confirm your new secure password.' : 'Password Reset Verification' ?>
                </p>
            </div>

            <!-- Flash Error / Status Alerts -->
            <?php display_flash_alerts('auth'); ?>

            <?php if ($tokenState === 'valid'): ?>
                <!-- Password Reset Form -->
                <form action="<?= current_url() ?>" method="post" class="auth-form" id="resetPasswordForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($rawToken) ?>">

                    <div class="form-field-group">
                        <label for="new_password">New Password *</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="auth-input" 
                                   placeholder="Minimum 8 characters" 
                                   required 
                                   autocomplete="new-password" 
                                   autofocus>
                            <button type="button" 
                                    class="password-toggle-btn" 
                                    id="toggleNewPassBtn" 
                                    aria-label="Toggle password visibility" 
                                    tabindex="-1">
                                <i class="far fa-eye" id="toggleNewPassIcon"></i>
                            </button>
                        </div>

                        <!-- Live Strength Meter -->
                        <div class="strength-meter-container" id="resetStrengthContainer" style="display: none;">
                            <div class="strength-bar-bg">
                                <div class="strength-bar-fill" id="resetStrengthFill"></div>
                            </div>
                            <span class="strength-meter-text" id="resetStrengthText">Strength: Too Short</span>
                        </div>
                    </div>

                    <div class="form-field-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="auth-input" 
                                   placeholder="Re-enter your new password" 
                                   required 
                                   autocomplete="new-password">
                            <button type="button" 
                                    class="password-toggle-btn" 
                                    id="toggleConfirmPassBtn" 
                                    aria-label="Toggle confirm password visibility" 
                                    tabindex="-1">
                                <i class="far fa-eye" id="toggleConfirmPassIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit" id="btnSubmitReset">
                        <span class="btn-text">Update Password</span>
                        <span class="btn-spinner" style="display: none;" aria-hidden="true">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </span>
                    </button>
                </form>

            <?php elseif ($tokenState === 'used'): ?>
                <!-- Used Token Message -->
                <div style="text-align: center; padding: 12px 0;">
                    <p style="font-size: 14px; color: var(--color-text-muted); line-height: 1.6; margin-bottom: 24px;">
                        This password reset link has already been used.
                    </p>
                    <a href="<?= url_for('forgot-password.php') ?>" class="btn-auth-submit" style="text-decoration: none;">
                        Request a new link &rarr;
                    </a>
                </div>

            <?php elseif ($tokenState === 'expired'): ?>
                <!-- Expired Token Message -->
                <div style="text-align: center; padding: 12px 0;">
                    <p style="font-size: 14px; color: var(--color-text-muted); line-height: 1.6; margin-bottom: 24px;">
                        This password reset link has expired.
                    </p>
                    <a href="<?= url_for('forgot-password.php') ?>" class="btn-auth-submit" style="text-decoration: none;">
                        Request a new link &rarr;
                    </a>
                </div>

            <?php else: ?>
                <!-- Invalid Token Message -->
                <div style="text-align: center; padding: 12px 0;">
                    <p style="font-size: 14px; color: var(--color-text-muted); line-height: 1.6; margin-bottom: 24px;">
                        This password reset link is invalid or has expired.
                    </p>
                    <a href="<?= url_for('forgot-password.php') ?>" class="btn-auth-submit" style="text-decoration: none;">
                        Request a new link &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <!-- Footer Navigation -->
            <div class="auth-footer-nav">
                Back to <a href="<?= url_for('login.php') ?>">Sign In</a>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Password Toggles
    function setupToggle(btnId, inputId, iconId) {
        var btn = document.getElementById(btnId);
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (btn && input && icon) {
            btn.addEventListener('click', function () {
                var isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                icon.className = isPass ? 'far fa-eye-slash' : 'far fa-eye';
            });
        }
    }
    setupToggle('toggleNewPassBtn', 'new_password', 'toggleNewPassIcon');
    setupToggle('toggleConfirmPassBtn', 'confirm_password', 'toggleConfirmPassIcon');

    // Strength Meter
    var passInput = document.getElementById('new_password');
    var meterWrap = document.getElementById('resetStrengthContainer');
    var fillBar = document.getElementById('resetStrengthFill');
    var textLabel = document.getElementById('resetStrengthText');

    if (passInput && meterWrap && fillBar && textLabel) {
        passInput.addEventListener('input', function () {
            var val = this.value;
            if (val.length === 0) {
                meterWrap.style.display = 'none';
                return;
            }
            meterWrap.style.display = 'flex';

            var score = 0;
            if (val.length >= 8) score++;
            if (val.length >= 12) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            fillBar.className = 'strength-bar-fill';
            if (score <= 2) {
                fillBar.classList.add('weak');
                textLabel.textContent = 'Strength: Weak';
                textLabel.style.color = '#ef4444';
            } else if (score <= 3) {
                fillBar.classList.add('fair');
                textLabel.textContent = 'Strength: Good';
                textLabel.style.color = '#f59e0b';
            } else {
                fillBar.classList.add('strong');
                textLabel.textContent = 'Strength: Strong';
                textLabel.style.color = '#10b981';
            }
        });
    }

    // Submit Loading
    var form = document.getElementById('resetPasswordForm');
    var btn = document.getElementById('btnSubmitReset');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.classList.add('is-loading');
            var btnText = btn.querySelector('.btn-text');
            var btnSpinner = btn.querySelector('.btn-spinner');
            if (btnText) btnText.textContent = 'Updating password...';
            if (btnSpinner) btnSpinner.style.display = 'inline-flex';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
