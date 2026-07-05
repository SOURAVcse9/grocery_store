<?php
/**
 * ==========================================================================
 * public/login.php — Secure Login Form View
 * ==========================================================================
 * Displays user sign-in fields, remember me hooks, password eye toggles,
 * and handles login rate-limit flags and redirect checks.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$pageTitle = 'Secure Sign In — ' . site_name();
$pageDescription = 'Sign in to your account to check order status, manage delivery addresses, and speed up checkout.';

$extraStylesheets = ['css/auth.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card">
            
            <div class="auth-header">
                <span class="auth-logo"><i class="fas fa-shopping-basket"></i> Grocery Store</span>
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtext">Sign in to continue shopping and manage orders</p>
            </div>

            <!-- Login Flash Messages -->
            <?php display_flash_alerts('auth'); ?>

            <!-- Login Rate Limit Check -->
            <?php if (isset($_SESSION['login_throttle_time']) && $_SESSION['login_throttle_time'] > time()): 
                $timeLeft = $_SESSION['login_throttle_time'] - time();
            ?>
                <div class="rate-limit-warning">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div>
                        <strong>Too many login attempts.</strong><br>
                        Please wait <strong><?= $timeLeft ?></strong> seconds before trying again.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="<?= url_for('process_login.php') ?>" method="post" class="auth-form">
                <?= csrf_field() ?>

                <div class="form-field-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email', '')) ?>" placeholder="e.g. customer@gmail.com" required autocomplete="email" autofocus>
                </div>

                <div class="form-field-group">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label for="password">Password</label>
                        <a href="<?= url_for('forgot-password.php') ?>" class="forgot-password-link">Forgot?</a>
                    </div>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" aria-label="Toggle Password Visibility">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Reset row -->
                <div class="auth-remember-row">
                    <label class="auth-remember-checkbox">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember Me</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-auth-submit">Sign In</button>
            </form>

            <div class="auth-footer">
                Don't have an account? <a href="<?= url_for('register.php') ?>">Register here</a>
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
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
