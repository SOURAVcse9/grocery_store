<?php
/**
 * ==========================================================================
 * public/forgot-password.php — Password Reset Request Page
 * ==========================================================================
 * Verifies email addresses, writes secure reset tokens to the database,
 * and displays development reset links for testing.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$resetLink = null;
$resetEmail = null;

if (method_is('post')) {
    // Verify CSRF
    verify_csrf_or_fail();

    $email = trim(input('email', ''));

    $v = new Validator();
    $v->required('email', $email, 'Email address is required.')
      ->email('email', $email);

    if ($v->hasErrors()) {
        flash('forgot', $v->first(), 'error');
        set_old_input($_POST);
    } else {
        try {
            $pdo = db();
            
            // Look up user
            $userStmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
            $userStmt->execute(['email' => $email]);
            $user = $userStmt->fetch();

            // Always display a user-friendly flash message to avoid user enumeration
            flash('forgot', 'If your email is registered with us, a password reset link has been generated.', 'success');
            clear_old_input();

            if ($user) {
                // Generate secure token: 32 random bytes
                $rawToken = bin2hex(random_bytes(32));
                $hashedToken = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour validity

                // Invalidate any older resets for this user
                $invalidateStmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid');
                $invalidateStmt->execute(['uid' => (int) $user['id']]);

                // Save new token
                $insertStmt = $pdo->prepare('
                    INSERT INTO password_resets (user_id, token, expires_at, used)
                    VALUES (:uid, :token, :expires_at, 0)
                ');
                $insertStmt->execute([
                    'uid'        => (int) $user['id'],
                    'token'      => $hashedToken,
                    'expires_at' => $expiresAt
                ]);

                // Expose link for testing in development environment
                $resetLink = url_for('reset-password.php?token=' . $rawToken);
                $resetEmail = $email;
            }
        } catch (PDOException $e) {
            error_log('[forgot-password.php] Error: ' . $e->getMessage());
            flash('forgot', 'An error occurred. Please try again later.', 'error');
        }
    }
}

$pageTitle = 'Forgot Password — ' . site_name();
$pageDescription = 'Request a password reset link to regain access to your customer account.';

$extraStylesheets = ['css/auth.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card">
            
            <div class="auth-header">
                <span class="auth-logo"><i class="fas fa-shopping-basket"></i> Grocery Store</span>
                <h1 class="auth-title">Reset Password</h1>
                <p class="auth-subtext">Enter your email and we'll help you reset your password</p>
            </div>

            <!-- Flash alerts -->
            <?php display_flash_alerts('forgot'); ?>

            <!-- Form -->
            <form action="<?= current_url() ?>" method="post" class="auth-form">
                <?= csrf_field() ?>

                <div class="form-field-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email', '')) ?>" placeholder="e.g. customer@gmail.com" required autocomplete="email" autofocus>
                </div>

                <button type="submit" class="btn btn-primary btn-auth-submit">Send Reset Link</button>
            </form>

            <!-- Dev/Sandbox reset link presenter -->
            <?php if ($resetLink !== null): ?>
                <div style="background: #fff8e1; border: 1px dashed #ffe082; border-radius: var(--radius-md); padding: var(--space-4); margin-top: var(--space-4); text-align: left;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 800; color: #b78103; margin-top: 0; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-flask"></i> Sandbox Environment Reset Link
                    </h4>
                    <p style="font-size: 11px; color: var(--color-text-muted); line-height: 1.4; margin-bottom: var(--space-3);">
                        Because SMTP emails are mock-configured locally, click the generated link below to test the reset flow for <strong><?= e($resetEmail) ?></strong>:
                    </p>
                    <a href="<?= $resetLink ?>" class="btn btn-secondary" style="display: block; text-align: center; font-size: 11px; font-weight: 700; border-radius: var(--radius-pill); padding: 8px;">
                        Reset Password Now
                    </a>
                </div>
            <?php endif; ?>

            <div class="auth-footer">
                Remember your password? <a href="<?= url_for('login.php') ?>">Sign in here</a>
            </div>

        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
