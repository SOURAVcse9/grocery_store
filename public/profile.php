<?php
/**
 * ==========================================================================
 * public/profile.php — Customer Profile Management Form View
 * ==========================================================================
 * Allows logged-in users to update details, upload avatar photos, and change
 * account passwords securely.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page access
require_login();

$user = current_user();

$pageTitle = 'Edit Profile — ' . site_name();
$pageDescription = 'Manage your profile contact details, upload profile photos, and change security credentials.';

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
                    <a href="<?= url_for('profile.php') ?>"><i class="fas fa-user-gear"></i> Edit Profile</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('addresses.php') ?>"><i class="fas fa-map-location-dot"></i> Saved Addresses</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('orders.php') ?>"><i class="fas fa-box-open"></i> My Orders</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('analytics.php') ?>"><i class="fas fa-chart-line"></i> Analytics</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('logout.php') ?>" style="color:var(--color-danger);"><i class="fas fa-power-off" style="color:var(--color-danger);"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Right Main Panel -->
        <main class="account-main-content">
            
            <!-- Edit Profile details Form Card -->
            <div class="dashboard-card">
                <h3 class="dashboard-card-title">Profile Information</h3>
                
                <!-- Display profile flash alerts -->
                <?php display_flash_alerts('profile'); ?>

                <form action="<?= url_for('update_profile.php') ?>" method="post" enctype="multipart/form-data" class="auth-form" id="profileDetailsForm">
                    <?= csrf_field() ?>

                    <!-- Avatar Uploader widget -->
                    <div class="profile-avatar-upload-box">
                        <div class="avatar-preview-circle">
                            <?php 
                            $avatarUrl = image_url($user['avatar'], 'users');
                            ?>
                            <img id="avatarPreviewImage" src="<?= e($avatarUrl) ?>" alt="Avatar Preview">
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
                            <input type="text" id="full_name" name="full_name" value="<?= e(old('full_name', $user['full_name'])) ?>" required>
                        </div>
                        
                        <div class="form-field-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" required>
                        </div>

                        <div class="form-field-group col-span-2">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?= e(old('email', $user['email'])) ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:var(--space-4); border-radius:var(--radius-pill); font-weight:700; width:fit-content; padding: 10px 28px; border:none;">Save Profile</button>
                </form>
            </div>

            <!-- Change Password Form Card -->
            <div class="dashboard-card" style="margin-top: var(--space-5);">
                <h3 class="dashboard-card-title">Change Password</h3>
                
                <!-- Display password flash alerts -->
                <?php display_flash_alerts('password'); ?>

                <form action="<?= url_for('update_password.php') ?>" method="post" class="auth-form" id="profilePasswordForm">
                    <?= csrf_field() ?>

                    <div class="form-field-group">
                        <label for="current_password">Current Password *</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required autocomplete="current-password">
                            <button type="button" class="password-toggle-btn" aria-label="Toggle Current Password Visibility">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="checkout-form-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="form-field-group">
                            <label for="new_password">New Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" aria-label="Toggle New Password Visibility">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                            
                            <!-- strength bar -->
                            <div class="strength-meter-container">
                                <div class="strength-bar-bg">
                                    <div class="strength-bar-fill" id="strengthBar"></div>
                                </div>
                                <span class="strength-meter-text" id="strengthText">Strength: Too Short</span>
                            </div>
                        </div>

                        <div class="form-field-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" aria-label="Toggle Confirm Password Visibility">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:var(--space-4); border-radius:var(--radius-pill); font-weight:700; width:fit-content; padding: 10px 28px; border:none;">Change Password</button>
                </form>
            </div>

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
