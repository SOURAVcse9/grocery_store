<?php
/**
 * ==========================================================================
 * admin/profile.php — Administrator Profile & Settings Page
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/middleware/auth_middleware.php';

require_admin_auth();

$pdo = db();
$admin = current_admin();

$success = null;
$error = null;

// A. Handle Profile Updates (Name, Email, Phone, Avatar)
if (method_is('post') && isset($_POST['update_profile'])) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF token failed).';
    } else {
        $fullName = trim(input('full_name', ''));
        $email = trim(input('email', ''));
        $phone = trim(input('phone', ''));

        // Basic validations
        if (empty($fullName) || empty($email)) {
            $error = 'Full Name and Email are required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Check if email already registered by other admin
                $emailCheck = $pdo->prepare("SELECT id FROM admins WHERE email = :email AND id != :id LIMIT 1");
                $emailCheck->execute(['email' => $email, 'id' => $admin['id']]);
                if ($emailCheck->fetch()) {
                    $error = 'This email address is already in use by another administrator.';
                } else {
                    $avatarName = $admin['avatar'];
                    
                    // Handle Avatar File Upload
                    if (!empty($_FILES['avatar']['name'])) {
                        $file = $_FILES['avatar'];
                        
                        if ($file['error'] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                if ($file['size'] <= 2 * 1024 * 1024) { // Max 2MB
                                    $uploadDir = __DIR__ . '/../public/uploads/users'; // Wait, let's save in storefront uploads/users/
                                    if (!is_dir($uploadDir)) {
                                        mkdir($uploadDir, 0775, true);
                                    }
                                    
                                    // Generate unique filename
                                    $newAvatarName = 'admin_av_' . uniqid('', true) . '.' . $ext;
                                    $targetFile = $uploadDir . '/' . $newAvatarName;
                                    
                                    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                                        // Delete old avatar if exists
                                        if (!empty($admin['avatar'])) {
                                            $oldAvatarPath = $uploadDir . '/' . $admin['avatar'];
                                            if (file_exists($oldAvatarPath)) {
                                                @unlink($oldAvatarPath);
                                            }
                                        }
                                        $avatarName = $newAvatarName;
                                    } else {
                                        $error = 'Failed to save uploaded image.';
                                    }
                                } else {
                                    $error = 'Avatar file size must be smaller than 2MB.';
                                }
                            } else {
                                $error = 'Only JPG, JPEG, PNG, and WebP images are allowed.';
                            }
                        } else {
                            $error = 'Image upload encountered error code ' . $file['error'];
                        }
                    }

                    if ($error === null) {
                        // Update Database
                        $up = $pdo->prepare("
                            UPDATE admins 
                            SET full_name = :name, email = :email, phone = :phone, avatar = :avatar, updated_at = NOW() 
                            WHERE id = :id
                        ");
                        $up->execute([
                            'name'   => $fullName,
                            'email'  => $email,
                            'phone'  => $phone,
                            'avatar' => $avatarName,
                            'id'     => $admin['id']
                        ]);

                        log_admin_activity('profile_update', 'Updated profile details (name, email, or avatar)');
                        $success = 'Profile details updated successfully!';
                        
                        // Reload admin values
                        $admin = current_admin();
                    }
                }
            } catch (PDOException $e) {
                error_log('[admin/profile] Profile save fail: ' . $e->getMessage());
                $error = 'Failed to save profile changes due to database error.';
            }
        }
    }
}

// B. Handle Password Changes
if (method_is('post') && isset($_POST['change_password'])) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF token failed).';
    } else {
        $currentPass = input('current_password', '');
        $newPass = input('new_password', '');
        $confirmPass = input('confirm_password', '');

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $error = 'Please enter all password fields.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New password and confirm password do not match.';
        } elseif (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif (!password_verify($currentPass, $admin['password'])) {
            $error = 'Incorrect current password.';
        } else {
            try {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE admins SET password = :pass, updated_at = NOW() WHERE id = :id");
                $up->execute(['pass' => $newHash, 'id' => $admin['id']]);

                log_admin_activity('password_change', 'Changed account login password');
                $success = 'Your password has been changed successfully!';
            } catch (PDOException $e) {
                error_log('[admin/profile] Password save fail: ' . $e->getMessage());
                $error = 'Failed to save new password due to database error.';
            }
        }
    }
}

// C. Fetch recent login/activity logs
try {
    $activitiesStmt = $pdo->prepare("
        SELECT * FROM admin_activity_logs 
        WHERE admin_id = :aid 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $activitiesStmt->execute(['aid' => $admin['id']]);
    $activities = $activitiesStmt->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/profile] Log fetch fail: ' . $e->getMessage());
    $activities = [];
}

$pageTitle = 'My Profile Settings — ' . site_name();
require_once __DIR__ . '/layouts/dashboard_layout.php';
?>

<div style="margin-top: var(--space-6); margin-bottom: var(--space-8);">
    
    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Admin Profile Settings</h1>
            <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage your login credentials, avatar photo, and monitor login activity.</p>
        </div>
        <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </div>

    <!-- Alert notifications -->
    <?php if ($success !== null): ?>
        <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:14px; border-radius:var(--radius-md); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
            <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
        </div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:14px; border-radius:var(--radius-md); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
            <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Two-column responsive layout -->
    <div style="display:grid; grid-template-columns: 320px 1fr; gap:var(--space-6); align-items:start;" class="profile-settings-layout">
        
        <!-- Left Sidebar Details -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            
            <!-- Quick overview card -->
            <div class="dashboard-card" style="text-align:center; padding:var(--space-6);">
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; border:3px solid var(--color-primary); display:inline-block; margin-bottom:12px; background:var(--color-bg);">
                    <?php 
                    $avatarUrl = !empty($admin['avatar']) ? asset('uploads/users/' . $admin['avatar']) : asset('images/ui/placeholder.png');
                    ?>
                    <img src="<?= e($avatarUrl) ?>" alt="Admin Avatar" style="width:100%; height:100%; object-fit:cover;">
                </div>
                
                <h3 style="font-size:16px; font-weight:800; color:var(--color-text); margin:0 0 4px 0;"><?= e($admin['full_name']) ?></h3>
                <span style="font-size:11px; background:var(--color-primary-light); color:var(--color-primary-dark); padding:4px 10px; border-radius:12px; font-weight:700; text-transform:uppercase; display:inline-block;"><?= e($admin['role_name']) ?></span>
                
                <div style="margin-top:20px; text-align:left; border-top:1px solid var(--color-border); padding-top:16px; font-size:12px; display:flex; flex-direction:column; gap:8px;">
                    <div><span style="color:var(--color-text-faint);">Username:</span> <strong style="color:var(--color-text);"><?= e($admin['username']) ?></strong></div>
                    <div><span style="color:var(--color-text-faint);">Registered:</span> <strong style="color:var(--color-text);"><?= date('M d, Y', strtotime($admin['created_at'])) ?></strong></div>
                    <div><span style="color:var(--color-text-faint);">Last Login:</span> <strong style="color:var(--color-text);"><?= !empty($admin['last_login_at']) ? date('M d, Y H:i', strtotime($admin['last_login_at'])) : 'First login session' ?></strong></div>
                </div>
            </div>
            
            <!-- Logout action card -->
            <a href="logout.php" class="btn btn-secondary" style="width:100%; padding:12px; border-radius:var(--radius-pill); font-weight:700; text-align:center; display:block; border-color:var(--color-danger); color:var(--color-danger); text-decoration:none;"><i class="fas fa-sign-out-alt"></i> Sign Out Session</a>

        </div>

        <!-- Right Main Settings area -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            
            <!-- Update Profile Form -->
            <div class="dashboard-card" style="padding:var(--space-6);">
                <h2 style="font-size:16px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-5); border-bottom:1px solid var(--color-border); padding-bottom:10px;">Update Profile Details</h2>
                
                <form method="post" enctype="multipart/form-data" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);" class="grid-2">
                        <!-- Full Name -->
                        <div class="form-field-group">
                            <label for="profName" style="font-weight:700;">Full Name *</label>
                            <input type="text" id="profName" name="full_name" required value="<?= e($admin['full_name']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                        </div>

                        <!-- Email -->
                        <div class="form-field-group">
                            <label for="profEmail" style="font-weight:700;">Email Address *</label>
                            <input type="email" id="profEmail" name="email" required value="<?= e($admin['email']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);" class="grid-2">
                        <!-- Phone -->
                        <div class="form-field-group">
                            <label for="profPhone" style="font-weight:700;">Phone Number</label>
                            <input type="text" id="profPhone" name="phone" value="<?= e($admin['phone'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                        </div>

                        <!-- Avatar Upload -->
                        <div class="form-field-group">
                            <label for="profAvatar" style="font-weight:700;">Avatar Image</label>
                            <input type="file" id="profAvatar" name="avatar" accept="image/*" style="font-size:12px; display:block; margin-top:6px;">
                            <span class="field-help-text">JPG, PNG, or WebP. Max 2MB.</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding:10px 24px; border:none; border-radius:var(--radius-pill); font-weight:700; margin-top:12px;">Save Profile Changes</button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="dashboard-card" style="padding:var(--space-6);">
                <h2 style="font-size:16px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-5); border-bottom:1px solid var(--color-border); padding-bottom:10px;">Change Password</h2>
                
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="change_password" value="1">

                    <div class="form-field-group">
                        <label for="passCurrent" style="font-weight:700;">Current Password *</label>
                        <input type="password" id="passCurrent" name="current_password" required placeholder="Enter current login password" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);" class="grid-2">
                        <div class="form-field-group">
                            <label for="passNew" style="font-weight:700;">New Password *</label>
                            <input type="password" id="passNew" name="new_password" required minlength="8" placeholder="At least 8 characters" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                        </div>
                        <div class="form-field-group">
                            <label for="passConfirm" style="font-weight:700;">Confirm New Password *</label>
                            <input type="password" id="passConfirm" name="confirm_password" required placeholder="Retype new password" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding:10px 24px; border:none; border-radius:var(--radius-pill); font-weight:700; margin-top:12px;">Change Password</button>
                </form>
            </div>

            <!-- Recent Activity Log List -->
            <div class="dashboard-card" style="padding:var(--space-6);">
                <h2 style="font-size:16px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-5); border-bottom:1px solid var(--color-border); padding-bottom:10px;">Recent Activity Log</h2>
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $log): ?>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid var(--color-border); padding-bottom:10px; font-size:12px;">
                                <div>
                                    <strong style="text-transform:uppercase; font-size:10px; background:#f1f3f5; color:#495057; padding:2px 6px; border-radius:4px; margin-right:6px;"><?= e($log['activity_type']) ?></strong>
                                    <span style="color:var(--color-text-muted);"><?= e($log['description']) ?></span>
                                </div>
                                <span style="font-size:10px; color:var(--color-text-faint); font-weight:500;"><?= time_ago($log['created_at']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--color-text-faint); font-size:12px; margin:0; text-align:center;">No recent activity logs found.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
</div>
