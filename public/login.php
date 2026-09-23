<?php
/**
 * ==========================================================================
 * public/login.php — Production Customer Sign-In (Email + Google OAuth)
 * ==========================================================================
 * Dual customer authentication supporting both Google OAuth 2.0 (Primary)
 * and Email + Password credentials with seamless account linking.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/includes/google_auth.php';
require_once __DIR__ . '/includes/rate_limit.php';

// Redirect if already logged in as customer
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$pageTitle = 'Sign In — ' . site_name();
$pageDescription = 'Sign in securely to your GroCo customer account with Google or email to manage orders and checkout faster.';
$extraStylesheets = ['css/auth.css'];

// Read return URL (sanitized internal paths only)
$returnUrl = input('return_url', '', 'get') ?: input('redirect', '', 'get');
if (empty($returnUrl) && !empty($_SESSION['intended_url'])) {
    $returnUrl = $_SESSION['intended_url'];
}

$googleLoginUrl = url_for('auth/google-login.php' . (!empty($returnUrl) ? '?return_url=' . urlencode($returnUrl) : ''));

// Process Email + Password POST Login
if (method_is('post')) {
    if (!verify_csrf()) {
        flash('auth', 'Security token expired. Please try submitting again.', 'error');
    } else {
        $email = strtolower(trim(input('email', '')));
        $password = input('password', '');

        // Rate limiting: 6 attempts per minute per IP
        if (!check_rate_limit('customer_login', 6, 60, false)) {
            flash('auth', 'Too many failed login attempts. Please wait 1 minute before trying again.', 'error');
        } elseif (empty($email) || empty($password)) {
            flash('auth', 'Please enter both your email address and password.', 'error');
        } else {
            $user = attempt_login($email, $password);

            if ($user !== false) {
                // Check if banned or suspended
                if ((int) ($user['is_banned'] ?? 0) === 1 || (int) $user['is_active'] === 0) {
                    flash('auth', 'Your account is currently unavailable. Please contact support.', 'error');
                } else {
                    login_user($user);

                    flash('auth', 'Welcome back, ' . htmlspecialchars($user['full_name'] ?? 'Customer') . '!', 'success');

                    // Redirect to return URL or account dashboard
                    $destination = $returnUrl;
                    unset($_SESSION['intended_url']);

                    if (!empty($destination) && is_string($destination) && str_starts_with($destination, '/') && !str_starts_with($destination, '//')) {
                        redirect($destination);
                    } else {
                        redirect(url_for('account.php'));
                    }
                }
            } else {
                // Check if account exists as a Google-only account without password
                $checkStmt = db()->prepare('SELECT id, google_id, password FROM users WHERE email = :email LIMIT 1');
                $checkStmt->execute(['email' => $email]);
                $existing = $checkStmt->fetch();

                if ($existing && empty($existing['password']) && !empty($existing['google_id'])) {
                    flash('auth', 'This account uses Google Sign-In. Please click "Continue with Google" above.', 'info');
                } else {
                    flash('auth', 'Invalid email or password. Please check your credentials.', 'error');
                }
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
                <h1 class="auth-title">Welcome Back!</h1>
                <p class="auth-subtext">
                    Sign in to continue shopping and manage your orders.
                </p>
            </div>

            <!-- Flash Error / Status Alerts -->
            <?php display_flash_alerts('auth'); ?>

            <!-- Google OAuth Primary Action -->
            <div class="auth-action-box" style="margin-bottom: 20px;">
                <a href="<?= e($googleLoginUrl) ?>" 
                   class="btn-google-auth" 
                   id="btnGoogleAuth" 
                   role="button" 
                   aria-label="Continue with Google">
                    <span class="google-icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                    </span>
                    <span class="btn-text">Continue with Google</span>
                    <span class="btn-spinner" style="display: none;" aria-hidden="true">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </span>
                </a>
            </div>

            <!-- OR Divider -->
            <div class="auth-divider">
                <span>OR SIGN IN WITH EMAIL</span>
            </div>

            <!-- Email + Password Secondary Form -->
            <form action="<?= current_url() ?>" method="post" class="auth-form" id="customerLoginForm">
                <?= csrf_field() ?>
                <?php if (!empty($returnUrl)): ?>
                    <input type="hidden" name="return_url" value="<?= e($returnUrl) ?>">
                <?php endif; ?>

                <div class="form-field-group">
                    <label for="email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="auth-input" 
                               placeholder="you@example.com" 
                               value="<?= e(old('email', '')) ?>" 
                               required 
                               autocomplete="email">
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="auth-input" 
                               placeholder="Enter your password" 
                               required 
                               autocomplete="current-password">
                        <button type="button" 
                                class="password-toggle-btn" 
                                id="togglePasswordBtn" 
                                aria-label="Toggle password visibility" 
                                tabindex="-1">
                            <i class="far fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-sub-row">
                    <a href="<?= url_for('forgot-password.php') ?>" class="forgot-password-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-auth-submit" id="btnEmailLogin">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-spinner" style="display: none;" aria-hidden="true">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </span>
                </button>
            </form>

            <!-- Footer Navigation -->
            <div class="auth-footer-nav">
                Don't have an account? <a href="<?= url_for('register.php') ?>">Create Account</a>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Password Toggle
    var toggleBtn = document.getElementById('togglePasswordBtn');
    var passwordInput = document.getElementById('password');
    var toggleIcon = document.getElementById('togglePasswordIcon');

    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function () {
            var isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            toggleIcon.className = isPassword ? 'far fa-eye-slash' : 'far fa-eye';
        });
    }

    // Google Button Click Loading State
    var googleBtn = document.getElementById('btnGoogleAuth');
    if (googleBtn) {
        var isGoogleProcessing = false;
        googleBtn.addEventListener('click', function (e) {
            if (isGoogleProcessing) {
                e.preventDefault();
                return false;
            }
            isGoogleProcessing = true;
            googleBtn.classList.add('is-loading');
            var btnText = googleBtn.querySelector('.btn-text');
            var btnSpinner = googleBtn.querySelector('.btn-spinner');
            var iconWrap = googleBtn.querySelector('.google-icon-wrapper');
            if (btnText) btnText.textContent = 'Connecting to Google...';
            if (btnSpinner) btnSpinner.style.display = 'inline-flex';
            if (iconWrap) iconWrap.style.display = 'none';
        });
    }

    // Form Submit Loading State
    var loginForm = document.getElementById('customerLoginForm');
    var submitBtn = document.getElementById('btnEmailLogin');
    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', function () {
            submitBtn.classList.add('is-loading');
            var btnText = submitBtn.querySelector('.btn-text');
            var btnSpinner = submitBtn.querySelector('.btn-spinner');
            if (btnText) btnText.textContent = 'Signing in...';
            if (btnSpinner) btnSpinner.style.display = 'inline-flex';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
