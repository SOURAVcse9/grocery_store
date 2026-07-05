<?php
/**
 * ==========================================================================
 * public/update_password.php — Password POST Action Processor
 * ==========================================================================
 * Verifies current passwords, validates new strength properties, hashes,
 * updates customer records, and rotates session tokens.
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

$currentPassword = input('current_password', '');
$newPassword = input('new_password', '');
$confirmPassword = input('confirm_password', '');

// --------------------------------------------------------------------------
// 1. Inputs validation
// --------------------------------------------------------------------------
$v = new Validator();
$v->required('current_password', $currentPassword, 'Current password is required.')
  ->required('new_password', $newPassword, 'New password is required.')
  ->custom('new_password', validate_password_strength($newPassword), 'New password must be at least 8 characters long and contain at least one letter and one number.')
  ->required('confirm_password', $confirmPassword, 'Please confirm your new password.')
  ->custom('confirm_password', $newPassword === $confirmPassword, 'Passwords do not match.');

if ($v->hasErrors()) {
    flash('password', $v->first(), 'error');
    redirect(url_for('profile.php'));
}

try {
    $pdo = db();

    // --------------------------------------------------------------------------
    // 2. Verify Current Password Match
    // --------------------------------------------------------------------------
    $passQuery = $pdo->prepare('SELECT password FROM users WHERE id = :uid LIMIT 1');
    $passQuery->execute(['uid' => $userId]);
    $dbHash = $passQuery->fetchColumn();

    if ($dbHash === false || !password_verify($currentPassword, $dbHash)) {
        flash('password', 'Current password is incorrect.', 'error');
        redirect(url_for('profile.php'));
    }

    // --------------------------------------------------------------------------
    // 3. Update Password
    // --------------------------------------------------------------------------
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare('UPDATE users SET password = :pass, remember_token = NULL, updated_at = NOW() WHERE id = :uid');
    $updateStmt->execute([
        'pass' => $newHash,
        'uid'  => $userId
    ]);

    // Force clear remember cookie
    setcookie('remember_me', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);

    flash('password', 'Password updated successfully.', 'success');

} catch (PDOException $e) {
    error_log('[update_password.php] Error: ' . $e->getMessage());
    flash('password', 'A database error occurred. Please try again.', 'error');
}

redirect(url_for('profile.php'));
