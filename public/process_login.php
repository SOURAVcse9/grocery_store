<?php
/**
 * ==========================================================================
 * public/process_login.php — Secure Login Form Processor
 * ==========================================================================
 * Verifies credentials, manages attempt counters, handles remember me
 * database cookie locks, and routes users to their dashboard or checkout.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail();

// Enforce login rate limit
check_rate_limit('login', 10, 60, false);

// Redirect if already logged in
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

// --------------------------------------------------------------------------
// 1. Rate Limiting Protection
// --------------------------------------------------------------------------
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if (isset($_SESSION['login_throttle_time']) && $_SESSION['login_throttle_time'] > time()) {
    $timeLeft = $_SESSION['login_throttle_time'] - time();
    flash('auth', "Too many login attempts. Please wait {$timeLeft} seconds.", 'error');
    redirect(url_for('login.php'));
}

$email = trim(input('email', ''));
$password = input('password', '');
$remember = input('remember', '') === '1';

// --------------------------------------------------------------------------
// 2. Input Validation
// --------------------------------------------------------------------------
$v = new Validator();
$v->required('email', $email, 'Email address is required.')
  ->email('email', $email)
  ->required('password', $password, 'Password is required.');

if ($v->hasErrors()) {
    flash('auth', $v->first(), 'error');
    set_old_input($_POST);
    redirect(url_for('login.php'));
}

// --------------------------------------------------------------------------
// 3. Authenticate Credentials
// --------------------------------------------------------------------------
$user = attempt_login($email, $password);

if ($user === false) {
    // Increment attempts
    $_SESSION['login_attempts']++;

    // Lockout after 5 failed attempts
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_throttle_time'] = time() + 60; // 60 seconds lockout
        $_SESSION['login_attempts'] = 0;
        flash('auth', 'Too many invalid attempts. Access throttled for 60 seconds.', 'error');
    } else {
        $remaining = 5 - $_SESSION['login_attempts'];
        flash('auth', "Invalid email or password. {$remaining} attempts remaining.", 'error');
    }

    set_old_input(['email' => $email]);
    redirect(url_for('login.php'));
}

// Success: Reset rate limiting counters
unset($_SESSION['login_attempts']);
unset($_SESSION['login_throttle_time']);

// Establish session
login_user($user);

// Handle Remember Me cookie
if ($remember) {
    handle_remember_me_cookie((int) $user['id']);
}

// Flush success message
flash('auth', 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!', 'success');

// Redirect to intended URL (e.g. checkout.php) or dashboard
$intendedUrl = $_SESSION['intended_url'] ?? null;
if ($intendedUrl !== null) {
    unset($_SESSION['intended_url']);
    redirect($intendedUrl);
}

redirect(url_for('account.php'));
