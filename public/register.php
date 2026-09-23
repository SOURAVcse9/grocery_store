<?php
/**
 * ==========================================================================
 * public/register.php — Production Customer Registration
 * ==========================================================================
 * Dual customer registration supporting Email + Password with validation,
 * password strength metering, and Google OAuth onboarding.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/includes/google_auth.php';
require_once __DIR__ . '/includes/rate_limit.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$pageTitle = 'Create Account — ' . site_name();
$pageDescription = 'Create your GroCo customer account with email or Google to enjoy fast grocery checkout and order tracking.';
$extraStylesheets = ['css/auth.css'];

$returnUrl = input('return_url', '', 'get') ?: input('redirect', '', 'get');
if (empty($returnUrl) && !empty($_SESSION['intended_url'])) {
    $returnUrl = $_SESSION['intended_url'];
}

$googleLoginUrl = url_for('auth/google-login.php' . (!empty($returnUrl) ? '?return_url=' . urlencode($returnUrl) : ''));

// Process Email + Password POST Registration
if (method_is('post')) {
    if (!verify_csrf()) {
        flash('auth', 'Security token expired. Please try submitting again.', 'error');
    } else {
        $fullName = trim(input('full_name', ''));
        $email = strtolower(trim(input('email', '')));
        $phone = trim(input('phone', ''));
        $password = input('password', '');
        $confirmPassword = input('confirm_password', '');

        // Rate limiting: 5 registrations per 5 minutes per IP
        if (!check_rate_limit('customer_register', 5, 300, false)) {
            flash('auth', 'Too many registration requests from your network. Please wait a few minutes.', 'error');
        } else {
            // Validation
            $v = new Validator();
            $v->required('full_name', $fullName, 'Full name is required.')
              ->length('full_name', $fullName, 2, 100, 'Full name must be between 2 and 100 characters.')
              ->required('email', $email, 'Email address is required.')
              ->email('email', $email)
              ->required('password', $password, 'Password is required.')
              ->length('password', $password, 8, 100, 'Password must contain at least 8 characters.')
              ->required('confirm_password', $confirmPassword, 'Please confirm your password.')
              ->custom('confirm_password', $password === $confirmPassword, 'Passwords do not match.');

            if (!empty($phone)) {
                $v->phone('phone', $phone);
            }

            if ($v->hasErrors()) {
                flash('auth', $v->first() ?? 'Validation failed.', 'error');
                set_old_input($_POST);
            } else {
                // Strictly prevent administrator emails from registering customer accounts
                if (is_admin_email($email)) {
                    flash('auth', 'This email address belongs to an administrator account and cannot be registered as a customer. Please log in via the Admin Panel or use a different email.', 'error');
                    set_old_input($_POST);
                } else {
                    $pdo = db();

                    // Check for duplicate email in users table
                    $checkStmt = $pdo->prepare('SELECT id, role_id, full_name, email, password, google_id FROM users WHERE email = :email LIMIT 1');
                    $checkStmt->execute(['email' => $email]);
                    $existingUser = $checkStmt->fetch();

                if ($existingUser) {
                    if (!empty($existingUser['password'])) {
                        flash('auth', 'An account with this email address already exists. Please sign in.', 'error');
                    } else {
                        // Google account exists without password: add password to this account (seamless linking)
                        $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                        $linkStmt = $pdo->prepare('
                            UPDATE users SET 
                                password = :pass,
                                full_name = COALESCE(NULLIF(:name, \'\'), full_name),
                                phone = COALESCE(NULLIF(:phone, \'\'), phone),
                                updated_at = NOW()
                            WHERE id = :id
                        ');
                        $linkStmt->execute([
                            'pass'  => $hashedPass,
                            'name'  => $fullName,
                            'phone' => $phone ?: null,
                            'id'    => (int) $existingUser['id'],
                        ]);

                        $checkStmt->execute(['email' => $email]);
                        $updatedUser = $checkStmt->fetch();

                        login_user($updatedUser);
                        flash('auth', 'Your password has been set and your account is ready!', 'success');

                        $destination = $returnUrl;
                        unset($_SESSION['intended_url']);
                        redirect(!empty($destination) && str_starts_with($destination, '/') ? $destination : url_for('account.php'));
                    }
                } else {
                    // Create new customer account
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $insertStmt = $pdo->prepare('
                        INSERT INTO users (
                            role_id, full_name, email, phone, password, 
                            email_verified, is_verified, is_active, session_version,
                            wallet_balance, reward_points, created_at, updated_at, last_login_at
                        ) VALUES (
                            2, :full_name, :email, :phone, :password,
                            0, 0, 1, 1,
                            0.00, 0, NOW(), NOW(), NOW()
                        )
                    ');
                    $insertStmt->execute([
                        'full_name' => $fullName,
                        'email'     => $email,
                        'phone'     => $phone ?: null,
                        'password'  => $hashedPassword,
                    ]);

                    $newId = (int) $pdo->lastInsertId();

                    $fetchStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
                    $fetchStmt->execute(['id' => $newId]);
                    $newCustomer = $fetchStmt->fetch();

                    // Log in customer immediately and merge cart
                    login_user($newCustomer);
                    clear_old_input();

                    flash('auth', 'Welcome to ' . site_name() . ', ' . htmlspecialchars($fullName) . '! Your account is created.', 'success');

                    $destination = $returnUrl;
                    unset($_SESSION['intended_url']);
                    redirect(!empty($destination) && str_starts_with($destination, '/') ? $destination : url_for('account.php'));
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
                <h1 class="auth-title">Create Your Account</h1>
                <p class="auth-subtext">
                    Join GroCo and enjoy faster checkout and order tracking.
                </p>
            </div>

            <!-- Flash Error / Status Alerts -->
            <?php display_flash_alerts('auth'); ?>

            <!-- Registration Form -->
            <form action="<?= current_url() ?>" method="post" class="auth-form" id="customerRegisterForm">
                <?= csrf_field() ?>
                <?php if (!empty($returnUrl)): ?>
                    <input type="hidden" name="return_url" value="<?= e($returnUrl) ?>">
                <?php endif; ?>

                <div class="form-field-group">
                    <label for="full_name">Full Name *</label>
                    <div class="auth-input-wrapper">
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               class="auth-input" 
                               placeholder="e.g. John Doe" 
                               value="<?= e(old('full_name', '')) ?>" 
                               required 
                               autocomplete="name" 
                               autofocus>
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="email">Email Address *</label>
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
                    <label for="phone">Phone Number</label>
                    <div class="auth-input-wrapper">
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               class="auth-input" 
                               placeholder="e.g. +880 1700-000000" 
                               value="<?= e(old('phone', '')) ?>" 
                               autocomplete="tel">
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="password">Password *</label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="auth-input" 
                               placeholder="Minimum 8 characters" 
                               required 
                               autocomplete="new-password">
                        <button type="button" 
                                class="password-toggle-btn" 
                                id="togglePasswordBtn" 
                                aria-label="Toggle password visibility" 
                                tabindex="-1">
                            <i class="far fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>

                    <!-- Dynamic Password Strength Meter -->
                    <div class="strength-meter-container" id="strengthMeterContainer" style="display: none;">
                        <div class="strength-bar-bg">
                            <div class="strength-bar-fill" id="strengthBarFill"></div>
                        </div>
                        <span class="strength-meter-text" id="strengthBarText">Strength: Too Short</span>
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="auth-input" 
                               placeholder="Re-enter your password" 
                               required 
                               autocomplete="new-password">
                        <button type="button" 
                                class="password-toggle-btn" 
                                id="toggleConfirmPasswordBtn" 
                                aria-label="Toggle confirm password visibility" 
                                tabindex="-1">
                            <i class="far fa-eye" id="toggleConfirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-auth-submit" id="btnRegisterSubmit">
                    <span class="btn-text">Create Account</span>
                    <span class="btn-spinner" style="display: none;" aria-hidden="true">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </span>
                </button>
            </form>

            <!-- OR Divider -->
            <div class="auth-divider">
                <span>OR</span>
            </div>

            <!-- Google OAuth Button -->
            <div class="auth-action-box" style="margin: 0;">
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

            <!-- Footer Navigation -->
            <div class="auth-footer-nav">
                Already have an account? <a href="<?= url_for('login.php') ?>">Sign In</a>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Password Visibility Toggles
    function setupPasswordToggle(btnId, inputId, iconId) {
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
    setupPasswordToggle('togglePasswordBtn', 'password', 'togglePasswordIcon');
    setupPasswordToggle('toggleConfirmPasswordBtn', 'confirm_password', 'toggleConfirmPasswordIcon');

    // Live Password Strength Meter
    var passwordInput = document.getElementById('password');
    var meterContainer = document.getElementById('strengthMeterContainer');
    var barFill = document.getElementById('strengthBarFill');
    var barText = document.getElementById('strengthBarText');

    if (passwordInput && meterContainer && barFill && barText) {
        passwordInput.addEventListener('input', function () {
            var val = this.value;
            if (val.length === 0) {
                meterContainer.style.display = 'none';
                return;
            }
            meterContainer.style.display = 'flex';

            var score = 0;
            if (val.length >= 8) score++;
            if (val.length >= 12) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            barFill.className = 'strength-bar-fill';
            if (score <= 2) {
                barFill.classList.add('weak');
                barText.textContent = 'Strength: Weak';
                barText.style.color = '#ef4444';
            } else if (score <= 3) {
                barFill.classList.add('fair');
                barText.textContent = 'Strength: Medium';
                barText.style.color = '#f59e0b';
            } else {
                barFill.classList.add('strong');
                barText.textContent = 'Strength: Strong';
                barText.style.color = '#10b981';
            }
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
    var regForm = document.getElementById('customerRegisterForm');
    var submitBtn = document.getElementById('btnRegisterSubmit');
    if (regForm && submitBtn) {
        regForm.addEventListener('submit', function () {
            submitBtn.classList.add('is-loading');
            var btnText = submitBtn.querySelector('.btn-text');
            var btnSpinner = submitBtn.querySelector('.btn-spinner');
            if (btnText) btnText.textContent = 'Creating account...';
            if (btnSpinner) btnSpinner.style.display = 'inline-flex';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
