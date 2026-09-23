<?php
/**
 * ==========================================================================
 * public/profile.php — Customer Profile & Security Management
 * ==========================================================================
 * Allows logged-in customers to update details, view connected auth providers
 * (Google / Email), manage credentials, and sign out of all devices.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page access
require_login();

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();

// Check if user has password and fetch security details
$passStmt = $pdo->prepare('SELECT password, google_id, last_password_change FROM users WHERE id = :id LIMIT 1');
$passStmt->execute(['id' => $userId]);
$userSec = $passStmt->fetch();

$hasPassword = !empty($userSec['password']);
$isGoogleConnected = !empty($userSec['google_id']);
$lastPasswordChange = $userSec['last_password_change'] ?? null;

$pageTitle = 'Security & Profile — ' . site_name();
$pageDescription = 'Manage your profile contact details, connected accounts, and security credentials.';

$extraStylesheets = ['css/account.css', 'css/auth.css'];
$extraScripts = ['js/account.js'];

require_once __DIR__ . '/header.php';
?>

<div class="container" style="margin-top: var(--space-5);">
    <div class="account-layout">
        
        <!-- Left Sidebar Navigation Menu -->
        <aside class="account-sidebar">
            <ul class="account-menu-list">
                <li class="account-menu-item">
                    <a href="<?= url_for('account.php') ?>"><i class="fas fa-gauge"></i> Dashboard</a>
                </li>
                <li class="account-menu-item active">
                    <a href="<?= url_for('profile.php') ?>"><i class="fas fa-user-shield"></i> Security &amp; Profile</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('addresses.php') ?>"><i class="fas fa-map-location-dot"></i> Saved Addresses</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('orders.php') ?>"><i class="fas fa-box-open"></i> My Orders</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('wishlist.php') ?>"><i class="fas fa-heart"></i> Wishlist</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('logout.php') ?>" style="color:var(--color-danger);"><i class="fas fa-power-off" style="color:var(--color-danger);"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Right Main Panel -->
        <main class="account-main-content">
            
            <!-- Edit Profile Details Card -->
            <div class="dashboard-card">
                <h3 class="dashboard-card-title">Profile Information</h3>
                
                <?php display_flash_alerts('profile'); ?>

                <form action="<?= url_for('update_profile.php') ?>" method="post" enctype="multipart/form-data" class="auth-form" id="profileDetailsForm">
                    <?= csrf_field() ?>

                    <!-- Avatar Uploader -->
                    <div class="profile-avatar-upload-box">
                        <div class="avatar-preview-circle">
                            <?php 
                            $avatarUrl = user_avatar_url($user['avatar'], $user['full_name'] ?? 'Customer'); 
                            $fallbackInitial = generate_initials_svg_data_uri($user['full_name'] ?? 'Customer');
                            ?>
                            <img id="avatarPreviewImage" src="<?= e($avatarUrl) ?>" alt="Avatar Preview" onerror="this.onerror=null;this.src='<?= e($fallbackInitial) ?>';">
                        </div>
                        <div class="avatar-upload-instructions">
                            <label for="avatarUploadInput">Choose Image</label>
                            <input type="file" id="avatarUploadInput" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <span>Max size: 2MB. Format: JPG, PNG, WEBP.</span>
                        </div>
                    </div>

                    <div class="checkout-form-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="form-field-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="auth-input" value="<?= e(old('full_name', $user['full_name'])) ?>" required>
                        </div>
                        
                        <div class="form-field-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="auth-input" value="<?= e(old('phone', $user['phone'] ?? '')) ?>">
                        </div>

                        <div class="form-field-group col-span-2">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="auth-input" value="<?= e(old('email', $user['email'])) ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit" style="width: fit-content; padding: 0 28px; height: 44px; margin-top: 8px;">Save Profile</button>
                </form>
            </div>

            <!-- SECURITY & SIGN-IN CENTER -->
            <div class="dashboard-card" style="margin-top: var(--space-5);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                    <h3 class="dashboard-card-title" style="margin: 0;">Security &amp; Sign-In</h3>
                    <span style="font-size: 12px; font-weight: 700; color: var(--color-primary); background: rgba(12, 166, 120, 0.1); padding: 4px 12px; border-radius: var(--radius-pill);">
                        <i class="fas fa-shield-halved"></i> Protected
                    </span>
                </div>
                
                <div class="connected-accounts-grid">
                    <!-- Google Provider -->
                    <div class="connected-account-card">
                        <div class="connected-account-info">
                            <span class="connected-account-icon" style="color:#4285F4;"><i class="fab fa-google"></i></span>
                            <div>
                                <strong style="display:block; font-size:14px; color:var(--color-text);">Google</strong>
                                <span style="font-size:12px; color:var(--color-text-muted); display: block; margin-top: 2px;">
                                    <?php if ($isGoogleConnected): ?>
                                        <?= e($user['email']) ?><br>
                                        <span style="color: #10b981; font-weight: 600;"><i class="fas fa-circle-check"></i> Verified Google account</span>
                                    <?php else: ?>
                                        Not linked to this customer account
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <?php if ($isGoogleConnected): ?>
                                <span class="connected-account-badge badge-connected"><i class="fas fa-check"></i> Connected</span>
                            <?php else: ?>
                                <a href="<?= url_for('auth/google-login.php?return_url=' . urlencode('/profile.php')) ?>" class="btn-google-auth" style="height: 36px; padding: 0 14px; font-size: 12px;">
                                    Connect Google
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Email & Password Provider -->
                    <div class="connected-account-card">
                        <div class="connected-account-info">
                            <span class="connected-account-icon" style="color:var(--color-primary);"><i class="fas fa-envelope"></i></span>
                            <div>
                                <strong style="display:block; font-size:14px; color:var(--color-text);">Email &amp; Password</strong>
                                <span style="font-size:12px; color:var(--color-text-muted); display: block; margin-top: 2px;">
                                    <?php if ($hasPassword): ?>
                                        Enabled<br>
                                        <span style="color: var(--color-text-muted);">Last password change: <?= $lastPasswordChange ? date('F j, Y', strtotime($lastPasswordChange)) : 'Recently' ?></span>
                                    <?php else: ?>
                                        Not configured<br>
                                        <span style="color: var(--color-text-muted);">Add a password to enable email sign-in.</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <?php if ($hasPassword): ?>
                                <span class="connected-account-badge badge-connected"><i class="fas fa-check"></i> Enabled</span>
                            <?php else: ?>
                                <span class="connected-account-badge badge-unconnected">Not configured</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Account Security Overview -->
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; margin-top: 16px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
                        <div>
                            <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Password Status</span>
                            <strong style="font-size: 13px; color: <?= $hasPassword ? '#10b981' : 'var(--color-text-muted)' ?>;">
                                <i class="fas <?= $hasPassword ? 'fa-lock' : 'fa-lock-open' ?>"></i> <?= $hasPassword ? 'Strong / Configured' : 'Not Configured' ?>
                            </strong>
                        </div>
                        <div>
                            <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Two-Factor Authentication</span>
                            <span style="font-size: 13px; color: var(--color-text-muted); font-weight: 700;">Coming soon / Not configured</span>
                        </div>
                        <div>
                            <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Active Sessions</span>
                            <span style="font-size: 13px; color: var(--color-primary); font-weight: 700;">Active on this device</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Management Form Card -->
            <div class="dashboard-card" style="margin-top: var(--space-5);">
                <h3 class="dashboard-card-title"><?= $hasPassword ? 'Change Password' : 'Create a password' ?></h3>
                
                <p style="font-size: 13px; color: var(--color-text-muted); line-height: 1.5; margin-bottom: 16px;">
                    <?= $hasPassword 
                        ? 'To update your existing password, enter your current password followed by your new secure password.' 
                        : 'Add an email password so you can sign in with either Google or your email address.' ?>
                </p>

                <?php display_flash_alerts('password'); ?>

                <form action="<?= url_for('update_password.php') ?>" method="post" class="auth-form" id="profilePasswordForm">
                    <?= csrf_field() ?>

                    <?php if ($hasPassword): ?>
                        <div class="form-field-group">
                            <label for="current_password">Current Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="current_password" name="current_password" class="auth-input" placeholder="Enter your current password" required autocomplete="current-password">
                                <button type="button" class="password-toggle-btn" id="toggleCurPassBtn" aria-label="Toggle password visibility" tabindex="-1">
                                    <i class="far fa-eye" id="toggleCurPassIcon"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="checkout-form-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="form-field-group">
                            <label for="new_password">New Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="new_password" name="new_password" class="auth-input" placeholder="Min. 8 characters" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" id="toggleNewPassBtn" aria-label="Toggle password visibility" tabindex="-1">
                                    <i class="far fa-eye" id="toggleNewPassIcon"></i>
                                </button>
                            </div>
                            
                            <!-- Live Strength Bar -->
                            <div class="strength-meter-container" id="profileStrengthContainer" style="display: none;">
                                <div class="strength-bar-bg">
                                    <div class="strength-bar-fill" id="profileStrengthFill"></div>
                                </div>
                                <span class="strength-meter-text" id="profileStrengthText">Strength: Too Short</span>
                            </div>
                        </div>

                        <div class="form-field-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" class="auth-input" placeholder="Confirm new password" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" id="toggleConfirmPassBtn" aria-label="Toggle password visibility" tabindex="-1">
                                    <i class="far fa-eye" id="toggleConfirmPassIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Password Policy Requirements Checklist -->
                    <div style="font-size: 12px; color: var(--color-text-muted); line-height: 1.6; margin: 4px 0 12px 0;">
                        <strong>Password requirements:</strong>
                        <ul style="margin: 4px 0 0 18px; padding: 0;">
                            <li>Minimum 8 characters in length</li>
                            <li>At least one uppercase letter (A-Z) and lowercase letter (a-z)</li>
                            <li>At least one numeric digit (0-9)</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn-auth-submit" style="width: fit-content; padding: 0 28px; height: 44px;">
                        <?= $hasPassword ? 'Update Password' : 'Create Password' ?>
                    </button>
                </form>
            </div>

            <!-- Device Security / Sign Out All Devices Card -->
            <div class="dashboard-card" style="margin-top: var(--space-5);">
                <h3 class="dashboard-card-title">Device &amp; Session Security</h3>
                
                <?php display_flash_alerts('security'); ?>

                <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <strong style="font-size: 14px; color: var(--color-text); display: block; margin-bottom: 4px;">Sign Out of All Devices</strong>
                        <span style="font-size: 13px; color: var(--color-text-muted); line-height: 1.4; display: block;">
                            If you suspect unauthorized access or left your account signed in on a public computer, you can invalidate all other active sessions immediately.
                        </span>
                    </div>
                    <form action="<?= url_for('security_actions.php') ?>" method="post" onsubmit="return confirm('Are you sure you want to sign out of all other devices?');" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="sign_out_all_devices">
                        <button type="submit" class="btn-auth-submit" style="background-color: transparent; border: 1.5px solid var(--color-danger); color: var(--color-danger); height: 42px; padding: 0 20px; font-size: 13px; box-shadow: none;">
                            <i class="fas fa-arrow-right-from-bracket"></i> Sign Out All Devices
                        </button>
                    </form>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
    setupToggle('toggleCurPassBtn', 'current_password', 'toggleCurPassIcon');
    setupToggle('toggleNewPassBtn', 'new_password', 'toggleNewPassIcon');
    setupToggle('toggleConfirmPassBtn', 'confirm_password', 'toggleConfirmPassIcon');

    // Strength Meter
    var passInput = document.getElementById('new_password');
    var meterWrap = document.getElementById('profileStrengthContainer');
    var fillBar = document.getElementById('profileStrengthFill');
    var textLabel = document.getElementById('profileStrengthText');

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
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
