<?php
/**
 * ==========================================================================
 * public/auth/google-callback.php — Google OAuth 2.0 Callback Handler
 * ==========================================================================
 * Receives the authorization code and state from Google, performs server-side
 * claims verification, links/creates the customer account, establishes the
 * authenticated customer session, and redirects to the intended destination.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';
require_once __DIR__ . '/../includes/google_auth.php';

// If already logged in, redirect to account dashboard
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

// 1. Check for OAuth errors / cancellations returned by Google
$googleError = input('error', '', 'get');
if (!empty($googleError)) {
    error_log("[google-callback] Google OAuth error parameter: {$googleError}");
    if ($googleError === 'access_denied') {
        flash('auth', 'Google sign-in was cancelled.', 'info');
    } else {
        flash('auth', 'Unable to sign in with Google. Please try again.', 'error');
    }
    redirect(url_for('login.php'));
}

// 2. Read authorization code and CSRF state parameter
$code = input('code', '', 'get');
$state = input('state', '', 'get');

if (empty($code) || empty($state)) {
    flash('auth', 'Unable to sign in with Google. Please try again.', 'error');
    redirect(url_for('login.php'));
}

// 3. Verify CSRF OAuth state token (Defense against login CSRF / state replay)
if (!verify_google_oauth_state($state)) {
    error_log('[google-callback] Invalid or expired OAuth state parameter.');
    flash('auth', 'Your login session expired. Please click Continue with Google again.', 'error');
    redirect(url_for('login.php'));
}

try {
    // 4. Server-to-server authorization code exchange and token claims verification
    $googleClaims = exchange_google_code_for_user($code);

    // 5. Synchronize Google identity with permanent internal customer ID (users.id)
    $customer = sync_google_customer($googleClaims);

    // 6. Establish secure customer session and merge guest cart/wishlist
    login_user($customer);

    flash('auth', 'Welcome back, ' . htmlspecialchars($customer['full_name'] ?? 'Customer') . '!', 'success');

    // 7. Validate and redirect to intended URL (Open Redirect protection)
    $intendedUrl = $_SESSION['intended_url'] ?? null;
    unset($_SESSION['intended_url']);

    if ($intendedUrl && is_string($intendedUrl) && str_starts_with($intendedUrl, '/') && !str_starts_with($intendedUrl, '//')) {
        redirect($intendedUrl);
    } else {
        redirect(url_for('account.php'));
    }

} catch (RuntimeException $e) {
    error_log('[google-callback] Authentication failed: ' . $e->getMessage());

    $msg = $e->getMessage();
    if (str_contains(strtolower($msg), 'administrator') || str_contains(strtolower($msg), 'admin')) {
        flash('auth', 'This email belongs to an administrator account. Please log in via the Admin Panel.', 'error');
    } elseif (str_contains($msg, 'unverified') || str_contains($msg, 'verified email')) {
        flash('auth', 'Your Google account email could not be verified.', 'error');
    } elseif (str_contains($msg, 'suspended') || str_contains($msg, 'deactivated') || str_contains($msg, 'banned')) {
        flash('auth', 'Your account is currently unavailable. Please contact support.', 'error');
    } else {
        flash('auth', 'Unable to sign in with Google. Please try again.', 'error');
    }

    redirect(url_for('login.php'));
} catch (Throwable $e) {
    error_log('[google-callback] Unexpected error during Google auth: ' . $e->getMessage());
    flash('auth', 'Unable to sign in with Google. Please try again.', 'error');
    redirect(url_for('login.php'));
}
