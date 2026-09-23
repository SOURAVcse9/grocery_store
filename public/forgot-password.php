<?php
/**
 * ==========================================================================
 * public/forgot-password.php — Password Reset Request Page
 * ==========================================================================
 * Allows customers to request a secure, single-use password reset link.
 * Implements anti-enumeration protection and SHA-256 token hashing.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/includes/rate_limit.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$pageTitle = 'Forgot Password — ' . site_name();
$pageDescription = 'Request a password reset link to regain access to your customer account.';
$extraStylesheets = ['css/auth.css'];

$resetLink = null;
$resetEmail = null;
$devUserNotFound = null;
$submitted = false;

if (method_is('post')) {
    if (!verify_csrf()) {
        flash('auth', 'Security token expired. Please try submitting again.', 'error');
    } else {
        $email = strtolower(trim(input('email', '')));

        // Rate limiting: 3 requests per 15 minutes (relaxed in development mode)
        $rateLimitPassed = (APP_DEBUG && APP_ENV !== 'production') ? true : check_rate_limit('customer_forgot_pw', 3, 900, false);

        if (!$rateLimitPassed) {
            flash('auth', 'Too many password reset requests. Please try again later.', 'error');
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('auth', 'Please enter a valid email address.', 'error');
        } else {
            $submitted = true;
            try {
                $pdo = db();

                // Look up active customer by email
                $stmt = $pdo->prepare('SELECT id, full_name, email, password, google_id, is_active, is_banned FROM users WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                // Always display a generic response to prevent account enumeration
                flash('auth', 'If an account exists for this email, we\'ve sent a secure password reset link.', 'success');

                if ($user && (int)$user['is_active'] === 1 && (int)($user['is_banned'] ?? 0) === 0) {
                    $userId = (int) $user['id'];
                    $isGoogleOnly = empty($user['password']) && !empty($user['google_id']);

                    // Generate cryptographically secure 32-byte (64 hex char) random token
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour validity

                    // Invalidate previous reset tokens for this user
                    $invalidateStmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid');
                    $invalidateStmt->execute(['uid' => $userId]);

                    // Store hashed token
                    $insertStmt = $pdo->prepare('
                        INSERT INTO password_resets (user_id, token, expires_at, used, created_at)
                        VALUES (:uid, :token, :expires_at, 0, NOW())
                    ');
                    $insertStmt->execute([
                        'uid'        => $userId,
                        'token'      => $tokenHash,
                        'expires_at' => $expiresAt,
                    ]);

                    // Determine base URL using APP_URL or BASE_URL
                    $appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
                    if (!empty($appUrl)) {
                        $fullResetUrl = rtrim($appUrl, '/') . '/public/reset-password.php?token=' . $rawToken;
                    } else {
                        $relativeLink = url_for('reset-password.php?token=' . $rawToken);
                        $fullResetUrl = str_starts_with($relativeLink, 'http') ? $relativeLink : rtrim(BASE_URL, '/') . '/' . ltrim($relativeLink, '/');
                    }

                    // Generate email template
                    $emailSubject = $isGoogleOnly
                        ? 'Set Up Account Password — ' . site_name()
                        : 'Reset Your Password — ' . site_name();
                    $emailHtml = get_password_reset_email_template($user, $fullResetUrl, $isGoogleOnly);

                    // Dispatch email via SMTP mailer engine
                    send_smtp_email($email, $emailSubject, $emailHtml);

                    // Expose link in local development environment only (strictly hidden in production)
                    if (APP_DEBUG && APP_ENV !== 'production') {
                        $resetLink = url_for('reset-password.php?token=' . $rawToken);
                        $resetEmail = $email;
                        $_SESSION['dev_last_reset_link'] = $resetLink;
                        $_SESSION['dev_last_reset_email'] = $email;
                    }
                } else {
                    // In dev mode, record if email was not found to assist developer
                    if (APP_DEBUG && APP_ENV !== 'production') {
                        $devUserNotFound = $email;
                        unset($_SESSION['dev_last_reset_link'], $_SESSION['dev_last_reset_email']);
                    }
                }
            } catch (PDOException $e) {
                error_log('[forgot-password.php] Error: ' . $e->getMessage());
                flash('auth', 'An unexpected error occurred. Please try again later.', 'error');
            }
        }
    }
}

// In development mode, retrieve last generated reset link if available
if (APP_DEBUG && APP_ENV !== 'production' && empty($resetLink) && !empty($_SESSION['dev_last_reset_link'])) {
    $resetLink = $_SESSION['dev_last_reset_link'];
    $resetEmail = $_SESSION['dev_last_reset_email'] ?? '';
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

            <!-- Header with Lock Icon -->
            <div class="auth-header">
                <div style="font-size: 32px; color: var(--color-primary); margin-bottom: 8px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h1 class="auth-title">Forgot your password?</h1>
                <p class="auth-subtext">
                    Enter your email address and we'll send you a secure password reset link.
                </p>
            </div>

            <!-- Flash Error / Status Alerts -->
            <?php display_flash_alerts('auth'); ?>

            <!-- Reset Request Form -->
            <form action="<?= current_url() ?>" method="post" class="auth-form" id="forgotPasswordForm">
                <?= csrf_field() ?>

                <div class="form-field-group">
                    <label for="email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="auth-input" 
                               placeholder="e.g. customer@example.com" 
                               value="<?= e(old('email', '')) ?>" 
                               required 
                               autocomplete="email" 
                               autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-auth-submit" id="btnSendReset">
                    <span class="btn-text">Send Reset Link</span>
                    <span class="btn-spinner" style="display: none;" aria-hidden="true">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </span>
                </button>
            </form>

            <?php if ($submitted): ?>
                <!-- Helpful User Guidance -->
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; margin-top: 20px; font-size: 13px; color: var(--color-text-muted); line-height: 1.5;">
                    <strong style="color: var(--color-text); display: block; margin-bottom: 6px;">Didn't receive the email?</strong>
                    <ul style="margin: 0; padding-left: 18px;">
                        <li>Check your Spam / Junk folder.</li>
                        <li>Verify that you entered the correct email address.</li>
                        <li>Wait a few moments for mail delivery.</li>
                        <li>If your account is linked via Google, you can sign in directly with Google.</li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Dev Sandbox Helper (Strictly hidden in production) -->
            <?php if (APP_DEBUG && APP_ENV !== 'production'): ?>
                <?php if ($resetLink !== null): ?>
                    <div style="background: var(--color-bg); border: 1px dashed var(--color-primary); border-radius: var(--radius-md); padding: 16px; margin-top: 20px; text-align: left;">
                        <div style="font-size: 12px; font-weight: 800; color: var(--color-primary); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-flask"></i> Local Development Reset Link
                        </div>
                        <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 12px; line-height: 1.4;">
                            Generated secure link for <strong><?= e($resetEmail) ?></strong>:
                        </p>
                        <a href="<?= e($resetLink) ?>" class="btn-auth-submit" style="height: 40px; font-size: 13px; text-decoration: none;">
                            Open Password Reset Form &rarr;
                        </a>
                    </div>
                <?php elseif ($devUserNotFound !== null): ?>
                    <div style="background: #fff5f5; border: 1px dashed #e03131; border-radius: var(--radius-md); padding: 16px; margin-top: 20px; text-align: left;">
                        <div style="font-size: 12px; font-weight: 800; color: #e03131; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-triangle-exclamation"></i> Dev Notice: User Not Found in Database
                        </div>
                        <p style="font-size: 12px; color: #c92a2a; margin-bottom: 0; line-height: 1.4;">
                            No registered customer account exists with email <strong><?= e($devUserNotFound) ?></strong>. (Note: Production hides this to prevent user enumeration, but in Dev mode you must use a registered email address such as your Google/registered account email).
                        </p>
                    </div>
                <?php endif; ?>
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
    var form = document.getElementById('forgotPasswordForm');
    var btn = document.getElementById('btnSendReset');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.classList.add('is-loading');
            var btnText = btn.querySelector('.btn-text');
            var btnSpinner = btn.querySelector('.btn-spinner');
            if (btnText) btnText.textContent = 'Sending link...';
            if (btnSpinner) btnSpinner.style.display = 'inline-flex';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
