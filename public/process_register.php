<?php
/**
 * ==========================================================================
 * public/process_register.php — Secure Registration Form Processor
 * ==========================================================================
 * Validates signup parameters, enforces unique constraints for email/phone,
 * hashes passwords, inserts customer records, and performs auto-login.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail();

// Enforce login rate limit
check_rate_limit('registration', 5, 60, false);

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$fullName = trim(input('full_name', ''));
$email = trim(input('email', ''));
$phone = trim(input('phone', ''));
$password = input('password', '');
$passwordConfirm = input('password_confirm', '');

// --------------------------------------------------------------------------
// 1. Initial Input Validations
// --------------------------------------------------------------------------
$v = new Validator();
$v->required('full_name', $fullName, 'Full name is required.')
  ->length('full_name', $fullName, 2, 100, 'Name must be between 2 and 100 characters.')
  ->required('email', $email, 'Email address is required.')
  ->email('email', $email)
  ->required('phone', $phone, 'Phone number is required.')
  ->phone('phone', $phone)
  ->required('password', $password, 'Password is required.')
  ->custom('password', validate_password_strength($password), 'Password must be at least 8 characters long and contain at least one letter and one number.')
  ->required('password_confirm', $passwordConfirm, 'Please confirm your password.')
  ->custom('password_confirm', $password === $passwordConfirm, 'Password confirmation does not match.');

if ($v->hasErrors()) {
    flash('auth', $v->first(), 'error');
    set_old_input($_POST);
    redirect(url_for('register.php'));
}

// --------------------------------------------------------------------------
// 2. Database Uniqueness Checks (Email & Phone)
// --------------------------------------------------------------------------
try {
    $pdo = db();

    // Check duplicate email
    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $emailCheck->execute(['email' => $email]);
    if ($emailCheck->fetch()) {
        flash('auth', 'An account with this email address already exists.', 'error');
        set_old_input($_POST);
        redirect(url_for('register.php'));
    }

    // Check duplicate phone number
    $phoneCheck = $pdo->prepare('SELECT id FROM users WHERE phone = :phone LIMIT 1');
    $phoneCheck->execute(['phone' => $phone]);
    if ($phoneCheck->fetch()) {
        flash('auth', 'An account with this phone number already exists.', 'error');
        set_old_input($_POST);
        redirect(url_for('register.php'));
    }

    // --------------------------------------------------------------------------
    // 3. Save New Customer Account (role_id = 2)
    // --------------------------------------------------------------------------
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare('
        INSERT INTO users (role_id, full_name, email, phone, password, is_verified, is_active)
        VALUES (2, :name, :email, :phone, :password, 0, 1)
    ');
    $insertStmt->execute([
        'name'     => $fullName,
        'email'    => $email,
        'phone'    => $phone,
        'password' => $hashedPassword
    ]);

    $userId = (int) $pdo->lastInsertId();

    // Fetch newly created user record for login
    $userQuery = $pdo->prepare('SELECT id, role_id, full_name, email, is_active FROM users WHERE id = :id LIMIT 1');
    $userQuery->execute(['id' => $userId]);
    $user = $userQuery->fetch();

    if ($user) {
        // Auto-login
        login_user($user);
    }

    flash('auth', 'Registration successful! Welcome to Grocery Store.', 'success');
    clear_old_input();

    // Redirect to checkout if they came from checkout funnel, otherwise dashboard
    $intendedUrl = $_SESSION['intended_url'] ?? null;
    if ($intendedUrl !== null) {
        unset($_SESSION['intended_url']);
        redirect($intendedUrl);
    }

    redirect(url_for('account.php'));

} catch (PDOException $e) {
    error_log('[process_register.php] Error: ' . $e->getMessage());
    flash('auth', 'An error occurred during registration. Please try again.', 'error');
    set_old_input($_POST);
    redirect(url_for('register.php'));
}
