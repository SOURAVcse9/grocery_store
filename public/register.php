<?php
/**
 * ==========================================================================
 * public/register.php — Secure User Registration Form View
 * ==========================================================================
 * Collects new user details, checks password strength parameters, and
 * redirects users back to checkout after successful sign-up.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$pageTitle = 'Create Account — ' . site_name();
$pageDescription = 'Create an account to track orders, save shipping addresses, and apply discount promos.';

$extraStylesheets = ['css/auth.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card">
            
            <div class="auth-header">
                <span class="auth-logo"><i class="fas fa-shopping-basket"></i> Grocery Store</span>
                <h1 class="auth-title">Create Account</h1>
                <p class="auth-subtext">Join us today to get fresh groceries delivered to your door</p>
            </div>

            <!-- Display Validation Errors -->
            <?php display_flash_alerts('auth'); ?>

            <!-- Registration Form -->
            <form action="<?= url_for('process_register.php') ?>" method="post" class="auth-form" id="registerForm">
                <?= csrf_field() ?>

                <div class="form-field-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" value="<?= e(old('full_name', '')) ?>" placeholder="e.g. Karim Rahman" required autofocus autocomplete="name">
                </div>

                <div class="form-field-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email', '')) ?>" placeholder="e.g. customer@gmail.com" required autocomplete="email">
                </div>

                <div class="form-field-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" value="<?= e(old('phone', '')) ?>" placeholder="e.g. 01700000000" required autocomplete="tel">
                </div>

                <div class="form-field-group">
                    <label for="password">Password *</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Min. 8 characters with numbers & symbols" required autocomplete="new-password">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle Password Visibility">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    
                    <!-- Password Strength Meter -->
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
                        <input type="password" id="password_confirm" name="password_confirm" placeholder="Re-enter password" required autocomplete="new-password">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle Confirm Password Visibility">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-auth-submit">Register Now</button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="<?= url_for('login.php') ?>">Sign in here</a>
            </div>

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

    // Password Strength Meter Script
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
