<?php
/**
 * ==========================================================================
 * public/update_password.php — Password Change / Create Processor
 * ==========================================================================
 * Supports updating passwords for existing password accounts and creating
 * passwords for Google-only customer accounts without creating duplicate rows.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure endpoint access
require_login();
require_method('POST');
verify_csrf_or_fail();

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();

// Check if user currently has a password
$passQuery = $pdo->prepare('SELECT password FROM users WHERE id = :uid LIMIT 1');
$passQuery->execute(['uid' => $userId]);
$dbHash = $passQuery->fetchColumn();

$hasExistingPassword = !empty($dbHash);
$currentPassword = input('current_password', '');
$newPassword = input('new_password', '');
$confirmPassword = input('confirm_password', '');

$v = new Validator();

if ($hasExistingPassword) {
    $v->required('current_password', $currentPassword, 'Current password is required.');
}

$v->required('new_password', $newPassword, 'New password is required.')
  ->length('new_password', $newPassword, 8, 100, 'New password must contain at least 8 characters.')
  ->required('confirm_password', $confirmPassword, 'Please confirm your new password.')
  ->custom('confirm_password', $newPassword === $confirmPassword, 'Passwords do not match.');

// Verify password complexity (uppercase, lowercase, number)
if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
    $v->addError('new_password', 'Password must include uppercase, lowercase, and a number.');
}

if ($v->hasErrors()) {
    flash('password', $v->first(), 'error');
    redirect(url_for('profile.php'));
}

try {
    // If existing password, verify current password
    if ($hasExistingPassword) {
        if (!password_verify($currentPassword, (string)$dbHash)) {
            flash('password', 'Current password is incorrect.', 'error');
            redirect(url_for('profile.php'));
        }
    }

    // Hash and update password
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare('
        UPDATE users 
        SET password = :pass, 
            last_password_change = NOW(), 
            remember_token = NULL, 
            updated_at = NOW() 
        WHERE id = :uid
    ');
    $updateStmt->execute([
        'pass' => $newHash,
        'uid'  => $userId
    ]);

    if ($hasExistingPassword) {
        flash('password', 'Password updated successfully.', 'success');
    } else {
        flash('password', 'Password created successfully! You can now sign in with either Google or your email and password.', 'success');
    }

} catch (PDOException $e) {
    error_log('[update_password.php] Error: ' . $e->getMessage());
    flash('password', 'A database error occurred. Please try again.', 'error');
}

redirect(url_for('profile.php'));
